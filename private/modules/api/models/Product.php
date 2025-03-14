<?php

namespace SMXD\Api\Models;

use Phalcon\Helper\Number;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use SMXD\Application\Models\MediaAttachmentExt;

const LIMIT_PER_PAGE = 12;

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

        $this->belongsTo('description_id', '\SMXD\Api\Models\BasicContent', 'id', [
            'alias' => 'BasicContent'
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
            'Product.description_id',
            'Product.brand_id',
            'sale_info_price' => 'ProductSaleInfo.price',
            'sale_info_quantity' => 'ProductSaleInfo.quantity',
            'sale_info_currency' => 'ProductSaleInfo.currency',
            'rent_info_price' => 'ProductSaleInfo.price',
            'rent_info_quantity' => 'ProductSaleInfo.quantity',
            'rent_info_currency' => 'ProductSaleInfo.currency',
            'company_name' => 'Company.name',
            'brand_name' => 'Brand.name',
            'model_name' => 'Model.name',
            'main_category_name' => 'MainCategory.label',
            'sub_category_name' => 'SecondaryCategory.label',
            'address_name' => 'Address.name',
            'warehouse_address1' => 'Address.address1',
            'warehouse_ward_name' => 'Address.ward_name',
            'warehouse_district_name' => 'Address.district_name',
            'warehouse_province_name' => 'Address.province_name',
            'warehouse_country' => 'Address.country',
            'Product.created_at',
            'Product.updated_at',
        ]);
        $queryBuilder->where("Product.is_deleted <> 1");

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Product.name LIKE :search: OR Model.name LIKE :search: OR Address.name LIKE :search: OR Model.name LIKE :search:", [
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

        if (isset($options['status'])) {
            $queryBuilder->andwhere('Product.status = :status:', [
                'status' => $options["status"]
            ]);
        }

        if (isset($options['end_user_id']) && $options['end_user_id'] > 0) {
            $queryBuilder->andwhere('FavouriteProduct.end_user_id = :end_user_id:', [
                'end_user_id' => $options["end_user_id"]
            ]);
        }

        if (isset($options['creator_company_id']) && $options['creator_company_id'] > 0) {
            $queryBuilder->andwhere('Product.creator_company_id = :creator_company_id:', [
                'creator_company_id' => $options["creator_company_id"]
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

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : LIMIT_PER_PAGE;
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
            if (isset($options['type']) && $options['type'] == 1) {
                $queryBuilder->orderBy(['sale_info_quantity ASC']);
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
                    $queryBuilder->orderBy(['Product.year DESC']);
                } else {
                    $queryBuilder->orderBy(['Product.year ASC']);
                }
            }

            if ($order['field'] == "price") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ProductSaleInfo.price ASC']);
                } else {
                    $queryBuilder->orderBy(['ProductSaleInfo.price DESC']);
                }
            }

        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->paginate();

            $dataArr = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $itemArr = $item->toArray();
                    $image = MediaAttachment::__getImageByObjUuidAndIsThumb($itemArr['uuid'], MediaAttachmentExt::IS_THUMB_YES);
                    $itemArr['url_thumb'] = '';
                    if ($image) {
                        $itemArr['url_thumb'] = $image->getTemporaryThumbS3Url();
                    }

                    $itemArr['is_favourite'] = false;

                    if (isset(ModuleModel::$user) && ModuleModel::$user && ModuleModel::$user->getId()) {
                        $favourite = FavouriteProduct::findFirst([
                            'conditions' => 'product_id = :product_id: and end_user_id = :end_user_id:',
                            'bind' => [
                                'product_id' => $itemArr['id'],
                                'end_user_id' => ModuleModel::$user->getId(),
                            ]
                        ]);

                        $itemArr['is_favourite'] = $favourite instanceof FavouriteProduct;
                    }

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
        } catch
        (\PDOException $e) {
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
        $data_array['warehouse_address1'] = '';
        $data_array['warehouse_ward_name'] = '';
        $data_array['warehouse_district_name'] = '';
        $data_array['warehouse_province_name'] = '';
        $data_array['warehouse_country'] = '';
        $data_array['description'] = $this->getBasicContent() ? $this->getBasicContent()->getDescription() : '';

        $media = MediaAttachment::__getImageByObjUuidAndIsThumb($this->getUuid(), MediaAttachment::IS_THUMB_YES);

        $data_array['url_thumb'] = $media ? $media->getTemporaryThumbS3Url() : null;
        $brand = $this->getBrand();
        if (isset($brand) && $brand instanceof Brand) {
            $data_array['brand_name'] = $brand->getName();
        }

        $data_array['is_favourite'] = false;
        if (ModuleModel::$user && ModuleModel::$user->getId()) {
            $favourite = FavouriteProduct::findFirst([
                'conditions' => 'product_id = :product_id: and end_user_id = :end_user_id:',
                'bind' => [
                    'product_id' => $data_array['id'],
                    'end_user_id' => ModuleModel::$user->getId(),
                ]
            ]);

            $data_array['is_favourite'] = $favourite instanceof FavouriteProduct;

            if ($this->getStatus() == self::STATUS_UNVERIFIED || $this->getStatus() == self::STATUS_VERIFIED) {
                $data_array['isEditable'] = true;
            } else {
                $data_array['isEditable'] = false;
            }
        } else {
            $data_array['isEditable'] = false;

        }

        if ($this->getCurrentAddressId()) {
            $address = $this->getCurrentAddress();
            if (isset($address) && $address instanceof Address) {
                $data_array['address_name'] = $address->getName();
                $data_array['warehouse_address1'] = $address->getAddress1();
                $data_array['warehouse_ward_name'] = $address->getWardName();
                $data_array['warehouse_district_name'] = $address->getDistrictName();
                $data_array['warehouse_province_name'] = $address->getProvinceName();
                $data_array['warehouse_country'] = $address->getCountry();
            }
        }

        $product_field_groups = $category ? $category->getProductFieldGroups() : [];

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


    /**
     * @param $params
     * @return array
     */
    public static function __findWithFiltersV2($options, $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Api\Models\Product', 'Product');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Brand', 'Product.brand_id = Brand.id', 'Brand');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Model', 'Product.model_id = Model.id', 'Model');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Company', 'Product.creator_company_id = Company.id', 'Company');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Category', 'Product.main_category_id = MainCategory.id', 'MainCategory');
        $queryBuilder->leftJoin('\SMXD\Api\Models\Address', 'Product.current_address_id = Address.id', 'Address');
        $queryBuilder->leftJoin('\SMXD\Api\Models\ProductSaleInfo', 'Product.uuid = ProductSaleInfo.uuid', 'ProductSaleInfo');
        $queryBuilder->leftJoin('\SMXD\Api\Models\ProductRentInfo', 'Product.uuid = ProductRentInfo.uuid', 'ProductRentInfo');
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
            'company_name' => 'Company.name',
            'brand_name' => 'Brand.name',
            'model_name' => 'Model.name',
            'main_category_name' => 'MainCategory.name',
            'address_name' => 'Address.name',
            'Product.created_at',
            'Product.updated_at',
        ]);
        $queryBuilder->where("Product.is_deleted <> 1");
        $queryBuilder->andWhere("Product.creator_end_user_id = :creator_end_user_id:", [
            'creator_end_user_id' => ModuleModel::$user->getId()
        ]);

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

        if (isset($options['statuses']) && count($options["statuses"]) > 0) {
            $queryBuilder->andwhere('Product.status IN ({statuses:array})', [
                'statuses' => $options["statuses"]
            ]);
        }

        if (isset($options['product_type_id']) && $options['product_type_id']) {
            $queryBuilder->andwhere('Product.product_type_id = :product_type_id:', [
                'product_type_id' => $options["product_type_id"]
            ]);
        }

        if (isset($options['model_ids']) && count($options["model_ids"]) > 0) {
            $queryBuilder->andwhere('Product.model_id IN ({model_ids:array})', [
                'model_ids' => $options["model_ids"]
            ]);
        }

        if (isset($options['company_ids']) && count($options["company_ids"]) > 0) {
            $queryBuilder->andwhere('Product.creator_company_id IN ({company_ids:array})', [
                'company_ids' => $options["company_ids"]
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

        if (isset($options['is_rent']) && ($options["is_rent"] === 1 || $options["is_rent"] === 0)) {
            $queryBuilder->andwhere('ProductRentInfo.status = :is_rent:', [
                'is_rent' => $options["is_rent"]
            ]);
        }

        if (isset($options['is_sale']) && ($options["is_sale"] === 1 || $options["is_sale"] === 0)) {
            $queryBuilder->andwhere('ProductSaleInfo.status = :is_sale:', [
                'is_sale' => $options["is_sale"]
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
            $pagination = $paginator->paginate();

            $dataArr = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $item = $item->toArray();
                    $media = MediaAttachment::__getImageByObjUuidAndIsThumb($item['uuid'], MediaAttachment::IS_THUMB_YES);
                    $item['url_thumb'] = $media ? $media->getTemporaryThumbS3Url() : null;

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
}
