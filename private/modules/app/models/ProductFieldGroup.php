<?php

namespace SMXD\App\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ProductFieldGroup extends \SMXD\Application\Models\ProductFieldGroupExt
{	

	public function initialize(){
		parent::initialize(); 
	}

    public function getProductFields(){
        $fields = [];
        $product_field_in_groups = ProductFieldInGroup::find([
            'conditions' => 'product_field_group_id = :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'pos ASC'
            ]);
        if(count($product_field_in_groups) > 0){
            foreach($product_field_in_groups as $product_field_in_group){
                $product_field = $product_field_in_group->getProductField();
                $fields[] = $product_field;
            }

        }
        return $fields;
        
    }

    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options, $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\ProductFieldGroup', 'ProductFieldGroup');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('ProductFieldGroup.id');

        $queryBuilder->columns([
            'ProductFieldGroup.id',
            'ProductFieldGroup.uuid',
            'ProductFieldGroup.name',
            'ProductFieldGroup.label',
            'ProductFieldGroup.status',
            'ProductFieldGroup.created_at',
            'ProductFieldGroup.updated_at',
        ]);
        $queryBuilder->where("ProductFieldGroup.is_deleted <> 1");

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("ProductFieldGroup.name LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
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
        $queryBuilder->orderBy('ProductFieldGroup.id DESC');

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ProductFieldGroup.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['ProductFieldGroup.created_at DESC']);
                }
            }
            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ProductFieldGroup.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ProductFieldGroup.name DESC']);
                }
            }
            if ($order['field'] == "label") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ProductFieldGroup.label ASC']);
                } else {
                    $queryBuilder->orderBy(['ProductFieldGroup.label DESC']);
                }
            }
        }


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
                'total_pages' => $pagination->total_pages,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
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
        $item = $this->toArray();
        return $item;
    }
}
