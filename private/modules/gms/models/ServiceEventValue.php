<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class ServiceEventValue extends \Reloday\Application\Models\ServiceEventValueExt
{
    /**
     *
     */
	public function initialize(){
		parent::initialize(); 
	}
}
