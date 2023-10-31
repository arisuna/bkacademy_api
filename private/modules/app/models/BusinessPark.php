<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class BusinessPark extends \SMXD\Application\Models\BusinessParkExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}

    public function getParsedData(){
        $item = $this->toArray();
        $item['province_name'] = $this->getProvince() ? $this->getProvince()->getName() : '';
        $item['district_name'] = $this->getDistrict() ? $this->getDistrict()->getName() : '';
        $item['ward_name'] = $this->getWard() ? $this->getWard()->getName() : '';
        $item['business_zone_name'] = $this->getBusinessZone() ? $this->getBusinessZone()->getName() : '';
        return $item;
    }

    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\BusinessPark', 'BusinessPark');
        $queryBuilder->leftJoin('\SMXD\App\Models\Province', 'BusinessPark.province_id = Province.id','Province');
        $queryBuilder->leftJoin('\SMXD\App\Models\Ward', 'BusinessPark.ward_id = Ward.id','Ward');
        $queryBuilder->leftJoin('\SMXD\App\Models\District', 'BusinessPark.district_id = District.id','District');
        $queryBuilder->leftJoin('\SMXD\App\Models\BusinessZone', 'BusinessPark.business_zone_uuid = BusinessZone.uuid','BusinessZone');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('BusinessPark.id');

        $queryBuilder->columns([
            'BusinessPark.id',
            'BusinessPark.uuid',
            'BusinessPark.name',
            'BusinessPark.address',
            'BusinessPark.province_id',
            'BusinessPark.district_id',
            'BusinessPark.ward_id',
            'BusinessPark.business_zone_uuid',
            'province_name' => 'Province.name',
            'district_name' => 'District.name',
            'ward_name' => 'Ward.name',
            'business_zone_name' => 'BusinessZone.name',
            'BusinessPark.created_at',
            'BusinessPark.updated_at',
        ]);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("BusinessPark.name LIKE :search: OR BusinessPark.address LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }


        if (isset($options['business_zones']) && is_array($options['business_zones']) && count($options['business_zones']) > 0) {
            $queryBuilder->andwhere("BusinessZone.id IN ({business_zones:array})", [
                'business_zones' => $options['business_zones']
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
        $queryBuilder->orderBy('BusinessPark.id DESC');

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
}
