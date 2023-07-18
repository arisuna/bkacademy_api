<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class CommunicationTopicContact extends \Reloday\Application\Models\CommunicationTopicContactExt
{

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('contact_id', 'Reloday\Gms\Models\Contact', 'id', [
            'alias' => 'Contact',
            'cache' => [
                'key' => 'CONTACT_' . $this->getContactId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
            'reusable' => true
        ]);
    }
}
