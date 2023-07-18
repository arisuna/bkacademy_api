<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class TransactionType extends \Reloday\Application\Models\TransactionTypeExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}
}
