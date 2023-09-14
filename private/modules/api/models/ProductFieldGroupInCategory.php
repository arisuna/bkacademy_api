<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ProductFieldGroupInCategory extends \SMXD\Application\Models\ProductFieldGroupInCategoryExt
{	

	public function initialize(){
		parent::initialize(); 

        $this->belongsTo('category_id', '\SMXD\Api\Models\Category', 'id', [
            'alias' => 'Category'
        ]);
 
        $this->belongsTo('product_field_group_id', '\SMXD\Api\Models\ProductFieldGroup', 'id', [
            'alias' => 'ProductFieldGroup'
        ]);
	}
}
