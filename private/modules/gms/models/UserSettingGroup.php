<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class UserSettingGroup extends \Reloday\Application\Models\UserSettingGroupExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();

        $this->hasMany('id', 'Reloday\Gms\Models\UserSettingDefault', 'user_setting_group_id', [
            'alias' => 'CompanySettingDefault',
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\UserSettingDefault',
            'user_setting_group_id', 'id',
            'Reloday\Gms\Models\UserSetting', 'user_setting_default_id', [
                'alias' => 'UserSetting'
            ]);


        $this->hasMany('id', 'Reloday\Gms\Models\UserSettingDefault', 'user_setting_group_id', [
            'alias' => 'UserSettingDefault',
        ]);
    }


    /**
     * @param $app_id
     */
    public function getCurrentUserSetting()
    {
        return $this->getCompanySetting([
            'conditions' => 'user_profile_id = :user_profile_id:',
            'bind' => [
                'user_profile_id' => ModuleModel::$user_profile->getId()
            ]
        ]);
    }


    /**
     *
     */
    public function getSettingList()
    {
        $settingList = $this->getUserSettingDefault();
        $array = [];
        foreach ($settingList as $settingItem) {
            $array[$settingItem->getName()] = $settingItem->toArray();
            $array[$settingItem->getName()]['label'] = $settingItem->getLabel();
            $array[$settingItem->getName()]['field_type_id'] = intval($settingItem->getFieldTypeId());
            $array[$settingItem->getName()]['value'] = $settingItem->getValue();
        }
        return array_values($array);
    }
}
