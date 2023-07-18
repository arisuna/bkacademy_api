<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class Timezone extends \Reloday\Application\Models\TimezoneExt
{
	public function initialize(){
		parent::initialize(); 
	}
}
