<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;
use SMXD\Application\Lib\Helpers;

class StudentScore extends \SMXD\Application\Models\StudentScoreExt
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
    }

    public static function __findWithFilters($options, $orders = []): array
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\StudentScore', 'StudentScore');
        $queryBuilder->leftJoin('\SMXD\App\Models\Student', 'StudentScore.student_id = Student.id', 'Student');
        $queryBuilder->leftJoin('\SMXD\App\Models\Lesson', 'StudentScore.lesson_id = Lesson.id', 'Lesson');
        $queryBuilder->leftJoin('\SMXD\App\Models\Classroom', 'Lesson.class_id = Classroom.id', 'Classroom');
        $queryBuilder->leftJoin('\SMXD\App\Models\LessonType', 'Lesson.lesson_type_id = LessonType.id', 'LessonType');

        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('StudentScore.id');
        $queryBuilder->where('Classroom.is_deleted <> 1');

        $queryBuilder->columns([
            'StudentScore.id',
            'StudentScore.score',
            'student_id' => 'Student.id',
            'firstname' => 'Student.firstname',
            'lastname' => 'Student.lastname',
            'class_name' => 'Classroom.name',
            'Classroom.grade',
            'Lesson.date',
            'Lesson.week',
            'Lesson.lesson_type_id',
            'lesson_type_name'=>'LessonType.name'
        ]);
        if (isset($options['is_main_score']) && is_numeric($options['is_main_score'])) {
            $queryBuilder->andwhere("StudentScore.is_main_score = :is_main_score:", [
                'is_main_score' => $options['is_main_score'],
            ]);
        }

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Lesson.name LIKE :search: OR Lesson.code LIKE :search: OR Classroom.name LIKE :search: OR LessonType.name LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        if (isset($options['grades']) && count($options["grades"]) > 0) {
            $queryBuilder->andwhere('Classroom.grade IN ({grades:array})', [
                'grades' => $options["grades"]
            ]);
        }

        if (isset($options['weeks']) && count($options["weeks"]) > 0) {
            $queryBuilder->andwhere('Lesson.week IN ({weeks:array})', [
                'weeks' => $options["weeks"]
            ]);
        }

        if (isset($options['classrooms']) && count($options["classrooms"]) > 0) {
            $queryBuilder->andwhere('Classroom.id IN ({classrooms:array})', [
                'classrooms' => $options["classrooms"]
            ]);
        }

        if (isset($options['lesson_types']) && count($options["lesson_types"]) > 0) {
            $queryBuilder->andwhere('Lesson.lesson_type_id IN ({lesson_types:array})', [
                'lesson_types' => $options["lesson_types"]
            ]);
        }
        if (isset($options['lesson_type_id']) && is_numeric($options['lesson_type_id'])) {
            $queryBuilder->andwhere("Lesson.lesson_type_id = :lesson_type_id:", [
                'lesson_type_id' => $options['lesson_type_id'],
            ]);
        }
        if (isset($options['week']) && is_numeric($options['week'])) {
            $queryBuilder->andwhere("Lesson.week = :week:", [
                'week' => $options['week'],
            ]);
        }

        if (isset($options['date']) && is_array($options['date'])
            && isset($options['date']['startDate']) && Helpers::__isTimeSecond($options['date']['startDate'])
            && isset($options['date']['endDate']) && Helpers::__isTimeSecond($options['date']['endDate'])) {

            $queryBuilder->andwhere("Lesson.date >= :start_date_range_begin: AND Lesson.date <= :start_date_range_end:", [
                'start_date_range_begin' => Helpers::__getStartTimeOfDay($options['date']['startDate']),
                'start_date_range_end' => Helpers::__getEndTimeOfDay($options['date']['endDate']),
            ]);
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Student.firstname ASC', 'Student.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Student.firstname DESC', 'Student.lastname DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Student.id DESC");
        }

        try {
            $data = $queryBuilder->getQuery()->execute();

            $data_array = [];
            $result = [];
            foreach ($data as $item){
                    $item = $item->toArray();
                    if(!isset($data_array[$item['student_id']])){
                        $data_array[$item['student_id']] = [];
                        $data_array[$item['student_id']]['firstname'] = $item['firstname'];
                        $data_array[$item['student_id']]['lastname'] = $item['lastname'];
                        $data_array[$item['student_id']]['class_name'] = $item['class_name'];
                        $data_array[$item['student_id']]['scores'] = [];
                        $data_array[$item['student_id']]['sum_score'] = 0;
                        $data_array[$item['student_id']]['average_score'] = 0;
                    }
                    $data_array[$item['student_id']]['scores'][] = $item;
                    $data_array[$item['student_id']]['sum_score'] += $item['score'];
                    $data_array[$item['student_id']]['average_score'] = number_format($data_array[$item['student_id']]['sum_score'] / count($data_array[$item['student_id']]['scores']), 2, '.', '');
            }
            foreach ($data_array as $item){
                $result[] = $item; 
            }

            return [
                'success' => true,
                // 'sql' => $queryBuilder->getQuery()->getSql(),
                'data' => $result,
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    public static function __findWithFiltersForProgress($options, $orders = []): array
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\StudentScore', 'StudentScore');
        $queryBuilder->leftJoin('\SMXD\App\Models\Student', 'StudentScore.student_id = Student.id', 'Student');
        $queryBuilder->leftJoin('\SMXD\App\Models\ExamType', 'StudentScore.exam_type_id = ExamType.id', 'ExamType');
        $queryBuilder->leftJoin('\SMXD\App\Models\Lesson', 'StudentScore.lesson_id = Lesson.id', 'Lesson');
        $queryBuilder->leftJoin('\SMXD\App\Models\Classroom', 'Lesson.class_id = Classroom.id', 'Classroom');
        $queryBuilder->leftJoin('\SMXD\App\Models\LessonType', 'Lesson.lesson_type_id = LessonType.id', 'LessonType');

        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('StudentScore.id');
        $queryBuilder->where('Classroom.is_deleted <> 1');

        $queryBuilder->columns([
            'StudentScore.id',
            'StudentScore.score',
            'student_id' => 'Student.id',
            'firstname' => 'Student.firstname',
            'lastname' => 'Student.lastname',
            'exam_type_name' => 'ExamType.name',
            'class_name' => 'Classroom.name',
            'Classroom.grade',
            'Lesson.date',
            'Lesson.week',
            'Lesson.lesson_type_id',
            'lesson_type_name'=>'LessonType.name'
        ]);
        if (isset($options['is_main_score']) && is_numeric($options['is_main_score'])) {
            $queryBuilder->andwhere("StudentScore.is_main_score = :is_main_score:", [
                'is_main_score' => $options['is_main_score'],
            ]);
        }

        if (isset($options['search']) && is_string($options['search']) && $options['search'] != '') {
            $queryBuilder->andwhere("Lesson.name LIKE :search: OR Lesson.code LIKE :search: OR Classroom.name LIKE :search: OR LessonType.name LIKE :search:", [
                'search' => '%' . $options['search'] . '%',
            ]);
        }

        if (isset($options['grades']) && count($options["grades"]) > 0) {
            $queryBuilder->andwhere('Classroom.grade IN ({grades:array})', [
                'grades' => $options["grades"]
            ]);
        }

        if (isset($options['weeks']) && count($options["weeks"]) > 0) {
            $queryBuilder->andwhere('Lesson.week IN ({weeks:array})', [
                'weeks' => $options["weeks"]
            ]);
        }

        if (isset($options['classrooms']) && count($options["classrooms"]) > 0) {
            $queryBuilder->andwhere('Classroom.id IN ({classrooms:array})', [
                'classrooms' => $options["classrooms"]
            ]);
        }

        if (isset($options['lesson_types']) && count($options["lesson_types"]) > 0) {
            $queryBuilder->andwhere('Lesson.lesson_type_id IN ({lesson_types:array})', [
                'lesson_types' => $options["lesson_types"]
            ]);
        }
        if (isset($options['lesson_type_id']) && is_numeric($options['lesson_type_id'])) {
            $queryBuilder->andwhere("Lesson.lesson_type_id = :lesson_type_id:", [
                'lesson_type_id' => $options['lesson_type_id'],
            ]);
        }
        if (isset($options['week']) && is_numeric($options['week'])) {
            $queryBuilder->andwhere("Lesson.week = :week:", [
                'week' => $options['week'],
            ]);
        }

        if (isset($options['date']) && is_array($options['date'])
            && isset($options['date']['startDate']) && Helpers::__isTimeSecond($options['date']['startDate'])
            && isset($options['date']['endDate']) && Helpers::__isTimeSecond($options['date']['endDate'])) {

            $queryBuilder->andwhere("Lesson.date >= :start_date_range_begin: AND Lesson.date <= :start_date_range_end:", [
                'start_date_range_begin' => Helpers::__getStartTimeOfDay($options['date']['startDate']),
                'start_date_range_end' => Helpers::__getEndTimeOfDay($options['date']['endDate']),
            ]);
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Student.firstname ASC', 'Student.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Student.firstname DESC', 'Student.lastname DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Student.id DESC");
        }

        try {
            $data = $queryBuilder->getQuery()->execute();

            $data_array = [];
            $result = [];
            foreach ($data as $item){
                    $item = $item->toArray();
                    if(!isset($data_array[$item['student_id']])){
                        $data_array[$item['student_id']] = [];
                        $data_array[$item['student_id']]['firstname'] = $item['firstname'];
                        $data_array[$item['student_id']]['lastname'] = $item['lastname'];
                        $data_array[$item['student_id']]['class_name'] = $item['class_name'];
                        $data_array[$item['student_id']]['scores'] = [];
                        $data_array[$item['student_id']]['sum_score'] = 0;
                        $data_array[$item['student_id']]['average_score'] = 0;
                    }
                    $day = date('w', $item['date']);
                    $week_start = $item['date'] -  3600*24*($day -1);
                    if(!isset($data_array[$item['student_id']]['scores'][$week_start])){
                        $data_array[$item['student_id']]['scores'][$week_start] = [];
                        $data_array[$item['student_id']]['scores'][$week_start]['score'] = $item['score'];
                        $data_array[$item['student_id']]['scores'][$week_start]['average_score'] = $item['score'];
                        $data_array[$item['student_id']]['scores'][$week_start]['scores'] = [];
                        $data_array[$item['student_id']]['scores'][$week_start]['scores'][] = $item;
                    } else {
                        $data_array[$item['student_id']]['scores'][$week_start]['scores'][] = $item;
                        $data_array[$item['student_id']]['scores'][$week_start]['score'] += $item['score'];
                        $data_array[$item['student_id']]['scores'][$week_start]['average_score'] = number_format($data_array[$item['student_id']]['scores'][$week_start]['score'] / count($data_array[$item['student_id']]['scores'][$week_start]['scores']), 2, '.', '');
                    }
            }
            foreach ($data_array as $item){
                $result[] = $item; 
            }

            return [
                'success' => true,
                // 'sql' => $queryBuilder->getQuery()->getSql(),
                'data' => $result,
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}