<?php

namespace Reloday\Gms\Models;

use Phalcon\Config;
use Phalcon\Exception;
use Phalcon\Http\Response;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\JWTEncodedHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\SupportedLanguageExt;

/**
 * Base class of Gms model
 */
class ModuleModel extends ApplicationModel
{
    static $user_login_token;
    static $user_login_refresh_token;
    static $user_login;
    static $user_profile;
    static $app;
    static $company;
    static $language; //display language
    static $system_language; //system language
    static $plan;
    static $subscription;

    static $assignment = null;
    static $oldAssignmentStartDate = null;
    static $oldAssignmentEndDate;
    static $relocationServiceCompany;
    static $serviceCompany;
    static $relocationServiceCompanies;
    static $relocation;
    static $employee;
    static $dependant;
    static $task;
    static $task_created_list;
    static $task_deleted_list;
    static $comment;
    static $housingProposition;
    static $needFormRequest;
    static $assigneeDocument;
    static $housingVisiteEvent;
    static $property;
    static $taskFile;
    static $invoice;
    static $credit_note;
    static $expense;
    static $timelog;
    static $transaction;
    static $bill;
    static $cacheFilterConfig;
    static $assignmentRequest;
    static $hrCompany;
    static $contract;
    static $objectFolder;
    /**
     * @param $accessToken
     * @param string $language
     * @return array
     */
    static function __checkAuthenByCognitoToken($accessToken, $language = 'en')
    {
        self::$language = $language; // set language default
        if (Helpers::__isNull($accessToken)) {
            $return = [
                'success' => false,
                'errorType' => 'LOGIN_REQUIRED',
                'message' => 'ERROR_SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        $checkAwsCognito = ModuleModel::__verifyUserCognitoAccessToken($accessToken);
        if ($checkAwsCognito['success'] == false) {
            $return = [
                'success' => false,
                'errorType' => 'LOGIN_REQUIRED',
                'message' => 'ERROR_SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        $auth = self::__checkAuthenByAwsUuid($checkAwsCognito['key'], $accessToken);
        if ($auth['success'] == false) {
            $return = [
                'success' => false,
                'message' => 'LOGIN_REQUIRED',
                'required' => 'login'
            ];
            goto end_of_function;
        }
        $return = [
            'success' => true,
            'message' => 'LOGIN_SUCCESS',
        ];

        end_of_function:
        return $return;
    }

    /**
     * @param $token_key
     * @param Config $config
     * @return array
     * @throws \Exception
     */
    static function __checkAuthenByOldToken($accessToken, $language = 'en')
    {
        self::$language = $language; // set language default

        if (Helpers::__isNull($accessToken)) {
            $return = [
                'success' => false,
                'message' => 'LOGIN_REQUIRED',
                'required' => 'login'
            ];
            goto end_of_function;
        }
        $tokenUserData = JWTEncodedHelper::decode($accessToken);
        if ($tokenUserData == '' || !isset($tokenUserData['user_login_id'])) {
            $return = [
                'success' => false,
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        $user_login = UserLogin::findFirstById($tokenUserData['user_login_id']);
        if (!$user_login ||
            !$user_login->getUserProfile() ||
            !$user_login->getApp() ||
            App::$appType[$user_login->getApp()->getType()] != \Phalcon\DI::getDefault()->getShared('moduleConfig')->application->appType) {
            $return = [
                'tokenKey' => $accessToken,
                'success' => false,
                '$tokenUserData' => $tokenUserData,
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        if ($user_login->isDesactivated()) {
            $return = [
                'success' => false,
                'message' => 'USER_DESACTIVATED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        self::$user_login_token = $accessToken;
        self::$user_login = $user_login;
        self::$user_profile = self::$user_login->getUserProfile();
        self::$app = self::$user_login->getApp();
        self::$company = self::$user_profile->getCompany();
        self::$subscription = self::$company->getSubscription();
        self::$plan = self::$subscription ? self::$subscription->getPlan() : null;

        if (self::$company->isActive() == false) {
            $return = [
                'success' => false,
                'message' => 'LOGIN_FAIL',
            ];
            goto end_of_function;
        }

        $language = self::$user_profile->getUserSettingValue(UserSettingDefault::DISPLAY_LANGUAGE);
        self::$language = $language != '' && in_array($language, SupportedLanguage::$languages) ? $language : SupportedLanguage::LANG_EN;

        $return = [
            'success' => true,
            'message' => 'LOGIN_SUCCESS',
        ];

        end_of_function:
        return $return;
    }

    /**
     * @param $awsUuid
     * @param string $language
     * @return array
     * @throws \Exception
     */
    static function __checkAuthenByAwsUuid($awsUuid, $accessToken = "", $language = 'en')
    {
        self::$language = $language; // set language default
        $user_login = UserLogin::findFirstByAwsUuid($awsUuid);

        if (!$user_login) {
            $userAwslogin = ApplicationModel::__getUserCognitoByUsername($awsUuid);
            if ($userAwslogin['success'] == true && isset($userAwslogin['user']) && isset($userAwslogin['user']['email'])) {
                $user_login = UserLogin::findFirstByEmail($userAwslogin['user']['email']);
            }
        }
        if (!$user_login ||
            !$user_login->getUserProfile() ||
            !$user_login->getUserProfile()->isGms() ||
            !$user_login->getApp() ||
            App::$appType[$user_login->getApp()->getType()] != \Phalcon\DI::getDefault()->getShared('moduleConfig')->application->appType) {
            $return = [
                'success' => false,
                'tokenKey' => $accessToken,
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        if ($user_login->isDesactivated()) {
            $return = [
                'success' => false,
                'tokenKey' => $accessToken,
                'message' => 'USER_DESACTIVATED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }


        self::$user_login_token = $accessToken;
        self::$user_login = $user_login;
        self::$user_profile = self::$user_login->getUserProfile();
        self::$app = self::$user_login->getApp();
        self::$company = self::$user_profile->getCompany();
        self::$subscription = self::$company->getSubscription();
        self::$plan = self::$subscription ? self::$subscription->getPlan() : null;
        if (!self::$company || self::$company->isActive() == false) {
            $return = [
                'success' => false,
                'message' => 'LOGIN_FAIL',
            ];
            goto end_of_function;
        }
        self::$system_language = self::$company->getLanguage();
        $system_language = self::$company->getLanguage();
        self::$system_language = $system_language != '' && in_array($system_language, SupportedLanguage::$languages) ? $system_language : SupportedLanguage::LANG_EN;;

        $language = self::$user_profile->getUserSettingValue(UserSettingDefault::DISPLAY_LANGUAGE);
        self::$language = $language != '' && in_array($language, SupportedLanguage::$languages) ? $language : SupportedLanguage::LANG_EN;

        $return = [
            'success' => true,
            'tokenKey' => $accessToken,
            'message' => 'LOGIN_SUCCESS',
        ];

        end_of_function:
        return $return;
    }

    /**
     * @param $accessToken
     * @param $refreshToken
     * @param string $language
     * @throws \Exception
     */
    static function __checkAndRefreshAuthenByCognitoToken($accessToken, $refreshToken, $language = SupportedLanguageExt::LANG_EN)
    {

        self::$language = $language; // set language default
        $checkAwsUuid = ModuleModel::__verifyUserCognitoAccessToken($accessToken);
        $isRefreshed = false;

        if ($checkAwsUuid['success'] == false) {
            $checkAwsUuid['tokenKey'] = $accessToken;
            if ($checkAwsUuid['isExpired'] == false) {
                return $checkAwsUuid;
            }

            $userPayload = JWTEncodedHelper::__getPayload($accessToken);


            if (!isset($userPayload['username'])) {
                return ['success' => false];
            }

            if (isset($userPayload['exp']) && intval($userPayload['exp']) < time()) {
                $resultRefreshToken = ModuleModel::__refreshUserCognitoAccessToken($userPayload['username'], $refreshToken);
                if ($resultRefreshToken['success'] == false) {
                    return $resultRefreshToken;
                }
                $accessToken = $resultRefreshToken['accessToken'];
                $refreshToken = $resultRefreshToken['refreshToken'];

                $userResult = ModuleModel::__getUserCognito($accessToken);
                if ($userResult['success'] == false) {
                    return $userResult;
                }

                $auth = ModuleModel::__checkAuthenByAwsUuid($userResult['user']['awsUuid'], $accessToken);
                ModuleModel::$user_login_token = $accessToken;
                if ($auth['success'] == false) {
                    return $auth;
                }
                $isRefreshed = true;
            }
            $awsUuid = $userPayload['username'];
        } else {
            $awsUuid = $checkAwsUuid['key'];
        }


        $return = ModuleModel::__checkAuthenByAwsUuid($awsUuid, $accessToken);
        ModuleModel::$user_login_token = $accessToken;
        $return['accessToken'] = $accessToken;
        $return['refreshToken'] = $refreshToken;
        $return['isRefreshed'] = $isRefreshed;
        end_of_function:
        return $return;
    }


}