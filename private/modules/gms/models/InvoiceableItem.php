<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class InvoiceableItem extends \Reloday\Application\Models\InvoiceableItemExt
{
    const LIMIT_PER_PAGE = 20;

    const STATUS_NOT_INVOICED = -1;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('expense_id', 'Reloday\Gms\Models\Expense', 'id', [
            'alias' => 'Expense',
        ]);

        $this->belongsTo('account_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Account',
        ]);

        $this->belongsTo('tax_rule_id', 'Reloday\Gms\Models\TaxRule', 'id', [
            'alias' => 'TaxRule',
        ]);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation'
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\InvoiceQuoteItem', 'invoiceable_item_id', [
            'alias' => 'InvoiceQuoteItems'
        ]);
    }

    public function getInvoice()
    {
        $invoice_quote_items = $this->getInvoiceQuoteItems();
        if (count($invoice_quote_items) > 0) {
            foreach ($invoice_quote_items as $invoice_quote_item) {
                $invoice_quote = $invoice_quote_item->getInvoiceQuote();
                if ($invoice_quote instanceof InvoiceQuote && $invoice_quote->getType() == InvoiceQuote::TYPE_INVOICE) {
                    return $invoice_quote;
                }
            }
        }
        return false;
    }

    /**
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\InvoiceableItem', 'InvoiceableItem');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'InvoiceableItem.id',
            'InvoiceableItem.uuid',
            'InvoiceableItem.number',
            'InvoiceableItem.expense_id',
            'InvoiceableItem.tax_rate',
            'expense_date' => 'Expense.expense_date',
            'expense_number' => 'Expense.number',
            'expense_cost' => 'Expense.cost',
            'expense_total' => 'Expense.total',
            'expense_is_payable' => 'Expense.is_payable',
            'expense_service_provider_name' => 'ServiceProviderCompany.name',
            'InvoiceableItem.currency',

            'invoice_quote_item_id' => 'InvoiceQuoteItem.id',
            'InvoiceableItem.description',
            'InvoiceQuoteItem.invoice_quote_id',
            'InvoiceableItem.account_id',
            'invoiceable_item_relocation_id' => 'InvoiceableItem.relocation_id',
            'expense_relocation_id' => 'Expense.relocation_id',
            'invoice_quote_relocation_id' => 'InvoiceQuote.relocation_id',
            'InvoiceableItem.employee_id',
            'InvoiceableItem.expense_category_id',
            'InvoiceableItem.expense_category_name',
            'InvoiceableItem.tax_rule_id',
            'account_name' => 'Account.name',
            'InvoiceableItem.total',
            'InvoiceableItem.quantity',
            'InvoiceableItem.price',
            'InvoiceableItem.unit_price',
            'InvoiceableItem.total_tax',
            'InvoiceableItem.charge_to',
            'InvoiceableItem.description',
            'expense_uuid' => 'Expense.uuid',
            'category_name' => 'ExpenseCategory.name',
            'category_code' => 'ExpenseCategory.external_hris_id',
            'expense_category_code' => 'ExpenseCategory.external_hris_id',
            'employee_uuid' => 'Employee.uuid',
            'employee_name' => 'CONCAT(Employee.firstname,\' \', Employee.lastname)',
            'relocation_employee_name' => 'CONCAT(RelocationEmployee.firstname,\' \', RelocationEmployee.lastname)',
            'relocation_identify' => 'Relocation.identify',
            'invoice_number' => 'InvoiceQuote.number',
            'invoice_uuid' => 'InvoiceQuote.uuid',
            'invoice_status' => 'InvoiceQuote.status',
            'InvoiceableItem.relocation_id',
            'relocation_uuid' => 'Relocation.uuid',
            'assignment_id' => 'Assignment.id',
            'assignment_uuid' => 'Assignment.uuid',
            'assignment_reference' => 'Assignment.reference'
        ]);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Account.id = InvoiceableItem.account_id', 'Account');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Expense', 'Expense.id = InvoiceableItem.expense_id', 'Expense');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany.id = Expense.service_provider_id', 'ServiceProviderCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = InvoiceableItem.relocation_id AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Assignment', 'Assignment.id = Relocation.assignment_id AND Assignment.archived = ' . Assignment::ARCHIVED_NO, 'Assignment');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'RelocationEmployee.id = Relocation.employee_id', 'RelocationEmployee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Employee.id = InvoiceableItem.employee_id', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\TaxRule', 'TaxRule.id = InvoiceableItem.tax_rule_id', 'TaxRule');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\InvoiceQuoteItem', 'InvoiceQuoteItem.invoiceable_item_id = InvoiceableItem.id and InvoiceQuoteItem.invoice_quote_type <> ' . InvoiceQuote::TYPE_CREDIT_NOTE, 'InvoiceQuoteItem');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\InvoiceQuote', 'InvoiceQuote.id = InvoiceQuoteItem.invoice_quote_id', 'InvoiceQuote');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\InvoiceQuoteItem', 'CreditNoteItem.invoiceable_item_id = InvoiceableItem.id and CreditNoteItem.invoice_quote_type = ' . InvoiceQuote::TYPE_CREDIT_NOTE, 'CreditNoteItem');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\InvoiceQuote', 'CreditNote.id = CreditNoteItem.invoice_quote_id', 'CreditNote');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ExpenseCategory', 'ExpenseCategory.id = Expense.expense_category_id ', 'ExpenseCategory');

        $queryBuilder->where('InvoiceableItem.company_id = :gms_company_id:', [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andWhere('InvoiceableItem.is_deleted = 0');

        if (is_array($options["companies"]) && count($options["companies"]) > 0) {
            $queryBuilder->andwhere('InvoiceableItem.account_id IN ({account_ids:array} )', [
                'account_ids' => $options["companies"]
            ]);
        }

        if (is_array($options["invoice_statues"]) && count($options["invoice_statues"]) > 0) {
            if (in_array(self::STATUS_NOT_INVOICED, $options["invoice_statues"])) {
                $queryBuilder->andwhere('InvoiceQuote.status IN ({invoice_status_ids:array}) OR InvoiceQuote.id IS NULL', [
                    'invoice_status_ids' => $options["invoice_statues"]
                ]);
            } else {
                $queryBuilder->andwhere('InvoiceQuote.status IN ({invoice_status_ids:array} )', [
                    'invoice_status_ids' => $options["invoice_statues"]
                ]);
            }
        }

        if (is_array($options["assignees"]) && count($options["assignees"]) > 0) {
            $queryBuilder->andwhere('InvoiceableItem.employee_id IN ({employee_ids:array} )', [
                'employee_ids' => $options["assignees"]
            ]);
        }

        if (is_array($options["categories"]) && count($options["categories"]) > 0) {
            $queryBuilder->andwhere('InvoiceableItem.expense_category_id IN ({category_ids:array} )', [
                'category_ids' => $options["categories"]
            ]);
        }

        if (isset($options['start_date']) && is_numeric($options['start_date']) && $options['start_date'] > 0) {
            $queryBuilder->andwhere('Expense.expense_date >= :start_date:', [
                'start_date' => Helpers::__getStartTimeOfDay($options["start_date"])
            ]);
        }

        if (isset($options['end_date']) && is_numeric($options['end_date']) && $options['end_date'] > 0) {
            $queryBuilder->andwhere('Expense.expense_date < :end_date:', [
                'end_date' => Helpers::__getEndTimeOfDay($options["end_date"])
            ]);
        }

        if (isset($options['account_id']) && is_numeric($options['account_id']) && $options['account_id'] > 0) {
            $queryBuilder->andwhere('InvoiceableItem.account_id = :account_id:', [
                'account_id' => $options["account_id"]
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere('InvoiceableItem.employee_id = :employee_id:', [
                'employee_id' => $options["employee_id"]
            ]);
        }

        if (isset($options['relocation_id']) && is_numeric($options['relocation_id']) && $options['relocation_id'] > 0) {
            $queryBuilder->andwhere('[Relocation].[id] = :relocation_id_fix:', [
                'relocation_id_fix' => $options["relocation_id"]
            ]);
        }

        if (isset($options['has_no_link_invoice']) && is_numeric($options['has_no_link_invoice']) && $options['has_no_link_invoice'] == 1) {
            $queryBuilder->andwhere('InvoiceQuoteItem.id is null');
        }

        if (isset($options['has_no_link_relocation']) && is_bool($options['has_no_link_relocation']) && $options['has_no_link_relocation'] == true) {
            $queryBuilder->andwhere('InvoiceableItem.relocation_id is null');
        }

        if (isset($options['has_no_link_credit_note']) && is_numeric($options['has_no_link_credit_note']) && $options['has_no_link_credit_note'] == 1) {
            $queryBuilder->andwhere('CreditNoteItem.id is null');
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("InvoiceableItem.number LIKE :query: or ExpenseCategory.name LIKE :query: or Account.name LIKE :query: or InvoiceQuote.number LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['currency']) && is_string($options['currency']) && $options['currency'] != '') {
            $queryBuilder->andwhere("InvoiceableItem.currency = :currency:", [
                'currency' => $options['currency'],
            ]);
        }

        if (isset ($options['filter_config_id'])) {
            self::_addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp']);
        }

        /** process order */
        if (is_array($orders) && count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy(['Expense.expense_date DESC']);
            if ($order['field'] == "account") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Account.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Account.name DESC']);
                }
            }

            if ($order['field'] == "invoice") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceableItem.number ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceableItem.number DESC']);
                }
            }

            if ($order['field'] == "date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Expense.expense_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Expense.expense_date DESC']);
                }
            }

            if ($order['field'] == "total") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceableItem.total ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceableItem.total DESC']);
                }
            }

            if ($order['field'] == "price") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceableItem.price ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceableItem.price DESC']);
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

        $queryBuilder->groupBy('InvoiceableItem.id');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $items = $pagination->items;
            $data = [];
            if (count($items) > 0){
                foreach ($items as $item){
                    $item = $item->toArray();
                    $data[] = $item;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => $data,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'order' => $orders,
                'option' => $options
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
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
     * @return bool
     */
    public function isDeletable()
    {
        $checkIfExist = InvoiceQuoteItem::findFirstByInvoiceableItemId($this->getId());
        if ($checkIfExist) {
            return false;
        }
        return true;
    }
}
