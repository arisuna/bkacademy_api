<?php

namespace  SMXD\Application\Behavior;

use Phalcon\Mvc\Model\Behavior;
use Phalcon\Mvc\Model\BehaviorInterface;

class StudentCacheBehavior extends SMXDCacheBehavior implements BehaviorInterface
{

    const CACHE_USER_PROFILE = 'USER_PROFILE';
    /**
     * @param $eventType
     * @param $model
     */
    public function notify($eventType, \Phalcon\Mvc\ModelInterface $model)
    {
        switch ($eventType) {

            case 'afterCreate':
            case 'afterDelete':
            case 'afterUpdate':


                //clear cache gms workers
                if( method_exists( $model ,'getCacheNameStudent'))
                    $this->emptyCache( $model->getCacheNameStudent() );

                break;

            default:
                /* ignore the rest of events */
        }
    }
}