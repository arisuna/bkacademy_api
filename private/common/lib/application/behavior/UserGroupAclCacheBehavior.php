<?php

namespace SMXD\Application\Behavior;

use Phalcon\Mvc\Model\Behavior;
use Phalcon\Mvc\Model\BehaviorInterface;
use SMXD\Application\Lib\CacheHelper;

class UserGroupAclCacheBehavior extends SMXDCacheBehavior implements BehaviorInterface
{

    const CACHE_NAME = 'USER_GROUP_ACL';

    /**
     * @param $eventType
     * @param $model
     */
    public function notify($eventType, \Phalcon\Mvc\ModelInterface $model)
    {
        switch ($eventType) {

            case 'afterDelete':
            case 'afterUpdate':
                //clear cache gms workers

                break;
            default:
                /* ignore the rest of events */
        }
    }
}