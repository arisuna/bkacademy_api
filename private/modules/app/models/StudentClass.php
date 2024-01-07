<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;

class StudentClass extends \SMXD\Application\Models\StudentClassExt
{

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('class_id', 'SMXD\App\Models\Classroom', 'id', [
            [
                'alias' => 'Class'
            ]
        ]);

        $this->belongsTo('student_id', 'SMXD\App\Models\Student', 'id', [
            'alias' => 'Student'
        ]);
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