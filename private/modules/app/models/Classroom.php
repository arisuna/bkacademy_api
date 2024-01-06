<?php

namespace SMXD\App\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use SMXD\Application\Lib\Helpers;

class Classroom extends \SMXD\Application\Models\ClassroomExt
{

    const LIMIT_PER_PAGE = 50;

    public static function __findWithFilters($options, $orders = []): array
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Classroom', 'Classroom');

        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Classroom.id');

        $queryBuilder->columns([
            'Classroom.id',
            'Classroom.uuid',
            'Classroom.name',
            'Classroom.grade',
            'Classroom.status',
            'Classroom.is_deleted',
            'Classroom.created_at',
            'Classroom.updated_at',
        ]);
        $queryBuilder->andwhere("Classroom.is_deleted <> 1 ");

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->andwhere("Classroom.status IN ({statuses:array}) ", [
                'statuses' => $options['statuses']
            ]);
        }

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Classroom.name LIKE :search: OR Classroom.number LIKE :search: OR Classroom.phone LIKE :search: OR Classroom.email LIKE :search: OR Classroom.address LIKE :search: OR Classroom.street LIKE :search: OR Classroom.zipcode LIKE :search: ", [
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
                    $queryBuilder->orderBy(['Classroom.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Classroom.name DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Classroom.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Classroom.created_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Classroom.id DESC");
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