<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class AppSettingGroupDefault extends \Reloday\Application\Models\AppSettingGroupDefaultExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->hasMany('id', 'Reloday\Gms\Models\AppSettingDefault', 'app_setting_group_default_id', [
            'alias' => 'AppSettingDefault'
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\AppSettingDefault',
            'app_setting_group_default_id', 'id',
            'Reloday\Gms\Models\AppSetting', 'app_setting_default_id', [
                'alias' => 'AppSetting'
            ]);
    }

    /**
     * @param $app_id
     */
    public function getAppSettingByApp($app_id)
    {
        return self::getAppSetting([
            'conditions' => 'app_id = :app_id:',
            'bind' => [
                'app_id' => $app_id
            ]
        ]);
    }

    /**
     *
     */
    public function getAppSettingOfCurrentApp()
    {
        $appSettingDefaultList = $this->getAppSettingDefault();
        $array = [];
        foreach ($appSettingDefaultList as $settingDefault) {
            $array[$settingDefault->getName()] = $settingDefault->toArray();
            $array[$settingDefault->getName()]['value'] = $settingDefault->getValue();
        }
        return array_values($array);
    }
}
