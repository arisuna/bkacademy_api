<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class InvoiceTemplate extends \Reloday\Application\Models\InvoiceTemplateExt
{

	public function initialize(){
		parent::initialize(); 
	}

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if (!ModuleModel::$company) return false;
        if ($this->getCompanyId() == ModuleModel::$company->getId()) {
            return true;
        } else {
            return false;
        }
    }
}
