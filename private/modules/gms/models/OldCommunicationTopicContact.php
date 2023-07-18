<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class OldCommunicationTopicContact extends \Reloday\Application\Models\OldCommunicationTopicContactExt
{

    /**
     *
     */
	public function initialize(){
		parent::initialize();

        $this->belongsTo('contact_id', 'Reloday\Gms\Models\Contact', 'id', [
            'alias' => 'Contact',
            'reusable' => true
        ]);
	}
}
