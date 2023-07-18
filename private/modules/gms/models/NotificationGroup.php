<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class NotificationGroup extends \Reloday\Application\Models\NotificationGroupExt
{
    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\NotificationGroup|\Reloday\Application\Models\NotificationGroup[]
     */
    public static function __findListGms()
    {
        return self::find([
            'conditions' => 'is_gms = :yes:',
            'bind' => [
                'yes' => Helpers::YES,
            ],
            'cache' => [
                'key' => 'NOTIFICATION_GROUP_GMS',
                'lifetime' => CacheHelper::__TIME_24H
            ]
        ]);
    }
}
