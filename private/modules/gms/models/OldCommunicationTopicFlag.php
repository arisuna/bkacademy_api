<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class OldCommunicationTopicFlag extends \Reloday\Application\Models\OldCommunicationTopicFlagExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}

    /**
     * @param $topic_uuid
     * @param $user_profile_id
     * @return \Phalcon\Mvc\Model\ResultInterface|\Reloday\Application\Models\CommunicationTopicRead
     */
    public static function findTopicUser($topic_id, $user_profile_id)
    {
        return self::findFirst([
            'conditions' => 'topic_id = :topic_id: AND user_profile_id = :user_profile_id:',
            'bind' => [
                'topic_id' => $topic_id,
                'user_profile_id' => $user_profile_id,
            ]
        ]);
    }
}
