<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security\Random;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Gms\Models\AccountProductPricing;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanySetting;
use Reloday\Gms\Models\CompanySettingDefault;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\Expense;
use Reloday\Gms\Models\ExpenseCategory;
use Reloday\Gms\Models\InvoiceQuote;
use Reloday\Gms\Models\InvoiceQuoteItem;
use Reloday\Gms\Models\InvoiceTemplate;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectAvatar;
use Reloday\Gms\Models\PdfObject;
use Reloday\Gms\Models\ProductPricing;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\TaxRule;
use Reloday\Application\Lib\ConstantHelper;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class InvoiceController extends BaseController
{
    /**
     * @Route("/invoice", paths={module="gms"}, methods={"GET"}
     */
    public function getListInvoiceQuoteAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $params['currencies'] = [];
        $currencies = Helpers::__getRequestValue('currencies');
        if (is_array($currencies) && count($currencies) > 0) {
            foreach ($currencies as $currency) {
                $params['currencies'][] = $currency->code;
            }
        }
        $params['type'] = Helpers::__getRequestValue('type');
        switch ($params['type']) {
            case InvoiceQuote::TYPE_CREDIT_NOTE:
                $this->checkAcl('index', 'credit_note');
                break;
            case InvoiceQuote::TYPE_INVOICE:
                $this->checkAcl('index', 'invoice');
                break;
            case InvoiceQuote::TYPE_QUOTE:
                $this->checkAcl('index', 'quote');
                break;
            default:
                $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'NO_PERMISSION_TEXT'
                ]);
                goto end_of_function;

        }
        $params['statuses'] = Helpers::__getRequestValue('statuses');
        $params['statuses'] = [];
        $statuses = Helpers::__getRequestValue('statuses');
        if (is_array($statuses) && count($statuses) > 0) {
            foreach ($statuses as $status) {
                $params['statuses'][] = $status->value;
            }
        }
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['currency'] = Helpers::__getRequestValue('currency');
        $params['status'] = Helpers::__getRequestValue('status');
        $params['account_id'] = Helpers::__getRequestValue('account_id');
        $params['charge_to'] = Helpers::__getRequestValue('charge_to');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['query'] = Helpers::__getRequestValue('query');

