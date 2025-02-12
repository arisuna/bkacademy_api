<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;
use SMXD\Application\Lib\Helpers;

class StudentEvaluation extends \SMXD\Application\Models\StudentEvaluationExt
{

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('lesson_id', 'SMXD\App\Models\Lesson', 'id', [
            [
                'alias' => 'Lesson'
            ]
        ]);

        $this->belongsTo('student_id', 'SMXD\App\Models\Student', 'id', [
            'alias' => 'Student'
        ]);

        $this->belongsTo('evaluation_id', 'SMXD\App\Models\Evaluation', 'id', [
            'alias' => 'Evaluation'
        ]);
    }
}