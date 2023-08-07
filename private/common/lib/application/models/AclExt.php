<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Security;
use Phalcon\Validation;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Relation;
use SMXD\Hr\Models\ModuleModel;
use SMXD\Application\Traits\ModelTraits;


class AclExt extends Acl
{

    use ModelTraits;

    const ACL_CREATE = '1';
    const ACL_EDIT = '2';
    const APP_DELETE = '3';
    const ACL_VIEW = '4';

    const ADMIN = 1;

    const STATUS_INACTIVATED = -1;
    const STATUS_ACTIVATED = 1;

    static $aclGroupMap = [
        self::APP_DELETE => 'Delete',
        self::ACL_VIEW => 'View',
        self::ACL_EDIT => 'Edit',
        self::ACL_CREATE => 'Create'
    ];

    /**
     * @return string
     */
    static function getTable()
    {
        $instance = new Acl();
        return $instance->getSource();
    }

    /**
     *
     */
    public function initialize()
    {

        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable(
            array(
                'beforeValidationOnCreate' => array(
                    'field' => array(
                        'created_at', 'updated_at'
                    ),
                    'format' => 'Y-m-d H:i:s'
                ),
                'beforeValidationOnUpdate' => array(
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                )
            )
        ));

        $this->belongsTo('acl_id', 'SMXD\Application\Models\AclExt', 'id', [
            'alias' => 'Parent',
            'reusable' => true,
            "foreignKey" => [
                "allowNulls" => true,
                "action" => Relation::ACTION_CASCADE,
            ]
        ]);

