<?php
/**
 * Created by PhpStorm.
 * Student: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;

use Phalcon\Mvc\Model\Behavior\Timestampable;
use Phalcon\Mvc\Model\Relation;
use Phalcon\Validation;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;
use SMXD\Application\Lib\CacheHelper;

class Student extends \SMXD\Application\Models\StudentExt
{
    const LIMIT_PER_PAGE = 50;

    public function initialize()
    {
        parent::initialize();
    }


    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options, $orders = [])
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Student', 'Student');
        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Student.id');

        $queryBuilder->columns([
            'Student.id',
            'name' => 'CONCAT(Student.lastname, " ", Student.firstname)',
            'Student.email',
            'Student.birthdate',
            'Student.birth_year',
            'Student.phone',
            'mother_name' => 'CONCAT(Student.mother_lastname, " ", Student.mother_firstname)',
            'father_name' => 'CONCAT(Student.father_lastname, " ", Student.father_firstname)',
            'Student.is_active',
            'Student.status',
            'Student.lvl',
            'Student.created_at'
        ]);
        $queryBuilder->andwhere("Student.status != :deleted:", [
            'deleted' => self::STATUS_DELETED
        ]);

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->andwhere("Student.status IN ({statuses:array})", [
                'statuses' => $options['statuses']
            ]);
        }

        if (isset($options['user_group_id']) && is_numeric($options['user_group_id'])) {
            $queryBuilder->andwhere("Student.user_group_id = :user_group_id:", [
                'user_group_id' => $options['user_group_id'],
            ]);
        }
        if (isset($options['exclude_user_group_ids']) && is_array($options['exclude_user_group_ids'])) {
            $queryBuilder->andwhere("Student.user_group_id NOT IN ({exclude_user_group_ids:array})", [
                'exclude_user_group_ids' => $options['exclude_user_group_ids'],
            ]);
        }

        if (isset($options['is_end_user']) && is_bool($options['is_end_user']) && $options['is_end_user'] == true) {
            $queryBuilder->andwhere("Student.is_end_user = 1");
        }


        if (isset($options['user_group_ids']) && is_array($options['user_group_ids'])) {
            $queryBuilder->andwhere("Student.user_group_id IN ({user_group_ids:array})", [
                'user_group_ids' => $options['user_group_ids'],
            ]);
        }

        if (isset($options['years']) && count($options["years"]) > 0) {
            $queryBuilder->andwhere('Student.birth_year IN ({years:array})', [
                'years' => $options["years"]
            ]);
        }

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("CONCAT(Student.lastname, ' ', Student.firstname) LIKE :search: OR Student.email LIKE :search: OR Student.phone LIKE :search: ", [
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
                    $queryBuilder->orderBy(['Student.firstname ASC', 'Student.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Student.firstname DESC', 'Student.lastname DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Student.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Student.created_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Student.id DESC");
        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->paginate();

            $data_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $data_array[] = $item;
                }
            }

            return [
                //'sql' => $queryBuilder->getQuery()->getSql(),
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