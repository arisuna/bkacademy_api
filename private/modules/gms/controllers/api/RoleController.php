<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\PushHelper;
use Reloday\Gms\Models\Acl;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserGroupAcl;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserGroupAclCompany;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\UserProfile;
use Reloday\Application\Models\SubscriptionExt;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class RoleController extends BaseController
{


    /**
     * @Route("/role", paths={module="gms"}, methods={"GET"}, name="gms-role-index")
     */
    public function indexAction()
    {


    }

    /**
     * @return mixed
     */
    public function getRoleByNameAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $name = Helpers::__getRequestValue('name');
        $role = UserGroup::findFirstByName(strtolower($name));

        if ($role) {
            $roleArray = $role->toArray();
            $roleArray['is_admin'] = $role->isGmsAdmin();

            return $this->response->setJsonContent([
                'success' => true,
                'data' => $roleArray
            ]);
            $this->response->send();
        } else {
            $this->response->setJsonContent([
                'success' => false,
                'msg' => 'DATA_NOT_FOUND_TEXT'
            ]);
            return $this->response->send();
        }
    }

    /**
     *
     */
    public function getRolesListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $roles = UserGroup::getAllGms();
        
        $this->response->setJsonContent([
            'success' => true,
            'data' => count($roles) ? $roles->toArray() : [],
        ]);
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
            'conditions' => 'is_gms=1',
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
     * get controller Action Item List
     */
    public function getControllerActionItemListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $user_login = ModuleModel::$user_login;
        $user_profile = ModuleModel::$user_profile;
        $subscription = SubscriptionExt::findFirstByCompanyId($user_profile->getCompanyId());
        $list_controller_action = Acl::getTreeGms($subscription, $user_profile);
        $result = ['success' => true, 'data' => array_values($list_controller_action)];
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function initAction()
    {
        $this->checkAjaxGet();
        // Load roles
        $roles = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_GMS . '"'
        ]);

        // Load acls
        $acls = Acl::find([
            'conditions' => 'is_gms=1',
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

        $this->view->disable();
        $this->response->setJsonContent([
            'success' => true,
            'roles' => count($roles) ? $roles->toArray() : [],
            'acls' => $acls_arr
        ]);
        $this->response->send();
    }

    /**
     * [detailAction description]
     * @param integer $user_group_id [description]
     * @return [type]                 [description]
     */
    public function detailAction($user_group_id = 0)
    {
        $this->checkAjaxGet();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function saveAction()
    {
        $this->checkAjaxGet();
    }

    /**
     * show group
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function showgroupAction($user_group_id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $current_gms_company = ModuleModel::$company;
        if (!$current_gms_company) {
            $return = [
                'success' => false,
                'message' => 'COMPANY_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        $userGroup = UserGroup::findFirstById($user_group_id);
        if (!$userGroup) {
            $return = [
                'success' => false,
                'message' => 'USER_GROUP_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }
        $acl_list = Acl::find([
            'conditions' => 'is_gms = :is_gms:',
            'bind' => [
                'is_gms' => Acl::IS_GMS_YES
            ],
            'order' => 'pos, lvl ASC'
        ]);

        $privileges = UserGroupAclCompany::find([
            'conditions' => 'user_group_id = :user_group_id: AND company_id = :company_id:',
            'bind' => [
                'user_group_id' => $user_group_id,
                'company_id' => $current_gms_company->getId()
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
            $list_controller_action[$item['id']]['selected'] = $userGroup->isGmsAdmin() == true ? true : null;
            $list_controller_action[$item['id']]['accessible'] = isset($result[$item['id']]);
        }

        $return = [
            'success' => true,
            'privileges' => $privileges,
            'roles' => $list_controller_action,
            'disabled' => $user_group_id == UserGroup::GMS_ADMIN ? true : false
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * submit new Action
     * @return [type] [description]
     */
    public function submitAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclUpdate();

        //load current gms country
        $current_gms_company = ModuleModel::$company;
        if (!$current_gms_company) {
            $return = [
                'success' => false,
                'message' => 'COMPANY_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        $user_group_id = $this->request->getPost('id');
        // Find group
        $group = UserGroup::findFirst($user_group_id ? $user_group_id : 0);
        if (!$group instanceof UserGroup) {
            $return = [
                'success' => false,
                'message' => 'GROUP_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }
        // -------------------

        $privileges = UserGroupAclCompany::find([
            'conditions' => 'user_group_id = :user_group_id: AND company_id = :company_id:',
            'bind' => [
                'user_group_id' => $user_group_id,
                'company_id' => $current_gms_company->getId()
            ]
        ]);

        $list_acls = $this->request->getPost('acl');
        $this->db->begin();

        // Delete list acl has been un-check
        if (count($privileges)) {

            foreach ($privileges as $privilege) {
                if (is_array($list_acls) && count($list_acls) > 0) {
                    if (is_array($list_acls) && count($list_acls) > 0 && in_array($privilege->getAclId(), $list_acls)) {
                        $key = array_search($privilege->getAclId(), $list_acls);
                        if (!empty($key))
                            unset($list_acls[$key]);
                    } else {
                        if (!$privilege->delete()) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'UNSET_PRIVILEGE_ERROR_TEXT'
                            ];
                            goto end_of_function;
                        }
                    }
                } else {
                    if (!$privilege->delete()) {
                        $this->db->rollback();
                        $return = [
                            'success' => false,
                            'message' => 'UNSET_PRIVILEGE_ERROR_TEXT'
                        ];
                        goto end_of_function;
                    }
                }
            }
        }

        if (count($list_acls)) {
            // Add new privilege for group
            foreach ($list_acls as $item) {
                $model = new UserGroupAclCompany();
                $model->setAclId($item); //set acl id
                $model->setUserGroupId($user_group_id); //set user group
                $model->setCompanyId($current_gms_company->getId()); //set company
                if (!$model->save()) {
                    $this->db->rollback();
                    $msg = [];
                    foreach ($model->getMessages() as $message) {
                        $msg[$message->getField()] = $message->getMessage();
                    }
                    $return = [
                        'success' => false,
                        'message' => 'SAVE_PRIVILEGE_ERROR_TEXT',
                        'detail' => $msg
                    ];
                }
            }

        }

        $this->db->commit();
        $this->setCacheACL();

        $return = [
            'success' => true,
            'message' => 'SAVE_ROLE_SUCCESS_TEXT'
        ];

        end_of_function :
        $this->response->setJsonContent($return);
        $this->response->send();

    }

    /**
     * reset all ACL module of COMPANY from ACL GLOBAL
     * ONLY GMS ADMIN CAN DO THAT
     * @return [type] [description]
     */
    public function resetAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclReset();

        $current_gms_company = ModuleModel::$company;
        if (!$current_gms_company) {
            $return = [
                'success' => false,
                'message' => 'COMPANY_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }
        $this->db->begin();
        //delete all acl configuration of the company
        $acls_group_company_items = UserGroupAclCompany::findByCompany_id($current_gms_company->getId());
        if (count($acls_group_company_items)) {
            foreach ($acls_group_company_items as $acls_group_company_item) {

                if (!$acls_group_company_item->delete()) {
                    $this->db->rollback();

                    $msg = [];
                    foreach ($acls_group_company_item->getMessages() as $message) {
                        $msg[$message->getField()] = $message->getMessage();
                    }
                    $return = [
                        'success' => false,
                        'message' => 'RESET_ACL_WITH_FAILED_WHEN_TRUNCATE_CURRENT_ACL_CONFIG_TEXT',
                        'detail' => $msg
                    ];
                    goto end_of_function;
                }
            }
        }

        //copy acl configuration from the global template
        $acls_group_global_items = UserGroupAcl::find();
        foreach ($acls_group_global_items as $acls_group_global_item) {
            if ($acls_group_global_item->getAcl() && $acls_group_global_item->getAcl()->getIsGms() == true) {
                /// IF IS GMS
                $acls_group_company_item = new UserGroupAclCompany();
                $acls_group_company_item->setCompanyId($current_gms_company->getId()); // set current gms company
                $acls_group_company_item->setAclId($acls_group_global_item->getAclId()); // set acl id from global acl
                $acls_group_company_item->setUserGroupId($acls_group_global_item->getUserGroupId()); // set user group id from global acl

                if (!$acls_group_company_item->save()) {
                    $this->db->rollback();

                    $msg = [];
                    foreach ($acls_group_company_item->getMessages() as $message) {
                        $msg[$message->getField()] = $message->getMessage();
                    }

                    $return = [
                        'success' => false,
                        'message' => 'RESET_ACL_WITH_FAILED_WHEN_COPY_FROM_GLOBAL_ACL_TEXT',
                        'detail' => $msg
                    ];
                    goto end_of_function;
                }
            }
        }


        $this->db->commit();

        $return = [
            'success' => true,
            'message' => 'ALL_ACL_OF_COMPANY_ARE_RESETED_TEXT',
        ];
        goto end_of_function;


        end_of_function :

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function setCacheACL()
    {
        $this->checkAjaxGet();
        $groups = UserGroup::find([
            'conditions' => 'type = :type:',
            'bind' => [
                'type' => 'GMS',
            ]
        ]);

        $cacheManager = $this->di->getShared('cache');
        if ($groups->count()) {
            foreach ($groups as $group) {

                $appId = ModuleModel::$app->getId();
                $cacheName = "ACL_CACHE_" . $appId . "_" . $group->getId();
                // 3. Load acl by group
                if ($group->getId() != UserGroup::GMS_ADMIN) {
                    // Get full permission with AMIN of group
                    $company = ModuleModel::$company;

                    $groups_acl = UserGroupAclCompany::getAllPriviligiesGroupCompany($group->getId(), $company->getId());
                    if (count($groups_acl) == 0) {
                        $groups_acl = UserGroupAcl::getAllPrivilegiesGroup($group->getId());
                    }

                    $acl_ids = [];
                    if (count($groups_acl)) {
                        foreach ($groups_acl as $item) {
                            $acl_ids[] = $item->getAclId();
                        }
                    }
                    // Get controller and action in list ACLs, order by level
                    $acl_list = Acl::find([
                        'conditions' => 'id IN ({acl_ids:array})',
                        'bind' => [
                            'acl_ids' => $acl_ids
                        ],
                        'order' => 'pos, lvl ASC'
                    ]);
                } else {
                    $acl_list = Acl::find([
                        'conditions' => 'is_gms = :is_gms:',
                        'bind' => [
                            'is_gms' => 1
                        ],
                        'order' => 'pos, lvl ASC'
                    ]);
                }

                if (count($acl_list)) {
                    $acl_list = $acl_list->toArray();
                    foreach ($acl_list as $item) {
                        if (!isset($permissions[$item['controller']])) {
                            $permissions[$item['controller']] = [];
                        }
                        $permissions[$item['controller']][] = $item['action'];
                        // Create json data, check if enable in the menu
                        if (!$item['status']) continue;
                    }
                }
                $cacheManager->save($cacheName, $permissions, getenv('CACHE_TIME'));
            }
        }
    }

    /**
     *
     */
    public function addAclItemAction()
    {

        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $user_group_id = Helpers::__getRequestValue('role_id');
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


            $aclCompany = UserGroupAclCompany::getItem($user_group_id, $acl->id, ModuleModel::$company->getId());
            if (!$aclCompany) {
                $aclCompany = new UserGroupAclCompany();
            }
            $aclCompany->setAclId($acl->id); //set acl id
            $aclCompany->setUserGroupId($user_group_id); //set user group
            $aclCompany->setCompanyId(ModuleModel::$company->getId()); //set company

            $cacheName = CacheHelper::getAclCacheByCompanyGroupName($aclCompany->getCompanyId(), $aclCompany->getUserGroupId());

            $resultSave = $aclCompany->__quickSave();

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
        $users = UserProfile::getWorkersByGroup($user_group_id);
        foreach ($users as $user) {
            if ($user->getUuid() != ModuleModel::$user_profile->getUuid()) {
                $channels[] = $user->getUuid();
            }
        };

        if (count($channels) > 0) {
            $pushHelpers = new PushHelper();
            return $pushHelpers->sendMultipleChannels($channels, PushHelper::EVENT_REFRESH_CACHE_ACL, ['message' => 'YOUR_PERMISSIONS_CHANGE_TEXT']);
        }
    }

    /**
     *
     */
    public function removeAclItemAction()
    {

        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate();

        $user_group_id = Helpers::__getRequestValue('role_id');
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

            $aclCompany = UserGroupAclCompany::getItem($user_group_id, $acl->id, ModuleModel::$company->getId());
            if ($aclCompany instanceof UserGroupAclCompany) {

                //$cacheName = CacheHelper::getAclCacheByCompanyGroupName($aclCompany->getCompanyId(), $aclCompany->getUserGroupId());
                $cacheName = CacheHelper::__getAclCacheByCompanyGroupAclName($aclCompany->getCompanyId(), $aclCompany->getUserGroupId(), $aclCompany->getAclId());
                $resultSave = $aclCompany->__quickRemove();

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
}