//        $orders = Helpers::__getRequestValue('orders');
//        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        $params['employee_id'] = Helpers::__getRequestValue('employee_id');
        $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');

        /***** new filter ******/
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');
        $params['nopaging'] = Helpers::__getRequestValue('nopaging');

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


        $logs = InvoiceQuote::__findWithFilter($params, $ordersConfig);

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
        end_of_function:
        $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createFirstInvoiceAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $type = Helpers::__getRequestValue('type');
        switch ($type) {
            case InvoiceQuote::TYPE_CREDIT_NOTE:
                $this->checkAcl('create', 'credit_note');
                $error_message = 'SAVE_CREDIT_NOTE_FAIL_TEXT';
                $success_message = 'SAVE_CREDIT_NOTE_SUCCESS_TEXT';
                break;
            case InvoiceQuote::TYPE_INVOICE:
                $this->checkAcl('create', 'invoice');
                $error_message = 'SAVE_INVOICE_FAIL_TEXT';
                $success_message = 'SAVE_INVOICE_SUCCESS_TEXT';
                break;
            case InvoiceQuote::TYPE_QUOTE:
                $this->checkAcl('create', 'quote');
                $error_message = 'SAVE_QUOTEE_FAIL_TEXT';
                $success_message = 'SAVE_QUOTE_SUCCESS_TEXT';
                break;
            default:
                $result = [
                    'success' => false,
                    'message' => 'NO_PERMISSION_TEXT'
                ];
                goto end_of_function;
        }

        $uuid = Helpers::__getRequestValue('uuid');
        $products = [];
        $items = [];

        if (Helpers::__isValidUuid($uuid)) {
            $invoiceOriginal = InvoiceQuote::findFirstByUuid($uuid);
            $invoice = clone $invoiceOriginal;
            if (!$invoice instanceof InvoiceQuote || ($invoice->getType() != InvoiceQuote::TYPE_QUOTE && $type == InvoiceQuote::TYPE_INVOICE) || ($invoice->getType() != InvoiceQuote::TYPE_INVOICE && $type == InvoiceQuote::TYPE_CREDIT_NOTE)) {
                $result["uuid"] = $uuid;
                $result['message'] = 'INVOICE_QUOTE_NOT_FOUND_TEXT';
                goto end_of_function;
            }
            $invoice->setUuid(Helpers::__uuid());
            $invoice->setReference(null);
            $invoice->setType($type);
            $invoice->setName(ConstantHelper::__translate('INVOICE_TEXT', ModuleModel::$language));
            if ($type == InvoiceQuote::TYPE_CREDIT_NOTE) {
                $invoice->setName(ConstantHelper::__translate('CREDIT_NOTE_TEXT', ModuleModel::$language));
            }
            $invoice->setNumber($invoice->generateNumberForPreCreate());
//            $invoice->setDate(time());
            $invoice->setInvoiceQuoteId($invoice->getId());
            $invoice_template = $invoice->getInvoiceTemplate();
            $invoice_quote_items = $invoice->getInvoiceQuoteItems();
            if (count($invoice_quote_items) > 0) {
                foreach ($invoice_quote_items as $invoice_quote_item) {
                    $item_array = $invoice_quote_item->toArray();
                    $item_array['id'] = null;
                    $item_array['invoice_quote_type'] = $type;
                    $item_array['uuid'] = null;
                    $item_array['invoice_quote_id'] = null;
                    $item_array['tax_rule_name'] = $invoice_quote_item->getTaxRule() ? $invoice_quote_item->getTaxRule()->getName() : "";
                    switch ($invoice_quote_item->getType()) {
                        case InvoiceQuoteItem::TYPE_EXPENSE:
                            $items[] = $item_array;
                            break;
                        case InvoiceQuoteItem::TYPE_PRODUCT:
                            $products[] = $item_array;
                            break;
                        case InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT:
                            $products[] = $item_array;
                            break;
                    }
                }
            }
            $invoice->setId(null);
            switch ($type) {
                /** copy credit note totalPaid should be 0 */
                case InvoiceQuote::TYPE_CREDIT_NOTE:
                    $invoice->setTotalPaid(0);
                    $invoice->setStatus(InvoiceQuote::STATUS_DRAFT);
                    break;
            }
        } else {
            $invoice = new InvoiceQuote();
            $random = new Random();
            $invoice->setUuid($random->uuid());
            $invoice->setType($type);
            $invoice->setName(ConstantHelper::__translate('INVOICE_TEXT', ModuleModel::$language));
            if ($type == InvoiceQuote::TYPE_QUOTE) {
                $invoice->setName(ConstantHelper::__translate('QUOTE_TEXT', ModuleModel::$language));
            } else if ($type == InvoiceQuote::TYPE_CREDIT_NOTE) {
                $invoice->setName(ConstantHelper::__translate('CREDIT_NOTE_TEXT', ModuleModel::$language));
            }
            $invoice->setCompanyId(ModuleModel::$company->getId());
//            $invoice->setDate(time());
            $invoice->setNumber($invoice->generateNumberForPreCreate());
            $invoice->setCompanyName(ModuleModel::$company->getName());
            $invoice->setCompanyAddress1(ModuleModel::$company->getAddress());
            $invoice->setCompanyAddress2(ModuleModel::$company->getStreet());
            $invoice->setCompanyCityName(ModuleModel::$company->getTown());
            $invoice->setCompanyZipCode(ModuleModel::$company->getZipcode());
            $invoice->setCompanyCountryId(ModuleModel::$company->getCountryId());
            $invoice->setCompanyPhone(ModuleModel::$company->getPhone());
            $invoice->setCompanyCountyName(ModuleModel::$company->getStatecounty());
            $invoice->setCompanyFax(ModuleModel::$company->getFax());
            $invoice->setStatus(InvoiceQuote::STATUS_DRAFT);
            $invoice->setTotal(0);
            $invoice->setSubTotal(0);
            $invoice->setDiscount(0);
            $invoice->setTotalPaid(0);
            $invoice->setIsPaid(Helpers::NO);
            $invoice->setTotalBeforeTax(0);
            $invoice->setTotalTax(0);

            $invoice_template = InvoiceTemplate::findFirst([
                "conditions" => "is_default = 1 and company_id = :company_id: and is_deleted = 0",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId()
                ]
            ]);

            if ($invoice_template instanceof InvoiceTemplate) {
                $invoice->setInvoiceTemplateId($invoice_template->getId());
            }
        }

        $invoice_template = $invoice->getInvoiceTemplate();

        if ($invoice_template) {

            $avatar = ObjectAvatar::__getImageByObjectUuid($invoice_template->getUuid());
        }


        $invoice_data = $invoice->toArray();
        $invoice_data["items"] = $items;
        $invoice_data["products"] = $products;
        $invoice_data["company_country_name"] = $invoice->getCompanyCountry() ? $invoice->getCompanyCountry()->getName() : "";
        $invoice_data["biller_country_name"] = $invoice->getBillerCountry() ? $invoice->getBillerCountry()->getName() : "";
        $invoice_data["biller_office_name"] = $invoice->getBillerOffice() ? $invoice->getBillerOffice()->getName() : "";
        $invoice_data["invoice_template"] = $invoice_template;
        $invoice_data["quote"] = $invoice->getType() == InvoiceQuote::TYPE_INVOICE && $invoice->getInvoiceQuote() ? $invoice->getInvoiceQuote()->toArray() : [];
        $invoice_data["invoice"] = $invoice->getType() == InvoiceQuote::TYPE_CREDIT_NOTE && $invoice->getInvoiceQuote() ? $invoice->getInvoiceQuote()->toArray() : [];

        if ($invoice->getRelocationId() > 0) {
            $relocation = $invoice->getRelocation();
            $invoice_data["relocation"] = $relocation ? $relocation->toArray() : null;
            $invoice_data["relocation_identify"] = $relocation ? $relocation->getName() : null;
            $invoice_data["relocation"]['number'] = $relocation ? $relocation->getIdentify() : null;
            $invoice_data["relocation"]['employee_uuid'] = $relocation ? $relocation->getEmployee()->getUuid() : null;
            $invoice_data["relocation"]['employee_name'] = $relocation ? $relocation->getEmployee()->getFullname() : null;
            $invoice_data["assignment"] = $relocation ? ($relocation->getAssignment()) : null;
            $invoice_data['relocation']['assignment']['order_number'] = $relocation ? $relocation->getAssignment()->getOrderNumber() : '';
        }
        if (isset($avatar) && $avatar && !is_null($avatar)) {
            $invoice_data['avatar'] = $avatar->__toArray();
            $invoice_data['avatar']['image_data']['url_thumb'] = $avatar->getUrlThumb() ? $avatar->getUrlThumb() : $avatar->getTemporaryThumbS3Url();
            $result['user_avatar'] = $avatar;
        }
        $result['message'] = $success_message;
        $result['data'] = $invoice_data;
        $result['success'] = true;

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createInvoiceQuoteAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $data = Helpers::__getRequestValuesArray();
        $type = Helpers::__getRequestValue("type");
        $reference = Helpers::__getRequestValue("reference");
        switch ($type) {
            case InvoiceQuote::TYPE_CREDIT_NOTE:
                $this->checkAcl('create', 'credit_note');
                $error_message = 'SAVE_CREDIT_NOTE_FAIL_TEXT';
                $success_message = 'SAVE_CREDIT_NOTE_SUCCESS_TEXT';
                break;
            case InvoiceQuote::TYPE_INVOICE:
                $this->checkAcl('create', 'invoice');
                $error_message = 'SAVE_INVOICE_FAIL_TEXT';
                $success_message = 'SAVE_INVOICE_SUCCESS_TEXT';
                break;
            case InvoiceQuote::TYPE_QUOTE:
                $this->checkAcl('create', 'quote');
                $error_message = 'SAVE_QUOTEE_FAIL_TEXT';
                $success_message = 'SAVE_QUOTE_SUCCESS_TEXT';
                break;
            default:
                $result = [
                    'success' => false,
                    'message' => 'NO_PERMISSION_TEXT'
                ];
                goto end_of_function;
        }
        $generate_default_value = false;

        if ($reference && $reference != '') {
            $checkReferenceExist = InvoiceQuote::findFirstByReference($reference);
            if ($checkReferenceExist instanceof InvoiceQuote) {
                $result['message'] = 'REFERENCE_MUST_BE_UNIQUE_TEXT';
                $result['detail'] = $checkReferenceExist;
                $result['success'] = false;
                goto end_of_function;
            }
        }
        $invoice = new InvoiceQuote();
        $invoice->setCompanyId(ModuleModel::$company->getId());
        $invoice->setType($type);
        $invoice->setData($data);
        $invoice->setNumber($invoice->generateNumber());
        $invoice->setStatus(InvoiceQuote::STATUS_DRAFT);
        $invoice->setIsPaid(Helpers::NO);
        $data['company_id'] = ModuleModel::$company->getId();
        if (isset($data['invoice_template'])) {
            $data['invoice_template_id'] = $data['invoice_template']['id'];
            $invoice_template = InvoiceTemplate::findFirstById($data["invoice_template_id"]);
            if (!$invoice_template instanceof InvoiceTemplate || !$invoice_template->belongsToGms()) {
                $result['message'] = "INVOICE_TEMPLATE_NOT_FOUND_TEXT";
                $result['success'] = false;
                $result['detail'] = "INVOICE_TEMPLATE_NOT_FOUND_TEXT";
                goto end_of_function;
            }
        }
        if (isset($data['relocation']) && isset($data['relocation']['id'])) {
            $data['relocation_id'] = $data['relocation']['id'];
            $relocation = Relocation::findFirstById($data['relocation_id']);
            if ($relocation instanceof Relocation && !$relocation->belongsToGms()) {
                $result['message'] = $error_message;
                $result['success'] = false;
                $result['detail'] = "RELOCATION_NOT_FOUND_TEXT";
                goto end_of_function;
            }
            $invoice->setRelocationId($data['relocation_id']);
        }
        $uuid = Helpers::__getRequestValue("uuid");
        if (!Helpers::__isValidUuid($uuid)) {
            $random = new Random();
            $uuid = $random->uuid();
            $invoice->setUuid($uuid);
            $invoice->setName(ConstantHelper::__translate('INVOICE_TEXT', ModuleModel::$language));
            $invoice->setNumber($invoice->generateNumber());
            $invoice->setCompanyName(ModuleModel::$company->getName());
            $invoice->setCompanyAddress1(ModuleModel::$company->getAddress());
            $invoice->setCompanyAddress2(ModuleModel::$company->getStreet());
            $invoice->setCompanyCityName(ModuleModel::$company->getTown());
            $invoice->setCompanyZipCode(ModuleModel::$company->getZipcode());
            $invoice->setCompanyCountryId(ModuleModel::$company->getCountryId());
            $invoice->setCompanyPhone(ModuleModel::$company->getPhone());
            $invoice->setCompanyCountyName(ModuleModel::$company->getStatecounty());
            $invoice->setCompanyFax(ModuleModel::$company->getFax());

            $invoice_template = InvoiceTemplate::findFirst([
                "conditions" => "is_default = 1 and company_id = :company_id: and is_deleted = 0",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId()
                ]
            ]);

            if ($invoice_template instanceof InvoiceTemplate) {
                $invoice->setInvoiceTemplateId($invoice_template->getId());
            }

            if ($invoice->getChargeTo() == InvoiceQuote::CHARGE_TO_EMPLOYEE) {
                $employee = $invoice->getEmployee();
                if ($employee) {
                    $invoice->setBillerName($employee->getFirstname() . " " . $employee->getLastname());
                    $invoice->setBillerAddress($employee->getAddress());
                    $invoice->setBillerAddress2($employee->getStreet());
                    $invoice->setBillerTown($employee->getTown());
                    $invoice->setBillerZipCode($employee->getZipcode());
                    $invoice->setBillerCountryId($employee->getCountryId());
                    $invoice->setBillerCountyName("");
                    $invoice->setBillerPhone($employee->getPhonework());
                } else {
                    $result['message'] = 'CUSTOMER_REQUIRED_TEXT';
                    $result['detail'] = $invoice->toArray();
                    $result['success'] = false;
                    goto end_of_function;
                }
            } else if ($invoice->getChargeTo() == InvoiceQuote::CHARGE_TO_ACCOUNT || intval($invoice->getChargeTo()) == InvoiceQuote::CHARGE_TO_ACCOUNT) {
                $account = $invoice->getAccount();
                if ($account) {

                    $invoice->setBillerName($account->getName());
                    $invoice->setBillerAddress($account->getAddress());
                    $invoice->setBillerAddress2($account->getStreet());
                    $invoice->setBillerZipCode($account->getZipcode());
                    $invoice->setBillerCountyName($account->getStateCounty());
                    $invoice->setBillerPhone($account->getPhone());
                    $invoice->setBillerTown($account->getTown());
                    $invoice->setBillerEmail($account->getEmail());
                    $invoice->setBillerCountryId($account->getCountryId());

                    $financial = $account->getCompanyFinancialDetail();

                    if ($financial) {
                        $city = $financial->getCity();
                        if ($city) {
                            $invoice->setBillerTown($city ? $city->getAsciiname() : "");
                        } else {
                            if ($financial->getCreditorTown() != '') $invoice->setBillerTown($financial->getCreditorTown());
                        }
                        if ($financial->getInvoicingCountryId() != '') $invoice->setBillerCountryId($financial->getInvoicingCountryId());
                        if ($financial->getVatNumber() != '') $invoice->setBillerVat($financial->getVatNumber());
                        if ($financial->getInvoicingPhone() != '') $invoice->setBillerPhone($financial->getInvoicingPhone());
                        if ($financial->getInvoicingAddress() != '') $invoice->setBillerAddress($financial->getInvoicingAddress());
                        if ($financial->getInvoicingEmail() != '') $invoice->setBillerEmail($financial->getInvoicingEmail());
                    }

                } else {
                    $result['message'] = 'CUSTOMER_REQUIRED_TEXT';
                    $result['detail'] = $invoice->toArray();
                    $result['success'] = false;
                    goto end_of_function;
                }
            } else {
                $result['message'] = 'CUSTOMER_REQUIRED_TEXT';
                $result['detail'] = $invoice->toArray();
                $result['success'] = false;
                goto end_of_function;
            }

            $generate_default_value = true;
        } else {
            $invoice->setUuid($uuid);
            if ($invoice->getChargeTo() == InvoiceQuote::CHARGE_TO_EMPLOYEE) {
                $employee = $invoice->getEmployee();
                if (!$employee) {
                    $result['message'] = 'CUSTOMER_REQUIRED_TEXT';
                    $result['employee'] = $employee;
                    $result['detail'] = $invoice->toArray();
                    $result['success'] = false;
                    goto end_of_function;
                }
            } else if ($invoice->getChargeTo() == InvoiceQuote::CHARGE_TO_ACCOUNT) {
                $account = $invoice->getAccount();
                if (!$account) {
                    $result['message'] = 'CUSTOMER_REQUIRED_TEXT';
                    $result['account'] = $account;
                    $result['detail'] = $invoice->toArray();
                    $result['success'] = false;
                    goto end_of_function;
                }
            } else {
                $result['message'] = 'CUSTOMER_REQUIRED_TEXT';
                $result['detail'] = $invoice->toArray();
                $result['errorType'] = 'noChargeableTypeError';
                $result['success'] = false;
                goto end_of_function;
            }
            if (!$invoice->getDate() > 0) {
                $result['message'] = 'DATE_REQUIRED_TEXT';
                $result['detail'] = $invoice->toArray();
                $result['success'] = false;
                goto end_of_function;
            }
        }
        if (!($invoice->getDate() > 0)) {
            $invoice->setDate(Helpers::__getStartTimeOfDay());
        }
        $this->db->begin();
        if ($invoice->getBillerCountryId() == 0) {
            $invoice->setBillerCountryId(null);
        }
        $result = $invoice->__quickCreate();
        if ($result['success'] == false) {
            $this->db->rollback();
            $result["message"] = isset($result["errorMessage"]) ? $result["errorMessage"][0] : $error_message;
//            $result['message'] = $error_message;
            goto end_of_function;
        }
        $products = Helpers::__getRequestValue("products");
        $invoice_quote_items = [];
        if (count($products) > 0) {
            foreach ($products as $item) {
                if (($item->type == InvoiceQuoteItem::TYPE_EXPENSE && property_exists($item, 'expense_id') && $item->expense_id > 0) ||
                    ($item->type == InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT && property_exists($item, 'account_product_pricing_id') && $item->account_product_pricing_id > 0) ||
                    ($item->type == InvoiceQuoteItem::TYPE_PRODUCT && property_exists($item, 'product_pricing_id') && $item->product_pricing_id > 0)) {
                    $invoice_quote_item = new InvoiceQuoteItem();
                    $invoice_quote_item->setInvoiceQuoteId($invoice->getId());
                    $invoice_quote_item->setType($item->type);
                    $invoice_quote_item->setInvoiceQuoteType($type);
                    $invoice_quote_item->setQuantity($item->quantity);
                    $invoice_quote_item->setTotal($item->total);
                    $invoice_quote_item->setCompanyId(ModuleModel::$company->getId());
                    if (isset($item->number) && $item->number != '') {
                        $invoice_quote_item->setNumber($item->number);
                    } else {
                        $invoice_quote_item->setNumber($invoice_quote_item->generateNumber());
                    }

                    $invoice_quote_item->setName($item->name);
                    if (isset($item->product_pricing_id) && $item->product_pricing_id > 0) {
                        $invoice_quote_item->setProductPricingId($item->product_pricing_id);
                    }
                    if (isset($item->account_product_pricing_id) && $item->account_product_pricing_id > 0) {
                        $invoice_quote_item->setAccountProductPricingId($item->account_product_pricing_id);
                    }
                    $invoice_quote_item->setTaxRuleId($item->tax_rule_id);
                    $invoice_quote_item->setPrice($item->price);
                    $invoice_quote_item->setCurrency($invoice->getCurrency());
                    $invoice_quote_item->setTaxRate($item->tax_rate);
                    $invoice_quote_item->setUnitPrice($item->unit_price);
                    $invoice_quote_item->setDiscount($item->discount);
                    $invoice_quote_item->setDiscountEnabled($item->discount_enabled);
                    $invoice_quote_item->setDiscountType($item->discount_type);
                    $invoice_quote_item->setTotalBeforeTax($item->total_before_tax);
                    $invoice_quote_item->setDiscountAmount($item->discount_amount);
                    $invoice_quote_item->setTotalTax($item->total_tax);
                    if (isset($item->description)) {
                        $invoice_quote_item->setDescription($item->description);
                    }
                    $random = new Random();
                    $uuid = $random->uuid();
                    $invoice_quote_item->setUuid($uuid);
                    $result = $invoice_quote_item->__quickCreate();
                    $invoice_quote_items[] = $invoice_quote_item;
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = $error_message;
                        goto end_of_function;
                    }
                    //@TODO fix bug  of create expense in Invoice
//                    if ($invoice->getType() == InvoiceQuote::TYPE_INVOICE && ($item->type == InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT || $item->type == InvoiceQuoteItem::TYPE_PRODUCT)) {
//                        $expense = new Expense();
//                        $random = new Random();
//                        $uuid = $random->uuid();
//                        $expense->setUuid($uuid);
//                        $expense->setRelocationId($invoice->getRelocationId());
//                        $expense->setExpenseDate($invoice->getDate());
//                        $expense->setQuantity($item->quantity);
//                        $expense->setTaxRuleId($item->tax_rule_id);
//                        $expense->setChargeableType(Expense::CHARGE_TO_NONE);
//                        $expense->setAccountId($invoice->getAccountId());
//                        $expense->setCompanyId(ModuleModel::$company->getId());
//                        $expense->setIsPayable(Expense::NOT_PAYABLE);
//                        $expense->setNumber($expense->generateNumber());
//                        $expense->setStatus(Expense::STATUS_APPROVED);
//                        $expense->setCurrency($invoice->getCurrency());
//                        $expense->setIsAutomated(Expense::IS_AUTOMATED);
//                        $setting = ModuleModel::$company->getRecurrentExpenseApproval();
//                        if ($setting instanceof CompanySetting) {
//                            if (intval($setting->getValue()) == 1) {
//                                $expense->setStatus(Expense::STATUS_DRAFT);
//                            }
//                        }
//                        switch ($item->type) {
//                            case InvoiceQuoteItem::TYPE_PRODUCT:
//                                $product_pricing = ProductPricing::findFirstById($item->product_pricing_id);
//                                if (!$product_pricing instanceof ProductPricing || !$product_pricing->belongsToGms()) {
//                                    $this->db->rollback();
//                                    $result['message'] = $error_message;
//                                    goto end_of_function;
//                                }
//                                $expense->setProductPricingId($item->product_pricing_id);
//                                $expense->setCost($product_pricing->getCost() * $item->quantity);
//                                $expense->setTaxInclude($product_pricing->getTaxRule() instanceof TaxRule ? Expense::TAX_INCLUDE : Expense::TAX_NOT_INCLUDE);
//                                break;
//                            case InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT:
//                                $account_product_pricing = AccountProductPricing::findFirstById($item->account_product_pricing_id);
//                                if (!$account_product_pricing instanceof $account_product_pricing || !$account_product_pricing->belongsToGms()) {
//                                    $this->db->rollback();
//                                    $result['message'] = $error_message;
//                                    goto end_of_function;
//                                }
//                                $product_pricing = $account_product_pricing->getProductPricing();
//                                $expense->setAccountProductPricingId($item->account_product_pricing_id);
//                                $expense->setCost($product_pricing->getCost() * $item->quantity);
//                                $expense->setTaxInclude($product_pricing->getTaxRule() instanceof TaxRule ? Expense::TAX_INCLUDE : Expense::TAX_NOT_INCLUDE);
//                                break;
//                            default:
//                                break;
//                        }
//                        $expense->setTotal($expense->getCost() * (1 + (($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) ? $expense->getTaxRule()->getRate() : 0) / 100));
//                        $result = $expense->__quickCreate();
//                        if ($result['success'] == false) {
//                            $this->db->rollback();
//                            $result['message'] = $error_message;
//                            goto end_of_function;
//                        }
//                    }
                }
            }
        }
        $items = Helpers::__getRequestValue("items");
        if (count($items) > 0) {
            foreach ($items as $item) {
                $invoice_quote_item = new InvoiceQuoteItem();
                $random = new Random();
                $uuid = $random->uuid();
                $invoice_quote_item->setUuid($uuid);
                $invoice_quote_item->setInvoiceQuoteId($invoice->getId());
                $invoice_quote_item->setInvoiceQuoteType($type);
                $invoice_quote_item->setNumber($item->number);
                $invoice_quote_item->setExpenseId($item->expense_id);
                $invoice_quote_item->setTaxRuleId($item->tax_rule_id);
                $invoice_quote_item->setType(InvoiceQuoteItem::TYPE_EXPENSE);
                $invoice_quote_item->setQuantity($item->quantity);
                $invoice_quote_item->setPrice($item->price);
                $invoice_quote_item->setTotal($item->total);
                $invoice_quote_item->setName("");
                $invoice_quote_item->setCurrency($invoice->getCurrency());
                $invoice_quote_item->setCompanyId(ModuleModel::$company->getId());
                $invoice_quote_item->setAccountId($item->account_id);
                $invoice_quote_item->setEmployeeId($item->employee_id);
                $invoice_quote_item->setRelocationId($item->relocation_id);
                $invoice_quote_item->setChargeTo($item->charge_to);
                $invoice_quote_item->setTaxRate($item->tax_rate);
                $invoice_quote_item->setUnitPrice($item->unit_price);
                $invoice_quote_item->setDiscount($item->discount);
                $invoice_quote_item->setDiscountEnabled($item->discount_enabled);
                $invoice_quote_item->setDiscountType($item->discount_type);
                $invoice_quote_item->setTotalBeforeTax($item->total_before_tax);
                $invoice_quote_item->setDiscountAmount($item->discount_amount);
                $invoice_quote_item->setTotalTax($item->total_tax);
                $invoice_quote_item->setDescription($item->description);
                $invoice_quote_item->setInvoiceableItemId($item->invoiceable_item_id);
                $invoice_quote_item->setExpenseCategoryId($item->expense_category_id);
                $invoice_quote_item->setExpenseCategoryName($item->expense_category_name);
                $result = $invoice_quote_item->__quickCreate();
                $invoice_quote_items[] = $invoice_quote_item;
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = $error_message;
                    goto end_of_function;
                }
            }
        }
        $this->db->commit();
        $result['success'] = true;
        $result['message'] = $success_message;
        if($invoice->getType() == InvoiceQuote::TYPE_INVOICE){
            ModuleModel::$invoice = $invoice;
            $this->dispatcher->setParam('return', $result);
        } else if($invoice->getType() == InvoiceQuote::TYPE_CREDIT_NOTE){
            ModuleModel::$credit_note = $invoice;
            $this->dispatcher->setParam('return', $result);
        } 
        $invoice_data = $invoice->toArray();
        $invoice_data["invoice_template"] = $invoice_template;
        $result['data'] = $invoice_data;
        $result['items'] = $invoice_quote_items;

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/invoice", paths={module="gms"}, methods={"GET"}, name="gms-needform-index")
     */
    public function detailInvoiceQuoteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice = InvoiceQuote::findFirstByUuid($uuid);
            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                switch ($invoice->getType()) {
                    case InvoiceQuote::TYPE_CREDIT_NOTE:
                        $this->checkAcl('index', 'credit_note');
                        break;
                    case InvoiceQuote::TYPE_INVOICE:
                        $this->checkAcl('index', 'invoice');
                        break;
                    case InvoiceQuote::TYPE_QUOTE:
                        $this->checkAcl('index', 'quote');
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => 'NO_PERMISSION_TEXT'
                        ];
                        goto end_of_function;
                }
                $item = $invoice->toArray();

                $item["biller_country_name"] = $invoice->getBillerCountry() ? $invoice->getBillerCountry()->getName() : null;
                $item["company_country_name"] = $invoice->getCompanyCountry() ? $invoice->getCompanyCountry()->getName() : null;
                $item["date"] = intval($invoice->getDate());
                $item["due_date"] = intval($invoice->getDueDate());
