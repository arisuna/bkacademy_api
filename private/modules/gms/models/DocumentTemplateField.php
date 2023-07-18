<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class DocumentTemplateField extends \Reloday\Application\Models\DocumentTemplateFieldExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 

        $this->hasOne('map_field_id', 'Reloday\Gms\Models\MapField', 'id', [
            'alias' => 'MapField'
        ]);

		$this->hasOne('document_template_id', 'Reloday\Gms\Models\DocumentTemplate', 'id', [
            'alias' => 'DocumentTemplate'
        ]);

        $this->hasOne('service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', [
            'alias' => 'ServiceCompany'
        ]);
	}
}
