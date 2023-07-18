<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyBusinessDetail;
use Reloday\Gms\Models\CompanyFinancialDetail;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\InvoiceTemplate;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\MediaAttachment;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class InvoiceTemplateController extends BaseController
{

    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $templates = InvoiceTemplate::find([
            "conditions" => "is_deleted = 0 and company_id = :company_id:",
            "bind" => [
                "company_id" => ModuleModel::$company->getId()
            ]
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $templates,
        ]);
        $this->response->send();

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $uuid = Helpers::__getRequestValue("uuid");
        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'INVOICE_TEMPLATE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $template = InvoiceTemplate::findFirstByUuid($uuid);

            if ($template instanceof InvoiceTemplate && $template->belongsToGms()) {
                if(isset($data['invoice_tc']) && $data['invoice_tc'] != null && $data['invoice_tc'] != ''){
                    $data['invoice_tc'] = rawurldecode(base64_decode($data['invoice_tc']));
                }

                $template->setData($data);
                $templateIfExist = InvoiceTemplate::findFirst([
                    "conditions" => "name = :name: and company_id = :company_id: and id != :id: and is_deleted = 0 ",
                    "bind" => [
                        "name" => Helpers::__getRequestValue("name"),
                        "company_id" => ModuleModel::$company->getId(),
                        "id" => $template->getId()
                    ]
                ]);
                if ($templateIfExist instanceof InvoiceTemplate) {
                    $result['message'] = 'INVOICE_TEMPLATE_NAME_MUST_BE_UNIQUE_TEXT';
                    $result['success'] = false;
                    goto end_of_function;
                }
                $template->setIsDeleted(Helpers::NO); // make it available

                $result = $template->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_INVOICE_TEMPLATE_FAIL_TEXT';
                } else $result['message'] = 'SAVE_INVOICE_TEMPLATE_SUCCESS_TEXT';

            }
        } else {
            $template = new InvoiceTemplate();
            $data['company_id'] = ModuleModel::$company->getId();
            if(isset($data['invoice_tc']) && $data['invoice_tc'] != null && $data['invoice_tc'] != ''){
                $data['invoice_tc'] = rawurldecode(base64_decode($data['invoice_tc']));
            }
            $template->setData($data);
            $template->setIsDeleted(Helpers::YES); // make it temporary because we had avatar when create > we need uuid
            $templateIfExist = InvoiceTemplate::findFirst([
                "conditions" => "name = :name: and company_id = :company_id: and is_deleted = 0 ",
                "bind" => [
                    "name" => Helpers::__getRequestValue("name"),
                    "company_id" => ModuleModel::$company->getId()
                ]
            ]);
            if ($templateIfExist instanceof InvoiceTemplate) {
                $template->setName($template->getName() . $template->getUuid());
            }
            $result = $template->__quickCreate();
            if ($result['success'] == false) {
                $result['message'] = 'SAVE_INVOICE_TEMPLATE_FAIL_TEXT';
            } else {
                $result['message'] = 'SAVE_INVOICE_TEMPLATE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeAction($uuid = "")
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'INVOICE_TEMPLATE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $template = InvoiceTemplate::findFirstByUuid($uuid);

            if ($template instanceof InvoiceTemplate && $template->belongsToGms()) {

                $return = $template->__quickRemove();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $uid
     */
    public function detailsAction($uid = "")
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = [
            'success' => false,
            'message' => 'INVOICE_TEMPLATE_NOT_FOUND_TEXT'
        ];
        if ($uid != '' && Helpers::__isValidUuid($uid)) {
            $template = InvoiceTemplate::findFirstByUuid($uid);
        } elseif (Helpers::__isValidId($uid)) {
            $template = InvoiceTemplate::findFirstById($uid);
        }
        if (isset($template) && $template instanceof InvoiceTemplate && $template->belongsToGms()) {
            $return = ['success' => true, 'data' => $template];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $uuid
     */
    public function setDefaultInvoiceTemplateAction($uuid = "")
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'FINANCIAL_ACCOUNT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $invoice_template = InvoiceTemplate::findFirstByUuid($uuid);

            if ($invoice_template instanceof InvoiceTemplate && $invoice_template->belongsToGms()) {
                $this->db->begin();
                $default_invoice_template = InvoiceTemplate::findFirst([
                    "conditions" => "company_id = :company_id: and is_deleted = 0 and is_default =1",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId()
                    ]
                ]);
                if($default_invoice_template instanceof InvoiceTemplate and $default_invoice_template->getUuid() != $uuid){
                    $default_invoice_template->setIsDefault(0);
                    $return = $default_invoice_template->__quickUpdate();

                    if ($return['success'] == false) {
                        $return['message'] = 'SAVE_INVOICE_TEMPLATE_FAIL_TEXT';
                        $this->db->rollback();
                        goto end_of_function;
                    }

                }
                $invoice_template->setIsDefault(1);
                $return = $invoice_template->__quickUpdate();

                if ($return['success'] == false) {
                    $return['message'] = 'SAVE_INVOICE_TEMPLATE_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }

                $this->db->commit();
                $return = [
                    "success" => true,
                    "message" => 'SAVE_INVOICE_TEMPLATE_SUCCESS_TEXT'
                ];

            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getListByIdsAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $ids = Helpers::__getRequestValue('ids');
        if (!is_array($ids)){
            $ids = explode(',', $ids);
        }

        $invoice_templates = InvoiceTemplate::find([
            'conditions' => 'is_deleted = 0 and company_id = :company_id: AND id IN ({ids:array})',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'ids' => $ids,
            ],
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $invoice_templates
        ]);
        return $this->response->send();
    }
}
