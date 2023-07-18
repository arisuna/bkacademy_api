<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class UserGuideTopic extends \Reloday\Application\Models\UserGuideTopicExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->hasMany('id', 'Reloday\Gms\Models\UserGuideTopic', 'user_guide_topic_id', [
            'alias' => 'UserGuideTopics',
            'params' => [
                'conditions' => '\Reloday\Gms\Models\UserGuideTopic.status = :status_active:',
                'bind' => [
                    'status_active' => self::STATUS_ACTIVE
                ]
            ]
        ]);

        $this->belongsTo('user_guide_topic_id', 'Reloday\Gms\Models\UserGuideTopic', 'id', [
            'alias' => 'UserGuideTopic',
            'reusable' => true,
            'cache' => [
                'key' => 'USER_GUIDE_TOPIC_' . $this->getUserGuideTopicId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
    }
}
