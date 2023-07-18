<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class Subscription extends \Reloday\Application\Models\SubscriptionExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();

        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'reusable' => true
        ]);

        $this->belongsTo('plan_id', 'Reloday\Gms\Models\Plan', 'id', [
            'alias' => 'Plan',
            'reusable' => true
        ]);
	}
}
