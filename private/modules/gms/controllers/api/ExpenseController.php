<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security\Random;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\AccountProductPricing;
use Reloday\Gms\Models\CompanySetting;
use Reloday\Gms\Models\CompanySettingDefault;
use Reloday\Gms\Models\Expense;
use Reloday\Gms\Models\ExpenseCategory;
use Reloday\Gms\Models\FinancialAccount;
use Reloday\Gms\Models\Transaction;
use Reloday\Gms\Models\Payment;
use Reloday\Gms\Models\InvoiceableItem;
use Reloday\Gms\Models\InvoiceQuoteItem;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ModuleModel;
use \Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ProductPricing;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\TaxRule;
use Reloday\Gms\Models\Timelog;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ExpenseController extends BaseController
{

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getExpenseSettingAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        $data = [];

        $data["recurring_expense"] = ModuleModel::$company->getCompanySettingValue('recurring_expense');
        $recurring_expense_time = ModuleModel::$company->getCompanySettingValue('recurring_expense_time');

        if($recurring_expense_time){
            $times =  explode(":", $recurring_expense_time);
            $hourNumber = is_numeric($times[0]) ? intval($times[0]) : 0;
            $minuteNumber = is_numeric($times[1]) ? intval($times[1]) : 0;

            $hour = str_pad($hourNumber, 2, 0, STR_PAD_LEFT);
            $minute = str_pad($minuteNumber, 2, 0, STR_PAD_LEFT);

            $recurring_expense_time = $hour . ':' . $minute;
        }

        $data["recurring_expense_time"] = $recurring_expense_time;
        $data["expense_approval_required"] = ModuleModel::$company->getCompanySettingValue('expense_approval_required');

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data,
        ]);
        $this->response->send();
    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function saveExpenseSettingAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $recurring_expense_time = Helpers::__getRequestValue("recurring_expense_time");
        $expense_approval_require = Helpers::__getRequestValue("expense_approval_required");
        $result['success'] = false;
        $this->db->begin();

        if (Helpers::__existRequestValue('recurring_expense_time')) {
            if ($recurring_expense_time != null && $recurring_expense_time != "") {
                $resultUpdate = ModuleModel::$company->saveCompanySetting(CompanySettingDefault::NAME_RECURRING_EXPENSE_TIME, $recurring_expense_time);
                if ($resultUpdate['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_EXPENSE_SETTING_FAIL_TEXT';
                    $result['errorType'] = 'canNotUpdateRecurringExpenseTime';
                    $result['detail'] = $resultUpdate;
                    goto end_of_function;
                }
            } else {
                $result['success'] = false;
                $result['message'] = 'TIME_SET_CAN_NOT_BE_NULL_TEXT';
                goto end_of_function;
            }
        }


        if (Helpers::__existRequestValue('expense_approval_required')) {
            if (is_bool($expense_approval_require)) {
                $resultUpdate = ModuleModel::$company->saveCompanySetting(CompanySettingDefault::NAME_EXPENSE_APPROVAL_REQUIRE, $expense_approval_require);
                if ($resultUpdate['success'] == false) {
                    $this->db->rollback();
                    $result['errorType'] = 'canNotUpdateRecurringExpenseTime';
                    $result['message'] = 'SAVE_EXPENSE_SETTING_FAIL_TEXT';
                    $result['detail'] = $resultUpdate;
                    goto end_of_function;
                }
            } else {
                $result['success'] = false;
                $result['message'] = 'TIME_SET_CAN_NOT_BE_NULL_TEXT';
                goto end_of_function;
            }
        }

        $this->db->commit();
        $result = ['success' => true, "message" => "SAVE_EXPENSE_SETTING_SUCCESS_TEXT"];


        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();

    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getListCategoryAction()
    {
        $this->view->disable();
        $this->checkAjaxPOST();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $categories = ExpenseCategory::getListOfMyCompany($params);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $categories,
            'total_rest_items' => 0
        ]);
        $this->response->send();

    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getListCategoryActiveAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $categories = ExpenseCategory::getListActiveOfMyCompany();

        $this->response->setJsonContent([
            'success' => true,
            'data' => $categories,
        ]);
        $this->response->send();

    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getListTimelogCategoryAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $categories = ExpenseCategory::getListTimelogCategoryOfMyCompany();

        $this->response->setJsonContent([
            'success' => true,
            'data' => $categories,
        ]);
        $this->response->send();

    }

    /**
     * @return mixed
     */
    public function createCategoryAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $data = Helpers::__getRequestValuesArray();
        $category = new ExpenseCategory();
        $data['company_id'] = ModuleModel::$company->getId();
        $categoryIfExist = ExpenseCategory::findFirst([
            "conditions" => "name = :name: and company_id = :company_id: and is_deleted = 0",
            "bind" => [
                "name" => $data["name"],
                "company_id" => $data["company_id"]
            ]
        ]);
        if ($categoryIfExist instanceof ExpenseCategory) {
            $result['message'] = 'EXPENSE_CATEGORY_NAME_MUST_BE_UNIQUE_TEXT';
            $result['success'] = false;
            goto end_of_function;
        }

        if(isset($data["external_hris_id"]) && $data["external_hris_id"] != null && $data["external_hris_id"]!= ''){

            $categoryCodeIfExist = ExpenseCategory::findFirst([
                "conditions" => "external_hris_id = :external_hris_id: and company_id = :company_id: and is_deleted = 0",
                "bind" => [
                    "external_hris_id" => $data["external_hris_id"],
                    "company_id" => $data["company_id"]
                ]
            ]);
            if ($categoryCodeIfExist instanceof ExpenseCategory) {
                $result['message'] = 'EXPENSE_CATEGORY_CODE_MUST_BE_UNIQUE_TEXT';
                $result['success'] = false;
                goto end_of_function;
            }
        }

        $category->setData($data);
        $result = $category->__quickCreate();
        if ($result['success'] == false) {
            $result['message'] = 'SAVE_EXPENSE_CATEGORY_FAIL_TEXT';
        } else {
            $result['message'] = 'SAVE_EXPENSE_CATEGORY_SUCCESS_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/expense", paths={module="gms"}, methods={"GET"}, name="gms-needform-index")
     */
    public function detailCategoryAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $result = [
            'success' => false,
            'message' => 'EXPENSE_CATEGORY_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $expense_category = ExpenseCategory::findFirstByUuid($uuid);
        } elseif (Helpers::__isValidId($uuid)) {
            $expense_category = ExpenseCategory::findFirstById($uuid);
        }


        if (isset($expense_category) && $expense_category && $expense_category->belongsToGms()) {
            $expense_category_array = $expense_category->toArray();
            $expense_category_array['is_editable'] = $expense_category->isEditable();
            $expense_category_array['unit_name'] = isset(ExpenseCategory::UNIT_NAME[$expense_category->getUnit()]) ? ExpenseCategory::UNIT_NAME[$expense_category->getUnit()] : "";
            $result = [
                'success' => true,
                'data' => $expense_category_array
            ];
        }


        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Save service action
     */
    public function editCategoryAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'EXPENSE_CATEGORY_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense_category = ExpenseCategory::findFirstByUuid($uuid);

            if ($expense_category instanceof ExpenseCategory && $expense_category->belongsToGms()) {

                $expense_category->setData(Helpers::__getRequestValuesArray());
                $categoryIfExist = ExpenseCategory::findFirst([
                    "conditions" => "name = :name: and company_id = :company_id: and id != :id: and is_deleted = 0",
                    "bind" => [
                        "name" => Helpers::__getRequestValue("name"),
                        "company_id" => ModuleModel::$company->getId(),
                        "id" => $expense_category->getId()
                    ]
                ]);
                if ($categoryIfExist instanceof ExpenseCategory) {
                    $result['message'] = 'EXPENSE_CATEGORY_NAME_MUST_BE_UNIQUE_TEXT';
                    $result['success'] = false;
                    goto end_of_function;
                }
                $categoryIfExist = ExpenseCategory::findFirst([
                    "conditions" => "external_hris_id = :external_hris_id: and company_id = :company_id: and id != :id: and is_deleted = 0",
                    "bind" => [
                        "external_hris_id" => Helpers::__getRequestValue("external_hris_id"),
                        "company_id" => ModuleModel::$company->getId(),
                        "id" => $expense_category->getId()
                    ]
                ]);
                if ($categoryIfExist instanceof ExpenseCategory) {
                    $result['message'] = 'EXPENSE_CATEGORY_CODE_MUST_BE_UNIQUE_TEXT';
                    $result['success'] = false;
                    goto end_of_function;
                }
                $is_editable = $expense_category->isEditable();
                if (!$is_editable) {
                    $expense_category->setName(Helpers::__getRequestValue('name'));
                } else {
                    $expense_category->setData(Helpers::__getRequestValuesArray());
                }
                $expense_category->setIsEnabled(Helpers::__getRequestValue('is_enabled'));
                $expense_category->setCurrency(Helpers::__getRequestValue('currency'));
                $result = $expense_category->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_EXPENSE_CATEGORY_FAIL_TEXT';
                } else $result['message'] = 'SAVE_EXPENSE_CATEGORY_SUCCESS_TEXT';

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function enableCategoryAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'EXPENSE_CATEGORY_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense_category = ExpenseCategory::findFirstByUuid($uuid);

            if ($expense_category instanceof ExpenseCategory && $expense_category->belongsToGms()) {

                $expense_category->setIsEnabled(1);

                $result = $expense_category->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_EXPENSE_CATEGORY_FAIL_TEXT';
                } else $result['message'] = 'SAVE_EXPENSE_CATEGORY_SUCCESS_TEXT';

            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function disableCategoryAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'EXPENSE_CATEGORY_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense_category = ExpenseCategory::findFirstByUuid($uuid);

            if ($expense_category instanceof ExpenseCategory && $expense_category->belongsToGms()) {

                $expense_category->setIsEnabled(0);

                $result = $expense_category->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_EXPENSE_CATEGORY_FAIL_TEXT';
                } else $result['message'] = 'SAVE_EXPENSE_CATEGORY_SUCCESS_TEXT';

            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeCategoryAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $return = [
            'success' => false,
            'message' => 'EXPENSE_CATEGORY_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense_category = ExpenseCategory::findFirstByUuid($uuid);

            if ($expense_category instanceof ExpenseCategory && $expense_category->belongsToGms()) {
                if ($expense_category->isEditable()) {
                    $return = $expense_category->__quickRemove();
                } else {
                    $return = [
                        'success' => false,
                        'message' => 'CAN_NOT_DELETE_USED_EXPENSE_CATEGORY_TEXT'
                    ];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/expense", paths={module="gms"}, methods={"GET"}
     */
    public function getListExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['bill_id'] = Helpers::__getRequestValue('bill_id');
        $params['not_belong_to_other_bill'] = Helpers::__getRequestValue('not_belong_to_other_bill');
        $params['currency'] = Helpers::__getRequestValue('currency');
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        if ($params['end_date'] > 0) {
            $params['end_date'] = $params['end_date'] + 3600 * 24;
        }
        $params['companies'] = [];
        $params['products'] = [];
        $params['providers'] = [];
        $params['relocations'] = [];
        $params['assignees'] = [];
        $params['status'] = [];
        $params['categories'] = [];
        $params['exclude_items'] = [];
        $assignees = Helpers::__getRequestValue('assignees');
        if (is_array($assignees) && count($assignees) > 0) {
            foreach ($assignees as $assignee) {
                $params['assignees'][] = $assignee->id;
            }
        }

        $products = Helpers::__getRequestValue('products');
        if (is_array($products) && count($products) > 0) {
            foreach ($products as $product) {
                $params['products'][] = $product->id;
            }
        }

        $relocations = Helpers::__getRequestValue('relocations');
        if (is_array($relocations) && count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $params['relocations'][] = $relocation->id;
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $providers = Helpers::__getRequestValue('providers');
        if (is_array($providers) && count($providers) > 0) {
            foreach ($providers as $provider) {
                $params['providers'][] = $provider->id;
            }
        }
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $params['categories'][] = $category->id;
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

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        $logs = Expense::__findWithFilter($params, $ordersConfig);

        $this->response->setJsonContent($logs);
        $this->response->send();
    }



    /**
     * @Route("/expense", paths={module="gms"}, methods={"GET"}
     */
    public function getListReportExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['bill_id'] = Helpers::__getRequestValue('bill_id');
        $params['not_belong_to_other_bill'] = Helpers::__getRequestValue('not_belong_to_other_bill');
        $params['currency'] = Helpers::__getRequestValue('currency');
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        if ($params['end_date'] > 0) {
            $params['end_date'] = $params['end_date'] + 3600 * 24;
        }
        $params['companies'] = [];
        $params['exclude_items'] = [];
        $params['products'] = [];
        $params['providers'] = [];
        $params['relocations'] = [];
        $params['assignees'] = [];
        $params['status'] = [];
        $params['categories'] = [];
        $assignees = Helpers::__getRequestValue('assignees');
        if (is_array($assignees) && count($assignees) > 0) {
            foreach ($assignees as $assignee) {
                $params['assignees'][] = $assignee->id;
            }
        }

        $exclude_items = Helpers::__getRequestValue('exclude_items');
        if (is_array($exclude_items) && count($exclude_items) > 0) {
            foreach ($exclude_items as $exclude_item) {
                $params['exclude_items'][] = $exclude_item;
            }
        }

        $products = Helpers::__getRequestValue('products');
        if (is_array($products) && count($products) > 0) {
            foreach ($products as $product) {
                $params['products'][] = $product->id;
            }
        }

        $relocations = Helpers::__getRequestValue('relocations');
        if (is_array($relocations) && count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $params['relocations'][] = $relocation->id;
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $providers = Helpers::__getRequestValue('providers');
        if (is_array($providers) && count($providers) > 0) {
            foreach ($providers as $provider) {
                $params['providers'][] = $provider->id;
            }
        }
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $params['categories'][] = $category->id;
            }
        }
        $statuses = Helpers::__getRequestValue('status');
        if (is_array($statuses) && count($statuses) > 0) {
            foreach ($statuses as $status) {
                $params['status'][] = $status->value;
            }
        }

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = 1;

        $logs = Expense::__findWithFilter($params, $ordersConfig);

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

        if($logs['total_pages'] > 1){
            for($i = 2; $i <= $logs['total_pages']; $i++){
                $params['page'] = $i;
                $logs = Expense::__findWithFilter($params, $ordersConfig);

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
     *
     */
    public function getStatisticExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $return = [
            "success" => true,
            "total_pending" => 0,
            "total_amount" => 0,
            "total_paid" => 0
        ];

        $financial_account_id = Helpers::__getRequestValue("financial_account_id");
        $financial_account = FinancialAccount::findFirstById($financial_account_id);
        if (!$financial_account instanceof FinancialAccount || !$financial_account->belongsToGms()) {
            $return = [
                "success" => true,
                "data" => [],
                "message" => "DATA_NOT_FOUND_TEXT",
                "detail" => $financial_account
            ];
            goto end_of_function;
        }
        $currency = $financial_account->getCurrency();
        $provider_id = Helpers::__getRequestValue('provider_id');
        $hr_company_id = Helpers::__getRequestValue('hr_company_id');
        if ($provider_id > 0) {
            $expense_watting_approvals = Expense::find([
                "conditions" => "company_id = :company_id: and is_payable = 1 and service_provider_id  = :service_provider_ids: and status = :pending_status: and currency = :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->id,
                    "service_provider_ids" => $provider_id,
                    "pending_status" => Expense::STATUS_PENDING,
                    "currency" => $currency
                ]
            ]);
            $total_amount = Expense::sum([
                    "column" => "total",
                    "conditions" => "company_id = :company_id: and is_payable = 1 and service_provider_id = :service_provider_ids: and status != :archived_status: and currency = :currency:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->id,
                        "service_provider_ids" => $provider_id,
                        "archived_status" => Expense::STATUS_ARCHIVED,
                        "currency" => $currency
                    ]
                ]
            );
            $total_paid = Expense::sum([
                    "column" => "total_paid",
                    "conditions" => "company_id = :company_id: and is_payable = 1 and service_provider_id = :service_provider_ids: and status != :archived_status: and currency = :currency:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->id,
                        "service_provider_ids" => $provider_id,
                        "archived_status" => Expense::STATUS_ARCHIVED,
                        "currency" => $currency
                    ]
                ]
            );
            $return = [
                "success" => true,
                "total_pending" => count($expense_watting_approvals),
                "total_amount" => $total_amount,
                "total_paid" => $total_paid
            ];
        } else if ($hr_company_id > 0) {
            $expense_watting_approvals = Expense::find([
                "conditions" => "company_id = :company_id: and (chargeable_type = :chargeable_account: or chargeable_type = :chargeable_assignee:) and account_id = :account_ids: and status = :pending_status: and currency = :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->id,
                    "account_ids" => $hr_company_id,
                    'chargeable_account' => Expense::CHARGE_TO_ACCOUNT,
                    'chargeable_assignee' => Expense::CHARGE_TO_EMPLOYEE,
                    "pending_status" => Expense::STATUS_PENDING,
                    "currency" => $currency
                ]
            ]);
            $total_amount = Expense::sum([
                    "column" => "total",
                    "conditions" => "company_id = :company_id: and (chargeable_type = :chargeable_account: or chargeable_type = :chargeable_assignee:) and account_id = :account_ids: and status != :archived_status: and currency = :currency:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->id,
                        "account_ids" => $hr_company_id,
                        'chargeable_account' => Expense::CHARGE_TO_ACCOUNT,
                        'chargeable_assignee' => Expense::CHARGE_TO_EMPLOYEE,
                        "archived_status" => Expense::STATUS_ARCHIVED,
                        "currency" => $currency
                    ]
                ]
            );
            $total_paid = Expense::sum([
                    "column" => "total_paid",
                    "conditions" => "company_id = :company_id: and (chargeable_type = :chargeable_account: or chargeable_type = :chargeable_assignee:) and account_id = :account_ids: and status != :archived_status: and currency = :currency:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->id,
                        "account_ids" => $hr_company_id,
                        'chargeable_account' => Expense::CHARGE_TO_ACCOUNT,
                        'chargeable_assignee' => Expense::CHARGE_TO_EMPLOYEE,
                        "archived_status" => Expense::STATUS_ARCHIVED,
                        "currency" => $currency
                    ]
                ]
            );
            $return = [
                "success" => true,
                "total_pending" => count($expense_watting_approvals),
                "total_amount" => $total_amount,
                "total_paid" => $total_paid
            ];
        } else {
            $expense_watting_approvals = Expense::find([
                "conditions" => "company_id = :company_id:  and status = :pending_status: and currency = :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->id,
                    "pending_status" => Expense::STATUS_PENDING,
                    "currency" => $currency
                ]
            ]);
            $total_amount = Expense::sum([
                    "column" => "total",
                    "conditions" => "company_id = :company_id:  and status != :archived_status: and currency = :currency:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->id,
                        "archived_status" => Expense::STATUS_ARCHIVED,
                        "currency" => $currency
                    ]
                ]
            );
            $total_paid = Expense::sum([
                    "column" => "total_paid",
                    "conditions" => "company_id = :company_id:  and status != :archived_status: and currency = :currency:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->id,
                        "archived_status" => Expense::STATUS_ARCHIVED,
                        "currency" => $currency
                    ]
                ]
            );
            $return = [
                "success" => true,
                "total_pending" => count($expense_watting_approvals),
                "total_amount" => $total_amount,
                "total_paid" => $total_paid
            ];
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $uuid = Helpers::__getRequestValue('uuid');
        $data = Helpers::__getRequestValuesArray();
        $attachments = Helpers::__getRequestValue('attachments');
        $expense = new Expense();
        $data['company_id'] = ModuleModel::$company->getId();
        $relocation = Helpers::__getRequestValueAsArray('relocation');
        $account_id = Helpers::__getRequestValueAsArray('account_id');

        $list_timelog = Helpers::__getRequestValue('list_timelog');

