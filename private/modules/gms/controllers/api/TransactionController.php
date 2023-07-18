<?php

namespace Reloday\Gms\Controllers\API;

use Elasticsearch\Endpoints\Cat\Help;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Help\TransactionHelper;
use Reloday\Gms\Models\Bill;
use Reloday\Gms\Models\Expense;
use Reloday\Gms\Models\FinancialAccount;
use Reloday\Gms\Models\InvoiceQuote;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Payment;
use Reloday\Gms\Models\PaymentMethod;
use Reloday\Gms\Models\ServiceProviderCompany;
use Reloday\Gms\Models\Transaction;
use Reloday\Gms\Models\TransactionType;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TransactionController extends BaseController
{

    /**
     * @return mixed
     */
    public function getPaymentMethodListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $methods = PaymentMethod::find([
            "conditions" => "1 = 1"
        ]);
        $result = [
            "success" => true,
            "data" => $methods
        ];
        $this->response->setJsonContent($result);
        $this->response->send();

    }

    /**
     * @Route("/bill", paths={module="gms"}, methods={"GET"}
     */
    public function getListTransactionAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['types'] = [];
        $types = Helpers::__getRequestValue('types');
        if (is_array($types) && count($types) > 0) {
            foreach ($types as $type) {
                $params['types'][] = $type->value;
            }
        }
        $params["financial_account_id"] = Helpers::__getRequestValue('financial_account_id');
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['order'] = Helpers::__getRequestValue('order');
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
        $transactions = Transaction::__findWithFilter($params, $ordersConfig);
        if (!$transactions["success"]) {
            $this->response->setJsonContent([
                'success' => false,
                'params' => $params,
                'queryBuider' => $transactions
            ]);
        } else {
            $this->response->setJsonContent([
                'success' => true,
                'data' => $transactions["data"],
                'params' => $params,
                'queryBuider' => $transactions
            ]);
        }
        $this->response->send();
    }

    public function getListTransactionOfObjectAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        $payments = [];
        $bill_id = Helpers::__getRequestValue("bill_id");
        if ($bill_id > 0) {
            $bill = Bill::findFirstById($bill_id);
            if ($bill instanceof Bill and $bill->belongsToGms()) {
                $payments = $bill->getPayments();
            }
        } else {
            $expense_id = Helpers::__getRequestValue("expense_id");
            if ($expense_id > 0) {
                $expense = Expense::findFirstById($expense_id);
                if ($expense instanceof Expense and $expense->belongsToGms()) {
                    $payments = $expense->getPayments();
                }
            } else {
                $invoice_quote_id = Helpers::__getRequestValue("invoice_quote_id");
                if ($invoice_quote_id > 0) {
                    $invoice_quote = InvoiceQuote::findFirstById($invoice_quote_id);
                    if ($invoice_quote instanceof InvoiceQuote and $invoice_quote->belongsToGms()) {
                        $payments = $invoice_quote->getPayments();
                    }
                }
            }
        }

        $transactions = [];
        if (count($payments) > 0) {
            foreach ($payments as $payment) {
                $transaction = $payment->getTransactionInvoicing()->toArray();
                $transaction["payment_method_name"] = $payment->getPaymentMethod()->getName();
                $transactions[] = $transaction;
            }
        }

        $result = [
            "success" => true,
            "data" => $transactions
        ];

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function getTargetOfTransactionAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $item_arrays = [];
        $currency = Helpers::__getRequestValue('currency');
        $direction = Helpers::__getRequestValue('direction');

        if ($direction == Transaction::DIRECTION_IN) {
            $items = InvoiceQuote::find([
                "conditions" => "company_id = :company_id: and type=:type: and status IN ({statuses:array} ) and currency= :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => InvoiceQuote::TYPE_INVOICE,
                    "statuses" => [InvoiceQuote::STATUS_APPROVED, InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID],
                    "currency" => $currency
                ]
            ]);
            if (count($items) > 0) {
                foreach ($items as $item) {
                    $item_array = $item->toArray();
                    $item_array['account_name'] = $item->getAccount()->getName();
                    $item_array["type"] = "INVOICE_TEXT";
                    $item_arrays[] = $item_array;
                }
            }
        } else {
            $items = Bill::find([
                "conditions" => "company_id = :company_id: and status  IN ({statuses:array} ) and currency= :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "statuses" => [Bill::STATUS_UNPAID, Bill::STATUS_PAID, Bill::STATUS_PARTIAL_PAID],
                    "currency" => $currency
                ]
            ]);
            if (count($items) > 0) {
                foreach ($items as $item) {
                    $item_array = $item->toArray();
                    $item_array['account_name'] = $item->getServiceProviderCompany()->getName();
                    $item_array["type"] = "BILL_TEXT";
                    $item_arrays[] = $item_array;
                }
            }
            $items = Expense::find([
                "conditions" => "company_id = :company_id: and status  IN ({statuses:array} ) and currency= :currency: and bill_id IS NULL",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "statuses" => [Expense::STATUS_APPROVED, Expense::STATUS_PAID],
                    "currency" => $currency
                ]
            ]);
            if (count($items) > 0) {
                foreach ($items as $item) {
                    $item_array = $item->toArray();
                    $item_array['account_name'] = $item->getServiceProviderCompany() instanceof ServiceProviderCompany ? $item->getServiceProviderCompany()->getName() : "";
                    $item_array["type"] = "EXPENSE_TEXT";
                    $item_array["reference"] = $item->getNumber();
                    $item_arrays[] = $item_array;
                }
            }
            $items = InvoiceQuote::find([
                "conditions" => "company_id = :company_id: and type=:type: and status IN ({statuses:array} ) and currency= :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                    "statuses" => [InvoiceQuote::STATUS_APPROVED, InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID],
                    "currency" => $currency
                ]
            ]);
            if (count($items) > 0) {
                foreach ($items as $item) {
                    $item_array = $item->toArray();
                    $item_array['account_name'] = $item->getAccount()->getName();
                    $item_array["type"] = "CREDIT_NOTE_TEXT";
                    $item_arrays[] = $item_array;
                }
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $item_arrays
        ]);
        $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function getDetailTargetOfTransactionAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $targetItem = InvoiceQuote::findFirstByUuid($uuid);
        $targetItemArray = [];
        if ($targetItem) {
            $targetItemArray = $targetItem->toArray();
            $targetItemArray['account_name'] = $targetItem->getAccount()->getName();
            if ($targetItem->getType() == InvoiceQuote::TYPE_CREDIT_NOTE) {
                $targetItemArray['type'] = 'CREDIT_NOTE_TEXT';
            }
            if ($targetItem->getType() == InvoiceQuote::TYPE_INVOICE) {
                $targetItemArray['type'] = 'INVOICE_TEXT';
            }
            if ($targetItem->getType() == InvoiceQuote::TYPE_QUOTE) {
                $targetItemArray['type'] = 'QUOTE_TEXT';
            }
        }

        if (!($targetItem && $targetItem->belongsToGms())) {
            $targetItem = Bill::findFirstByUuid($uuid);
            if ($targetItem && $targetItem->belongsToGms()) {
                $targetItemArray = $targetItem->toArray();
                $targetItemArray['type'] = 'BILL_TEXT';
                $targetItemArray['account_name'] = $targetItem->getServiceProviderCompany()->getName();
            }
        }

        if (!($targetItem && $targetItem->belongsToGms())) {
            $targetItem = Expense::findFirstByUuid($uuid);
            if ($targetItem && $targetItem->belongsToGms()) {
                $targetItemArray = $targetItem->toArray();
                $targetItemArray['type'] = 'EXPENSE_TEXT';
                $targetItemArray['account_name'] = $targetItem->getServiceProviderCompany()->getName();
            }
        }

        if (!($targetItem && $targetItem->belongsToGms())) {
            $result = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
        } else {
            $result = [
                'success' => true,
                'data' => $targetItemArray
            ];
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createTransactionAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $description = Helpers::__getRequestValue("description");
        $direction = Helpers::__getRequestValue("direction");
        $date = Helpers::__getRequestValue("date");
        $financial_account_id = Helpers::__getRequestValue("financial_account_id");
        $payment_method_id = Helpers::__getRequestValue("payment_method_id");
        $amount = Helpers::__getRequestValue("amount");
        $currency = Helpers::__getRequestValue("currency");
        $target_uuid = Helpers::__getRequestValue('target_uuid');
        $target = Helpers::__getRequestValue("target");
        $this->db->begin();
        $transaction = new Transaction();
        $transaction->setDescription($description);
        $transaction->setDirection($direction);
        $transaction->setTransactionTypeId(TransactionType::TYPE_PAYMENT);
        $transaction->setCompanyId(ModuleModel::$company->getId());
        $transaction->setAmount($amount);
        $transaction->setDate($date);
        $transaction->setCurrency($currency);
        $transaction->setFinancialAccountId($financial_account_id);

        $payment = new Payment();
        $payment->setPaymentMethodId($payment_method_id);
        $payment->setCompanyId(ModuleModel::$company->getId());
        $payment->setAmount($amount);
        $payment->setCurrency($currency);
        $payment->setDate($date);

        $financial_account = FinancialAccount::findFirstById($financial_account_id);
        if (!$financial_account instanceof FinancialAccount || !$financial_account->belongsToGms()) {
            $this->db->rollback();
            $result = [
                "success" => false,
                "message" => "CREATE_TRANSACTION_FAIL_TEXT",
                "errorType" => "errorFinancialAccountNotFound",
                "detail" => $financial_account
            ];
            goto end_of_function;
        }

        if (Helpers::__isValidUuid($target_uuid)) {
            $targetObject = TransactionHelper::__getTargetItem($target_uuid);
        }else{
            $targetObject = (array)$target;
        }

        if(isset($targetObject['type']) && isset($targetObject['id'])) {
            switch ($targetObject['type']) {
                case "INVOICE_TEXT":
                    $invoice = InvoiceQuote::findFirstById($targetObject['id']);
                    if (!$invoice instanceof InvoiceQuote || !$invoice->belongsToGms() || !$invoice->getType() == InvoiceQuote::TYPE_INVOICE) {
                        $this->db->rollback();
                        $result = [
                            "success" => false,
                            "errorType" => "errorInvoiceNotFound",
                            "message" => "CREATE_TRANSACTION_FAIL_TEXT",
                            "detail" => $invoice
                        ];
                        goto end_of_function;
                    }
                    $transaction->setAccountId($invoice->getAccountId());
                    $payment->setInvoiceQuoteId($invoice->getId());
                    $invoice->setTotalPaid($invoice->getTotalPaid() + $amount);
                    if ($invoice->getTotalPaid() >= $invoice->getTotal()) {
                        $invoice->setStatus(InvoiceQuote::STATUS_PAID);
                        if ($invoice->getIsPaid() != 1) {
                            $invoice->setPaidDate(time());
                            $invoice->setIsPaid(1);
                        }
                    } else {
                        $invoice->setIsPaid(0);
                        $invoice->setPaidDate(null);
                        if ($invoice->getTotalPaid() > 0) {
                            $invoice->setStatus(InvoiceQuote::STATUS_PARTIAL_PAID);
                        } else {
                            $invoice->setStatus(InvoiceQuote::STATUS_APPROVED);
                        }
                    }
                    $result = $invoice->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $financial_account->setAmount($financial_account->getAmount() + $amount);
                    break;
                case "BILL_TEXT" :
                    $bill = Bill::findFirstById($targetObject['id']);

                    if (!$bill || !$bill instanceof Bill || !$bill->belongsToGms() || ($bill->getStatus() != Bill::STATUS_UNPAID && $bill->getStatus() != Bill::STATUS_PAID && $bill->getStatus() != Bill::STATUS_PARTIAL_PAID)) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            "errorType" => "errorBillNotFound",
                            'message' => 'CREATE_TRANSACTION_FAIL_TEXT',
                            "detail" => $bill
                        ];
                        goto end_of_function;
                    }
                    $transaction->setServiceProviderCompanyId($bill->getServiceProviderCompanyId());
                    $payment->setBillId($bill->getId());
                    $bill->setTotalPaid($bill->getTotalPaid() + $amount);
                    if ($bill->getTotalPaid() >= $bill->getTotal()) {
                        $bill->setStatus(Bill::STATUS_PAID);
                        if ($bill->getIsPaid() != 1) {
                            $bill->setPaidDate(time());
                            $bill->setIsPaid(1);
                        }
                        $expenses = $bill->getExpenses();
                        if (count($expenses) > 0) {
                            foreach ($expenses as $expense) {
                                $expense->setTotalPaid($expense->getTotal());
                                $expense->setStatus(Expense::STATUS_PAID);
                                $result = $expense->__quickUpdate();
                                if ($result['success'] == false) {
                                    $this->db->rollback();
                                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }
                        }
                    } else {
                        $bill->setIsPaid(0);
                        $bill->setPaidDate(null);
                        if ($bill->getTotalPaid() > 0) {
                            $bill->setStatus(Bill::STATUS_PARTIAL_PAID);
                        } else {
                            $bill->setStatus(Bill::STATUS_UNPAID);
                        }
                        $expenses = $bill->getExpenses();
                        if (count($expenses) > 0) {
                            foreach ($expenses as $expense) {
                                $expense->setTotalPaid(null);
                                $expense->setStatus(Expense::STATUS_APPROVED);
                                $result = $expense->__quickUpdate();
                                if ($result['success'] == false) {
                                    $this->db->rollback();
                                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }
                        }
                    }


                    $result = $bill->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $financial_account->setAmount($financial_account->getAmount() - $amount);
                    break;
                case "EXPENSE_TEXT":
                    $expense = Expense::findFirstById($targetObject['id']);
                    if (!$expense instanceof Expense || !$expense->belongsToGms() || $expense->getStatus() != Expense::STATUS_APPROVED) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            "errorType" => "errorExpenseNotFound",
                            'message' => 'CREATE_TRANSACTION_FAIL_TEXT',
                            "detail" => $expense
                        ];
                        goto end_of_function;
                    }
                    $transaction->setServiceProviderCompanyId($expense->getServiceProviderId());
                    $payment->setExpenseId($expense->getId());
                    $expense->setTotalPaid($expense->getTotalPaid() + $amount);
                    if ($expense->getTotalPaid() >= $expense->getTotal()) {
                        $expense->setStatus(Expense::STATUS_PAID);
                    } else {
                        $expense->setStatus(Expense::STATUS_APPROVED);
                    }
                    $result = $expense->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $financial_account->setAmount($financial_account->getAmount() - $amount);
                    break;
                case "CREDIT_NOTE_TEXT":
                    $invoice = InvoiceQuote::findFirstById($targetObject['id']);
                    if (!$invoice instanceof InvoiceQuote || !$invoice->belongsToGms() || !$invoice->getType() == InvoiceQuote::TYPE_CREDIT_NOTE) {
                        $this->db->rollback();
                        $result = [
                            "success" => false,
                            "errorType" => "errorCreditNoteNotFound",
                            "message" => "CREATE_TRANSACTION_FAIL_TEXT",
                            "detail" => $invoice
                        ];
                        goto end_of_function;
                    }
                    $transaction->setAccountId($invoice->getAccountId());
                    $payment->setInvoiceQuoteId($invoice->getId());
                    $invoice->setTotalPaid($invoice->getTotalPaid() + $amount);
                    if ($invoice->getTotalPaid() >= $invoice->getTotal()) {
                        $invoice->setStatus(InvoiceQuote::STATUS_PAID);
                        if ($invoice->getIsPaid() != 1) {
                            $invoice->setPaidDate(time());
                            $invoice->setIsPaid(1);
                        }
                    } else {
                        $invoice->setIsPaid(0);
                        $invoice->setPaidDate(null);
                        if ($invoice->getTotalPaid() > 0) {
                            $invoice->setStatus(InvoiceQuote::STATUS_PARTIAL_PAID);
                        } else {
                            $invoice->setStatus(InvoiceQuote::STATUS_APPROVED);
                        }
                    }
                    $result = $invoice->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $financial_account->setAmount($financial_account->getAmount() - $amount);
                    break;
                default:
                    $this->db->rollback();
                    $result = [
                        "success" => false,
                        "message" => "CREATE_TRANSACTION_FAIL_TEXT",
                        "detail" => $targetObject
                    ];
                    goto end_of_function;
                    break;
            }
        }

        $result = $financial_account->__quickUpdate();
        if ($result['success'] == false) {
            $this->db->rollback();
            $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
            goto end_of_function;
        }

        $transaction->setData();
        $result = $transaction->__quickCreate();
        if ($result['success'] == false) {
            $this->db->rollback();
            $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
            goto end_of_function;
        }

        $payment->setTransactionId($transaction->getId());

        $payment->setData();
        $result = $payment->__quickCreate();
        if ($result['success'] == false) {
            $this->db->rollback();
            $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
            goto end_of_function;
        }

        $this->db->commit();
        $result = [
            "success" => true,
            "message" => 'SAVE_TRANSACTION_SUCCESS_TEXT',
            "data" => $payment,
            "bill" => isset($bill) ? $bill : null,
            "expense" => isset($expense) ? $expense : null,
            "invoice" => isset($invoice) ? $invoice : null,
        ];
        ModuleModel::$transaction = $transaction;
        $this->dispatcher->setParam('return', $result);

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailTransactionAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $transaction = Transaction::findFirstByUuid($uuid);

            if ($transaction instanceof Transaction && $transaction->belongsToGms()) {
                $item = $transaction->toArray();
                $item["date"] = intval($transaction->getDate());
                $payments = $transaction->getPayments();
                $first_payment = $payments[0];
                $item["payment_method_id"] = intval($first_payment->getPaymentMethodId());
                $item["target_id"] = intval($first_payment->getInvoiceQuoteId() > 0 ? $first_payment->getInvoiceQuoteId() : ($first_payment->getExpenseId() > 0 ? $first_payment->getExpenseId() : $first_payment->getBillId()));
                $item["target_uuid"] = "";

                if ($first_payment->getInvoiceQuoteId() > 0) {
                    $item["target_uuid"] = $first_payment->getInvoiceQuote()->getUuid();
                }
                if ($first_payment->getExpenseId() > 0) {
                    $item["target_uuid"] = $first_payment->getExpense()->getUuid();
                }
                if ($first_payment->getBillId() > 0) {
                    $item["target_uuid"] = $first_payment->getBill()->getUuid();
                }
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
     * @return mixed
     */
    public function editTransactionAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $description = Helpers::__getRequestValue("description");
        $date = Helpers::__getRequestValue("date");
        $financial_account_id = Helpers::__getRequestValue("financial_account_id");
        $payment_method_id = Helpers::__getRequestValue("payment_method_id");
        $amount = Helpers::__getRequestValue("amount");
        $currency = Helpers::__getRequestValue("currency");
        $target_uuid = Helpers::__getRequestValue("target_uuid");
        $target = Helpers::__getRequestValue("target");
        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $financial_account = FinancialAccount::findFirstById($financial_account_id);
        if (!$financial_account instanceof FinancialAccount || !$financial_account->belongsToGms()) {
            $result = [
                "success" => false,
                "message" => "SAVE_TRANSACTION_FAIL_TEXT",
                "detail" => $financial_account
            ];
            goto end_of_function;
        }

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $transaction = Transaction::findFirstByUuid($uuid);

            if ($transaction instanceof Transaction && $transaction->belongsToGms()) {
                $this->db->begin();
                $old_amount = $transaction->getAmount();
                $transaction->setDescription($description);
                $transaction->setAmount($amount);
                $transaction->setDate($date);
                $transaction->setCurrency($currency);
                $transaction->setFinancialAccountId($financial_account_id);
                $payments = $transaction->getPayments();
                $payment = $payments[0];
                $payment->setPaymentMethodId($payment_method_id);
                $payment->setAmount($amount);
                $payment->setCurrency($currency);
                $payment->setDate($date);

                switch ($target->type) {
                    case "INVOICE_TEXT":
                        $invoice = InvoiceQuote::findFirstById($target->id);
                        if (!$invoice instanceof InvoiceQuote || !$invoice->belongsToGms() || !$invoice->getType() == InvoiceQuote::TYPE_INVOICE) {
                            $this->db->rollback();
                            $result = [
                                "success" => false,
                                "message" => "SAVE_TRANSACTION_FAIL_TEXT",
                                "detail" => $invoice
                            ];
                            goto end_of_function;
                        }
                        $transaction->setAccountId($invoice->getAccountId());
                        $payment->setInvoiceQuoteId($invoice->getId());
                        $invoice->setTotalPaid($invoice->getTotalPaid() - $old_amount + $amount);
                        if ($invoice->getTotalPaid() >= $invoice->getTotal()) {
                            $invoice->setStatus(InvoiceQuote::STATUS_PAID);
                            if ($invoice->getIsPaid() != 1) {
                                $invoice->setPaidDate(time());
                                $invoice->setIsPaid(1);
                            }
                        } else {
                            $invoice->setIsPaid(0);
                            $invoice->setPaidDate(null);
                            if ($invoice->getTotalPaid() > 0) {
                                $invoice->setStatus(InvoiceQuote::STATUS_PARTIAL_PAID);
                            } else {
                                $invoice->setStatus(InvoiceQuote::STATUS_APPROVED);
                            }
                        }
                        $result = $invoice->__quickUpdate();
                        if ($result['success'] == false) {
                            $this->db->rollback();
                            $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                            goto end_of_function;
                        }

                        $financial_account->setAmount($financial_account->getAmount() - $old_amount + $amount);
                        break;
                    case "BILL_TEXT" :
                        $bill = Bill::findFirstById($target->id);

                        if (!$bill || !$bill instanceof Bill || !$bill->belongsToGms() || ($bill->getStatus() != Bill::STATUS_UNPAID && $bill->getStatus() != Bill::STATUS_PAID && $bill->getStatus() != Bill::STATUS_PARTIAL_PAID)) {
                            $this->db->rollback();
                            $result = [
                                'success' => false,
                                'message' => 'SAVE_TRANSACTION_FAIL_TEXT',
                                "detail" => $bill
                            ];
                            goto end_of_function;
                        }
                        $transaction->setServiceProviderCompanyId($bill->getServiceProviderCompanyId());
                        $payment->setBillId($bill->getId());
                        $bill->setTotalPaid($bill->getTotalPaid() - $old_amount + $amount);
                        if ($bill->getTotalPaid() >= $bill->getTotal()) {
                            $bill->setStatus(Bill::STATUS_PAID);
                            if ($bill->getIsPaid() != 1) {
                                $bill->setPaidDate(time());
                                $bill->setIsPaid(1);
                            }
                            $expenses = $bill->getExpenses();
                            if (count($expenses) > 0) {
                                foreach ($expenses as $expense) {
                                    $expense->setTotalPaid($expense->getTotal());
                                    $expense->setStatus(Expense::STATUS_PAID);
                                    $result = $expense->__quickUpdate();
                                    if ($result['success'] == false) {
                                        $this->db->rollback();
                                        $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                                        goto end_of_function;
                                    }
                                }
                            }
                        } else {
                            $bill->setIsPaid(0);
                            $bill->setPaidDate(null);
                            if ($bill->getTotalPaid() > 0) {
                                $bill->setStatus(Bill::STATUS_PARTIAL_PAID);
                            } else {
                                $bill->setStatus(Bill::STATUS_UNPAID);
                            }
                            $expenses = $bill->getExpenses();
                            if (count($expenses) > 0) {
                                foreach ($expenses as $expense) {
                                    $expense->setTotalPaid(null);
                                    $expense->setStatus(Expense::STATUS_APPROVED);
                                    $result = $expense->__quickUpdate();
                                    if ($result['success'] == false) {
                                        $this->db->rollback();
                                        $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                                        goto end_of_function;
                                    }
                                }
                            }
                        }

                        $result = $bill->__quickUpdate();
                        if ($result['success'] == false) {
                            $this->db->rollback();
                            $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                            goto end_of_function;
                        }

                        $financial_account->setAmount($financial_account->getAmount() + $old_amount - $amount);
                        break;
                    case "EXPENSE_TEXT":
                        $expense = Expense::findFirstById($target->id);
                        if (!$expense instanceof Expense || !$expense->belongsToGms() || ($expense->getStatus() != Expense::STATUS_APPROVED && $expense->getStatus() != Expense::STATUS_PAID)) {
                            $this->db->rollback();
                            $result = [
                                'success' => false,
                                'message' => 'SAVE_TRANSACTION_FAIL_TEXT',
                                "detail" => $expense
                            ];
                            goto end_of_function;
                        }
                        $transaction->setServiceProviderCompanyId($expense->getServiceProviderId());
                        $payment->setExpenseId($expense->getId());
                        $expense->setTotalPaid($expense->getTotalPaid() - $old_amount + $amount);
                        if ($expense->getTotalPaid() >= $expense->getTotal()) {
                            $expense->setStatus(Expense::STATUS_PAID);
                        } else {
                            $expense->setStatus(Expense::STATUS_APPROVED);
                        }
                        $result = $expense->__quickUpdate();
                        if ($result['success'] == false) {
                            $this->db->rollback();
                            $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                            goto end_of_function;
                        }
                        $financial_account->setAmount($financial_account->getAmount() + $old_amount - $amount);
                        break;
                    case "CREDIT_NOTE_TEXT":
                        $invoice = InvoiceQuote::findFirstById($target->id);
                        if (!$invoice instanceof InvoiceQuote || !$invoice->belongsToGms() || !$invoice->getType() == InvoiceQuote::TYPE_CREDIT_NOTE) {
                            $this->db->rollback();
                            $result = [
                                "success" => false,
                                "message" => "CREATE_TRANSACTION_FAIL_TEXT",
                                "detail" => $invoice
                            ];
                            goto end_of_function;
                        }
                        $transaction->setAccountId($invoice->getAccountId());
                        $payment->setInvoiceQuoteId($invoice->getId());
                        $invoice->setTotalPaid($invoice->getTotalPaid() - $old_amount + $amount);
                        if ($invoice->getTotalPaid() >= $invoice->getTotal()) {
                            $invoice->setStatus(InvoiceQuote::STATUS_PAID);
                            if ($invoice->getIsPaid() != 1) {
                                $invoice->setPaidDate(time());
                                $invoice->setIsPaid(1);
                            }
                        } else {
                            $invoice->setIsPaid(0);
                            $invoice->setPaidDate(null);
                            if ($invoice->getTotalPaid() > 0) {
                                $invoice->setStatus(InvoiceQuote::STATUS_PARTIAL_PAID);
                            } else {
                                $invoice->setStatus(InvoiceQuote::STATUS_APPROVED);
                            }
                        }
                        $result = $invoice->__quickUpdate();
                        if ($result['success'] == false) {
                            $this->db->rollback();
                            $result['message'] = 'CREATE_TRANSACTION_FAIL_TEXT';
                            goto end_of_function;
                        }
                        $financial_account->setAmount($financial_account->getAmount() + $old_amount - $amount);
                        break;
                    default:
                        $this->db->rollback();
                        $result = [
                            "success" => false,
                            "message" => "SAVE_TRANSACTION_FAIL_TEXT",
                            "detail" => $target
                        ];
                        goto end_of_function;
                        break;
                }


                $result = $financial_account->__quickUpdate();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                    goto end_of_function;
                }

                $result = $transaction->__quickUpdate();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                    goto end_of_function;
                }

                $payment->setTransactionId($transaction->getId());
                $result = $payment->__quickUpdate();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                    goto end_of_function;
                }

                $this->db->commit();
                $result = [
                    "success" => true,
                    "message" => 'SAVE_TRANSACTION_SUCCESS_TEXT',
                    "data" => $payment,
                    "bill" => isset($bill) ? $bill : null,
                    "expense" => isset($expense) ? $expense : null,
                    "invoice" => isset($invoice) ? $invoice : null,
                ];
                ModuleModel::$transaction = $transaction;
                $this->dispatcher->setParam('return', $result);
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeTransactionAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $transaction = Transaction::findFirstByUuid($uuid);

            if ($transaction instanceof Transaction && $transaction->belongsToGms()) {
                $amount = $transaction->getAmount();
                $financial_account = $transaction->getFinancialAccount();
                $this->db->begin();
                $payments = $transaction->getPayments();
                $payment = $payments[0];

                if ($payment->getInvoiceQuoteId() > 0) {
                    $invoice = $payment->getInvoiceQuote();
                    if (!$invoice instanceof InvoiceQuote || !$invoice->belongsToGms() ||
                        ((!$invoice->getType() == InvoiceQuote::TYPE_INVOICE && $transaction->getDirection() == Transaction::DIRECTION_IN)
                            || (!$invoice->getType() == InvoiceQuote::TYPE_CREDIT_NOTE && $transaction->getDirection() == Transaction::DIRECTION_OUT))) {
                        $this->db->rollback();
                        $result = [
                            "success" => false,
                            "message" => "SAVE_TRANSACTION_FAIL_TEXT",
                            "detail" => $invoice
                        ];
                        goto end_of_function;
                    }
                    $invoice->setTotalPaid($invoice->getTotalPaid() - $amount);
                    if ($invoice->getTotalPaid() >= $invoice->getTotal()) {
                        $invoice->setStatus(InvoiceQuote::STATUS_PAID);
                        if ($invoice->getIsPaid() != 1) {
                            $invoice->setPaidDate(time());
                            $invoice->setIsPaid(1);
                        }
                    } else {
                        $invoice->setIsPaid(0);
                        $invoice->setPaidDate(null);
                        if ($invoice->getTotalPaid() > 0) {
                            $invoice->setStatus(InvoiceQuote::STATUS_PARTIAL_PAID);
                        } else {
                            $invoice->setStatus(InvoiceQuote::STATUS_APPROVED);
                        }
                    }
                    $result = $invoice->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                        goto end_of_function;
                    }
                } else if ($payment->getBillId() > 0) {
                    $bill = $payment->getBill();

                    if (!$bill || !$bill instanceof Bill || !$bill->belongsToGms() || ($bill->getStatus() != Bill::STATUS_UNPAID && $bill->getStatus() != Bill::STATUS_PAID && $bill->getStatus() != Bill::STATUS_PARTIAL_PAID)) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_TRANSACTION_FAIL_TEXT',
                            "detail" => $bill
                        ];
                        goto end_of_function;
                    }
                    $transaction->setServiceProviderCompanyId($bill->getServiceProviderCompanyId());
                    $payment->setBillId($bill->getId());
                    $bill->setTotalPaid($bill->getTotalPaid() - $amount);
                    if ($bill->getTotalPaid() >= $bill->getTotal()) {
                        $bill->setStatus(Bill::STATUS_PAID);
                        if ($bill->getIsPaid() != 1) {
                            $bill->setPaidDate(time());
                            $bill->setIsPaid(1);
                        }
                        $expenses = $bill->getExpenses();
                        if (count($expenses) > 0) {
                            foreach ($expenses as $expense) {
                                $expense->setTotalPaid($expense->getTotal());
                                $expense->setStatus(Expense::STATUS_PAID);
                                $result = $expense->__quickUpdate();
                                if ($result['success'] == false) {
                                    $this->db->rollback();
                                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }
                        }
                    } else {
                        $bill->setIsPaid(0);
                        $bill->setPaidDate(null);
                        if ($bill->getTotalPaid() > 0) {
                            $bill->setStatus(Bill::STATUS_PARTIAL_PAID);
                        } else {
                            $bill->setStatus(Bill::STATUS_UNPAID);
                        }
                        $expenses = $bill->getExpenses();
                        if (count($expenses) > 0) {
                            foreach ($expenses as $expense) {
                                $expense->setTotalPaid(null);
                                $expense->setStatus(Expense::STATUS_APPROVED);
                                $result = $expense->__quickUpdate();
                                if ($result['success'] == false) {
                                    $this->db->rollback();
                                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }
                        }
                    }

                    $result = $bill->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                        goto end_of_function;
                    }
                } else {
                    $expense = $payment->getExpense();
                    if (!$expense instanceof Expense || !$expense->belongsToGms() || ($expense->getStatus() != Expense::STATUS_APPROVED && $expense->getStatus() != Expense::STATUS_PAID)) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_TRANSACTION_FAIL_TEXT',
                            "detail" => $expense
                        ];
                        goto end_of_function;
                    }
                    $transaction->setServiceProviderCompanyId($expense->getServiceProviderId());
                    $payment->setExpenseId($expense->getId());
                    $expense->setTotalPaid($expense->getTotalPaid() - $amount);
                    if ($expense->getTotalPaid() >= $expense->getTotal()) {
                        $expense->setStatus(Expense::STATUS_PAID);
                    } else {
                        $expense->setStatus(Expense::STATUS_APPROVED);
                    }
                    $result = $expense->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                        goto end_of_function;
                    }
                }


                if ($transaction->getDirection() == Transaction::DIRECTION_OUT) {
                    $financial_account->setAmount($financial_account->getAmount() + $amount);
                } else {
                    $financial_account->setAmount($financial_account->getAmount() - $amount);
                }
                $result = $financial_account->__quickUpdate();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_TRANSACTION_FAIL_TEXT';
                    goto end_of_function;
                }

                $result = $payment->__quickRemove();
                if ($result["success"] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }
                ModuleModel::$transaction = $transaction;

                $result = $transaction->__quickRemove();
                if ($result["success"] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }
                $this->db->commit();
                $result["bill"] = isset($bill) && $bill instanceof Bill ? $bill->toArray() : null;
                $result["expense"] = isset($expense) && $expense instanceof Expense ? $expense->toArray() : null;
                $result["invoice"] = isset($invoice) && $invoice instanceof InvoiceQuote ? $invoice->toArray() : null;
                $this->dispatcher->setParam('return', $result);
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
