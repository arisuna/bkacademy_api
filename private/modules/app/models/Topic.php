<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Security\Random;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

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
     * Filter list of topic
     */
    public static function __findWithFilters($options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Topic', 'Topic');
        $queryBuilder->leftJoin('\SMXD\App\Models\Chapter', 'Chapter.id = Topic.chapter_id', 'Chapter');
        $queryBuilder->distinct(true);

        $queryBuilder->columns([
            'Topic.id',
            'Topic.uuid',
            'Topic.code',
            'Topic.name',
            'Topic.grade',
            'Topic.subject',
            'chapter_name' => 'Chapter.name',
        ]);

        if (!empty($options['query'])) {
            $queryBuilder->andWhere("Topic.name LIKE :query: OR Topic.code LIKE :query:", [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (!empty($options['chapter_id'])) {
            $queryBuilder->andWhere("Topic.chapter_id = :chapter_id:", [
                'chapter_id' => $options['chapter_id']
            ]);
        }

        if (!empty($options['grade'])) {
            $queryBuilder->andWhere("Topic.grade = :grade:", ['grade' => $options['grade']]);
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
