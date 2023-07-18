<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class Plan extends \Reloday\Application\Models\PlanExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();

        $this->hasMany('id', 'Reloday\Gms\Models\PlanModule', 'plan_id', [
            'alias' => 'PlanModules'
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\PlanModuleLimit', 'plan_id', [
            'alias' => 'PlanModuleLimits'
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\PlanContent', 'plan_id', [
            'alias' => 'PlanContents'
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\PlanModule', 'plan_id', 'module_id', 'Reloday\Gms\Models\Module', 'id', [
            'alias' => 'Modules',
            'params' => [
                'conditions' => 'Reloday\Gms\Models\Module.is_deleted = :is_deleted_no: AND Reloday\Gms\Models\Module.is_active = :is_active_yes: AND Reloday\Gms\Models\Module.company_type_id = :company_type_id_hr: ',
                'bind' => [
                    'is_deleted_no' => ModelHelper::NO,
                    'is_active_yes' => ModelHelper::YES,
                    'company_type_id_hr' => CompanyType::TYPE_GMS
                ]
            ]
        ]);
    }

    /**
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\Plan|\Reloday\Application\Models\Plan[]
     */
    public static function __getList()
    {
        $companyCreatedTime = Helpers::__convertDateToSecond(ModuleModel::$company->getCreatedAt());
        $companyIsLegacy = ModuleModel::$company->isLegacy();
        $legacyPlan = ModuleModel::$company->getPlan();
        if ($companyIsLegacy) {
            return self::find([
                "conditions" => "company_type_id = :company_type_id: AND status = :is_active_yes:  AND ( id = :plan_id: OR ( is_legacy = 0  AND is_display = :is_display_yes: AND is_legacy_client_visible = 1) )",
                "bind" => [
                    "company_type_id" => ModuleModel::$company->getCompanyTypeId(),
                    "plan_id" => $legacyPlan->getId(),
                    "is_display_yes" => ModelHelper::YES,
                    "is_active_yes" => self::STATUS_ACTIVE
                ]
            ]);
        } else {
            return self::find([
                "conditions" => "company_type_id = :company_type_id: AND status = :is_active_yes:  AND is_legacy = 0  AND is_display = :is_display_yes:",
                "bind" => [
                    "company_type_id" => ModuleModel::$company->getCompanyTypeId(),
                    "is_display_yes" => ModelHelper::YES,
                    "is_active_yes" => self::STATUS_ACTIVE
                ]
            ]);
        }
    }

    /**
     * @return mixed
     */
    public function getParsedData()
    {
        $currentPlanData = $this->toArray();
        $currentPlanData["content"] = [];
        $plan_contents = $this->getPlanContents();
        if (count($plan_contents) > 0) {
            foreach ($plan_contents as $plan_content) {
                $currentPlanData["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                $currentPlanData["content"][$plan_content->getLanguage()]["features"] = $plan_content->getDecodeFeatures();
            }
        }
        $currentPlanData['modules'] = $this->getModules();
        return $currentPlanData;
    }
}
