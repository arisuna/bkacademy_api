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
use SMXD\Application\Lib\CacheHelper;

class User extends \SMXD\Application\Models\UserExt
{
    const LIMIT_PER_PAGE = 50;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('user_group_id', 'SMXD\App\Models\StaffUserGroup', 'id', [
            'alias' => 'UserGroup'
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
    public static function __findWithFilters($options, $orders = [])
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\User', 'User');
        $queryBuilder->distinct(true);
        $queryBuilder->leftJoin('\SMXD\App\Models\StaffUserGroup', "UserGroup.id = User.user_group_id", 'UserGroup');
        $queryBuilder->groupBy('User.id');

        $queryBuilder->columns([
            'User.id',
            'name' => 'CONCAT(User.firstname, " ", User.lastname)',
            'User.email',
            'User.phone',
            'User.is_active',
            'User.status',
            'role' => 'UserGroup.label',
            'User.aws_cognito_uuid',
            'User.created_at'
        ]);

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->where("User.status IN ({statuses:array})", [
                'statuses' => [
                    self::STATUS_ACTIVE,
                    self::STATUS_DELETED,
                    self::STATUS_DRAFT,
                ]
            ]);
        } else {
            $queryBuilder->where("User.status <> :deleted:", [
                'deleted' => self::STATUS_DELETED,
            ]);
        }

        if (isset($options['user_group_id']) && is_numeric($options['user_group_id'])) {
            $queryBuilder->andwhere("User.user_group_id = :user_group_id:", [
                'user_group_id' => $options['user_group_id'],
            ]);
        }
        if (isset($options['exclude_user_group_ids']) && is_array($options['exclude_user_group_ids'])) {
            $queryBuilder->andwhere("User.user_group_id NOT IN ({exclude_user_group_ids:array})", [
                'exclude_user_group_ids' => $options['exclude_user_group_ids'],
            ]);
        }

        if (isset($options['is_end_user']) && is_bool($options['is_end_user']) && $options['is_end_user'] == true) {
            $queryBuilder->andwhere("User.user_group_id is null");
        }


        if (isset($options['user_group_ids']) && is_array($options['user_group_ids'])) {
            $queryBuilder->andwhere("User.user_group_id IN ({user_group_ids:array})", [
                'user_group_ids' => $options['user_group_ids'],
            ]);
        }

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

        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['User.firstname ASC', 'User.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['User.firstname DESC', 'User.lastname DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['User.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['User.created_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("User.id DESC");
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

    /**
     * @return \Reloday\Application\Models\UserProfile[]
     */
    public static function getWorkersByGroup($groupId)
    {
        $users = self::find([
            'conditions' => 'status = :user_profile_active: AND user_group_id = :user_group_id:',
            'bind' => [
                'user_profile_active' => User::STATUS_ACTIVE,
                'user_group_id' => $groupId
            ]
        ]);
        return $users;
    }

    /*
    * create new user login from [email,password,app_id,user_group_id]
    */
    public function createNewUserLogin($data = array())
    {
        $model = new self();
        $userPasswordValidation = new UserPasswordValidation();

        if ($userPasswordValidation->check($data['password'])) {
            $security = new Security();
            $model->setPassword($security->hash($data['password']));
        } else {
            $result = [
                'success' => false,
                'message' => $userPasswordValidation->getFirstMessage(),
                'detail' => $userPasswordValidation->getMessages(),
                'password' => $data['password'],
            ];
            return $result;
        }

        $model->setEmail($data['email']);
        $model->setStatus(self::STATUS_ACTIVE);
        $model->setCreatedAt(date('Y-m-d H:i:s'));
        $model->setUserGroupId($data['user_group_id']);
        $model->setAppId($data['app_id']);
        try {
            if ($model->save()) {
                return $model;
            } else {
                $error_message = [];
                foreach ($model->getMessages() as $message) {
                    $error_message[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'CREATE_USER_FAIL_TEXT',
                    'detail' => $error_message
                ];
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'CREATE_USER_FAIL_TEXT',
                'detail' => $e->getMessage()
            ];
        }
        return $result;
    }

    /**
     *
     */
    public function loadListPermission()
    {
        $user = $this;
        $cacheManager = \Phalcon\DI\FactoryDefault::getDefault()->getShared('cache');
        $cacheName = CacheHelper::getAclCacheByGroupName($user->getUserGroupId());
//        $permissions = $cacheManager->get($cacheName, getenv('CACHE_TIME'));
        $permissions = [];
        $acl_list = [];
        //1. load from JWT

        if (!is_null($permissions) && is_array($permissions) && count($permissions) > 0) {
            return ($permissions);
        } else {
            $menus = array();
        }

        if (!$user->isAdmin()) {
            $groups_acl = StaffUserGroupAcl::getAllPrivilegiesGroup($user->getUserGroupId());
            $acl_ids = [];
            if (count($groups_acl)) {
                foreach ($groups_acl as $item) {
                    $acl_ids[] = $item->getAclId();
                }
            }
            if (count($acl_ids) > 0) {
                // Get controller and action in list ACLs, order by level
                $acl_list = Acl::find([
                    'conditions' => 'id IN ({acl_ids:array}) AND status = :status_active: ',
                    'bind' => [
                        'acl_ids' => $acl_ids,
                        'status_active' => Acl::STATUS_ACTIVATED,
                    ],
                    'order' => 'pos, lvl ASC'
                ]);
            }
        } else {
            $acl_list = Acl::__findAdminAcls();
        }

        if (count($acl_list)) {
            $acl_list = $acl_list->toArray();
            foreach ($acl_list as $item) {
                if (!isset($permissions[$item['controller']])) {
                    $permissions[$item['controller']] = [];
                }
                $permissions[$item['controller']][] = $item['action'];
                if (!$item['status']) continue;
            }
        }

        $cacheManager->save($cacheName, $permissions, getenv('CACHE_TIME'));
        return ($permissions);
    }

}