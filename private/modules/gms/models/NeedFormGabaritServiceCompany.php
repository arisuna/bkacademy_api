<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ServiceCompany;

class NeedFormGabaritServiceCompany extends \Reloday\Application\Models\NeedFormGabaritServiceCompanyExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();
        $this->belongsTo('need_form_gabarit_id', 'Reloday\Application\Models\NeedFormGabarit', 'id', ['alias' => 'NeedFormGabarit']);
        $this->belongsTo('service_company_id', 'Reloday\Application\Models\ServiceCompany', 'id', ['alias' => 'ServiceCompany']);
	}

	public static function getByNeedFormGabarit($id){
        $need_form_gabarit_services = NeedFormGabaritServiceCompany::find([
            'conditions' => 'need_form_gabarit_id = :need_form_gabarit_id:',
            'bind' => [
                'need_form_gabarit_id' => $id
            ]
        ]);
        $need_form_gabarit_services_array = [];
        if ($need_form_gabarit_services->count() > 0) {
            foreach ($need_form_gabarit_services as $item) {
                $need_form_gabarit_services_array[$item->getId()] = ServiceCompany::findFirstById($item->getServiceCompanyId());
            }
        }
        return ($need_form_gabarit_services_array);
    }
}
