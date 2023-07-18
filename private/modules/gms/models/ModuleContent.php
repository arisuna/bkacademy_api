<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class ModuleContent extends \Reloday\Application\Models\ModuleContentExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}

    /**
     * @return array|mixed
     */
    public static function __getPremiumContents()
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ModuleContent', 'ModuleContent');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Module', 'ModuleContent.module_id = Module.id', 'Module');
        $queryBuilder->where('Module.is_active = :is_active_yes:', [
            'is_active_yes' => ModelHelper::YES
        ]);
        $queryBuilder->andWhere('Module.is_premium = :is_premium_yes:', [
            'is_premium_yes' => ModelHelper::YES
        ]);
        $queryBuilder->andWhere('ModuleContent.is_on_modalbox = :is_on_modalbox_yes:', [
            'is_on_modalbox_yes' => ModelHelper::YES
        ]);
        $queryBuilder->andWhere('Module.company_type_id = :company_type_id:', [
            'company_type_id' => ModuleModel::$company->getCompanyTypeId()
        ]);
        $queryBuilder->andWhere('ModuleContent.language = :language:', [
            'language' => ModuleModel::$language != '' ? ModuleModel::$language : SupportedLanguageExt::LANG_EN
        ]);
        try {
            return $queryBuilder->getQuery()->execute();
        } catch (\Exception $e) {
            return [];
        }
    }
}
