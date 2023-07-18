<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Module;

class CompanySettingGroup extends \Reloday\Application\Models\CompanySettingGroupExt
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

        $this->hasMany('id', 'Reloday\Gms\Models\CompanySettingDefault', 'company_setting_group_id', [
            'alias' => 'CompanySettingDefault'
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\CompanySettingDefault',
            'company_setting_group_id', 'id',
            'Reloday\Gms\Models\CompanySetting', 'company_setting_default_id', [
                'alias' => 'CompanySetting'
            ]);


        $this->hasMany('id', 'Reloday\Gms\Models\CompanySettingDefault', 'company_setting_group_id', [
            'alias' => 'CompanySettingDefault',
        ]);
    }

    /**
     * @param $app_id
     */
    public function getCurrentCompanySetting()
    {
        return $this->getCompanySetting([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId()
            ]
        ]);
    }


    /**
     *
     */
    public function getSettingList()
    {
        $settingList = $this->getCompanySettingDefault();
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
