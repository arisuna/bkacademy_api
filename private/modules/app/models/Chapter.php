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
                    $data_array[] = $item;
                }
            }

            return [
                //'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
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
