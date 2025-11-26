<?php

namespace SMXD\App\Models;

use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;

class KnowledgePoint extends \SMXD\Application\Models\KnowledgePointExt
{
    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();

        // Belongs to Chapter
        $this->belongsTo('chapter_id', 'SMXD\App\Models\Chapter', 'id', [
            'alias' => 'Chapter'
        ]);

        // Belongs to Topic
        $this->belongsTo('topic_id', 'SMXD\App\Models\Topic', 'id', [
            'alias' => 'Topic'
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
        $qb->addFrom('SMXD\App\Models\KnowledgePoint', 'KnowledgePoint');
        $qb->leftjoin('SMXD\App\Models\Chapter', 'Chapter.id = KnowledgePoint.chapter_id', 'Chapter');
        $qb->leftjoin('SMXD\App\Models\Topic', 'Topic.id = KnowledgePoint.topic_id', 'Topic');

        $qb->columns([
            'KnowledgePoint.id',
            'KnowledgePoint.uuid',
            'KnowledgePoint.code',
            'KnowledgePoint.name',
            'KnowledgePoint.grade',
            'KnowledgePoint.subject',
            'KnowledgePoint.level',
            'chapter_name'=>'Chapter.name',
            'Chapter.type',
            'topic_name'=>'Topic.name',
        ]);

        // Filters
        if (!empty($options['query'])) {
            $qb->andWhere(
                "(KnowledgePoint.name LIKE :query: OR KnowledgePoint.code LIKE :query:)",
                ['query' => '%' . $options['query'] . '%']
            );
        }

        if (is_array($options['grades']) && count($options['grades']) > 0) {
            $qb->andWhere("KnowledgePoint.grade IN ({grades:array})", ['grades' => $options['grades']]);
        }
        if (is_array($options['levels']) && count($options['levels']) > 0) {
            $qb->andWhere("KnowledgePoint.level IN ({levels:array})", ['levels' => $options['levels']]);
        }
        if (is_array($options['chapters']) && count($options['chapters']) > 0) {
            $qb->andWhere("KnowledgePoint.chapter_id IN ({chapters:array})", ['chapters' => $options['chapters']]);
        }
        if (is_array($options['topics']) && count($options['topics']) > 0) {
            $qb->andWhere("KnowledgePoint.topic_id IN ({topics:array})", ['topics' => $options['topics']]);
        }
        if (is_array($options['types']) && count($options['types']) > 0) {
            $qb->andWhere("Chapter.type IN ({types:array})", ['types' => $options['types']]);
        }

        if (!empty($options['subject'])) {
            $qb->andWhere("KnowledgePoint.subject = :subject:", ['subject' => $options['subject']]);
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
