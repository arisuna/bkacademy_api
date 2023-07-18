<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\EmailHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\EmployeeExt;
use Reloday\Application\Models\UserLoginExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\SupportedLanguage;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\UserGroup;
use Reloday\Application\Models\UserProfileExt;
use Reloday\Gms\Models\MediaAttachment;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class UserController extends BaseController
{
    /**
     *
     */
    public function initialize()
    {
        $this->view->disable();
    }

    /**
     * @Route("/user", paths={module="gms"}, methods={"GET"}, name="gms-user-index")
     */
    public function indexAction()
    {

    }

    /**
     * Load list user into dropdown list
     */
    public function dropdownAction()
    {

    }


    /**
     * Change password by ajax call
     * @url /user/changePwd
     * @param old_pwd , new_pwd
     * @method PUT
     * @return string
     */
    public function changePwdAction()
    {

        $this->checkAjaxPut();

        // 1. Check old password exactly or wrong
        $old_pwd = $this->request->getPut('old_pwd');
        $current_pwd = ModuleModel::$user_login->getPassword();
        if (!$this->security->checkHash($old_pwd, $current_pwd)) {
            exit(json_decode([
                'success' => false,
                'message' => 'PREVIOUS_PASSWORD_WRONG_TEXT'
            ]));
        }

        // 2. Change new password
        $new_pwd = $this->request->getPut('new_pwd');
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Za-z\d$@$.!%*?&#]{8,}$/', $new_pwd)) {
            exit(json_decode([
                'success' => false,
                'message' => 'PASSWORD_INVALID_TEXT'
            ]));
        }

        // 3. New password has validated, then update
        $user = UserLogin::findFirst(ModuleModel::$user_login->getId());
        $user->setPassword($this->security->hash($new_pwd));
        if ($user->save()) {
            $result = [
                'success' => true,
                'message' => 'CHANGE_PASSWORD_SUCCESS_TEXT'
            ];
        } else {
            $result = [
                'success' => false,
                'message' => 'UPDATE_PASSWORD_FAIL_TEXT'
            ];
        }
        $this->view->disable();
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * detail of Profile
     * @param $uuid
     */
    public function simpleAction($user_uuid)
    {
        $this->view->disable();
        if ($user_uuid != '') {
            $profile = UserProfile::findFirstByUuid($user_uuid);
            $profile_array = $profile->toArray();
            $data = [];
            $data['uuid'] = $profile_array['uuid'];
            $data['firstname'] = $profile_array['firstname'];
            $data['email'] = $profile_array['workemail'];
            $data['lastname'] = $profile_array['lastname'];
            $data['avatar'] = $profile->getAvatar();
            if ($profile instanceof UserProfile) {
                $result = [
                    'success' => true,
                    'data' => $data,
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
     * @param $id
     */
    public function itemAction($id)
    {
        $this->view->disable();
        $access = $this->canAccessResource($this->router->getControllerName(), 'index');
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $id = $id ? $id : $this->request->get('id');
        if ($id) {
            $profile = UserProfile::findFirst($id);
            if ($profile instanceof UserProfile && $profile->controlByGms()) {
                $result = [
                    'success' => true,
                    'data' => $profile,
                ];
            } else {
                $result = [
                    'success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
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
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());

        $result = [
            'success' => false,
            'message' => 'USER_PROFILE_NOT_FOUND_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user_profile_uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid'] : "";

        if ($user_profile_uuid != '') {
            $userProfile = UserProfile::findFirstByUuid($user_profile_uuid);
            if ($userProfile && $userProfile->controlByGms()) {

                if ($userProfile->getNickname() == '') {
                    $nickname = $userProfile->generateNickName();
                    $dataInput['nickname'] = $nickname;
                }
                $modelResult = $userProfile->saveUserProfile($dataInput);
                if ($modelResult instanceof UserProfile) {
                    $result = [
                        'success' => true,
                        'message' => 'USER_PROFILE_SAVE_SUCCESS_TEXT',
                        'data' => $modelResult,
                    ];
                } else {
                    $result = $modelResult;
                }
            }
        }

        $this->response->setJsonContent($result, 'utf-8');
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
        return $this->createUserProfile();
    }

    /**
     * [checkAction description]
     * @param string $hash [description]
     * @return [type]       [description]
     */
    public function checkEmailAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $email = Helpers::__getRequestValue('email');
        $userProfile = Helpers::__getRequestValueAsArray('profile');

        $return = [
            'success' => true,
            'message' => 'EMAIL_AVAILABLE_TEXT',
            'email' => $email,
        ];

        if (!$userProfile || $userProfile == null) {
            $emailCheck = EmailHelper::__isAvailable($email);
            if ($emailCheck == false) {
                $return = [
                    'success' => false,
                    'message' => 'EMAIL_ALREADY_TAKEN_TEXT'
                ];
                goto end_of_function;
            }
        } else if (Helpers::__existCustomValue('uuid', $userProfile)) {
            $emailCheck = EmailHelper::__isAvailable($email, $userProfile['uuid']);
            if ($emailCheck == false) {
                $return = [
                    'success' => false,
                    'message' => 'EMAIL_NOT_AVAILABLE_TEXT'
                ];
                goto end_of_function;
            }
        }
        end_of_function:

        //$userLoginToCheck = UserLoginExt::findFirstByEmail($email);
        //$userProfileToCheck = UserProfileExt::findFirstByUserLoginId($userLoginToCheck->getId());
        //$employeeProfileToCheck = EmployeeExt::findFirstByUserLoginId($userLoginToCheck->getId());
        //$return['$employeeProfileToCheck'] = $employeeProfileToCheck;
        //$return['$userProfileToCheck'] = $userProfileToCheck;
        //$return['$userLoginToCheck'] = $userLoginToCheck;

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createUserProfile()
    {

        $dataInput = $this->request->getJsonRawBody(true);
        if (isset($dataInput['user_group_id']) && is_numeric($dataInput['user_group_id']) && $dataInput['user_group_id'] > 0) {
            $gmpRoles = UserGroup::__getGmpRoleIds();
            $hrRoles = UserGroup::__getHrRoleIds();
            if (in_array($dataInput['user_group_id'], $gmpRoles)) {
                //create GMS
                $dataInput['company_id'] = ModuleModel::$company->getId();
            } elseif (in_array($dataInput['company_id'], $gmpRoles)) {
                //Create HR
            }
        } else {
            goto end_of_function;
        }
        $userProfile = new UserProfile();
        $userProfile->setData($dataInput);
        $nickname = $userProfile->generateNickName();
        unset($userProfile);
        $this->db->begin();
        $userProfile = new UserProfile();
        $dataInput['nickname'] = $nickname;
        $modelResult = $userProfile->saveUserProfile($dataInput);
        if (!($modelResult instanceof UserProfile || $modelResult instanceof UserProfileExt)) {
            $result = [
                'success' => false,
                'message' => 'USER_PROFILE_SAVE_FAIL_TEXT',
                'detail' => $modelResult
            ];
            goto end_of_function;
        }

        $result = [
            'success' => true,
            'message' => 'USER_PROFILE_SAVE_SUCCESS_TEXT',
            'data' => $modelResult,
        ];
        //create login
        if ($modelResult->getActive() == UserProfile::STATUS_ACTIVE) {
            $user_login = new UserLogin();
            $user_login_email = '';
            $user_login_password = '';
            if (isset($dataInput->user_login)) {
                $user_login_email = $dataInput->user_login->email;
                $user_login_password = $dataInput->user_login->password;
            }
            if (isset($dataInput['user_login'])) {
                $user_login_email = $dataInput['user_login']['email'];
                $user_login_password = $dataInput['user_login']['password'];
            }
            if ($user_login_email != '' && $user_login_password != '') {
                $checkUserLoginModel = $user_login->__save_infos([
                    'user_login_email' => $user_login_email,
                    'user_login_password' => $user_login_password,
                    'app_id' => $modelResult->getCompany()->getAppId(),
                    'status' => UserLogin::STATUS_ACTIVATED,
                    'user_group_id' => $modelResult->getUserGroupId()
                ]);
                if ($checkUserLoginModel instanceof UserLogin) {
                    $modelResult->setUserLoginId($checkUserLoginModel->getId());
                    $res = $modelResult->__quickUpdate();
                    if ($res['success'] == false) {
                        $result = [
                            'success' => false,
                            'message' => 'USER_PROFILE_SAVE_FAIL_TEXT',
                            'detail' => $res,
                            'raw' => $modelResult,
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    //create email login
                    $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                    $dataArray = [
                        'action' => "sendMail",
                        'to' => $user_login->getEmail(),
                        'user_name' => $modelResult->getFullname(),
                        'user_login' => $user_login->getEmail(),
                        'user_password' => $user_login_password,
                        'url_login' => $user_login->getApp()->getLoginUrl(),
                        'company_name' => ModuleModel::$company->getName(),
                        'templateName' => EmailTemplateDefault::INFORMATION_LOGIN,
                        'language' => ModuleModel::$system_language
                    ];
                    $resultCheck = $beanQueue->addQueue($dataArray);
                    $result['relodayQueue'] = $resultCheck;

                } else {
                    $this->db->rollback();
                    $result = [
                        'success' => false,
                        'message' => 'USER_PROFILE_SAVE_FAIL_TEXT',
                        'profile' => $modelResult,
                        'detail' => $checkUserLoginModel
                    ];
                    goto end_of_function;
                }
            }
        }

        $this->db->commit();
        end_of_function:
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

}
