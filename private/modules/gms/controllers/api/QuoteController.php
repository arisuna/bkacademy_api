<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Backend\Models\Invoice;
use Reloday\Gms\Help\Utils;
use Reloday\Gms\Models\AppSetting;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanySetting;
use Reloday\Gms\Models\Constant;
use Reloday\Gms\Models\ConstantTranslation;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\InvoiceQuote;
use Reloday\Gms\Models\InvoiceQuoteItem;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ServicePackPricing;
use Reloday\Gms\Models\ServicePricing;
use Reloday\Gms\Models\TaxRule;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\SupportedLanguage;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class QuoteController extends BaseController
{

    /**
     * @Route("/invoicequote", paths={module="gms"}, methods={"GET"}, name="gms-invoicequote-index")
     */
    public function getQuoteListAction()
    {
        // Find list company they have permission manage
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = [];

        $invoiceQuotes = InvoiceQuote::find([
            "conditions" => "owner_company_id = :owner_company_id: AND status != :status: AND type = :type: ",
            "bind" => [
                'owner_company_id' => ModuleModel::$company->getId(),
                'status' => InvoiceQuote::STATUS_DELETED,
                'type' => InvoiceQuote::TYPE_QUOTE
            ]
        ]);

        if (count($invoiceQuotes)) {
            foreach ($invoiceQuotes as $quote) {
                $result[$quote->getId()] = $quote->toArray();
                $status_map = [];
                switch ($quote->getStatus()) {
                    case InvoiceQuote::STATUS_DRAFT:
                        $status_map = [
                            'class' => 'relo-bg-yellow',
                            'value' => 'DRAFT_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_PENDING:
                        $status_map = [
                            'class' => 'relo-bg-yellow',
                            'value' => 'PENDING_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_SENT:
                        $status_map = [
                            'class' => 'relo-bg-bright-blue',
                            'value' => 'SENT_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_APPROVED:
                        $status_map = [
                            'class' => 'relo-bg-green',
                            'value' => 'ACCEPTED_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_REFUSED:
                        $status_map = [
                            'class' => 'relo-bg-red',
                            'value' => 'REFUSED_TEXT'
                        ];
                        break;
                    default:
                        break;
                }
                $result[$quote->getId()]['status_map'] = $status_map;
                if ($quote->getInvoiceQuote() && $quote->getInvoiceQuote()->isInvoice() && $quote->getInvoiceQuote()->isDeleted() == false) {
                    $result[$quote->getId()]['invoice_number'] = $quote->getInvoiceQuote()->getNumber();
                    $result[$quote->getId()]['invoice_uuid'] = $quote->getInvoiceQuote()->getUuid();
                } else {
                    $result[$quote->getId()]['invoice_quote_id'] = 0;
                }
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $result
        ]);
        return $this->response->send();
    }

    /**
     * @Route("/invoicequote", paths={module="gms"}, methods={"GET"}, name="gms-invoicequote-index")
     */
    public function getInvoiceListAction()
    {
        // Find list company they have permission manage
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = [];

        $invoiceQuotes = InvoiceQuote::find([
            "conditions" => "owner_company_id = :owner_company_id: AND status != :status: AND type = :type: ",
            "bind" => [
                'owner_company_id' => ModuleModel::$company->getId(),
                'status' => InvoiceQuote::STATUS_DELETED,
                'type' => InvoiceQuote::TYPE_INVOICE
            ]
        ]);

        if (count($invoiceQuotes)) {
            foreach ($invoiceQuotes as $invoice) {
                $result[$invoice->getId()] = $invoice->toArray();
                $status_map = [];
                switch ($invoice->getStatus()) {
                    case InvoiceQuote::STATUS_DRAFT:
                        $status_map = [
                            'class' => 'relo-bg-yellow',
                            'value' => 'DRAFT_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_PENDING:
                        $status_map = [
                            'class' => 'bg-inverse',
                            'value' => 'PENDING_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_SENT:
                        $status_map = [
                            'class' => 'relo-bg-bright-blue',
                            'value' => 'SENT_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_APPROVED:
                        $status_map = [
                            'class' => 'relo-bg-green',
                            'value' => 'ACCEPTED_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_REFUSED:
                        $status_map = [
                            'class' => 'relo-bg-red',
                            'value' => 'REFUSED_TEXT'
                        ];
                        break;
                    default:
                        break;
                }

                $status_paid_map = [
                    'class' => 'relo-bg-yellow',
                    'value' => 'UNPAID_TEXT'
                ];

                switch ($invoice->getIsPaid()) {
                    case InvoiceQuote::STATUS_UNPAID:
                        $status_paid_map = [
                            'class' => 'relo-bg-yellow',
                            'value' => 'UNPAID_TEXT'
                        ];
                        break;
                    case InvoiceQuote::STATUS_PAID:
                        $status_paid_map = [
                            'class' => 'relo-bg-green',
                            'value' => 'PAID_TEXT'
                        ];
                        break;
                    default:
                        break;
                }
                $result[$invoice->getId()]['status_map'] = $status_map;
                $result[$invoice->getId()]['status_paid_map'] = $status_paid_map;
                $result[$invoice->getId()]['company_name'] = $invoice->getCompany() ? $invoice->getCompany()->getName() : '';
            }
        }

        end_of_function:
        $this->response->setJsonContent([
            'success' => true,
            'data' => $result
        ]);
        return $this->response->send();
    }


    /**
     * Get detail of object
     * @param string $id
     */
    public function detailAction($id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $id = (int)($id ? $id : $this->request->get('id'));
        $data = InvoiceManage::findFirstById($id);
        if ($data instanceof InvoiceManage && $data->belongsToGms() && $data->isDeleted() == false) {

            $content = $data->toArray();
            $content['items'] = json_decode($content['items'], true);

            $content['tax_value'] = floatval($data->getTaxRule()->getRate());
            if ($content['tax_value'] > 0) {
                $content['applied_taxes'] = true;
            } else {
                $content['applied_taxes'] = false;
            }
            $content['discount'] = floatval($content['discount']);
            if ($content['discount'] > 0) {
                $content['applied_discount'] = true;
            } else {
                $content['applied_discount'] = false;
            }
            $result = [
                'success' => true,
                'data' => $content
            ];

        } else {
            $result = [
                'success' => false, 'message' => 'INVOICE_QUOTE_NOT_FOUND_TEXT'
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Get detail of object
     * @param string $id
     */
    public function detailByUuidAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }
        $invoice = InvoiceQuote::findFirstByUuid($uuid);

        if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms() && $invoice->isDeleted() == false) {

            $content = $invoice->toArray();
            $content['items'] = $invoice->getInvoiceQuoteItems();

            $content['tax_value'] = floatval($invoice->getTaxRate());
            if ($content['tax_value'] > 0) {
                $content['applied_taxes'] = true;
            } else {
                $content['applied_taxes'] = false;
            }
            $content['discount'] = floatval($content['discount']);
            if ($content['discount'] > 0) {
                $content['applied_discount'] = true;
            } else {
                $content['applied_discount'] = false;
            }
            $result = [
                'success' => true,
                'data' => $content
            ];

        } else {
            $result = [
                'success' => false, 'message' => 'INVOICE_QUOTE_NOT_FOUND_TEXT'
            ];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Create or update method
     * @return string
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();


        $items = Helpers::__getRequestValueAsArray('items');
        $quoteUuid = Helpers::__getRequestValue('quote_uuid');
        $opt = Helpers::__getRequestValue('opt');

        $isInvoice = Helpers::__getRequestValue('is_invoice');
        $isQuote = Helpers::__getRequestValue('is_quote');
        $quoteData = Helpers::__getRequestValue('quote');
        $paramsData = $this->getParamsData();

        $newQuoteNumber = InvoiceQuote::__getNewQuoteNumber(ModuleModel::$company->getId());


        $this->db->begin();
        $invoiceQuote = new InvoiceQuote();
        $invoiceQuote->setData($paramsData);
        $invoiceQuote->setNumber($newQuoteNumber);
        $resultInvoiceCreate = $invoiceQuote->__quickCreate();


        if ($resultInvoiceCreate['success'] == false) {
            $result = [
                'success' => false,
                'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT',
                'detail' => $resultInvoiceCreate['detail'],
            ];
            $this->db->rollback();
            goto end_of_function;
        } elseif ($resultInvoiceCreate['success'] == true) {
            $result = [
                'success' => true,
                'message' => 'SAVE_INVOICE_QUOTE_SUCCESS_TEXT',
                'data' => $invoiceQuote
            ];


            // Check if make invoice from QUOTE
            if ($quoteUuid != '' && Helpers::__isValidUuid($quoteUuid)) {
                $quoteOrigin = InvoiceQuote::findFirstByUuid($quoteUuid);
                if ($quoteOrigin instanceof InvoiceQuote && $quoteOrigin->belongsToGms() && $quoteOrigin->getType() == InvoiceQuote::TYPE_QUOTE) {
                    $quoteOrigin->setInvoiceQuoteId($invoiceQuote->getId());
                    $resultUpdateQuote = $quoteOrigin->__quickUpdate();

                    if ($resultUpdateQuote['success'] == false) {
                        $result = ['success' => false, 'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT'];
                        goto end_of_function;
                    }
                }
            }


            foreach ($items as $item) {
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $invoiceQuoteItem = InvoiceQuoteItem::findFirstById($item['id']);
                    if (!$invoiceQuoteItem && $invoiceQuoteItem->getInvoiceQuoteId() !== $invoiceQuote->getId()) {
                        continue;
                    }
                } else {
                    $invoiceQuoteItem = new InvoiceQuoteItem();
                }

                if (isset($quoteOrigin) &&
                    $quoteOrigin instanceof InvoiceQuote &&
                    $quoteOrigin->belongsToGms() &&
                    $quoteOrigin->getType() == InvoiceQuote::TYPE_QUOTE) {
                    $invoiceQuoteItem = new InvoiceQuoteItem();
                }

                if ($invoiceQuote->checkValidateItem($item)) {
                    $invoiceQuoteItem->setData([
                        'invoice_quote_id' => $invoiceQuote->getId(),
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'currency_code' => $item['currency_code'],
                        'service_pricing_id' => $item['service_pricing_id']
                    ]);

                    if ($invoiceQuoteItem->getId() > 0) {
                        $resultSaveInvoiceItem = $invoiceQuoteItem->__quickUpdate();
                    } else {
                        $resultSaveInvoiceItem = $invoiceQuoteItem->__quickCreate();
                    }

                    if ($resultSaveInvoiceItem['success'] == false) {
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT', 'detail' => $resultSaveInvoiceItem['detail']
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                }
            }


            // Check if make invoice from QUOTE
            if ($quoteUuid != '' && Helpers::__isValidUuid($quoteUuid)) {
                $quote = InvoiceQuote::findFirstByUuid($quoteUuid);
                if ($quote instanceof InvoiceQuote && $quote->belongsToGms() && $quote->getType() == InvoiceQuote::TYPE_QUOTE) {
                    $quote->setInvoiceQuoteId($invoiceQuote->getId());
                    $resultUpdateQuote = $quote->__quickUpdate();

                    if ($resultUpdateQuote['success'] == false) {
                        $result = ['success' => false, 'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT'];
                        goto end_of_function;
                    }
                }
            }


            $this->db->commit();

            switch ($opt) {
                case 'send' :
                    $resultSendInvoice = $invoiceQuote->sendEmail();
                    break;
                default:
                    break;
            }
        }

        end_of_function:
        if ($result['success'] == false && isset($result['detail']) && $result['detail']) {
            $result['message'] = 'SAVE_INVOICE_QUOTE_FAIL_TEXT';
            if (is_array($result['detail'])) {
                $result['detail'] = reset($result['detail']);
            }
            if (Helpers::__isLabelConstant($result['detail'])) {
                $result['message'] = $result['detail'];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Create or update method
     * @return string
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();


        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $items = Helpers::__getRequestValueAsArray('items');
        $quoteUuid = Helpers::__getRequestValue('quote_uuid');
        $invoiceUuid = Helpers::__getRequestValue('uuid');
        $opt = Helpers::__getRequestValue('opt');

        if (!($invoiceUuid != '' && Helpers::__isValidUuid($invoiceUuid))) {
            goto end_of_function;
        }
        $paramsData = $this->getParamsData();

        $this->db->begin();

        $invoiceQuote = InvoiceQuote::findFirstByUuid($invoiceUuid);

        if ($invoiceQuote && $invoiceQuote->belongsToGms() && $invoiceQuote->isDeleted() == false) {
            $invoiceQuote->setData($paramsData);
            $resultInvoiceCreate = $invoiceQuote->__quickUpdate();
            if ($resultInvoiceCreate['success'] == false) {
                $result = [
                    'success' => false,
                    'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT',
                    'detail' => $resultInvoiceCreate['detail'],
                ];
                $this->db->rollback();
                goto end_of_function;
            } elseif ($resultInvoiceCreate['success'] == true) {
                $result = [
                    'success' => true,
                    'message' => 'SAVE_INVOICE_QUOTE_SUCCESS_TEXT',
                    'data' => $invoiceQuote
                ];

                /******* delete quote item *******/
                $invoicedIds = [];
                foreach ($items as $item) {
                    if (isset($item['id']) && is_numeric($item['id'])) {
                        $invoicedIds[] = $item['id'];
                    }
                }

                if (count($invoicedIds) > 0) {
                    $invoiceQuoteItems = $invoiceQuote->getInvoiceQuoteItem([
                        'conditions' => 'id NOT IN ({ids:array})',
                        'bind' => [
                            'ids' => $invoicedIds
                        ]
                    ]);
                } else {
                    $invoiceQuoteItems = $invoiceQuote->getInvoiceQuoteItem();
                }

                foreach ($invoiceQuoteItems as $invoiceQuoteItem) {
                    $resultDelete = $invoiceQuoteItem->__quickRemove();
                    if ($resultDelete['success'] == false) {
                        $result = ['success' => false, 'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT', 'detail' => $resultDelete['detail']];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                }
                /******* create quote item *******/

                foreach ($items as $item) {
                    if (isset($item['id']) && is_numeric($item['id'])) {
                        $invoiceQuoteItem = InvoiceQuoteItem::findFirstById($item['id']);
                        if (!$invoiceQuoteItem && $invoiceQuoteItem->getInvoiceQuoteId() !== $invoiceQuote->getId()) {
                            continue;
                        }
                    } else {
                        $invoiceQuoteItem = new InvoiceQuoteItem();
                    }

                    if ($invoiceQuote->checkValidateItem($item)) {
                        $invoiceQuoteItem->setData([
                            'invoice_quote_id' => $invoiceQuote->getId(),
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'quantity' => $item['quantity'],
                            'currency_code' => $item['currency_code'],
                            'service_pricing_id' => $item['service_pricing_id']
                        ]);

                        if ($invoiceQuoteItem->getId() > 0) {
                            $resultSaveInvoiceItem = $invoiceQuoteItem->__quickUpdate();
                        } else {
                            $resultSaveInvoiceItem = $invoiceQuoteItem->__quickCreate();
                        }

                        if ($resultSaveInvoiceItem['success'] == false) {
                            $result = ['success' => false, 'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT', 'detail' => $resultSaveInvoiceItem['detail']];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }
                }

                $this->db->commit();

                switch ($opt) {
                    case 'send' :
                        $resultSendInvoice = $invoiceQuote->sendEmail();
                        break;
                    default:
                        break;
                }
            }
        }

        end_of_function:
        if ($result['success'] == false && isset($result['detail']) && $result['detail']) {
            $result['message'] = 'SAVE_INVOICE_QUOTE_FAIL_TEXT';
            if (is_array($result['detail'])) {
                $result['detail'] = reset($result['detail']);
            }
            if (Helpers::__isLabelConstant($result['detail'])) {
                $result['message'] = $result['detail'];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $result = ['success' => false, 'message' => 'INVOICE_NOT_FOUND_TEXT'];
        $id = (int)$id;
        $invoice = InvoiceQuote::findFirstById($id);
        if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
            $quote = $invoice->getQuote();
            $result = $invoice->__quickRemove();
            if ($result['success'] == true) {

                if ($invoice->isInvoice() == true && $quote) {
                    $quote = $invoice->getQuote();
                    $quote->setInvoiceQuoteId(0);
                    $quote->__quickUpdate();
                }

                $result['message'] = 'INVOICE_DELETE_SUCCESS_TEXT';
            } else {
                $result['message'] = 'INVOICE_DELETE_FAIL_TEXT';
            }
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteByUuidAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $result = ['success' => false, 'message' => 'INVOICE_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $invoice = InvoiceQuote::findFirstByUuid($uuid);
            if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
                $result = $invoice->__quickRemove();
                if ($result['success'] == true) {
                    $result['message'] = 'INVOICE_DELETE_SUCCESS_TEXT';
                } else {
                    $result['message'] = 'INVOICE_DELETE_FAIL_TEXT';
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param int $invoice_id
     */
    public function downloadAction($invoice_id = 0)
    {
        // Load data
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $invoice_id = (int)($invoice_id ? $invoice_id : $this->request->get('invoice_id'));
        $invoice = InvoiceQuote::findFirstById($invoice_id);
        if ($invoice instanceof InvoiceQuote && $invoice->belongsToGms()) {
            $invoice->downloadPdfFromHTML();
            $this->view->disable();
        }
    }

    /**
     * @param int $id
     */
    public function printAction($uuid = '')
    {
        // Load config
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $result = ["success" => false, 'message' => 'INVOICE_NOT_FOUND_TEXT'];

        if (!($uuid != '') && Helpers::__isValidUuid($uuid)) {
            goto end_of_function;
        } else {

            $config = CompanySetting::findByCompanyId(ModuleModel::$company->getId());

            $config_arr = [];
            if (count($config)) {
                foreach ($config as $item) {
                    $config_arr[$item->getName()] = $item->getValue();
                }
            }

            // Load invoice
            $invoice = InvoiceQuote::findFirstByUuid($uuid);

            $data = [];
            if ($invoice && $invoice->belongsToGms() && $invoice->isDeleted() == false) {
                $data = $invoice->toArray();

                $data['items'] = $invoice->getInvoiceQuoteItems();
                if ($invoice->getCompany()) {
                    $data['company_name'] = $invoice->getCompany()->getName();
                }
                if ($invoice->getCountry()) {
                    $data['country_name'] = $invoice->getCountry()->getName();
                }
            }

            $base_company = ModuleModel::$company;
            $country = $base_company->getCountry();
            if (!$country) {
                $country = $invoice->getCountry();
            }
            $base_company = $base_company->toArray();
            // Add country name
            $base_company['country'] = $country instanceof Country ? $country->getName() : '';

            // Add tax value
            $data['tax_value'] = $invoice->getTaxRate();
            $result = [
                'success' => true,
                'config' => $config_arr,
                'data' => $data,
                'base_company' => $base_company
            ];


            end_of_function:
            $this->response->setJsonContent($result);
            return $this->response->send();
        }

    }

    /**
     * @return array
     */
    public function getParamsData()
    {
        $isInvoice = Helpers::__getRequestValue('is_invoice');
        $isQuote = Helpers::__getRequestValue('is_quote');

        $paramsData = [
            'type' => $isInvoice ? InvoiceQuote::TYPE_INVOICE : InvoiceQuote::TYPE_QUOTE,
            'owner_company_id' => ModuleModel::$user_profile->getCompanyId(),
            'company_id' => Helpers::__getRequestValue('company_id'),
            'currency_code' => Helpers::__getRequestValue('currency_code'),
            'date' => Helpers::__getRequestValue('date'),
            'discount' => Helpers::__getRequestValue('discount'),
            'discount_total' => Helpers::__getRequestValue('discount_total'),
            'reference' => Helpers::__getRequestValue('reference'),
            'subtotal' => Helpers::__getRequestValue('subtotal'),
            'tax_rule_id' => Helpers::__getRequestValue('tax_rule_id'),
            'tax_value' => Helpers::__getRequestValue('tax_value'),
            'total' => Helpers::__getRequestValue('total'),
            'items' => Helpers::__getRequestValueAsArray('items'),

            'email' => Helpers::__getRequestValue('email'),
            'contact_name' => Helpers::__getRequestValue('contact_name'),
            'origin' => Helpers::__getRequestValue('origin'),
            'address' => Helpers::__getRequestValue('address'),
            'town' => Helpers::__getRequestValue('town'),
            'country_id' => Helpers::__getRequestValue('country_id'),
        ];

        return $paramsData;
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function cloneAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclCreate();

        $uuid = Helpers::__getRequestValue('uuid');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $quote = InvoiceQuote::findFirstByUuid($uuid);
            if ($quote && $quote->belongsToGms()) {
                $quoteClone = new InvoiceQuote();
                $inputClone = $quote->toArray();

                unset($inputClone['id']);
                unset($inputClone['number']);
                unset($inputClone['uuid']);
                unset($inputClone['invoice_id']);
                unset($inputClone['sent']);

                $inputClone['status'] = InvoiceQuote::STATUS_PENDING;
                $quoteClone->setData($inputClone);
                $result = $quoteClone->__quickCreate();

                if ($result['success'] == true) {
                    $return = ['success' => true, 'message' => 'SAVE_INVOICE_QUOTE_SUCCESS_TEXT', 'data' => $quoteClone, 'raw' => $inputClone];
                } else {
                    $return = ['success' => true, 'message' => 'SAVE_INVOICE_QUOTE_FAIL_TEXT', 'detail' => $result['success']];
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function countInvoiceQuoteAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => true,
            'data' => [
                'number_invoice' => InvoiceQuote::countInvoice(),
                'number_quote' => InvoiceQuote::countQuote()
            ]
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
