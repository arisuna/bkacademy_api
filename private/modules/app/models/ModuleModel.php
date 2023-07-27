<?php

namespace SMXD\App\Models;

use Phalcon\Config;
use SMXD\Application\Lib\JWTEncodedHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\SupportedLanguageExt;
use SMXD\Application\Models\UserSettingDefaultExt;

/**
 * Base class of App model
 */
class ModuleModel extends ApplicationModel
{
    static $user_login_token;
    static $user_login;
    static $user;
    static $app;
    static $company;
    static $language;

    /**
     * @param $token_key
     * @param Config $config
     * @return array
     * @throws \Exception
     */
    static function __checkAuthenByOldToken($token_key, $language = 'en')
    {
        self::$language = $language; // set language default

        if ($token_key == '') {
            $return = [
                'success' => false,
                'message' => 'LOGIN_REQUIRED',
                'required' => 'login'
            ];
            goto end_of_function;
        }
        $tokenUserData = JWTEncodedHelper::decode($token_key);
        if ($tokenUserData == '' || !isset($tokenUserData['user_login_id'])) {
            $return = [
                'success' => false,
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        $user_login = UserLogin::findFirstById($tokenUserData['user_login_id']);
        if (!$user_login || (!$user_login->getUser() && !$user_login->getEmployee())) {
            $return = [
                'tokenKey' => $token_key,
                'success' => false,
                '$tokenUserData' => $tokenUserData,
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }
        //Only for User
        if( $user_login->getUser() ) {
            if (!$user_login->getApp()) {
                $return = [
                    'tokenKey' => $token_key,
                    'success' => false,
                    '$tokenUserData' => $tokenUserData,
                    'message' => 'SESSION_EXPIRED_TEXT',
                    'required' => 'login'
                ];
                goto end_of_function;
            }
        }

        if ($user_login->isDesactivated()) {
            $return = [
                'success' => false,
                'message' => 'USER_DESACTIVATED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        self::$user_login_token = $token_key;
        self::$user_login = $user_login;
        self::$user = self::$user_login->getUser() ? self::$user_login->getUser() : (self::$user_login->getEmployee() ? self::$user_login->getEmployee() : null);
        self::$app = self::$user_login->getApp();
        self::$company = self::$user ? self::$user->getCompany() : null;
        $language = self::$user->getUserSettingValue(UserSettingDefaultExt::DISPLAY_LANGUAGE);
        self::$language = $language != '' && in_array($language, SupportedLanguageExt::$languages) ? $language : SupportedLanguageExt::LANG_EN;

        $return = [
            'success' => true,
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
            !$user_login->getUser()) {
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
        self::$user = self::$user_login->getUser();
        if(!self::$user->isAdmin()){
            self::$plan = self::$subscription ? self::$subscription->getPlan() : null;
            self::$company = self::$user->getCompany();
            if (!self::$company || self::$company->isActive() == false) {
                $return = [
                    'success' => false,
                    'message' => 'LOGIN_FAIL',
                ];
                goto end_of_function;
            }
            self::$language = SupportedLanguage::LANG_VI;

            // $language = self::$user->getUserSettingValue(UserSettingDefault::DISPLAY_LANGUAGE);
            // self::$language = $language != '' && in_array($language, SupportedLanguage::$languages) ? $language : SupportedLanguage::LANG_EN;
        } else {
            self::$language = SupportedLanguage::LANG_VI;
        }
        


        $return = [
            'success' => true,
            'tokenKey' => $accessToken,
            'message' => 'LOGIN_SUCCESS',
        ];

        end_of_function:
        return $return;
    }
}