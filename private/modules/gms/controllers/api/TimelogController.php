<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\ExpenseCategoryExt;
use Reloday\Gms\Models\ExpenseCategory;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\Timelog;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserProfile;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TimelogController extends BaseController
{

    /**
     * @Route("/timelog", paths={module="gms"}, methods={"GET"}
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $params['categories'] = [];
        $params['relocations'] = [];
        $params['services'] = [];
        $params['user_profiles'] = [];
        $params['exclude_items'] = [];
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $params['categories'][] = $category->id;
            }
        }
        $relocations = Helpers::__getRequestValue('relocations');
        if (is_array($relocations) && count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $params['relocations'][] = $relocation->id;
            }
        }
        $services = Helpers::__getRequestValueAsArray('services');
        if (is_array($services) && count($services) > 0) {
            foreach ($services as $service) {
                $params['services'][] = $service['id'];
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        if (ModuleModel::$user_profile->isAdminOrManager()) {
            $user_profiles = Helpers::__getRequestValue('user_profiles');
            if (is_array($user_profiles) && count($user_profiles) > 0) {
                foreach ($user_profiles as $user_profile) {
                    $params['user_profiles'][] = $user_profile->id;
                }
            }
        } else {
            $params['user_profiles'][] = ModuleModel::$user_profile->getId();
        }
        $params['date'] = Helpers::__getRequestValue('date');

        if (is_object($params['date'])) {
            $params['date'] = (array)$params['date'];
        }

        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        $logs = Timelog::__findWithFilter($params, $ordersConfig);

        if (!$logs["success"]) {
            $this->response->setJsonContent([
                'success' => false,
                'params' => $params,
                'queryBuider' => $logs
            ]);
        } else {
            $data = $logs['data']->toArray();
            foreach ($data as $key => $log) {
                $data[$key]['unit'] = (int)$log['expense_category_unit'];
            }

            $this->response->setJsonContent([
                'success' => true,
                'data' => $data,
                'params' => $params,
                'queryBuider' => $logs
            ]);
        }
        $this->response->send();
    }

    /**
     * @Route("/timelog", paths={module="gms"}, methods={"GET"}
     */
    public function getListReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $params['categories'] = [];
        $params['relocations'] = [];
        $options['services'] = [];
        $params['user_profiles'] = [];
        $params['exclude_items'] = [];
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $params['categories'][] = $category->id;
            }
        }

        $exclude_items = Helpers::__getRequestValue('exclude_items');
        if (is_array($exclude_items) && count($exclude_items) > 0) {
            foreach ($exclude_items as $exclude_item) {
                $params['exclude_items'][] = $exclude_item;
            }
        }

        $relocations = Helpers::__getRequestValue('relocations');
        if (is_array($relocations) && count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $params['relocations'][] = $relocation->id;
            }
        }
        $services = Helpers::__getRequestValueAsArray('services');
        if (is_array($services) && count($services) > 0) {
            foreach ($services as $service) {
                $params['services'][] = $service['id'];
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        if (ModuleModel::$user_profile->isAdminOrManager()) {
            $user_profiles = Helpers::__getRequestValue('user_profiles');
            if (is_array($user_profiles) && count($user_profiles) > 0) {
                foreach ($user_profiles as $user_profile) {
                    $params['user_profiles'][] = $user_profile->id;
                }
            }
        } else {
            $params['user_profiles'][] = ModuleModel::$user_profile->getId();
        }
        $params['date'] = Helpers::__getRequestValue('date');

        if (is_object($params['date'])) {
            $params['date'] = (array)$params['date'];
        }

        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        $params['page'] = 1;
        $logs = Timelog::__findWithFilter($params, $ordersConfig);

        if (!$logs["success"]) {
            $result = [
                'success' => false,
                'params' => $params,
                'queryBuider' => $logs
            ];
            goto end_of_function;
        }

        $data = [];

        $data[] = $logs['data'];

        if ($logs['total_pages'] > 1) {
            for ($i = 2; $i <= $logs['total_pages']; $i++) {
                $params['page'] = $i;
                $logs = Timelog::__findWithFilter($params, $ordersConfig);

                if (!$logs["success"]) {
                    $result = [
                        'success' => false,
                        'params' => $params,
                        'queryBuider' => $logs
                    ];
                    goto end_of_function;
                }
                $data[] = $logs['data'];
            }
        }

        $result = [
            'success' => true,
            'data' => $data,
            'params' => $params,
            'queryBuider' => $logs
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @Route("/timelog", paths={module="gms"}, methods={"GET"}
     */
    public function getListtimelogByObjectAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['task_uuid'] = Helpers::__getRequestValue('task_uuid');
        $params['relocation_uuid'] = Helpers::__getRequestValue('relocation_uuid');
        $params['relocation_service_company_uuid'] = Helpers::__getRequestValue('relocation_service_company_uuid');
        $total_hour = 0;
        $total_minute = 0;
        $logs = [];

        if ($params['task_uuid'] != "" && Helpers::__isValidUuid($params['task_uuid'])) {
            $logs = Timelog::find([
                "conditions" => "task_uuid = :uuid: and is_deleted = 0",
                "bind" => [
                    "uuid" => $params['task_uuid']
                ]
            ]);

            $task = Task::findFirstByUuid($params['task_uuid']);
            if (!$task instanceof Task) {
                $result = [
                    'success' => false,
                    'data' => "TASK_NOT_FOUND_TEXT"
                ];
                goto end_of_function;
            }

            $total_hour = Timelog::sum([
                "column" => "hour_spent",
                "conditions" => "task_uuid = :uuid: and is_deleted = 0",
                "bind" => [
                    "uuid" => $params['task_uuid']
                ]
            ]);
            $total_minute = Timelog::sum([
                "column" => "minute_spent",
                "conditions" => "task_uuid = :uuid: and is_deleted = 0",
                "bind" => [
                    "uuid" => $params['task_uuid']
                ]
            ]);
        } else if ($params['relocation_uuid'] != "" && Helpers::__isValidUuid($params['relocation_uuid'])) {
            $relocation = Relocation::findFirstByUuid($params['relocation_uuid']);
            if (!$relocation instanceof Relocation) {
                $result = [
                    'success' => false,
                    'data' => "RELOCATION_NOT_FOUND_TEXT"
                ];
                goto end_of_function;
            }
            $logs = Timelog::find([
                "conditions" => "relocation_id = :relocation_id: and is_deleted = 0",
                "bind" => [
                    "relocation_id" => $relocation->getId()
                ]
            ]);

            $total_hour = Timelog::sum([
                "column" => "hour_spent",
                "conditions" => "relocation_id = :relocation_id: and is_deleted = 0",
                "bind" => [
                    "relocation_id" => $relocation->getId()
                ]
            ]);
            $total_minute = Timelog::sum([
                "column" => "minute_spent",
                "conditions" => "relocation_id = :relocation_id: and is_deleted = 0",
                "bind" => [
                    "relocation_id" => $relocation->getId()
                ]
            ]);
        } else if ($params['relocation_service_company_uuid'] != "" && Helpers::__isValidUuid($params['relocation_service_company_uuid'])) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($params['relocation_service_company_uuid']);
            if (!$relocation_service_company instanceof RelocationServiceCompany) {
                $result = [
                    'success' => false,
                    'data' => "SERVICE_NOT_FOUND_TEXT"
                ];
                goto end_of_function;
            }
            $logs = Timelog::find([
                "conditions" => "relocation_service_company_id = :relocation_service_company_id: and is_deleted = 0",
                "bind" => [
                    "relocation_service_company_id" => $relocation_service_company->getId()
                ]
            ]);

            $total_hour = Timelog::sum([
                "column" => "hour_spent",
                "conditions" => "relocation_service_company_id = :relocation_service_company_id: and is_deleted = 0",
                "bind" => [
                    "relocation_service_company_id" => $relocation_service_company->getId()
                ]
            ]);
            $total_minute = Timelog::sum([
                "column" => "minute_spent",
                "conditions" => "relocation_service_company_id = :relocation_service_company_id: and is_deleted = 0",
                "bind" => [
                    "relocation_service_company_id" => $relocation_service_company->getId()
                ]
            ]);
        }
        $chartData = [];
        if (count($logs) > 0) {

            $total_time = $total_hour * 60 + $total_minute;
            $total_hour = intdiv($total_time, 60);
            $total_minute = $total_time % 60;

            foreach ($logs as $log) {
                if (array_key_exists($log->getUserProfile()->getUuid(), $chartData)) {
                    $chartData[$log->getUserProfile()->getUuid()]['hour_spent'] += $log->getHourSpent();
                    $chartData[$log->getUserProfile()->getUuid()]['minute_spent'] += $log->getMinuteSpent();
                    $chartData[$log->getUserProfile()->getUuid()]['total_time'] += $log->getHourSpent() * 60 + $log->getMinuteSpent();
                } else {
                    $chartData[$log->getUserProfile()->getUuid()]['hour_spent'] = $log->getHourSpent();
                    $chartData[$log->getUserProfile()->getUuid()]['minute_spent'] = $log->getMinuteSpent();
                    $chartData[$log->getUserProfile()->getUuid()]['total_time'] = $log->getHourSpent() * 60 + $log->getMinuteSpent();
                    $chartData[$log->getUserProfile()->getUuid()]['user_name'] = $log->getUserProfile()->getFirstname() . " " . $log->getUserProfile()->getLastname();
                }
            }
        }
        $config = [];
        $config['user_profile'] = ModuleModel::$user_profile;
        if ($params['task_uuid'] != "" && $task instanceof Task) {
            $relocation = $task->getMainRelocation();
            $config['relocation_service_company_id'] = intval($task->getRelocationServiceCompanyId());
        }
        if ($params['relocation_service_company_uuid'] != "" && $relocation_service_company instanceof RelocationServiceCompany) {
            $relocation = $relocation_service_company->getRelocation();
            $config['relocation_service_company_id'] = intval($relocation_service_company->getId());
        }
        $config['relocation'] = isset($relocation) && $relocation ? $relocation->toArray() : null;
        $config['relocation_id'] = isset($relocation) && $relocation ? intval($relocation->getId()) : null;

        if (isset($relocation) && $relocation) {
            if ($relocation->getStatus() == Relocation::STATUS_INITIAL) {
                $config['show'] = false;
            } elseif ($relocation->getStatus() == Relocation::STATUS_ONGOING) {
                $config['show'] = true;
                $config['add_posibility'] = true;
            } else {
                $config['show'] = true;
                $config['add_posibility'] = false;
            }
        }
        /*
        if (isset($relocation) && ) {
            $config['show'] = false;
        } elseif (isset($relocation) && $relocation->getStatus() == Relocation::STATUS_ONGOING) {
            $config['show'] = true;
            $config['add_posibility'] = true;
        } else {
            $config['show'] = true;
            $config['add_posibility'] = false;
        }
        */

        $timelog_array = [];

        if (count($logs) > 0) {
            foreach ($logs as $log) {
                $timelog_array[] = $log->getParsedData();
            }
        }

        $result = [
            'success' => true,
            'data' => $timelog_array,
            'config' => $config,
            'chartData' => $chartData,
            'total_time' => $total_hour . 'h ' . $total_minute . 'm'
        ];

        end_of_function:
        $this->response->setJsonContent($result);

        $this->response->send();
    }

    /**
     * @Route("/timelog", paths={module="gms"}, methods={"GET"}
     */
    public function getStatisticAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $options['query'] = Helpers::__getRequestValue('query');
        $options['companies'] = [];
        $options['categories'] = [];
        $options['relocations'] = [];
        $options['services'] = [];
        $options['user_profiles'] = [];
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $options['categories'][] = $category->id;
            }
        }
        $relocations = Helpers::__getRequestValue('relocations');
        if (is_array($relocations) && count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $options['relocations'][] = $relocation->id;
            }
        }
        $services = Helpers::__getRequestValueAsArray('services');
        if (is_array($services) && count($services) > 0) {
            foreach ($services as $service) {
                $options['services'][] = $service['id'];
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $options['companies'][] = $company->id;
            }
        }

        if (ModuleModel::$user_profile->isAdminOrManager()) {
            $user_profiles = Helpers::__getRequestValue('user_profiles');
            if (is_array($user_profiles) && count($user_profiles) > 0) {
                foreach ($user_profiles as $user_profile) {
                    $options['user_profiles'][] = $user_profile->id;
                }
            }
        } else {
            $options['user_profiles'][] = ModuleModel::$user_profile->getId();
        }
        $options['start_date'] = Helpers::__getRequestValue('start_date');
        $options['end_date'] = Helpers::__getRequestValue('end_date');

        $average_hour = 0;
        $average_minute = 0;
        $total_hour = 0;
        $total_minute = 0;
        $total_user = 0;

        $options['date'] = Helpers::__getRequestValue('date');

        if (is_object($options['date'])) {
            $options['date'] = (array)$options['date'];
        }

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Timelog', 'Timelog');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'sum_hour' => 'SUM(Timelog.hour_spent)',
            'sum_minute' => 'SUM(Timelog.minute_spent)',
            'count' => 'COUNT(Timelog.id)',
            'total_user' => 'COUNT(DISTINCT Timelog.user_profile_id)'
        ]);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = Timelog.relocation_id AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = Timelog.relocation_service_company_id AND RelocationServiceCompany.status != ' . RelocationServiceCompany::STATUS_ARCHIVED, 'RelocationServiceCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = RelocationServiceCompany.service_company_id', 'ServiceCompany');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\UserProfile', 'UserProfile.id = Timelog.user_profile_id ', 'UserProfile');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Employee', 'Employee.id = Relocation.employee_id ', 'Employee');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Account.id = Relocation.hr_company_id ', 'Account');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\ExpenseCategory', 'ExpenseCategory.id = Timelog.expense_category_id ', 'ExpenseCategory');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Expense', 'Expense.timelog_id = Timelog.id ', 'Expense');

        $queryBuilder->where('Timelog.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andWhere('Timelog.is_deleted = 0');

        if (count($options["relocations"]) > 0) {
            $queryBuilder->andwhere('Relocation.id IN ({relocation_ids:array} )', [
                'relocation_ids' => $options["relocations"]
            ]);
        }

        if (count($options["services"]) > 0) {
            $queryBuilder->andwhere('RelocationServiceCompany.service_company_id IN ({service_ids:array} )', [
                'service_ids' => $options["services"]
            ]);
        }

        if (count($options["companies"]) > 0) {
            $queryBuilder->andwhere('Account.id IN ({account_ids:array} )', [
                'account_ids' => $options["companies"]
            ]);
        }

        if (count($options["user_profiles"]) > 0) {
            $queryBuilder->andwhere('Timelog.user_profile_id IN ({user_profile_ids:array} )', [
                'user_profile_ids' => $options["user_profiles"]
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('(Timelog.comment Like :query: or CONCAT(UserProfile.firstname,\' \', UserProfile.lastname) Like :query:)', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (count($options["categories"]) > 0) {
            $queryBuilder->andwhere('Timelog.expense_category_id IN ({categories:array} )', [
                'categories' => $options["categories"]
            ]);
        }

        if ($options["start_date"] != null && $options["start_date"] != "") {
            $queryBuilder->andwhere('Timelog.start_date >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if ($options["end_date"] != null && $options["end_date"] != "") {
            $queryBuilder->andwhere('Timelog.end_date <= :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }

        if (isset($options['date']) && is_array($options['date'])
            && isset($options['date']['startDate']) && Helpers::__isTimeSecond($options['date']['startDate'])
            && isset($options['date']['endDate']) && Helpers::__isTimeSecond($options['date']['endDate'])) {

            $queryBuilder->andwhere("Timelog.start_date >= :start_date_range_begin: AND Timelog.start_date <= :start_date_range_end:", [
                'start_date_range_begin' => date('Y-m-d H:i:s', Helpers::__getStartTimeOfDay($options['date']['startDate'])),
                'start_date_range_end' => date('Y-m-d H:i:s', Helpers::__getEndTimeOfDay($options['date']['endDate'])),
            ]);
        }

        $result = $queryBuilder->getQuery()->execute();
        $number_logged_time = $result[0]['count'];
        $total_hour = $result[0]['sum_hour'];
        $total_minute = $result[0]['sum_minute'];
        $total_user = $result[0]['total_user'];

        $total_time = $total_hour * 60 + $total_minute;
        $total_hour = intdiv($total_time, 60);
        $total_minute = $total_time % 60;
        if ($number_logged_time > 0) {
            $average_time = $total_time / $number_logged_time;
            $average_hour = intdiv($average_time, 60);
            $average_minute = $average_time % 60;
        } else {
            $average_time = 0;
            $average_hour = 0;
            $average_minute = 0;
        }


        $checkAdmin = ModuleModel::$user_login->checkPermission("timelog", "admin");
        if ($checkAdmin["success"] == true) {
//            $queryBuilder->distinct("Timelog.user_profile_id");
//            $queryBuilder->groupBy("Timelog.user_profile_id");
//            $queryBuilder->columns([
//                'Timelog.user_profile_id'
//            ]);
//            $total_user = $queryBuilder->getQuery()->execute();
        }

        $this->response->setJsonContent([
            'success' => true,
            "number_logged_time" => $number_logged_time,
            'average_logged_time' => $average_hour . "h " . $average_minute . "m",
            'total_logged_time' => $total_hour . "h " . $total_minute . "m",
            'total_user' => $total_user,
            'queryBuider' => $queryBuilder->getQuery()->getSql()
        ]);
        $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createTimelogAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $data = Helpers::__getRequestValuesArray();
        $timelog = new Timelog();
        $data['company_id'] = ModuleModel::$company->getId();

        $relocation = Relocation::findFirstById($data['relocation_id']);
        if (!$relocation) {
            $result = [
                "success" => false,
                "message" => "RELOCATION_REQUIRED_TEXT"
            ];
            goto end_of_function;
        }

        if (isset($data['relocation_service_company_id'])) {
            $relocation_service_company = RelocationServiceCompany::findFirstById($data['relocation_service_company_id']);
            if ($relocation_service_company instanceof RelocationServiceCompany && $relocation_service_company->getStatus() != RelocationServiceCompany::STATUS_ACTIVE) {
                $result = [
                    "success" => false,
                    "message" => "SERVICE_STATUS_NOT_STARTED_TEXT",
                    "detail" => "relocation service company is not activated"
                ];
                goto end_of_function;
            }
        }

        $timelog->setData($data);
        $timelog->setRelocationServiceCompanyId(isset($data['relocation_service_company_id']) ? $data['relocation_service_company_id'] : null);

        if ($timelog->getMinuteSpent() == 0 && $timelog->getHourSpent() == 0) {
            $result = [
                "success" => false,
                "message" => "TIME_SPENT_CANNOT_BE_NULL_TEXT",
            ];
            goto end_of_function;
        }

        $result = $timelog->__quickCreate();
        if ($result['success'] == false) {
            $result['message'] = 'SAVE_TIMELOG_FAIL_TEXT';
        } else {
            $result['message'] = 'SAVE_TIMELOG_SUCCESS_TEXT';
            ModuleModel::$timelog = $timelog;
            $this->dispatcher->setParam('return', $result);
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/timelog", paths={module="gms"}, methods={"GET"}, name="gms-needform-index")
     */
    public function detailTimelogAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' => 'TIMELOG_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $timelog = Timelog::findFirstByUuid($uuid);

            if ($timelog instanceof Timelog && $timelog->belongsToGms()) {
                $item = $timelog->toArray();
                $item["user_profile"] = $timelog->getUserProfile();

                $item["relocation"] = $timelog->getRelocation()->toArray();
                $item["relocation"]["number"] = $item["relocation"]["identify"];
                $item["relocation"]["employee_uuid"] = $timelog->getRelocation()->getEmployee()->getUuid();
                $item["relocation"]["employee_name"] = $timelog->getRelocation()->getEmployee()->getFirstname() . ' ' . $timelog->getRelocation()->getEmployee()->getLastname();
                $item["relocation"]["company_name"] = $timelog->getRelocation()->getHrCompany()->getName();
                $item["relocation_service_company"] = $timelog->getRelocationServiceCompany() ? $timelog->getRelocationServiceCompany()->toArray() : null;
                $item["expense"] = $timelog->getExpense() ? $timelog->getExpense()->toArray() : null;
                $item['is_editable'] = $timelog->isEditable();

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
    public function editTimelogAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();

        $uuid = Helpers::__getRequestValue("uuid");
        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'TIMELOG_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $timelog = Timelog::findFirstByUuid($uuid);

            if ($timelog instanceof Timelog && $timelog->belongsToGms()) {

                $relocation = Relocation::findFirstById($data['relocation_id']);
                if (!$relocation) {
                    $result = [
                        "success" => false,
                        "message" => "RELOCATION_REQUIRED_TEXT",
                        "detail" => "relocation is required"
                    ];
                    goto end_of_function;
                }

                $relocation_service_company = RelocationServiceCompany::findFirstById($data['relocation_service_company_id']);
                if ($relocation_service_company instanceof RelocationServiceCompany && $relocation_service_company->getStatus() != RelocationServiceCompany::STATUS_ACTIVE) {
                    $result = [
                        "success" => false,
                        "message" => "SAVE_TIMELOG_FAIL_TEXT",
                        "detail" => "relocation service company is not activated"
                    ];
                    goto end_of_function;
                }


                if ($data['minute_spent'] == null || $data['minute_spent'] == '') {
                    $data['minute_spent'] = 0;
                }

                if ($data['hour_spent'] == null || $data['hour_spent'] == '') {
                    $data['hour_spent'] = 0;
                }

                $timelog->setData($data);
                $timelog->setRelocationServiceCompanyId(isset($data['relocation_service_company_id']) ? $data['relocation_service_company_id'] : null);
                $timelog->setTaskUuid(isset($data['task_uuid']) ? $data['task_uuid'] : null);

                if ($timelog->getMinuteSpent() == 0 && $timelog->getHourSpent() == 0) {
                    $result = [
                        "success" => false,
                        "message" => "TIME_SPENT_CANNOT_BE_NULL_TEXT",
                    ];
                    goto end_of_function;
                }

                $result = $timelog->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_TIMELOG_FAIL_TEXT';
                } else {
                    $result['message'] = 'SAVE_TIMELOG_SUCCESS_TEXT';
                    ModuleModel::$timelog = $timelog;
                    $this->dispatcher->setParam('return', $result);
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
    public function removeTimelogAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'TIMELOG_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $timelog = Timelog::findFirstByUuid($uuid);

            if ($timelog instanceof Timelog && $timelog->belongsToGms()) {

                $return = $timelog->__quickRemove();
            }
        }
        if ($return['success']) {
            ModuleModel::$timelog = $timelog;
            $this->dispatcher->setParam('return', $return);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/timelog", paths={module="gms"}, methods={"GET"}
     */
    public function executeReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $params['categories'] = [];
        $params['relocations'] = [];
        $params['user_profiles'] = [];
        $categories = Helpers::__getRequestValue('categories');
        if (count($categories) > 0) {
            foreach ($categories as $category) {
                $params['categories'][] = $category->id;
            }
        }
        $relocations = Helpers::__getRequestValue('relocations');
        if (count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $params['relocations'][] = $relocation->id;
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $user_profiles = Helpers::__getRequestValue('user_profiles');
        if (count($user_profiles) > 0) {
            foreach ($user_profiles as $user_profile) {
                $params['user_profiles'][] = $user_profile->id;
            }
        }
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        $result = Timelog::getReport($params, $ordersConfig);

        end:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @Route("/timelog", paths={module="gms"}, methods={"GET"}
     */
    public function getReportAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = AthenaHelper::getExecutionInfo($executionId);

        $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");

        $this->response->setJsonContent($result);
        $this->response->send();
    }

}
