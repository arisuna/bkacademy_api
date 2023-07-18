<?php

namespace Reloday\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Api\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class CommunicationTopicFollower extends \Reloday\Application\Models\CommunicationTopicFollowerExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}
}
