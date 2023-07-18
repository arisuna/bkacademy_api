<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ServiceFieldType;

class ServiceField extends \Reloday\Application\Models\ServiceFieldExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('attributes_id', 'Reloday\Gms\Models\Attributes', 'id', ['alias' => 'Attribute']);
        $this->belongsTo('service_field_group_id', 'Reloday\Gms\Models\ServiceFieldGroup', 'id', ['alias' => 'ServiceFieldGroup']);
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
    public function __save($custom = [])
    {

        $req = new Request();
        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            // 
            $data_id = isset($custom['data_id']) && $custom['data_id'] > 0 ? $custom['data_id'] : $req->getPut('id');

            if ($data_id > 0) {
                $model = $this->findFirstById($data_id);
                if (!$model instanceof $this) {
                    return [
                        'success' => false,
                        'message' => 'DATA_NOT_FOUND_TEXT',
                    ];
                }
            }
            $data = $req->getPut();
        }
        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            $uuid = isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid());
            if ($uuid == '') {
                $random = new Random;
                $uuid = $random->uuid();
            }
            if ($uuid != '') {
                $model->setUuid($uuid);
            }
        }
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at'
                && $field_name != "password") {

                if (!isset($fields_numeric[$field_name])) {
                    $model->set(
                        $field_name,
                        isset($custom[$field_name]) ? $custom[$field_name] :
                            (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name))
                    );
                } else {
                    $field_name_value = isset($custom[$field_name]) ? $custom[$field_name] :
                        (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name));
                    if (is_numeric($field_name_value) && $field_name_value != '' && !is_null($field_name_value) && !empty($field_name_value)) {
                        $model->set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/

        /****** END YOUR CODE **/
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
        }
    }

    /** get type name of data */
    public function getDataTypeName()
    {
        if ($this->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATE ||
            $this->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATETIME) {
            return "date";
        }else if($this->getServiceFieldTypeId() == ServiceFieldType::TYPE_TIME){
            return "time";
        }else {
            return "text";
        }
    }

    /**
     * @param $relocationServiceId
     * @return string
     */
    public function getFieldValue($relocationServiceId)
    {
        $fieldValue = ServiceFieldValue::__getByServiceAndField($relocationServiceId, $this->getId());
        if ($fieldValue) {

            if ($this->getCode() == self::HOST_COUNTRY_TEXT) {
                return $fieldValue->getValue() != '' ? intval($fieldValue->getValue()) : null;
            }

            if ($this->getCode() == self::EXPIRY_DATE) {
                return $fieldValue->getTimeValueInSecond();
            }

            if ($this->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATE ||
                $this->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATETIME
            ) {
                return $fieldValue->getTimeValueInSecond();
            }

            if ($this->getServiceFieldTypeId() == ServiceFieldType::TYPE_NUMBER) {
                return $fieldValue->getValue() != '' ? floatval($fieldValue->getValue()) : null;
            }

            if ($this->getServiceFieldTypeId() == ServiceFieldType::TYPE_FLOAT) {
                return $fieldValue->getValue() != '' ? floatval($fieldValue->getValue()) : null;
            }
            if ($this->getServiceFieldTypeId() == ServiceFieldType::TYPE_NATIONALITY) {
                return ($fieldValue->getValue() != '' && Helpers::__isJsonValid($fieldValue->getValue()))? json_decode($fieldValue->getValue(), true) : null;
            }

            return $fieldValue->getValue();
        } else return null;
    }


}
