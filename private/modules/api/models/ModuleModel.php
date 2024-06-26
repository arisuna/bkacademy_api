<?php

namespace SMXD\Api\Models;

use Phalcon\Config;
use SMXD\Api\Models\SupportedLanguage;
use SMXD\Api\Models\User;
use SMXD\Application\Lib\JWTEncodedHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\SupportedLanguageExt;

/**
 * Base class of Api model
 */
class ModuleModel extends ApplicationModel
{

    static $user_token;
    static $user;
    static $app;
    static $company;
    static $language;


    /**
     * @param $accessToken
     * @param $refreshToken
     * @param string $language
     * @throws \Exception
     */
    static function __checkAndRefreshAuthenByToken($accessToken, $refreshToken, $language = SupportedLanguageExt::LANG_EN)
    {

        self::$language = $language; // set language default
        $checkAwsUuid = ModuleModel::__verifyUserAccessToken($accessToken);
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
                $resultRefreshToken = ModuleModel::__refreshUserAccessToken($userPayload['username'], $refreshToken);
                if ($resultRefreshToken['success'] == false) {
                    return $resultRefreshToken;
                }
                $accessToken = $resultRefreshToken['accessToken'];
                $refreshToken = $resultRefreshToken['refreshToken'];

                $userResult = ModuleModel::__verifyUserAccessToken($accessToken);
                if ($userResult['success'] == false) {
                    return $userResult;
                }

                $auth = ModuleModel::__checkAuthenByAwsUuid($userResult['user']['awsUuid'], $accessToken);
                ModuleModel::$user_token = $accessToken;
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
        ModuleModel::$user_token = $accessToken;
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
        $user = User::findFirstByAwsCognitoUuid($awsUuid);

        if (!$user) {
            $userAwslogin = ApplicationModel::__verifyUserAccessTokenByUsername($awsUuid);
            if ($userAwslogin['success'] == true && isset($userAwslogin['user']) && isset($userAwslogin['user']['email'])) {
                $user = User::findFirstByEmail($userAwslogin['user']['email']);
            }
        }
        if (!$user) {
            $return = [
                'success' => false,
                'tokenKey' => $accessToken,
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        if ($user->isDeleted()) {
            $return = [
                'success' => false,
                'tokenKey' => $accessToken,
                'message' => 'USER_DESACTIVATED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }


        self::$user_token = $accessToken;
        self::$user = $user;
        self::$language = SupportedLanguage::LANG_VI;


        $return = [
            'success' => true,
            'tokenKey' => $accessToken,
            'message' => 'LOGIN_SUCCESS',
        ];

        end_of_function:
        return $return;
    }

    /**
     * authentificate in the system
     * @param $token_key
     * @param Config $config
     * @return array
     * @throws \Exception
     */
    static function __checkAuthenByAccessToken($accessToken)
    {
        if ($accessToken == '') {
            $return = [
                'success' => false,
                'type' => 'tokenNull',
                'message' => 'LOGIN_REQUIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        $checkAwsCognito = self::__verifyUserAccessToken($accessToken);
        if ($checkAwsCognito['success'] == false) {
            $return = [
                'success' => false,
                'type' => 'checkAwsCognitoError',
                'isExpired' => isset($checkAwsCognito['isExpired']) ? $checkAwsCognito['isExpired'] : false,
                'detail' => $checkAwsCognito,
                'token' => $accessToken,
                'message' => 'LOGIN_REQUIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        $authResult = self::__checkAuthenByAwsUuid($checkAwsCognito['key'], $accessToken);
        if ($authResult['success'] == false) {
            $return = [
                'success' => false,
                'isExpired' => isset($authResult['isExpired']) ? $authResult['isExpired'] : false,
                'type' => isset($authResult['type']) ? $authResult['type'] : 'checkAuthenByAwsUuidFail',
                'message' => 'LOGIN_REQUIRED_TEXT',
                'required' => 'login',
                'checkAwsCognito' => $checkAwsCognito
            ];
            goto end_of_function;
        }

        $return = [
            'success' => true,
            'message' => 'LOGIN_SUCCESS_TEXT',
        ];

        end_of_function:
        return $return;
    }
}