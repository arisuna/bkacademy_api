<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model;
use Phalcon\Mvc\Model\Relation;
use Phalcon\Mvc\Model\Validator\PresenceOf;
use Phalcon\Mvc\Model\Validator\Uniqueness;
use Phalcon\Security;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Traits\ModelTraits;

class EndUserLvlWebAclExt extends EndUserLvlWebAcl
{

    const LEVELS = [
        0 => 'LEVEL_0_TEXT',
        1 => 'LEVEL_1_TEXT',
        2 => 'LEVEL_2_TEXT',
        3 => 'LEVEL_3_TEXT',
    ];
    use ModelTraits;
    /**
     *
     */
    public function initialize()
    {

        parent::initialize();


        $this->belongsTo('web_acl_id', 'SMXD\Application\Models\WebAclExt', 'id', [
            [
                'alias' => 'WebAcl',
                'reusable' => true,
                "foreignKey" => [
                    "action" => Relation::ACTION_CASCADE,
                ]
            ]
        ]);
    }

    /**
     * [getTable description]
     * @return [type] [description]
     */
    static function getTable()
    {
        $instance = new EndUserLvlWebAcl();
        return $instance->getSource();
    }


    /**
     * [beforeValidation description]
     * @return [type] [description]
     */
    public function beforeValidation()
    {
        return $this->validationHasFailed() != true;
    }

    /**
     * [beforeSave description]
     * @return [type] [description]
     */
    public function beforeSave()
    {

    }

    /**
     * @return array|AppExt|App
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
                    'message' => 'END_USER_LVL_WEB_ACL_NOT_FOUND'
                ];
            }

            $data = $req->getPut();
        }

        $model->setWebAclId(isset($data['web_acl_id']) ? $data['web_acl_id'] : $model->getWebAclId());
        $model->setLvl(isset($data['lvl']) ? $data['lvl'] : $model->getLvl());

        if ($model->save()) {
            return $model;
        } else {
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }
            $result = [
                'success' => false,
                'message' => 'SAVE_END_USER_LVL_WEB_ACL_FAIL',
                'detail' => $msg
            ];
            return $result;
        }

    }

    /**
     * @return array
     */
    public function saveMulti()
    {
        $req = new Request();
        $magicMethod = 'get' . ucfirst(strtolower($req->getMethod()));

        $lvl = $req->$magicMethod('lvl');

        // -------------
        // 1. Load all current list acl in lvl
        // -------------
        $lvl_acls = EndUserLvlWebAcl::find('lvl=' . $lvl);

        $current_acls = [];
        if (count($lvl_acls)) {
            foreach ($lvl_acls as $g) {
                $current_acls[$g->getWebAclId()] = $g;
            }
        }

        // -------------
        // 2. Filter to get list new ACL and delete unchecked acl
        // -------------
        $acl_post = $req->$magicMethod('acls');
        if (!is_array($current_acls)) {
            $acl_post = [];
        }

        foreach ($acl_post as $k => $acl_id) {
            if (array_key_exists($acl_id, $current_acls)) {
                unset($acl_post[$k]); // Dismiss acl already existed in system
                unset($current_acls[$acl_id]); // Keep old acl from current list
            }
        }

        // Remove acl has been unchecked
        foreach ($current_acls as $acl) {
            if ($acl instanceof EndUserLvlWebAcl)
                $acl->delete();
        }

        $result = ['success' => true, 'message' => 'SAVE_END_USER_LVL_WEB_ACL_SUCCESS', 'acl_success' => [], 'acl_dismiss' => []];

        // -------------
        // 3. Add acl posted to group
        // -------------
        foreach ($acl_post as $acl) {
            $model = new EndUserLvlWebAcl();
            $model->setAclId($acl);
            $model->setUserGroupId($group_id);
            if ($model->save()) {
                $result['acl_success'][] = $acl;
            } else {
                $result['acl_dismiss'][] = $acl;
            }
        }

        if (count($result['acl_dismiss']) == count($acl_post)) {
            $result['message'] = 'SAVE_END_USER_LVL_WEB_ACL_FAIL';
            $result['success'] = false;
        } else if (count($result['acl_dismiss']) > 0 & count($result['acl_success']) > 0) {
            $result['message'] = 'SOME_ACL_NOT_SAVED';
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * @param $user_group_id
     * @param $acl_id
     * @param $company_id
     */
    public static function getItem($lvl, $web_acl_id)
    {
        return self::findFirst([
            'conditions' => 'lvl = :lvl: AND web_acl_id  = :web_acl_id:',
            'bind' => [
                'web_acl_id' => $web_acl_id,
                'lvl' => $lvl,
            ]
        ]);
    }

    /**
     * @return array
     */
    public function __quickUpdate(){
        return ModelHelper::__quickUpdate( $this );
    }

    /**
     * @return array
     */
    public function __quickCreate(){
        return ModelHelper::__quickCreate( $this );
    }

    /**
     * @return array
     */
    public function __quickSave(){
        return ModelHelper::__quickSave( $this );
    }

    /**
     * @return array
     */
    public function __quickRemove(){
        return ModelHelper::__quickRemove( $this );
    }

    /**
     * [getAllPriviligiesGroupCompany description]
     * @param  [type] $user_group_id [user group id of user group]
     * @param  [type] $company_id    [company id of company]
     * @return [type]                [object phalcon : collection of all data in user group acl company table]
     */
    public static function getAllPrivilegiesLvl($lvl)
    {
        return self::find([
            'conditions' => 'lvl = :lvl:',
            'bind' => [
                'lvl' => $lvl,
            ]
        ]);
    }

}