<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class Module extends \Reloday\Application\Models\ModuleExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\ModuleContent', 'module_id', [
            'alias' => 'Contents',
        ]);
	}

    /**
     * @param $name
     * @return mixed
     */
    public static function __findModule($name)
    {
        return self::findFirst([
            'conditions' => 'code = :code: AND company_type_id = :company_type_id:  AND is_active = 1',
            'bind' => [
                'code' => $name,
                'company_type_id' => ModuleModel::$company->getCompanyTypeId()
            ]
        ]);
    }

    /**
     * @param $language
     * @return mixed
     */
    public function getContentByLanguage($language)
    {
        $contents = $this->getContents([
            'conditions' => 'language = :language: AND is_on_modalbox  = :is_on_modalbox_no:',
            'bind' => [
                'is_on_modalbox_no' => ModelHelper::NO,
                'language' => $language
            ]
        ]);
        if ($contents->count() > 0) {
            return $contents->getFirst();
        }
        return null;
    }
}
