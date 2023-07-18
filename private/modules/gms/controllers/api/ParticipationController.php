<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ParticipationController extends BaseController
{
    /**
     * @Route("/participation", paths={module="gms"}, methods={"GET"}, name="gms-participation-index")
     */
    public function indexAction()
    {

    }


    public function allAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($object_uuid != '') {
            return $this->getItems($object_uuid);
        }
    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public function get_viewersAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($object_uuid != '') {
            return $this->getItems($object_uuid, DataUserMember::MEMBER_TYPE_VIEWER);
        }
    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public function get_ownersAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($object_uuid != '') {
            return $this->getItems($object_uuid, DataUserMember::MEMBER_TYPE_OWNER);
        }
    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public function get_reporters($object_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($object_uuid != '') {
            return $this->getItems($object_uuid, DataUserMember::MEMBER_TYPE_REPORTER);
        }
    }

    /**
     * get items of data_user_member of an object
     * @param $object_uuid
     */
    public function getItems($object_uuid, $member_type_id)
    {
        $this->view->disable();
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($object_uuid != '' && Helpers::checkUuid($object_uuid) == true) {
            if ($member_type_id != '' && $member_type_id > 0) {
                $items = DataUserMember::find([
                    'conditions' => 'object_uuid = :object_uuid: AND member_type_id = :member_type_id:',
                    'bind' => [
                        'object_uuid' => $object_uuid,
                        'member_type_id' => $member_type_id
                    ]
                ]);
            } else {
                $items = DataUserMember::findByObject_uuid($object_uuid);
            }
            $return = [
                'success' => true,
                'data' => array_values($items->toArray())
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function save_viewersAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $data = $this->request->getJsonRawBody();
        return $this->saveMember($data, DataUserMember::MEMBER_TYPE_VIEWER);
    }

    /**
     * @return mixed
     */
    public function save_ownerAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $data = $this->request->getJsonRawBody();
        return $this->saveMember($data, DataUserMember::MEMBER_TYPE_OWNER);
    }

    /**
     * @return mixed
     */
    public function save_reporterAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $data = $this->request->getJsonRawBody();
        $member_type = DataUserMember::MEMBER_TYPE_REPORTER;
        return $this->saveMember($data, $member_type);
    }

    /**
     *
     */
    public function saveMember($data, $member_type)
    {

        $return = [
            'success' => false,
            'data' => [],
            'message' => 'DATA_SAVE_FAILED_TEXT',
            'raw' => $data
        ];

        if (isset($data->object_uuid) &&
            $data->object_uuid != '' &&
            Helpers::checkUuid($data->object_uuid) == true &&
            isset($data->task_uuid) &&
            $data->task_uuid != '' &&
            Helpers::checkUuid($data->task_uuid) == true &&

            $data->task_uuid == $data->object_uuid
        ) {
            $data->object_name = "task";
        }

        if (isset($data->object_uuid) &&
            $data->object_uuid != '' &&
            Helpers::checkUuid($data->object_uuid) == true &&

            isset($data->assignment_uuid) &&
            $data->assignment_uuid != '' &&
            Helpers::checkUuid($data->assignment_uuid) == true &&

            $data->assignment_uuid == $data->object_uuid
        ) {
            $data->object_name = "assignment";
        }

        if (isset($data->object_uuid) &&
            $data->object_uuid != '' &&
            Helpers::checkUuid($data->object_uuid) == true &&

            isset($data->relocation_uuid) &&
            $data->relocation_uuid != '' &&
            Helpers::checkUuid($data->relocation_uuid) == true &&

            $data->relocation_uuid == $data->object_uuid
        ) {
            $data->object_name = "relocation";
        }


        if (isset($data->object_uuid) &&
            $data->object_uuid != '' &&
            Helpers::checkUuid($data->object_uuid) == true &&
            isset($data->member_uuid) &&
            $data->member_uuid != '' &&
            Helpers::checkUuid($data->member_uuid) == true &&
            isset($data->object_name) &&
            $data->object_name != ''


        ) {

            $user = UserProfile::findFirstByUuid($data->member_uuid);
            //User of GMS or USER of HR
            if ($user->getCompanyId() == ModuleModel::$company->getId()) {
                $selected = isset($data->selected) ? $data->selected : null;
                if ($selected === true) {
                    $data_user_member = DataUserMember::findFirst([
                        "conditions" => "object_uuid = :object_uuid: AND user_profile_id = :user_profile_id: AND member_type_id = :member_type_id:",
                        'bind' => [
                            'object_uuid' => $data->object_uuid,
                            'user_profile_id' => $user->getId(),
                            'member_type_id' => $member_type
                        ]
                    ]);

                    if (!$data_user_member) {
                        $data_user_member_manager = new DataUserMember();
                        $model = $data_user_member_manager->__save([
                            'object_uuid' => $data->object_uuid,
                            'user_profile_id' => $user->getId(),
                            'user_profile_uuid' => $user->getUuid(),
                            'user_profile' => $user->getId(),
                            'user_login_id' => $user->getUserLoginId(),
                            'object_name' => $data->object_name,
                            'member_type_id' => $member_type
                        ]);
                        if ($model instanceof DataUserMember) {
                            $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_ADDED_SUCCESS_TEXT'];
                        } else {
                            $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_ADDED_FAIL_TEXT'];
                        }
                    }
                } elseif ($selected === false) {
                    $data_user_member = DataUserMember::findFirst([
                        "conditions" => "object_uuid = :object_uuid: AND user_profile_id = :user_profile_id: AND member_type_id = :member_type_id:",
                        'bind' => [
                            'object_uuid' => $data->object_uuid,
                            'user_profile_id' => $user->getId(),
                            'member_type_id' => $member_type
                        ]
                    ]);
                    $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_REMOVE_SUCCESS_TEXT'];
                    if ($data_user_member) {
                        if (!$data_user_member->delete()) {
                            $return = ['success' => false, 'data' => [], 'message' => 'VIEWER_REMOVE_FAIL_TEXT'];
                        }
                    }
                } else {
                    //
                }
            }

        }
        $this->view->disable();
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
