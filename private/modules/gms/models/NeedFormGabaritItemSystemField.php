<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class NeedFormGabaritItemSystemField extends \Reloday\Application\Models\NeedFormGabaritItemSystemFieldExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}

    public static function __isMapFieldDataExisted($options = []){
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\NeedFormGabaritItemSystemField', 'NeedFormGabaritItemSystemField');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\NeedFormGabarit','NeedFormGabaritItemSystemField.need_form_gabarit_id = NeedFormGabarit.id', 'NeedFormGabarit');
        $queryBuilder->distinct(true);



        $queryBuilder->where('NeedFormGabarit.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andWhere('NeedFormGabaritItemSystemField.map_field_uuid = :map_field_uuid:', [
            'map_field_uuid' => $options['map_field_uuid']
        ]);


        $queryBuilder->orderBy("NeedFormGabaritItemSystemField.created_at ASC");
        $queryBuilder->groupBy('NeedFormGabaritItemSystemField.id');

        $data = $queryBuilder->getQuery()->execute();

        if(count($data) > 0){
            return true;
        }else{
            return false;
        }
    }
}
