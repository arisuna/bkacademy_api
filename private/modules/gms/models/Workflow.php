<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Phalcon\Validation\Validator\Uniqueness;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Workflow extends \Reloday\Application\Models\WorkflowExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const WORKFLOW_ASSIGNMENT = 1;
    const WORKFLOW_RELOCATION = 2;

    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company'
        ]);
        $this->belongsTo('workflow_premium_id', 'Reloday\Gms\Models\WorkflowPremium', 'id', [
            'alias' => 'WorkflowPremium'
        ]);
    }

    /**
     * @return \Reloday\Gms\Models\Workflow[]
     */
    public static function getListOfMyCompany($type)
    {
        $workflow = Workflow::find([
            'conditions' => 'company_id = :company_id: AND status = :status_active: AND type = :type:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_active' => Workflow::STATUS_ACTIVE,
                'type' => $type
            ]
        ]);
        $workflow_array = [];
        if ($workflow->count() > 0) {
            foreach ($workflow as $item) {
                $workflow_array[] = $item->toArray();
            }
        }
        return ($workflow_array);
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if (!ModuleModel::$company) return false;
        if ($this->getCompanyId() == ModuleModel::$company->getId()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __update($custom = [])
    {
        $this->setData($custom);
        return $this->__quickUpdate();
    }

    public static function __findWithFilter($options = array(), $orders = array()){
        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Workflow', 'Workflow');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Workflow.company_id = Company.id', 'Company');

        $queryBuilder->where('Workflow.company_id = :company_id:', [
            'company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Workflow.status = :status:', [
            'status' => Workflow::STATUS_ACTIVE
        ]);


        if (isset($options['type']) && is_numeric($options['type']) && $options['type'] != null) {
            $queryBuilder->andwhere("(Workflow.type = :type:)", [
                'type' => $options['type'],
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("(Workflow.name LIKE :query:)", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (count($orders)) {

            $order = reset($orders);

            if ($order['field'] == "name" || $order['field'] == "title") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Workflow.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Workflow.name DESC']);
                }
            }
        } else {
            $queryBuilder->orderBy(['Workflow.name ASC', 'Workflow.created_at DESC']);
        }
        $queryBuilder->groupBy('Workflow.id');

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
                foreach ($pagination->items as $workflow) {
                    $workflow = $workflow->toArray();
                    $arr[$workflow['uuid']] = $workflow;
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
