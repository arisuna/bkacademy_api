<?php

namespace Reloday\Gms\Help;

use Aws\Exception\AwsException;
use Phalcon\Security;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\CognitoAppHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\UserGroupExt;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\HrCompany;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;

class EmployeeHelper
{
    /**
     * @param $userProfile
     * @param string $email
     * @param string $password
     * @param boolean $isForceCreation
     * @param boolean $isCreateCognito
     * @return array
     */
    public static function __createLogin($userProfile, $email = '', $password = '', $isForceCreation = true, $isCreateCognito = true)
    {
        //1. verification
        if (!($userProfile instanceof Employee || $userProfile->isEmployee())) {
            $result = ['success' => false, 'message' => 'LOGIN_EXISTED_TEXT'];
            goto end_of_function;
        }

        $userLogin = $userProfile->getUserLogin();

        if ($userLogin) {
            $result = ['success' => false, 'message' => 'LOGIN_EXISTED_TEXT'];
            goto end_of_function;
        }

        if ($password == '') {
            $password = Helpers::password();
        }

        if ($email == '') {
            $email = $userProfile->getWorkemail();
        }

        //2 create user login
        $userLogin = new UserLogin();
        $resultCreateLogin = $userLogin->createNewUserLogin([
            'email' => $email,
            'password' => $password,
            //'app_id' => $userProfile->getCompany()->getAppId(),
            'status' => UserLogin::STATUS_ACTIVATED,
            'user_group_id' => UserGroupExt::GROUP_EE
        ]);

        if ($resultCreateLogin['success'] == false) {
            $result = [
                'success' => false,
                'errorDetail' => $resultCreateLogin,
                'message' => isset($resultCreateLogin['message']) ? $resultCreateLogin['message'] : 'USER_LOGIN_SAVE_FAIL_TEXT'
            ];
            goto end_of_function;
        }


        $userProfile->setActive(UserProfile::STATUS_ACTIVE);
        $userProfile->setLoginStatus(UserProfile::LOGIN_STATUS_PENDING);
        $userProfile->setUserLoginId($userLogin->getId());
        $resultUpdate = $userProfile->__quickUpdate();


        if ($resultUpdate['success'] == false) {
            $result = [
                'success' => false,
                'detail' => $resultUpdate,
                'message' => 'USER_LOGIN_SAVE_FAIL_TEXT'
            ];
            goto end_of_function;
        }


        //3.copy to Aws Cognito
        if ($isCreateCognito) {
            if ($isForceCreation == true) {
                $result = $userLogin->forceCreateCognitoLogin($password);
            } else {
                $result = $userLogin->createCognitoLogin($password);
            }
        } else {
            $result = [
                'success' => true
            ];
        }


        if ($result['success'] == false) {
            goto end_of_function;
        }


        /**** SEND LOGIN INFORMATION ***/
        $urlLogin = '';
        if (method_exists($userProfile, 'getEmployeeUrl')) {
            $urlLogin = $userProfile->getEmployeeUrl();
        }

        $resultCheck = [];
        $beanQueueEmail = RelodayQueue::__getQueueSendMail();


        //if first user login
        if ($userProfile && $userLogin->isFirst() == true) {
            $dataQueue = [
                //todo : 10 parameters maxi
                'action' => "sendMail",
                'to' => $userLogin->getEmail(),
                'email' => $userLogin->getEmail(),
                'templateName' => EmailTemplateDefault::CREATION_APPLICATION_SUCCESS,
                'language' => ModuleModel::$system_language,
                'params' => [
                    'user_login' => $userLogin->getEmail(),
                    'url_login' => $urlLogin,
                    'user_password' => $password,
                    'user_name' => $userProfile->getFullname(),
                ]
            ];
            $resultCheck[] = $beanQueueEmail->addQueue($dataQueue);
        }


        $dataArrayInformationLogin = [
            //todo : 10 parameters maxi
            'action' => "sendMail",
            'to' => $userLogin->getEmail(),
            'email' => $userLogin->getEmail(),
            'templateName' => EmailTemplateDefault::ASSIGNEE_LOGIN,
            'language' => ModuleModel::$system_language,
            'params' => [
                'user_login' => $userLogin->getEmail(),
                'url_login' => $urlLogin,
                'user_password' => $password,
                'user_name' => $userProfile->getFullname(),
                'company_name' => ModuleModel::$company->getName(),
            ]
        ];
        $resultCheck[] = $beanQueueEmail->addQueue($dataArrayInformationLogin);

        $result = [
            'success' => true,
            'data' => $userLogin,
            'resultCheckSendMail' => $resultCheck,
            'templateName' => EmailTemplateDefault::ASSIGNEE_LOGIN,
            'message' => 'USER_LOGIN_SAVE_SUCCESS_TEXT'
        ];

        /**** end send new password to user ***/

        end_of_function:
        return $result;

    }


