<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class EmailTemplateDefaultExt extends EmailTemplateDefault
{

    use ModelTraits;

    /** status archived */
    const STATUS_ARCHIVED = -1;
    /** status active */
    const STATUS_ACTIVE = 1;
    /** status draft */
    const STATUS_DRAFT = 0;

    const
        CREATION_APPLICATION_SUCCESS = 'CREATION_APPLICATION_SUCCESS',
        RESET_PASSWORD = 'RESET_PASSWORD',
        CONFIRM_INSCRIPTON = 'CONFIRM_INSCRIPTON',
        CONFIRM_APPLICATION = 'CONFIRM_APPLICATION',
        NOTIFICATION = 'NOTIFICATION',
        FORGOT_PASSWORD = 'FORGOT_PASSWORD',
        INFORMATION_LOGIN = 'INFORMATION_LOGIN',
        ADD_OWNER = 'ADD_OWNER',
        ADD_REPORTER = 'ADD_REPORTER',
        ADD_VIEWER = 'ADD_VIEWER',
        ADD_SERVICE = 'ADD_SERVICE',
        CREATE_OBJECT = 'CREATE_OBJECT',
        ADD_COMMENT = 'ADD_COMMENT',
        UPDATE_OBJECT = 'UPDATE_OBJECT',
        DELETE_OBJECT = 'DELETE_OBJECT',
        DELETE_SERVICE = 'DELETE_SERVICE',
        TAG_USER = 'TAG_USER',
        UPDATE_OWNER = 'UPDATE_OWNER',
        UPDATE_VIEWER = 'UPDATE_VIEWER',
        UPDATE_REPORTER = 'UPDATE_REPORTER',
        UPDATE_COMMENT = 'UPDATE_COMMENT',
        DELETE_COMMENT = 'DELETE_COMMENT',
        SEND_QUOTE = 'SEND_QUOTE',
        SEND_INVOICE = 'SEND_INVOICE',
        SEND_NEEDS_ASSESSMENT = 'SEND_NEEDS_ASSESSMENT',
        SEND_COMPLAIN = 'SEND_COMPLAIN',
        REPLY_NEEDS_ASSESSMENT = 'REPLY_NEEDS_ASSESSMENT',
        ADD_EXTERNAL_COMMENT = 'ADD_EXTERNAL_COMMENT',
        ADD_EXTERNAL_COMMENT_TEMPLATE = 'ADD_EXTERNAL_COMMENT_TEMPLATE',
        SEND_PROPERTY = 'SEND_PROPERTY',
        REPLY_PROPERTY = 'REPLY_PROPERTY',
        SEND_NEW_PASSWORD = 'SEND_NEW_PASSWORD',
        REMINDER_NOTIFICATION = 'REMINDER_NOTIFICATION',
        SUBDOMAIN_UPDATED_FORWARD_EMAIL = 'SUBDOMAIN_UPDATED_FORWARD_EMAIL',
        INVITE_ASSIGNEE = 'INVITE_ASSIGNEE';

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
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

//        $this->addBehavior(new SoftDelete([
//            'field' => 'status',
//            'value' => self::STATUS_ARCHIVED
//        ]));

        $this->hasMany('id', 'SMXD\Application\Models\EmailTemplate', 'email_template_default_id', ['alias' => 'EmailTemplate']);
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {

        ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }

    public function remove()
    {
        try {
            if ($this->delete()) {
                return ['success' => true, "msg" => "DATA_DELETE_SUCCESS_TEXT"];
            } else {
                $msg = [];
                foreach ($this->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                return ['success' => false, "msg" => "DATA_DELETE_FAIL_TEXT", "detail" => $msg];
            }
        } catch (\PDOException $e) {
            return ['success' => false, "msg" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, "msg" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        }
    }
}
