<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserInContract;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;

use Reloday\Gms\Models\SvpMembers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class SvpWorkerController extends BaseController
{
    /**
     * Load list worker
     * @Route("/serviceprovider", paths={module="gms"}, methods={"GET"}, name="gms-serviceprovider-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax(['POST', 'GET']);
        $this->checkAclIndex();

        $data = Helpers::__getRequestValue('data');


        $service_provider_ids = Helpers::__getRequestValue('service_provider_ids');

        $conditions = ($data ? " AND (SvpMembers.firstname LIKE '%$data%' OR SvpMembers.lastname LIKE '%$data%')" : '');
        $svpManager = new SvpMembers();

        $params = [];
        if($service_provider_ids){
            $params['service_provider_ids'] = $service_provider_ids;
        }
        $result = $svpManager->loadList($conditions, $params);

        if ($result['success']) {
            $this->response->setJsonContent([
                'success' => true,
                'data' => $result['data']
            ]);
            return $this->response->send();
        } else {
            $this->response->setJsonContent($result);
            return $this->response->send();
        }
    }

    /**
     * Load base information for form
     */
    public function initAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $roles_arr = [];
        // Load list roles
        $roles = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_SVP . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);
        if (count($roles))
            foreach ($roles as $role) {
                $roles_arr[] = [
                    'text' => $role->getName(),
                    'value' => $role->getId()
                ];
            }

        echo json_encode([
            'success' => true,
            'roles' => $roles_arr
        ]);
    }

    /**
     * [detailAction description]
     * @param  string $svp_worker_id [description]
     * @return [type]                [description]
     */
    public function detailAction($svp_worker_id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        /** check policy id */
        if ($svp_worker_id > 0) {
            $svp_worker = SvpMembers::findFirstById($svp_worker_id);

            $svp_worker_array = $svp_worker->toArray();
            $svp_worker_array['svp_name'] = $svp_worker->getProviderName();
            $svp_worker_array['svp_status'] = $svp_worker->getProviderStatus();
            $svp_worker_array['language'] = $svp_worker->parseLanguage();

            if ($svp_worker && $svp_worker->belongsToGms()) {
                $this->response->setJsonContent([
                    'success' => true,
                    'message' => 'SVP_WORKER_SUCCESS_TEXT',
                    'fields' => $svp_worker->getFieldsDataStructure(),
                    'data' => $svp_worker_array
                ]);
            } else {
                $this->response->setJsonContent(['success' => false, 'message' => 'SVP_WORKER_NOT_FOUND_TEXT']);
            }
            return $this->response->send();
        } else {
            $this->response->setJsonContent(['success' => false, 'message' => 'SVP_WORKER_NOT_FOUND_TEXT']);
            return $this->response->send();
        }
    }

    /**
     * Create or update method
     * @return string
     */
    public function updateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid == '' || !Helpers::__isValidUuid($uuid)) {
            goto end_of_function;
        }
        $svpMemberManager = SvpMembers::findFirstByUuid($uuid);
        if ($svpMemberManager && $svpMemberManager->belongsToGms()) {
            $data = Helpers::__getRequestValuesArray();
            if ($data['uuid'] == $svpMemberManager->getUuid()) {
                $svpMemberManager->setData($data);
                $return = $svpMemberManager->__quickUpdate();
                if ($return['success'] == true) {
                    $return['message'] = 'SVP_WORKER_SAVE_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'SVP_WORKER_SAVE_FAIL_TEXT';
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Create or update method
     * @return string
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclCreate();
        $data = Helpers::__getRequestValuesArray();
        $svpMemberManager = new SvpMembers();
        $svpMemberManager->setStatus(SvpMembers::STATUS_ACTIVE);
        $svpMemberManager->setData($data);
        $svpMemeberResult = $svpMemberManager->__quickCreate();
        if ($svpMemeberResult['success'] == true) {
            $return = [
                'success' => true,
                'message' => 'SVP_WORKER_SAVE_SUCCESS_TEXT',
                'data' => $svpMemberManager
            ];
        } else {
            $return = $svpMemeberResult;
            $return['message'] == 'SVP_WORKER_SAVE_FAIL_TEXT';
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** delete policy */
    public function deleteAction($svp_worker_uuid)
    {
        $this->view->disable();
        if (!$this->request->isDelete()) {
            exit('Restrict access');
        }
        $access = $this->canAccessResource($this->router->getControllerName(), 'delete');
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($svp_worker_uuid != '') {
            $svp_member = SvpMembers::findFirstByUuid($svp_worker_uuid);
            if ($svp_member && $svp_member instanceof SvpMembers && $svp_member->belongsToGms() == true) {
                $return = $svp_member->remove();
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
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
     *
     */
    public function searchAction(){
        $this->view->disable();
        $this->checkAjaxPut();

        $options["query"] = Helpers::__getRequestValue("query");
        $options["svpCompanyId"] = Helpers::__getRequestValue("svpCompanyId");
        $options["svpMemberId"] = Helpers::__getRequestValue("svpMemberId");
        $result = SvpMembers::__loadListSvpMembers($options);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $result
        ]);
        return $this->response->send();
    }
}
