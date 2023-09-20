<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ProductFieldInGroup extends \SMXD\Application\Models\ProductFieldInGroupExt
{	

	public function initialize(){
		parent::initialize(); 

        $this->belongsTo('product_field_id', '\SMXD\Api\Models\ProductField', 'id', [
            'alias' => 'ProductField'
        ]);
 
        $this->belongsTo('product_field_group_id', '\SMXD\Api\Models\ProductFieldGroup', 'id', [
            'alias' => 'ProductFieldGroup'
        ]);
	}
}
