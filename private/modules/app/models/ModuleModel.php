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
    static function __checkAndRefreshAuthenByToken($accessToken, $refreshToken, $language = SupportedLanguageExt::LANG_VI)
    {

        self::$language = $language; // set language default
        $verifyAccessToken = ModuleModel::__verifyUserAccessToken($accessToken);
        $isRefreshed = false;

        if ($verifyAccessToken['success'] == false) {
            if ($verifyAccessToken['isExpired'] == false) {
                return $verifyAccessToken;
            }

            if (!isset($verifyAccessToken['user'])) {
                return $verifyAccessToken;
            }
            $resultRefreshToken = ModuleModel::__refreshUserAccessToken($verifyAccessToken['user'], $refreshToken);
            if ($resultRefreshToken['success'] == false) {
                return $resultRefreshToken;
            }
            $accessToken = $resultRefreshToken['accessToken'];
            $isRefreshed = true;
        }

        ModuleModel::$user_token = $accessToken;
        $return = $verifyAccessToken;
        $return['accessToken'] = $accessToken;
        $return['refreshToken'] = $refreshToken;
        $return['isRefreshed'] = $isRefreshed;
        self::$user = $verifyAccessToken['user'];
        end_of_function:
        return $return;
    }
}