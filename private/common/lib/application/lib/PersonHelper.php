<?php

namespace SMXD\Application\Lib;

use SMXD\Application\Models\ContactExt;
use SMXD\Application\Models\DependantExt;
use SMXD\Application\Models\EmployeeExt;
use SMXD\Application\Models\UserExt;

class PersonHelper
{
    /**
     * @param $uuid
     */
    public static function __getProfile(String $uuid)
    {
        $cacheName = CacheHelper::__getCacheNamePerson($uuid);
        $profile = CacheHelper::__getCacheValue($cacheName);

        if(!$profile) {
            $profile = UserExt::findFirstByUuidCache($uuid);
        }

        // if (!$profile) {
        //     $profile = EmployeeExt::findFirstByUuidCache($uuid);
        // }

        // if (!$profile) {
        //     $profile = DependantExt::findFirstByUuidCache($uuid);
        // }

        // if (!$profile) {
        //     $profile = ContactExt::findFirstByUuidCache($uuid);
        // }

        if( $profile ){
            CacheHelper::__updateCacheValue($cacheName, $profile);
        }
        return $profile;
    }
}