<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class CommunicationTopicMessage extends \Reloday\Application\Models\CommunicationTopicMessageExt
{

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('sender_email', 'Reloday\Gms\Models\UserCommunicationEmail', 'imap_email', [
            'alias' => 'Email',
            'reusable' => true,
            'cache' => [
                'key' => 'USER_COMMUNICATION_EMAIL_' . $this->getSenderUserCommunicationEmailId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('communication_topic_id', 'Reloday\Gms\Models\CommunicationTopic', 'id', [
            'alias' => 'CommunicationTopic',
        ]);
    }
}
