<?php

namespace SMXD\App\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ProductField extends \SMXD\Application\Models\ProductFieldExt
{	

	public function initialize(){
		parent::initialize(); 

        $this->belongsTo('attribute_id', '\SMXD\App\Models\Attributes', 'id', [
            'alias' => 'Attribute'
        ]);
	}

    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options, $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\ProductField', 'ProductField');
        $queryBuilder->leftJoin('\SMXD\App\Models\Attributes', 'ProductField.attribute_id = Attribute.id', 'Attribute');
        $queryBuilder->leftJoin('\SMXD\App\Models\ProductFieldInGroup', 'ProductField.id = ProductFieldInGroup.product_field_id', 'ProductFieldInGroup');
        $queryBuilder->leftJoin('\SMXD\App\Models\ProductFieldGroup', 'ProductFieldInGroup.product_field_group_id = ProductFieldGroup.id', 'ProductFieldGroup');
        $queryBuilder->leftJoin('\SMXD\App\Models\ProductFieldGroupInCategory', 'ProductFieldGroup.id = ProductFieldGroupInCategory.product_field_group_id', 'ProductFieldGroupInCategory');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('ProductField.id');

        $queryBuilder->columns([
            'ProductField.id',
            'ProductField.uuid',
            'ProductField.name',
            'ProductField.label',
            'ProductField.type',
            'attribute_name' => 'Attribute.name',
            'ProductField.created_at',
            'ProductField.updated_at',
        ]);
        $queryBuilder->where("ProductField.is_deleted <> 1");

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("ProductField.name LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        if (isset($options['groups']) && count($options["groups"]) > 0) {
            $queryBuilder->andwhere('ProductFieldGroup.id IN ({groups:array})', [
                'groups' => $options["groups"]
            ]);
        }

        if (isset($options['types']) && count($options["types"]) > 0) {
            $queryBuilder->andwhere('ProductField.type IN ({types:array})', [
                'types' => $options["types"]
            ]);
        }

        if (isset($options['categories']) && count($options["categories"]) > 0) {
            $queryBuilder->andwhere('ProductFieldGroupInCategory.category_id IN ({categories:array})', [
                'categories' => $options["categories"]
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
        $queryBuilder->orderBy('ProductField.id DESC');
        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ProductField.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['ProductField.created_at DESC']);
                }
            }
            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ProductField.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ProductField.name DESC']);
                }
            }
            if ($order['field'] == "label") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ProductField.label ASC']);
                } else {
                    $queryBuilder->orderBy(['ProductField.label DESC']);
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
                'sql' => $queryBuilder->getQuery()->getSql(),
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
        $item = $this->toArray();
        return $item;
    }
}
