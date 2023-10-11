<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class Address extends \SMXD\Application\Models\AddressExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE= 1000;

	public function initialize(){
		parent::initialize(); 
	}

    public static function __findWithFilters($options, $orders = []): array
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Address', 'Address');

        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Address.id');

        $queryBuilder->columns([
            'Address.id',
            'Address.uuid',
            'Address.name',
            'Address.end_user_id',
            'Address.company_id',
            'Address.vn_district_id',
            'Address.vn_ward_id',
            'Address.vn_province_id',
            'Address.address1',
            'Address.address2',
            'Address.latitude',
            'Address.longitude',
            'Address.ward_name',
            'Address.district_name',
            'Address.province_name',
            'Address.country_id',
            'Address.postal',
            'Address.city',
            'Address.country',
            'Address.telephone',
            'Address.phone',
            'Address.is_default',
            'Address.address_type',
            'Address.created_at',
            'Address.updated_at',
        ]);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Address.name LIKE :search: OR Address.address1 LIKE :search: OR Address.phone LIKE :search: OR Address.ward_name LIKE :search: OR Address.address2 LIKE :search: OR Address.district_name LIKE :search: OR Address.province_name LIKE :search: ", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        if (isset($options['company_id']) && is_numeric($options['company_id'])) {
            $queryBuilder->andwhere("Address.company_id = :company_id:", [
                'company_id' => $options['company_id'],
            ]);
        }

        if (isset($options['end_user_id']) && is_numeric($options['end_user_id'])) {
            $queryBuilder->andwhere("Address.end_user_id = :end_user_id:", [
                'end_user_id' => $options['end_user_id'],
            ]);
        }

        if (isset($options['address_type']) && is_numeric($options['address_type'])) {
            $queryBuilder->andwhere("Address.address_type = :address_type:", [
                'address_type' => $options['address_type'],
            ]);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;

        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Address.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Address.name DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Address.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Address.created_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Address.id DESC, 'Address.is_default ASC'");
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $data_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $item = $item->toArray();

                    $data_array[] = $item;
                }
            }

            return [
                'success' => true,
                'params' => $options,
                'page' => $page,
                'data' => $data_array,
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
