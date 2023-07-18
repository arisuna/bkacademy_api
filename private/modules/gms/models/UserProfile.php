<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayCachePrefixHelper;
use Reloday\Application\Models\CompanySettingDefaultExt;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Models\UserProfileExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Module;
use Reloday\Gms\Models\Contract;


use Phalcon\Validation;
use Phalcon\Validation\Validator\Regex as RegexValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class UserProfile extends UserProfileExt
{
    const LIMIT_PER_PAGE = 20;

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasOne('user_login_id', 'Reloday\Gms\Models\UserLogin', 'id', [
            'alias' => 'UserLogin',
            'params' => [
                'conditions' => 'Reloday\Gms\Models\UserLogin.status = :user_login_active:',
                'bind' => [
                    'user_login_active' => UserLogin::STATUS_ACTIVATED
                ]
            ]
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
        ]);
        $this->belongsTo('user_group_id', 'Reloday\Gms\Models\UserGroup', 'id', [
            'alias' => 'UserGroup',
            // 'usable' => true,
        ]);
        $this->belongsTo('country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'Country',
        ]);
        $this->belongsTo('office_id', 'Reloday\Gms\Models\Office', 'id', [
            'alias' => 'Office',
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Contract', 'from_company_id', [
            'alias' => 'HrContract',
            'params' => [
                'order' => 'id DESC',
                'conditions' => 'Reloday\Gms\Models\Contract.status = :status:',
                'bind' => [
                    "status" => Contract::STATUS_ACTIVATED
                ]
            ]
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Contract', 'to_company_id', [
            'alias' => 'GmsContract',
            'params' => [
                'order' => 'id DESC',
                'conditions' => 'Reloday\Gms\Models\Contract.status = :status:',
                'bind' => [
                    "status" => Contract::STATUS_ACTIVATED
                ]
            ]
        ]);
        $this->hasOne('uuid', 'Reloday\Gms\Models\UserProfileData', 'user_profile_uuid', [
            'alias' => 'UserProfileData',
            'cache' => [
                'key' => 'USER_PROFILE_DATA_' . $this->getUuid(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\UserInterfaceVersion', 'user_profile_id', [
            'alias' => 'UserInterfaceVersions',
        ]);
    }

    /**
     * Load list user by company
     * @param string $condition
     * @return array
     */
    public function loadList($condition = '')
    {
        $company_id = ModuleModel::$user_profile->getCompanyId();
        $profiles = self::find([
            'conditions' => 'company_id=' . $company_id . ($condition ? " AND $condition" : '')
        ]);

        $roles = UserGroup::find();
        $roles_arr = [];
        if (count($roles)) {
            foreach ($roles as $role) {
                $roles_arr[$role->getId()] = $role->toArray();
            }
        }

        $users = [];
        if (count($profiles)) {
            foreach ($profiles as $profile) {
                $item = $profile->toArray();
                if (isset($roles_arr[$item['user_group_id']])) {
                    $item['group_name'] = $roles_arr[$item['user_group_id']]['label'];
                }
                $item['hasUserLogin'] = $profile->getUserLogin() ? true : false;
                $item['hasAwsCognito'] = $profile->hasLogin();
                $users[$item['id']] = $item;
            }
        }

        return [
            'success' => true,
            'users' => $users,
        ];
    }

    /**
     * [getManager description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function getManagers($company_id)
    {
        $gms_company_id = ModuleModel::$user_profile->getCompanyId();
        $user_group_ids = [
            UserGroup::GROUP_GMS_ADMIN,
            UserGroup::GROUP_GMS_MANAGER,
            UserGroup::GROUP_HR_ADMIN,
            UserGroup::GROUP_HR_MANAGER
        ];

        $company_ids = Company::getCompanyIdsOfGms();
        $users = [];
        if (in_array($company_id, $company_ids)) {
            $company_ids = [$gms_company_id, $company_id];
            $users = self::find([
                'conditions' => 'company_id IN ({company_ids:array}) AND user_group_id IN ({user_group_ids:array})',
                'bind' => [
                    'company_ids' => $company_ids,
                    'user_group_ids' => $user_group_ids
                ]
            ]);
        }
        return $users;
    }

    /**
     * [getHrMembers description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function getHrMembers($hr_company_id = '')
    {
        $user_group_ids = UserGroup::__getHrRoleIds();

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\UserGroup', 'UserGroup.id = UserProfile.user_group_id', 'UserGroup');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = UserProfile.company_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :to_company_id: ');
        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})');
        $queryBuilder->andWhere('UserProfile.status = :status_active:');
        $bindArray = [
            'user_group_ids' => $user_group_ids,
            'to_company_id' => ModuleModel::$company->getId(),
            'status_active' => UserProfile::STATUS_ACTIVE
        ];

        if (is_numeric($hr_company_id) && $hr_company_id > 0) {
            $queryBuilder->andWhere('Contract.from_company_id = :hr_company_id:');
            $bindArray['hr_company_id'] = $hr_company_id;
        }

        $queryBuilder->columns([
            'UserProfile.id',
            'UserProfile.uuid',
            'UserProfile.firstname',
            'UserProfile.lastname',
            'UserProfile.status',
            'UserProfile.workemail',
            'UserProfile.phonework',
            'UserProfile.office_id',
            'company_name' => 'Company.name',
            'role_name' => 'UserGroup.label',
        ]);
        $users = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
        return [
            'success' => true,
            'data' => $users,
        ];
    }

    /**
     * [getHrMembers description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function getHrMembersFullInfo($params = [])
    {

        $user_group_ids = UserGroup::__getHrRoleIds();

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Office', 'Office.id = UserProfile.office_id', 'Office');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserGroup', 'UserGroup.id = UserProfile.user_group_id', 'UserGroup');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = UserProfile.company_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :to_company_id: ');
        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})');

        $bind = [
            'user_group_ids' => $user_group_ids,
            'to_company_id' => ModuleModel::$company->getId()
        ];
        //$query = $queryBuilder->getPhql();
        //var_dump( $query ); die();
        if (isset($params['hr_company_id']) && $params['hr_company_id'] > 0) {
            $queryBuilder->andwhere('UserProfile.company_id =  :hr_company_id:');
            $bind['hr_company_id'] = $params['hr_company_id'];
        }

        if (isset($params['status']) && is_numeric($params['status'])) {
            $queryBuilder->andwhere('UserProfile.status =  :status_users:');
            $bind['status_users'] = $params['status'];
        }

        $users = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bind));
        $users_array = [];
        foreach ($users as $user) {
            $users_array[$user->getId()] = $user->toArray();
            $users_array[$user->getId()]['avatar'] = $user->getAvatar();
            $users_array[$user->getId()]['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
            $users_array[$user->getId()]['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;
            $users_array[$user->getId()]['role_name'] = ($user->getUserGroup()) ? $user->getUserGroup()->getLabel() : null;
        }

        return [
            'success' => true,
            'data' => array_values($users_array),
        ];
    }


    /**
     * @return array|null
     */
    public function getAvatar()
    {
//        $avatar = MediaAttachment::__getLastAttachment($this->getUuid(), "avatar");
        $avatar = ObjectAvatar::__getAvatar($this->getUuid());
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }

    /**
     * get  Avatar URL
     * @return string
     */
    public function getAvatarUrl()
    {
        return parent::getAvatarUrl();
    }

    /**
     * [getManager description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function getWorkers($hr_company_id)
    {

        $hrRoles = UserGroup::__getHrRoleIds();
        $gmpRoles = UserGroup::__getGmpRoleIds();
        $user_group_ids = [];
        foreach ($hrRoles as $hrRoleId) {
            $user_group_ids[] = $hrRoleId;
        }
        foreach ($gmpRoles as $gmpRoleId) {
            $user_group_ids[] = $gmpRoleId;
        }

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = UserProfile.company_id', 'Contract');

        $queryBuilder->where('Contract.to_company_id = :to_company_id:');
        $queryBuilder->andWhere('UserProfile.status = :user_profile_active:');
        $queryBuilder->andWhere('Contract.from_company_id = :from_company_id: ');
        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})');
        $queryBuilder->andWhere('Contract.status = :status_active:');
        $queryBuilder->orWhere('UserProfile.company_id = :to_company_id:');


        $bindArray = [
            'user_profile_active' => UserProfile::STATUS_ACTIVE,
            'to_company_id' => (int)ModuleModel::$company->getId(),
            'from_company_id' => (int)$hr_company_id,
            'user_group_ids' => $user_group_ids,
            'status_active' => Contract::STATUS_ACTIVATED,
        ];

        try {
            $users = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
            return $users;
        } catch (\PDOException $e) {
            return [];
        } catch (Exception $e) {
            return [];
        }
    }


    /**
     * [getManager description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function getHrWorkers($company_id)
    {
        $user_group_ids = UserGroup::__getHrRoleIds();
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->columns([
            'UserProfile.id',
            'UserProfile.uuid',
            'UserProfile.workemail',
            'UserProfile.firstname',
            'UserProfile.lastname',
            'UserProfile.phonework',
            'UserProfile.mobilework',
            'UserProfile.jobtitle'
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = UserProfile.company_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :to_company_id: ');
        $queryBuilder->andWhere('Contract.from_company_id = :from_company_id: ');
        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})');
        $queryBuilder->andWhere('Contract.status = :contract_activated:');
        $queryBuilder->andWhere('UserProfile.status = :user_profile_active:');
        $queryBuilder->orderBy(['UserProfile.firstname ASC']);
        $bindArray = [
            'user_profile_active' => UserProfile::STATUS_ACTIVE,
            'user_group_ids' => $user_group_ids,
            'from_company_id' => $company_id,
            'to_company_id' => ModuleModel::$company->getId(),
            'contract_activated' => Contract::STATUS_ACTIVATED,
        ];

        try {
            $users = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
            return $users;
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @return \Reloday\Application\Models\UserProfile[]
     */
    public static function getGmsWorkers()
    {
        $user_group_ids = UserGroup::__getGmpRoleIds();
        $users = self::find([
            'conditions' => 'status = :user_profile_active: AND company_id = :company_id: AND user_group_id IN ({user_group_ids:array})',
            'bind' => [
                'user_profile_active' => UserProfile::STATUS_ACTIVE,
                'company_id' => ModuleModel::$company->getId(),
                'user_group_ids' => $user_group_ids
            ],
            'order' => 'firstname ASC',
            'cache' => [
                'key' => self::__getCacheNameGmsWorker(ModuleModel::$company->getId()),
                'lifetime' => RelodayCachePrefixHelper::CACHE_TIME_DAILY,
            ]
        ]);
        return $users;
    }

    /**
     * @return \Reloday\Application\Models\UserProfile[]
     */
    public static function getGmsWorkersForTimelog()
    {
        if(ModuleModel::$user_profile->getUserGroupId() != UserGroup::GMS_ADMIN && ModuleModel::$user_profile->getUserGroupId() != UserGroup::GROUP_GMS_MANAGER){
            return [ModuleModel::$user_profile];
        } 
        $user_group_ids = UserGroup::__getGmpRoleIds();
        $users = self::find([
            'conditions' => 'status = :user_profile_active: AND company_id = :company_id: AND user_group_id IN ({user_group_ids:array})',
            'bind' => [
                'user_profile_active' => UserProfile::STATUS_ACTIVE,
                'company_id' => ModuleModel::$company->getId(),
                'user_group_ids' => $user_group_ids
            ],
            'order' => 'firstname ASC',
            'cache' => [
                'key' => self::__getCacheNameGmsWorker(ModuleModel::$company->getId()),
                'lifetime' => RelodayCachePrefixHelper::CACHE_TIME_DAILY,
            ]
        ]);
        return $users;
    }

    /**
     * @return \Reloday\Application\Models\UserProfile[]
     */
    public static function getWorkersByGroup($groupId)
    {
        $users = self::find([
            'conditions' => 'status = :user_profile_active:  AND company_id = :company_id: AND user_group_id = :user_group_id:',
            'bind' => [
                'user_profile_active' => UserProfile::STATUS_ACTIVE,
                'company_id' => ModuleModel::$company->getId(),
                'user_group_id' => $groupId
            ]
        ]);
        return $users;
    }

    public function belongsToGms()
    {
        if ($this->getCompanyId() == ModuleModel::$company->getId()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function manageByGms()
    {
        $hrContract = $this->getHrContract("to_company_id = " . ModuleModel::$company->getId());
        if ($hrContract) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * @return mixed
     */
    public function checkNickName($nickname)
    {
        $validator = new Validation();
        $validator->add(
            'nickname', //your field name
            new UniquenessValidator([
                'model' => $this,
                'message' => 'NICKNAME_UNIQUE_TEXT'
            ])
        );
        $messages = $validator->validate(['nickname' => $nickname]);
        $check = $messages->count();
        if ($check > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * generate nickname before save
     */
    public function beforeSave()
    {
        if ($this->getNickname() == '') {
            $nickname = $this->generateNickName();
            $this->setNickname($nickname);
        }
    }

    /**
     * @return mixed
     */
    public function beforeValidation()
    {
        if ($this->getNickname() == '') {
            $nickname = $this->generateNickName();
            $this->setNickname($nickname);
        }
    }

    /**
     * @return mixed
     */
    public function beforeValidationOnSave()
    {
        if ($this->getNickname() == '') {
            $nickname = $this->generateNickName();
            $this->setNickname($nickname);
        }
    }

    /**
     * check User Profile is Admin or Manager
     * @return bool
     */
    public function isAdminOrManager()
    {
        if ($this->getUserGroupId() == UserGroup::GROUP_GMS_ADMIN ||
            $this->getUserGroupId() == UserGroup::GROUP_GMS_MANAGER
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return $this->getUserGroupId() == UserGroup::GMS_ADMIN;
    }

    /**
     * @return bool
     */
    public function controlByGms()
    {
        if (($this->isHr() && $this->manageByGms())) {
            return true;
        } elseif ($this->isGms() && $this->belongsToGms()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $params
     * @return array
     */
    public static function __findHrContactWithFilter($options = [])
    {

        $user_group_ids = UserGroup::__getHrRoleIds();

        $queryBuilder = new QueryBuilder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = UserProfile.company_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :to_company_id: ', ['to_company_id' => ModuleModel::$company->getId()]);
        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})', ['user_group_ids' => $user_group_ids]);
        $queryBuilder->andWhere('Contract.status = :contract_activated:', ['contract_activated' => Contract::STATUS_ACTIVATED]);
        $queryBuilder->andWhere('UserProfile.status = :user_profile_active:', ['user_profile_active' => UserProfile::STATUS_ACTIVE]);

        if (isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids'])) {
            $queryBuilder->andwhere("UserProfile.company_id IN ({company_ids:array})", [
                'company_ids' => $options['company_ids'],
            ]);
        }

        if (isset($options['role_ids']) && is_array($options['role_ids']) && count($options['role_ids'])) {
            $queryBuilder->andwhere("UserProfile.user_group_id IN ({role_ids:array})", [
                'role_ids' => $options['role_ids'],
            ]);
        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("UserProfile.company_id = :company_id:", [
                'company_id' => $options['company_id'],
            ]);
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("UserProfile.firstname LIKE :query: OR UserProfile.lastname LIKE :query: OR UserProfile.workemail LIKE :query: OR Company.name LIKE :query:",
                ['query' => '%' . $options['query'] . '%']);
        }

        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;

        if (isset($options['start'])) {
            $page = intval($start / self::LIMIT_PER_PAGE) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        }


        // $tasks = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));

        $queryBuilder->orderBy('UserProfile.created_at DESC');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => self::LIMIT_PER_PAGE,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $items_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $items_array[$item->getUuid()] = $item->toArray();
                    $items_array[$item->getUuid()]['company_name'] = $item->getCompany()->getName();
                    $items_array[$item->getUuid()]['role_name'] = $item->getUserGroup()->getLabel();
                }
            }

            return [
                'success' => true,
                'sql' => $queryBuilder->getQuery()->getSql(),
                'page' => $page,
                'data' => array_values($items_array),
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
     * @param $params
     * @return array
     */
    public static function __findGmsWorkersWithFilter($options = [])
    {
        $user_group_ids = UserGroup::__getGmpRoleIds();

        $queryBuilder = new QueryBuilder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\UserGroup', 'UserGroup.id = UserProfile.user_group_id', 'UserGroup');

        $queryBuilder->where('UserProfile.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})', ['user_group_ids' => $user_group_ids]);
        $queryBuilder->andWhere('UserProfile.status = :user_profile_active:', ['user_profile_active' => UserProfile::STATUS_ACTIVE]);


        if (isset($options['role_ids']) && is_array($options['role_ids']) && count($options['role_ids'])) {
            $queryBuilder->andwhere("Relocation.user_group_id IN ({role_ids:array})", [
                'role_ids' => $options['role_ids'],
            ]);
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("UserProfile.firstname LIKE :query: OR UserProfile.lastname LIKE :query: OR UserProfile.workemail LIKE :query:",
                ['query' => '%' . $options['query'] . '%']);
        }

        if (isset($options['owner_uuid']) && is_string($options['owner_uuid']) && $options['owner_uuid'] != '') {
            $queryBuilder->andwhere("UserProfile.uuid = :owner_uuid:",
                ['owner_uuid' => $options['owner_uuid']]);
        }

        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andwhere("UserProfile.id IN ({ids:array} )", [
                'ids' => $options['ids'],
            ]);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        $queryBuilder->orderBy(['UserProfile.firstname ASC']);

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $items_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $items_array[$item->getUuid()] = $item->toArray();
                    $items_array[$item->getUuid()]['company_name'] = $item->getCompany()->getName();
                    $items_array[$item->getUuid()]['role_name'] = $item->getUserGroup()->getLabel();

                    if (isset($options['isRelocation']) && is_bool($options['isRelocation']) && $options['isRelocation']) {
                        $items_array[$item->getUuid()]['countRelocationOwner'] = $item->countRelocationOwner();
                        $items_array[$item->getUuid()]['relocationOngoing'] = $item->countRelocationOwner(['statuses' => [Relocation::STATUS_ONGOING]]);
                        $items_array[$item->getUuid()]['relocationTodo'] = $item->countRelocationOwner(['statuses' => [Relocation::STATUS_PENDING]]);
                    }
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => array_values($items_array),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }


    /**
     * @return mixed
     */
    public function getParsedArray()
    {
        $array = $this->toArray();
        $array['avatar'] = $this->getAvatar();
        $array['isAdmin'] = $this->isGmsAdmin();
        $array['isAdminOrManager'] = $this->isGmsAdminOrManager();
        $array['spoken_languages'] = $this->parseSpokenLanguages();
        return $array;
    }

    /**
     * @return \Reloday\Application\Models\UserProfile[]
     */
    public static function __getWorkers()
    {
        $user_group_ids = UserGroup::__getGmpRoleIds();
        $users = self::find([
            'conditions' => 'company_id = :company_id: AND user_group_id IN ({user_group_ids:array})',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'user_group_ids' => $user_group_ids
            ],
            'cache' => [
                'key' => self::__getCacheNameCompanyWorker(ModuleModel::$company->getId()),
                'lifetime' => RelodayCachePrefixHelper::CACHE_TIME_DAILY,
            ]
        ]);
        return $users;
    }


    /**
     * @param $params
     * @param $order
     * @return array
     */
    public static function __findGmsViewersWithFilter($options = [], $orders = [])
    {
        $user_group_ids = UserGroup::__getGmpRoleIds();

        $queryBuilder = new QueryBuilder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');

        $queryBuilder->where('UserProfile.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})', [
            'user_group_ids' => $user_group_ids
        ]);
        $queryBuilder->andWhere('UserProfile.status = :user_profile_active:', [
            'user_profile_active' => UserProfile::STATUS_ACTIVE
        ]);


        if (isset($options['role_ids']) && is_array($options['role_ids']) && count($options['role_ids'])) {
            $queryBuilder->andwhere("UserProfile.user_group_id IN ({role_ids:array})", [
                'role_ids' => $options['role_ids'],
            ]);
        }

        if (isset($options['object_uuid']) && is_string($options['object_uuid']) && $options['object_uuid'] != '') {
            $memberUuids = DataUserMember::__getMembersUuids($options['object_uuid']);
            if (is_array($memberUuids) && count($memberUuids) > 0) {
                $queryBuilder->andwhere("UserProfile.uuid NOT IN ({user_profile_uuids:array})", [
                    'user_profile_uuids' => array_values($memberUuids),
                ]);
            }
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("UserProfile.firstname LIKE :query: OR UserProfile.lastname LIKE :query: OR concat(UserProfile.firstname, ' ', UserProfile.lastname) LIKE :query: OR UserProfile.jobtitle LIKE :query: OR UserProfile.nickname LIKE :query: OR UserProfile.workemail LIKE :query: OR Company.name LIKE :query:",
                ['query' => '%' . $options['query'] . '%']);
        }

        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andwhere("UserProfile.id IN ({ids:array} )", [
                'ids' => $options['ids'],
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'STATUS_ARRAY_TEXT' => 'UserProfile.login_status',
                'ROLE_ARRAY_TEXT' => 'UserProfile.user_group_id',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::GMS_MEMBER_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('UserProfile.firstname ASC');

            if ($order['field'] == "firstname") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['UserProfile.firstname ASC']);
                } else {
                    $queryBuilder->orderBy(['UserProfile.firstname DESC']);
                }
            }

            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['UserProfile.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['UserProfile.updated_at DESC']);
                }
            }

        }


        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $items_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $data = $item->toArray();
                    //Check has login
//                    if($item->hasLogin() && $item->getLoginStatus() != self::LOGIN_STATUS_HAS_ACCESS){
//                        $userProfile = self::findFirstById($item->getId());
//                        if($userProfile){
//                            $userProfile->setLoginStatus(self::LOGIN_STATUS_HAS_ACCESS);
//                            $resultUpdate = $userProfile->__quickUpdate();
//
//                            $data['login_status'] = self::LOGIN_STATUS_HAS_ACCESS;
//                        }
//                    }
                    $data['group_name'] = $item->getUserGroup() ? $item->getUserGroup()->getLabel() : null;
                    $items_array[] = $data;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => $items_array,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_pages' => $pagination->total_pages,
                'params' => [
                    '$options' => $options,
                ]
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param $controller
     * @param $action
     */
    public function hasPermission(String $controller, String $action)
    {
        $result = AclHelper::__checkPermission($controller, $action);
        if ($result['success'] == true) {
            return true;
        }
        return false;
    }

    /**
     * @param string $moduleName
     */
    public function switchUiVersion(String $moduleName)
    {
        $userVersions = $this->getUserInterfaceVersions([
            'conditions' => 'module_name = :module_name:',
            'bind' => [
                'module_name' => $moduleName
            ]
        ]);

        if ($userVersions && $userVersions->count() > 0) {
            $userVersion = $userVersions->getFirst();
            if ($userVersion->getVersion() == UserInterfaceVersion::V1) {
                $userVersion->setVersion(UserInterfaceVersion::V2);
            } elseif ($userVersion->getVersion() == UserInterfaceVersion::V2) {
                $userVersion->setVersion(UserInterfaceVersion::V1);
            }
            return $userVersion->__quickUpdate();
        } else {
            $userVersion = new UserInterfaceVersion();
            $userVersion->setVersion(UserInterfaceVersion::V2);
            $userVersion->setUserProfileId(ModuleModel::$user_profile->getId());
            $userVersion->setModuleName($moduleName);
            return $userVersion->__quickCreate();
        }

    }

    /**
     * @return array
     */
    public function getUiVersionList()
    {
        $userVersions = $this->getUserInterfaceVersions();
        $arrayItems = [];
        if ($userVersions->count() > 0) {
            foreach ($userVersions as $userVersion) {
                $arrayItems[$userVersion->getModuleName()] = $userVersion->getVersion();
            }
        } else {
            foreach (UserInterfaceVersion::$modules as $moduleName) {
                $arrayItems[$moduleName] = UserInterfaceVersion::CURRENT_VERSION;
            }
        }
        return $arrayItems;
    }

    /**
     * @return boolean
     */
    public function hasLogin()
    {
        if ($this->getUserLogin()) {
            $userLogin = $this->getUserLogin();
            $resultCognito = $userLogin->isConvertedToUserCognito();
            if ($resultCognito) {
                $cognitoLogin = $userLogin->getCognitoLogin();
                if ($cognitoLogin && ($cognitoLogin['userStatus'] == CognitoClient::UNCONFIRMED || $cognitoLogin['userStatus'] == CognitoClient::FORCE_CHANGE_PASSWORD)) {
                    return false;
                } else {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return \Reloday\Application\Models\UserProfile
     */
    public static function __getDefaultSupportContact()
    {
        $uuid = ModuleModel::$company->getCompanySettingValue(CompanySettingDefaultExt::DEFAULT_SUPPORT_CONTACT_UUID);
        if (Helpers::__isValidUuid($uuid)) {
            $contact = self::findFirstByUuidCache($uuid);
            if ($contact->isDeleted() == false && $contact->belongsToGms() == true) {
                return $contact;
            }
        }
    }

    public static function __getMembersWorkload($options = [], $orders = []){
        $user_group_ids = UserGroup::__getGmpRoleIds();

        $queryBuilder = new QueryBuilder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\UserProfile', 'UserProfile');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Company.id = UserProfile.company_id', 'Company');

        $queryBuilder->where('UserProfile.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andWhere('UserProfile.id != :current_user_id:', [
            'current_user_id' => ModuleModel::$user_profile->getId()
        ]);

        $queryBuilder->andWhere('UserProfile.user_group_id IN ({user_group_ids:array})', [
            'user_group_ids' => $user_group_ids
        ]);
        $queryBuilder->andWhere('UserProfile.status = :user_profile_active:', [
            'user_profile_active' => UserProfile::STATUS_ACTIVE
        ]);


        if (isset($options['role_ids']) && is_array($options['role_ids']) && count($options['role_ids'])) {
            $queryBuilder->andwhere("UserProfile.user_group_id IN ({role_ids:array})", [
                'role_ids' => $options['role_ids'],
            ]);
        }

        if (isset($options['object_uuid']) && is_string($options['object_uuid']) && $options['object_uuid'] != '') {
            $memberUuids = DataUserMember::__getMembersUuids($options['object_uuid']);
            if (is_array($memberUuids) && count($memberUuids) > 0) {
                $queryBuilder->andwhere("UserProfile.uuid NOT IN ({user_profile_uuids:array})", [
                    'user_profile_uuids' => array_values($memberUuids),
                ]);
            }
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("UserProfile.firstname LIKE :query: OR UserProfile.lastname LIKE :query: OR concat(UserProfile.firstname, ' ', UserProfile.lastname) LIKE :query: OR UserProfile.jobtitle LIKE :query: OR UserProfile.nickname LIKE :query: OR UserProfile.workemail LIKE :query: OR Company.name LIKE :query:",
                ['query' => '%' . $options['query'] . '%']);
        }

        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andwhere("UserProfile.id IN ({ids:array} )", [
                'ids' => $options['ids'],
            ]);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : 10;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('UserProfile.firstname ASC');

            if ($order['field'] == "firstname") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['UserProfile.firstname ASC']);
                } else {
                    $queryBuilder->orderBy(['UserProfile.firstname DESC']);
                }
            }

            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['UserProfile.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['UserProfile.updated_at DESC']);
                }
            }

        }


        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $items_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $data = $item->toArray();
                    $options = [
                        'user_profile_uuid' => $item->getUuid(),
                        'limit' => 1,
                        'page' => 1,
                    ];
                    //Assignment Ongoing
                    $assignmentResult = Assignment::__getAssignmentOngoing($options);
                    $data['assignment_count'] = $assignmentResult['count'];
                    //Relocation Ongoing
                    $optionRelocation = [
                        'status' => Relocation::STATUS_ONGOING,
                        'user_profile_uuid' => $item->getUuid(),
                        'is_count_all' => true,
                        'limit' => 1,
                        'page' => 1,
                        'is_owner' => true,
                    ];
                    $relocationResult = Relocation::__findWithFilter($optionRelocation);
                    $data['relocation_count'] = isset($relocationResult['total_items']) ? $relocationResult['total_items'] : 0;

                    //Task count
                    $optionsTasks = [
                        'limit' => 1,
                        'statuses' => [Task::STATUS_IN_PROCESS],
                        'owner_uuid' => $item->getUuid(),
                        'is_count_all' => true];
                    $tasksResult = Task::__findWithFilter(false, $optionsTasks);
                    $data['task_count'] = isset($tasksResult['total_items']) ? $tasksResult['total_items'] : 0;

                    //Task status in progress
                    $optionsServices = [
                        'limit' => 1,
                        'statuses' => [Task::STATUS_IN_PROCESS],
                        'user_profile_uuid' =>  $item->getUuid(),
                        'is_owner' =>  true,
                        'has_not_relocation_cancel' =>  true,
                        'service_in_progress' =>  true,
                        'is_count_all' => true];
                    $serviceResult = RelocationServiceCompany::__findWithFilter( $optionsServices);
                    $data['service_count'] = isset($serviceResult['total_items']) ? $serviceResult['total_items']: 0;

                    $items_array[] = $data;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => $items_array,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_pages' => $pagination->total_pages,
                'params' => [
                    '$options' => $options,
                ]
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}
