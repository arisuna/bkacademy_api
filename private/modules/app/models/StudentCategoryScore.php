<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;
use SMXD\Application\Lib\Helpers;

class StudentCategoryScore extends \SMXD\Application\Models\StudentCategoryScoreExt
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

        $this->belongsTo('category_id', 'SMXD\App\Models\KnowledgePoint', 'id', [
            'alias' => 'Category'
        ]);
    }
}