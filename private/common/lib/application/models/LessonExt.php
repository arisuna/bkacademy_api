<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Security;
use Phalcon\Validation;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class LessonExt extends Lesson
{
    use ModelTraits;

    const DATE_NAME =  [
        'Mon' => 'T2',
        'Tue' => 'T3',
        'Wed' => 'T4',
        'Thu' => 'T5',
        'Fri' => 'T6',
        'Sat' => 'T7',
        'Sun' => 'CN'
    ];

    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');


        $this->belongsTo('lesson_type_id', 'SMXD\Application\Models\LessonTypeExt', 'id', [
            [
                'alias' => 'LessonType'
            ]
        ]);
        $this->belongsTo('class_id', 'SMXD\Application\Models\ClassroomExt', 'id', [
            [
                'alias' => 'Classroom'
            ]
        ]);
    }

    public function beforeValidation()
    {
        $validator = new Validation();

        return $this->validate($validator);
    }
    
    /**
     * @param array $custom
     */
    public function setData( $custom = []){

         ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }
}