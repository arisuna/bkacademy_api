<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Security\Random;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Chapter extends \SMXD\Application\Models\ChapterExt
{
    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();

        // Relations: Chapter → Topic
        $this->hasMany('id', 'SMXD\App\Models\Topic', 'chapter_id', [
            'alias' => 'Topics',
            'params' => [
                'order' => 'code ASC'
            ]
        ]);
    }

    /**
     * Find with filters (simple version, similar to Category)
     */
    public static function __findWithFilters($options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Chapter', 'Chapter');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Chapter.id',
            'Chapter.uuid',
            'Chapter.code',
            'Chapter.name',
            'Chapter.grade',
            'Chapter.subject',
            'Chapter.type',
        ]);

        if (!empty($options['query'])) {
            $queryBuilder->andWhere("Chapter.name LIKE :query: OR Chapter.code LIKE :query:", [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (!empty($options['grade'])) {
            $queryBuilder->andWhere("Chapter.grade = :grade:", ['grade' => $options['grade']]);
        }

        if (!empty($options['subject'])) {
            $queryBuilder->andWhere("Chapter.subject = :subject:", ['subject' => $options['subject']]);
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
