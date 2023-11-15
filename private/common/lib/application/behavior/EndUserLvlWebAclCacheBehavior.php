<?php

namespace SMXD\Application\Behavior;

use Phalcon\Mvc\Model\Behavior;
use Phalcon\Mvc\Model\BehaviorInterface;
use SMXD\Application\Lib\CacheHelper;

class EndUserLvlWebAclCacheBehavior extends SMXDCacheBehavior implements BehaviorInterface
{

    const CACHE_NAME = 'END_USER_LVL_WEB_ACL';

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