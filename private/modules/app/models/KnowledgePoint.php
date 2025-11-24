<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Security\Random;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

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
     * List knowledge points with filters
     */
    public static function __findWithFilters($options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\KnowledgePoint', 'KP');
        $queryBuilder->leftJoin('\SMXD\App\Models\Topic', 'Topic.id = KP.topic_id', 'Topic');
        $queryBuilder->leftJoin('\SMXD\App\Models\Chapter', 'Chapter.id = KP.chapter_id', 'Chapter');

        $queryBuilder->distinct(true);

        $queryBuilder->columns([
            'KP.id',
            'KP.uuid',
            'KP.code',
            'KP.name',
            'KP.level',
            'KP.grade',
            'KP.subject',
            'chapter_name' => 'Chapter.name',
            'topic_name' => 'Topic.name',
        ]);

        if (!empty($options['query'])) {
            $queryBuilder->andWhere("KP.name LIKE :query: OR KP.code LIKE :query:", [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (!empty($options['chapter_id'])) {
            $queryBuilder->andWhere("KP.chapter_id = :chapter_id:", [
                'chapter_id' => $options['chapter_id']
            ]);
        }

        if (!empty($options['topic_id'])) {
            $queryBuilder->andWhere("KP.topic_id = :topic_id:", [
                'topic_id' => $options['topic_id']
            ]);
        }

        if (!empty($options['grade'])) {
            $queryBuilder->andWhere("KP.grade = :grade:", ['grade' => $options['grade']]);
        }

        $limit = $options['limit'] ?? self::LIMIT_PER_PAGE;
        $page = $options['page'] ?? 1;

        $paginator = new PaginatorQueryBuilder([
            "builder" => $queryBuilder,
            "limit" => $limit,
            "page" => $page,
        ]);

        return $paginator->paginate();
    }
}
