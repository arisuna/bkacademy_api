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


class WebAclExt extends WebAcl
{

    use ModelTraits;

    const ACL_CREATE = '1';
    const ACL_EDIT = '2';
    const APP_DELETE = '3';
    const ACL_VIEW = '4';

    const STATUS_INACTIVATED = -1;
    const STATUS_ACTIVATED = 1;

    const ACL_SELL_ID = 4;

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
        $instance = new WebAcl();
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

        $this->belongsTo('web_acl_id', 'SMXD\Application\Models\WebAclExt', 'id', [
            'alias' => 'Parent',
            'reusable' => true,
            "foreignKey" => [
                "allowNulls" => true,
                "action" => Relation::ACTION_CASCADE,
            ]
        ]);

        $this->hasMany('id', 'SMXD\Application\Models\WebAclExt', 'web_acl_id', [
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
                    'message' => 'WEB_ACL_NOT_FOUND_TEXT'
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
                'message' => 'SAVE_WEB_ACL_FAIL_TEXT',
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
            'conditions' => '( web_acl_id IS NULL or web_acl_id = 0 ) AND status = 1',
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
            'conditions' => 'status = :status_active:',
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
            'conditions' => 'web_acl_id IS NULL OR web_acl_id = 0'
        ]);
    }


    /**
     * @return Acl
     */
    public function getPreviousSibling()
    {
        return $this->getWebAclId() > 0 ? self::findFirst([
            'conditions' => 'web_acl_id = :web_acl_id: AND id <> :id:',
            'bind' => [
                'web_acl_id' => $this->getWebAclId(),
                'id' => $this->getId()
            ],
            'order' => 'pos DESC'
        ]) : self::findFirst([
            'conditions' => 'web_acl_id IS NULL OR web_acl_id = 0 AND id <> :id:',
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
        return $this->getWebAclId() > 0 ? self::findFirst([
            'conditions' => 'web_acl_id = :web_acl_id: AND id <> :id:',
            'bind' => [
                'web_acl_id' => $this->getWebAclId(),
                'id' => $this->getId()
            ],
            'order' => 'pos ASC'
        ]) : self::findFirst([
            'conditions' => 'web_acl_id IS NULL OR web_acl_id = 0 AND id <> :id:',
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
        if ($parent && $parent->getWebAclId() > 0 && $parent->getWebAclId()) {
            $this->setWebAclId($parent->getWebAclId());
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
        ]);
    }

    /**
     * @param $controller
     * @param $action
     * @return Acl
     */
    public static function __findWebAcl($controller, $action, $lifetime = CacheHelper::__TIME_5_MINUTES)
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
    public static function __findWebAcls($lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        return self::__findWithCache([
            'conditions' => "true"
        ], $lifetime);
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
    public static function __getAppliedItemsByLvl($lvl)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Application\Models\WebAclExt', 'WebAcl');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\SMXD\Application\Models\EndUserLvlWebAclExt', 'EndUserLvlWebAclExt.web_acl_id = WebAcl.id and lvl = '.$lvl, 'EndUserLvlWebAclExt');


        $queryBuilder->andWhere('WebAcl.status = ' . self::STATUS_ACTIVATED);

        $columnsConfig = [
            'WebAcl.id',
            'WebAcl.name',
            'WebAcl.controller',
            'WebAcl.action',
            'WebAcl.status',
            'count_lvl' => 'COUNT(EndUserLvlWebAclExt.id)',
        ];


        $queryBuilder->columns($columnsConfig);
        $queryBuilder->orderBy('WebAcl.pos');
        $queryBuilder->groupBy('WebAcl.id');

        try {
            $items = $queryBuilder->getQuery()->execute();
            $itemsArray = [];
            foreach ($items as $item) {
                $item = $item->toArray();
                $item['is_selected'] = false;
                if (is_numeric($item['count_lvl']) && $item['count_lvl'] > 0) {
                    $item['is_selected'] = true;
                }
                $item['is_active'] = $item['status'] == self::STATUS_ACTIVATED;
                $item['id'] = intval($item['id']);
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
     * @return Acl[]
     */
    public static function __getLevel1Items()
    {
        return self::find([
            'conditions' => 'web_acl_id IS NULL',
            'order' => 'pos ASC',
        ]);
    }


    /**
     * @return Acl[]
     */
    public static function __getChildrenItems($aclId)
    {
        return self::find([
            'conditions' => 'web_acl_id = :web_acl_id:',
            'bind' => [
                'web_acl_id' => $aclId
            ],
            'order' => 'pos ASC',
        ]);
    }

    /**
     * @return bool
     */
    public function checkUniqueControllerAction()
    {
        if(!is_numeric($this->getWebAclId())){
            $data = self::findFirst([
                'conditions' => 'controller = :controller: and action = :action:',
                'bind' => [
                    'controller' => $this->getController(),
                    'action' => $this->getAction(),
                ]
            ]);
        }else{
            $data = self::findFirst([
                'conditions' => 'controller = :controller: and action = :action: and web_acl_id = :web_acl_id:',
                'bind' => [
                    'controller' => $this->getController(),
                    'action' => $this->getAction(),
                    'web_acl_id' => $this->getWebAclId(),
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