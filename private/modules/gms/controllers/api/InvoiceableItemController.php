<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\InvoiceableItem;
use Reloday\Gms\Models\Relocation;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class InvoiceableItemController extends BaseController
{

    /**
     * @Route("/invoiceable-item", paths={module="gms"}, methods={"GET"}
     */
    public function getListInvoiceableItemAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_INVOICE);

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['companies'] = [];
        $params['invoice_statues'] = [];
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        $invoice_statues = Helpers::__getRequestValue('invoice_statues');
        if (is_array($invoice_statues) && count($invoice_statues) > 0) {
            foreach ($invoice_statues as $invoice_status) {
                if (isset($invoice_status->value)) {
                    $params['invoice_statues'][] = $invoice_status->value;
                }
            }
        }

        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                if (isset($company->id)) {
                    $params['companies'][] = $company->id;
                }
            }
        }
        $params['assignees'] = [];
        $assignees = Helpers::__getRequestValue('assignees');
        if (is_array($assignees) && count($assignees) > 0) {
            foreach ($assignees as $assignee) {
                if (isset($assignee->id)) {
                    $params['assignees'][] = $assignee->id;
                }
            }
        }
        $params['categories'] = [];
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                if (isset($category->id)) {
                    $params['categories'][] = $category->id;
                }
            }
        }
        $params['companies'] = [];
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['companies'][] = $company->id;
            }
        }
        $params['start_date'] = Helpers::__getRequestValue('start_date');
        $params['end_date'] = Helpers::__getRequestValue('end_date');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['account_id'] = Helpers::__getRequestValue('account_id');
        $params['employee_id'] = Helpers::__getRequestValue('employee_id');
        $params['relocation_id'] = Helpers::__getRequestValue('relocation_id');
        $params['currency'] = Helpers::__getRequestValue('currency');
        $params['has_no_link_invoice'] = Helpers::__getRequestValue('has_no_link_invoice');
        $params['has_no_link_credit_note'] = Helpers::__getRequestValue('has_no_link_credit_note');
        $params['has_no_link_relocation'] = Helpers::__getRequestValue('has_no_link_relocation');

        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $logs = InvoiceableItem::__findWithFilter($params, $ordersConfig);

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
     * @param $uuid
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_INVOICE);
        $result = [
            'success' => false,
            'message' => 'INVOICEABLE_ITEM_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoiceable_item = InvoiceableItem::findFirstByUuid($uuid);

            if ($invoiceable_item instanceof InvoiceableItem && $invoiceable_item->belongsToGms()) {
                $item = $invoiceable_item->toArray();
                $expense = $invoiceable_item->getExpense();
                if ($invoiceable_item->getRelocation() instanceof Relocation) {
                    $item["relocation"] = $invoiceable_item->getRelocation()->toArray();
                    $item["relocation"]["number"] = $item["relocation"]["identify"];
                    $item["relocation"]["assignment"] = $invoiceable_item->getRelocation()->getAssignment()->toArray();
                    $item["relocation"]["company_name"] = $invoiceable_item->getRelocation()->getHrCompany()->getName();
                    $item["relocation"]["employee_uuid"] = $invoiceable_item->getRelocation()->getEmployee()->getUuid();
                    $item["relocation"]["employee_name"] = $invoiceable_item->getRelocation()->getEmployee()->getFirstname() . ' ' . $invoiceable_item->getRelocation()->getEmployee()->getLastname();
                }
                $item["account_name"] = $invoiceable_item->getAccount() ? $invoiceable_item->getAccount()->getName() : "";
                $item["tax_rule_name"] = $invoiceable_item->getTaxrule() ? $invoiceable_item->getTaxrule()->getName() : "";
                $item["expense_category"] = $invoiceable_item->getExpenseCategory() ? $invoiceable_item->getExpenseCategory()->toArray() : [];
                $item["expense"] = $expense->toArray();
                $item["expense"]["service_provider_name"] = $expense->getServiceProviderCompany() ? $expense->getServiceProviderCompany()->getName() : "";
                $item["invoice"] = $invoiceable_item->getInvoice();
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
     * @param $uuid
     */
    public function removeAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclDelete(AclHelper::CONTROLLER_INVOICE);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $items = Helpers::__getRequestValue('items');
        if (is_array($items) && count($items) > 0) {
            $this->db->begin();
            foreach ($items as $item) {
                if (isset($item->uuid)) {
                    $uuid = $item->uuid;
                    if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

                        $invoiceable_item = InvoiceableItem::findFirstByUuid($uuid);

                        if (!$invoiceable_item instanceof InvoiceableItem || !$invoiceable_item->belongsToGms()) {
                            $return = [
                                'success' => false,
                                'message' => 'INVOICEABLE_ITEM_NOT_FOUND_TEXT'
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                        if ($invoiceable_item->isDeletable() == false) {
                            $return = [
                                "success" => false,
                                "message" => "INVOICEABLE_ITEM_LINKED_TO_INVOICE_TEXT"
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                        $return = $invoiceable_item->__quickRemove();
                        if (!$return['success']) {
                            $return = [
                                "success" => false,
                                "message" => "DATA_REMOVE_FAILED_TEXT"
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    } else {
                        $return = [
                            'success' => false,
                            'message' => 'INVOICEABLE_ITEM_NOT_FOUND_TEXT'
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                } else {
                    $return = [
                        'success' => false,
                        'message' => 'INVOICEABLE_ITEM_NOT_FOUND_TEXT'
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }
            }
            $return = [
                'success' => true,
                'message' => 'DATA_REMOVE_SUCCESS_TEXT'
            ];
            $this->db->commit();
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
