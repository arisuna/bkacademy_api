<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class Category extends \SMXD\Application\Models\CategoryExt
{


    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
    }

    public function getProductFieldGroups()
    {
        $groups = [];
        $product_field_group_in_categories = ProductFieldGroupInCategory::find([
            'conditions' => 'category_id = :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'pos ASC'
        ]);
        if (count($product_field_group_in_categories) > 0) {
            foreach ($product_field_group_in_categories as $product_field_group_in_category) {
                $product_field_group = $product_field_group_in_category->getProductFieldGroup();
                if ($product_field_group instanceof ProductFieldGroup && $product_field_group->getIsDeleted() != Helpers::YES) {
                    $groups[] = $product_field_group;
                }
            }

        }
        return $groups;

    }

    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Api\Models\Category', 'Category');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Category.id');

        $queryBuilder->columns([
            'Category.id',
            'Category.uuid',
            'Category.name',
            'Category.created_at',
            'Category.updated_at',
        ]);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Category.name LIKE :search:", [
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
        $queryBuilder->orderBy('Category.id DESC');

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


    /**
     * @param $params
     * @return array
     */
    public static function getList($params)
    {
        $categoriesArr = [];

        $categories = Category::find([
            'conditions' => 'name LIKE :query: and parent_category_id is null',
            'bind' => [
                'query' => "%" . $params['query'] . "%",
            ],
            'order' => 'pos ASC'
        ]);

        if ($categories && count($categories) > 0) {
            $categoriesChildArr = [];
            $makesArr = [];

            $categoriesChild = Category::find([
                'conditions' => 'parent_category_id is not null',
                'order' => 'pos ASC'
            ]);

            if ($categoriesChild && count($categoriesChild) > 0) {
                foreach ($categoriesChild as $item) {
                    $itemArr = $item->toArray();
                    $itemArr['category_name'] = $itemArr['name'];
                    $itemArr['name'] = $itemArr['label'];
                    $itemArr['product_count'] = 0;
                    $count = Product::count([
                        'conditions' => 'is_deleted <> 1 and status = 3 and secondary_category_id = :secondary_category_id:',
                        'bind' => [
                            'secondary_category_id' => $itemArr['id'],
                        ]
                    ]);
                    if ($count) {
                        $itemArr['product_count'] = $count;
                    }

                    $categoriesChildArr[$item->getParentCategoryId()][] = $itemArr;
                }
            }

            $makes = [];
            if ($params['has_make']) {
                $makes = Brand::find([
                    'conditions' => 'status = :status:',
                    'bind' => [
                        'status' => Brand::STATUS_ACTIVE
                    ],
                ]);
            }

            foreach ($categories as $item) {
                $itemArr = $item->toArray();
                $itemArr['category_name'] = $itemArr['name'];
                $itemArr['name'] = $itemArr['label'];
                $itemArr['items'] = [];
                $itemArr['product_count'] = 0;

                $count = Product::count([
                    'conditions' => 'is_deleted <> 1 and status = 3 and main_category_id = :main_category_id:',
                    'bind' => [
                        'main_category_id' => $itemArr['id'],
                    ]
                ]);
                if ($count) {
                    $itemArr['product_count'] = $count;
                }

                if (isset($categoriesChildArr[$item->getId()]) && $categoriesChildArr[$item->getId()]) {
                    $itemArr['items'] = $categoriesChildArr[$item->getId()];
                }

                $itemArr['makes'] = [];
                if ($params['has_make']) {
                    if ($makes && count($makes) > 0) {
                        foreach ($makes as $make) {
                            $makeArr = $make->toArray();
                            $makeArr['product_count'] = 0;
                            $count = Product::count([
                                'conditions' => 'is_deleted <> 1 and status = 3 and brand_id = :brand_id: and main_category_id = :main_category_id:',
                                'bind' => [
                                    'brand_id' => $makeArr['id'],
                                    'main_category_id' => $itemArr['id'],
                                ]
                            ]);

                            if ($count) {
                                $makeArr['product_count'] = $count;
                            }
                            $itemArr['makes'][] = $makeArr;
                        }
                    }
                }

                $itemArr['rectangular_logo'] = null;

                $rectangularLogo = ObjectAvatar::__getImageByUuidAndType($itemArr['uuid'], 'rectangular_logo');
                if ($rectangularLogo && $rectangularLogo->getUrlThumb()) {
                    $itemArr['rectangular_logo'] = $rectangularLogo->getUrlThumb();
                }

                $categoriesArr[] = $itemArr;
            }
        }

        return $categoriesArr;
    }
}
