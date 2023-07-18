<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class UserSettingDefault extends \Reloday\Application\Models\UserSettingDefaultExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

    /**
     *
     */
	public function initialize(){
		parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\UserSetting', 'user_setting_default_id', [
            'alias' => 'UserSetting'
        ]);
	}

    /**
     * @param int $user_profile_id
     * @return string
     */
    public function getValue($user_profile_id = 0)
    {
        if ($user_profile_id == 0) $user_profile_id = ModuleModel::$user_profile->getId();
        $companySettingApp = $this->getUserSetting([
            'conditions' => 'user_profile_id = :user_profile_id:',
            'bind' => [
                'user_profile_id' => $user_profile_id
            ],
            'limit' => 1
        ]);
        if ($companySettingApp && $companySettingApp->count() > 0) {
            return $companySettingApp->getFirst()->getValue();
        }
    }

    /**
     * @return mixed
     */
    public function toArray($columns = NULL)
    {
        $array = parent::toArray($columns);
        $metadata = $this->getDI()->get('modelsMetadata');
        $types = $metadata->getDataTypes($this);
        foreach ($types as $attribute => $type) {
            $array[$attribute] = ModelHelper::__getAttributeValue($type, $array[$attribute]);
        }
        return $array;
    }

}
