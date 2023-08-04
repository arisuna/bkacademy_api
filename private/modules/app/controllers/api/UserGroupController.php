<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
use SMXD\App\Models\UserLogin;
use SMXD\App\Models\User;
use SMXD\App\Models\UserGroup;
use SMXD\App\Models\UserGroupAcl;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\PushHelper;
use SMXD\Application\Lib\CacheHelper;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class UserGroupController extends BaseController
{

    /**
     * Get detail of object
     * @param int $id
     */
    public function detailAction($id = 0)
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        $data = UserGroup::findFirst((int)$id);
        $data = $data instanceof UserGroup ? $data->toArray() : [];
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        return $this->response->send();
    }

    /**
     * Get detail of object
     * @param int $id
     */
    public function showAclAction($id = 0)
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        $user_group = UserGroup::findFirst((int)$id);
        
        $acl_list = Acl::find([
            'conditions' => 'is_admin <> :yes:',
            'bind' => [
                'yes' => Helpers::YES
            ],
            'order' => 'pos, lvl ASC'
        ]);

        $privileges = UserGroupAcl::find([
            'conditions' => 'user_group_id = :user_group_id:',
            'bind' => [
                'user_group_id' => $id
            ]
        ]);
        if (count($privileges)) {
            foreach ($privileges as $privilege) {
                $result[$privilege->getAclId()] = $privilege;
            }
        }


        $list_controller_action = [];
        foreach ($acl_list->toArray() as $item) {
            $list_controller_action[$item['id']]['module'] = $item['name'];
            $list_controller_action[$item['id']]['name'] = $item['label'];
            $list_controller_action[$item['id']]['controller'] = $item['controller'];
            $list_controller_action[$item['id']]['action'] = $item['action'];
            $list_controller_action[$item['id']]['visible'] = $item['status'];
            $list_controller_action[$item['id']]['selected'] = null;
            $list_controller_action[$item['id']]['accessible'] = isset($result[$item['id']]);
        }

        $return = [
            'success' => true,
            'privileges' => $privileges,
            'roles' => $list_controller_action
        ];
        $this->response->setJsonContent($return);

        end:
        return $this->response->send();
    }

    /**
     * get controller Action Item List
     */
    public function getControllerActionItemListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $list_controller_action = Acl::getTreeAcl();
        $result = ['success' => true, 'data' => array_values($list_controller_action)];
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function getAclListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $acls = Acl::find([
            'conditions' => 'is_admin<>1',
            'order' => 'controller'
        ]);
        $acls_arr = [];
        if (count($acls)) {
            foreach ($acls as $acl) {
                if ($acl->getController() & $acl->getAction()) {
                    if (!isset($acls_arr[$acl->getName()])) {
                        $acls_arr[$acl->getName()] = [];
                    }
                    $acls_arr[$acl->getName()][$acl->getGroup()] = $acl->getId();
                }
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => count($acls_arr) ? $acls_arr : [],
        ]);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $name = Helpers::__getRequestValue('name');
        $checkIfExist = UserGroup::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $name
            ]
            ]);
        if($checkIfExist){
            $result = [
                'success' => false,
                'message' => 'NAMEL_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }

        $model = new UserGroup();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);

        $this->db->begin();
        $resultCreate = $model->__quickCreate();

        if ($resultCreate['success'] == true) {
            $this->db->commit();
            $result = [
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT'
            ];
        } else {
            $this->db->rollback();
            $result = ([
                'success' => false,
                'detail' => $resultCreate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ]);
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateAction($id)
    {

    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (Helpers::__isValidId($id)) {

            $model = UserGroup::findFirstById($id);
            if ($model) {
                $data = Helpers::__getRequestValuesArray();

                $model->setData($data);
                $name = Helpers::__getRequestValue('name');
                $checkIfExist = UserGroup::findFirst([
                    'conditions' => 'name = :name: and id <> :id:',
                    'bind' => [
                        'name' => $name,
                        'id' => $id
                    ]
                ]);
                if($checkIfExist){
                    $result = [
                        'success' => false,
                        'message' => 'NAMEL_MUST_UNIQUE_TEXT'
                    ];
                    goto end;
                }

                $this->db->begin();
                $resultCreate = $model->__quickUpdate();

                if ($resultCreate['success'] == true) {
                    $this->db->commit();
                    $result = $resultCreate;
                } else {
                    $this->db->rollback();
                    $result = ([
                        'success' => false,
                        'message' => 'DATA_SAVE_FAIL_TEXT',
                        'detail' => $resultCreate
                    ]);
                }
            }
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Delete data
     */
    public function deleteAction($id)
    {

    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();
        $user_group = UserGroup::findFirstById($id);
        if($id == UserGroup::GROUP_ADMIN || $id == UserGroup::GROUP_CRM_ADMIN){
            $result = [
                'success' => false,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
            ];
            goto end;
        }
        $user = User::findFirst([
            'conditions' => 'status <> :deleted: and user_group_id = :user_group_id:',
            'bind' => [
                'deleted' => User::STATUS_DELETED,
                'user_group_id' => $id
            ]
        ]);
        if($user){
            $result = [
                'success' => false,
                'message' => 'CAN_NOT_DELETE_IN_USED_ITEM_TEXT'
            ];
            goto end;
        } else {
            $result = $user_group->__quickRemove();
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/constant", paths={module="backend"}, methods={"GET"}, name="backend-constant-index")
     */
    public function searchAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $user_groups = UserGroup::find([
            'conditions' => 'id <> :admin_id:',
            'bind' => [
                'admin_id' => UserGroup::GROUP_ADMIN
            ],
            'order' => 'name'
        ]);
        $return = ['success' => true, 'data' => $user_groups];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function removeAclItemAction()
    {

        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $user_group_id = Helpers::__getRequestValue('user_group_id');
        $acl = Helpers::__getRequestValue('acl');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        // Find group
        if ($user_group_id > 0 && $acl && isset($acl->id)) {

            $userGroup = UserGroup::findFirstById($user_group_id);

            if (!$userGroup instanceof UserGroup) {
                $return = [
                    'success' => false,
                    'message' => 'USER_GROUP_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }

            $aclUserGroup = UserGroupAcl::getItem($user_group_id, $acl->id);
            if ($aclUserGroup instanceof UserGroupAcl) {
                $cacheName = CacheHelper::__getAclCacheByGroupAclName($aclUserGroup->getUserGroupId(), $aclUserGroup->getAclId());
                $resultSave = $aclUserGroup->__quickRemove();

                if ($resultSave['success']) {
                    $return = $resultSave;
                    $return['cacheName'] = $cacheName;
                    $return['message'] = 'REMOVE_ROLE_SUCCESS_TEXT';
                    $this->sendPushRefreshAcl($user_group_id);
                } else {
                    $return = $resultSave;
                    $return['message'] = 'REMOVE_ACL_ERROR_TEXT';
                    Helpers::__createException("REMOVE_ACL_ERROR_TEXT");
                }
            }
        }

        end_of_function :
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function addAclItemAction()
    {

        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $user_group_id = Helpers::__getRequestValue('user_group_id');
        $acl = Helpers::__getRequestValue('acl');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        // Find group
        if ($user_group_id > 0 && $acl && isset($acl->id)) {
            $userGroup = UserGroup::findFirstById($user_group_id);
            if (!$userGroup instanceof UserGroup) {
                $return = [
                    'success' => false,
                    'message' => 'GROUP_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }


            $aclUserGroup = UserGroupAcl::getItem($user_group_id, $acl->id);
            if (!$aclUserGroup) {
                $aclUserGroup = new UserGroupAcl();
            }
            $aclUserGroup->setAclId($acl->id); //set acl id
            $aclUserGroup->setUserGroupId($user_group_id); //set user group

            $cacheName = CacheHelper::getAclCacheByGroupName($aclUserGroup->getUserGroupId());

            $resultSave = $aclUserGroup->__quickSave();

            if ($resultSave['success']) {
                $return = $resultSave;
                $return['cacheName'] = $cacheName;
                $return['message'] = 'SAVE_ROLE_SUCCESS_TEXT';

                $this->sendPushRefreshAcl($user_group_id);
            } else {
                $return = $resultSave;
                $return['message'] = 'SAVE_ROLE_FAIL_TEXT';
            }

        }

        end_of_function :
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $user_group_id
     * @return array
     */

    public function sendPushRefreshAcl($user_group_id)
    {
        /***** refresh acl **/
        $channels = [];
        $users = User::getWorkersByGroup($user_group_id);
        foreach ($users as $user) {
            if ($user->getUuid() != ModuleModel::$user->getUuid()) {
                $channels[] = $user->getUuid();
            }
        };

        if (count($channels) > 0) {
            $pushHelpers = new PushHelper();
            return $pushHelpers->sendMultipleChannels($channels, PushHelper::EVENT_REFRESH_CACHE_ACL, ['message' => 'YOUR_PERMISSIONS_CHANGE_TEXT']);
        }
    }
}