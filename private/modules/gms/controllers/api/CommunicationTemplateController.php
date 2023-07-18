<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\ModelHelper;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\Assignment;
use \Reloday\Gms\Models\Company;
use \Reloday\Gms\Models\CommentsTemplate;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\MediaAttachment;
use \Reloday\Gms\Models\ModuleModel;
use \Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Help\AutofillEmailTemplateHelper;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ServiceCompany;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CommunicationTemplateController extends BaseController
{
    /**
     * @Route("/allowancetitle", paths={module="gms"}, methods={"GET"}, name="gms-allowancetitle-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION); //list all templates = COMMUNICATION OK
        $templates = CommentsTemplate::__loadByCompany();
        $results = ['success' => true, 'data' => $templates];
        $this->response->setJsonContent($results);
        $this->response->send();
    }

    /**
     * init controller
     * @return [type] [description]
     */
    public function simpleAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $query = Helpers::__getRequestValue('query');
        $templates = CommentsTemplate::__loadByCompany($query);
        $results = ['success' => true, 'data' => $templates];
        $this->response->setJsonContent($results);
        $this->response->send();
    }


    /**
     * init controller
     * @return [type] [description]
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['has_autofill'] = Helpers::__getRequestValue('has_autofill');
        $params['has_files'] = Helpers::__getRequestValue('has_files');
        $params['supported_language_id'] = Helpers::__getRequestValue('supported_language_id');
        $params['is_active'] = Helpers::__getRequestValue('is_active');

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


        $results = CommentsTemplate::__findWithFilter($params, $ordersConfig);
        $this->response->setJsonContent($results);
        $this->response->send();
    }

    /**
     * list assigmnet type by company
     * @param  string $company_id [description]
     * @return [type]             [description]
     */
    public function companyAction($company_id = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);
        $templates = CommentsTemplate::__loadByCompany($company_id);
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $templates]);
        return $this->response->send();
    }


    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit(AclHelper::CONTROLLER_COMMUNICATION_TEMPLATE);

        $data = $this->request->getJsonRawBody();
        $id = Helpers::__getRequestValue('id');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (isset($id) && is_numeric($id) && $id > 0) {
            $commentsTemplateManager = CommentsTemplate::findFirstById($id);
            if (!$commentsTemplateManager || !$commentsTemplateManager->belongsToGms()) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                $this->response->setJsonContent($result);
                return $this->response->send();
            }

            $isMapField = false;
            if(isset($data->message)){
                $message = rawurldecode(base64_decode($data->message));
                $isMapField = AutofillEmailTemplateHelper::isExistedField($message);
            }


            $commentsTemplateManager->setData([
                'subject' => isset($data->subject) ? $data->subject : null,
                'company_id' => ModuleModel::$company->getId(),
            ]);

            $commentsTemplateManager->setMessage(isset($data->message) ? rawurldecode(base64_decode($data->message)) : null);
            $commentsTemplateManager->setIsActive(isset($data->is_active) ? $data->is_active : ModelHelper::YES);
            $commentsTemplateManager->setIsMapField($isMapField ? ModelHelper::YES : ModelHelper::NO);

            $resultSave = $commentsTemplateManager->__quickUpdate();

            if ($resultSave['success'] == true) {
                $result = ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $commentsTemplateManager];
            } else {
                $result = $resultSave;
            }

        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax(['POST']);
        $this->checkAclCreate(AclHelper::CONTROLLER_COMMUNICATION_TEMPLATE);

        $data = $this->request->getJsonRawBody();
        $items = Helpers::__getRequestValue('items');


        $commentsTemplateManager = new CommentsTemplate();
        $commentsTemplateManager->setData([
            'subject' => isset($data->subject) ? $data->subject : null,
            'message' => isset($data->message) ? rawurldecode(base64_decode($data->message)) : null,
            'company_id' => ModuleModel::$company->getId(),
            'reference' => CommentsTemplate::__generateNumber(ModuleModel::$company->getId())
        ]);

        $commentsTemplateManager->setIsActive(ModelHelper::NO);

        $this->db->begin();
        $resultSave = $commentsTemplateManager->__quickCreate();

        if ($resultSave['success'] == true) {
            $result = ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $commentsTemplateManager];
            if (is_array($items) && count($items) > 0) {
                $resultAttach = MediaAttachment::__createAttachments([
                    'objectUuid' => $commentsTemplateManager->getUuid(),
                    'fileList' => $items,
                ]);

                if (!$resultAttach["success"]) {
                    $this->db->rollback();
                    $result = ['success' => false, 'detail' => $resultAttach, 'message' => 'DATA_SAVE_FAIL_TEXT'];
                    goto end_of_function;
                }
            }
            $this->db->commit();
        } else {
            $this->db->rollback();
            $result = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $resultSave['detail']];
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

     /**
     * [createAction description]
     * @return [type] [description]
     */
    public function previewAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT']);
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION_TEMPLATE);

        $data = $this->request->getJsonRawBody();
        $content =  isset($data->message) ? rawurldecode(base64_decode($data->message)) : null;
        
        $result = AutofillEmailTemplateHelper::previewContent($content);
        

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $id : id for allowance title
     */
    public function itemAction($id)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        if (is_numeric($id) && $id > 0) {
            $template = CommentsTemplate::findFirstById($id);
        }

        if (is_string($id) && Helpers::__isValidUuid($id)) {
            $template = CommentsTemplate::findFirstByUuid($id);
        }

        if (isset($template) && $template && $template instanceof CommentsTemplate && $template->belongsToGms() == true) {
            $templateArray = $template->toArray();
            $mediaList = MediaAttachment::__findWithFilter([
                'limit' => 1000,
                'object_uuid' => $template->getUuid(),
                'object_name' => false,
                'is_shared' => false,
                'sharer_uuid' => ModuleModel::$company->getUuid()
            ]);
            if($mediaList['success']) {
                $templateArray['items'] = $mediaList['data'];
            } else {
                $templateArray['items'] = [];
            }
            $return = [
                'success' => true,
                'message' => 'DATA_FOUND_SUCCESS_TEXT',
                'data' => $templateArray
            ];
        } else {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id : id for allowance title
     */
    public function itemAutoFillAction($id)
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        if (is_numeric($id) && $id > 0) {
            $template = CommentsTemplate::findFirstById($id);
        }

        if (is_string($id) && Helpers::__isValidUuid($id)) {
            $template = CommentsTemplate::findFirstByUuid($id);
        }

        if (isset($template) && $template && $template instanceof CommentsTemplate && $template->belongsToGms() == true) {
            $templateArray = $template->toArray();
            $mediaList = MediaAttachment::__findWithFilter([
                'limit' => 1000,
                'object_uuid' => $template->getUuid(),
                'object_name' => false,
                'is_shared' => false,
                'sharer_uuid' => ModuleModel::$company->getUuid()
            ]);
            if($mediaList['success']) {
                $templateArray['items'] = $mediaList['data'];
            } else {
                $templateArray['items'] = [];
            }

            if($template->getIsMapField()){
                $assignee = Helpers::__getRequestValue("assignee");
                if ($assignee != null && property_exists($assignee, 'id')) {
                    ModuleModel::$employee = Employee::findFirstById($assignee->id);
                }
                $relocation = Helpers::__getRequestValue("relocation");
                if ($relocation != null && property_exists($relocation, 'id')) {
                    ModuleModel::$relocation = Relocation::findFirstById($relocation->id);
                    ModuleModel::$assignment = ModuleModel::$relocation->getAssignment();
                } else {
                    $assignment = Helpers::__getRequestValue("assignment");
                    if ($assignment != null && property_exists($assignment, 'id')) {
                        ModuleModel::$assignment = Assignment::findFirstById($assignment->id);
                    }
                }
                $relocation_service_company = RelocationServiceCompany::findFirstById(Helpers::__getRequestValue("relocation_service_company_id"));
                if($relocation_service_company){
                    ModuleModel::$relocationServiceCompany = $relocation_service_company;
                    ModuleModel::$serviceCompany = $relocation_service_company->getServiceCompany();
                } else {
                    ModuleModel::$serviceCompany = ServiceCompany::findFirstById(Helpers::__getRequestValue("service_company_id"));
                }

                $phpDateFormat = ModuleModel::$company->getPhpDateFormat();
                $language = $template->getSupportedLanguage() ? $template->getSupportedLanguage()->getIso() : ModuleModel::$language;

                $access = $this->canAccessResource('communication-template', 'autofill_email');


                $returnFill = AutofillEmailTemplateHelper::fillContent($template->getMessage(), $phpDateFormat, $language, $access['success']);

                if($returnFill['success']){
                    $templateArray['message'] = $returnFill['data'];
                    $templateArray['map_fields'] = $returnFill['map_fields'];
                    $templateArray['empty_fields_array'] = $returnFill['empty_fields_array'];
                }else{
                    $return = $returnFill;
                    goto end_of_funciton;
                }
            }



            $return = [
                'success' => true,
                'message' => 'DATA_FOUND_SUCCESS_TEXT',
                'data' => $templateArray
            ];
        } else {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
        }

        end_of_funciton:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjax('DELETE');
        $this->checkAclDelete(AclHelper::CONTROLLER_COMMUNICATION_TEMPLATE);
        $data = $this->request->getJsonRawBody();
        $id = is_numeric($id) && $id > 0 ? $id : (isset($data->id) ? $data->id : null);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (is_numeric($id) && $id > 0) {
            $template = CommentsTemplate::findFirstById($id);
            if ($template && $template instanceof CommentsTemplate && $template->belongsToGms() == true) {
                $return = $template->__quickRemove();
                if ($return['success'] == true) {
                    $return['message'] = 'COMMUNICATION_DELETE_SUCCESS_TEXT';
                }
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function cloneAction(){
        $this->view->disable();
        $this->checkAjax(['POST']);
        $this->checkAclCreate(AclHelper::CONTROLLER_COMMUNICATION_TEMPLATE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        $oldCommentsTemplate = CommentsTemplate::findFirstByUuid($uuid);

        if(!$oldCommentsTemplate || ($oldCommentsTemplate && !$oldCommentsTemplate->belongsToGms())){
            goto end_of_function;
        }

        $this->db->begin();

        $data = $oldCommentsTemplate->toArray();
        unset($data['id']);
        unset($data['uuid']);
        unset($data['created_at']);
        unset($data['updated_at']);
        $data['subject'] = 'CLONE ' . $data['subject'];
        $newUuid = Helpers::__uuid();
        $commentsTemplate = new CommentsTemplate();
        $commentsTemplate->setData($data);
        $commentsTemplate->setUuid($newUuid);
        $commentsTemplate->setCreatedAt(date('Y-m-d H:i:s'));
        $commentsTemplate->setUpdatedAt(time());

        $returnCreate = $commentsTemplate->__quickCreate();

        if(!$returnCreate['success']){
            $return = $returnCreate;
            $this->db->rollback();
            goto end_of_function;
        }

        //Atttachments
        $mediaAttachments = $oldCommentsTemplate->getMediaAttachments();
        if($mediaAttachments){
            foreach ($mediaAttachments as $mediaAttachment){
                $item = $mediaAttachment->toArray();
                unset($item['id']);
                unset($item['uuid']);
                unset($item['object_uuid']);
                unset($item['created_at']);
                unset($item['updated_at']);
                $newAttachment = new MediaAttachment();
                $newAttachment->setData($item);
                $newAttachment->setUuid(Helpers::__uuid());
                $newAttachment->setObjectUuid($newUuid);
                $newAttachment->setCreatedAt(date('Y-m-d H:i:s'));
                $newAttachment->setUpdatedAt(date('Y-m-d H:i:s'));
                $returnCreateAttachment = $newAttachment->__quickCreate();


                if(!$returnCreateAttachment['success']){
                    $return = $returnCreateAttachment;
                    $this->db->rollback();
                    goto end_of_function;
                }
            }
        }
        $this->db->commit();

        $return = [
            'success' => true,
            'data' => $commentsTemplate,
            'message' => 'EMAIL_TEMPLATE_CLONE_SUCCESS_TEXT'
        ];


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
