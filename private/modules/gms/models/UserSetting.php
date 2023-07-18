<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class UserSetting extends \Reloday\Application\Models\UserSettingExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

    const TYPE_COUNTRY = 9;
    const TYPE_SURFACE_UNIT = 10;
    const TYPE_RENT_PERIOD = 11;
    const TYPE_CURRENCY = 12;

    const PROPERTY_SETTINGS = [self::TYPE_COUNTRY, self::TYPE_SURFACE_UNIT, self::TYPE_RENT_PERIOD, self::TYPE_CURRENCY];

    const TYPE_COUNTRY_NAME = "property_country_id";
    const TYPE_SURFACE_UNIT_NAME = "surface_unit";
    const TYPE_RENT_PERIOD_NAME = "rent_period";
    const TYPE_CURRENCY_NAME = "rent_currency";

	public function initialize(){
		parent::initialize(); 
	}
}
