<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;


class InvitationRequest extends \Reloday\Application\Models\InvitationRequestExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 10;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('from_company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'FromCompany',
            'reusable' => true,
        ]);

        $this->belongsTo('to_company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'ToCompany',
            'reusable' => true,
        ]);
    }

    /**
     * @param array $options
     * @return array
     */
    public static function __findWithFilter($options = array())
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\InvitationRequest', 'InvitationRequest');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'InvitationRequest.id',
            'InvitationRequest.uuid',
            'InvitationRequest.to_company_id',
            'InvitationRequest.company_name',
            'InvitationRequest.email',
            'InvitationRequest.website',
            'InvitationRequest.firstname',
            'InvitationRequest.lastname',
            'InvitationRequest.is_active',
            'InvitationRequest.is_executed',
            'InvitationRequest.direction',
            'InvitationRequest.created_at',
            'InvitationRequest.expired_at',
            'InvitationRequest.status'
        ]);

        $queryBuilder->where('InvitationRequest.is_deleted = 0');

        if (isset($options["from_company_id"]) && ($options["from_company_id"]) > 0) {
            $queryBuilder->andwhere('InvitationRequest.from_company_id = :from_company_id:', [
                'from_company_id' => $options["from_company_id"]
            ]);
        }

        if (isset($options["to_company_id"]) && ($options["to_company_id"]) > 0) {
            $queryBuilder->andwhere('InvitationRequest.to_company_id = :to_company_id:', [
                'to_company_id' => $options["to_company_id"]
            ]);
        }

        if (isset($options["direction"]) && count($options["direction"]) > 0) {
            $queryBuilder->andwhere('InvitationRequest.direction = :direction:', [
                'direction' => $options["direction"]
            ]);
        }

        if (isset($options["start_date"]) && $options["start_date"] != null && $options["start_date"] != "") {
            $queryBuilder->andwhere('InvitationRequest.created_at >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if (isset($options["end_date"]) && $options["end_date"] != null && $options["end_date"] != "") {
            $queryBuilder->andwhere('InvitationRequest.created_at <= :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }
        $queryBuilder->groupBy('InvitationRequest.id');


        try {
            if (isset($options['has_pagination']) && is_bool($options['has_pagination']) && $options['has_pagination'] === true) {
                $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
                $limit = self::LIMIT_PER_PAGE;
            } else {
                $limit = 10000;
                $page = 0;
            }
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "page" => $page,
                "limit" => $limit
            ]);
            $pagination = $paginator->getPaginate();
            $items = [];
            foreach ($pagination->items as $item) {
                $item = (array)$item;
                $item['status'] = isset($item['status']) ? (int)$item['status'] : null;
                $items[] = $item;
            }
            return [
                'success' => true,
                'rawSql' => $queryBuilder->getQuery()->getSql(),
                'page' => isset($page) ? $page : null,
                'data' => $items,
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

    /**
     * @return array
     */
    public static function __getReceivedInvitations($options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\InvitationRequest', 'InvitationRequest');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Company', 'Company.id = InvitationRequest.from_company_id', 'Company');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'InvitationRequest.id',
            'InvitationRequest.uuid',
            'InvitationRequest.from_company_id',
            'company_name' => 'Company.name',
            'email' => 'Company.email',
            'website' => 'Company.website',
            'InvitationRequest.is_active',
            'InvitationRequest.is_executed',
            'InvitationRequest.direction',
            'InvitationRequest.created_at',
            'InvitationRequest.expired_at',
            'InvitationRequest.status'
        ]);

        $queryBuilder->where('InvitationRequest.is_deleted = 0');

        $queryBuilder->andwhere('InvitationRequest.to_company_id = :to_company_id:', [
            'to_company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andwhere('InvitationRequest.direction = :direction:', [
            'direction' => self::DIRECTION_FROM_HR_TO_DSP
        ]);

        $queryBuilder->groupBy('InvitationRequest.id');

        try {
            if (isset($options['has_pagination']) && is_bool($options['has_pagination']) && $options['has_pagination'] === true) {
                $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
                $limit = self::LIMIT_PER_PAGE;
            } else {
                $limit = 10000;
                $page = 0;
            }
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "page" => $page,
                "limit" => $limit
            ]);
            $pagination = $paginator->getPaginate();
            $items = [];
            foreach ($pagination->items as $item) {
                $item = (array)$item;
                $item['status'] = isset($item['status']) ? (int)$item['status'] : null;
                $items[] = $item;
            }
            return [
                'success' => true,
                'rawSql' => $queryBuilder->getQuery()->getSql(),
                'page' => isset($page) ? $page : null,
                'data' => $items,
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

    /**
     * @param $email
     * @return \Phalcon\Mvc\Model\ResultInterface|\Reloday\Application\Models\InvitationRequest
     */
    public static function __findActiveInvitationSentByEmail($email)
    {
        return self::findFirst([
            'conditions' => 'email = :email: AND from_company_id = :from_company_id: AND is_deleted = :is_deleted_no: AND status > :status_declined:',
            'bind' => [
                'email' => $email,
                'from_company_id' => ModuleModel::$company->getId(),
                'is_deleted_no' => ModelHelper::NO,
                'status_declined' => self::STATUS_DENIED
            ]
        ]);
    }

    public function belongsToCompany() {
        if($this->getDirection() == self::DIRECTION_FROM_DSP_TO_HR) {
            return $this->getFromCompanyId() == ModuleModel::$company->getId();
        }
        return false;
    }
}
