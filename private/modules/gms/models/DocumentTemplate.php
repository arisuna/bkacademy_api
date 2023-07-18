<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class DocumentTemplate extends \Reloday\Application\Models\DocumentTemplateExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const IN_USED = 1;
    const NOT_IN_USED = 0;
    const LIMIT_PER_PAGE = 10;

    const NAME_TYPE = 'name';
    const STATUS_TYPE = 'status';
    const STYLE_TYPE = 'style';
    const LANGUAGE_TYPE = 'language';

    public function initialize()
    {
        parent::initialize();
    }


    /**
     * @return array
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        if (isset($options['mode']) && is_string($options['mode'])) {
            $mode = $options['mode'];
        } else {
            $mode = "large";
        }
        $bindArray = [];

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->distinct(true);
        $queryBuilder->addFrom('\Reloday\Gms\Models\DocumentTemplate', 'DocumentTemplate');
        $queryBuilder->groupBy('DocumentTemplate.id');

        $queryBuilder->columns([
            'DocumentTemplate.id',
            'DocumentTemplate.uuid',
            'DocumentTemplate.name',
            'DocumentTemplate.description',
            'DocumentTemplate.status',
            'DocumentTemplate.updated_at',
            'DocumentTemplate.created_at',
            'DocumentTemplate.number_of_dependant_field',
            'DocumentTemplate.number_of_entity_document_field',
        ]);

        $queryBuilder->andwhere("DocumentTemplate.company_id = :company_id: AND DocumentTemplate.is_deleted != :is_deleted:", [
            'company_id' => ModuleModel::$company->getId(),
            'is_deleted' => ModelHelper::YES
        ]);

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("DocumentTemplate.name LIKE :query: OR DocumentTemplate.description LIKE :query: OR DocumentTemplate.id LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }
        if (isset($options['status']) && is_numeric($options['status'])) {
            $queryBuilder->andwhere("DocumentTemplate.status = :status:", [
                'status' => $options['status'],
            ]);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;

        if (!isset($options['page'])) {
            $page = intval($start / self::LIMIT_PER_PAGE) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        }

        $queryBuilder->orderBy('DocumentTemplate.created_at DESC');
         /** process order */
         if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['DocumentTemplate.name ASC']);
                } else {
                    $queryBuilder->orderBy(['DocumentTemplate.name DESC']);
                }
            }
        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $itemsArray = [];

            foreach ($pagination->items as $item) {
                $itemArray = $item->toArray();
                $itemArray['id'] = intval($itemArray['id']);
                $itemArray['status'] = intval($itemArray['status']);
                $itemsArray[] = $itemArray;
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => $itemsArray,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'sql' => $queryBuilder->getQuery()->getSql()
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
     *
     */
    public function isSetupField(){
        $documentFields = $this->getDocumentTemplateFields([
            'conditions' => 'document_field_type > 0'
        ])->getFirst();
        if($documentFields){
            return true;
        }
        return false;
    }
}
