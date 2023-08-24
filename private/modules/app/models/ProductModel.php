<?php

namespace SMXD\App\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ProductModel extends \SMXD\Application\Models\ProductModelExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
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
        $queryBuilder->addFrom('\SMXD\App\Models\ProductModel', 'ProductModel');
        $queryBuilder->leftJoin('\SMXD\App\Models\Brand', 'Brand.id = Brand.brand_id ', 'Brand');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('ProductModel.id');

        $queryBuilder->columns([
            'ProductModel.id',
            'ProductModel.uuid',
            'ProductModel.name',
            'ProductModel.code',
            'ProductModel.status',
            'ProductModel.description',
            'ProductModel.created_at',
            'ProductModel.updated_at',
            'brand_name' => 'Brand.name',
        ]);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("ProductModel.name LIKE :search:", [
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
        $queryBuilder->orderBy('ProductModel.id DESC');

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
        $item = $this->toArray();
        $item['brand_name'] = $this->getBrand() ? $this->getBrand()->getName() : '';
        return $item;
    }
}
