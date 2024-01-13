<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;

class LessonCategory extends \SMXD\Application\Models\LessonCategoryExt
{

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('lesson_id', 'SMXD\App\Models\Lesson', 'id', [
            [
                'alias' => 'Lesson'
            ]
        ]);

        $this->belongsTo('category', 'SMXD\App\Models\Category', 'id', [
            [
                'alias' => 'Category'
            ]
        ]);
    }
}