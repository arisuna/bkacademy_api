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

class StaffUserGroupZoneExt extends StaffUserGroupZone
{
    use ModelTraits;
    /**
     *
     */
    public function initialize()
    {

        parent::initialize();


        $this->belongsTo('business_zone_id', 'SMXD\Application\Models\BusinessZoneExt', 'id', [
            [
                'alias' => 'BusinessZone',
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
        $instance = new StaffUserGroupZone();
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

        $model->setBusinessZoneId(isset($data['business_zone_id']) ? $data['business_zone_id'] : $model->getBusinessZoneId());
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
        $group_acls = StaffUserGroupZoneExt::find('user_group_id=' . $group_id);

        $current_acls = [];
        if (count($group_acls)) {
            foreach ($group_acls as $g) {
                $current_acls[$g->getBusinessZoneId()] = $g;
            }
        }

        // -------------
        // 2. Filter to get list new ACL and delete unchecked acl
        // -------------
        $acl_post = $req->$magicMethod('acls');
        if (!is_array($current_acls)) {
            $acl_post = [];
        }

        foreach ($acl_post as $k => $business_zone_id) {
            if (array_key_exists($business_zone_id, $current_acls)) {
                unset($acl_post[$k]); // Dismiss acl already existed in system
                unset($current_acls[$business_zone_id]); // Keep old acl from current list
            }
        }

        // Remove acl has been unchecked
        foreach ($current_acls as $acl) {
            if ($acl instanceof StaffUserGroupZoneExt)
                $acl->delete();
        }

        $result = ['success' => true, 'message' => 'SAVE_USER_GROUP_ACL_SUCCESS', 'acl_success' => [], 'acl_dismiss' => []];

        // -------------
        // 3. Add acl posted to group
        // -------------
        foreach ($acl_post as $acl) {
            $model = new StaffUserGroupZoneExt();
            $model->setBusinessZoneId($acl);
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
     * @param $business_zone_id
     * @param $company_id
     */
    public static function getItem($user_group_id, $business_zone_id)
    {
        return self::findFirst([
            'conditions' => 'user_group_id = :user_group_id: AND business_zone_id  = :business_zone_id:',
            'bind' => [
                'business_zone_id' => $business_zone_id,
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