<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class UserSetting extends \SMXD\Application\Models\UserSettingExt
{	
	public function initialize(){
		parent::initialize(); 
	}
}