//                $employee = $invoice->getEmployee();
//                $item['discount_amount'] = $invoice->getSubTotal() - $invoice->getTotal();
//                $item["account_name"] = $invoice->getAccount() instanceof Company ? $invoice->getAccount()->getName() : null;
//                $item['biller_name'] = $invoice->getChargeTo() == InvoiceQuote::CHARGE_TO_ACCOUNT ? $invoice->getAccount()->getName() : ($employee ? $employee->getFirstname() . ' ' . $employee->getLastname() : '');
//                $item["employee_name"] = $invoice->getChargeTo() == InvoiceQuote::CHARGE_TO_ACCOUNT ? "" : ($employee ? $employee->getFirstname() . ' ' . $employee->getLastname() : "");
//                $item["employee_uuid"] = $invoice->getChargeTo() == InvoiceQuote::CHARGE_TO_ACCOUNT ? "" : ($employee ? $employee->getUuid() : "");
//                $item['biller_country_name'] = $invoice->getBillerCountry() instanceof Country ? $invoice->getBillerCountry()->getName() : "";
                $invoice_quote_item_array = [];
                $invoice_quote_product_array = [];
                if ($invoice->getRelocationId() > 0) {
                    $relocation = $invoice->getRelocation();
                    $item["relocation"] = $relocation ? $relocation->toArray() : null;
                    $item["relocation_identify"] = $relocation ? $relocation->getName() : null;
                    $item["relocation"]['number'] = $relocation ? $relocation->getIdentify() : null;
                    $item["relocation"]['employee_uuid'] = $relocation ? $relocation->getEmployee()->getUuid() : null;
                    $item["relocation"]['employee_name'] = $relocation ? $relocation->getEmployee()->getFullname() : null;
                    $item["assignment"] = $relocation ? ($relocation->getAssignment()) : null;
                    $item['relocation']['assignment']['order_number'] = $relocation ? $relocation->getAssignment()->getOrderNumber() : '';
                }
                $invoice_quote_items = $invoice->getInvoiceQuoteItems();
                $showDiscountItem = false;
                if (count($invoice_quote_items) > 0) {
                    foreach ($invoice_quote_items as $invoice_quote_item) {
                        $item_array = $invoice_quote_item->toArray();
                        if ($invoice_quote_item->getTaxRuleId() > 0) {
                            $item_array['tax_rate'] = $invoice_quote_item->getTaxRule() ? $invoice_quote_item->getTaxRule()->getRate() : 0;
                        } else {
                            $item_array['tax_rate'] = 0;
                        }

                        if ($item_array['discount'] > 0) {
                            $showDiscountItem = true;
                        }
                        $item_array["expense_category_code"] = $invoice_quote_item->getExpenseCategory() ? $invoice_quote_item->getExpenseCategory()->getExternalHrisId() : "";
                        $item_array["product_pricing"] = $invoice_quote_item->getProductPricing() ? $invoice_quote_item->getProductPricing()->toArray() :
                            ($invoice_quote_item->getAccountProductPricing() ? $invoice_quote_item->getAccountProductPricing()->getProductPricing()->toArray() : null);
                        $item_array['tax_rule_name'] = $invoice_quote_item->getTaxRule() ? $invoice_quote_item->getTaxRule()->getName() : "";
                        switch ($invoice_quote_item->getType()) {
                            case InvoiceQuoteItem::TYPE_EXPENSE:
                                $invoice_quote_item_array[] = $item_array;
                                break;
                            case InvoiceQuoteItem::TYPE_PRODUCT:
                                $invoice_quote_product_array[] = $item_array;
                                break;
                            case InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT:
                                $invoice_quote_product_array[] = $item_array;
                                break;
                        }
                    }
                }
                $item["showDiscountItem"] = $showDiscountItem;
                $item["items"] = $invoice_quote_item_array;
                $item["products"] = $invoice_quote_product_array;
                $item["account"] = $invoice->getAccount();
                $item["employee"] = $invoice->getEmployee() ? $invoice->getEmployee() : ($invoice->getRelocation() ? $invoice->getRelocation()->getEmployee() : null);
                $invoice_template = $invoice->getInvoiceTemplate();
                if ($invoice_template) {
                    $item["invoice_template"] = $invoice_template;
                    $avatar = ObjectAvatar::__getImageByObjectUuid($invoice_template->getUuid());
                    if ($avatar && !is_null($avatar)) {

                        $item['avatar'] = $avatar->__toArray();
                        $item['avatar']['image_data']['url_thumb'] = $avatar->getUrlThumb() ? $avatar->getUrlThumb() : $avatar->getTemporaryThumbS3Url();
                    }
                }
                if ($invoice->getTotal() < $invoice->getSubTotal()) {
                    $item["discount_apply"] = true;
                }
                $item["quote"] = $invoice->getType() == InvoiceQuote::TYPE_INVOICE && $invoice->getInvoiceQuote() ? $invoice->getInvoiceQuote()->toArray() : [];
                $item["invoice"] = $invoice->getType() == InvoiceQuote::TYPE_CREDIT_NOTE && $invoice->getInvoiceQuote() ? $invoice->getInvoiceQuote()->toArray() : [];
                $result = [
                    'success' => true,
                    'data' => $item
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Save service action
     */
    public function editInvoiceQuoteAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $uuid = Helpers::__getRequestValue("uuid");
        $type = Helpers::__getRequestValue("type");

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $invoice = InvoiceQuote::findFirstByUuid($uuid);
            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {

                if ($invoice->isEditable() == false) {
                    $result = [
                        'success' => false,
                        'message' => 'INVOICE_NOT_EDITABLE_TEXT',
                    ];
                    goto end_of_function;
                }

                switch ($invoice->getType()) {
                    case InvoiceQuote::TYPE_CREDIT_NOTE:
                        $this->checkAcl('edit', 'credit_note');
                        $error_message = 'SAVE_CREDIT_NOTE_FAIL_TEXT';
                        $success_message = 'SAVE_CREDIT_NOTE_SUCCESS_TEXT';
                        break;
                    case InvoiceQuote::TYPE_INVOICE:
                        $this->checkAcl('edit', 'invoice');
                        $error_message = 'SAVE_INVOICE_FAIL_TEXT';
                        $success_message = 'SAVE_INVOICE_SUCCESS_TEXT';
                        break;
                    case InvoiceQuote::TYPE_QUOTE:
                        $this->checkAcl('edit', 'quote');
                        $error_message = 'SAVE_QUOTEE_FAIL_TEXT';
                        $success_message = 'SAVE_QUOTE_SUCCESS_TEXT';
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => 'NO_PERMISSION_TEXT'
                        ];
                        goto end_of_function;
                }
                $data = Helpers::__getRequestValuesArray();
                if (isset($data['relocation'])) {
                    $data['relocation_id'] = $data['relocation']['id'];
                    $relocation = Relocation::findFirstById($data['relocation_id']);
                    if ($relocation instanceof Relocation && !$relocation->belongsToGms()) {
                        $result['message'] = $error_message;
                        $result['success'] = false;
                        $result['detail'] = "RELOCATION_NOT_FOUND_TEXT";
                        goto end_of_function;
                    }
                } else {
                    $data['relocation_id'] = null;
                }

                $checkReferenceExist = InvoiceQuote::findFirst([
                    "conditions" => "reference = :reference: and id != :id:",
                    "bind" => [
                        "reference" => $data["reference"],
                        "id" => $data["id"]
                    ]
                ]);
                if ($checkReferenceExist instanceof InvoiceQuote) {
                    $result['message'] = 'REFERENCE_MUST_BE_UNIQUE_TEXT';
                    $result['detail'] = $checkReferenceExist;
                    $result['success'] = false;
                    goto end_of_function;
                }
                $invoice->setData($data);

                $invoice->setStatus(InvoiceQuote::STATUS_DRAFT);
                $this->db->begin();

                $totalBeforeTax = 0;
                $totalTax = 0;
                $total = 0;

                $products = Helpers::__getRequestValue("products");
                $invoice_quote_items = [];
                if (count($products) > 0) {
                    foreach ($products as $item) {
                        if (isset($item->id) && $item->id > 0) {
                            $invoice_quote_item = InvoiceQuoteItem::findFirstById($item->id);
                            $create_invoice_quote_item = false;
                            if (!$invoice_quote_item instanceof InvoiceQuoteItem) {
                                $this->db->rollback();
                                $result['message'] = $error_message;
                                goto end_of_function;
                            }
                        } else {
                            $create_invoice_quote_item = true;
                            $invoice_quote_item = new InvoiceQuoteItem();
                        }
                        $invoice_quote_item->setInvoiceQuoteId($invoice->getId());
                        $invoice_quote_item->setInvoiceQuoteType($invoice->getType());
                        $invoice_quote_item->setQuantity($item->quantity);
                        $invoice_quote_item->setTotal($item->total);
                        if (isset($item->product_pricing_id) && $item->product_pricing_id > 0) {
                            $invoice_quote_item->setProductPricingId($item->product_pricing_id);
                            $invoice_quote_item->setType(InvoiceQuoteItem::TYPE_PRODUCT);
                        }
                        if (isset($item->account_product_pricing_id) && $item->account_product_pricing_id > 0) {
                            $invoice_quote_item->setAccountProductPricingId($item->account_product_pricing_id);
                            $invoice_quote_item->setType(InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT);
                        }

                        $invoice_quote_item->setCompanyId(ModuleModel::$company->getId());
                        if (isset($item->number) && $item->number != '') {
                            $invoice_quote_item->setNumber($item->number);
                        } else {
                            $invoice_quote_item->setNumber($invoice_quote_item->generateNumber());
                        }

                        $invoice_quote_item->setName($item->name);
                        $invoice_quote_item->setPrice($item->price);
                        $invoice_quote_item->setCurrency($invoice->getCurrency());
                        $invoice_quote_item->setUnitPrice($item->unit_price);
                        $invoice_quote_item->setDiscount($item->discount);
                        $invoice_quote_item->setDiscountEnabled($item->discount_enabled);
                        $invoice_quote_item->setDiscountType($item->discount_type);
                        $invoice_quote_item->setTotalBeforeTax($item->total_before_tax);
                        $invoice_quote_item->setDiscountAmount($item->discount_amount);
                        $invoice_quote_item->setTaxRuleId($item->tax_rule_id ? $item->tax_rule_id : null);

                        if (isset($item->description) && $item->description != '') {
                            $invoice_quote_item->setDescription($item->description);
                        } else {
                            $invoice_quote_item->setDescription(null);
                        }

                        $invoice_quote_item->calculateTotal();

                        $totalBeforeTax = $totalBeforeTax + $invoice_quote_item->getTotalBeforeTax();
                        $totalTax = $totalTax + $invoice_quote_item->getTotalTax();
                        $total = $total + $invoice_quote_item->getTotal();

                        if ($create_invoice_quote_item) {
                            $random = new Random();
                            $uuid = $random->uuid();
                            $invoice_quote_item->setUuid($uuid);
                            $result = $invoice_quote_item->__quickCreate();
                            $invoice_quote_items[] = $invoice_quote_item;
                            if ($result['success'] == false) {
                                $this->db->rollback();
                                $result['message'] = $error_message;
                                goto end_of_function;
                            }
                        } else {
                            $result = $invoice_quote_item->__quickUpdate();
                            $invoice_quote_items[] = $invoice_quote_item;
                            if ($result['success'] == false) {
                                $this->db->rollback();
                                $result['message'] = $error_message;
                                goto end_of_function;
                            }
                        }
                        //@TODO remove code of create expense  in EDIT MODE
//                        if ($create_invoice_quote_item && $invoice->getType() == InvoiceQuote::TYPE_INVOICE && ($item->type == InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT || $item->type == InvoiceQuoteItem::TYPE_PRODUCT)) {
//                            $expense = new Expense();
//                            $random = new Random();
//                            $uuid = $random->uuid();
//                            $expense->setUuid($uuid);
//                            $expense->setRelocationId($invoice->getRelocationId());
//                            $expense->setExpenseDate($invoice->getDate());
//                            $expense->setQuantity($item->quantity);
//                            $expense->setTaxRuleId($item->tax_rule_id);
//                            $expense->setChargeableType(Expense::CHARGE_TO_NONE);
//                            $expense->setAccountId($invoice->getAccountId());
//                            $expense->setCompanyId(ModuleModel::$company->getId());
//                            $expense->setIsPayable(Expense::NOT_PAYABLE);
//                            $expense->setNumber($expense->generateNumber());
//                            $expense->setStatus(Expense::STATUS_APPROVED);
//                            $expense->setCurrency($invoice->getCurrency());
//                            $expense->setIsAutomated(Expense::IS_AUTOMATED);
//                            $setting = ModuleModel::$company->getRecurrentExpenseApproval();
//                            if ($setting instanceof CompanySetting) {
//                                if (intval($setting->getValue()) == 1) {
//                                    $expense->setStatus(Expense::STATUS_DRAFT);
//                                }
//                            }
//                            switch ($item->type) {
//                                case InvoiceQuoteItem::TYPE_PRODUCT:
//                                    $product_pricing = ProductPricing::findFirstById($item->product_pricing_id);
//                                    if (!$product_pricing instanceof ProductPricing || !$product_pricing->belongsToGms()) {
//                                        $this->db->rollback();
//                                        $result['message'] = $error_message;
//                                        goto end_of_function;
//                                    }
//                                    $expense->setProductPricingId($item->product_pricing_id);
//                                    $expense->setCost($product_pricing->getCost() * $item->quantity);
//                                    $expense->setTaxInclude($product_pricing->getTaxRule() instanceof TaxRule ? Expense::TAX_INCLUDE : Expense::TAX_NOT_INCLUDE);
//                                    break;
//                                case InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT:
//                                    $account_product_pricing = AccountProductPricing::findFirstById($item->account_product_pricing_id);
//                                    if (!$account_product_pricing instanceof $account_product_pricing || !$account_product_pricing->belongsToGms()) {
//                                        $this->db->rollback();
//                                        $result['message'] = $error_message;
//                                        goto end_of_function;
//                                    }
//                                    $product_pricing = $account_product_pricing->getProductPricing();
//                                    $expense->setAccountProductPricingId($item->account_product_pricing_id);
//                                    $expense->setCost($product_pricing->getCost() * $item->quantity);
//                                    $expense->setTaxInclude($product_pricing->getTaxRule() instanceof TaxRule ? Expense::TAX_INCLUDE : Expense::TAX_NOT_INCLUDE);
//                                    break;
//                                default:
//                                    break;
//                            }
//                            $expense->setTotal($expense->getCost() * (1 + (($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) ? $expense->getTaxRule()->getRate() : 0) / 100));
//                            $result = $expense->__quickCreate();
//                            if ($result['success'] == false) {
//                                $this->db->rollback();
//                                $result['message'] = $error_message;
//                                goto end_of_function;
//                            }
//                        }
                    }
                }
                $items = Helpers::__getRequestValue("items");
                if (count($items) > 0) {
                    foreach ($items as $item) {
                        if (isset($item->id) && $item->id > 0) {
                            $invoice_quote_item = InvoiceQuoteItem::findFirstById($item->id);
                            $create_invoice_quote_item = false;
                            if (!$invoice_quote_item instanceof InvoiceQuoteItem) {
                                $this->db->rollback();
                                $result['message'] = $error_message;
                                goto end_of_function;
                            }
                        } else {
                            $create_invoice_quote_item = true;
                            $invoice_quote_item = new InvoiceQuoteItem();
                        }
                        $invoice_quote_item->setInvoiceQuoteId($invoice->getId());
                        $invoice_quote_item->setInvoiceQuoteType($invoice->getType());
                        $invoice_quote_item->setNumber($item->number);
                        $invoice_quote_item->setExpenseId($item->expense_id);
                        $invoice_quote_item->setType(InvoiceQuoteItem::TYPE_EXPENSE);
                        $invoice_quote_item->setQuantity($item->quantity);
                        $invoice_quote_item->setPrice($item->price);
                        $invoice_quote_item->setTotal($item->total);
                        $invoice_quote_item->setName("");
                        $invoice_quote_item->setCurrency($invoice->getCurrency());
                        $invoice_quote_item->setCompanyId(ModuleModel::$company->getId());
                        $invoice_quote_item->setAccountId($item->account_id);
                        $invoice_quote_item->setEmployeeId($item->employee_id);
                        $invoice_quote_item->setRelocationId($item->relocation_id);
                        $invoice_quote_item->setChargeTo($item->charge_to);
                        $invoice_quote_item->setUnitPrice($item->unit_price);
                        $invoice_quote_item->setDiscount($item->discount);
                        $invoice_quote_item->setDiscountEnabled($item->discount_enabled);
                        $invoice_quote_item->setDiscountType($item->discount_type);
                        $invoice_quote_item->setTotalBeforeTax($item->total_before_tax);
                        $invoice_quote_item->setDiscountAmount($item->discount_amount);
                        if (isset($item->description) && $item->description != '') {
                            $invoice_quote_item->setDescription($item->description);
                        } else {
                            $invoice_quote_item->setDescription(null);
                        }
                        $invoice_quote_item->setInvoiceableItemId($item->invoiceable_item_id);
                        $invoice_quote_item->setExpenseCategoryId($item->expense_category_id);
                        $invoice_quote_item->setExpenseCategoryName($item->expense_category_name);
                        $invoice_quote_item->setTaxRuleId($item->tax_rule_id ? $item->tax_rule_id : null);

                        //Calculate total
                        $invoice_quote_item->calculateTotal();

                        $totalBeforeTax = $totalBeforeTax + $invoice_quote_item->getTotalBeforeTax();
                        $totalTax = $totalTax + $invoice_quote_item->getTotalTax();
                        $total = $total + $invoice_quote_item->getTotal();

                        if ($create_invoice_quote_item) {
                            $random = new Random();
                            $uuid = $random->uuid();
                            $invoice_quote_item->setUuid($uuid);
                            $result = $invoice_quote_item->__quickCreate();
                            $invoice_quote_items[] = $invoice_quote_item;
                            if ($result['success'] == false) {
                                $this->db->rollback();
                                $result['message'] = $error_message;
                                goto end_of_function;
                            }
                        } else {
                            $result = $invoice_quote_item->__quickUpdate();
                            $invoice_quote_items[] = $invoice_quote_item;
                            if ($result['success'] == false) {
                                $this->db->rollback();
                                $result['message'] = $error_message;
                                goto end_of_function;
                            }
                        }
                        $invoice_quote_items[] = $invoice_quote_item;
                    }
                }

                //Calculate total invoice
                $invoice->setTotalBeforeTax($totalBeforeTax);
                $invoice->setTotal($total);
                $invoice->setTotalTax($totalTax);

                $result = $invoice->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = $error_message;
                    goto end_of_function;
                }
                $result['message'] = $success_message;


                $this->db->commit();

            }
        }
        if($result['success']){
            if($invoice->getType() == InvoiceQuote::TYPE_INVOICE){
                ModuleModel::$invoice = $invoice;
                $this->dispatcher->setParam('return', $result);
            } else if($invoice->getType() == InvoiceQuote::TYPE_CREDIT_NOTE){
                ModuleModel::$credit_note = $invoice;
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
    public function removeInvoiceQuoteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice = InvoiceQuote::findFirstByUuid($uuid);

            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                if ($invoice->isEditable() == false) {
                    $return = [
                        "success" => false,
                        "message" => "REMOVE_DATA_FAIL_TEXT"
                    ];
                    goto end_of_function;
                }
                switch ($invoice->getType()) {
                    case InvoiceQuote::TYPE_CREDIT_NOTE:
                        ModuleModel::$credit_note = $invoice;
                        $this->checkAcl('delete', 'credit_note');
                        break;
                    case InvoiceQuote::TYPE_INVOICE:
                        ModuleModel::$invoice = $invoice;
                        $this->checkAcl('delete', 'invoice');
                        break;
                    case InvoiceQuote::TYPE_QUOTE:
                        $this->checkAcl('delete', 'quote');
                        break;
                    default:
                        $return = [
                            'success' => false,
                            'message' => 'NO_PERMISSION_TEXT'
                        ];
                        goto end_of_function;
                }
                $invoice_quote_items = $invoice->getInvoiceQuoteItems();
                $this->db->begin();
                if (count($invoice_quote_items) > 0) {
                    foreach ($invoice_quote_items as $invoice_quote_item) {
                        $return = $invoice_quote_item->__quickRemove();
                        if ($return["success"] == false) {
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }
                }
                $return = $invoice->__quickRemove();
                if ($return["success"] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }
                $this->db->commit();
                if(ModuleModel::$invoice instanceof InvoiceQuote || ModuleModel::$credit_note instanceof InvoiceQuote){
                    $this->dispatcher->setParam('return', $return);
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
    public function saveInvoiceQuoteItemAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $uuid = Helpers::__getRequestValue("uuid");
        $invoice_quote_total = Helpers::__getRequestValue("invoice_quote_total");
        $invoice_quote_total_before_tax = Helpers::__getRequestValue("invoice_quote_total_before_tax");
        $invoice_quote_total_tax = Helpers::__getRequestValue("invoice_quote_total_tax");
        $invoice_quote_subtotal = Helpers::__getRequestValue("invoice_quote_subtotal");
        $data = Helpers::__getRequestValuesArray();
        $type = Helpers::__getRequestValue("type");
        $result = [
            'success' => false,
            'message' => 'INVOICE_QUOTE_ITEM_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice_quote_item = InvoiceQuoteItem::findFirstByUuid($uuid);

            if ($invoice_quote_item instanceof InvoiceQuoteItem) {
                $invoice_quote_item->setData(Helpers::__getRequestValuesArray());
                if ($type == InvoiceQuoteItem::TYPE_EXPENSE) {
                    $invoice_quote_item->setInvoiceQuoteId(Helpers::__getRequestValue("invoice_quote_id"));
                }
                $invoice = $invoice_quote_item->getInvoiceQuote();

                if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                    switch ($invoice->getType()) {
                        case InvoiceQuote::TYPE_CREDIT_NOTE:
                            $this->checkAcl('edit', 'credit_note');
                            break;
                        case InvoiceQuote::TYPE_INVOICE:
                            $this->checkAcl('edit', 'invoice');
                            break;
                        case InvoiceQuote::TYPE_QUOTE:
                            $this->checkAcl('edit', 'quote');
                            break;
                        default:
                            $result = [
                                'success' => false,
                                'message' => 'NO_PERMISSION_TEXT'
                            ];
                            goto end_of_function;
                    }
                    $checkExist = InvoiceQuoteItem::findFirst([
                        "conditions" => "invoice_quote_id = :invoice_quote_id: and type = :type:  and id != :id: and ((type = :expense_type: and expense_id = :expense_id:) or (type = :product_type: and product_pricing_id = :product_pricing_id:) or (type = :account_product_type: and account_product_pricing_id = :account_product_pricing_id:))",
                        "bind" => [
                            "invoice_quote_id" => Helpers::__getRequestValue("invoice_quote_id"),
                            "product_pricing_id" => Helpers::__getRequestValue("product_pricing_id"),
                            "expense_id" => Helpers::__getRequestValue("expense_id"),
                            "account_product_pricing_id" => Helpers::__getRequestValue("account_product_pricing_id"),
                            "type" => Helpers::__getRequestValue("type"),
                            "expense_type" => InvoiceQuoteItem::TYPE_EXPENSE,
                            "product_type" => InvoiceQuoteItem::TYPE_PRODUCT,
                            "account_product_type" => InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT,
                            "id" => $invoice_quote_item->getId()
                        ]
                    ]);
                    if ($checkExist instanceof InvoiceQuoteItem) {
                        $result['message'] = 'INVOICE_QUOTE_ITEM_MUST_BE_UNIQUE_TEXT';
                        $result['success'] = false;
                        goto end_of_function;
                    }
                    $invoice_quote_item->setInvoiceQuoteType($invoice->getType());
                    $this->db->begin();
                    $invoice->setData($data);

                    $result = $invoice_quote_item->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    }


                    $invoice->setTotal($invoice_quote_total);
                    $invoice->setSubTotal($invoice_quote_subtotal);
                    $invoice->setTotalBeforeTax($invoice_quote_total_before_tax);
                    $invoice->setTotalTax($invoice_quote_total_tax);
                    $result = $invoice->__quickUpdate();
                    if ($result['success'] == false) {
                        $this->db->rollback();
                        $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    }

                    $invoice_quote_item_data = $invoice_quote_item->toArray();
                    $invoice_quote_item_data['tax_rule'] = $invoice_quote_item->getTaxRule() ? $invoice_quote_item->getTaxRule() : false;
                    $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_SUCCESS_TEXT';
                    $result['data'] = $invoice_quote_item_data;
                    $this->db->commit();


                } else {
                    $result = [
                        'success' => false,
                        'message' => 'INVOICE_QUOTE_NOT_FOUND_TEXT'
                    ];
                    goto end_of_function;
                }
            }
        } else {
            $invoice_quote_item = new InvoiceQuoteItem();
            $checkExist = InvoiceQuoteItem::findFirst([
                "conditions" => "invoice_quote_id = :invoice_quote_id: and type = :type: and ((type = :expense_type: and expense_id = :expense_id:) or (type = :product_type: and product_pricing_id = :product_pricing_id:) or (type = :account_product_type: and account_product_pricing_id = :account_product_pricing_id:))",
                "bind" => [
                    "invoice_quote_id" => Helpers::__getRequestValue("invoice_quote_id"),
                    "product_pricing_id" => Helpers::__getRequestValue("product_pricing_id"),
                    "expense_id" => Helpers::__getRequestValue("expense_id"),
                    "account_product_pricing_id" => Helpers::__getRequestValue("account_product_pricing_id"),
                    "type" => Helpers::__getRequestValue("type"),
                    "expense_type" => InvoiceQuoteItem::TYPE_EXPENSE,
                    "product_type" => InvoiceQuoteItem::TYPE_PRODUCT,
                    "account_product_type" => InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT
                ]
            ]);
            if ($checkExist instanceof InvoiceQuoteItem) {
                $result['message'] = 'INVOICE_QUOTE_ITEM_MUST_BE_UNIQUE_TEXT';
                $result['detail'] = $checkExist;
                $result['success'] = false;
                goto end_of_function;
            }

            $temporaryExpense = new Expense();
            $temporaryExpense->setCompanyId(ModuleModel::$company->getId());
            $temporaryExpenseNumber = $temporaryExpense->generateNumber();


            $invoice_quote_item->setData(Helpers::__getRequestValuesArray());
            $invoice = $invoice_quote_item->getInvoiceQuote();

            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                switch ($invoice->getType()) {
                    case InvoiceQuote::TYPE_CREDIT_NOTE:
                        $this->checkAcl('edit', 'credit_note');
                        break;
                    case InvoiceQuote::TYPE_INVOICE:
                        $this->checkAcl('edit', 'invoice');
                        break;
                    case InvoiceQuote::TYPE_QUOTE:
                        $this->checkAcl('edit', 'quote');
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => 'NO_PERMISSION_TEXT'
                        ];
                        goto end_of_function;
                }
                //begin transaction
                $this->db->begin();
                $invoice->setTotal($invoice_quote_total);
                $invoice->setSubTotal($invoice_quote_subtotal);
                $invoice->setTotalBeforeTax($invoice_quote_total_before_tax);
                $invoice->setTotalTax($invoice_quote_total_tax);
                $result = $invoice->__quickUpdate();

                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                    goto end_of_function;
                }
                $invoice_quote_item->setInvoiceQuoteType($invoice->getType());
                $result = $invoice_quote_item->__quickCreate();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                    goto end_of_function;
                } else {
                    $invoice_quote_item_data = $invoice_quote_item->toArray();
                    $invoice_quote_item_data['tax_rule'] = $invoice_quote_item->getTaxRule() ? $invoice_quote_item->getTaxRule() : false;
                    $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_SUCCESS_TEXT';
                    $result['data'] = $invoice_quote_item_data;
//                    $result['expense'] = $expense instanceof Expense ? $expense : null;
                    $this->db->commit();
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'INVOICE_QUOTE_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeInvoiceQuoteItemAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        $return = [
            'success' => false,
            'message' => 'INVOICE_QUOTE_ITEM_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice_quote_item = InvoiceQuoteItem::findFirstByUuid($uuid);

            if ($invoice_quote_item instanceof InvoiceQuoteItem) {


                $invoice = $invoice_quote_item->getInvoiceQuote();
                if ($invoice && $invoice->belongsToGms()) {
                    switch ($invoice->getType()) {
                        case InvoiceQuote::TYPE_CREDIT_NOTE:
                            $this->checkAcl('edit', 'credit_note');
                            break;
                        case InvoiceQuote::TYPE_INVOICE:
                            $this->checkAcl('edit', 'invoice');
                            break;
                        case InvoiceQuote::TYPE_QUOTE:
                            $this->checkAcl('edit', 'quote');
                            break;
                        default:
                            $return = [
                                'success' => false,
                                'message' => 'NO_PERMISSION_TEXT'
                            ];
                            goto end_of_function;
                    }

                    $this->db->begin();
                    $invoice->setTotal($invoice->getTotal() - $invoice_quote_item->getTotal());
                    $invoice->setSubTotal($invoice->getSubTotal() - $invoice_quote_item->getTotal());
                    $invoice->setTotalBeforeTax($invoice->getTotalBeforeTax() - $invoice_quote_item->getTotalBeforeTax());
                    $invoice->setTotalTax($invoice->getTotalTax() - $invoice->getTotalTax());
                    $return = $invoice->__quickUpdate();

                    if ($return['success'] == false) {
                        $this->db->rollback();
                        $return['message'] = 'REMOVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $return = $invoice_quote_item->__quickRemove();
                    if ($return['success'] == false) {
                        $this->db->rollback();
                        $return['message'] = 'REMOVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $this->db->commit();

                } else {
                    $return = [
                        'success' => false,
                        'message' => 'INVOICE_QUOTE_NOT_FOUND_TEXT'
                    ];
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getListInvoiceForCreditNoteAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $currency = intval(Helpers::__getRequestValue('currency'));
        $account_id = intval(Helpers::__getRequestValue('account_id'));
        $employee_id = intval(Helpers::__getRequestValue('employee_id'));

        $invoices = InvoiceQuote::sum([
            "column" => "total",
            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and ((account_id = :account_id: and charge_to = :charge_to_account:) or (employee_id = :employee_id:
                    and charge_to = :charge_to_employee:))",
            "bind" => [
                "company_id" => ModuleModel::$company->getId(),
                "type" => InvoiceQuote::TYPE_INVOICE,
                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                "currency" => $currency,
                "account_id" => $account_id,
                "employee_id" => $employee_id,
                "charge_to_account" => InvoiceQuote::CHARGE_TO_ACCOUNT,
                "charge_to_employee" => InvoiceQuote::CHARGE_TO_EMPLOYEE,
            ]
        ]);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $invoices
        ]);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function sentInvoiceQuoteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice = InvoiceQuote::findFirstByUuid($uuid);

            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                if ($invoice->getStatus() != InvoiceQuote::STATUS_DRAFT && $invoice->getStatus() != InvoiceQuote::STATUS_REJECTED) {
                    $result = [
                        "success" => false,
                        "message" => "DATA_SAVE_FAIL_TEXT"
                    ];
                    goto end_of_function;
                }
                switch ($invoice->getType()) {
                    case InvoiceQuote::TYPE_CREDIT_NOTE:
                        $this->checkAcl('edit', 'credit_note');
                        break;
                    case InvoiceQuote::TYPE_INVOICE:
                        $this->checkAcl('edit', 'invoice');
                        break;
                    case InvoiceQuote::TYPE_QUOTE:
                        $this->checkAcl('edit', 'quote');
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => 'NO_PERMISSION_TEXT'
                        ];
                        goto end_of_function;
                }
                $invoice->setStatus(InvoiceQuote::STATUS_SENT);

                $result = $invoice->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'DATA_SAVE_FAIL_TEXT';
                    goto end_of_function;
                }
                $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function approveInvoiceQuoteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice = InvoiceQuote::findFirstByUuid($uuid);

            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                if ($invoice->getStatus() != InvoiceQuote::STATUS_SENT) {
                    $result = [
                        "success" => false,
                        "message" => "DATA_SAVE_FAIL_TEXT"
                    ];
                    goto end_of_function;
                }
                $expense_list = [];

                $this->db->begin();
                switch ($invoice->getType()) {
                    case InvoiceQuote::TYPE_CREDIT_NOTE:
                        $this->checkAcl('approve', 'credit_note');
                        break;
                    case InvoiceQuote::TYPE_INVOICE:
                        $this->checkAcl('approve', 'invoice');
                        $invoice_quote_items = $invoice->getInvoiceQuoteItems();
                        if (count($invoice_quote_items) > 0) {
                            foreach ($invoice_quote_items as $invoice_quote_item) {
                                if (($invoice_quote_item->getType() == InvoiceQuoteItem::TYPE_PRODUCT && $invoice_quote_item->getProductPricing()->getCost() > 0) || ($invoice_quote_item->getType() == InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT && $invoice_quote_item->getAccountProductPricing()->getCost() > 0)) {
                                    switch ($invoice_quote_item->getType()) {
                                        case InvoiceQuoteItem::TYPE_PRODUCT:
                                        case InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT:
                                            $expense = new Expense();
                                            $random = new Random();
                                            $uuid = $random->uuid();
                                            $expense->setUuid($uuid);
                                            $expense->setCompanyId(ModuleModel::$company->getId());
                                            $expense->setExpenseDate($invoice->getDate());
                                            $expense->setQuantity($invoice_quote_item->getQuantity());
                                            $expense->setTaxRuleId($invoice_quote_item->getTaxRuleId());
                                            $expense->setChargeableType(Expense::CHARGE_TO_NONE);
                                            $expense->setAccountId($invoice->getAccountId());
                                            $expense->setIsPayable(Expense::NOT_PAYABLE);
                                            $expense->setNumber($expense->generateNumber());
                                            $expense->setStatus(Expense::STATUS_APPROVED);
                                            $expense->setCurrency($invoice->getCurrency());
                                            $expense->setIsAutomated(Expense::IS_AUTOMATED);
                                            $expense->setLinkType(Expense::LINK_TYPE_RELOCATION);
                                            $expense->setRelocationId($invoice->getRelocationId());

                                            $setting = ModuleModel::$company->getRecurrentExpenseApproval();
                                            if ($setting instanceof CompanySetting) {
                                                if (intval($setting->getValue()) == 1) {
                                                    $expense->setStatus(Expense::STATUS_DRAFT);
                                                }
                                            }
                                            switch ($invoice_quote_item->getType()) {
                                                case InvoiceQuoteItem::TYPE_PRODUCT:
                                                    $product_pricing = ProductPricing::findFirstById($invoice_quote_item->getProductPricingId());
                                                    if (!$product_pricing instanceof ProductPricing || !$product_pricing->belongsToGms()) {
                                                        $this->db->rollback();
                                                        $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                                                        goto end_of_function;
                                                    }
                                                    $expense->setProductPricingId($invoice_quote_item->getProductPricingId());
                                                    $expense->setCost($product_pricing->getCost() * $invoice_quote_item->getQuantity());
                                                    $expense->setTaxInclude($invoice_quote_item->getTaxRule() instanceof TaxRule ? Expense::TAX_INCLUDE : Expense::TAX_NOT_INCLUDE);
                                                    break;
                                                case InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT:
                                                    $account_product_pricing = AccountProductPricing::findFirstById($invoice_quote_item->getAccountProductPricingId());
                                                    if (!$account_product_pricing instanceof $account_product_pricing || !$account_product_pricing->belongsToGms()) {
                                                        $this->db->rollback();
                                                        $result['message'] = 'SAVE_INVOICE_QUOTE_ITEM_FAIL_TEXT';
                                                        goto end_of_function;
                                                    }
                                                    $product_pricing = $account_product_pricing->getProductPricing();
                                                    $expense->setAccountProductPricingId($invoice_quote_item->getAccountProductPricingId());
                                                    $expense->setCost($account_product_pricing->getCost() * $invoice_quote_item->getQuantity());
                                                    $expense->setTaxInclude($invoice_quote_item->getTaxRule() instanceof TaxRule ? Expense::TAX_INCLUDE : Expense::TAX_NOT_INCLUDE);
                                                    break;
                                                default:
                                                    break;
                                            }
                                            $expense->setTotal($expense->getCost() * (1 + (($expense->getTaxInclude() == Expense::TAX_INCLUDE && $expense->getTaxRule() instanceof TaxRule) ? $expense->getTaxRule()->getRate() : 0) / 100));
                                            $result = $expense->__quickCreate();
                                            if ($result['success'] == false) {
                                                $this->db->rollback();
                                                $result['message'] = 'DATA_SAVE_FAIL_TEXT';
                                                goto end_of_function;
                                            }
                                            $expense_list[] = $expense;
                                            break;
                                    }
                                }

                            }
                        }
                        break;
                    case InvoiceQuote::TYPE_QUOTE:
                        $this->checkAcl('approve', 'quote');
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => 'NO_PERMISSION_TEXT'
                        ];
                        goto end_of_function;
                }


                $invoice->setStatus(InvoiceQuote::STATUS_APPROVED);

                $result = $invoice->__quickUpdate();

                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'DATA_SAVE_FAIL_TEXT';
                    goto end_of_function;
                }
                $this->db->commit();
                $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $result['$expense_list'] = isset($expense_list) ? $expense_list : null;
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function rejectInvoiceQuoteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice = InvoiceQuote::findFirstByUuid($uuid);

            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                if ($invoice->getStatus() != InvoiceQuote::STATUS_SENT) {
                    $result = [
                        "success" => false,
                        "message" => "DATA_SAVE_FAIL_TEXT"
                    ];
                    goto end_of_function;
                }
                switch ($invoice->getType()) {
                    case InvoiceQuote::TYPE_CREDIT_NOTE:
                        $this->checkAcl('approve', 'credit_note');
                        break;
                    case InvoiceQuote::TYPE_INVOICE:
                        $this->checkAcl('approve', 'invoice');
                        break;
                    case InvoiceQuote::TYPE_QUOTE:
                        $this->checkAcl('approve', 'quote');
                        break;
                    default:
                        $result = [
                            'success' => false,
                            'message' => 'NO_PERMISSION_TEXT'
                        ];
                        goto end_of_function;
                }
                $invoice->setStatus(InvoiceQuote::STATUS_REJECTED);

                $result = $invoice->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'DATA_SAVE_FAIL_TEXT';
                    goto end_of_function;
                }
                $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

