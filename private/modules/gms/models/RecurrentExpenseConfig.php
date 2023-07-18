<?php

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\Helpers;

class RecurrentExpenseConfig extends \Reloday\Application\Models\RecurrentExpenseConfigExt
{
    const LIMIT_PER_PAGE = 20;

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_TERMINATED = 2;

    const UNIT_DAY = 2;
    const UNIT_WEEK = 3;
    const UNIT_MONTH = 4;
    const UNIT_QUATER = 5;
    const UNIT_YEAR = 6;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('account_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Account']);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Company']);
        $this->belongsTo('expense_category_id', 'Reloday\Gms\Models\ExpenseCategory', 'id', ['alias' => 'ExpenseCategory']);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', ['alias' => 'Relocation']);
        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', ['alias' => 'RelocationServiceCompany']);
        $this->belongsTo('service_provider_id', 'Reloday\Gms\Models\ServiceProviderCompany', 'id', ['alias' => 'ServiceProviderCompany']);
        $this->belongsTo('tax_rule_id', 'Reloday\Gms\Models\TaxRule', 'id', ['alias' => 'TaxRule']);
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
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\RecurrentExpenseConfig', 'RecurrentExpenseConfig');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'RecurrentExpenseConfig.id',
            'RecurrentExpenseConfig.uuid',
            'RecurrentExpenseConfig.expense_category_id',
            'RecurrentExpenseConfig.currency',
            'RecurrentExpenseConfig.amount',
            'RecurrentExpenseConfig.status',
            'RecurrentExpenseConfig.name',
            'RecurrentExpenseConfig.last_date',
            'RecurrentExpenseConfig.next_date',
            'RecurrentExpenseConfig.recurrence_unit',
            'RecurrentExpenseConfig.recurrence_number',
            'employee_name' => 'CONCAT(Employee.firstname,\' \', Employee.lastname)',
            'employee_uuid' => 'Employee.uuid',
            'category_name' => 'ExpenseCategory.name',
            'category_code' => 'ExpenseCategory.external_hris_id',

        ]);
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = RecurrentExpenseConfig.relocation_id AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Employee.id = Relocation.employee_id ', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Company', 'Account.id = RecurrentExpenseConfig.account_id ', 'Account');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\ExpenseCategory', 'ExpenseCategory.id = RecurrentExpenseConfig.expense_category_id ', 'ExpenseCategory');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = RecurrentExpenseConfig.relocation_service_company_id ', 'RelocationServiceCompany');

        $queryBuilder->where('RecurrentExpenseConfig.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('RecurrentExpenseConfig.status <> :status:', [
            'status' => self::STATUS_ARCHIVED
        ]);

        if (count($options["assignees"]) > 0) {
            $queryBuilder->andwhere('Employee.id IN ({assignees:array} )', [
                'assignees' => $options["assignees"]
            ]);
        }

        if (count($options["companies"]) > 0) {
            $queryBuilder->andwhere('Account.id IN ({account_ids:array} )', [
                'account_ids' => $options["companies"]
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('RecurrentExpenseConfig.name  Like :query: or RecurrentExpenseConfig.note Like :query:', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (isset($options['currency']) && is_string($options['currency']) && $options['currency'] != '') {
            $queryBuilder->andwhere('RecurrentExpenseConfig.currency = :currency:', [
                'currency' => $options['currency']
            ]);
        }

        if (count($options["categories"]) > 0) {
            $queryBuilder->andwhere('RecurrentExpenseConfig.expense_category_id IN ({categories:array} )', [
                'categories' => $options["categories"]
            ]);
        }

        if (count($options["status"]) > 0) {
            $queryBuilder->andwhere('RecurrentExpenseConfig.status IN ({statuses:array} )', [
                'statuses' => $options["status"]
            ]);
        }

        $queryBuilder->orderBy('RecurrentExpenseConfig.id DESC');

        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.name ASC']);
                } else {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.name DESC']);
                }
            }

            if ($order['field'] == "expense_category") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ExpenseCategory.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ExpenseCategory.name DESC']);
                }
            }

            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.status ASC']);
                } else {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.status DESC']);
                }
            }

            if ($order['field'] == "last_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.last_date ASC']);
                } else {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.last_date DESC']);
                }
            }

            if ($order['field'] == "next_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.next_date ASC']);
                } else {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.next_date DESC']);
                }
            }

            if ($order['field'] == "frequency") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.recurrence_unit ASC', 'RecurrentExpenseConfig.recurrence_number ASC']);
                } else {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.recurrence_unit DESC', 'RecurrentExpenseConfig.recurrence_number DESC']);
                }
            }

            if ($order['field'] == "currency") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.currency ASC']);
                } else {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.currency DESC']);
                }
            }

            if ($order['field'] == "amount") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.amount ASC']);
                } else {
                    $queryBuilder->orderBy(['RecurrentExpenseConfig.amount DESC']);
                }
            }

        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        $queryBuilder->groupBy('RecurrentExpenseConfig.id');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            return [
                //'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'page' => $page,
                'sql' => $queryBuilder->getQuery()->getSql(),
                'data' => $pagination->items,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'order' => $order
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
     * @return false|int|null
     *
     */
    public function generateRealStartDate()
    {
        $start_date = $this->getStartDate();
        $recurence_time_company_value = ModuleModel::$company->getRecurrentExpenseTimeValueOffset();

        if ($recurence_time_company_value >= 0) {
            $now = time();
            $now_start_date = Helpers::__getStartTimeOfDay($now) + $recurence_time_company_value;
            $now_hour_duration = $now - $now_start_date;

            $start_date = Helpers::__getStartTimeOfDay($start_date);
            $real_start_date = Helpers::__getStartTimeOfDay($start_date) + $recurence_time_company_value;
            $hour_duration = round($recurence_time_company_value);


            $dateConfig = [
                'start_date' => $start_date,
                'now' => $now,
                '$recurence_time_company_value' => $recurence_time_company_value,
                'start_date <= now' => $start_date <= $now,
                'now_start_date' => $now_start_date,
                '$real_start_date' => $real_start_date,
                '$hour_duration' => $hour_duration,
            ];

            return $real_start_date;
        }

        return null;
    }
}
