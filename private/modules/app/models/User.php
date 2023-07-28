<?php
/**
 * Created by PhpStorm.
 * User: binhnt
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

class User extends \SMXD\Application\Models\UserExt 
{
    const LIMIT_PER_PAGE = 50;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('user_group_id', 'SMXD\App\Models\UserGroup', 'id', [
            'alias' => 'UserGroup'
        ]);
        $this->belongsTo('user_login_id', 'SMXD\App\Models\UserLogin', 'id', [
            'alias' => 'UserLogin'
        ]);
        $this->belongsTo('company_id', 'SMXD\App\Models\Company', 'id', [
            'alias' => 'Company'
        ]);
        $this->belongsTo('country_id', 'SMXD\App\Models\Country', 'id', [
            'alias' => 'Country'
        ]);
    }


    /**
     * @param $params
     * @return array
     */
    public static function __findWithFilters($options)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\User', 'User');
        $queryBuilder->distinct(true);
        $queryBuilder->leftJoin('\SMXD\App\Models\UserGroup', "UserGroup.id = User.user_group_id", 'UserGroup');
        $queryBuilder->groupBy('User.id');

        $queryBuilder->columns([
            'User.id',
            'name' => 'CONCAT(User.firstname, " ", User.lastname)',
            'User.email',
            'User.phone',
            'User.is_active',
            'User.status',
            'role'=> 'UserGroup.label',
            'User.created_at'
        ]);

        $queryBuilder->where("User.status <> :deleted:", [
            'deleted' => self::STATUS_DELETED,
        ]);

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("CONCAT(User.firstname, ' ', User.lastname) LIKE :search: OR User.email LIKE :search: OR User.phone LIKE :search: ", [
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