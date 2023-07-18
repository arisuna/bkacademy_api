<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security\Random;
use Phalcon\Text;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Bill;
use Reloday\Gms\Models\Expense;
use Reloday\Gms\Models\ExpenseCategory;
use Reloday\Gms\Models\InvoiceableItem;
use Reloday\Gms\Models\InvoiceQuoteItem;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Payment;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\TaxRule;
use Reloday\Gms\Models\Transaction;
use Reloday\Gms\Models\TransactionType;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class BillController extends BaseController
{
    /**
     * @Route("/bill", paths={module="gms"}, methods={"GET"}
     */
    public function getListBillAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $params['type'] = Helpers::__getRequestValue('type');
        $params['statuses'] = Helpers::__getRequestValue('statuses');
        $params['statuses'] = [];
        $statuses = Helpers::__getRequestValue('statuses');
        if (is_array($statuses) && count($statuses) > 0) {
            foreach ($statuses as $status) {
                $params['statuses'][] = $status->value;
            }
        }
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['provider_id'] = Helpers::__getRequestValue('provider_id');

//        $orders = Helpers::__getRequestValue('orders');
//        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        /***** new filter ******/
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $logs = Bill::__findWithFilter($params, $ordersConfig);

        if (!$logs["success"]) {
            $this->response->setJsonContent([
                'success' => false,
                'params' => $params,
                'queryBuider' => $logs
            ]);
        } else {
            $this->response->setJsonContent([
                'success' => true,
                'data' => $logs["data"],
                'params' => $params,
                'queryBuider' => $logs
            ]);
        }
        $this->response->send();
    }

    /**
     * @Route("/bill", paths={module="gms"}, methods={"GET"}
     */
    public function getListReportBillAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $params['type'] = Helpers::__getRequestValue('type');
        $params['statuses'] = Helpers::__getRequestValue('statuses');
        $params['statuses'] = [];
        $statuses = Helpers::__getRequestValue('statuses');
        if (is_array($statuses) && count($statuses) > 0) {
            foreach ($statuses as $status) {
                $params['statuses'][] = $status->value;
            }
        }
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['provider_id'] = Helpers::__getRequestValue('provider_id');

