<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use \Reloday\Gms\Models\Company;
use \Reloday\Gms\Models\CommentsTemplate;
use Reloday\Gms\Models\MediaAttachment;
use \Reloday\Gms\Models\ModuleModel;
use \Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CommentstemplateController extends BaseController
{
    /**
     * @Route("/allowancetitle", paths={module="gms"}, methods={"GET"}, name="gms-allowancetitle-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $templates = CommentsTemplate::__loadByCompany();
        $results = ['success' => true, 'data' => $templates];
        $this->response->setJsonContent($results);
        $this->response->send();
    }

    /**
     * init controller
     * @return [type] [description]
     */
    public function initAction()
    {

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
        $this->checkAcl('index', $this->router->getControllerName());
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
        $this->checkAcl('edit', $this->router->getControllerName());

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


            $commentsTemplateManager->setData([
                'subject' => isset($data->subject) ? $data->subject : null,
                'message' => isset($data->message) ? $data->message : null,
                'company_id' => ModuleModel::$company->getId(),
            ]);

            $resultSave = $commentsTemplateManager->__quickUpdate();

            if ($resultSave['success'] == true) {
                $result = ["success" => true, "msg" => "DATA_SAVE_SUCCESS_TEXT", "data" => $commentsTemplateManager];
            }else{
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
        $this->checkAcl('create', $this->router->getControllerName());

        $data = $this->request->getJsonRawBody();
        $items = Helpers::__getRequestValue('items');

        $this->db->begin();
        $commentsTemplateManager = new CommentsTemplate();
        $commentsTemplateManager->setData([
            'subject' => isset($data->subject) ? $data->subject : null,
            'message' => isset($data->message) ? $data->message : null,
            'company_id' => ModuleModel::$company->getId(),
        ]);
        $resultSave = $commentsTemplateManager->__quickCreate();

        if ($resultSave['success'] == true) {
            $result = ["success" => true, "msg" => "DATA_SAVE_SUCCESS_TEXT", "data" => $commentsTemplateManager];
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
            $result = ["success" => false, "msg" => "DATA_SAVE_FAIL_TEXT", "detail" => $resultSave['detail']];
            goto end_of_function;
        }

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
        $this->checkAcl('index', $this->router->getControllerName());

        if (is_numeric($id) && $id > 0) {
            $template = CommentsTemplate::findFirstById($id);
            if ($template && $template instanceof CommentsTemplate && $template->belongsToGms() == true) {
                $return = [
                    'success' => true,
                    'message' => 'DATA_FOUND_SUCCESS_TEXT',
                    'data' => $template
                ];
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
     * @param $id
     */
    public function deleteAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        $action = "delete";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $data = $this->request->getJsonRawBody();
        $id = is_numeric($id) && $id > 0 ? $id : (isset($data->id) ? $data->id : null);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (is_numeric($id) && $id > 0) {
            $template = CommentsTemplate::findFirstById($id);
            if ($template && $template instanceof CommentsTemplate && $template->belongsToGms() == true) {
                $return = $template->remove();
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


}
