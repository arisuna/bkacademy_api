<?php

namespace Reloday\Gms\Models;

use Phalcon\Mvc\Model;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;

class Timelog extends \Reloday\Application\Models\TimelogExt
{
    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'UserProfile'
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company'
        ]);
        $this->belongsTo('expense_category_id', 'Reloday\Gms\Models\ExpenseCategory', 'id', [
            'alias' => 'ExpenseCategory'
        ]);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation'
        ]);
        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', [
            'alias' => 'RelocationServiceCompany'
        ]);
        $this->belongsTo('task_uuid', 'Reloday\Gms\Models\Task', 'uuid', [
            'alias' => 'Task'
        ]);
        $this->belongsTo('expense_id', 'Reloday\Gms\Models\Expense', 'id', [
            'alias' => 'Expense'
        ]);
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
        $queryBuilder->addFrom('\Reloday\Gms\Models\Timelog', 'Timelog');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Timelog.id',
            'Timelog.uuid',
            'Timelog.user_profile_id',
            'Timelog.expense_category_id',
            'Timelog.start_date',
            'Timelog.end_date',
            'Timelog.hour_spent',
            'Timelog.minute_spent',
            'Timelog.relocation_id',
            'expense_category_name' => 'ExpenseCategory.name',
            'expense_category_code' => 'ExpenseCategory.external_hris_id',
            'expense_category_unit' => 'ExpenseCategory.unit',
            'user_profile_name' => 'CONCAT(UserProfile.firstname,\' \', UserProfile.lastname)',
            'user_profile_uuid' => 'UserProfile.uuid',
            'employee_name' => 'CONCAT(Employee.firstname,\' \', Employee.lastname)',
            'employee_uuid' => 'Employee.uuid',
            'relocation_identify' => 'Relocation.identify',
            'related_expense_uuid' => 'Expense.uuid',
            'related_expense_status' => 'Expense.status',
            'relocation_service_company_name' => 'ServiceCompany.name',
            'task_name' => 'Task.name',
            'task_number' => 'Task.number',
            'comment' => 'Timelog.comment',
        ]);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = Timelog.relocation_id AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = Timelog.relocation_service_company_id AND RelocationServiceCompany.status != ' . RelocationServiceCompany::STATUS_ARCHIVED, 'RelocationServiceCompany');

        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = RelocationServiceCompany.service_company_id', 'ServiceCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Task', 'Task.uuid = Timelog.task_uuid', 'Task');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\UserProfile', 'UserProfile.id = Timelog.user_profile_id ', 'UserProfile');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Employee', 'Employee.id = Relocation.employee_id ', 'Employee');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Account.id = Relocation.hr_company_id ', 'Account');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\ExpenseCategory', 'ExpenseCategory.id = Timelog.expense_category_id ', 'ExpenseCategory');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Expense', 'Expense.id = Timelog.expense_id ', 'Expense');

        $queryBuilder->where('Timelog.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andWhere('Timelog.is_deleted = 0');

        if (isset($options['relocations']) && is_array($options['relocations']) && count($options["relocations"]) > 0) {
            $queryBuilder->andwhere('Relocation.id IN ({relocation_ids:array} )', [
                'relocation_ids' => $options["relocations"]
            ]);
        }

        if (isset($options['services']) && is_array($options['services']) && count($options["services"]) > 0) {
            $queryBuilder->andwhere('RelocationServiceCompany.service_company_id IN ({service_ids:array} )', [
                'service_ids' => $options["services"]
            ]);
        }

        if (isset($options['exclude_items']) && is_array($options['exclude_items']) && count($options["exclude_items"]) > 0) {
            $queryBuilder->andwhere('Timelog.uuid NOT IN ({exclude_items:array} )', [
                'exclude_items' => $options["exclude_items"]
            ]);
        }

        if (isset($options['companies']) && is_array($options['companies']) && count($options["companies"]) > 0) {
            $queryBuilder->andwhere('Account.id IN ({account_ids:array} )', [
                'account_ids' => $options["companies"]
            ]);
        }

        if (isset($options['user_profiles']) && is_array($options['user_profiles']) && count($options["user_profiles"]) > 0) {
            $queryBuilder->andwhere('Timelog.user_profile_id IN ({user_profile_ids:array} )', [
                'user_profile_ids' => $options["user_profiles"]
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('(Timelog.comment Like :query: or CONCAT(UserProfile.firstname,\' \', UserProfile.lastname) Like :query:)', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (count($options["categories"]) > 0) {
            $queryBuilder->andwhere('Timelog.expense_category_id IN ({categories:array} )', [
                'categories' => $options["categories"]
            ]);
        }

        if ($options["start_date"] != null && $options["start_date"] != "") {
            $queryBuilder->andwhere('Timelog.start_date >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if (isset($options['date']) && is_array($options['date'])
            && isset($options['date']['startDate']) && Helpers::__isTimeSecond($options['date']['startDate'])
            && isset($options['date']['endDate']) && Helpers::__isTimeSecond($options['date']['endDate'])) {

            $queryBuilder->andwhere("Timelog.start_date >= :start_date_range_begin: AND Timelog.start_date <= :start_date_range_end:", [
                'start_date_range_begin' => date('Y-m-d H:i:s', Helpers::__getStartTimeOfDay($options['date']['startDate'])),
                'start_date_range_end' => date('Y-m-d H:i:s', Helpers::__getEndTimeOfDay($options['date']['endDate'])),
            ]);
        }

        if ($options["end_date"] != null && $options["end_date"] != "") {
            $queryBuilder->andwhere('Timelog.end_date <= :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy(['Timelog.created_at DESC']);

            if ($order['field'] == "assignee") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['UserProfile.firstname ASC', 'UserProfile.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['UserProfile.firstname DESC', 'UserProfile.lastname DESC']);
                }
            }

            if ($order['field'] == "category") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ExpenseCategory.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ExpenseCategory.name DESC']);
                }
            }

            if ($order['field'] == "relocation") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.identify ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.identify DESC']);
                }
            }

            if ($order['field'] == "start_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Timelog.start_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Timelog.start_date DESC']);
                }
            }

            if ($order['field'] == "end_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Timelog.end_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Timelog.end_date DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Timelog.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Timelog.created_at DESC']);
                }
            }

            if ($order['field'] == "time_logged") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Timelog.hour_spent ASC', 'Timelog.minute_spent ASC']);
                } else {
                    $queryBuilder->orderBy(['Timelog.hour_spent DESC', 'Timelog.minute_spent DESC']);
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

        $queryBuilder->groupBy('Timelog.id');

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
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function getReport($options = array(), $orders = array())
    {
        $queryString = "SELECT CONCAT(UserProfile.firstname,' ', UserProfile.lastname) AS \"" . Constant::__translateConstant('LOGGED_BY_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", ExpenseCategory.name AS \"" . Constant::__translateConstant('EXPENSE_CATEGORY_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", RelocationServiceCompany.name AS \"" . Constant::__translateConstant('SERVICE_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", CONCAT(Employee.firstname, ' ', CONCAT(Employee.lastname, '; ', Relocation.identify)) AS \"" . Constant::__translateConstant('RELOCATION_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ",Timelog.start_date AS \"" . Constant::__translateConstant('START_DATE_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", Timelog.end_date AS \"" . Constant::__translateConstant('END_DATE_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", CONCAT(CAST(Timelog.hour_spent AS VARCHAR), 'h ', CONCAT(CAST(Timelog.minute_spent AS VARCHAR), 'm')) AS \"" . Constant::__translateConstant('TIME_LOGGED_TEXT', ModuleModel::$language) . "\"";
        $queryString .= " FROM timelog AS Timelog INNER JOIN relocation AS Relocation ON Relocation.id = Timelog.relocation_id AND Relocation.active = TRUE";
        $queryString .= " LEFT JOIN relocation_service_company AS RelocationServiceCompany ON RelocationServiceCompany.id = Timelog.relocation_service_company_id AND RelocationServiceCompany.status <> - 1";
        $queryString .= " INNER JOIN user_profile AS UserProfile ON UserProfile.id = Timelog.user_profile_id";
        $queryString .= " INNER JOIN employee AS Employee ON Employee.id = Relocation.employee_id";
        $queryString .= " INNER JOIN company AS Account ON Account.id = Relocation.hr_company_id";
        $queryString .= " INNER JOIN expense_category AS ExpenseCategory ON ExpenseCategory.id = Timelog.expense_category_id";
        $queryString .= " LEFT JOIN expense AS Expense ON Expense.timelog_id = Timelog.id";
        $queryString .= " WHERE (Timelog.company_id = 32)";
        $queryString .= " AND (Timelog.is_deleted = 0) ";
        if (count($options["relocations"]) > 0) {
            $queryString .= " AND Relocation.id IN (" . implode(',', $options["relocations"]) . ")";
        }

        if (count($options["companies"]) > 0) {
            $queryString .= " AND Account.id IN (" . implode(',', $options["companies"]) . ")";
        }

        if (count($options["user_profiles"]) > 0) {
            $queryString .= " AND Timelog.user_profile_id IN (" . implode(',', $options["user_profiles"]) . ")";
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryString .= " AND (Timelog.comment Like \%" . $options['query'] . "% or CONCAT(UserProfile.firstname,' ', UserProfile.lastname) Like %" . $options['query'] . "%)";
        }

        if (count($options["categories"]) > 0) {
            $queryString .= " AND Timelog.expense_category_id IN (" . implode(',', $options["categories"]) . ")";
        }

        if ($options["start_date"] != null && $options["start_date"] != "") {
            $queryString .= " AND Timelog.start_date >= " . $options["start_date"];
        }

        if ($options["end_date"] != null && $options["end_date"] != "") {
            $queryString .= " AND Timelog.end_date <= " . $options["end_date"];
        }
        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    /**
     * @return mixed
     */
    public function getParsedData()
    {
        $array = $this->toArray();
        $array['start_date_time'] = $this->getStartDate() != '' ? Helpers::__convertDateToSecond($this->getStartDate()) : null;
        $array['end_date_time'] = $this->getEndDate() != '' ? Helpers::__convertDateToSecond($this->getEndDate()) : null;
        $array['created_at_time'] = $this->getCreatedAt() != '' ? Helpers::__convertDateToSecond($this->getCreatedAt()) : null;
        $array['updated_at_time'] = $this->getUpdatedAt() != '' ? Helpers::__convertDateToSecond($this->getUpdatedAt()) : null;
        $array['user_name'] = $this->getUserProfile() ? $this->getUserProfile()->getFullname() : null;
        $array['user_profile_uuid'] = $this->getUserProfile() ? $this->getUserProfile()->getUuid() : null;
        $array['task_name'] = $this->getTask() ? $this->getTask()->getName() : null;
        $array['task_number'] = $this->getTask() ? $this->getTask()->getNumber() : null;
        $array['expense_category_name'] = $this->getExpenseCategory() ? $this->getExpenseCategory()->getName() : null;
        $array['employee_name'] = $this->getRelocation() ? $this->getRelocation()->getEmployee()->getFullname() : null;
        $array['relocation_service_company_name'] = $this->getRelocationServiceCompany() ? $this->getRelocationServiceCompany()->getName() : null;
        $array['relocation_identify'] = $this->getRelocation() ? $this->getRelocation()->getIdentify() : null;
        $array['task_name'] = $this->getTask() ? $this->getTask()->getName() : null;
        $array['task_number'] = $this->getTask() ? $this->getTask()->getNumber() : null;
        $array['relocation_uuid'] = $this->getRelocation() ? $this->getRelocation()->getUuid() : null;
        return $array;
    }


}
