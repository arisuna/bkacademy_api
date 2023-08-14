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
use SMXD\Application\Traits\ModelTraits;

class StaffUserGroupScopeExt extends StaffUserGroupScope
{
    use ModelTraits;
    /**
     *
     */
    public function initialize()
    {

        parent::initialize();


        $this->belongsTo('scope_id', 'SMXD\Application\Models\ScopeExt', 'id', [
            [
                'alias' => 'Scope',
            ]
        ]);

        $this->belongsTo('user_group_id', 'SMXD\Application\Models\StaffUserGroupExt', 'id', [
            'alias' => 'UserGroup',
        ]);
    }

    /**
     * [getTable description]
     * @return [type] [description]
     */
    static function getTable()
    {
        $instance = new StaffUserGroupScope();
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
                    'message' => 'USER_GROUP_NOT_FOUND'
                ];
            }

            $data = $req->getPut();
        }

        $model->setScopeId(isset($data['scope_id']) ? $data['scope_id'] : $model->getScopeId());
        $model->setUserGroupId(isset($data['user_group']) ? $data['user_group'] : $model->getUserGroupId());

        if ($model->save()) {
            return $model;
        } else {
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }
            $result = [
                'success' => false,
                'message' => 'SAVE_USER_GROUP_ACL_FAIL',
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

        $group_id = $req->$magicMethod('group_id');

        // -------------
        // 1. Load all current list acl in group
        // -------------
        $group_acls = StaffUserGroupScopeExt::find('user_group_id=' . $group_id);

        $current_acls = [];
        if (count($group_acls)) {
            foreach ($group_acls as $g) {
                $current_acls[$g->getScopeId()] = $g;
            }
        }

        // -------------
        // 2. Filter to get list new ACL and delete unchecked acl
        // -------------
        $acl_post = $req->$magicMethod('acls');
        if (!is_array($current_acls)) {
            $acl_post = [];
        }

        foreach ($acl_post as $k => $scope_id) {
            if (array_key_exists($scope_id, $current_acls)) {
                unset($acl_post[$k]); // Dismiss acl already existed in system
                unset($current_acls[$scope_id]); // Keep old acl from current list
            }
        }

        // Remove acl has been unchecked
        foreach ($current_acls as $acl) {
            if ($acl instanceof StaffUserGroupScopeExt)
                $acl->delete();
        }

        $result = ['success' => true, 'message' => 'SAVE_USER_GROUP_ACL_SUCCESS', 'acl_success' => [], 'acl_dismiss' => []];

        // -------------
        // 3. Add acl posted to group
        // -------------
        foreach ($acl_post as $acl) {
            $model = new StaffUserGroupScopeExt();
            $model->setScopeId($acl);
            $model->setUserGroupId($group_id);
            if ($model->save()) {
                $result['acl_success'][] = $acl;
            } else {
                $result['acl_dismiss'][] = $acl;
            }
        }

        if (count($result['acl_dismiss']) == count($acl_post)) {
            $result['message'] = 'SAVE_USER_GROUP_ACL_FAIL';
            $result['success'] = false;
        } else if (count($result['acl_dismiss']) > 0 & count($result['acl_success']) > 0) {
            $result['message'] = 'SOME_ACL_NOT_SAVED';
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * @param $user_group_id
     * @param $scope_id
     * @param $company_id
     */
    public static function getItem($user_group_id, $scope_id)
    {
        return self::findFirst([
            'conditions' => 'user_group_id = :user_group_id: AND scope_id  = :scope_id:',
            'bind' => [
                'scope_id' => $scope_id,
                'user_group_id' => $user_group_id,
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

}