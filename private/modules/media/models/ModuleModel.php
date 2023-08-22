<?php

namespace SMXD\Media\Models;

use Phalcon\Config;
use Phalcon\Exception;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\JWTEncodedHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\SupportedLanguageExt;

/**
 * Base class of Media model
 */
class ModuleModel extends ApplicationModel
{
    static $user_token;
    static $user;
    static $app;
    static $company;
    static $language;

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

        $checkAwsCognito = self::__verifyUserCognitoAccessToken($accessToken);
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

    /**
     * authentificate in the system
     * @param $token_key
     * @param Config $config
     * @return array
     * @throws \Exception
     */
    static function __checkAuthenByAwsUuid($awsUuid, $accessToken)
    {
        if ($awsUuid == '') {
            $return = [
                'success' => false,
                'message' => 'LOGIN_REQUIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        $user = User::findFirstByAwsCognitoUuid($awsUuid);

        if (!$user) {
            $return = [
                'awsUuid' => $awsUuid,
                'user' => $user,
                'success' => false,
                'type' => 'checkAuthenByAwsUuidFail:UserNotFound',
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }

        if ($user->isDeleted()) {
            $return = [
                'success' => false,
                'type' => 'checkAuthenByAwsUuidFail:UserDesactivated',
                'message' => 'USER_DESACTIVATED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }


        self::$user_token = $accessToken;
        self::$user = $user;
        self::$user = self::$user->getUser();
        self::$company = self::$user->getCompany();

        $return = [
            'success' => true,
            'message' => 'LOGIN_SUCCESS_TEXT',
        ];

        end_of_function:
        return $return;
    }

    /**
     * @return mixed|null
     */
    public static function __getAccessToken()
    {
        $accessToken = Helpers::__getHeaderValue(Helpers::TOKEN);
        if ($accessToken == '' || $accessToken == null) $accessToken = Helpers::__getHeaderValue(Helpers::EMPLOYEE_TOKEN_KEY);
        if ($accessToken == '' || $accessToken == null) $accessToken = Helpers::__getHeaderValue(Helpers::TOKEN_KEY);
        if ($accessToken == '' || $accessToken == null) $accessToken = Helpers::__getRequestValue(Helpers::TOKEN);
        if ($accessToken == '' || $accessToken == null) $accessToken = Helpers::__getRequestValue(Helpers::TOKEN_KEY);
        return $accessToken;
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
        $checkAwsUuid = self::__verifyUserCognitoAccessToken($accessToken);

        if ($checkAwsUuid['success'] == false) {
            $checkAwsUuid['tokenKey'] = $accessToken;
            if ($checkAwsUuid['isExpired'] == false) {
                $return = $checkAwsUuid;
                goto end_of_function;
            }

            $userPayload = JWTEncodedHelper::__getPayload($accessToken);


            if (!isset($userPayload['username'])) {
                return ['success' => false];
            }

            if (isset($userPayload['exp']) && intval($userPayload['exp']) < time()) {
                $resultRefreshToken = self::__refreshUserCognitoAccessToken($userPayload['username'], $refreshToken);
                if ($resultRefreshToken['success'] == false) {
                    $return = $resultRefreshToken;
                    goto end_of_function;
                }

                $accessToken = $resultRefreshToken['accessToken'];
                $refreshToken = $resultRefreshToken['refreshToken'];

                $userResult = self::__getUserCognito($accessToken);
                if ($userResult['success'] == false) {
                    $return = $userResult;
                    goto end_of_function;
                }

                $auth = self::__checkAuthenByAwsUuid($userResult['user']['awsUuid'], $accessToken);
                self::$user_token = $accessToken;
                if ($auth['success'] == false) {
                    $return = $auth;
                    goto end_of_function;
                }
            }
            $awsUuid = $userPayload['username'];
        } else {
            $awsUuid = $checkAwsUuid['key'];
        }


        $return = self::__checkAuthenByAwsUuid($awsUuid, $accessToken);
        self::$user_token = $accessToken;
        $return['accessToken'] = $accessToken;
        $return['refreshToken'] = $refreshToken;
        $return['isStep'] = 3;

        end_of_function:
        if (isset($return['success']) && $return['success'] == false) {
            if (isset($return['accessToken'])) unset($return['accessToken']);
            if (isset($return['refreshToken'])) unset($return['refreshToken']);
            if (isset($return['awsUuid'])) unset($return['awsUuid']);
        }
        return $return;
    }

}