//        $orders = Helpers::__getRequestValue('orders');
//        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        /***** new filter ******/
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }
        $params['nopaging'] = true;

        $logs = Bill::__findWithFilter($params, $ordersConfig);

        if (!$logs["success"]) {
            $this->response->setJsonContent([
                'success' => false,
                'params' => $params,
                'queryBuider' => $logs
            ]);
        } else {
            $this->response->setJsonContent([
                'success' => true,
                'data' => $logs["data"],
                'params' => $params,
                'queryBuider' => $logs
            ]);
        }
        $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createBillAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $data = Helpers::__getRequestValuesArray();
        $bill = new Bill();
        $type = Helpers::__getRequestValue("type");
        $data['company_id'] = ModuleModel::$company->getId();
        $reference = Helpers::__getRequestValue("reference");

        if ($reference && $reference != '') {
            $checkReferenceExist = Bill::__findBillByReference($reference);
            if ($checkReferenceExist instanceof Bill) {
                $result['message'] = 'BILL_REFERENCE_MUST_BE_UNIQUE_TEXT';
                $result['detail'] = $checkReferenceExist;
                $result['success'] = false;
                goto end_of_function;
            }
        }
        $bill->setCompanyId(ModuleModel::$company->getId());
        $bill->setNumber($bill->generateNumber());
        $bill->setData($data);
        $bill->setStatus(Bill::STATUS_DRAFT);
        $this->db->begin();
        $result = $bill->__quickCreate();
        if ($result['success'] == false) {
            $this->db->rollback();
            $result['message'] = 'SAVE_BILL_FAIL_TEXT';
            goto end_of_function;
        }
        $items = Helpers::__getRequestValue("items");
        if (count($items) > 0) {
            foreach ($items as $item) {
                $expense = Expense::findFirstById($item->expense->id);
                if (!$expense instanceof Expense || !$expense->belongsToGms() || ($expense->getStatus() != Expense::STATUS_DRAFT && $expense->getStatus() != Expense::STATUS_PENDING && $expense->getStatus() != Expense::STATUS_APPROVED)) {
                    $this->db->rollback();
                    $result['message'] = 'EXPENSE_NOT_FOUND_TEXT';
                    $result['raw'] = $item;
                    $result['success'] = false;
                    goto end_of_function;
                }
                $expense->setBillId($bill->getId());
                if ($expense->getStatus() == Expense::STATUS_REJECTED){
                    $expense->setStatus(Expense::STATUS_DRAFT);
                }

                $resultItem = $expense->__quickUpdate();
                if ($resultItem['success'] == false) {
                    $this->db->rollback();
                    $result = $resultItem;
                    $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                    goto end_of_function;
                }
            }
        }
        $this->db->commit();
        $result['message'] = 'SAVE_BILL_SUCCESS_TEXT';
        ModuleModel::$bill = $bill;
        $this->dispatcher->setParam('return', $result);

        end_of_function:
        $this->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/bill", paths={module="gms"}, methods={"GET"}, name="gms-needform-index")
     */
    public function detailBillAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $bill = Bill::findFirstByUuid($uuid);

            if ($bill instanceof Bill && $bill->belongsToGms() && $bill->getStatus() != Bill::STATUS_ARCHIVED) {
                $item = $bill->toArray();
                $item["date"] = intval($bill->getDate());
                $item["due_date"] = intval($bill->getDueDate());

                $bill_item_array = [];
                $bill_items = $bill->getExpenses();
                if (count($bill_items) > 0) {
                    foreach ($bill_items as $expense) {
                        $item_array["expense"] = $expense->toArray();
                        $item_array["expense"]["expense_category"] = $expense->getExpenseCategory() ?$expense->getExpenseCategory()->toArray() : null;
                        $item_array["expense"]["tax_rule"] = $expense->getTaxRule();
                        $item_array["expense"]["tax_rate"] = $expense->getTaxRule() instanceof TaxRule ? $expense->getTaxRule()->getRate() : 0;
                        $item_array["expense"]["category_name"] = $expense->getExpenseCategory() ?$expense->getExpenseCategory()->getName() : "";
                        $item_array["expense"]["category_code"] = $expense->getExpenseCategory() ?$expense->getExpenseCategory()->getExternalHrisId() : "";
                        $item_array["expense"]['expense_category_unit'] = $expense->getExpenseCategory() ?$expense->getExpenseCategory()->getUnit():null;
                        $item_array["expense"]['expense_category_unit_name'] = $expense->getExpenseCategory() ? ($expense->getExpenseCategory()->getUnit() ? ExpenseCategory::UNIT_NAME[$expense->getExpenseCategory()->getUnit()] : null) : null;
                        $bill_item_array[] = $item_array;
                    }
                }
                $item["items"] = $bill_item_array;
                $item['provider'] = $bill->getServiceProviderCompany();
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
    public function editBillAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $bill = Bill::findFirstByUuid($uuid);

            if ($bill instanceof Bill && $bill->belongsToGms()) {

                $data = Helpers::__getRequestValuesArray();
                $checkReferenceExist = Bill::findFirst([
                    "conditions" => "reference = :reference: and id != :id:",
                    "bind" => [
                        "reference" => $data["reference"],
                        "id" => $data["id"]
                    ]
                ]);
                if ($checkReferenceExist instanceof Bill) {
                    $result['message'] = 'BILL_REFERENCE_MUST_BE_UNIQUE_TEXT';
                    $result['detail'] = $checkReferenceExist;
                    $result['success'] = false;
                    goto end_of_function;
                }

                if ($bill->getStatus() == Bill::STATUS_UNPAID || $bill->getStatus() == Bill::STATUS_PARTIAL_PAID && $bill->getStatus() != Bill::STATUS_PAID) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_BILL_FAIL_TEXT',
                        "detail" => "can edit an approved bill"
                    ];
                    goto end_of_function;
                }
                $this->db->begin();

                $total = $bill->getTotal();
                $subTotal = $bill->getSubTotal();

                $items = Helpers::__getRequestValue("items");
                if (count($items) > 0) {
                    foreach ($items as $item) {
                        $expense = Expense::findFirstById($item->expense->id);
                        if (!$expense instanceof Expense || !$expense->belongsToGms() ||
                            ($expense->getStatus() != Expense::STATUS_DRAFT &&
                                $expense->getStatus() != Expense::STATUS_PENDING &&
                                $expense->getStatus() != Expense::STATUS_APPROVED)) {
                            $this->db->rollback();
                            $result['message'] = 'EXPENSE_NOT_FOUND_TEXT';
                            $result['raw'] = $item;
                            $result['success'] = false;
                            goto end_of_function;
                        }
                        if ($expense->getBillId() == null){
                            $expense->setBillId($bill->getId());
                            $total = floatval($total) + floatval($expense->getTotal());
                            $subTotal = floatval($subTotal) + floatval($expense->getCost());

                            if ($expense->getStatus() == Expense::STATUS_REJECTED){
                                $expense->setStatus(Expense::STATUS_DRAFT);
                            }

                            $resultItem = $expense->__quickUpdate();
                            if ($resultItem['success'] == false) {
                                $this->db->rollback();
                                $result = $resultItem;
                                goto end_of_function;
                            }
                        }else{
                            if ($expense->getBillId() != $bill->getId()){
                                $result = [
                                    'success' => false,
                                    'message' => 'EXPENSE_ALREADY_BELONG_TO_ANOTHER_BILL_TEXT'
                                ];
                                $this->db->rollback();
                                goto end_of_function;
                            }else{
                                if ($expense->getStatus() == Expense::STATUS_REJECTED){
                                    $expense->setStatus(Expense::STATUS_DRAFT);
                                    $resultItem = $expense->__quickUpdate();
                                    if ($resultItem['success'] == false) {
                                        $this->db->rollback();
                                        $result = $resultItem;
                                        goto end_of_function;
                                    }
                                }

                            }
                        }
                    }
                }else{
                    $total = 0;
                    $subTotal = 0;
                }

                $bill->setData($data);
                $bill->setStatus(Bill::STATUS_DRAFT);
                $bill->setTotal($total);
                $bill->setSubTotal($subTotal);
                $result = $bill->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }else{
                    $this->db->commit();
                    $result['message'] = 'SAVE_BILL_SUCCESS_TEXT';
                    ModuleModel::$bill = $bill;
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
    public function removeBillAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $bill = Bill::findFirstByUuid($uuid);

            if ($bill instanceof Bill && $bill->belongsToGms()) {
                if ($bill->getStatus() != Bill::STATUS_DRAFT && $bill->getStatus() != Bill::STATUS_REJECTED) {
                    $return = [
                        'success' => false,
                        'message' => 'REMOVE_BILL_FAIL_TEXT',
                        "detail" => "can not remove a non draft bill"
                    ];
                    goto end_of_function;
                }

                $expenses = $bill->getExpenses();
                $this->db->begin();
                if (count($expenses) > 0) {
                    foreach ($expenses as $expense) {
                        $expense->setBillId(null);
                        $result = $expense->__quickUpdate();
                        if ($result['success'] == false) {
                            $this->db->rollback();
                            $result['message'] = 'REMOVE_BILL_FAIL_TEXT';
                            goto end_of_function;
                        }
                    }
                }
                $validation = $bill->beforeValidation();
                if (!$validation){
                    $bill->passedValidation();
                }
                ModuleModel::$bill = $bill;
                $return = $bill->__quickRemove();
                if ($return["success"] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }
                $this->db->commit();
                $this->dispatcher->setParam('return', $result);
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function saveBillItemAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();
        $expense = Helpers::__getRequestValue("expense");
        $bill_id = Helpers::__getRequestValue("bill_id");
        if ($bill_id == '' || $bill_id == null) {
            $bill_id = $expense->bill_id;
        }
        $uuid = $expense->uuid;

        $bill_total = Helpers::__getRequestValue("bill_total");
        $bill_subtotal = Helpers::__getRequestValue("bill_subtotal");

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $expense = Expense::findFirstByUuid($uuid);

            if ($expense instanceof Expense && $expense->belongsToGms()) {

                if ($expense->getBillId() == $bill_id) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_BILL_ITEM_SUCCESS_TEXT'
                    ];
                    goto end_of_function;
                } else if ($expense->getBillId() > 0) {
                    if ($expense->getBillId() == $bill_id) {
                        $result = [
                            'success' => false,
                            'message' => 'EXPENSE_ALREADY_BELONG_TO_THIS_BILL_TEXT'
                        ];
                        goto end_of_function;
                    } else {
                        $result = [
                            'success' => false,
                            'message' => 'EXPENSE_ALREADY_BELONG_TO_ANOTHER_BILL_TEXT'
                        ];
                        goto end_of_function;
                    }
                }
                $expense->setBillId($bill_id);
                $bill = $expense->getBill();

                if ($bill instanceof Bill && $bill->belongsToGms()) {

                    if ($bill->getStatus() != Bill::STATUS_DRAFT && $bill->getStatus() != Bill::STATUS_REJECTED) {
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_BILL_ITEM_FAIL_TEXT',
                            "detail" => "can not add item to an a non draft bill"
                        ];
                        goto end_of_function;
                    }

                    $this->db->begin();
                    $bill->setTotal($bill_total);
                    $bill->setSubTotal($bill_subtotal);
                    $result = $bill->__quickUpdate();

                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_BILL_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $result = $expense->__quickUpdate();

                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_BILL_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    } else $result['message'] = 'SAVE_BILL_ITEM_SUCCESS_TEXT';
                    $this->db->commit();
                    ModuleModel::$bill = $bill;
                    $this->dispatcher->setParam('return', $result);
                } else {
                    $result = [
                        'success' => false,
                        'message' => 'BILL_NOT_FOUND_TEXT'
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
    public function removeBillItemAction($uuid)
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

                $bill = $expense->getBill();

                if ($bill instanceof Bill && $bill->belongsToGms()) {
                    if ($bill->getStatus() != Bill::STATUS_DRAFT && $bill->getStatus() != Bill::STATUS_REJECTED) {
                        $return = [
                            'success' => false,
                            'message' => 'SAVE_BILL_ITEM_FAIL_TEXT',
                            "detail" => "can not remove item from an a non draft bill"
                        ];
                        goto end_of_function;
                    }
                    $expense->setBillId(null);
                    $expense->setStatus(Expense::STATUS_DRAFT);

                    $this->db->begin();
                    $bill->setTotal($bill->getTotal() - $expense->getTotal());
                    $bill->setSubTotal($bill->getSubTotal() - $expense->getCost());
                    $result = $bill->__quickUpdate();

                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_BILL_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    }

                    $return = $expense->__quickUpdate();
                    if ($return['success'] == false) {
                        $this->db->rollback();
                        $return['message'] = 'REMOVE_BILL_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    } else {
                        $this->db->commit();
                        $return['message'] = 'REMOVE_BILL_ITEM_SUCCESS_TEXT';
                        ModuleModel::$bill = $bill;
                        $this->dispatcher->setParam('return', $return);
                    }
                } else {
                    $return = [
                        'success' => false,
                        'data' => $expense,
                        'bill' => $bill,
                        'message' => 'BILL_NOT_FOUND_TEXT'
                    ];
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function sentBillAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();

        $result = [
            'success' => false,
            'message' => 'BILL_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $bill = Bill::findFirstByUuid($uuid);

            if ($bill instanceof Bill && $bill->belongsToGms()) {

                if ($bill->getStatus() != Bill::STATUS_DRAFT && $bill->getStatus() != Bill::STATUS_REJECTED) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_BILL_FAIL_TEXT',
                        "detail" => "can not sent non draft bill"
                    ];
                    goto end_of_function;
                }
                $this->db->begin();
                $expenses = $bill->getExpenses();
                if (count($expenses) > 0) {
                    foreach ($expenses as $expense) {
                        $expense->setStatus(Expense::STATUS_PENDING);
                        $result = $expense->__quickUpdate();
                        if ($result['success'] == false) {
                            $this->db->rollback();
                            $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                            goto end_of_function;
                        }
                    }
                }
                $bill->setStatus(BILL::STATUS_PENDING);

                $result = $bill->__quickUpdate();

                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                    goto end_of_function;
                }
                $result['message'] = 'SENT_BILL_SUCCESS_TEXT';
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
    public function approveBillAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('approve', 'bill');

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $bill = Bill::findFirstByUuid($uuid);

            if ($bill instanceof Bill && $bill->belongsToGms()) {

                if ($bill->getStatus() != Bill::STATUS_PENDING) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_BILL_FAIL_TEXT',
                        "detail" => "can not approve non pending bill"
                    ];
                    goto end_of_function;
                }
                $expenses = $bill->getExpenses();
                $invoiceable_items = [];
                if (count($expenses) > 0) {
                    foreach ($expenses as $expense) {
                        $invoiceable_item = new InvoiceableItem();
                        $invoiceable_item->setCompanyId(ModuleModel::$company->getId());
                        $invoiceable_item->setNumber($invoiceable_item->generateNumber());
                        $invoiceable_items[$expense->getId()] = $invoiceable_item;
                    }
                }
                $this->db->begin();
                $expenses = $bill->getExpenses();
                if (count($expenses) > 0) {
                    foreach ($expenses as $expense) {
                        if ($expense->getStatus() != Expense::STATUS_APPROVED) {
                            $expense->setStatus(Expense::STATUS_APPROVED);
                            $expense_category = $expense->getExpenseCategory();
                            if($expense_category) {
                                if ($expense->getChargeableType() != Expense::CHARGE_TO_NONE && $expense_category->getCostPriceEnable() == ExpenseCategory::COST_PRICE_ENABLE) {
                                    if ($expense_category->getPrice() > 0) {
                                        $random = new Random();
                                        $uuid = $random->uuid();
                                        $invoiceable_items[$expense->getId()]->setUuid($uuid);
                                        $invoiceable_items[$expense->getId()]->setExpenseId($expense->getId());
                                        $invoiceable_items[$expense->getId()]->setTaxRuleId($expense->getTaxRuleId());
                                        $invoiceable_items[$expense->getId()]->setQuantity($expense->getQuantity());
                                        $invoiceable_items[$expense->getId()]->setPrice($expense->getQuantity() * $expense_category->getPrice());
                                        $invoiceable_items[$expense->getId()]->setTotal($invoiceable_item->getPrice() * (1 + (($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) ? $expense->getTaxRule()->getRate() / 100 : 0)));
                                        $invoiceable_items[$expense->getId()]->setCurrency($expense->getCurrency());
                                        $invoiceable_items[$expense->getId()]->setAccountId($expense->getAccountId());
                                        $invoiceable_items[$expense->getId()]->setRelocationId($expense->getRelocationId());
                                        $invoiceable_items[$expense->getId()]->setChargeTo($expense->getChargeableType());
                                        if ($expense->getChargeableType() == Expense::CHARGE_TO_EMPLOYEE) {
                                            $invoiceable_items[$expense->getId()]->setEmployeeId($expense->getRelocation() instanceof Relocation ? $expense->getRelocation()->getEmployeeId() : null);
                                            $invoiceable_items[$expense->getId()]->setAccountId($expense->getRelocation() instanceof Relocation ? $expense->getRelocation()->getHrCompanyId() : null);
                                        }
                                        $invoiceable_items[$expense->getId()]->setTaxRate(($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) ? $expense->getTaxRule()->getRate() : 0);
                                        $invoiceable_items[$expense->getId()]->setUnitPrice($expense_category->getPrice());
                                        $invoiceable_items[$expense->getId()]->setTotalTax($invoiceable_items[$expense->getId()]->getPrice() * $invoiceable_items[$expense->getId()]->getTaxRate() / 100);
                                        $invoiceable_items[$expense->getId()]->setExpenseCategoryId($expense_category->getId());
                                        $invoiceable_items[$expense->getId()]->setExpenseCategoryName($expense_category->getName());
                                        $invoiceable_items[$expense->getId()]->setDescription($expense->getNote());
                                        $result = $invoiceable_items[$expense->getId()]->__quickCreate();
                                        if ($result['success'] == false) {
                                            $this->db->rollback();
                                            $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                                            goto end_of_function;
                                        }
                                    }
                                } else if ($expense->getChargeableType() != Expense::CHARGE_TO_NONE && $expense_category->getCostPriceEnable() != ExpenseCategory::COST_PRICE_ENABLE) {
                                    $random = new Random();
                                    $uuid = $random->uuid();
                                    $invoiceable_items[$expense->getId()]->setUuid($uuid);
                                    $invoiceable_items[$expense->getId()]->setExpenseId($expense->getId());
                                    $invoiceable_items[$expense->getId()]->setTaxRuleId($expense->getTaxRuleId());
                                    $invoiceable_items[$expense->getId()]->setQuantity($expense->getQuantity());
                                    $invoiceable_items[$expense->getId()]->setPrice($expense->getPrice());
                                    $invoiceable_items[$expense->getId()]->setTotal($invoiceable_items[$expense->getId()]->getPrice() * (1 + (($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) ? $expense->getTaxRule()->getRate() / 100 : 0)));
                                    $invoiceable_items[$expense->getId()]->setCurrency($expense->getCurrency());
                                    $invoiceable_items[$expense->getId()]->setAccountId($expense->getAccountId());
                                    $invoiceable_items[$expense->getId()]->setRelocationId($expense->getRelocationId());
                                    $invoiceable_items[$expense->getId()]->setChargeTo($expense->getChargeableType());
                                    if ($expense->getChargeableType() == Expense::CHARGE_TO_EMPLOYEE) {
                                        $invoiceable_items[$expense->getId()]->setEmployeeId($expense->getRelocation() instanceof Relocation ? $expense->getRelocation()->getEmployeeId() : null);
                                        $invoiceable_items[$expense->getId()]->setAccountId($expense->getRelocation() instanceof Relocation ? $expense->getRelocation()->getHrCompanyId() : null);
                                    }
                                    $invoiceable_items[$expense->getId()]->setTaxRate(($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) ? $expense->getTaxRule()->getRate() : 0);
                                    $invoiceable_items[$expense->getId()]->setTotalTax($invoiceable_item->getPrice() * $invoiceable_item->getTaxRate() / 100);
                                    $invoiceable_items[$expense->getId()]->setExpenseCategoryId($expense_category->getId());
                                    $invoiceable_items[$expense->getId()]->setExpenseCategoryName($expense_category->getName());
                                    $invoiceable_items[$expense->getId()]->setDescription($expense->getNote());
                                    $result = $invoiceable_items[$expense->getId()]->__quickCreate();
                                    if ($result['success'] == false) {
                                        $this->db->rollback();
                                        $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                                        goto end_of_function;
                                    }
                                }
                            }
                            $result = $expense->__quickUpdate();
                            if ($result['success'] == false) {
                                $this->db->rollback();
                                $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                                goto end_of_function;
                            }
                        }
                    }
                }
                $bill->setStatus(BILL::STATUS_UNPAID);

                $result = $bill->__quickUpdate();

                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                    goto end_of_function;
                }
                $result['message'] = 'SENT_BILL_SUCCESS_TEXT';
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
    public function rejectBillAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('approve', 'bill');

        $result = [
            'success' => false,
            'message' => 'EXPENSE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $bill = Bill::findFirstByUuid($uuid);

            if ($bill instanceof Bill && $bill->belongsToGms()) {

                if ($bill->getStatus() != Bill::STATUS_PENDING) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_BILL_FAIL_TEXT',
                        "detail" => "can not reject non pending bill"
                    ];
                    goto end_of_function;
                }
                $this->db->begin();
                $expenses = $bill->getExpenses();
                if (count($expenses) > 0) {
                    foreach ($expenses as $expense) {
                        $expense->setStatus(Expense::STATUS_REJECTED);
                        $result = $expense->__quickUpdate();
                        if ($result['success'] == false) {
                            $this->db->rollback();
                            $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                            goto end_of_function;
                        }
                    }
                }
                $bill->setStatus(BILL::STATUS_REJECTED);

                $result = $bill->__quickUpdate();

                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_BILL_FAIL_TEXT';
                    goto end_of_function;
                }
                $result['message'] = 'SENT_BILL_SUCCESS_TEXT';
                $this->db->commit();
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