//    /**
//     * @Route("/bill", paths={module="gms"}, methods={"GET"}
//     */
//    public function getListInvoiceableItemOfTargetAction()
//    {
//        $this->view->disable();
//        $this->checkAjaxPost();
//
//        $account_id = Helpers::__getRequestValue("account_id");
//        $employee_id = Helpers::__getRequestValue("employee_id");
//        $currency = Helpers::__getRequestValue("currency");
//
//        if ($account_id > 0) {
//            $items = InvoiceQuoteItem::find([
//                "conditions" => "company_id = :company_id: and account_id = :account_id: and currency = :currency: and charge_to = :charge_to: and invoice_quote_id is null and type = :type:",
//                "bind" => [
//                    "company_id" => ModuleModel::$company->getId(),
//                    "currency" => $currency,
//                    "type" => InvoiceQuoteItem::TYPE_EXPENSE,
//                    "account_id" => $account_id,
//                    "charge_to" => InvoiceQuote::CHARGE_TO_ACCOUNT
//                ]
//            ]);
//        } else {
//            $items = InvoiceQuoteItem::find([
//                "conditions" => "company_id = :company_id: and employee_id = :employee_id: and currency = :currency: and charge_to = :charge_to: and invoice_quote_id is null and type = :type:",
//                "bind" => [
//                    "company_id" => ModuleModel::$company->getId(),
//                    "currency" => $currency,
//                    "type" => InvoiceQuoteItem::TYPE_EXPENSE,
//                    "employee_id" => $employee_id,
//                    "charge_to" => InvoiceQuote::CHARGE_TO_EMPLOYEE
//                ]
//            ]);
//        }
//        $item_array = [];
//        foreach ($items as $item) {
//            $tmp = $item->toArray();
//            $tmp["tmp_number"] = $item->getExpense() ? $item->getExpense()->getNumber() : null;
//            $tmp["editable"] = true;
//            if ($item->getExpense() && $item->getExpense()->getExpenseCategory() instanceof ExpenseCategory) {
//                $tmp["category_name"] = $item->getExpense()->getExpenseCategory()->getName();
//            }
//            $item_array[] = $tmp;
//        }
//
//        $this->response->setJsonContent([
//            'success' => true,
//            'data' => $item_array
//        ]);
//        $this->response->send();
//    }

    public function getListInvoiceAvailableForRelocationAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();


        $type = Helpers::__getRequestValue("type");
        $account_id = Helpers::__getRequestValue("account_id");
        $employee_id = Helpers::__getRequestValue("employee_id");
        if ($account_id > 0) {
            $invoices = InvoiceQuote::find([
                "conditions" => "company_id = :company_id: and status != :archived: and type = :type: and relocation_id is null and account_id = :account_id:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => $type,
                    "archived" => InvoiceQuote::STATUS_ARCHIVED,
                    "account_id" => $account_id
                ]
            ]);
        } else if ($employee_id > 0) {
            $invoices = InvoiceQuote::find([
                "conditions" => "company_id = :company_id: and status != :archived: and type = :type: and relocation_id is null and employee_id = :employee_id:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => $type,
                    "archived" => InvoiceQuote::STATUS_ARCHIVED,
                    "employee_id" => $employee_id
                ]
            ]);
        } else {
            $invoices = InvoiceQuote::find([
                "conditions" => "company_id = :company_id: and status != :archived: and type = :type: and relocation_id is null",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => $type,
                    "archived" => InvoiceQuote::STATUS_ARCHIVED,
                ]
            ]);
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $invoices
        ]);
        $this->response->send();
    }

    public function attachInvoiceQuoteToRelocationAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $uuid = Helpers::__getRequestValue("uuid");
        $relocation_id = Helpers::__getRequestValue("relocation_id");
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice = InvoiceQuote::findFirstByUuid($uuid);

            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                $relocation = Relocation::findFirstById($relocation_id);
                if ($relocation instanceof Relocation && $relocation->belongsToGms()) {
                    $invoice->setRelocationId($relocation_id);
                    $result = $invoice->__quickUpdate();

                    if ($result['success'] == false) {
                        $result['message'] = 'DATA_SAVE_FAIL_TEXT';
                        goto end_of_function;
                    }
                    $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
                    $result['data'] = $invoice;
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @description Create
     * @param $uuid
     */
    public function createPdfAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $uuid = Helpers::__getRequestValue('uuid');
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidUuid($uuid)) {
            $invoiceQuote = InvoiceQuote::findFirstByUuid($uuid);
            if ($invoiceQuote instanceof $invoiceQuote && $invoiceQuote->belongsToGms()) {
                $pdfObject = new PdfObject();
                if ($invoiceQuote->getType() == InvoiceQuote::TYPE_INVOICE ||
                    $invoiceQuote->getType() == InvoiceQuote::TYPE_QUOTE) {
                    $pdf_uuid = Helpers::__uuid();
                    $pdfObject->setUuid($pdf_uuid);
                    $pdfObject->setObjectUuid($uuid);
                    $pdfObject->setType($invoiceQuote->getType() == InvoiceQuote::TYPE_INVOICE ? PdfObject::TYPE_INVOICE : PdfObject::TYPE_QUOTE);
                    $pdfObject->setStatus(PdfObject::STATUS_ON_LOADING);
                    $pdfObject->setCreatedAt(time());
                    $pdfObject->setUpdatedAt(time());
                    $result = $pdfObject->__quickCreate();
                    if (!$result["success"]) {
                        $result["data"] = $pdfObject->toArray();
                        goto end;
                    }
                    $queue = RelodayQueue::__getQueueGeneratePdf();

                    $addQueue = $queue->addQueue([
                        'uuid' => $invoiceQuote->getUuid(),
                        'action' => "generatePdf",
                        'params' => [
                            'uuid' => $invoiceQuote->getUuid(),
                            'type' => $invoiceQuote->getType() == InvoiceQuote::TYPE_INVOICE ? PdfObject::TYPE_INVOICE : PdfObject::TYPE_QUOTE,
                            'pdf_uuid' => $pdf_uuid
                        ],
                    ]);

                    if ($addQueue['success'] == true) {
                        $result = [
                            'success' => true,
                            'data' => $pdfObject->toArray(),
                            'detail' => $addQueue,
                            'is_request_done' => true
                        ];
                    } else {
                        $result = [
                            'success' => false,
                            'data' => $pdfObject->toArray(),
                            'detail' => $addQueue,
                            'is_request_done' => false
                        ];
                    }
                }
            }
        }

        end:
        $this->response->setJsonContent($result);
        $this->response->send();
    }


    /**
     * @description get Data and Status and PDF download link of Creation Process
     */
    public function getPdfAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {

            $invoiceQuote = InvoiceQuote::findFirstByUuid($uuid);

            if ($invoiceQuote instanceof InvoiceQuote && $invoiceQuote->belongsToGms()) {
                try {

                    $pdfObjects = PdfObject::find([
                        'conditions' => 'object_uuid = :object_uuid: and created_at >= :created_at:',
                        'bind' => [
                            'object_uuid' => $uuid,
                            'created_at' => strtotime($invoiceQuote->getUpdatedAt())
                        ],
                        'order' => 'created_at DESC'
                    ]);

                    if (count($pdfObjects) > 0) {
                        if ($pdfObjects[0]->getStatus() == PdfObject::STATUS_COMPLETED) {
                            if ($invoiceQuote->getType() == 1) {
                                $typeName = ConstantHelper::__translate("INVOICE_TEXT", ModuleModel::$language);
                            } elseif ($invoiceQuote->getType() == 0) {
                                $typeName = ConstantHelper::__translate("QUOTE_TEXT", ModuleModel::$language);
                            } else {
                                $typeName = ConstantHelper::__translate("CREDIT_NOTE_TEXT", ModuleModel::$language);
                            }

                            $fileName = $typeName . '-' . $invoiceQuote->getNumber();
                            $url = RelodayS3Helper::__getPresignedUrl("sc-cp/" . $pdfObjects[0]->getUuid() . '-content.pdf', "", $fileName . '.pdf', "application/pdf");
                            if ($url) {
                                $result['url'] = $url;
                                $result['success'] = true;
                                $result['data'] = $pdfObjects[0]->toArray();
                                $result['message'] = "";
//                                $result['time_elapsed'] = ($pdfObjects[0]->getCreatedAt() - $costProjection->getUpdatedAt()) * 1000;
                            } else {
                                $result = ['success' => false, 'data' => $pdfObjects[0]->toArray(), 'url' => $url];
                            }
                        } else {
                            $result = ['success' => false, 'data' => $pdfObjects[0]->toArray()];
                        }
                    } else {
                        $result = [
                            'success' => false
                        ];
                    }
                } catch (\Exception $e) {
                    $result = ['success' => false, 'detail' => $e->getMessage()];
                }
            }
        }

        end:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function preCalculateInvoiceProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $productData = Helpers::__getRequestValuesArray();


        $productData['price'] = floatval($productData['unit_price']) * floatval($productData['quantity']);
        $productData['total_before_tax'] = floatval($productData['price']);
        $productData['discount_amount'] = 0;
        $productData['total_tax'] = 0;

        if (!($productData['discount'] >= 0)) {
            $productData['discount'] = 0;
        }
        if ($productData['discount_enabled'] == 1 || $productData['discount_enabled'] == true) {
            if ($productData['discount_type'] == 1) {
                $productData['total_before_tax'] = $productData['price'] * (1 - $productData['discount'] / 100);
                $productData['discount_amount'] = $productData['price'] * $productData['discount'] / 100;
            } else if ($productData['discount_type'] == 2) {
                $productData['total_before_tax'] = $productData['price'] - $productData['discount'];
                $productData['discount_amount'] = $productData['discount'];
            }
        }

        if ($productData['tax_rule_id'] > 0) {

            $taxRate = TaxRule::__getActiveRateById($productData['tax_rule_id']);

            if ($taxRate) {
                $productData['tax_rate'] = $taxRate;
                $productData['total_tax'] = $productData['total_before_tax'] * $taxRate / 100;
            }
        }

        $productData['total'] = $productData['total_before_tax'] + $productData['total_tax'];

        $result = ['success' => true, 'data' => $productData];
        end:
        $this->response->setJsonContent($result);
        $this->response->send();
    }


    /**
     *
     */
    public function precalculateInvoiceableItemAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $itemData = Helpers::__getRequestValuesArray();


        $itemData['total_before_tax'] = $itemData['price'];
        $itemData['discount_amount'] = 0;
        $itemData['total_tax'] = 0;

        if (!($itemData['discount'] >= 0)) {
            $itemData['discount'] = 0;
        }
        if ($itemData['discount_enabled'] == 1 || $itemData['discount_enabled'] == true) {
            if ($itemData['discount_type'] == 1) {
                $itemData['total_before_tax'] = $itemData['price'] * (1 - $itemData['discount'] / 100);
                $itemData['discount_amount'] = $itemData['price'] * $itemData['discount'] / 100;
            } else if ($itemData['discount_type'] == 2) {
                $itemData['total_before_tax'] = $itemData['price'] - $itemData['discount'];
                $itemData['discount_amount'] = $itemData['discount'];
            }
        }

        if ($itemData['tax_rule_id'] > 0) {

            $taxRate = TaxRule::__getActiveRateById($itemData['tax_rule_id']);

            if ($taxRate) {
                $itemData['tax_rate'] = $taxRate;
                $itemData['total_tax'] = $itemData['total_before_tax'] * $taxRate / 100;
            }
        }

        $itemData['total'] = $itemData['total_before_tax'] + $itemData['total_tax'];

        $result = ['success' => true, 'data' => $itemData];
        end:
        $this->response->setJsonContent($result);
        $this->response->send();


        // item.total_before_tax = item.price;
        // item.discount_amount = 0;
        // item.total_tax = 0;
        // if (!(item.discount >= 0)) {
        //     item.discount = 0;
        // }
        // if (item.discount_enabled == 1) {
        //     if (item.discount_type == 1) {
        //         item.total_before_tax = item.price * (1 - item.discount / 100);
        //         item.discount_amount = item.price * item.discount / 100;
        //     } else if (item.discount_type == 2) {
        //         item.total_before_tax = item.price - item.discount;
        //         item.discount_amount = item.discount;
        //     }
        // }
        // if (item.tax_rule_id > 0) {
        //     angular.forEach($scope.tax_rules, function (tax_rule) {
        //         if (item.tax_rule_id == tax_rule.id) {
        //             item.tax_rate = tax_rule.rate;
        //             item.total_tax = item.total_before_tax * tax_rule.rate / 100;
        //         }
        //     });
        // }
        // item.total = item.total_before_tax + item.total_tax;
        // $scope.updateTotal();
    }
}
