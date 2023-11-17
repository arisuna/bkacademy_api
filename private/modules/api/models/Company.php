<?php

namespace SMXD\Api\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use SMXD\Application\Lib\Helpers;

class Company extends \SMXD\Application\Models\CompanyExt
{

    const LIMIT_PER_PAGE = 50;

    public static function __findWithFilters($options, $orders = []): array
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Company', 'Company');
        $queryBuilder->innerjoin('\SMXD\App\Models\Country', 'Country.id = Company.country_id', 'Country');

        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Company.id');

        $queryBuilder->columns([
            'Company.id',
            'Company.uuid',
            'Company.name',
            'Company.number',
            'Company.nickname',
            'Company.tax_number',
            'Company.email',
            'Company.phone',
            'Company.phone',
            'Company.creator_uuid',
            'Company.fax',
            'Company.website',
            'Company.address',
            'Company.street',
            'Company.town',
            'Company.zipcode',
            'Company.country_id',
            'Company.is_official',
            'Company.status',
            'Company.is_deleted',
            'Country.name as country_name',
            'Company.created_at',
            'Company.updated_at',
        ]);

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->where("Company.status IN ({statuses:array})", [
                'statuses' => $options['statuses']
            ]);

            if (!in_array(self::STATUS_ARCHIVED, $options['statuses'])) {
                $queryBuilder->andwhere("Company.is_deleted = 0", []);
            }
        }

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Company.name LIKE :search: OR Company.number LIKE :search: OR Company.phone LIKE :search: OR Company.email LIKE :search: OR Company.address LIKE :search: OR Company.street LIKE :search: OR Company.zipcode LIKE :search: ", [
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

        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Company.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Company.name DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Company.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Company.created_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Company.id DESC");
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
                    if (isset($item['creator_uuid']) && $item['creator_uuid']) {
                        $creator = User::findFirstByUuidCache($item['creator_uuid']);
                        if ($creator) {
                            $item['creator_name'] = $creator->getFirstname() . " " . $creator->getLastname();
                            $item['creator_email'] = $creator->getEmail();
                        }
                    }
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