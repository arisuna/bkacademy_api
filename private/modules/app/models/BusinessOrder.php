<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
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

        $this->belongsTo('product_id', '\SMXD\App\Models\Product', 'id', [
            'alias' => 'Product'
        ]);
	}

    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\BusinessOrder', 'BusinessOrder');
        $queryBuilder->leftJoin('\SMXD\App\Models\Product', 'Product.id = BusinessOrder.product_id ','Product');
        $queryBuilder->leftJoin('\SMXD\App\Models\User', 'CreatorEndUser.id = BusinessOrder.creator_end_user_id ','CreatorEndUser');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('BusinessOrder.id');

        $queryBuilder->columns([
            'BusinessOrder.id',
            'BusinessOrder.uuid',
            'BusinessOrder.number',
            'BusinessOrder.amount',
            'BusinessOrder.currency',
            'BusinessOrder.product_id',
            'product_name' => 'Product.name',
            'buyer_first_name' => 'CreatorEndUser.firstname',
            'buyer_last_name' => 'CreatorEndUser.lastname',
            'buyer_phone' => 'CreatorEndUser.phone',
            'BusinessOrder.status',

            'BusinessOrder.created_at',
            'BusinessOrder.updated_at',
        ]);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("BusinessOrder.number LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->andwhere("BusinessOrder.status IN ({statuses:array})", [
                'statuses' => $options['statuses']
            ]);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }
        $queryBuilder->orderBy('BusinessOrder.id DESC');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $dataArr = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $dataArr[] = $item;
                }
            }

            return [
                //'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'params' => $options,
                'page' => $page,
                'data' => $dataArr,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    public function parsedDataToArray(){
        $item = [];
        $product = $this->getProduct();

        $item['number'] = $this->getNumber();
        $item['uuid'] = $this->getUuid();
        $item['product'] = $product->parsedDataToArray();
        $item['seller'] = $product->getCreatorUser() ? $product->getCreatorUser() : null;
        $item['buyer'] = $this->getCreatorEndUser() ? $this->getCreatorEndUser() : null;
        $item['amount'] = floatval($this->getAmount());
        $item['currency'] = $this->getCurrency();
        $item['quantity'] = $this->getQuantity();
        $item['status'] = $this->getStatus();
        $item['type'] = $this->getType();

        $item['created_at'] = strtotime($this->getCreatedAt());
        $item['updated_at'] = strtotime($this->getUpdatedAt());

        return $item;
    }
}
