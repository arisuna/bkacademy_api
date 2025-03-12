<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use function Composer\Autoload\includeFile;

class Brand extends \SMXD\Application\Models\BrandExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Api\Models\Brand', 'Brand');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Brand.id');

        $queryBuilder->columns([
            'Brand.id',
            'Brand.uuid',
            'Brand.name',
            'Brand.status',
            'Brand.deleted_at',
            'Brand.description',
            'Brand.created_at',
            'Brand.squared_logo_uuid',
            'Brand.rectangular_logo_uuid',
            'Brand.updated_at',
        ]);

        $queryBuilder->where("Brand.status = 1", []);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Brand.name LIKE :search:", [
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
//        $queryBuilder->orderBy('Brand.id DESC');

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

                    if ($itemArr['squared_logo_uuid']) {
                        $image = ObjectAvatar::__getImageByUuidAndType($itemArr['uuid'], 'squared_logo');

                        if ($image && $image->getUrlThumb() ) {
                            $itemArr['squared_logo'] = $image->getUrlThumb();
                        }
                    };

                    if ($itemArr['rectangular_logo_uuid']) {
                        $rectangularLogo = ObjectAvatar::__getImageByUuidAndType($itemArr['uuid'], 'rectangular_logo');
                        if ($rectangularLogo && $rectangularLogo->getUrlThumb()) {
                            $itemArr['rectangular_logo'] = $rectangularLogo->getUrlThumb();
                        }
                    };

                    $itemArr['product_count'] = 0;
                    $count = Product::count([
                        'conditions' => 'is_deleted <> 1 and status = 3 and brand_id = :brand_id:',
                        'bind' => [
                            'brand_id' => $itemArr['id'],
                        ]
                    ]);

                    if ($count) {
                        $itemArr['product_count'] = $count;
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
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}
