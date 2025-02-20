<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class UserSettingDefault extends \SMXD\Application\Models\UserSettingDefaultExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

    /**
     *
     */
	public function initialize(){
		parent::initialize();
        $this->hasMany('id', 'SMXD\Api\Models\UserSetting', 'user_setting_default_id', [
            'alias' => 'UserSetting'
        ]);
	}

    /**
     * @param int $user_id
     * @return string
     */
    public function getValue($user_id = 0)
    {
        if ($user_id == 0) $user_id = ModuleModel::$user->getId();
        $companySettingApp = $this->getUserSetting([
            'conditions' => 'user_id = :user_id:',
            'bind' => [
                'user_id' => $user_id
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
    public function toArray($columns = NULL): array
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
