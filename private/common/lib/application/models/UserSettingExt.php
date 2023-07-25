<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;

class UserSettingExt extends UserSetting
{
    /** status archived */
    const STATUS_ARCHIVED = -1;
    /** status active */
    const STATUS_ACTIVE = 1;
    /** status draft */
    const STATUS_DRAFT = 0;

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
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

        $this->addBehavior(new SoftDelete([
            'field' => 'status',
            'value' => self::STATUS_ARCHIVED
        ]));
    }

    /**
     * @return array
     */
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
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __update($custom = [])
    {
        $model = $this;
        if (!($model->getId() > 0)) {
            return [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT',
            ];
        } else {
            return $model->__saveData($custom);
        }
    }

    /**
     * @param array $custom
     * @return array
     */
    public function __create($custom = [])
    {
        $model = $this;
        return $model->__saveData($custom);
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __saveData($custom = [])
    {

        $req = new Request();
        $model = $this;

        if (!($model->getId() > 0)) {
            if ($req->isPut()) {
                $data_id = isset($custom['user_setting_id']) && $custom['user_setting_id'] > 0 ? $custom['user_setting_id'] : $req->getPut('user_setting_id');
                if ($data_id > 0) {
                    $model = $this->findFirstById($data_id);
                    if (!$model instanceof $this) {
                        return [
                            'success' => false,
                            'message' => 'DATA_NOT_FOUND_TEXT',
                        ];
                    }
                }
            }
        }

        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
                if ($model->getUuid() == '') {
                    $random = new Random;
                    $uuid = $random->uuid();
                    $model->setUuid($uuid);
                }
            }
        }

        $model->setData($custom);

        try {
            if ($model->getId() == null) {

            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * @return array
     */
    public function __quickUpdate()
    {
        return ModelHelper::__quickUpdate($this);
    }

    /**
     * @return array
     */
    public function __quickCreate()
    {
        return ModelHelper::__quickCreate($this);
    }

    /**
     * @return array
     */
    public function __quickSave()
    {
        return ModelHelper::__quickSave($this);
    }

    /**
     * @return array
     */
    public function __quickRemove()
    {
        return ModelHelper::__quickRemove($this);
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {

        /** @var [varchar] [set uunique id] */
        if (property_exists($this, 'uuid') && method_exists($this, 'getUuid') && method_exists($this, 'setUuid')) {
            if (property_exists($this, 'uuid') && method_exists($this, 'getUuid') && method_exists($this, 'setUuid')) {
                if ($this->getUuid() == '') {
                    $random = new Random;
                    $uuid = $random->uuid();
                    $this->setUuid($uuid);
                }
            }
        }
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $this->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($this);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($this);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id' &&
                $field_name != 'uuid' &&
                $field_name != 'created_at' &&
                $field_name != 'updated_at' &&
                $field_name != 'email' &&
                $field_name != "password") {

                if (!isset($fields_numeric[$field_name])) {
                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $this->get($field_name));
                    $field_name_value = $field_name_value != '' ? $field_name_value : $this->get($field_name);
                    $this->set($field_name, $field_name_value);

                } else {

                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $this->get($field_name));
                    if ($field_name_value != '' && !is_null($field_name_value)) {
                        $this->set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }


}
