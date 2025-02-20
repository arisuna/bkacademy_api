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

class StudentCategoryScoreExt extends StudentCategoryScore
{
    use ModelTraits;
    /**
     *
     */
    public function initialize()
    {

        parent::initialize();

        $this->belongsTo('lesson_id', 'SMXD\Application\Models\LessonExt', 'id', [
            [
                'alias' => 'Lesson'
            ]
        ]);

        $this->belongsTo('student_id', 'SMXD\Application\Models\StudentExt', 'id', [
            'alias' => 'Student'
        ]);

        $this->belongsTo('category_id', 'SMXD\Application\Models\CategoryExt', 'id', [
            'alias' => 'Category'
        ]);
    }

    /**
     * [getTable description]
     * @return [type] [description]
     */
    static function getTable()
    {
        $instance = new StudentCategoryScore();
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
                    'message' => 'STUDENT_SCORE_NOT_FOUND_TEXT'
                ];
            }

            $data = $req->getPut();
        }

        $model->setLessonId(isset($data['lesson_id']) ? $data['lesson_id'] : $model->getLessonId());
        $model->setStudentId(isset($data['student_id']) ? $data['student_id'] : $model->getStudentId());
        $model->setCategoryId(isset($data['category_id']) ? $data['category_id'] : $model->getCategoryId());
        $model->setScore(isset($data['score']) ? $data['score'] : $model->getScore());
        $model->setIsHomeScore(isset($data['is_home_score']) ? $data['is_home_score'] : $model->getIsHomeScore());
        $model->setDate(isset($data['date']) ? $data['date'] : $model->getDate());

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
     * @return mixed
     */
    public function toArray($columns = NULL): array
    {
        $array = parent::toArray($columns);
        $metadata = $this->getDI()->get('modelsMetadata');
        $types = $metadata->getDataTypes($this);
        foreach ($types as $attribute => $type) {
            $array[$attribute] = ModelHelper::__getAttributeValue($type, $array[$attribute]);
        }
        $array['date'] = intval($array['date']);
        return $array;
    }

}