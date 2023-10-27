<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ProductRentInfo extends \SMXD\Application\Models\ProductRentInfoExt
{	

	public function initialize(){
		parent::initialize(); 

        $this->belongsTo('uuid', '\SMXD\Api\Models\Product', 'uuid', [
            'alias' => 'Product'
        ]);
	}
}
