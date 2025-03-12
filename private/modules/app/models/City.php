<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Application\Lib\CacheHelper;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;
use SMXD\Application\Models\CityExt;

class City extends CityExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE=1000;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @param $options
     */
    public static function __findWithFilters($options = [], $orders = [])
    {
        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\City', 'City');
        $queryBuilder->distinct(true);
        $queryBuilder->where('City.country_id = :country_id:', [
            'country_id' => $options['country_id']
        ]);

        $bindArray['country_id'] = $options['country_id'];

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("City.name LIKE :query: OR City.alternate_names LIKE :query: OR City.asciiname LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
            $bindArray['query'] = '%' . $options['query'] . '%';
        }

        if (isset($options['population']) && is_numeric($options['population']) && $options['population'] > 0) {
            $queryBuilder->andwhere("City.population >= :population:", [
                'population' => $options['population'],
            ]);
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "population") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['City.population ASC']);
                } else {
                    $queryBuilder->orderBy(['City.population DESC']);
                }
            }

            if ($order['field'] == "fcode") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['City.fcode ASC']);
                } else {
                    $queryBuilder->orderBy(['City.fcode DESC']);
                }
            }
        } else {
            $queryBuilder->orderBy(['City.population DESC']);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->paginate();
            return [
                'success' => true,
                'orders' => $orders,
                'page' => $page,
                'data' => $pagination->items,
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
