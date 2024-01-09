<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Factory;

class Category extends \SMXD\Application\Models\CategoryExt
{

    const LIMIT_PER_PAGE = 20;

	public function initialize(){
		parent::initialize();

        $this->belongsTo('parent_category_id', 'SMXD\App\Models\Category', 'id', [
            'alias' => 'Parent'
        ]);

        $this->hasMany('id', 'SMXD\App\Models\Category', 'parent_category_id', [
            'alias' => 'Children',
            'params' => [
                'order' => 'position ASC'
            ]
        ]);
	}

    /**
     * Using Elastic search
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Category', 'Category');
        $queryBuilder->leftJoin('\SMXD\App\Models\Category', 'ParentCategory.id = Category.parent_category_id', 'ParentCategory');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Category.uuid',
            'Category.id',
            'Category.reference',
            'Category.name',
            'parent_category_reference' => 'ParentCategory.reference',
            'parent_category_name' => 'ParentCategory.name',
        ]);
        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Category.name LIKE :query: or Category.reference LIKE :query: or ParentCategory.name LIKE :query: or ParentCategory.reference LIKE :query:",
                ['query' => '%' . $options['query'] . '%']
            );
        }

        if (isset($options['subject']) && is_string($options['subject']) && $options['subject'] != '') {
            $queryBuilder->andwhere("Category.subject = :subject:", [
                'subject' => $options['subject']
            ]);
            $bindArray['subject'] = $options['subject'];
        }

        if (isset($options['grade']) && is_numeric($options['grade']) && $options['grade'] > 0) {
            $queryBuilder->andwhere("Category.grade = :grade:", [
                'grade' => $options['grade']
            ]);
            $bindArray['grade'] = $options['grade'];
        }

        if (isset($options['lvl']) && is_numeric($options['lvl']) && $options['lvl'] > 0) {
            $queryBuilder->andwhere("Category.lvl = :lvl:", [
                'lvl' => $options['lvl']
            ]);
            $bindArray['lvl'] = $options['lvl'];
        }

        if (isset($options['grades']) && count($options["grades"]) > 0) {
            $queryBuilder->andwhere('Category.grade IN ({grades:array})', [
                'grades' => $options["grades"]
            ]);
        }

        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        if ($page == 0) {
            $page = intval($start / $limit) + 1;
        }
        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            return [
                'success' => true,
                'page' => $page,
                'data' => $pagination->items,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
            ];
        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * Using Elastic search
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function __findAllWithFilter($options = [], $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Category', 'Category');
        $queryBuilder->leftJoin('\SMXD\App\Models\Category', 'ParentCategory.id = Category.parent_category_id', 'ParentCategory');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Category.uuid',
            'Category.id',
            'Category.reference',
            'Category.name',
            'parent_category_reference' => 'ParentCategory.reference',
            'parent_category_name' => 'ParentCategory.name',
        ]);
        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Category.name LIKE :query: or Category.reference LIKE :query: or ParentCategory.name LIKE :query: or ParentCategory.reference LIKE :query:",
                ['query' => '%' . $options['query'] . '%']
            );
        }

        if (isset($options['subject']) && is_string($options['subject']) && $options['subject'] != '') {
            $queryBuilder->andwhere("Category.subject = :subject:", [
                'subject' => $options['subject']
            ]);
            $bindArray['subject'] = $options['subject'];
        }

        if (isset($options['grade']) && is_numeric($options['grade']) && $options['grade'] > 0) {
            $queryBuilder->andwhere("Category.grade = :grade:", [
                'grade' => $options['grade']
            ]);
            $bindArray['grade'] = $options['grade'];
        }

        if (isset($options['lvl']) && is_numeric($options['lvl']) && $options['lvl'] > 0) {
            $queryBuilder->andwhere("Category.lvl = :lvl:", [
                'lvl' => $options['lvl']
            ]);
            $bindArray['grade'] = $options['grade'];
        }

        if (isset($options['grades']) && count($options["grades"]) > 0) {
            $queryBuilder->andwhere('Category.grade IN ({grades:array})', [
                'grades' => $options["grades"]
            ]);
        }
        try {
            $items = $queryBuilder->getQuery()->execute();
            $itemsArray = [];
            foreach ($items as $item) {
                $item = $item->toArray();
                $itemsArray[] = $item;
            }
            return $itemsArray;
        } catch (\Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        } catch (Exception $e) {
            Helpers::__trackError($e);
            $items = [];
            return $items;
        }
    }
}