//        if ($relocation && isset($relocation['id']) && Helpers::__isValidId($relocation['id'])) {
//            $relocation = Relocation::findFirstById($relocation['id']);
//            if ($relocation instanceof Relocation && $relocation->belongsToGms() && $relocation->checkMyViewPermission()) {
//                $data['relocation_id'] = $relocation->getId();
//                $data['account_id'] = $relocation->getHrCompanyId();
//            }
//        }


        if (isset($data['relocation_service_company_id'])) {
            $relocation_service_company = RelocationServiceCompany::findFirstById($data['relocation_service_company_id']);
            if ($relocation_service_company instanceof RelocationServiceCompany && $relocation_service_company->getStatus() != RelocationServiceCompany::STATUS_ACTIVE) {
                $result = [
                    "success" => false,
                    "message" => "SAVE_EXPENSE_FAIL_TEXT",
                    "detail" => "relocation service company is not activated"
                ];
                goto end_of_function;
            }
        }

        $expense->setData($data);
        if ($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) {
            $expense->setTotal($expense->getCost() * (1 + $expense->getTaxRule()->getRate() / 100));
        } else {
            $expense->setTotal($expense->getCost());
        }
        if ($account_id && Helpers::__isValidId($account_id)) {
            $expense->setAccountId($account_id);
        }
        $expense->setNumber($expense->generateNumber());
        $expense->setStatus(Expense::STATUS_APPROVED);
        $setting = ModuleModel::$company->getRecurrentExpenseApproval();
        if ($setting instanceof CompanySetting) {
            if (intval($setting->getValue()) == 1) {
                $expense->setStatus(Expense::STATUS_DRAFT);
            }
        }

        //Prefix uuid
        if (!Helpers::__isValidUuid($uuid) || $uuid == null) {
            $uuid = Helpers::__uuid();
        }

        $this->db->begin();

        $expense->setLinkType(Helpers::__getRequestValue("link_type"));
        if (isset($data['relocation']) && isset($data['relocation']['id'])) {
            $relocation = Relocation::findFirstById($data['relocation']['id']);
            if ($relocation instanceof Relocation && $relocation->belongsToGms() && $relocation->checkMyViewPermission()) {
                if ($expense->getLinkType() == Expense::LINK_TYPE_RELOCATION) {
                    $expense->setRelocationId($relocation->getId());
                    if (!$account_id) {
                        $expense->setAccountId($relocation->getHrCompanyId());
                    }

                } else {
                    if ($expense->getAccountId() == $relocation->getHrCompanyId() ||
                        $expense->getAccountId() == $relocation->getAssignment()->getBookerCompanyId()) {
                        $expense->setRelocationId($relocation->getId());
                        $expense->setLinkType(Expense::LINK_TYPE_RELOCATION);
                        if (!$account_id) {
                            $expense->setAccountId($relocation->getHrCompanyId());
                        }
                    } else {
                        $expense->setRelocationId(null);
                    }
                }
            }
        }

        $expense->setUuid($uuid);

        $result = $expense->__quickCreate();
        $result["data"] = $expense->toArray();
        $result["data"]["tax_rule"] = $expense->getTaxRule();

        $expenseCategory = $expense->getExpenseCategory();
        $taxRule = $expense->getTaxRule();

        $result["data"]["expense_category"] = $expenseCategory ? $expenseCategory->toArray() : null;
        $result["data"]["category_name"] = $expenseCategory ? $expenseCategory->getName() : null;
        $result["data"]["expense_category_unit"] = $expenseCategory ? $expenseCategory->getUnit() : null;
        $result["data"]["tax_rate"] = $taxRule ? $taxRule->getRate() : 0;

        if ($expenseCategory && $expenseCategory->getUnit() != '' && isset(ExpenseCategory::UNIT_NAME[$expenseCategory->getUnit()])) {
            $result["data"]["expense_category"]['unit_name'] = ExpenseCategory::UNIT_NAME[$expenseCategory->getUnit()];
        }

        if ($result['success'] == false) {
            $this->db->rollback();
            $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
            goto end_of_function;
        }
        if (is_array($list_timelog) && count($list_timelog) > 0) {
            foreach ($list_timelog as $item) {
                $timelog = Timelog::findFirstByUuid($item->uuid);
                if ($timelog) {
                    $timelog->setExpenseId($expense->getId());
                    $result = $timelog->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                        goto end_of_function;
                    }
                }
            }
        }

        if ($expense->isApproved() == true) {
            $resultCreateInvoiceableExpense = $expense->generateInvoiceableItems();
            if ($resultCreateInvoiceableExpense['success'] == false) {
                $this->db->rollback();
                $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                goto end_of_function;
            }
        }

        if (Helpers::__isValidUuid($uuid)) {
            if (is_array($attachments) && count($attachments) > 0) {
                $attach = MediaAttachment::__createAttachments([
                    'objectUuid' => $uuid,
                    'fileList' => $attachments,
                ]);

                if ($attach['success'] == true) {
                    $this->db->commit();
                    $result['success'] = true;
                    $result['message'] = 'SAVE_EXPENSE_SUCCESS_TEXT';
                    $result['attachments'] = $attachments;
                    goto end_of_function;
                } else {
                    $this->db->rollback();
                    $result['success'] = false;
                    $result['detail'] = $attach;
                    $result['attachments'] = $attachments;
                    goto end_of_function;
                }
            }
        }

        $this->db->commit();
        ModuleModel::$expense = $expense;
        $result['message'] = 'SAVE_EXPENSE_SUCCESS_TEXT';
        
        $this->dispatcher->setParam('return', $result);
        goto end_of_function;

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                if ($expense->getStatus() == Expense::STATUS_PAID) {
                    $return = [
                        'success' => false,
                        'message' => 'PAID_EXPENSE_CAN_NOT_DELETE_TEXT'
                    ];
                    goto end_of_function;
                }

                if ($expense->getBillId() > 0) {
                    $return = [
                        'success' => false,
                        'message' => 'EXPENSE_LINKED_TO_BILL_CAN_NOT_DELETE_TEXT'
                    ];
                    goto end_of_function;
                }

                ModuleModel::$expense = $expense;
                $return = $expense->__quickRemove();
                if($return['success']){
                    $this->dispatcher->setParam('return', $return);
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function detailExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {
                $item = $expense->toArray();
                if ($expense->getRelocation() instanceof Relocation) {
                    $item["relocation"] = $expense->getRelocation()->toArray();
                    $item["relocation"]["number"] = $item["relocation"]["identify"];
                    $item["relocation"]["company_name"] = $expense->getRelocation()->getHrCompany()->getName();
                    $item["relocation"]["employee_uuid"] = $expense->getRelocation()->getEmployee()->getUuid();
                    $item["relocation"]["employee_name"] = $expense->getRelocation()->getEmployee()->getFirstname() . ' ' . $expense->getRelocation()->getEmployee()->getLastname();
                }

                $item["expense_category_id"] = intval($expense->getExpenseCategoryId());
                $item["relocation_service_company_id"] = intval($expense->getRelocationServiceCompanyId());
                $item["product_pricing_id"] = intval($expense->getProductPricingId());
                $item["account_product_pricing_id"] = intval($expense->getAccountProductPricingId());
                $product_pricing = $expense->getProductPricing();

                if ($product_pricing instanceof ProductPricing) {
                    $item["product_name"] = $product_pricing->getName();
                }
                $account_product_pricing = $expense->getAccountProductPricing();
                if ($account_product_pricing instanceof AccountProductPricing) {
                    $item["product_name"] = $account_product_pricing->getName();
                }

                $item["status"] = intval($expense->getStatus());
                $item["cost"] = floatval($expense->getCost());
                $item["chargeable_type"] = intval($expense->getChargeableType());
                $item["is_payable"] = intval($expense->getIsPayable());
                $item["link_type"] = intval($expense->getLinkType());
                $item["id"] = intval($expense->getId());
                $item["quantity"] = floatval($expense->getQuantity());
                $item["price"] = floatval($expense->getPrice());
                $item["total"] = floatval($expense->getTotal());
                $item["total_paid"] = floatval($expense->getTotalPaid());
                $item["relocation_id"] = intval($expense->getRelocationId());
                $item["account_id"] = intval($expense->getAccountId());
                $item["account_name"] = $expense->getAccount() ? $expense->getAccount()->getName() : null;
                $item["service_provider_id"] = intval($expense->getServiceProviderId());
                $item["tax_rule_id"] = intval($expense->getTaxRuleId());
                $item["expense_category_id"] = intval($expense->getExpenseCategoryId());
                $item["expense_date"] = intval($expense->getExpenseDate());
                $item["expense_date_second"] = intval($expense->getExpenseDate() / 1000);
                $item["expense_date_local"] = intval($expense->getExpenseDate() / 1000);

                $item["bill"] = $expense->getBill() ? $expense->getBill() : null;
                $item["isEditable"] = $item["is_editable"] = $expense->isEditable();
                $item['isLinkedWithTimelog'] = $expense->isLinkedWithTimelog();

                $item["can_edit_tax"] = $item["is_editable"] || $expense->isFieldEditable('tax_rule_id');
                $item["can_edit_quantity"] = $item["is_editable"] || $expense->isFieldEditable('quantity');
                $item["linked_with_timelog"] = $expense->isLinkedWithTimelog();
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
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Phalcon\Security\Exception
     */
    public function editExpenseAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $uuid = Helpers::__getRequestValue("uuid");
        $data = Helpers::__getRequestValuesArray();
        $attachments = Helpers::__getRequestValue('attachments');
        $data['company_id'] = ModuleModel::$company->getId();

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                if ($expense->getStatus() == Expense::STATUS_APPROVED || $expense->getStatus() == Expense::STATUS_PAID) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not edit expense approve or paid"
                    ];
                    goto end_of_function;
                }


                if (isset($data['relocation_service_company_id'])) {
                    $relocation_service_company = RelocationServiceCompany::findFirstById($data['relocation_service_company_id']);
                    if ($relocation_service_company instanceof RelocationServiceCompany && $relocation_service_company->getStatus() != RelocationServiceCompany::STATUS_ACTIVE) {
                        $result = [
                            "success" => false,
                            "message" => "SAVE_EXPENSE_FAIL_TEXT",
                            "detail" => "relocation service company is not activated"
                        ];
                        goto end_of_function;
                    }
                }

                $old_status = $expense->getStatus();
                $expense->setData($data);


                if ($expense->getTaxRule() instanceof TaxRule) {
                    $expense->setTotal($expense->getCost() * (1 + $expense->getTaxRule()->getRate() / 100));
                } else {
                    $expense->setTotal($expense->getCost());
                }


                $expense->setStatus(Expense::STATUS_APPROVED);
                $expense->setLinkType(Helpers::__getRequestValue("link_type"));

                if (isset($data['relocation']) && isset($data['relocation']['id'])) {
                    $relocation = Relocation::findFirstById($data['relocation']['id']);
                    if ($relocation instanceof Relocation && $relocation->belongsToGms() && $relocation->checkMyViewPermission()) {
                        if ($expense->getLinkType() == Expense::LINK_TYPE_RELOCATION) {
                            $expense->setRelocationId($relocation->getId());
                            if ($expense->getAccountId() == null) {
                                $expense->setAccountId($relocation->getHrCompanyId());
                            }
                        } else {
                            if ($expense->getAccountId() == $relocation->getHrCompanyId() ||
                                $expense->getAccountId() == $relocation->getAssignment()->getBookerCompanyId()) {
                                $expense->setRelocationId($relocation->getId());
                                if ($expense->getAccountId() == null) {
                                    $expense->setAccountId($relocation->getHrCompanyId());
                                }
                                $expense->setLinkType(Expense::LINK_TYPE_RELOCATION);
                            } else {
                                $expense->setRelocationId(null);
                            }
                        }
                    }
                }

                $setting = ModuleModel::$company->getRecurrentExpenseApproval();
                if ($setting instanceof CompanySetting) {
                    if (intval($setting->getValue()) == 1) {
                        $expense->setStatus(Expense::STATUS_DRAFT);
                    }
                }
                $this->db->begin();
                $result = $expense->__quickUpdate();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['data'] = $expense;
                    $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                    goto end_of_function;
                }
                $expense = $result['data'];

