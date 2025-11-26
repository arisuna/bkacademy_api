<?php

namespace SMXD\App\Models;

use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;

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
     * Pagination thủ công – chạy mọi phiên bản Phalcon
     */
    public static function __findWithFilters($options = [])
    {
        $limit = $options['limit'] ?? self::LIMIT_PER_PAGE;
        $page  = $options['page'] && $options['page'] > 0 ? $options['page'] : 1;
        $offset = ($page - 1) * $limit;

        // --- Query Builder ---
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

        if (is_array($options['grades']) && count($options['grades']) > 0) {
            $qb->andWhere("Chapter.grade IN ({grades:array})", ['grades' => $options['grades']]);
        }
        if (is_array($options['types']) && count($options['types']) > 0) {
            $qb->andWhere("Chapter.type IN ({types:array})", ['types' => $options['types']]);
        }

        if (!empty($options['subject'])) {
            $qb->andWhere("Chapter.subject = :subject:", ['subject' => $options['subject']]);
        }

        // Total count
        $countQb = clone $qb;
        $countQb->columns("COUNT(*) AS total");
        $total = $countQb->getQuery()->execute()->getFirst()->total;

        // Apply limit + offset
        $qb->limit($limit, $offset);
        $items = $qb->getQuery()->execute();

        return [
            'success'      => true,
            'params'       => $options,
            'page'         => $page,
            'data'         => $items->toArray(),
            'total_items'  => (int)$total,
            'total_pages'  => ceil($total / $limit),
            'current'      => $page,
            'next'         => $page < ceil($total / $limit) ? $page + 1 : null,
            'before'       => $page > 1 ? $page - 1 : null,
            'last'         => ceil($total / $limit),
        ];
    }
}
