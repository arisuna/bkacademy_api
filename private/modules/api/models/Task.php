<?php

namespace Reloday\Api\Models;

use \Aws;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Gms\Models\ModuleModel as ModuleModel;
use Reloday\Application\Lib\Helpers as Helpers;

use Reloday\Api\Models\DataUserMember;
use Phalcon\Utils\Slug as PhpSlug;
use \Phalcon\Mvc\Model\Transaction\Failed as TransationFailed;
use \Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class Task extends \Reloday\Application\Models\TaskExt
{
    public $due_at_time = 0;

    /**
     * initialize all for all
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('owner_id',
            'Reloday\Api\Models\UserProfile',
            'id', [
                'alias' => 'UserProfile'
            ]);
        $this->belongsTo('company_id',
            'Reloday\Api\Models\Company',
            'id', [
                'alias' => 'Company'
            ]);
        $this->hasMany('id',
            'Reloday\Api\Models\Task',
            'parent_task_id', [
                'alias' => 'SubTask'
            ]);
        $this->hasManyToMany(
            'uuid', 'Reloday\Api\Models\DataUserMember',
            'object_uuid', 'user_profile_id',
            'Reloday\Api\Models\UserProfile', 'id', [
            'alias' => 'Members'
        ]);
    }


    /**
     * [get description]
     * @param  {[type]} $name [description]
     * @return {[type]}       [description]
     */
    public function get($name)
    {
        return $this->$name;
    }

    /**
     * [set description]
     * @param {[type]} $name     [description]
     * @param {[type]} $variable [description]
     */
    public function set($name, $value)
    {
        $this->$name = $value;
    }


    /**
     *
     */
    public function afterFetch()
    {
        parent::afterFetch();
        if ($this->getDueAt() != '') {
            $this->due_at_time = strtotime($this->getDueAt());
        }
    }

    public function getDueAtTime()
    {
        if ($this->getDueAt() != '') {
            return strtotime($this->getDueAt());
        }
    }

    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkViewPermissionOfUser($user_login_id = 0)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_login_id = :user_login_id: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_login_id' => $user_login_id,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_INITIATOR,
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_VIEWER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }


    /**
     * @return bool
     * check exist in cloud
     * @deprecated
     */
    public static function __existInCloud($task_uuid)
    {
        return false;
    }

    /**
     * @deprecated
     * transfert to cloud
     */
    public function transfertToCloud()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getSenderEmailComments()
    {
        return "task_" . base64_encode($this->getUuid()) . "@" . getenv('SENDER_COMMENT_DOMAIN');
    }

    /**
     * URL sender mail of profile
     * @param $profile
     * @return string
     */
    public function getUserSenderEmailComments($profile)
    {
        $prefix = PhpSlug::generate($profile->getFirstname() . " " . $profile->getLastname());
        $user_uuid_base64 = base64_encode($profile->getUuid());
        return $prefix . "+" . $user_uuid_base64 . "+" . $this->getSenderEmailComments();
    }

    /**
     * @return string
     */
    public function getFrontendUrl()
    {
        $url = RelodayUrlHelper::__getMainUrl() . "/#/app/tasks/page/" . $this->getUuid();

        if ($this->getCompany()) {
            if ($this->getCompany()->getApp()) {
                $url = $this->getCompany()->getApp()->getFrontendUrl() . "/#/app/tasks/page/" . $this->getUuid();
            }
        }
        return $url;
    }

    /**
     * @return mixed
     */
    public static function __getDi()
    {
        return \Phalcon\DI::getDefault();
    }

}
