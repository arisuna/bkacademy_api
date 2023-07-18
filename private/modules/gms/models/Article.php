<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\Helpers;

class Article extends \Reloday\Application\Models\ArticleExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;
	const LIMIT_PER_PAGE=20;

	public function initialize(){
		parent::initialize(); 
	}

    /**
     * @return bool
     */
    public function belongsToCompany()
    {
        return $this->getCompanyId() == ModuleModel::$company->getId();
    }

    /**
     * @param array $options
     * @param array $orders
     * @param bool $fullinfo
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {

        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Article', 'Article');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Article.company_id = Company.id', 'Company');

        $queryBuilder->where('Article.company_id = :company_id:', [
            'company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Article.is_deleted = :is_deleted_no:', [
            'is_deleted_no' => ModelHelper::NO
        ]);


        if (isset($options['type_id']) && is_numeric($options['type_id']) && $options['type_id'] != null) {
            $queryBuilder->andwhere("(Article.type_id = :type_id:)", [
                'type_id' => $options['type_id'],
            ]);
        }

        if (isset($options['type_ids']) && is_array($options['type_ids']) && count($options['type_ids']) > 0){
            $queryBuilder->andwhere("Article.type_id IN ({type_ids:array})", [
                'type_ids' => $options['type_ids'],
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("(Article.title LIKE :query:)", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['is_publish']) && is_numeric($options['is_publish']) && $options['is_publish'] >= 0){
            $queryBuilder->andWhere("Article.is_publish = :is_publish:", [
                'is_publish' => (int)$options['is_publish'],
            ]);
        }


        if (isset($options['excepted_ids']) && is_array($options['excepted_ids']) && count($options['excepted_ids']) > 0){
            $queryBuilder->andwhere("(Article.id NOT IN ({excepted_ids:array})", [
                'excepted_ids' => $options['excepted_ids'],
            ]);
        }


        if (count($orders)) {

            $queryBuilder->orderBy(['Article.type_id ASC', 'Article.created_at DESC']);

            $order = reset($orders);

            if ($order['field'] == "name" || $order['field'] == "title") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Article.title ASC']);
                } else {
                    $queryBuilder->orderBy(['Article.title DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy(['Article.type_id ASC', 'Article.created_at DESC']);
        }
        $queryBuilder->groupBy('Article.id');

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : (
        isset($options['length']) && is_numeric($options['length']) && $options['length'] > 0 ? $options['length'] : self::LIMIT_PER_PAGE);

        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $arr = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $article) {
                    $article = $article->toArray();
                    $arr[$article['uuid']] = $article;
                    if (isset($options['is_logo']) && $options['is_logo'] == true){
                        $logo = ObjectAvatar::__getLogo($article['uuid']);
                        $arr[$article['uuid']]['logo'] = $logo ? $logo['image_data']['url_thumb'] : null;
                    }
                }
            }

            return [
                'success' => true,
                '$start' => $start,
                '$limit' => $limit,
                'sql' => $queryBuilder->getQuery()->getSql(),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'page' => $page,
                'order' => $orders,
                'data' => array_values($arr),
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false ,'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}
