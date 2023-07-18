<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class CustomerSupportTicket extends \Reloday\Application\Models\CustomerSupportTicketExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'UserProfile',
            'cache' => [
                'key' => 'USER_PROFILE_' . $this->getUserProfileId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);
    }

    /**
     *
     */
    public function belongsToGms()
    {
        return $this->getUserProfile() && $this->getUserProfile()->belongsToGms();
    }
}
