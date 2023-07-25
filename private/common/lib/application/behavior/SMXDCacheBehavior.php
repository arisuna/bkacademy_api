<?php

namespace SMXD\Application\Behavior;

use Phalcon\Mvc\Model\Behavior;
use Phalcon\Mvc\Model\BehaviorInterface;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\RelodayQueue;

class SMXDCacheBehavior extends Behavior implements BehaviorInterface
{
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
                $this->emptyCache(CacheHelper::getCacheNameObjectItem($model->getUuid()));
                break;
            default:
                /* ignore the rest of events */
        }
    }

    /**
     * empty Cache
     * @param String $cacheName
     */
    public function emptyCache(String $cacheName)
    {
        $di = \Phalcon\DI::getDefault();
        $cacheManager = $di->getShared('modelsCache');
        if ($cacheManager) {
            if ($cacheManager->exists($cacheName)) {
                $result = $cacheManager->delete($cacheName);
            }
            $beanQueue = new RelodayQueue(getenv('QUEUE_CLEAN_CACHE'));
            $return = $beanQueue->addQueue([
                'action' => 'emptyCache',
                'cacheName' => $cacheName,
            ]);
        }
    }
}