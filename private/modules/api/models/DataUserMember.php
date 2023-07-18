<?php

namespace Reloday\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Api\Models\ModuleModel;

class DataUserMember extends \Reloday\Application\Models\DataUserMemberExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const MEMBER_TYPE_INITIATOR = 1;
    const MEMBER_TYPE_OWNER = 2;
    const MEMBER_TYPE_ASSIGNEE = 3;
    const MEMBER_TYPE_APPROVER = 4;
    const MEMBER_TYPE_REPORTER = 5;
    const MEMBER_TYPE_VIEWER = 6;


    public function initialize(){
        parent::initialize();
    }
    /**
     * [get description]
     * @param  {[type]} $name [description]
     * @return {[type]}       [description]
     */
    public function get($name){
        return $this->$name;
    }
    /**
     * [set description]
     * @param {[type]} $name     [description]
     * @param {[type]} $variable [description]
     */
    public function set($name, $value){
        $this->$name = $value;
    }


    /**
     * get viewer of data
     */
    public static function getViewersIds( $object_uuid ){
        $data = self::find([
            "columns" => "id, user_login_id, user_profile_id",
            "conditions" => "member_type_id = :member_type_id: AND object_uuid = :object_uuid:",
            "bind"  => [
                'member_type_id' => self::MEMBER_TYPE_VIEWER,
                'object_uuid' => $object_uuid,
            ]
        ]);
        $ids = [];
        foreach( $data as $item ){
            $ids[$item->user_profile_id] = $item->user_profile_id;
        }
        return $ids;
    }

    /**
     * @param $model
     * @param null $transactionDb transaction object phalcon
     * @return bool
     */
    public function createOwnerFromModel( $model ,  $transactionDb = null ){
        $data_user_member = $this;
        $data_user_member->set('object_uuid', $model->getUuid());
        $data_user_member->set('object_name', $model->getSource());
        $data_user_member->set('user_profile_id', ModuleModel::$user_profile->getId());
        $data_user_member->set('user_profile_uuid', ModuleModel::$user_profile->getUuid());
        $data_user_member->set('user_login_id', ModuleModel::$user_login->getId());
        $data_user_member->set('member_type_id', DataUserMember::MEMBER_TYPE_OWNER);
        if( !is_null($transactionDb) && is_object($transactionDb)){
            $data_user_member->setTransaction($transactionDb);
        }
        if ($data_user_member->save()) {
            return true;
        } else {
            if( !is_null($transactionDb) && is_object($transactionDb)){
                $transactionDb->rollback('USER_MEMBER_CAN_NOT_SAVE_TEXT');
            }
            return false;
        }
    }

}
