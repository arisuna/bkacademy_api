<?php

namespace SMXD\App\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ProductFieldValue extends \SMXD\Application\Models\ProductFieldValueExt
{	

	public function initialize(){
		parent::initialize(); 

        $this->belongsTo('product_id', '\SMXD\App\Models\Product', 'id', [
            'alias' => 'Product'
        ]);

        $this->belongsTo('product_field_id', '\SMXD\App\Models\ProductField', 'id', [
            'alias' => 'ProductField'
        ]);
 
        $this->belongsTo('product_field_group_id', '\SMXD\App\Models\ProductFieldGroup', 'id', [
            'alias' => 'ProductFieldGroup'
        ]);
	}
}
