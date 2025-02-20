<?php

namespace SMXD\Application\Lib;

use Phalcon\Db\Dialect\DialectMysql;
use Phalcon\Db\RawValue;
use Phalcon\Di;
use Phalcon\Mvc\Model\Query;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Db\Column as Column;
use SMXD\Application\Models\DataFieldExt;

class ModelHelper
{
    const YES = 1;
    const NO = 0;
    const STATUS_DELETED = -1;

    /**
     * @param $model
     * @param array $custom
     * @param array $exceptFields field except can not provide in SetData
     * @return mixed
     * @throws \Phalcon\Security\Exception
     */
    public static function __setData($model, $custom = [], $exceptFields = [])
    {

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
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at'
                && in_array($field_name, $exceptFields) == false) {

                if (!isset($fields_numeric[$field_name])) {
                    if (Helpers::__existCustomValue($field_name, $custom)) {
                        $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                        $field_name_value = Helpers::__existCustomValue($field_name, $custom) ? $field_name_value : Helpers::__coalesce($field_name_value, $model->__get($field_name));
                        $model->__set($field_name, $field_name_value);
                    } else {
                        $model->__set($field_name, $model->__get($field_name));
                    }

                    //$field_name_value = Helpers::__coalesce($field_name_value, $model->__get($field_name));
                    //$field_name_value = $field_name_value != '' ? $field_name_value : $model->__get($field_name);

                } else {

                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__existCustomValue($field_name, $custom) ? $field_name_value : Helpers::__coalesce($field_name_value, $model->__get($field_name));

                    if ($field_name_value != '' && !is_null($field_name_value)) {
                        $model->__set($field_name, $field_name_value);
                    }

                    if ($field_name_value === 0) {
                        $model->__set($field_name, $field_name_value);
                    }
                    if (Helpers::__existCustomValue($field_name, $custom) && $field_name_value === null) {
                        $model->__set($field_name, NUll);
                    }
                }
            }
        }
        /****** YOUR CODE ***/
        if (Helpers::__existCustomValue('department_id', $custom)) {
            $department_id = Helpers::__getRequestValueWithCustom('department_id', $custom);
            if (Helpers::__isNull($department_id)) {
                $model->__set('department_id', $department_id);
            }
        }

        if (Helpers::__existCustomValue('office_id', $custom)) {
            $office_id = Helpers::__getRequestValueWithCustom('office_id', $custom);
            if (Helpers::__isNull($office_id)) {
                $model->__set('office_id', $office_id);
            }
        }

        if (Helpers::__existCustomValue('description_id', $custom)) {
            $description_id = Helpers::__getRequestValueWithCustom('description_id', $custom);
            if (Helpers::__isNull($description_id)) {
                $model->__set('description_id', $description_id);
            }
        }
        /****** END YOUR CODE **/

        return $model;
    }

    /**
     * @return array
     */
    public static function __quickSave($model, $returnObject = false)
    {
        try {
            if ($model->getId() > 0) {
                //update with save
                if ($model->save()) {
                    if ($returnObject == false)
                        return ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $model];
                    else return $model;
                } else {
                    return [
                        "success" => false,
                        "message" => self::__getDisplayErrorMessage($model),
                        "errorMessage" => self::__getErrorMessages($model),
                        "data" => $model
                    ];
                }
            } else {
                //create
                if ($model->create()) {
                    if ($returnObject == false)
                        return ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT"];
                    else return $model;
                } else {
                    return [
                        "success" => false,
                        "message" => self::__getDisplayErrorMessage($model),
                        "errorMessage" => self::__getErrorMessages($model),
                        "data" => $model
                    ];
                }
            }
        } catch (\PDOException $e) {
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage()];
        } catch (Exception $e) {
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage()];
        }
    }

    /**
     * @return array
     */
    public static function __quickUpdate($model, $returnObject = false)
    {
        if (!$model->getId() > 0) {
            return ["success" => false, "message" => "ID_NOT_FOUND_TEXT"];
        }

        try {
            $resultUpdate = $model->update();

            if ($resultUpdate) {
                if ($returnObject == false) {
                    return ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $model];
                } else {
                    return $model;
                }
            } else {
                return [
                    "success" => false,
                    "message" => self::__getDisplayErrorMessage($model),
                    "errorMessage" => self::__getErrorMessages($model),
                    "errorMessageLast" => self::__getErrorMessage($model),
                    "data" => $model
                ];
            }
        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return [
                "success" => false,
                "errorTraceAsString" => $e->getTraceAsString(),
                "errorMessage" => $e->getMessage(),
                "message" => "DATA_SAVE_FAIL_TEXT",
                "detail" => $e->getMessage()
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return [
                "success" => false,
                "errorTraceAsString" => $e->getTraceAsString(),
                "errorMessage" => $e->getMessage(),
                "message" => "DATA_SAVE_FAIL_TEXT",
                "detail" => $e->getMessage()
            ];
        }
    }

    /**
     * @return array
     */
    public static function __quickCreate($model, $returnObject = false)
    {
        try {
            if ($model->getId() > 0) {
                if (method_exists($model, 'isExists')) {
                    if ($model->isExists() == true) {
                        return ["success" => false, "message" => "DATA_EXISTED_TEXT", "detail" => 'Data Existed'];
                    }
                } else {
                    if (self::__isExists($model) == false) {
                        return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => 'Can not track method isExist'];
                    }
                }
            }
            if ($create = $model->create()) {
                if ($returnObject == false)
                    return ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $model, "create" => $create];
                else return $model;
            } else {
                return [
                    "success" => false,
                    "message" => self::__getDisplayErrorMessage($model),
                    "errorMessage" => self::__getErrorMessages($model),
                    "errorMessageLast" => self::__getErrorMessage($model),
                    "data" => $model
                ];
            }

        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage(), "errorMessage" => $e->getMessage(), "data" => $model];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage(), "errorMessage" => $e->getMessage()];
        }
    }


    /**
     * @return array
     */
    public static function __quickCreateWithId($model, $returnObject = false)
    {
        try {
            if ($model->create()) {
                if ($returnObject == false)
                    return ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT"];
                else return $model;
            } else {
                return [
                    "success" => false,
                    "message" => self::__getDisplayErrorMessage($model),
                    "errorMessage" => self::__getErrorMessages($model),
                    "data" => $model
                ];
            }
        } catch (\PDOException $e) {
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage(), "data" => $model];
        } catch (Exception $e) {
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage()];
        }
    }

    /**
     * @return array
     */
    public static function __remove($model)
    {
        try {
            if ($model->delete()) {
                return ["success" => true, "message" => "DATA_DELETE_SUCCESS_TEXT"];
            } else {
                return [
                    "success" => false,
                    "message" => self::__getDisplayErrorMessage($model),
                    "errorMessage" => self::__getErrorMessages($model),
                    "data" => $model
                ];
            }
        } catch (\PDOException $e) {
            return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        } catch (Exception $e) {
            return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        }
    }

    /**
     * @return array
     */
    public static function __quickRemove($model)
    {
        try {
            if ($model->delete()) {
                return ["success" => true, "message" => "DATA_DELETE_SUCCESS_TEXT"];
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[] = $message->getMessage();
                }
                return [
                    "success" => false,
                    "message" => "DATA_SAVE_FAIL_TEXT",
                    "systemMessage" => reset($msg),
                    "detail" => $msg,
                    "data" => $model
                ];
            }
        } catch (\PDOException $e) {
            return [
                "success" => false,
                "systemMessage" => $e->getMessage(),
                "message" => "DATA_DELETE_FAIL_TEXT",
                "detail" => $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "systemMessage" => $e->getMessage(),
                "message" => "DATA_DELETE_FAIL_TEXT",
                "detail" => $e->getMessage()
            ];
        }
    }


    /**
     * delete collection
     * @return array
     */
    public static function __quickRemoveCollection($collection)
    {
        try {
            if ($collection->delete()) {
                return ["success" => true, "message" => "DATA_DELETE_SUCCESS_TEXT"];
            } else {
                $msg = [];
                foreach ($collection->getMessages() as $message) {
                    $msg[] = $message->getMessage();
                }
                return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $msg, "data" => []];
            }
        } catch (\PDOException $e) {
            return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        } catch (Exception $e) {
            return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        }
    }

    /**
     * delete collection
     * @return array
     */
    public static function __quickUpdateCollection($collection, $fields = [])
    {
        if (is_array($fields) && count($fields) > 0) {
            try {
                if ($collection->update($fields)) {
                    return ["success" => true, "message" => "DATA_DELETE_SUCCESS_TEXT"];
                } else {
                    $msg = [];
                    foreach ($collection->getMessages() as $message) {
                        $msg[] = $message->getMessage();
                    }
                    return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $msg, "data" => []];
                }
            } catch (\PDOException $e) {
                return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
            } catch (Exception $e) {
                return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
            }
        }
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __saveData($model, $custom = [])
    {
        $req = new Request();
        if (!($model->getId() > 0)) {
            if ($req->isPut()) {
                $data_id = isset($custom['{tableName}_id']) && $custom['{tableName}_id'] > 0 ? $custom['{tableName}_id'] : $req->getPut('{tableName}_id');
                if ($data_id > 0) {
                    $model = $model->findFirstById($data_id);
                    if (!$model instanceof $model) {
                        return [
                            "success" => false,
                            'msg' => 'DATA_NOT_FOUND_TEXT',
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

        $model = self::__setData($model, $custom);

        try {
            if ($model->getId() == null) {

            }
            if ($model->save()) {
                return $model;
            } else {
                $result = [
                    "success" => false,
                    'msg' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $model->getMessages()
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                "success" => false,
                'msg' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                "success" => false,
                'msg' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * @param $model
     * @return mixed
     */
    public static function __toArray($model, $array = [])
    {
        $metadata = $model->getDI()->get('modelsMetadata');
        $types = $metadata->getDataTypes($model);
        foreach ($types as $attribute => $type) {
            if (array_key_exists($attribute, $array) && !is_null($array[$attribute])) {
                switch ($type) {
                    case Column::TYPE_INTEGER:
                        if (is_object($array[$attribute])) {
                            $array[$attribute] = (int)($array[$attribute])->getValue();
                        }else{
                            $array[$attribute] = (int)$array[$attribute];
                        }
                        break;
                    case Column::TYPE_DECIMAL:
                    case Column::TYPE_FLOAT:
                        $array[$attribute] = (float)$array[$attribute];
                        break;
                    case Column::TYPE_BOOLEAN:
                        $array[$attribute] = (bool)$array[$attribute];
                        break;
                }
            }
        }
        return $array;
    }

    /**
     * @param $type
     * @param null $value
     * @return bool|float|int|null
     */
    public static function __getAttributeValue($type, $value = null)
    {
        if (!is_null($value)) {
            switch ($type) {
                case Column::TYPE_INTEGER:
                    if (($value instanceof RawValue && method_exists($value, 'getValue')) || (is_object($value) && method_exists($value, 'getValue'))) {
                        if (is_numeric($value->getValue())) {
                            $value = (int)($value->getValue());
                        }
                    } else if (is_object($value) && get_class($value) == 'RawValue') {
                        if (is_numeric($value->getValue())) {
                            $value = (int)($value->getValue());
                        }
                    } else if (is_object($value)) {
                        $value = null;
                    } else {
                        $value = (int)$value;
                    }
                    break;
                case Column::TYPE_DECIMAL:
                case Column::TYPE_FLOAT:
                    if (($value instanceof \Phalcon\Db\RawValue && method_exists($value, 'getValue')) || (is_object($value) && method_exists($value, 'getValue'))) {
                        if (is_numeric($value->getValue())) {
                            $value = (float)($value->getValue());
                        } else {
                            $value = floatval($value->getValue());
                        }
                    } elseif (is_string($value) && is_numeric($value)) {
                        $value = (float)$value;
                    } else {
                        $value = floatval($value);
                    }
                    break;
                case Column::TYPE_BOOLEAN:
                    $value = (bool)$value;
                    break;
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return bool|float|int|null
     */
    public static function __floatval($value)
    {
        return self::__getAttributeValue(Column::TYPE_FLOAT, $value);
    }

    /**
     * @param $value
     * @return bool|float|int|null
     */
    public static function __intval($value)
    {
        return self::__getAttributeValue(Column::TYPE_INTEGER, $value);
    }

    /**
     * @param $type
     * @param null $value
     * @return bool|float|int|null
     */
    public static function __getFieldsDataStructure($model, $entityName = '')
    {
        if ($entityName == '') $entityName = $model->getSource();
        $fields = DataFieldExt::find([
            'conditions' => 'table_name = :table_name:',
            'bind' => [
                'table_name' => $entityName
            ],
            'order' => 'position ASC'
        ]);
        $resArray = [];
        foreach ($fields as $field) {
            $resArray[$field->getId()] = $field->toArray();
            if ($field->getMethod() != '' && method_exists($model, $field->getMethod())) {
                $resArray[$field->getId()]['value'] = $model->{$field->getMethod()}();
            } elseif (property_exists($model, $field->getName())) {
                $resArray[$field->getId()]['value'] = $model->__get($field->getName());
            }
            if ($field->getFieldTypeId() == 7) {
                $resArray[$field->getId()]['attribute_name'] = $field->getAttributes() ? $field->getAttributes()->getName() : '';
            }
        }
        return array_values($resArray);
    }

    /**
     * @param $model
     * @return array
     */
    public static function __forceDelete($model)
    {
        try {

            if (method_exists($model, 'getSource') && is_string($model->getSource())) {
                $db = Di::getDefault()->get('db');
                $result = $db->execute("DELETE FROM " . $model->getSource() . " WHERE id = " . $model->getId());
            } else {
                $query = new Query("DELETE FROM " . get_class($model) . " WHERE id = " . $model->getId(), Di::getDefault());
                $result = $query->execute();
            }

            if ($result) {
                return ["success" => true, "message" => "DATA_DELETE_SUCCESS_TEXT"];
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[] = $message->getMessage();
                }
                return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $msg, "data" => $model];
            }
        } catch (\PDOException $e) {
            return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        } catch (Exception $e) {
            return ["success" => false, "message" => "DATA_DELETE_FAIL_TEXT", "detail" => $e->getMessage()];
        }
    }


    /**
     * @return mixed
     */
    public static function __parseTagsItemsFromInput($itemsList = [])
    {
        $itemArrayList = [];
        if (is_array($itemsList) && count($itemsList)) {
            foreach ($itemsList as $item) {
                $item = (array)$item;
                if (isset($item['text'])) {
                    $itemArrayList[] = $item['text'];
                }
            }
        }
        return $itemArrayList;
    }

    /**
     * @param $model
     * @return mixed
     */
    public static function __isExists($model)
    {
        return $model->_exists($model->getModelsMetaData(), $model->getReadConnection());
    }

    /**
     * @return \Phalcon\Db\RawValue
     */
    public static function __getValueEmpty()
    {
        return new \Phalcon\Db\RawValue('""');
    }

    /**
     * @return \Phalcon\Db\RawValue
     */
    public static function __getValueNullRaw()
    {
        return new \Phalcon\Db\RawValue('NULL');
    }

    /**\
     * @param $model
     * @param $field
     */
    public static function __setNull($model, $field)
    {
        return $model->__set($field, new RawValue('NULL'));
    }

    /**
     * @return string
     */
    public static function __getValueNull()
    {
        return null;
    }

    /**
     * @return \Phalcon\Db\RawValue
     */
    public static function __getValueTimestampNow()
    {
        return new \Phalcon\Db\RawValue('now()');
    }

    /**
     * Implement a method that returns a string key based
     * on the query parameters
     */
    public static function _createCustomCacheKey($parameters)
    {
        $uniqueKey = [];
        foreach ($parameters as $key => $value) {
            if (is_scalar($value)) {
                $uniqueKey[] = $key . ':' . $value;
            } elseif (is_array($value)) {
                $uniqueKey[] = $key . ':[' . self::_createCustomCacheKey($value) . ']';
            }
        }
        return join(',', $uniqueKey);
    }

    /**
     * @param $parameters
     * @param int $lifetime
     */
    public static function _getFindParametersWithCache($parameters, int $lifetime, $cachePrefix = '')
    {
        // Convert the parameters to an array
        if (!is_array($parameters)) {
            $parameters = [$parameters];
        }

        return $parameters;
    }

    /**
     * @param $model
     */
    public static function __getDisplayErrorMessage($model)
    {
        $error_message = "DATA_SAVE_FAIL_TEXT";
        foreach ($model->getMessages() as $message) {
            $error_text = $message->getMessage();
            if (strpos($error_text, '_TEXT')) {
                $error_message = $error_text;
            }
            return $error_message;
        }
        return $error_message;
    }

    /**
     * @param $model
     */
    public static function __getErrorMessages($model)
    {
        $msg = [];
        foreach ($model->getMessages() as $message) {
            $msg[] = $message->getMessage();
        }
        return $msg;
    }

    /**
     * @param $model
     */
    public static function __getErrorMessage($model)
    {
        $msg = [];
        foreach ($model->getMessages() as $message) {
            $msg[] = $message->getMessage();
        }
        return end($msg);
    }

    /**
     * @param $model
     * @param array $custom
     * @param array $exceptFields field except can not provide in SetData
     * @return mixed
     * @throws \Phalcon\Security\Exception
     */
    public static function __setDataCustomOnly($model, $custom = [], $exceptFields = [])
    {

        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            if ($model->getUuid() == '') {
                $random = new Random;
                $uuid = $random->uuid();
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
                && in_array($field_name, $exceptFields) == false) {

                if (!isset($fields_numeric[$field_name])) {
                    if (Helpers::__existCustomValue($field_name, $custom)) {
                        $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                        $field_name_value = Helpers::__existCustomValue($field_name, $custom) ? $field_name_value : Helpers::__coalesce($field_name_value, $model->__get($field_name));
                        $model->__set($field_name, $field_name_value);
                    } else {
                        $model->__set($field_name, $model->__get($field_name));
                    }
                } else {

                    if (Helpers::__existCustomValue($field_name, $custom)) {
                        $field_name_value = Helpers::__getCustomValue($field_name, $custom);

                        ///$field_name_value = Helpers::__existCustomValue($field_name, $custom) ? $field_name_value : Helpers::__coalesce($field_name_value, $model->__get($field_name));
                        if ($field_name_value != '' && !is_null($field_name_value)) {
                            $model->__set($field_name, $field_name_value);
                        }
                        if ($field_name_value === 0) {
                            $model->__set($field_name, $field_name_value);
                        }

                        if (Helpers::__isNull($field_name_value)) {
                            $model->__set($field_name, NUll);
                        }

                    } else {
                        $model->__set($field_name, $model->__get($field_name));
                    }
                }
            }
        }
        /****** YOUR CODE ***/

        /****** END YOUR CODE **/

        return $model;
    }
}

?>