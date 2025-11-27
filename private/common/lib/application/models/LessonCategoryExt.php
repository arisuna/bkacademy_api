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

class LessonCategoryExt extends LessonCategory
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

        $this->belongsTo('category_id', 'SMXD\Application\Models\KnowledgePointExt', 'id', [
            [
                'alias' => 'Category'
            ]
        ]);
    }

    /**
     * [getTable description]
     * @return [type] [description]
     */
    static function getTable()
    {
        $instance = new LessonCategory();
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
        $model->setCategoryId(isset($data['category_id']) ? $data['category_id'] : $model->getCategoryId());
        $model->setPosition(isset($data['position']) ? $data['position'] : $model->getPosition());

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

}