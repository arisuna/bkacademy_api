<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\SupportedLanguage;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Di;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Aws\Exception\AwsException;
use Phalcon\Security;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class LoginController extends BaseController
{
    /**
     * @param $uuid
     * @return mixed
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $uuid = $uuid ? $uuid : $this->request->get('uuid');

        $result = [
            'success' => false, 'message' => 'LOGIN_NOT_FOUND_TEXT'
        ];

        if ($uuid != '') {
            $model = Employee::findFirstByUuid($uuid);
            if (!($model instanceof Employee)) {
                $model = UserProfile::findFirstByUuid($uuid);
            }

            if ($model && $model->belongsToGms() == true) {
                $login = $model->getUserLogin(['columns' => 'id, email, created_at, status, user_group_id']);
                if ($login) {
                    $result = [
                        'success' => true, 'data' => $login
                    ];
                }
            } elseif ($model && method_exists($model, 'manageByGms') && $model->manageByGms()) {
                $login = $model->getUserLogin(['columns' => 'id, email, created_at, status, user_group_id']);
                if ($login) {
                    $result = [
                        'success' => true, 'data' => $login
                    ];
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * save login from all api call
     * @return mixed
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);


        $data = Helpers::__getRequestValues();
        $uuid = Helpers::__getRequestValue('uuid') != '' ? Helpers::__getRequestValue('uuid') : false;
        $user_login_id = Helpers::__getRequestValue('user_login_id') != '' ? Helpers::__getRequestValue('user_login_id') : false;
        $user_login_password = Helpers::__getRequestValue('user_login_password ') != '' ? Helpers::__getRequestValue('user_login_password ') : false;
        $user_login_email = Helpers::__getRequestValue('user_login_email ') != '' ? Helpers::__getRequestValue('user_login_email ') : false;

        $result = ['success' => false, 'message' => 'LOGIN_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $userProfileModel = Employee::findFirstByUuid($uuid);

            if ($userProfileModel && $userProfileModel->belongsToGms()) {
                $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
            }

            if (!$userProfileModel) {
                $userProfileModel = UserProfile::findFirstByUuid($uuid);
                if ($userProfileModel && $userProfileModel->isGms() && $userProfileModel->belongsToGms()) {
                    $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
                }

                if ($userProfileModel && $userProfileModel->isHr() && $userProfileModel->manageByGms()) {
                    $this->checkAclEdit(AclHelper::CONTROLLER_HR_MEMBER);
                }
            }

            if (!$userProfileModel) {
                goto end_of_function;
            }

            if ((method_exists($userProfileModel, 'belongsToGms') && $userProfileModel->belongsToGms() == true) ||
                (method_exists($userProfileModel, 'manageByGms') && $userProfileModel->manageByGms() == true)
            ) {
                //EDIT USER PROFILE
                $user_login = $userProfileModel->getUserLogin();
                $this->db->begin();
                if ($user_login) {
                    //edit
                    $isEdit = true;
                    if ($userProfileModel instanceof Employee || $userProfileModel->isEmployee()) {
                        $checkUserModel = $user_login->updateUserLogin([
                            'email' => $user_login_email,
                            'password' => $user_login_password,
                            'app_id' => $userProfileModel->getCompany()->getAppId(),
                            'status' => UserLogin::STATUS_ACTIVATED,
                            'user_group_id' => UserLogin::USER_GROUP_EE,
                        ]);
                    }
                    if ($userProfileModel instanceof UserProfile || $userProfileModel->isUser()) {
                        $checkUserModel = $user_login->updateUserLogin([
                            'email' => $user_login_email,
                            'password' => $user_login_password,
                            'app_id' => $userProfileModel->getCompany()->getAppId(),
                            'status' => UserLogin::STATUS_ACTIVATED,
                            'user_group_id' => $userProfileModel->getUserGroupId()
                        ]);
                    }
                } else {
                    //create id
                    $isCreate = true;
                    $user_login = new UserLogin();
                    //is employee
                    if ($userProfileModel instanceof Employee || $userProfileModel->isEmployee()) {
                        $checkUserModel = $user_login->createNewUserLogin([
                            'email' => $user_login_email,
                            'password' => $user_login_password,
                            'app_id' => $userProfileModel->getCompany()->getAppId(),
                            'status' => UserLogin::STATUS_ACTIVATED,
                            'user_group_id' => UserLogin::USER_GROUP_EE,
                        ]);
                    }
                    //is worker
                    if ($userProfileModel instanceof UserProfile || $userProfileModel->isUser()) {
                        $checkUserModel = $user_login->createNewUserLogin([
                            'email' => $user_login_email,
                            'password' => $user_login_password,
                            'app_id' => $userProfileModel->getCompany()->getAppId(),
                            'status' => UserLogin::STATUS_ACTIVATED,
                            'user_group_id' => $userProfileModel->getUserGroupId()
                        ]);
                    }


                }

                if (!isset($checkUserModel) || $checkUserModel['success'] == false) {
                    $this->db->rollback();
                    $result = [
                        'success' => false,
                        'errorDetail' => isset($checkUserModel) ? $checkUserModel : null,
                        'message' => isset($checkUserModel['message']) ? $checkUserModel['message'] : 'USER_LOGIN_SAVE_FAIL_TEXT'
                    ];
                    goto end_of_function;
                }

                if (isset($checkUserModel) && $checkUserModel && $checkUserModel['success'] == true) {
                    $user_login = $checkUserModel["data"];

                    $userProfileModel->setActive(UserProfile::STATUS_ACTIVE);
                    if (isset($isCreate) && $isCreate == true) {
                        $userProfileModel->setUserLoginId($user_login->getId());
                    }

                    if ($userProfileModel->getUserLoginId() == null || $userProfileModel->getUserLoginId() == 0 || $userProfileModel->getUserLoginId() == 0) {
                        $userProfileModel->setUserLoginId($user_login->getId());
                    }
                    $resultUpdateUserProfile = $userProfileModel->__quickUpdate();
                    if ($resultUpdateUserProfile['success'] == false) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'detail' => $resultUpdateUserProfile,
                            'message' => 'USER_LOGIN_SAVE_FAIL_TEXT'
                        ];
                        goto end_of_function;
                    }
                    $result = [
                        'success' => true, 'data' => $checkUserModel, 'message' => 'USER_LOGIN_SAVE_SUCCESS_TEXT'
                    ];
                    $this->db->commit();
                    /**** if email and password to login is changed, delete the old record on aws cognito ****/
                    $security = new Security();
                    $pwd = $security->hash($user_login_password);
                    if ($user_login_email != $user_login->getEmail() || $pwd != $user_login->getPassword()) {
                        $resultCreate = ModuleModel::__startCognitoClient();
                        if ($resultCreate['success'] == false) {
                            $result = $resultCreate;
                            goto end_of_function;
                        }
                        try {
                            $client = ModuleModel::$cognitoClient;
                            $authenticationResponse = $client->adminDeleteUser($user_login_email);
                            $result['awsResponse'] = $authenticationResponse;
                        } catch (AwsException $e) {
                            $result['awsResponse'] = $e->getAwsErrorCode();
                            $result['awsResponseDetail'] = $e->getMessage();
                        }
                    }

                    /**** SEND LOGIN INFORMATION ***/
                    /**** SEND CONFIRM APPLICATION PROCESS ***/
                    $url_login = "";
                    if ($userProfileModel->isEmployee()) {
                        $url_login = $userProfileModel->getLoginUrl();
                    } else {
                        $url_login = $user_login->getApp()->getLoginUrl();
                    }
                    $resultCheck = [];
                    $beanQueueEmail = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                    //if first user login
                    if (!$userProfileModel->isEmployee() && $user_login->isFirst() == true) {
                        $dataArrayCreateApplicationSuccess = [
                            //todo : 10 parameters maxi
                            'action' => "sendMail",
                            'to' => $user_login->getEmail(),
                            'email' => $user_login->getEmail(),
                            'user_login' => $user_login->getEmail(),
                            'url_login' => $url_login,
                            'user_password' => $user_login_password,
                            'user_name' => $userProfileModel->getFullname(),
                            'templateName' => EmailTemplateDefault::CREATION_APPLICATION_SUCCESS,
                            'language' => ModuleModel::$system_language
                        ];
                        $resultCheck[] = $beanQueueEmail->addQueue($dataArrayCreateApplicationSuccess);
                    }


                    $dataArrayInformationLogin = [
                        //todo : 10 parameters maxi
                        'action' => "sendMail",
                        'to' => $user_login->getEmail(),
                        'email' => $user_login->getEmail(),
                        'user_login' => $user_login->getEmail(),
                        'url_login' => $url_login,
                        'user_password' => $user_login_password,
                        'user_name' => $userProfileModel->getFullname(),
                        'company_name' => ModuleModel::$company->getName(),
                        'templateName' => EmailTemplateDefault::INFORMATION_LOGIN,
                        'language' => ModuleModel::$language
                    ];
                    $resultCheck[] = $beanQueueEmail->addQueue($dataArrayInformationLogin);

                    $result = [
                        'success' => true,
                        'data' => $user_login,
                        'resultCheckSendMail' => $resultCheck,
                        'message' => 'USER_LOGIN_SAVE_SUCCESS_TEXT'
                    ];

                    /**** end send new password to user ***/
                } else {
                    $this->db->rollback();
                    $result = [
                        'success' => false,
                        'detail' => isset($checkUserModel) ? $checkUserModel : null,
                        'message' => 'USER_LOGIN_SAVE_FAIL_TEXT'
                    ];
                    goto end_of_function;
                }
            }
        }

        end_of_function:
        $result['resultCheck'] = isset($resultCheck) ? $resultCheck : false;
        $result['isEdit'] = isset($isEdit) ? $isEdit : false;
        $result['isCreate'] = isset($isCreate) ? $isCreate : false;
        if ($result['success'] == false) {
            if (is_array($result['detail'])) {
                $result['message'] = reset($result['detail']);
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


}