//                $result["data"] = $expense->toArray();
//                $result["data"]["tax_rule"] = $expense->getTaxRule();
//                $result["data"]["expense_category"] = $expense->getExpenseCategory()->toArray();
//                $result["data"]["category_name"] = $expense->getExpenseCategory() ? $expense->getExpenseCategory()->getName() : null;
//                $result["data"]["expense_category_unit"] = $expense->getExpenseCategory() ? $expense->getExpenseCategory()->getUnit() : null;
//                $result["data"]["tax_rate"] = $expense->getTaxRule() ? $expense->getTaxRule()->getRate() : 0;

//                if ($expense->getExpenseCategory()->getUnit() != '' && isset(ExpenseCategory::UNIT_NAME[$expense->getExpenseCategory()->getUnit()])) {
//                    $result["data"]["expense_category"]['unit_name'] = ExpenseCategory::UNIT_NAME[$expense->getExpenseCategory()->getUnit()];
//                }
                $expenseCategory = $expense->getExpenseCategory();
                $taxRule = $expense->getTaxRule();

                $result["data"] = $expense->toArray();
                $result["data"]["tax_rule"] = $taxRule;
                $result["data"]["expense_category"] = $expenseCategory ? $expenseCategory->toArray() : null;
                $result["data"]["category_name"] = $expenseCategory ? $expenseCategory->getName() : null;
                $result["data"]["expense_category_unit"] = $expenseCategory ? $expenseCategory->getUnit() : null;
                $result["data"]["tax_rate"] = $taxRule ? $taxRule->getRate() : 0;

                if ($expenseCategory && $expenseCategory->getUnit() != '' && isset(ExpenseCategory::UNIT_NAME[$expenseCategory->getUnit()])) {
                    $result["data"]["expense_category"]['unit_name'] = ExpenseCategory::UNIT_NAME[$expenseCategory->getUnit()];
                }