    /**
     * @param $userProfile
     * @param string $email
     * @param string $password
     * @return array
     */
    public static function __updateLogin($userProfile, $email = '', $password = '')
    {
        //1. verification
        if (!($userProfile instanceof Employee || $userProfile->isEmployee())) {
            $result = ['success' => false, 'message' => 'LOGIN_EXISTED_TEXT'];
            goto end_of_function;
        }

        $userLogin = $userProfile->getUserLogin();

        if (!$userLogin && $userLogin->isDeleted() == true) {
            $result = ['success' => false, 'message' => 'LOGIN_DOES_NOT_EXIST_TEXT'];
            goto end_of_function;
        }

        if ($password == '') {
            $password = Helpers::password();
        }

        //2 create user login if email changed

        if ($email != '' && Helpers::__isEmail($email) && $email != $userLogin->getEmail()) {
            //delete userLogin
            $resultDelete = $userLogin->__quickRemove();
            if ($resultDelete['success'] == false) {
                goto end_of_function;
            }

            //delete userLogin in cognito
            $resultDelete = CognitoAppHelper::__deleteClientUserByUuid($userLogin->getAwsUuid());
            if ($resultDelete['success'] == false) {
                goto end_of_function;
            }

            $userLogin = new UserLogin();
            $resultCreateLogin = $userLogin->createNewUserLogin([
                'email' => $email,
                'password' => $password,
                //'app_id' => $userProfile->getCompany()->getAppId(),
                'status' => UserLogin::STATUS_ACTIVATED,
                'user_group_id' => UserGroupExt::GROUP_EE
            ]);

            if ($resultCreateLogin['success'] == false) {
                $result = [
                    'success' => false,
                    'errorDetail' => $resultCreateLogin,
                    'message' => isset($resultCreateLogin['message']) ? $resultCreateLogin['message'] : 'USER_LOGIN_SAVE_FAIL_TEXT'
                ];
                goto end_of_function;
            }

            $userProfile->setActive(UserProfile::STATUS_ACTIVE);
            $userProfile->setUserLoginId($userLogin->getId());
            $resultUpdate = $userProfile->__quickUpdate();

            if ($resultUpdate['success'] == false) {
                $result = [
                    'success' => false,
                    'detail' => $resultUpdate,
                    'message' => 'USER_LOGIN_SAVE_FAIL_TEXT'
                ];
                goto end_of_function;
            }

            //update Cognito

            //3.copy to Aws Cognito
            $result = $userLogin->forceCreateCognitoLogin($password);

            if ($result['success'] == false) {
                goto end_of_function;
            }
        } else {

            $awsCognitoUserResult = CognitoAppHelper::__getUserCognitoByEmail($userLogin->getEmail());

            if ($awsCognitoUserResult['success'] == false) {
                $result = [
                    'success' => false,
                    'detail' => $awsCognitoUserResult,
                    'message' => 'USER_LOGIN_SAVE_FAIL_TEXT'
                ];
                goto end_of_function;
            }

            $awsCognitoUser = $awsCognitoUserResult['user'];

            if (!(isset($awsCognitoUser['awsUuid']) && Helpers::__isValidUuid($awsCognitoUser['awsUuid']) && $awsCognitoUser['awsUuid'] == $userLogin->getAwsUuid())) {
                $result = [
                    'success' => false,
                    'detail' => $awsCognitoUserResult,
                    'message' => 'USER_LOGIN_SAVE_FAIL_TEXT'
                ];
                goto end_of_function;
            }

            $result = CognitoAppHelper::__forceUpdatePassword($userLogin->getEmail(), $password);
            if ($result['success'] == true) {
                goto end_of_function;
            }
        }


        /**** SEND LOGIN INFORMATION ***/
        $urlLogin = '';
        if (method_exists($userProfile, 'getEmployeeUrl')) {
            $urlLogin = $userProfile->getEmployeeUrl();
        }

        $resultCheck = [];
        $beanQueueEmail = RelodayQueue::__getQueueSendMail();
        $dataArrayInformationLogin = [
            //todo : 10 parameters maxi
            'action' => "sendMail",
            'to' => $userLogin->getEmail(),
            'email' => $userLogin->getEmail(),
            'templateName' => EmailTemplateDefault::INFORMATION_LOGIN,
            'language' => ModuleModel::$language,
            'params' => [
                'user_login' => $userLogin->getEmail(),
                'url_login' => $urlLogin,
                'user_password' => $password,
                'user_name' => $userProfile->getFullname(),
                'company_name' => ModuleModel::$company->getName(),
            ]
        ];
        $resultCheck[] = $beanQueueEmail->addQueue($dataArrayInformationLogin);

        $result = [
            'success' => true,
            'data' => $userLogin,
            'resultCheckSendMail' => $resultCheck,
            'message' => 'USER_LOGIN_SAVE_SUCCESS_TEXT'
        ];

        /**** end send new password to user ***/

        end_of_function:
        return $result;

    }

