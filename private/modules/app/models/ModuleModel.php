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
            $userAwslogin = ApplicationModel::__getUserCognitoByUsername($awsUuid);
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
}