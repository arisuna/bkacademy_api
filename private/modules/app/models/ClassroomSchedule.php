<?php

namespace SMXD\App\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use SMXD\Application\Lib\Helpers;

class ClassroomSchedule extends \SMXD\Application\Models\ClassroomScheduleExt
{

    const LIMIT_PER_PAGE = 50;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('classroom_id', 'SMXD\App\Models\Classroom', 'id', [
            'alias' => 'Classroom'
        ]);
    }

    /**
     * [getAllStudentOfClass description]
     * @param  [type] $class_id [class id]
     * @return [type]                [object phalcon : collection of all data in student class table]
     */
    public static function getAllScheduleOfClass($class_id)
    {
        return self::find([
            'conditions' => 'classroom_id = :class_id:',
            'bind' => [
                'class_id' => $class_id,
            ]
        ]);
    }


}