    /**
     * @param $employee
     */
    public static function __reInvite($employee, $emailTemplate = EmailTemplateDefault::SEND_NEW_PASSWORD)
    {
        $di = \Phalcon\DI::getDefault();
        $db = $di->get('db');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!$employee || !$employee->manageByGms()) {
            $result = ['success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($employee->isEditable() == false) {
            $result = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            goto end_of_function;
        }

        $userLogin = $employee->getUserLogin();

        $db->begin();

        if ($userLogin) {
            //Delete cognito if exited
            $isExistedInCognito = $userLogin->isConvertedToUserCognito();

            if ($isExistedInCognito) {
                //Cognito Exist

                $cognitoLogin = $userLogin->getCognitoLogin();

                if ($cognitoLogin && ($cognitoLogin['userStatus'] == CognitoClient::CONFIRMED || $cognitoLogin['userStatus'] == CognitoClient::CHALLENGE_NEW_PASSWORD_REQUIRED)) {
                    $result = ['success' => false, 'message' => 'ASSIGNEE_AUTHENTICATED_CAN_NOT_REINITIALISE_TEXT'];
                    goto end_of_function;
                }

                if ($cognitoLogin && ($cognitoLogin['userStatus'] == CognitoClient::UNCONFIRMED || $cognitoLogin['userStatus'] == CognitoClient::FORCE_CHANGE_PASSWORD)) {
                    if ($userLogin->getAwsUuid()) {
                        $resultDeleteCognito = ApplicationModel::__adminDeleteUser($userLogin->getAwsUuid());
                    } else {
                        $resultDeleteCognito = ApplicationModel::__adminDeleteUser($userLogin->getEmail());
                    }

                    if ($resultDeleteCognito['success'] == true) {
                        //delete aws_uuid from userLogin
                        if ($userLogin->getAwsUuid()) {
                            $userLogin->setAwsUuid(null);
                        }

                        $user_login_password = Helpers::password(16);
                        $userLogin->setPassword(Helpers::__passwordHash($user_login_password));
                        $resultSaveUserLogin = $userLogin->__quickUpdate();
                        if ($resultSaveUserLogin['success'] == false) {
                            $db->rollback();
                            $result = $resultSaveUserLogin;
                            goto end_of_function;
                        }
                    }
                }
            } else {
                //Cognito NOT EXIST
                $user_login_password = Helpers::password(16);
                $userLogin->setPassword(Helpers::__passwordHash($user_login_password));
                $resultSaveUserLogin = $userLogin->__quickUpdate();
                if ($resultSaveUserLogin['success'] == false) {
                    $db->rollback();
                    $result = $resultSaveUserLogin;
                    goto end_of_function;
                }
            }
        } else {
            $user_login_password = Helpers::password(16);
            $userLogin = new UserLogin();
            $userLogin->setData([
                'email' => $employee->getWorkemail(),
                'password' => $user_login_password,
                'app_id' => $employee->getCompany()->getAppId(),
                'status' => UserLogin::STATUS_ACTIVATED,
                'user_group_id' => UserLogin::USER_GROUP_EE,
            ]);
            $resultSaveUserLogin = $userLogin->__quickCreate();
            if ($resultSaveUserLogin['success'] == false) {
                $db->rollback();
                $result = $resultSaveUserLogin;
                goto end_of_function;
            }
            $employee->setActive(Employee::ACTIVE_YES);
            $employee->setUserLoginId($userLogin->getId());
        }

        $employee->setActive(Employee::ACTIVE_YES);
        $employee->setLoginStatus(Employee::LOGIN_STATUS_PENDING);
        $resultSaveEmployee = $employee->__quickUpdate();
        if ($resultSaveEmployee['success'] == false) {
            $db->rollback();
            $result = $resultSaveEmployee;
            goto end_of_function;
        }

        $db->commit();

        $result['success'] = true;
        $result['employee'] = $employee->parseArrayData();
        $result['message'] = 'INVITE_ASSIGNEE_SUCCESS_TEXT';

        /*** send new login password **/
        if (isset($user_login_password) && $user_login_password != '') {
            $url_login = $employee->getLoginUrl();
            $beanQueue = RelodayQueue::__getQueueSendMail();
            $dataArray = [
                'action' => "sendMail",
                'to' => $userLogin->getEmail(),
                'email' => $userLogin->getEmail(),
                'user_login' => $userLogin->getEmail(),
                'url_login' => $url_login,
                'user_password' => $user_login_password,
                'user_name' => $employee->getFullname(),
                'company_name' => ModuleModel::$company->getName(),
                'templateName' => $emailTemplate,
                'language' => ModuleModel::$system_language
            ];
            $resultCheck = $beanQueue->addQueue($dataArray);
            $result['resultCheck'] = $resultCheck;
        }

        end_of_function:
        return $result;
    }

}