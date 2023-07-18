<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class ContractPermissionItem extends \Reloday\Application\Models\ContractPermissionItemExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\ContractPermissionItem|\Reloday\Application\Models\ContractPermissionItem[]
     */
    public static function __findFromCache()
    {
        return self::find([
            'cache' => [
                "key" => "__CACHE_CONTRACT_PERMISSIONS__",
                "lifetime" => CacheHelper::__TIME_24H
            ]
        ]);
    }
}
