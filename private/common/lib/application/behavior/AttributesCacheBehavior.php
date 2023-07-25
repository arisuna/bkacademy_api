<?php

namespace SMXD\Application\Behavior;

use Phalcon\Mvc\Model\Behavior;
use Phalcon\Mvc\Model\BehaviorInterface;
use SMXD\Application\Lib\CacheHelper;

class AttributesCacheBehavior extends Behavior implements BehaviorInterface
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
                $this->emptyCache(CacheHelper::__getCacheNameAttributeValue($model->getUuid()));
                break;
            default:
                /* ignore the rest of events */
        }
    }

    /**
     * @param $key
     *
     */
    public function emptyCache($cacheName)
    {
        $di = \Phalcon\DI::getDefault();
        $cacheManager = $di->getShared('modelsCache');
        if ($cacheManager) {
            if ($cacheManager->exists($cacheName)) {
                $cacheManager->delete($cacheName);
            }
        }
    }
}