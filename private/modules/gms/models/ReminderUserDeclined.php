<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;


class ReminderUserDeclined extends \Reloday\Application\Models\ReminderUserDeclinedExt
{

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
        $this->belongsTo('user_profile_uuid', 'Reloday\Gms\Models\UserProfile', 'uuid', [
            'alias' => 'UserProfile'
        ]);

        $this->belongsTo('reminder_config_id', 'Reloday\Gms\Models\ReminderConfig', 'id', [
            'alias' => 'ReminderConfig'
        ]);
    }
}