//                if (is_array($attachments) && count($attachments) > 0) {
//
//                    $result = MediaAttachment::__createAttachments([
//                        'objectUuid' => $expense->getUuid(),
//                        'fileList' => $attachments,
//                    ]);
//
//                    if ($result['success'] == true) {
//                        $this->db->commit();
//                        $result['message'] = 'SAVE_EXPENSE_SUCCESS_TEXT';
//                        goto end_of_function;
//                    } else {
//                        $this->db->rollback();
//                        $result['attachments'] = $attachments;
//                        goto end_of_function;
//                    }
//                } else {
                $this->db->commit();
                ModuleModel::$expense = $expense;
                $this->dispatcher->setParam('return', $result);
                $result['message'] = 'SAVE_EXPENSE_SUCCESS_TEXT';
                goto end_of_function;
//                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function sentExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                $setting = ModuleModel::$company->getRecurrentExpenseApproval();
                if ($setting instanceof CompanySetting) {
                    if (intval($setting->getValue()) == 1) {
                        if ($expense->getStatus() != Expense::STATUS_DRAFT) {
                            $result = [
                                'success' => false,
                                'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                                "detail" => "can not sent non draft expense"
                            ];
                            goto end_of_function;
                        }
                        $expense->setStatus(Expense::STATUS_PENDING);
                    }
                } else {
                    $expense->setStatus(Expense::STATUS_APPROVED);
                }


                if ($expense->getBillId() > 0) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not sent an expense belonged to a bill"
                    ];
                    goto end_of_function;
                }


                $result = $expense->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SENT_EXPENSE_FAIL_TEXT';
                } else $result['message'] = 'SENT_EXPENSE_SUCCESS_TEXT';

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function approveExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('approve', 'expense');

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                if ($expense->getStatus() != Expense::STATUS_PENDING) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not approve non pending expense"
                    ];
                    goto end_of_function;
                }

                if ($expense->getBillId() > 0) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not approve an expense belonged to a bill"
                    ];
                    goto end_of_function;
                }

                $this->db->begin();
                $expense->setStatus(Expense::STATUS_APPROVED);
                $result = $expense->__quickUpdate();
                if ($result['success'] == false) {
                    $result['errorType'] = 'canNotSaveExpense';
                    $result['message'] = 'SENT_EXPENSE_FAIL_TEXT';
                } else {
                    $result['message'] = 'EXPENSE_WAS_APPROVED_TEXT';
                }

                if ($expense->isApproved() == true) {
                    $resultCreateInvoiceableExpense = $expense->generateInvoiceableItems();
                    if ($resultCreateInvoiceableExpense['success'] == false) {
                        $this->db->rollback();
                        $result['errorType'] = 'canNotGenerateInvoiceableExpense';
                        $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                        goto end_of_function;
                    }
                }

                $this->db->commit();

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function rejectExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('approve', 'expense');

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                if ($expense->getStatus() != Expense::STATUS_PENDING) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not reject non pending expense"
                    ];
                    goto end_of_function;
                }

                if ($expense->getBillId() > 0) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not reject an expense belonged to a bill"
                    ];
                    goto end_of_function;
                }

                $expense->setStatus(Expense::STATUS_REJECTED);

                $result = $expense->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SENT_EXPENSE_FAIL_TEXT';
                } else $result['message'] = 'EXPENSE_WAS_REJECTED_TEXT';

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }



    /**
     * Save service action
     */
    public function revertExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('approve', 'expense');

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                if ($expense->getStatus() != Expense::STATUS_APPROVED) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not revert non approved expense"
                    ];
                    goto end_of_function;
                }

                if ($expense->getBillId() > 0) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not revert an expense belonged to a bill"
                    ];
                    goto end_of_function;
                }

                $transaction = Payment::findFirstByExpenseId($expense->getId());
                if($transaction){
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not revert an expense had transaction"
                    ];
                    goto end_of_function;
                }
                $this->db->begin();
                $invoiceable_item = InvoiceableItem::findFirstByExpenseId($expense->getId());
                if($invoiceable_item){
                    $invoice_quote_item = InvoiceQuoteItem::findFirstByExpenseId($expense->getId());
                    if($invoice_quote_item){
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                            "detail" => "can not revert an expense linked to an invoice/quote/credit note"
                        ];
                        goto end_of_function;
                    }
                    $result = $invoiceable_item->__quickRemove();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SENT_EXPENSE_FAIL_TEXT';
                        goto end_of_function;
                    } 
                }

                $expense->setStatus(Expense::STATUS_DRAFT);

                $result = $expense->__quickUpdate();

                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SENT_EXPENSE_FAIL_TEXT';
                    goto end_of_function;
                } 
                $result['message'] = 'SENT_EXPENSE_SUCCESS_TEXT';
                $this->db->commit();

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function payExpenseAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('approve', 'expense');

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                if ($expense->getStatus() != Expense::STATUS_APPROVED) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not pay non approve expense"
                    ];
                    goto end_of_function;
                }

                if ($expense->getBillId() > 0) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EXPENSE_FAIL_TEXT',
                        "detail" => "can not pay an expense belonged to a bill"
                    ];
                    goto end_of_function;
                }

                $expense->setStatus(Expense::STATUS_PAID);

                $result = $expense->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SENT_EXPENSE_FAIL_TEXT';
                } else $result['message'] = 'SENT_EXPENSE_SUCCESS_TEXT';

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/expense", paths={module="gms"}, methods={"GET"}
     */
    public function getExpensesByProviderAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_BILL, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $params = [];
        $params['service_provider_id'] = intval(Helpers::__getRequestValue('service_provider_id'));
        $params['currency'] = Helpers::__getRequestValue('currency');
        $params['bill_id'] = Helpers::__getRequestValue('bill_id');
        $expenses = Expense::find([
            "conditions" => "(status = :status: or (status = :approved_status: and bill_id = :bill_id: )) and is_payable = 1 and service_provider_id = :service_provider_id: and currency LIKE :currency: and company_id =:company_id: and (bill_id IS NULL or bill_id = :bill_id:)",
            "bind" => [
                "status" => Expense::STATUS_DRAFT,
                "approved_status" => Expense::STATUS_APPROVED,
                "service_provider_id" => $params['service_provider_id'],
                "currency" => "%" . $params['currency'] . "%",
                "company_id" => ModuleModel::$company->getId(),
                "bill_id" => $params['bill_id']
            ]
        ]);
        $items = [];
        if (count($expenses) > 0) {
            foreach ($expenses as $expense) {
                $item = $expense->toArray();
                $item["tax_rule"] = $expense->getTaxRule();
                if ($expense->getExpenseCategory()) {
                    $item["expense_category"] = $expense->getExpenseCategory()->toArray();
                    $item["expense_category"]['unit_name'] = $expense->getExpenseCategory()->getUnit() ? ExpenseCategory::UNIT_NAME[$expense->getExpenseCategory()->getUnit()] : null;
                } else {
                    $item["expense_category"] = null;
                }

                $items[] = $item;
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items,
        ]);
        $this->response->send();
    }

}
