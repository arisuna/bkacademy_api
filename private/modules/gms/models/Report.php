<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use phpDocumentor\Reflection\Types\Self_;
use Reloday\Application\Models\ReportExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Report extends \Reloday\Application\Models\ReportExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
    }

    public static function loadList($options = [], $params = []): array
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Report', 'Report');
        $queryBuilder->distinct(true);
        $queryBuilder->where('Report.company_uuid = :company_uuid:', [
            'company_uuid' => ModuleModel::$company->getUuid()
        ]);

        if (!isset($options['object_uuid']) || !$options['object_uuid']) {
            return ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        } else {
            $queryBuilder->andWhere('Report.object_uuid = :object_uuid:', [
                'object_uuid' => $options['object_uuid']
            ]);
        }

        if (isset($options['status'])) {
            $queryBuilder->andWhere('Report.status = :status:', [
                'status' => $options['status']
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Report.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        $queryBuilder->orderBy(["Report.created_at DESC"]);

        $queryBuilder->groupBy('Report.id');

        try {
            if (isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0) {
                $limit = $options['limit'];
                $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
                $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

                if ($page == 0) {
                    $page = intval($start / $limit) + 1;
                }

                $paginator = new PaginatorQueryBuilder([
                    "builder" => $queryBuilder,
                    "limit" => $limit,
                    "page" => $page
                ]);

                $pagination = $paginator->getPaginate();

                return [
                    'success' => true,
                    'options' => $options,
                    'limit_per_page' => $limit,
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
            }

            $items = $queryBuilder->getQuery()->execute();

            $data = [];
            if ($items) {
                foreach ($items as $report) {
                    $reportArr = $report->toArray();
                    $reportArr['user_profile'] = $report->getUserProfile()->toArray();
                    $data[] = $reportArr;
                }
            }

            return [
                'success' => true,
                'data' => $data,
                'total_pages' => 0
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    public static function canExportReport($object_uuid, $type): bool
    {
        $reports = self::find([
            'conditions' => 'company_uuid = :company_uuid: and object_uuid = :object_uuid: and type = :type: and status <= :status:',
            'bind' => [
                'company_uuid' => ModuleModel::$company->getUuid(),
                'object_uuid' => $object_uuid,
                'type' => $type,
                'status' => ReportExt::STATUS_IN_PROCESS
            ],
        ]);

        return !$reports || count($reports) <= 50;
    }

    public static function listNeedRemove($object_uuid, $type)
    {
        return self::find([
            'conditions' => 'company_uuid = :company_uuid: and object_uuid = :object_uuid: and type = :type: and expired_at <= :current_time:',
            'bind' => [
                'company_uuid' => ModuleModel::$company->getUuid(),
                'object_uuid' => $object_uuid,
                'type' => $type,
                'current_time' => date('Y-m-d H:i:s')
            ],
        ]);
    }

}
