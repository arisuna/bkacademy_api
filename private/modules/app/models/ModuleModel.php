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
    static $user_profile;
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
        if (!$user_login || (!$user_login->getUserProfile() && !$user_login->getEmployee())) {
            $return = [
                'tokenKey' => $token_key,
                'success' => false,
                '$tokenUserData' => $tokenUserData,
                'message' => 'SESSION_EXPIRED_TEXT',
                'required' => 'login'
            ];
            goto end_of_function;
        }
        //Only for userProfile
        if( $user_login->getUserProfile() ) {
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
        self::$user_profile = self::$user_login->getUserProfile() ? self::$user_login->getUserProfile() : (self::$user_login->getEmployee() ? self::$user_login->getEmployee() : null);
        self::$app = self::$user_login->getApp();
        self::$company = self::$user_profile ? self::$user_profile->getCompany() : null;
        $language = self::$user_profile->getUserSettingValue(UserSettingDefaultExt::DISPLAY_LANGUAGE);
        self::$language = $language != '' && in_array($language, SupportedLanguageExt::$languages) ? $language : SupportedLanguageExt::LANG_EN;

        $return = [
            'success' => true,
            'message' => 'LOGIN_SUCCESS',
        ];

        end_of_function:
        return $return;
    }
}