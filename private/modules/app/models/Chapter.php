<?php

namespace SMXD\App\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as Paginator;
use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;

class Chapter extends \SMXD\Application\Models\ChapterExt
{
    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();

        // Chapter → Topics
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
     * Tìm Chapter với filter + phân trang chuẩn Phalcon
     */
    public static function __findWithFilters($options = [])
    {
        // Chuẩn bị QueryBuilder
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

        // --- APPLY FILTERS ---
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

        // --- PAGINATION ---
        $limit = $options['limit'] ?? self::LIMIT_PER_PAGE;
        $page  = $options['page'] ?? 1;

        try {
            $paginator = new Paginator([
                'builder' => $qb,
                'limit'   => $limit,
                'page'    => $page,
            ]);

            $pagination = $paginator->paginate();

            return [
                'success'      => true,
                'params'       => $options,
                'page'         => $page,
                'data'         => $pagination->items->toArray(),
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
