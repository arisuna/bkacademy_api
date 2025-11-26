<?php

namespace SMXD\App\Models;

use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;
use Phalcon\Paginator\PaginatorFactory;

class Chapter extends \SMXD\Application\Models\ChapterExt
{
    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();

        $this->hasMany(
            'id',
            'SMXD\App\Models\Topic',
            'chapter_id',
            [
                'alias'  => 'Topics',
                'params' => ['order' => 'code ASC']
            ]
        );
    }

    /**
     * Tìm Chapter với phân trang – PHALCON 5.x
     */
    public static function __findWithFilters($options = [])
    {
        $qb = new QueryBuilder();
        $qb->addFrom('SMXD\App\Models\Chapter', 'Chapter');

        $qb->columns([
            'Chapter.id',
            'Chapter.uuid',
            'Chapter.code',
            'Chapter.name',
            'Chapter.grade',
            'Chapter.subject',
            'Chapter.type',
        ]);

        // Filters
        if (!empty($options['query'])) {
            $qb->andWhere(
                "(Chapter.name LIKE :query: OR Chapter.code LIKE :query:)",
                ['query' => '%' . $options['query'] . '%']
            );
        }

        if (!empty($options['grade'])) {
            $qb->andWhere("Chapter.grade = :grade:", ['grade' => $options['grade']]);
        }

        if (!empty($options['subject'])) {
            $qb->andWhere("Chapter.subject = :subject:", ['subject' => $options['subject']]);
        }

        // Pagination
        $limit = $options['limit'] ?? self::LIMIT_PER_PAGE;
        $page  = $options['page'] ?? 1;

        try {
            $factory = new PaginatorFactory();

            $paginator = $factory->newInstance(
                "queryBuilder",               // PHALCON 5 keyword
                [
                    "builder" => $qb,
                    "limit"   => $limit,
                    "page"    => $page,
                ]
            );

            $pagination = $paginator->paginate();   // ✔ PHALCON 5 có paginate()

            return [
                'success'      => true,
                'params'       => $options,
                'page'         => $page,
                'data'         => $pagination->items,
                'before'       => $pagination->before,
                'next'         => $pagination->next,
                'last'         => $pagination->last,
                'current'      => $pagination->current,
                'total_items'  => $pagination->total_items,
                'total_pages'  => $pagination->total_pages,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'detail'  => [$e->getMessage(), $e->getTraceAsString()]
            ];
        }
    }
}
