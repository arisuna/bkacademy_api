<?php

namespace SMXD\Api\models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Product extends \SMXD\Application\Models\ProductExt
{	

	public function initialize(){
		parent::initialize(); 

        $this->belongsTo('main_category_id', '\SMXD\Api\Models\Category', 'id', [
            'alias' => 'MainCategory'
        ]);
 
        $this->belongsTo('secondary_category_id', '\SMXD\Api\Models\Category', 'id', [
            'alias' => 'SecondaryCategory'
        ]);
 
        $this->belongsTo('current_address_id', '\SMXD\Api\Models\Address', 'id', [
            'alias' => 'CurrentAddress'
        ]);
 
        $this->belongsTo('brand_id', '\SMXD\Api\Models\Brand', 'id', [
            'alias' => 'Brand'
        ]);
 
        $this->belongsTo('model_id', '\SMXD\Api\Models\Model', 'id', [
            'alias' => 'Model'
        ]);
 
        $this->belongsTo('creator_end_user_id', '\SMXD\Api\Models\User', 'id', [
            'alias' => 'CreatorUser'
        ]);
 
        $this->belongsTo('creator_company_id', '\SMXD\Api\Models\Company', 'id', [
            'alias' => 'CreatorCompany'
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
        $queryBuilder->addFrom('\SMXD\Api\models\Product', 'Product');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Brand', 'Product.brand_id = Brand.id', 'Brand');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Model', 'Product.model_id = Model.id', 'Model');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Category', 'Product.main_category_id = MainCategory.id', 'MainCategory');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Address', 'Product.current_address_id = Address.id', 'Address');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Product.id');

        $queryBuilder->columns([
            'Product.id',
            'Product.uuid',
            'Product.name',
            'Product.usage',
            'Product.year',
            'Product.vehicle_id',
            'Product.status',
            'brand_name' => 'Brand.name',
            'model_name' => 'Model.name',
            'main_category_name' => 'MainCategory.name',
            'address_name' => 'Address.name',
            'Product.created_at',
            'Product.updated_at',
        ]);
        $queryBuilder->where("Product.is_deleted <> 1");

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Product.name LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        if (isset($options['brand_ids']) && count($options["brand_ids"]) > 0) {
            $queryBuilder->andwhere('Product.brand_id IN ({brand_ids:array})', [
                'brand_ids' => $options["brand_ids"]
            ]);
        }

        if (isset($options['model_ids']) && count($options["model_ids"]) > 0) {
            $queryBuilder->andwhere('Product.model_id IN ({model_ids:array})', [
                'model_ids' => $options["model_ids"]
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
        $queryBuilder->orderBy('Product.id DESC');
        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Product.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Product.created_at DESC']);
                }
            }
            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Product.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Product.name DESC']);
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
        $data_array = $this->toArray();
        $category = $this->getSecondaryCategory();
        $data_array['product_field_groups'] = [];
        $product_field_groups = $category->getProductFieldGroups();
        if(count($product_field_groups) > 0){
            foreach($product_field_groups as $product_field_group){
                $group_array = $product_field_group->toArray();
                $group_array['fields'] = [];
                $fields = $product_field_group->getProductFields();
                if(count($fields) > 0){
                    foreach($fields as $field){
                        if($field instanceof ProductField){
                            $field_array = $field->toArray();
                            $product_field_value = ProductFieldValue::findFirst([
                                'conditions' => 'product_id = :product_id: and product_field_id = :product_field_id: and product_field_group_id = :product_field_group_id:',
                                'bind' => [
                                    'product_id'=> $this->getId(),
                                    'product_field_id' => $field->getId(),
                                    'product_field_group_id' => $product_field_group->getId()
                                ]
                                ]);
                            if($product_field_value){
                                $field_array['value'] = $product_field_value->getValue();
                                $field_array['is_custom'] = $product_field_value->getIsCustom();
                                $field_array['product_field_name'] = $product_field_value->getProductFieldName();
                                $field_array['product_field_value_id'] = $product_field_value->getId();
                            } else {
                                $field_array['value'] = null;
                                $field_array['is_custom'] = null;
                                $field_array['product_field_name'] = null;
                                $field_array['product_field_value_id'] = null;
                            }
                            if($field->getType() == ProductField::TYPE_ATTRIBUTE){
                                $field_array['attribute_name'] = $field->getAttribute() ? $field->getAttribute()->getCode() : '';
                            }
                            $group_array['fields'][] = $field_array;
                        }
                    }
                }
                $data_array['product_field_groups'][] = $group_array;
            }
        }
        return $data_array;
    }
}
