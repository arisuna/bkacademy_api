<?php

namespace Reloday\Gms\Controllers\API;

use Aws\Result;
use Elasticsearch\Endpoints\Cat\Help;
use Phalcon\Security\Random;
use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\CloudWatchHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayRecurrentExpenseHelper;
use Reloday\Application\Lib\StepMachineHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\RecurrentExpenseConfigExt;
use Reloday\Gms\Models\CompanySetting;
use Reloday\Gms\Models\Expense;
use Reloday\Gms\Models\RecurrentExpenseConfig;
use Reloday\Gms\Models\ExpenseCategory;
use Reloday\Gms\Models\ModuleModel;
use \Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class RecurrentExpenseController extends BaseController
{

    /**
     * @Route("/recurrent-expense", paths={module="gms"}, methods={"GET"}
     */
    public function getListRecurrentExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'recurrent_expense');

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $params['assignees'] = [];
        $params['categories'] = [];
        $params['status'] = [];
        $assignees = Helpers::__getRequestValue('assignees');
        if (is_array($assignees) && count($assignees) > 0) {
            foreach ($assignees as $assignee) {
                $params['assignees'][] = $assignee->id;
            }
        }
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $params['categories'][] = $category->id;
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $statuses = Helpers::__getRequestValue('status');
        if (is_array($statuses) && count($statuses) > 0) {
            foreach ($statuses as $status) {
                $params['status'][] = $status->value;
            }
        }
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['currency'] = Helpers::__getRequestValue('currency');

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        $recurrent_expense_configs = RecurrentExpenseConfig::__findWithFilter($params, $ordersConfig);

        if (!$recurrent_expense_configs["success"]) {
            $this->response->setJsonContent([
                'success' => false,
                'params' => $params,
                'queryBuider' => $recurrent_expense_configs
            ]);
        } else {
            $this->response->setJsonContent([
                'success' => true,
                'data' => $recurrent_expense_configs["data"],
                'params' => $params,
                'queryBuider' => $recurrent_expense_configs
            ]);
        }
        $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createRecurrentExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('update', 'recurrent_expense');

        $data = Helpers::__getRequestValuesArray();
        $data['company_id'] = ModuleModel::$company->getId();
        $recurrence_number = Helpers::__getRequestValue("recurrence_number");
        $recurrence_unit = Helpers::__getRequestValue("recurrence_unit");
        $start_date = Helpers::__getRequestValue("start_date");
        $relocationInput = Helpers::__getRequestValueAsArray('relocation');

        $recurrent_expense_config = new RecurrentExpenseConfig();
        if (isset($relocationInput['id']) && Helpers::__isValidId($relocationInput['id'])) {
            $relocation = Relocation::findFirstById($relocationInput['id']);
            if ($relocation instanceof Relocation && $relocation->belongsToGms() && $relocation->checkMyViewPermission()) {
                $data['relocation_id'] = $relocation->getId();
                if (isset($data['relocation_service_company_id'])) {
                    $relocation_service_company = RelocationServiceCompany::findFirstById($data['relocation_service_company_id']);
                    if ($relocation_service_company->getStatus() != RelocationServiceCompany::STATUS_ACTIVE) {
                        $result = [
                            "success" => false,
                            "message" => "SAVE_RECURRENT_EXPENSE_FAIL_TEXT",
                            "detail" => "relocation service company is not activated"
                        ];
                        goto end_of_function;
                    }
                }
            }
        }

        $recurrent_expense_config->setData($data);
        $recurrent_expense_config->setUuid(Helpers::__uuid());

        // if start_date <= now + 3600 then next_date = now + 1 hour; else next_date = start_date;
        $recurence_time_company_value = ModuleModel::$company->getRecurrentExpenseTimeValueOffset();

        if ($recurence_time_company_value >= 0) {

            $now = time();

            $real_start_date = $recurrent_expense_config->generateRealStartDate();

            if ($real_start_date <= $now) {

                $result['message'] = 'START_DATE_SHOULD_BE_NEXT_DAY_TEXT';
                $result['success'] = false;
                goto end_of_function;

            }

            if (!$recurrence_number > 0) {
                $recurrence_number = 1;
            }
            $recurrent_expense_config->setRecurrenceNumber($recurrence_number);
            $next_date = null;
            $recurrent_expense_config->setNextDate($real_start_date);
            if (!$recurrent_expense_config->getRecurrenceUnit() > 0) {
                $recurrent_expense_config->setRecurrenceUnit(1);
            }

            $stepMachineConfig = [
                'uuid' => $recurrent_expense_config->getUuid(),
                'ttl' => $recurrent_expense_config->getNextDate(),
                'duration' => intval(($recurrent_expense_config->getNextDate() - $now)),
                'ruleArn' => getenv('STEP_MACHINE_EXECUTION_RECURRENT_EXPENSE_ARN'),
                'arn' => getenv('STEP_MACHINE_RECURRENT_EXPENSE_ARN'),
            ];
            $stepMachineResult = StepMachineHelper::__quickStart($stepMachineConfig);

            if ($stepMachineResult['success'] == true) {
                $recurrent_expense_config->setRuleArn($stepMachineResult['executionArn']);
            }

            if ($stepMachineResult['success'] == false) {
                //stepMachine
                $result = $stepMachineResult;
                $result['message'] = 'SAVE_RECURRENT_EXPENSE_FAIL_TEXT';
                $result['$stepMachineConfig'] = $stepMachineConfig;
                goto end_of_function;
            }

            $result = $recurrent_expense_config->__quickCreate();

            if ($result['success'] == false) {
                $result['message'] = 'SAVE_RECURRENT_EXPENSE_FAIL_TEXT';
                $result['result'] = $result;
                $result['$stepMachineConfig'] = $stepMachineConfig;
                goto end_of_function;
            }

            $result = [
                "success" => true,
                "data" => $recurrent_expense_config,
                "message" => 'SAVE_RECURRENT_EXPENSE_SUCCESS_TEXT',
                "stepMachineResult" => $stepMachineResult
            ];

            $result['$stepMachineConfig'] = $stepMachineConfig;

        } else {
            $result = [
                "success" => false,
                "message" => "YOU_NEED_UPDATE_GENERATE_TIME_TEXT",
                "detail" => "you do not setup the time to generate expense"
            ];
            goto end_of_function;
        }


        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeRecurrentExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAcl('delete', 'recurrent_expense');

        $return = [
            'success' => false,
            'message' => 'RECURRENT_EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $recurrent_expense_config = RecurrentExpenseConfig::findFirstByUuid($uuid);
            if ($recurrent_expense_config instanceof RecurrentExpenseConfig && $recurrent_expense_config->belongsToGms()) {
                if (!$recurrent_expense_config->isDeletable()) {
                    $return = [
                        'success' => false,
                        'message' => 'CAN_NOT_DELETE_A_GENERATED_RECURRENT_EXPENSE_TEXT'
                    ];
                    goto end_of_function;
                }
                if ($recurrent_expense_config->getRuleArn() != '') {
                    StepMachineHelper::__quickStop($recurrent_expense_config->getRuleArn());
                }
                $return = $recurrent_expense_config->__quickRemove();
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function detailRecurrentExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAcl('index', 'recurrent_expense');
        $result = [
            'success' => false,
            'message' => 'RECURRENT_EXPENSE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $recurrent_expense_config = RecurrentExpenseConfig::findFirstByUuid($uuid);

            if ($recurrent_expense_config instanceof RecurrentExpenseConfig && $recurrent_expense_config->belongsToGms()) {
                $item = $recurrent_expense_config->toArray();
                if ($recurrent_expense_config->getRelocation() instanceof Relocation) {
                    $item["relocation"] = $recurrent_expense_config->getRelocation()->toArray();
                    $item["relocation"]["number"] = $recurrent_expense_config->getRelocation()->getName();
                    $item["relocation"]["employee_name"] = $recurrent_expense_config->getRelocation()->getEmployee()->getFullname();
                    $item["relocation"]["employee_uuid"] = $recurrent_expense_config->getRelocation()->getEmployee()->getUuid();
                }
                $item["relocation_service_company_id"] = (int)$recurrent_expense_config->getRelocationServiceCompanyId();
                $item["service_provider_id"] = (int)$recurrent_expense_config->getServiceProviderId();
                $item["related_expenses"] = 0;
                $item["start_date"] = intval($recurrent_expense_config->getStartDate());
                $item["next_date"] = intval($recurrent_expense_config->getNextDate());
                $item["last_date"] = intval($recurrent_expense_config->getLastDate());
                $generated_expenses = Expense::find([
                    "conditions" => "recurrent_expense_config_id = :recurrent_expense_config_id:",
                    "bind" => [
                        "recurrent_expense_config_id" => $recurrent_expense_config->getId(),
                    ]
                ]);
                $generated_expenses_array = [];
                if (count($generated_expenses) > 0) {
                    foreach ($generated_expenses as $generated_expense) {
                        $expense = $generated_expense->toArray();
                        $expense["expense_category_name"] = $generated_expense->getExpenseCategory()->getName();
                        $generated_expenses_array[] = $expense;
                    }
                }
                $item["related_expenses"] = count($generated_expenses);
                $item["related_expenses_list"] = $generated_expenses_array;
                $item['is_editable'] = $recurrent_expense_config->isEditable();
                $item['rule_arn'] = $recurrent_expense_config->getRuleArn();
                $result = [
                    'success' => true,
                    'data' => $item
                ];
            }
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Save service action
     */
    public function editRecurrentExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('update', 'recurrent_expense');

        $uuid = Helpers::__getRequestValue("uuid");
        $data = Helpers::__getRequestValuesArray();
        $data['company_id'] = ModuleModel::$company->getId();
        $recurrence_number = Helpers::__getRequestValue("recurrence_number");
        $recurrence_unit = Helpers::__getRequestValue("recurrence_unit");
        $start_date = Helpers::__getRequestValue("start_date");

        $result = [
            'success' => false,
            'message' => 'RECURRENT_EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $recurrent_expense_config = RecurrentExpenseConfig::findFirstByUuid($uuid);

            if ($recurrent_expense_config instanceof RecurrentExpenseConfig && $recurrent_expense_config->belongsToGms()) {

                /** add condition of editable recurrent expense */
                if ($recurrent_expense_config->isEditable() == false) {
                    $result = [
                        'success' => false,
                        'message' => 'CAN_NOT_EDIT_RECURRENT_EXPENSE_TEXT'
                    ];
                    goto end_of_function;
                }

                if (isset($data['relocation'])) {
                    $relocation = Relocation::findFirstById($data['relocation']['id']);
                    if ($relocation instanceof Relocation && $relocation->belongsToGms() && $relocation->checkMyViewPermission()) {
                        $data['relocation_id'] = $relocation->getId();

                        if (isset($data['relocation_service_company_id'])) {
                            $relocation_service_company = RelocationServiceCompany::findFirstById($data['relocation_service_company_id']);
                            if ($relocation_service_company instanceof RelocationServiceCompany && $relocation_service_company->getStatus() != RelocationServiceCompany::STATUS_ACTIVE) {
                                $result = [
                                    "success" => false,
                                    "message" => "SAVE_RECURRENT_EXPENSE_FAIL_TEXT",
                                    "detail" => "relocation service company is not activated"
                                ];
                                goto end_of_function;
                            }
                        }
                    }
                }


                if ($recurrent_expense_config->getStartDate() != $start_date && $recurrent_expense_config->getLastDate() > 0) {
                    $result = [
                        "success" => false,
                        "message" => "CAN_NOT_CHANGE_START_DATE_OF_EXPENSE_TEXT",
                        "detail" => "can not change start date with started recurrent expense"
                    ];
                    goto end_of_function;
                }

                $recurrent_expense_config->setData($data);
                $recurrent_expense_config->setIsPayable(Helpers::__getRequestValue("is_payable"));
                // if start_date <= now + 3600 then next_date = now + 1 hour; else next_date = start_date;

                $recurence_time_company_value = ModuleModel::$company->getRecurrentExpenseTimeValueOffset();

                if ($recurence_time_company_value >= 0) {
                    $now = time();
                    $real_start_date = $recurrent_expense_config->generateRealStartDate();

                    if ($real_start_date <= $now) {
                        $result['message'] = 'START_DATE_SHOULD_BE_NEXT_DAY_TEXT';
                        $result['success'] = false;
                        goto end_of_function;
                    }

                    $recurrent_expense_config->setNextDate($real_start_date);

                    if ($recurrent_expense_config->isNextDateChanged()) {
                        $stepMachineResult = StepMachineHelper::__quickStart([
                            'uuid' => $recurrent_expense_config->getUuid(),
                            'ttl' => $recurrent_expense_config->getNextDate(),
                            'duration' => intval(($recurrent_expense_config->getNextDate() - $now)),
                            'ruleArn' => getenv('STEP_MACHINE_EXECUTION_RECURRENT_EXPENSE_ARN'),
                            'arn' => getenv('STEP_MACHINE_RECURRENT_EXPENSE_ARN'),
                        ]);

                        if ($stepMachineResult['success'] == true) {
                            $recurrent_expense_config->setRuleArn($stepMachineResult['executionArn']);
                        }
                    }

                    if (!$recurrent_expense_config->getRecurrenceUnit() > 0) {
                        $recurrent_expense_config->setRecurrenceUnit(1);
                    }

                    $result = $recurrent_expense_config->__quickUpdate();
                    if ($result['success'] == false) {
                        $result['message'] = 'SAVE_RECURRENT_EXPENSE_FAIL_TEXT';
                        $result['result'] = $result;
                        goto end_of_function;
                    }

//                    $reloday_recurrent_helper = new RelodayRecurrentExpenseHelper();
//                    $reloday_recurrent_helper->setUuid($recurrent_expense_config->getUuid());
//                    $reloday_recurrent_helper->setTtl($recurrent_expense_config->getNextDate());
//                    $reloday_recurrent_helper->setDuration($recurrent_expense_config->getNextDate() - $now);
//                    $reloday_recurrent_helper->setCompanyId($recurrent_expense_config->getCompanyId());
//                    $create_in_dynamo = $reloday_recurrent_helper->updateInDynamoDb();
//                    if( $create_in_dynamo['success'] == false ) {
//                        $result['success'] = false;
//                        $result['message'] = 'SAVE_RECURRENT_EXPENSE_FAIL_TEXT';
//                        $result['create_in_dynamo'] = $create_in_dynamo;
//                        goto end_of_function;
//                    }
                    $result = [
                        "success" => true,
                        "data" => $recurrent_expense_config,
                        "message" => 'SAVE_RECURRENT_EXPENSE_SUCCESS_TEXT',
                        "stepMachine" => isset($stepMachineResult) ? $stepMachineResult : null
                    ];

                } else {
                    $result = [
                        "success" => false,
                        "message" => "YOU_NEED_UPDATE_GENERATE_TIME_TEXT",
                        "detail" => "you do not setup the time to generate expense"
                    ];
                    goto end_of_function;
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function stopRecurrentExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('update', 'recurrent_expense');
        $result = [
            'success' => false,
            'message' => 'RECURRENT_EXPENSE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $recurrent_expense_config = RecurrentExpenseConfig::findFirstByUuid($uuid);

            if ($recurrent_expense_config instanceof RecurrentExpenseConfig && $recurrent_expense_config->belongsToGms()) {
                $recurrent_expense_config->setStatus(RecurrentExpenseConfig::STATUS_TERMINATED);

                $result = $recurrent_expense_config->__quickUpdate();
                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_RECURRENT_EXPENSE_FAIL_TEXT';
                } else $result['message'] = 'SAVE_RECURRENT_EXPENSE_SUCCESS_TEXT';

            }
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }
}
