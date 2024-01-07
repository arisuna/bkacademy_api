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

class StudentClassExt extends StudentClass
{
    use ModelTraits;
    /**
     *
     */
    public function initialize()
    {

        parent::initialize();

        $this->belongsTo('class_id', 'SMXD\Application\Models\ClassroomExt', 'id', [
            [
                'alias' => 'Class'
            ]
        ]);

        $this->belongsTo('student_id', 'SMXD\Application\Models\StudentExt', 'id', [
            'alias' => 'Student'
        ]);
    }

    /**
     * [getTable description]
     * @return [type] [description]
     */
    static function getTable()
    {
        $instance = new StudentClass();
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
                    'message' => 'STUDENT_CLASS_NOT_FOUND_TEXT'
                ];
            }

            $data = $req->getPut();
        }

        $model->setClassId(isset($data['class_id']) ? $data['class_id'] : $model->getClassId());
        $model->setStudentId(isset($data['student_id']) ? $data['student_id'] : $model->getStudentId());

        if ($model->save()) {
            return $model;
        } else {
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }
            $result = [
                'success' => false,
                'message' => 'SAVE_STUDENT_CLASS_FAIL_TEXT',
                'detail' => $msg
            ];
            return $result;
        }

    }

    /**
     * @param $student_id
     * @param $class_id
     * @param $company_id
     */
    public static function getItem($student_id, $class_id)
    {
        return self::findFirst([
            'conditions' => 'student_id = :student_id: AND class_id  = :class_id:',
            'bind' => [
                'class_id' => $class_id,
                'student_id' => $student_id,
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
     * [getAllClassOfStudent description]
     * @param  [type] $student_id [user group id of user group]
     * @return [type]                [object phalcon : collection of all data in student class table]
     */
    public static function getAllClassOfStudent($student_id)
    {
        return self::find([
            'conditions' => 'student_id = :student_id:',
            'bind' => [
                'student_id' => $student_id,
            ]
        ]);
    }

    /**
     * [getAllStudentOfClass description]
     * @param  [type] $class_id [class id]
     * @return [type]                [object phalcon : collection of all data in student class table]
     */
    public static function getAllStudentOfClass($class_id)
    {
        return self::find([
            'conditions' => 'class_id = :class_id:',
            'bind' => [
                'class_id' => $class_id,
            ]
        ]);
    }

}