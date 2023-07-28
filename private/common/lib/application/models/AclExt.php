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
     * [getTreeGms description]
     * @return [type] [description]
     */
    public static function getTreeHr($subscription)
    {
        $ids = [];
        $subscription_acls = $subscription->__loadListPermission(ModuleModel::$user);
        if (count($subscription_acls) > 0) {
            foreach ($subscription_acls as $subscription_acl) {
                $ids[$subscription_acl->getId()] = $subscription_acl;
            }
        }
        $acl_list = self::find([
            'conditions' => 'is_hr = 1 AND ( acl_id IS NULL or acl_id = 0 ) AND status = 1',
            'order' => 'pos ASC'
        ]);
        $list_controller_action = array();
        foreach ($acl_list as $item) {
            if ($item->getLvl() == 1) {
                if (isset($ids[$item->getId()]) && $ids[$item->getId()] instanceof Acl) {
                    $list_controller_action[$item->getId()]['lock'] = false;
                    $list_controller_action[$item->getId()]['is_active'] = true;
                } else {
                    $list_controller_action[$item->getId()]['lock'] = true;
                    $list_controller_action[$item->getId()]['is_active'] = false;
                }
                $list_controller_action[$item->getId()]['id'] = $item->getId();
                $list_controller_action[$item->getId()]['module'] = $item->getName();
                $list_controller_action[$item->getId()]['name'] = $item->getLabel();
                $list_controller_action[$item->getId()]['controller'] = $item->getController();
                $list_controller_action[$item->getId()]['action'] = $item->getAction();
                $list_controller_action[$item->getId()]['visible'] = $item->getStatus();
                $list_controller_action[$item->getId()]['position'] = $item->getPos();
                $list_controller_action[$item->getId()]['sub'] = $item->getSub(CompanyTypeExt::TYPE_HR);
            }
        }
        return $list_controller_action;
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
}