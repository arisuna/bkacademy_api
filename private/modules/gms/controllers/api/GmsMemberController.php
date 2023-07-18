<?php

namespace Reloday\Gms\Controllers\API;

use ParagonIE\Sodium\Core\Curve25519\H;
use Phalcon\Mvc\Model;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\ObjectMapExt;
use Reloday\Application\Models\UserProfileExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Module;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class GmsMemberController extends BaseController
{
    /**
     * Permission of See Company Member should be GLOBAL
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $gms_company = ModuleModel::$company;
        $data = $this->request->get('data');
        $conditions = "status =" . UserProfile::STATUS_ACTIVE . " AND company_id=" . $gms_company->getId() . ($data ? " AND (firstname LIKE '%$data%' OR lastname LIKE '%$data%')" : '');
        $manager = new UserProfile();
        $result = $manager->loadList($conditions);
        if ($result['success']) {
            $this->response->setJsonContent([
                'success' => true,
                'data' => array_values($result['users'])
            ]);
        } else {
            $this->response->setJsonContent($result);
        }
        return $this->response->send();
    }


    /**
     * Permission of See Company Member should be GLOBAL
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function searchGmsMemberAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');

        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');

        /***** new filter ******/
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

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


        $result = UserProfile::__findGmsViewersWithFilter($params, $ordersConfig);

        if ($result['success']) {
            $this->response->setJsonContent($result);
        } else {
            $this->response->setJsonContent($result);
        }
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function get_rolesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $roles = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_GMS . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $roles
        ]);
        return $this->response->send();
    }


    /**
     * [initAction description]
     * @return [type] [description]
     */
    public function initAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        // Load list roles
        $roles = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_GMS . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);
        $roles_arr = [];
        if (count($roles)) {
            foreach ($roles as $role) {
                $roles_arr[] = [
                    'text' => $role->getName(),
                    'value' => $role->getId()
                ];
            }
        }


        echo json_encode([
            'success' => true,
            'roles' => $roles_arr
        ]);
    }

    /**
     * [detailAction description]
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function detailAction($id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex("company_member");

        $result = [
            'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $id = $id ? $id : $this->request->get('id');
        if ($id && Helpers::__checkId($id)) {
            $profile = UserProfile::findFirstById($id);
        } elseif ($id && Helpers::__isValidUuid($id)) {
            $profile = UserProfile::findFirstByUuid($id);
        }

        if (isset($profile) && $profile instanceof UserProfile && $profile->belongsToGms()) {
            $dataArray = $profile->toArray();
            $dataArray['role_name'] = $profile->getRoleName();
            $dataArray['hasUserLogin'] = $profile->getUserLogin() ? true : false;
            $dataArray['hasAwsCognito'] = $profile->hasLogin();
            if($dataArray['hasAwsCognito'] && $profile->getLoginStatus() != UserProfileExt::LOGIN_STATUS_HAS_ACCESS){
                $profile->setLoginStatus(UserProfileExt::LOGIN_STATUS_HAS_ACCESS);
                $resultUpdate = $profile->__quickUpdate();
            }
            $dataArray['spoken_languages'] = $profile->parseSpokenLanguages();
            $result = [
                'success' => true,
                'data' => $dataArray,
            ];
        } else {
            $result = [
                'success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * generate nickname
     * @param string $uuid
     * @return mixed
     */
    public function generate_nicknameAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $uuid = $uuid ? $uuid : $this->request->get('uuid');
        if ($uuid) {
            $profile = UserProfile::findFirstByUuid($uuid);
            if ($profile instanceof UserProfile && $profile->belongsToGms() == true) {
                $nickname = $profile->generateNickName();
                $result = [
                    'success' => true, 'nickname' => $nickname
                ];
            } else {
                $result = [
                    'success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_IN_VALID_TEX'
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Create or update method
     * @return string
     */
    public function editAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit("company_member");

        $result = [
            'success' => false,
            'message' => 'GMS_MEMBER_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user_profile_uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid'] : "";

        if ($user_profile_uuid != '') {
            $userProfile = UserProfile::findFirstByUuid($user_profile_uuid);
            if ($userProfile && $userProfile->belongsToGms() && $userProfile->isGms()) {

                if ($userProfile->isAdminOrManager() && ModuleModel::$user_profile->isAdminOrManager() == false) {

                    /** @var ONLY ADMIN OR MANAGER CAN EDIT ADMIN $result */
                    $result = [
                        'success' => false,
                        'message' => 'ONLY_ADMIN_MANAGER_CAN_DO_THAT_TEXT',
                        'data' => $userProfile,
                    ];

                } else {
                    unset($dataInput['user_login_id']); //important, should unset it before

                    $modelResult = $userProfile->saveUserProfile($dataInput);
                    if ($modelResult instanceof UserProfile) {
                        $data = $modelResult->toArray();
                        $data['role_name'] = $modelResult->getRoleName();
                        $data['spoken_languages'] = $modelResult->parseSpokenLanguages();
                        $result = [
                            'success' => true,
                            'message' => 'GMS_MEMBER_SAVE_SUCCESS_TEXT',
                            'data' => $data,
                        ];
                    } else {
                        $result = $modelResult;
                    }
                }
            }
        }

        end:
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
        $this->checkAclCreate("company_member");
        $return = [
            'success' => false,
            'message' => 'GMS_MEMBER_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        if (isset($dataInput['user_group_id']) && is_numeric($dataInput['user_group_id']) && $dataInput['user_group_id'] > 0) {
            $gmpRoles = UserGroup::__getGmpRoleIds();
            if (in_array($dataInput['user_group_id'], $gmpRoles)) {
                $dataInput['company_id'] = ModuleModel::$company->getId();
            } else {
                goto end_of_function;
            }
        } else {
            goto end_of_function;
        }

        /*** create nick name ***/
        $nickname = Helpers::__createUserProfileNickName($dataInput['firstname'], $dataInput['lastname'], $dataInput['company_id']);

        /**** create user profile ***/
        $this->db->begin();
        $userProfile = new UserProfile();
        $dataInput['nickname'] = $nickname;

        if ($dataInput['workemail'] && (UserProfileExt::__ifEmailAvailable($dataInput['workemail']) == false)) {
            $result = [
                'success' => false,
                'message' => 'WORK_EMAIL_MUST_UNIQUE_TEXT',
                'detail' => 'User profile could not be saved',
            ];
            goto end_of_function;
        }

        $modelResult = $userProfile->saveUserProfile($dataInput);
        if (!($modelResult instanceof UserProfile || $modelResult instanceof UserProfileExt)) {
            $result = [
                'success' => false,
                'message' => 'USER_PROFILE_SAVE_FAIL_TEXT',
                'detail' => $modelResult,
            ];
            if (isset($modelResult['message']) && $modelResult['message'] != null && $modelResult['message'] != '') {
                $result['message'] = $modelResult['message'];
            }
            $this->db->rollback();
            goto end_of_function;
        } else {
            $this->db->commit();
            $result = [
                'success' => true,
                'message' => 'USER_PROFILE_SAVE_SUCCESS_TEXT',
                'data' => $modelResult,
            ];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * [detailAction description]
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function deleteAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete("company_member");

        $result = [
            'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (!$uuid || !Helpers::__isValidUuid($uuid)) {
            goto end_of_function;
        }

        $password = ModuleModel::__getRequestedPassword('password');
        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user_login->getEmail(), $password);
        if ($checkPassword['success'] == false) {
            $result = $checkPassword;
            $result['message'] = 'PASSWORD_INCORRECT_TEXT';
            goto end_of_function;
        }

        $userProfile = UserProfile::findFirstByUuid($uuid);
        if (isset($userProfile) && $userProfile instanceof UserProfile && $userProfile->belongsToGms() && $userProfile->isGms()) {
            if ($userProfile->getUuid() == ModuleModel::$user_profile->getUuid()) {
                $result = [
                    'success' => false,
                    'message' => 'YOU_CAN_NOT_DELETE_YOUR_SELF_TEXT',
                    'data' => $userProfile,
                ];
                goto end_of_function;
            } elseif ($userProfile->isGmsAdminOrManager() && ModuleModel::$user_profile->isGmsAdmin() == false) {
                /** @var ONLY ADMIN OR MANAGER CAN DELETE ADMIN $result */
                $result = [
                    'success' => false,
                    'message' => 'ONLY_ADMIN_MANAGER_CAN_DO_THAT_TEXT',
                    'data' => $userProfile,
                ];
                goto end_of_function;
            } else {
                $userLogin = $userProfile->getUserLogin();
                if ($userLogin && $userLogin instanceof UserLogin) {
                    $resultUserLogin = $userLogin->clearUserLoginWhenUserDeactivated();
                    if ($resultUserLogin['success'] == false) {
                        $result = $resultUserLogin;
                        goto end_of_function;
                    }
                }
                $this->db->begin();

                $userProfile->setActive(ModelHelper::NO);
                $userProfile->setInactiveStatus();
                $userProfile->setWorkemail($userProfile->getId() . "-" . $userProfile->getWorkemail());//9655
                $resultUserProfile = $userProfile->__quickUpdate();
                if (!$resultUserProfile['success']) {
                    $this->db->rollback();
                    $result = $resultUserProfile;
                }
                $result = $userProfile->__quickRemove();
                if ($result['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'DSP_MEMBER_DELETE_SUCCESS_TEXT',
                    ];
                    $this->db->commit();
                } else {
                    $this->db->rollback();
                    $result['message'] = 'DSP_MEMBER_DELETE_FAIL_TEXT';
                }
            }
        }

        end_of_function :
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function resetUserPasswordAction()
    {
        $this->view->disable();
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $this->checkAjaxPost();
//        $this->checkAcl('changeUserPassword');

        $userProfileUuid = Helpers::__getRequestValue('uuid');
        $userLoginId = Helpers::__getRequestValue('user_login_id');
        if (Helpers::__checkUuid($userProfileUuid)) {
            $userProfile = UserProfile::findFirstByUuid($userProfileUuid);
            if ($userProfile && ($userProfile->belongsToGms() || $userProfile->manageByGms()) &&
                $userProfile->isActive() &&
                $userProfile->hasActiveUserLogin()) {

                if ($userProfile->isAdminOrManager() && ModuleModel::$user_profile->isAdminOrManager() == false) {

                    /** @var ONLY ADMIN OR MANAGER CAN EDIT ADMIN $result */
                    $result = [
                        'success' => false,
                        'message' => 'ONLY_ADMIN_MANAGER_CAN_DO_THAT_TEXT',
                        'data' => $userProfile,
                    ];
                    goto end_of_function;
                }

                $userLogin = $userProfile->getUserLogin();
                $resultSent = $userLogin->forceResetPassword();
                if ($resultSent['success'] == false) {
                    $result = ['success' => false, 'message' => 'PASSWORD_UPDATED_AND_SENT_TO_USER_FAIL_TEXT', 'detail' => $resultSent];
                } else {
                    $result = ['success' => true, 'message' => 'PASSWORD_UPDATED_AND_SENT_TO_USER_SUCCESS_TEXT', 'detail' => $resultSent];
                }
                goto end_of_function;
            } else {
                $employee = Employee::findFirstByUuid($userProfileUuid);
                if ($employee && ($employee->manageByGms()) &&
                    $employee->isActive() &&
                    $employee->hasActiveUserLogin()) {

                    $userLogin = $employee->getUserLogin();
                    $resultSent = $userLogin->forceResetPassword();
                    if ($resultSent['success'] == false) {
                        $result = ['success' => false, 'message' => 'PASSWORD_UPDATED_AND_SENT_TO_USER_FAIL_TEXT', 'detail' => $resultSent];
                    } else {
                        $result = ['success' => true, 'message' => 'PASSWORD_UPDATED_AND_SENT_TO_USER_SUCCESS_TEXT', 'detail' => $resultSent];
                    }
                    goto end_of_function;
                } else {
                    $result = ['success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Create or update method
     * @return string
     */
    public function activateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_COMPANY_MEMBER);

        $result = [
            'success' => false,
            'message' => 'GMS_MEMBER_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user_profile_uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid'] : "";

        if ($user_profile_uuid != '' && Helpers::__isValidUuid($user_profile_uuid)) {

            $userProfile = UserProfile::findFirstByUuid($user_profile_uuid);

            if ($userProfile && $userProfile->belongsToGms() && $userProfile->isGms()) {

                if ($userProfile->isAdminOrManager() && ModuleModel::$user_profile->isAdminOrManager() == false) {

                    /** @var ONLY ADMIN OR MANAGER CAN EDIT ADMIN $result */
                    $result = [
                        'success' => false,
                        'message' => 'ONLY_ADMIN_MANAGER_CAN_DO_THAT_TEXT',
                        'data' => $userProfile,
                    ];

                } else {
                    $userProfile->setActive(ModelHelper::YES);
                    $userProfile->setLoginMissingStatus();
                    $resultUserProfile = $userProfile->__quickUpdate();
                    if ($resultUserProfile['success'] == true) {

                        $userLogin = $userProfile->getUserLogin();
                        if ($userLogin && $userLogin instanceof $userLogin) {
                            $resultUserLogin = $userLogin->clearUserLoginWhenUserDeactivated();
                            if ($resultUserLogin['success'] == false) {
                                $result = $resultUserLogin;
                            }
                        }

                        $result = [
                            'success' => true,
                            'message' => 'GMS_MEMBER_SAVE_SUCCESS_TEXT',
                            'data' => $userProfile->parsedDataToArray(),
                        ];
                    } else {
                        $result = $resultUserProfile;
                    }
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Create or update method
     * @return string
     */
    public function desactivateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_COMPANY_MEMBER);

        $result = [
            'success' => false,
            'message' => 'GMS_MEMBER_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user_profile_uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid'] : "";

        if ($user_profile_uuid != '' && Helpers::__isValidUuid($user_profile_uuid)) {

            $userProfile = UserProfile::findFirstByUuid($user_profile_uuid);

            if ($userProfile && $userProfile->belongsToGms() && $userProfile->isGms()) {

                if ($userProfile->isAdminOrManager() && ModuleModel::$user_profile->isAdminOrManager() == false) {

                    /** @var ONLY ADMIN OR MANAGER CAN EDIT ADMIN $result */
                    $result = [
                        'success' => false,
                        'message' => 'ONLY_ADMIN_MANAGER_CAN_DO_THAT_TEXT',
                        'data' => $userProfile,
                    ];

                } else {

                    $userLogin = $userProfile->getUserLogin();
                    if ($userLogin && $userLogin instanceof $userLogin) {
                        $resultUserLogin = $userLogin->clearUserLoginWhenUserDeactivated();
                        if ($resultUserLogin['success'] == false) {
                            $result = $resultUserLogin;
                            goto end_of_function;
                        }
                    }

                    $userProfile->setActive(ModelHelper::NO);
                    $userProfile->setInactiveStatus();
                    $resultUserProfile = $userProfile->__quickUpdate();
                    if ($resultUserProfile['success'] == true) {
                        $result = [
                            'success' => true,
                            'message' => 'GMS_MEMBER_SAVE_SUCCESS_TEXT',
                            'data' => $userProfile,
                        ];
                    } else {
                        $result = $resultUserProfile;
                    }
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $query = Helpers::__getRequestValue('query');
        $limit = Helpers::__getRequestValue('limit');
        if (is_string($query) && $query != '') {
            $params['query'] = $query;
        }

        $page = Helpers::__getRequestValue('page');
        if (is_numeric($page) && $page >= 0) {
            $params['page'] = $page;
        }
        $ids = Helpers::__getRequestValue('ids');
        if (!is_array($ids) && $ids) {
            $ids = explode(',', $ids);
        }
        $params['limit'] = $limit;
        $params['ids'] = $ids;
        $params['search_type'] = Helpers::__getRequestValue('search_type');
        $params['object_uuid'] = Helpers::__getRequestValue('object_uuid');
        $params['owner_uuid'] = Helpers::__getRequestValue('owner_uuid');
        $params['isRelocation'] = Helpers::__getRequestValue('isRelocation');

        if ($params['search_type'] == 'owner' && Helpers::__isValidUuid($params['object_uuid'])) {
            $objectMap = RelodayObjectMapHelper::__getObject($params['object_uuid']);
            if ($objectMap && $objectMap->isTask()) {
                $accessResult = $this->canAccessResource(AclHelper::CONTROLLER_DATA_MEMBER, AclHelper::ACTION_CHANGE_OWNER);
                if (!$accessResult['success']) {
                    $return = [
                        'data' => [ModuleModel::$user_profile],
                        'success' => true,
                        'total_items' => 1,
                        'total_rest_items' => 0,
                        'total_pages' => 1
                    ];
                    goto end_of_function;
                }
            }
        }

        $return = UserProfile::__findGmsWorkersWithFilter($params);

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getMembersByIdsAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $ids = Helpers::__getRequestValue('ids');
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }

        $users = UserProfile::find([
            'conditions' => 'id IN ({country_ids:array})',
            'bind' => [
                'country_ids' => $ids
            ],
            'columns' => 'id, uuid, firstname, lastname, workemail'
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $users
        ]);
        return $this->response->send();
    }
}
