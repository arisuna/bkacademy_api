<?php

namespace SMXD\Api\Models;

use Phalcon\Helper\Number;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Product extends \SMXD\Application\Models\ProductExt
{

    public function initialize()
    {
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

        $this->belongsTo('uuid', '\SMXD\Api\Models\ProductSaleInfo', 'uuid', [
            'alias' => 'ProductSaleInfo'
        ]);

        $this->belongsTo('uuid', '\SMXD\Api\Models\ProductRentInfo', 'uuid', [
            'alias' => 'ProductRentInfo'
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
        $queryBuilder->addFrom('\SMXD\Api\Models\Product', 'Product');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Brand', 'Product.brand_id = Brand.id', 'Brand');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Model', 'Product.model_id = Model.id', 'Model');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Company', 'Product.creator_company_id = Company.id', 'Company');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Category', 'Product.main_category_id = MainCategory.id', 'MainCategory');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Category', 'Product.secondary_category_id = SecondaryCategory.id', 'SecondaryCategory');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Address', 'Product.current_address_id = Address.id', 'Address');
        $queryBuilder->leftJoin('\SMXD\Api\Models\ProductRentInfo', 'Product.uuid = ProductRentInfo.uuid', 'ProductRentInfo');
        $queryBuilder->leftJoin('\SMXD\Api\Models\ProductSaleInfo', 'Product.uuid = ProductSaleInfo.uuid', 'ProductSaleInfo');
        $queryBuilder->leftJoin('\SMXD\Api\Models\FavouriteProduct', 'Product.id = FavouriteProduct.product_id', 'FavouriteProduct');
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
            'Product.product_type_id',
            'sale_info_price' => 'ProductSaleInfo.price',
            'sale_info_quantity' => 'ProductSaleInfo.quantity',
            'sale_info_currency' => 'ProductSaleInfo.currency',
            'rent_info_price' => 'ProductSaleInfo.price',
            'rent_info_quantity' => 'ProductSaleInfo.quantity',
            'rent_info_currency' => 'ProductSaleInfo.currency',
            'company_name' => 'Company.name',
            'brand_name' => 'Brand.name',
            'model_name' => 'Model.name',
            'main_category_name' => 'MainCategory.name',
            'sub_category_name' => 'SecondaryCategory.name',
            'address_name' => 'Address.name',
            'Product.created_at',
            'Product.updated_at',
        ]);
        $queryBuilder->where("Product.is_deleted <> 1");

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Product.name LIKE :search: OR Model.name LIKE :search: OR Address.name LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        if (isset($options['brand_ids']) && count($options["brand_ids"]) > 0) {
            $queryBuilder->andwhere('Product.brand_id IN ({brand_ids:array})', [
                'brand_ids' => $options["brand_ids"]
            ]);
        }

        if (isset($options['location_ids']) && count($options["location_ids"]) > 0) {
            $queryBuilder->andwhere('Address.vn_province_id IN ({location_ids:array})', [
                'location_ids' => $options["location_ids"]
            ]);
        }

        if (isset($options['main_category_id']) && $options['main_category_id'] > 0) {
            $queryBuilder->andwhere('Product.main_category_id = :main_category_id:', [
                'main_category_id' => $options["main_category_id"]
            ]);
        }

        if (isset($options['brand_id']) && $options['brand_id'] > 0) {
            $queryBuilder->andwhere('Product.brand_id = :brand_id:', [
                'brand_id' => $options["brand_id"]
            ]);
        }

        if (isset($options['end_user_id']) && $options['end_user_id'] > 0) {
            $queryBuilder->andwhere('FavouriteProduct.end_user_id = :end_user_id:', [
                'end_user_id' => $options["end_user_id"]
            ]);
        }

        if (isset($options['secondary_category_id']) && $options['secondary_category_id'] > 0) {
            $queryBuilder->andwhere('Product.secondary_category_id = :secondary_category_id:', [
                'secondary_category_id' => $options["secondary_category_id"]
            ]);
        }

        if (isset($options['category_ids']) && count($options["category_ids"]) > 0) {
            $queryBuilder->andwhere('Product.main_category_id IN ({category_ids:array}) OR Product.secondary_category_id IN ({category_ids:array})', [
                'category_ids' => $options["category_ids"]
            ]);
        }

        if (isset($options['years']) && count($options["years"]) > 0) {
            $queryBuilder->andwhere('Product.year IN ({years:array})', [
                'years' => $options["years"]
            ]);
        }

        if (isset($options["year_min"]) && $options["year_min"] > 0 && isset($options["year_max"]) && $options["year_max"] > 0) {
            $queryBuilder->andwhere('Product.year >= :year_min: and Product.year <= :year_max:', [
                'year_min' => $options["year_min"],
                'year_max' => $options["year_max"],
            ]);
        }

        if (isset($options['model_ids']) && count($options["model_ids"]) > 0) {
            $queryBuilder->andwhere('Product.model_id IN ({model_ids:array})', [
                'model_ids' => $options["model_ids"]
            ]);
        }

        if (isset($options['is_sale']) && ($options["is_sale"] === 1 || $options["is_sale"] === 0)) {
            $queryBuilder->andwhere('ProductSaleInfo.status = :is_sale:', [
                'is_sale' => $options["is_sale"]
            ]);
        }

        if (isset($options['is_rent']) && ($options["is_rent"] === 1 || $options["is_rent"] === 0)) {
            $queryBuilder->andwhere('ProductRentInfo.status = :is_rent:', [
                'is_rent' => $options["is_rent"]
            ]);
        }

        if (isset($options['type']) && $options['type'] == 2 && isset($options["price_min"])) {
            $queryBuilder->andwhere('ProductRentInfo.status = 1 and ProductRentInfo.price >= :price_min: and ProductRentInfo.price <= :price_max:', [
                'price_min' => $options["price_min"] ?: 0,
                'price_max' => $options["price_max"] ?: 100000000000,
            ]);
        } elseif (isset($options['type']) && isset($options["price_min"])) {
            $queryBuilder->andwhere('ProductSaleInfo.status = 1 and ProductSaleInfo.price >= :price_min: and ProductSaleInfo.price <= :price_max:', [
                'price_min' => $options["price_min"] ?: 0,
                'price_max' => $options["price_max"] ?: 100000000000,
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

            if ($order['field'] == "year") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Product.year ASC']);
                } else {
                    $queryBuilder->orderBy(['Product.year DESC']);
                }
            }

            if ($order['field'] == "price") {
                if(isset($options['type']) && $options['type'] == 1){
                    if ($order['order'] == "asc") {
                        $queryBuilder->orderBy(['ProductSaleInfo.price ASC']);
                    } else {
                        $queryBuilder->orderBy(['ProductSaleInfo.price DESC']);
                    }
                }
            }

            if (isset($options['type']) && $options['type'] == 1) {
                $queryBuilder->orderBy(['sale_info_quantity ASC']);
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
                    $itemArr = $item->toArray();
                    $dataArr[] = $itemArr;
                }
            }

            return [
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

    public function parsedDataToArray()
    {

        $data_array = $this->toArray();

        $category = $this->getSecondaryCategory();
        $data_array['product_sale_info'] = $this->getProductSaleInfo() ? $this->getProductSaleInfo()->toArray() : null;
        $data_array['product_rent_info'] = $this->getProductRentInfo() ? $this->getProductRentInfo()->toArray() : null;
        $data_array['product_field_groups'] = [];
        $data_array['brand_name'] = '';
        $data_array['address_name'] = '';
        $media = MediaAttachment::__getImageByObjUuidAndIsThumb($this->getUuid(), MediaAttachment::IS_THUMB_YES);

        $data_array['url_thumb'] = $media ? $media->getTemporaryThumbS3Url() : null;
        $brand = $this->getBrand();
        if (isset($brand) && $brand instanceof Brand) {
            $data_array['brand_name'] = $brand->getName();
        }

        $data_array['is_favourite'] = false;
        if (ModuleModel::$user && ModuleModel::$user->getId()){
            $favourite = FavouriteProduct::findFirst([
                'conditions' => 'product_id = :product_id: and end_user_id = :end_user_id:',
                'bind' => [
                    'product_id' => $data_array['id'],
                    'end_user_id' => ModuleModel::$user->getId(),
                ]
            ]);

            $data_array['is_favourite'] = $favourite instanceof FavouriteProduct;
        }

        if ($this->getCurrentAddressId()) {
            $address = $this->getCurrentAddress();
            if (isset($address) && $address instanceof Address) {
                $data_array['address_name'] = $address->getName();
            }
        }

        $product_field_groups = $category->getProductFieldGroups();

        if (count($product_field_groups) > 0) {
            foreach ($product_field_groups as $product_field_group) {
                if ($product_field_group instanceof ProductFieldGroup) {
                    $group_array = $product_field_group->toArray();
                    $group_array['fields'] = [];

                    $fields = $product_field_group->getProductFields();

                    if (count($fields) > 0) {
                        foreach ($fields as $field) {
                            if ($field instanceof ProductField) {
                                $field_array = $field->toArray();
                                $product_field_value = ProductFieldValue::findFirst([
                                    'conditions' => 'product_id = :product_id: and product_field_id = :product_field_id: and product_field_group_id = :product_field_group_id:',
                                    'bind' => [
                                        'product_id' => $this->getId(),
                                        'product_field_id' => $field->getId(),
                                        'product_field_group_id' => $product_field_group->getId()
                                    ]
                                ]);
                                if ($product_field_value) {
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
                                if ($field->getType() == ProductField::TYPE_ATTRIBUTE) {
                                    $field_array['attribute_name'] = $field->getAttribute() ? $field->getAttribute()->getCode() : '';
                                }
                                $group_array['fields'][] = $field_array;
                            }
                        }
                    }
                    $data_array['product_field_groups'][] = $group_array;
                }
            }
        }
        return $data_array;
    }
}
