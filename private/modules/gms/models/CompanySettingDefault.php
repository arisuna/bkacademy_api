<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class CompanySettingDefault extends \Reloday\Application\Models\CompanySettingDefaultExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const RECURRING_EXPENSE_OLD = 13;
    const RECURRING_EXPENSE_TIME = 15;
    const EXPENSE_APPROVAL_REQUIRE = 14;

    const EXPENSE_SETTINGS = [
        self::RECURRING_EXPENSE_OLD,
        self::EXPENSE_APPROVAL_REQUIRE,
        self::RECURRING_EXPENSE_TIME,
    ];


    const NAME_RECURRING_EXPENSE_OLD = 'recurring_expense';
    const NAME_RECURRING_EXPENSE_TIME = 'recurring_expense_time';
    const NAME_EXPENSE_APPROVAL_REQUIRE = 'expense_approval_required';



    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\CompanySetting', 'company_setting_default_id', [
            'alias' => 'CompanySetting'
        ]);
    }

    /**
     * @param int $app_id
     * @return mixed
     */
    public function getValue($company_id = 0)
    {
        if ($company_id == 0) $company_id = ModuleModel::$company->getId();
        $companySettingApp = $this->getCompanySetting([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => $company_id
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
