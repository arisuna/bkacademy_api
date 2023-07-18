<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class OldCommunicationTopicRead extends \Reloday\Application\Models\OldCommunicationTopicReadExt
{

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $topic_id
     * @param $user_profile_id
     * @return \Phalcon\Mvc\Model\ResultInterface|\Reloday\Application\Models\CommunicationTopicRead
     */
    public static function findTopicUser($topic_id, $user_profile_id)
    {
        /**
         * find topic user
         */
        return self::findFirst([
            'conditions' => 'topic_id = :topic_id: AND user_profile_id = :user_profile_id:',
            'bind' => [
                'topic_id' => $topic_id,
                'user_profile_id' => $user_profile_id,
            ]
        ]);
    }

}
