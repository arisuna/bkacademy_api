<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Security\Random;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\Email as EmailValidator;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Traits\ModelTraits;

class ClassroomExt extends Classroom
{
    use ModelTraits;

    const STATUS_ACTIVATED = 1;
    const STATUS_ARCHIVED = -1;

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
            'field' => 'is_deleted',
            'value' => Helpers::YES
        ]));
        $this->belongsTo('teacher_id', '\SMXD\Application\Models\UserExt', 'id', [
            'alias' => 'Teacher'
        ]);
    }


    /**
     * @return bool
     */
    public function beforeValidationOnCreate()
    {
        /**
         * set official Account YES
         */

        $validator = new Validation();
        return $this->validate($validator);
    }

    /**
     * @return bool
     */
    public function beforeValidationOnUpdate()
    {
        $validator = new Validation();
        return $this->validate($validator);
    }

    /**
     * [beforeValidation description]
     * @return [type] [description]
     */
    public function beforeValidation()
    {
        $validator = new Validation();
        return $this->validate($validator);
    }

    /**
     * [beforeSave description]
     * @return [type] [description]
     */
    public function beforeSave()
    {
        //@TODO get frontendurl
    }

    /**
     * [afterSave description]
     * @return [type] [description]
     */
    public function afterSave()
    {

    }

    /**
     * @param array $custom : app_id, profile_id, created_by
     * @return array|ClassroomExt|Classroom
     */
    public function __save($custom = [])
    {
        $req = new Request();

        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            $model = $this->findFirst($req->getPut('id'));
            if (!$model instanceof $this) {
                return [
                    'success' => false,
                    'message' => 'Classroom_NOT_FOUND_TEXT',
                    'detail' => []
                ];
            }
            $data = $req->getPut();
        }

        $model->setName(array_key_exists('name', $data) ? $data['name'] : (isset($custom['name']) ? $custom['name'] : $model->getName()));
        $model->setUuid(isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid()));
        $model->setGrade(isset($custom['grade']) ? $custom['grade'] : (isset($data['grade']) ? $data['grade'] : $model->getGrade()));

        $model->setStatus(array_key_exists('status', $data) ? (int)$data['status'] : $model->getStatus());

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
                    'message' => 'SAVE_CLASSROOM_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_CLASSROOM_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
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
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __saveData($custom = [])
    {

        $req = new Request();
        $model = $this;

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
                && $field_name != "password"
            ) {

                if (!isset($fields_numeric[$field_name])) {
                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__existCustomValue($field_name, $custom) ? $field_name_value : Helpers::__coalesce($field_name_value, $model->get($field_name));


                    //$field_name_value = $field_name_value != '' ? $field_name_value : $model->get($field_name);
                    $model->set($field_name, $field_name_value);

                } else {

                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->get($field_name));
                    if ($field_name_value != '' && !is_null($field_name_value)) {
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
     * @param $custom
     */
    public static function prepareDataFromArray($custom = [])
    {
        return [
            'id' => isset($custom['id']) && Helpers::__checkId($custom['id']) ? $custom['id'] : null,
            'uuid' => isset($custom['uuid']) && Helpers::__checkUuid($custom['uuid']) ? $custom['uuid'] : null,
            'name' => isset($custom['name']) && $custom['name'] != '' ? $custom['name'] : null,
            'grade' => isset($custom['grade']) && Helpers::__isEmail($custom['grade']) ? $custom['grade'] : null,
            'status' => isset($custom['status']) && Helpers::__checkStatus($custom['status']) ? (int)$custom['status'] : null,
        ];
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

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->getStatus() == self::STATUS_ACTIVATED;
    }

    /**
     * @param int $id
     * @return Classroom
     */
    public static function findFirstByIdCache(int $id)
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $id,
            ],
        ]);
    }

    /**
     * @param int $id
     * @return Classroom
     */
    public static function findFirstByUuidCache(String $uuid)
    {
        return self::findFirst([
            'conditions' => 'uuid = :uuid:',
            'bind' => [
                'uuid' => $uuid,
            ],
        ]);
    }

    /**
     * @param null $columns
     * @return mixed
     */
    public function toArrayInItem($columns = NULL, $language = 'en')
    {
        $excludeToDisplay = [
        ];

        $result = $this->toArray($columns);
        if (is_array($excludeToDisplay)) {
            foreach ($excludeToDisplay as $x) {
                unset($result[$x]);
            }
        }
        return $result;
    }

    public function parsedDataToArray(){
        $item = $this->toArray();
        return $item;
    }
}
