<?php

namespace SMXD\App\Models;

use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;

class Topic extends \SMXD\Application\Models\TopicExt
{
    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();

        // Topic belongs to Chapter
        $this->belongsTo('chapter_id', 'SMXD\App\Models\Chapter', 'id', [
            'alias' => 'Chapter'
        ]);

        // Topic has many KnowledgePoints
        $this->hasMany('id', 'SMXD\App\Models\KnowledgePoint', 'topic_id', [
            'alias' => 'KnowledgePoints',
            'params' => [
                'order' => 'code ASC'
            ]
        ]);
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
        $qb->addFrom('SMXD\App\Models\Topic', 'Topic');

        $qb->columns([
            'Topic.id',
            'Topic.uuid',
            'Topic.code',
            'Topic.name',
            'Topic.grade',
            'Topic.subject',
        ]);

        // Filters
        if (!empty($options['query'])) {
            $qb->andWhere(
                "(Topic.name LIKE :query: OR Topic.code LIKE :query:)",
                ['query' => '%' . $options['query'] . '%']
            );
        }

        if (is_array($options['grades']) && count($options['grades']) > 0) {
            $qb->andWhere("Topic.grade IN ({grades:array})", ['grades' => $options['grades']]);
        }

        if (!empty($options['subject'])) {
            $qb->andWhere("Topic.subject = :subject:", ['subject' => $options['subject']]);
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
