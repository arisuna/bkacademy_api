<?php

namespace SMXD\App\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use SMXD\Application\Lib\Helpers;

class Lesson extends \SMXD\Application\Models\LessonExt
{

    const LIMIT_PER_PAGE = 50;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('class_id', 'SMXD\App\Models\Classroom', 'id', [
            [
                'alias' => 'Class'
            ]
        ]);

        $this->belongsTo('lesson_type_id', 'SMXD\App\Models\LessonType', 'id', [
            'alias' => 'LessonType'
        ]);
    }

    public static function __findWithFilters($options, $orders = []): array
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Lesson', 'Lesson');
        $queryBuilder->leftJoin('\SMXD\App\Models\Classroom', 'Lesson.class_id = Classroom.id', 'Classroom');
        $queryBuilder->leftJoin('\SMXD\App\Models\LessonType', 'Lesson.lesson_type_id = LessonType.id', 'LessonType');

        $queryBuilder->distinct(true);
        $queryBuilder->groupBy('Lesson.id');
        $queryBuilder->where('Classroom.is_deleted <> 1');

        $queryBuilder->columns([
            'Lesson.id',
            'Lesson.code',
            'Lesson.name',
            'class_name' => 'Classroom.name',
            'Classroom.grade',
            'Lesson.date',
            'Lesson.week_report',
            'Lesson.lesson_type_id',
            'lesson_type_name'=>'LessonType.name'
        ]);

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
            $queryBuilder->andwhere('Lesson.week_report IN ({weeks:array})', [
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
        if (isset($options['week_report']) && is_numeric($options['week_report'])) {
            $queryBuilder->andwhere("Lesson.week_report = :week_report:", [
                'week_report' => $options['week_report'],
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

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Lesson.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Lesson.name DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Lesson.id ASC']);
                } else {
                    $queryBuilder->orderBy(['Lesson.id DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Lesson.id DESC");
        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->paginate();

            $data_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $item = $item->toArray();
                    $data_array[] = $item;
                }
            }

            return [
                'success' => true,
                // 'sql' => $queryBuilder->getQuery()->getSql(),
                'params' => $options,
                'page' => $page,
                'data' => $data_array,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
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