<?php

namespace Reloday\Gms\Help;

use Aws\Exception\AwsException;
use Phalcon\Security;
use Reloday\Application\Lib\CognitoAppHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\HrCompany;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;

class UserHelper
{
    /**
     * @param $userProfile
     * @param string $email
     * @param string $password
     * @return array
     */
    public static function __createLogin($userProfile, $email = '', $password = '', $isForceCreation = true)
    {
        //1. verification
        if (!($userProfile instanceof UserProfile || $userProfile->isUser())) {
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
            'app_id' => $userProfile->getCompany()->getAppId(),
            'status' => UserLogin::STATUS_ACTIVATED,
            'user_group_id' => $userProfile->getUserGroupId()
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


        //3.copy to Aws Cognito
        //For create member in GMS, we needn't create cognito account.
        //When User change password at the first time, we will create cognito user.
//        if($isForceCreation == true) {
//            $result = $userLogin->forceCreateCognitoLogin($password);
//        }else{
//            $result = $userLogin->createCognitoLogin($password);
//        }
//
//        if ($result['success'] == false) {
//            goto end_of_function;
//        }


        /**** SEND LOGIN INFORMATION ***/
        $urlLogin = $userLogin->getApp()->getLoginUrl();

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
            'templateName' => EmailTemplateDefault::INFORMATION_LOGIN,
            'templateName' => EmailTemplateDefault::CREATION_APPLICATION_SUCCESS,
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
        if (!($userProfile instanceof UserProfile || $userProfile->isUser())) {
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
                'app_id' => $userProfile->getCompany()->getAppId(),
                'status' => UserLogin::STATUS_ACTIVATED,
                'user_group_id' => $userProfile->getUserGroupId()
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

            if (!( isset($awsCognitoUser['awsUuid']) && Helpers::__isValidUuid($awsCognitoUser['awsUuid']) && $awsCognitoUser['awsUuid'] == $userLogin->getAwsUuid() ) ) {
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
        $urlLogin = $userLogin->getApp()->getLoginUrl();

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

}
