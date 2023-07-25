<?php

namespace  SMXD\Application\Behavior;

use Phalcon\Mvc\Model\Behavior;
use Phalcon\Mvc\Model\BehaviorInterface;

class UserCacheBehavior extends SMXDCacheBehavior implements BehaviorInterface
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

                if( method_exists( $model ,'getCacheNameWorker'))
                    $this->emptyCache( $model->getCacheNameWorker() );

                //clear cache gms workers
                if( method_exists( $model ,'getCacheNameUserProfile'))
                    $this->emptyCache( $model->getCacheNameUserProfile() );

                if( method_exists( $model ,'__getCacheNameGWorker'))
                    $this->emptyCache( $model->__getCacheNameWorker(  $model->getCompanyId() ) );

                break;

            default:
                /* ignore the rest of events */
        }
    }
}