        $this->hasMany('id', 'SMXD\Application\Models\AclExt', 'acl_id', [
            'alias' => 'Children',
            'reusable' => true,
            'params' => [
                'order' => 'pos ASC'
            ]
        ]);
    }
    /**
     * @param array $custom
     */
    public function setData( $custom = []){

         ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }

    /**
     * @return mixed
     */
    public function validation()
    {
        $validator = new Validation();
        if ($this->getController() || $this->getAction())
            $validator->add([
                'controller',
                'action'

            ], new UniquenessValidator([
                'field' => $this,
                'message' => 'CONTROLLER_ACTION_UNIQUE_TEXT'
            ]));

        $validator->add('label', new PresenceOfValidator([
            'model' => $this,
            'message' => 'ACL_LABEL_EMPTIED_TEXT'
        ]));

        return $this->validate($validator);
    }

    /**
     * @return array|Acl|AclExt
     */
    public function __save()
    {
        $req = new Request();
        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) { // Request update
            $model = $this->findFirst($req->getPut('id'));
            if (!$model instanceof $this) {
                return [
                    'success' => false,
                    'message' => 'ACL_NOT_FOUND_TEXT'
                ];
            }

            $data = $req->getPut();
        }

        $model->setController(array_key_exists('controller', $data) ? $data['controller'] : $model->getController());
        $model->setAction(array_key_exists('action', $data) ? $data['action'] : $model->getAction());
        $model->setGroup(isset($data['group']) ? $data['group'] : $model->getGroup());
        $model->setLabel(isset($data['label']) ? $data['label'] : $model->getLabel());
        $model->setStatus(isset($data['status']) ? $data['status'] : $model->getStatus());

        if ($model->save()) {
            return $model;
        } else {
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }
            $result = [
                'success' => false,
                'message' => 'SAVE_ACL_FAIL_TEXT',
                'detail' => $msg
            ];
            return $result;
        }

    }

    /**
     * [getTreeAcl description]
     * @return [type] [description]
     */
    public static function getTreeAcl()
    {
        $ids = [];
        $acl_list = self::find([
            'conditions' => 'is_admin <> 1 AND ( acl_id IS NULL or acl_id = 0 ) AND status = 1',
            'order' => 'pos ASC'
        ]);
        $list_controller_action = array();
        foreach ($acl_list as $item) {
            if ($item->getLvl() == 1) {
                $list_controller_action[$item->getId()]['lock'] = false;
                $list_controller_action[$item->getId()]['is_active'] = true;
                $list_controller_action[$item->getId()]['id'] = $item->getId();
                $list_controller_action[$item->getId()]['module'] = $item->getName();
                $list_controller_action[$item->getId()]['name'] = $item->getLabel();
                $list_controller_action[$item->getId()]['controller'] = $item->getController();
                $list_controller_action[$item->getId()]['action'] = $item->getAction();
                $list_controller_action[$item->getId()]['visible'] = $item->getStatus();
                $list_controller_action[$item->getId()]['position'] = $item->getPos();
                $list_controller_action[$item->getId()]['sub'] = $item->getSub($ids);
            }
        }
        return $list_controller_action;
    }

    /**
     * [getSub description]
     * @return [type] [description]
     */
    public function getSub($idList = [])
    {
        $acl_list = $this->getChildren([
            'conditions' => 'status = :status_active: AND is_admin <> 1',
            'order' => "pos ASC",
            'bind' => [
                'status_active' => self::STATUS_ACTIVATED,
            ],
        ]);


        $list_controller_action = [];
        if (count($acl_list)) {
            foreach ($acl_list as $item) {
                if($idList){
                    $list_controller_action[$item->getId()]['lock'] = false;
                    $list_controller_action[$item->getId()]['is_active'] = true;
                    $list_controller_action[$item->getId()]['id'] = $item->getId();
                    $list_controller_action[$item->getId()]['module'] = $item->getName();
                    $list_controller_action[$item->getId()]['name'] = $item->getLabel();
                    $list_controller_action[$item->getId()]['controller'] = $item->getController();
                    $list_controller_action[$item->getId()]['action'] = $item->getAction();
                    $list_controller_action[$item->getId()]['visible'] = $item->getStatus();
                    $list_controller_action[$item->getId()]['position'] = $item->getPos();
                    $sub = $item->getSub();
                    if (count($sub) > 0) {
                        $list_controller_action[$item->getId()]['sub'] = $item->getSub($idList);
                    }
                }else{
                    $list_controller_action[$item->getId()]['id'] = $item->getId();
                    $list_controller_action[$item->getId()]['module'] = $item->getName();
                    $list_controller_action[$item->getId()]['name'] = $item->getLabel();
                    $list_controller_action[$item->getId()]['controller'] = $item->getController();
                    $list_controller_action[$item->getId()]['action'] = $item->getAction();
                    $list_controller_action[$item->getId()]['visible'] = $item->getStatus();
                    $list_controller_action[$item->getId()]['position'] = $item->getPos();
                    $sub = $item->getSub();
                    if (count($sub) > 0) {
                        $list_controller_action[$item->getId()]['sub'] = $item->getSub();
                    }
                }

            }
        }
        return array_values($list_controller_action);
    }

    /**
     *
     */
    public function countTotalSibilings()
    {
        return $this->getParent() ? $this->getParent()->countChildren() : self::count([
            'conditions' => 'acl_id IS NULL OR acl_id = 0'
        ]);
    }


    /**
     * @return Acl
     */
    public function getPreviousSibling()
    {
        return $this->getAclId() > 0 ? self::findFirst([
            'conditions' => 'acl_id = :acl_id: AND id <> :id:',
            'bind' => [
                'acl_id' => $this->getAclId(),
                'id' => $this->getId()
            ],
            'order' => 'pos DESC'
        ]) : self::findFirst([
            'conditions' => 'acl_id IS NULL OR acl_id = 0 AND id <> :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'pos DESC'
        ]);
    }

    /**
     * @return Acl
     */
    public function getNextSibling()
    {
        return $this->getAclId() > 0 ? self::findFirst([
            'conditions' => 'acl_id = :acl_id: AND id <> :id:',
            'bind' => [
                'acl_id' => $this->getAclId(),
                'id' => $this->getId()
            ],
            'order' => 'pos ASC'
        ]) : self::findFirst([
            'conditions' => 'acl_id IS NULL OR acl_id = 0 AND id <> :id:',
            'bind' => [
                'id' => $this->getId()
            ],
            'order' => 'pos ASC'
        ]);
    }

    /**
     *
     */
    public function moveUp()
    {
        $previousSibling = $this->getPreviousSibling();
        if ($previousSibling) {
            $previousSibling->setPos($previousSibling->getPos() + 1 < $previousSibling->countTotalSibilings() ? $previousSibling->getPos() + 1 : $previousSibling->getPos());
            $resultSave = $previousSibling->__quickUpdate();
        }

        $this->setPos($this->getPos() - 1 > 0 ? $this->getPos() - 1 : $this->getPos());
        $resultSave = $this->__quickUpdate();
        return $resultSave;
    }

    /**
     *
     */
    public function moveDown()
    {
        $nextSibling = $this->getNextSibling();
        if ($nextSibling) {
            $nextSibling->setPos($nextSibling->getPos() - 1 > 0 ? $nextSibling->getPos() - 1 : $nextSibling->getPos());
            $resultSave = $nextSibling->__quickUpdate();
        }

        $this->setPos($this->getPos() + 1 > 0 ? $this->getPos() + 1 : $this->getPos());
        $resultSave = $this->__quickUpdate();
        return $resultSave;
    }

    /**
     *
     */
    public function levelUp()
    {
        $parent = $this->getParent();
        if ($parent && $parent->getAclId() > 0 && $parent->getAclId()) {
            $this->setAclId($parent->getAclId());
            $this->setLvl($parent->getLvl());
            $this->setPos($parent->countTotalSibilings() + 1);
        }
        return $resultSave = $this->__quickUpdate();
    }

    /**
     *
     */
    public function setNextPosition()
    {
        if ($this->getPreviousSibling()) {
            $this->setPos($this->getPreviousSibling()->getPos() + 1);
        } else {
            $this->setPos($this->countTotalSibilings() ? $this->countTotalSibilings() + 1 : 1);
        }
    }

    /**
     * @param $controller
     * @param $action
     */
    public static function getAclByControllerAction($controller, $action)
    {
        return self::findFirst([
            'conditions' => 'controller = :controller: AND action = :action:',
            'bind' => [
                'controller' => $controller,
                'action' => $action
            ],
            "cache" => [
                "key" => CacheHelper::getAclCacheItemCtrActionName($controller, $action),
                "lifetime" => 86400,
            ],
        ]);
    }

    /**
     * @param $controller
     * @param $action
     * @return Acl
     */
    public static function __findCrmAcl($controller, $action, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        return self::__findFirstWithCache([
            'conditions' => 'controller = :controller: AND action = :action: AND is_hr = :is_hr_yes:',
            'bind' => [
                'controller' => $controller,
                'action' => $action,
                'is_hr_yes' => ModelHelper::YES
            ]
        ], $lifetime);
    }

    /**
     * @param $controller
     * @param $action
     * @return Acl
     */
    public static function __findAdminAcl($controller, $action, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        return self::__findFirstWithCache([
            'conditions' => 'controller = :controller: AND action = :action:',
            'bind' => [
                'controller' => $controller,
                'action' => $action
            ]
        ], $lifetime);
    }

    /**
     * @param $controller
     * @param $action
     * @return Acl
     */
    public static function __findAdminAcls($lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        return self::__findWithCache([
        ], $lifetime);
    }

    /**
     * @param $controller
     * @param $action
     * @return Acl
     */
    public static function __findCrmAcls($lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        return self::__findWithCache([
            'conditions' => "is_admin <> 1"
        ], $lifetime);
    }

    /**
     * @param $options
     * @param bool $withCache
     * @return array
     */
    public static function __findFirstWithFilters($options, $withCache = true)
    {
        $modelManager = (new self())->getModelsManager();
        $queryBuilder = $modelManager->createBuilder();
        $queryBuilder->distinct(true);
        $queryBuilder->addFrom('\SMXD\Application\Models\AclExt', 'Acl');
        $queryBuilder->innerjoin('\SMXD\Application\Models\UserGroupAclExt', 'UserGroupAcl.acl_id = Acl.id', 'UserGroupAcl');
        $queryBuilder->innerjoin('\SMXD\Application\Models\UserGroupExt', 'UserGroup.id = UserGroupAcl.user_group_id', 'UserGroup');
        $queryBuilder->innerJoin('\SMXD\Application\Models\ModuleAclExt', 'ModuleAcl.acl_id = Acl.id', 'ModuleAcl');
        $queryBuilder->innerJoin('\SMXD\Application\Models\ModuleExt', 'Module.id = ModuleAcl.module_id', 'Module');
        $queryBuilder->innerJoin('\SMXD\Application\Models\PlanModuleExt', 'PlanModule.module_id = Module.id', 'PlanModule');
        $queryBuilder->innerJoin('\SMXD\Application\Models\PlanExt', 'PlanModule.plan_id = Plan.id', 'Plan');
        $queryBuilder->innerJoin('\SMXD\Application\Models\SubscriptionExt', 'Subscription.plan_id = Subscription.id', 'Subscription');
        $queryBuilder->innerJoin('\SMXD\Application\Models\CompanyExt', 'Subscription.company_id = Company.id', 'Company');

        $queryBuilder->where('Company.company_id = :company_id:');
        $queryBuilder->andwhere('Subscription.status = :subscription_active:');
        $queryBuilder->andwhere('Acl.controller = :controller:');
        $queryBuilder->andwhere('Acl.action = :action:');
        $queryBuilder->limit(1);


        $aclItems = [];

        try {
            if ($withCache) {
                $aclItems = $queryBuilder->getQuery()->cache([
                    'key' => '__ACL_ITEM_',
                    "lifetime" => CacheHelper::__TIME_5_MINUTES])->execute($bindArray);
            } else {
                $aclItems = $queryBuilder->getQuery()->execute($bindArray);
            }
            return reset($aclItems);
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

    /**
     * @return int
     */
    public function getLevelCalculated()
    {
        $level = 1;
        if ($this->getParent()) {
            $level++;
            if ($this->getParent()->getParent()) {
                $level++;
                if ($this->getParent()->getParent()->getParent()) {
                    $level++;
                    if ($this->getParent()->getParent()->getParent()->getParent()) {
                        $level++;
                    } else {
                        return $level;
                    }
                } else {
                    return $level;
                }
            } else {
                return $level;
            }
        }
        return $level;
    }

    /**
     * @param $pos
     * @return AclExt
     */
    public function setPosition($pos)
    {
        return $this->setPos($pos);
    }

    /**
     * @param $userGroupId
     * @return array|mixed
     */
    public static function __getItemsByUserGroupId($userGroupId)
    {
        $userGroup = UserGroupExt::findFirstById($userGroupId);
        if (!$userGroup) {
            return [];
        }
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Application\Models\AclExt', 'Acl');
        $queryBuilder->distinct(true);

        if ($userGroup->getCompanyType() == CompanyTypeExt::TYPE_GMS) {
            $queryBuilder->where('Acl.is_gms = ' . ModelHelper::YES);
        }

        if ($userGroup->getCompanyType() == CompanyTypeExt::TYPE_HR) {
            $queryBuilder->where('Acl.is_hr = ' . ModelHelper::YES);
        }

        $queryBuilder->andWhere('Acl.status = ' . self::STATUS_ACTIVATED);

//        if ($userGroup->isAdmin() == false) {
//            $queryBuilder->andWhere('Acl.is_admin_only = ' . ModelHelper::NO);
//        }

        $queryBuilder->orderBy('Acl.pos');

        try {
            $items = $queryBuilder->getQuery()->execute();
            return $items;
        } catch (\Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        }
    }

    /**
     * @return Acl[]
     */
    public function getDescendants()
    {
        $array = [];
        $items = self::__getChildrenItems($this->getId());
        if (count($items) > 0) {
            $array = $items->toArray();
            foreach ($items as $item) {
                $items_level1 = $item->getDescendants();
                $array = array_merge($array, $items_level1);
            }
        }
        return $array;
    }

    /**
     * @param $userGroupId
     * @return array|mixed
     */
    public static function __getAppliedItemsByUserGroup($userGroupId)
    {
        $userGroup = UserGroupExt::findFirstById($userGroupId);
        if (!$userGroup) {
            return [];
        }
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Application\Models\AclExt', 'Acl');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\SMXD\Application\Models\UserGroupAclExt', 'UserGroupAcl.acl_id = Acl.id', 'UserGroupAcl');
        $queryBuilder->innerJoin('\SMXD\Application\Models\UserGroupExt', 'UserGroup.id = UserGroupAcl.user_group_id AND UserGroup.id = ' . $userGroupId, 'UserGroup');


        $queryBuilder->andWhere('Acl.status = ' . self::STATUS_ACTIVATED);

//        if ($userGroup->isAdmin() == false) {
//            $queryBuilder->andWhere('Acl.is_admin_only = ' . ModelHelper::NO);
//        }

        $columnsConfig = [
            'Acl.id',
            'Acl.name',
            'Acl.controller',
            'Acl.action',
            'Acl.status',
            'count_group' => 'COUNT(UserGroupAcl.id)',
        ];


        $queryBuilder->columns($columnsConfig);
        $queryBuilder->orderBy('Acl.pos');
        $queryBuilder->groupBy('Acl.id');

        try {
            $items = $queryBuilder->getQuery()->execute();
            $itemsArray = [];
            foreach ($items as $item) {
                $item = $item->toArray();
                $item['is_selected'] = false;
                if (is_numeric($item['count_group']) && $item['count_group'] > 0) {
                    $item['is_selected'] = true;
                }
                $item['is_active'] = $item['status'] == self::STATUS_ACTIVATED;
                $item['id'] = intval($item['id']);
                $item['is_hr'] = intval($item['is_hr']);
                $item['is_gms'] = intval($item['is_gms']);
                $item['is_admin_only'] = false;
                $itemsArray[] = $item;
            }
            return $itemsArray;
        } catch (\Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        }
    }

    /**
     * @return \Project\Application\Models\Acl[]
     */
    public static function __getLevel1ItemsByCompanyType($companyTypeId)
    {
        return self::find([
            'conditions' => 'acl_id IS NULL',
            'bind' => [
                'yes' => ModelHelper::YES,
            ],
            'order' => 'pos ASC',
        ]);
    }

    /**
     * @return Acl[]
     */
    public static function __getLevel1Items()
    {
        return self::find([
            'conditions' => 'acl_id IS NULL',
            'order' => 'pos ASC',
        ]);
    }


    /**
     * @return Acl[]
     */
    public static function __getChildrenItems($aclId)
    {
        return self::find([
            'conditions' => 'acl_id = :acl_id:',
            'bind' => [
                'acl_id' => $aclId
            ],
            'order' => 'pos ASC',
        ]);
    }

    /**
     * @return bool
     */
    public function checkUniqueControllerAction()
    {
        if(!is_numeric($this->getAclId())){
            $data = self::findFirst([
                'conditions' => 'controller = :controller: and action = :action: and acl_id is null',
                'bind' => [
                    'controller' => $this->getController(),
                    'action' => $this->getAction(),
                ]
            ]);
        }else{
            $data = self::findFirst([
                'conditions' => 'controller = :controller: and action = :action: and acl_id = :acl_id:',
                'bind' => [
                    'controller' => $this->getController(),
                    'action' => $this->getAction(),
                    'acl_id' => $this->getAclId(),
                ]
            ]);
        }


        if ($data) {
            return false;
        } else {
            return true;
        }
    }
}