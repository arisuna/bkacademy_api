<?php

/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 10/7/16
 * Time: 13:29
 */

namespace SMXD\Application\Lib;

class CacheHelper
{
    const __TIME_24H = 86400;
    const __TIME_1H = 3600;
    const __TIME_2H = 7200;
    const __TIME_4H = 14400;
    const __TIME_7_DAYS = 604800;

    const __TIME_6_MONTHS = 15552000;
    const __TIME_3_MONTHS = 7776000;
    const __TIME_5_MINUTES = 300;
    const __TIME_10_MINUTES = 600;
    const __TIME_1_MINUTE = 60;
    const __TIME_30_SECONDES = 30;
    const __TIME_15_SECONDES = 15;

    const __TIME_1_YEAR = 31104000;
    const ACL_CACHE = "ACL_CACHE_";
    const MENU_CACHE = "MENU_CACHE_";
    const PRIVILEGIES_CACHE = "PRIVILIGIES_CACHE";
    const USER_PROFILE_ITEM = "USER_PROFILE_ITEM_";


    const OBJECT_OWNER = "OBJECT_OWNER";
    const OBJECT_REPORTER = "OBJECT_REPORTER";
    const OBJECT_MEMBER = "OBJECT_MEMBER";
    const OBJECT_VIEWER = "OBJECT_VIEWER";
    const OBJECT_ITEM = "OBJECT_ITEM";

    /**
     * @return string
     */
    public static function getAclListGroupCacheName($groupId)
    {
        return "ACL_CACHE_GROUP_" . $groupId;
    }

    public static function getAclCacheByGroupName($groupId)
    {
        return "ACL_CACHE_CP_GP_" .  $groupId;
    }

    public static function __getAclCacheByGroupAclName($groupId, $aclId)
    {
        return "ACL_CACHE_CP_GP_ACL_" . $groupId . "_" . $aclId;
    }

    /**
     * @return string
     */
    public static function getAclCacheItemName($aclId)
    {
        return "ACL_CACHE_ITEM_" . $aclId;
    }

    /**
     * @return string
     */
    public static function getAclCacheItemCtrActionName($controller, $action)
    {
        return "ACL_CACHE_ITEM_CTRL_ACTION_" . $controller . "_" . $action;
    }

    /**
     * @return string
     */
    public static function getAclCacheItemIdName($companyId, $groupId, $aclId)
    {
        return "ACL_CACHE_ITEM_" . $companyId . "_" . $groupId . "_" . $aclId;
    }

    public static function getWebAclCacheByLvl($lvl)
    {
        return "WEB_ACL_CACHE_LVL_" .  $lvl;
    }

    public static function __getWebAclCacheByLvlAclName($lvl, $aclId)
    {
        return "WEB_ACL_CACHE_LVL_ACL_" . $lvl . "_" . $aclId;
    }

    /**
     * @return string
     */
    public static function getMenuCacheName($companyId, $groupId)
    {
        return "MENU_CACHE_" . $companyId . "_" . $groupId;
    }

    /**
     * @return string
     */
    public static function getPrivilegiesCacheName($appId, $groupId)
    {
        return "PRIVILEGIES_CACHE_" . $appId . "_" . $groupId;
    }

    /**
     * @param $uuid
     */
    public static function getCacheNameObjectItem($uuid)
    {
        return self::OBJECT_ITEM . $uuid;
    }


    /**
     * @param $uuid
     */
    public static function getCacheNameObjectOwners($uuid)
    {
        return self::OBJECT_OWNER . $uuid;
    }

    /**
     * @param $uuid
     */
    public static function getCacheNameObjectReporters($uuid)
    {
        return self::OBJECT_REPORTER . $uuid;
    }
    
    /**
     * @param $uuid
     */
    public static function __getCacheNameAttributeValue($id, $company_id)
    {
        return "ATTRIBUTE_VALUE_" . $id . "_" . $company_id;
    }

    /**
     * @param $id
     * @param string $language
     * @return string
     */
    public static function __getCacheNameAttributeValueTranslate($id, $language = "en")
    {
        return "ATTRIBUTE_VALUE_TRANSLATE" . $id . "_" . $language;
    }


    /**
     * @param $name
     * @return string
     */
    public static function __getCacheNameConstant($name)
    {
        return "__CONSTANT_" . $name;
    }

    /**
     * @param $name
     * @return string
     */
    public static function __getCacheNameConstantTranslation($name)
    {
        return "__CONSTANT_" . $name . "_TRANSLATION_";
    }

    /**
     * @param $name
     * @param string $language
     * @return string
     */
    public static function __getCacheNameConstantTranslationLanguage($name, $language = "en")
    {
        return "__CONSTANT_TRANSLATION_" . $name . "_LANG_" . $language;
    }

    /**
     * @param $objectName
     * @param $objectId
     * @return string
     */
    public static function __getCacheName($objectName, $objectId, $lang = '')
    {
        return "__CACHE_OBJECT_" . $objectName . "_ID_" . $objectId . "_" . ($lang != '' ? strtoupper($lang) : '');
    }

    /**
     * @param String $uuid
     * @return string
     */
    public static function __getCacheNameAvatar(String $uuid)
    {
        return "__CACHE_AVATAR_" . $uuid;
    }

    /**
     * @param String $uuid
     * @return string
     */
    public static function __getCacheNamePerson(String $uuid)
    {
        return "__CACHE_PERSON_" . $uuid;
    }

    /**
     * @return mixed
     */
    public static function __getCacheManager()
    {
        $cacheManager = \Phalcon\DI\FactoryDefault::getDefault()->getShared('cache');
        return $cacheManager;
    }

    /**
     * @param String $cacheName
     */
    public static function __getCacheValue(String $cacheName, $lifetime = self::__TIME_24H)
    {
        $cacheManager = self::__getCacheManager();
        if ($cacheManager->exists($cacheName)) {
            $result = $cacheManager->get($cacheName, $lifetime);
            if ($result === null) {
                return false;
            } else {
                return $result;
            }
        }
        return false;
    }

    public static function __getCacheLifeTime(String $cacheName)
    {

    }

    /**
     * @param String $cacheName
     * @param $value
     */
    public static function __updateCacheValue(String $cacheName, $value, $time = self::__TIME_24H)
    {
        $cacheManager = self::__getCacheManager();
        return $cacheManager->save($cacheName, $value, $time);
    }


    /**
     * @param String $cacheName
     * @param $value
     */
    public static function __deleteCache(String $cacheName)
    {
        $cacheManager = self::__getCacheManager();
        if ($cacheManager->exists($cacheName)) {
            return $cacheManager->delete($cacheName);
        } else {
            return -1;
        }
    }

    /**
     * @param String $cacheName
     * @return int
     */
    public static function __deleteModelsCache(String $cacheName)
    {
        $cacheManager = \Phalcon\DI\FactoryDefault::getDefault()->getShared('modelsCache');
        if ($cacheManager->exists($cacheName)) {
            return $cacheManager->delete($cacheName);
        } else {
            var_dump($cacheName);
            return -1;
        }
    }

    /**
     * @param String $cacheName
     */
    public static function __sendRequestCleanCache(String $cacheName)
    {
        $cacheManager = self::__getCacheManager();
        if ($cacheManager->exists($cacheName)) {
            $result = $cacheManager->delete($cacheName);
        }
    }

    /**
     * @param $uuid
     */
    public static function __getCacheNamePrefixItem($prefix, $uId)
    {
        return $prefix . "_" . $uId;
    }
}