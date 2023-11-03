<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Http\Client\Provider\Exception;

class BusinessOrder extends \SMXD\Application\Models\BusinessOrderExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();

        $this->belongsTo('product_id', '\SMXD\Api\Models\Product', 'id', [
            'alias' => 'Product'
        ]);
	}

    public function parsedDataToArray(){
        $item = [];
        $product = $this->getProduct();

        $item['number'] = $this->getNumber();
        $item['uuid'] = $this->getUuid();
        $item['product'] = $product->parsedDataToArray();
        $item['amount'] = $this->getAmount();
        $item['currency'] = $this->getCurrency();
        $item['status'] = $this->getStatus();
        $item['type'] = $this->getType();

        $item['created_at'] = strtotime($this->getCreatedAt());
        $item['updated_at'] = strtotime($this->getUpdatedAt());

        return $item;
    }
}
