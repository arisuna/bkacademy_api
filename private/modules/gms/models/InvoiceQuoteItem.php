<?php

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Lib\ReportLogHelper;

class InvoiceQuoteItem extends \Reloday\Application\Models\InvoiceQuoteItemExt
{
    const LIMIT_PER_PAGE = 20;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('expense_id', 'Reloday\Gms\Models\Expense', 'id', [
            'alias' => 'Expense',
        ]);

        $this->belongsTo('product_pricing_id', 'Reloday\Gms\Models\ProductPricing', 'id', [
            'alias' => 'ProductPricing',
        ]);
        $this->belongsTo('account_product_pricing_id', 'Reloday\Gms\Models\AccountProductPricing', 'id', [
            'alias' => 'AccountProductPricing',
        ]);

        $this->belongsTo('invoice_quote_id', 'Reloday\Gms\Models\InvoiceQuote', 'id', [
            'alias' => 'InvoiceQuote',
        ]);

        $this->belongsTo('tax_rule_id', 'Reloday\Gms\Models\TaxRule', 'id', [
            'alias' => 'TaxRule',
        ]);

        $this->belongsTo('invoiceable_item_id', 'Reloday\Gms\Models\InvoiceableItem', 'id', [
            'alias' => 'InvoiceableItem',
        ]);
    }

    /**
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\InvoiceQuoteItem', 'InvoiceQuoteItem');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'InvoiceQuoteItem.id',
            'InvoiceQuoteItem.uuid',
            'InvoiceQuoteItem.number',
            'InvoiceQuoteItem.expense_id',
            'TaxRule.rate',
            'Expense.expense_date',
            'InvoiceQuoteItem.currency',
            'InvoiceQuoteItem.invoice_quote_id',
            'InvoiceQuoteItem.account_id',
            'InvoiceQuoteItem.relocation_id',
            'InvoiceQuoteItem.employee_id',
            'account_name' => 'Account.name',
            'InvoiceQuoteItem.total',
            'InvoiceQuoteItem.quantity',
            'InvoiceQuoteItem.price',
            'InvoiceQuoteItem.charge_to',
            'expense_uuid' => 'Expense.uuid',
            'category_name' => 'ExpenseCategory.name',
            'employee_uuid' => 'Employee.uuid',
            'employee_name' => 'CONCAT(Employee.firstname,\' \', Employee.lastname)',
            'relocation_identify' => 'Relocation.identify',
            'invoice_number' => 'InvoiceQuote.number',
            'invoice_uuid' => 'InvoiceQuote.uuid',
            'invoice_status' => 'InvoiceQuote.status',
            'relocation_id' => 'Relocation.id'
        ]);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Account.id = InvoiceQuoteItem.account_id', 'Account');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Expense', 'Expense.id = InvoiceQuoteItem.expense_id', 'Expense');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = Expense.relocation_id AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Employee.id = InvoiceQuoteItem.employee_id', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\TaxRule', 'TaxRule.id = InvoiceQuoteItem.tax_rule_id', 'TaxRule');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\InvoiceQuote', 'InvoiceQuote.id = InvoiceQuoteItem.invoice_quote_id', 'InvoiceQuote');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ExpenseCategory', 'ExpenseCategory.id = Expense.expense_category_id ', 'ExpenseCategory');

        $queryBuilder->where('InvoiceQuoteItem.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        if (count($options["companies"]) > 0) {
            $queryBuilder->andwhere('InvoiceQuoteItem.account_id IN ({account_ids:array} )', [
                'account_ids' => $options["companies"]
            ]);
        }

        if (count($options["assignees"]) > 0) {
            $queryBuilder->andwhere('InvoiceQuoteItem.employee_id IN ({employee_ids:array} )', [
                'employee_ids' => $options["assignees"]
            ]);
        }

        if (isset($options['start_date']) && is_numeric($options['start_date']) && $options['start_date'] > 0) {
            $queryBuilder->andwhere('Expense.expense_date >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if (isset($options['end_date']) && is_numeric($options['end_date']) && $options['end_date'] > 0) {
            $queryBuilder->andwhere('Expense.expense_date <= :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }


        /** process order */
        if (count($orders)) {
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
                    $queryBuilder->orderBy(['InvoiceQuote.number ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.number DESC']);
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
                    $queryBuilder->orderBy(['InvoiceQuoteItem.total ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuoteItem.total DESC']);
                }
            }

            if ($order['field'] == "price") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuoteItem.price ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuoteItem.price DESC']);
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

        $queryBuilder->groupBy('InvoiceQuoteItem.id');

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
     * @param $params
     * @param $orders
     * @return array
     */
    public static function __executeReport($params, $orders = [])
    {
        $queryString = "SELECT DISTINCT invoice.number, invoice.reference, account.name, account.reference,  case when item.type = 2 then account_product_pricing.external_hris_id when item.type = 0 then product_pricing.external_hris_id else item.number end, ";
        $queryString .= " case when item.type = 1 then concat(item.number , ' - ', category.name, ' - ', category.external_hris_id) else item.name end,  ";
        $queryString .= " item.description, case when item.type = 1 then '".ConstantHelper::__translateForPresto("EXPENSE_TEXT", ModuleModel::$language) ;"";
        $queryString .= " ' else '".ConstantHelper::__translateForPresto("PRODUCT_TEXT", ModuleModel::$language)."' end, "  ;
        $queryString .= " item.quantity, item.unit_price, ";
        $queryString .= " case when item.discount_enabled = 1 and (item.discount_type = 1 or item.discount_type is null) then concat(cast(item.discount as varchar),'%') else '0%' end,";
        $queryString .= " case when item.discount_enabled = 1 and item.discount_type = 2 then concat(cast(item.discount as varchar),item.currency) else concat('0',item.currency) end, ";
        $queryString .= " case when tax.id > 0 then tax.name else '".ConstantHelper::__translateForPresto("NO_TAX_APPLIED_TEXT", ModuleModel::$language)."' end, ";
        $queryString .= " item.total_tax, item.total_before_tax, item.total, ";
        $queryString .= " invoice.total_before_tax, invoice.total, invoice.total_paid, ";
        $queryString .= " case when invoice.status = 0 then '".ConstantHelper::__translateForPresto("DRAFT_TEXT", ModuleModel::$language)."' when invoice.status = 1 then '".ConstantHelper::__translateForPresto("PENDING_TEXT", ModuleModel::$language);
        $queryString .= "' when invoice.status = 2 then '".ConstantHelper::__translateForPresto("REJECTED_TEXT", ModuleModel::$language)."' when invoice.status = 3 then '".ConstantHelper::__translateForPresto("APPROVED_TEXT", ModuleModel::$language);
        $queryString .= "' when invoice.status = 4 then '".ConstantHelper::__translateForPresto("PARTIAL_PAID_TEXT", ModuleModel::$language)."' when invoice.status = 5 then '".ConstantHelper::__translateForPresto("PAID_TEXT", ModuleModel::$language);
        $queryString .= "' else '".ConstantHelper::__translateForPresto("NOT_VALID_TEXT", ModuleModel::$language)."' end, ";
        $queryString .= " invoice.date, case when invoice.due_date > 0 then invoice.due_date else 0 end, ";
        $queryString .= " invoice.biller_for, concat(ee.firstname, ' ', ee.lastname), ass.order_number, invoice.biller_vat, invoice.biller_address, invoice.biller_town, biller_country.name, invoice.biller_email, template.name, invoice.currency ";
        $queryString .= " FROM invoice_quote_item as item ";
        $queryString .= " JOIN invoice_quote as invoice on invoice.id = item.invoice_quote_id and invoice.type = 1 and invoice.status != -1 ";
        $queryString .= " join company as account on account.id = invoice.account_id ";
        $queryString .= " left JOIN expense as expense on expense.id = item.expense_id ";
        $queryString .= " left JOIN expense_category as category on category.id = expense.expense_category_id ";
        $queryString .= " left join tax_rule as tax on tax.id = item.tax_rule_id ";
        $queryString .= " left join employee as ee on ee.id = invoice.employee_id ";
        $queryString .= " left join relocation as relocation on relocation.id = invoice.relocation_id ";
        $queryString .= " left join assignment as ass on ass.id = relocation.assignment_id ";
        $queryString .= " left join country as biller_country on biller_country.id = invoice.biller_country_id ";
        $queryString .= " join invoice_template as template on template.id = invoice.invoice_template_id";
        $queryString .= " left JOIN account_product_pricing as account_product_pricing on item.account_product_pricing_id = account_product_pricing.id ";
        $queryString .= " left JOIN product_pricing as product_pricing  on item.product_pricing_id = product_pricing.id ";
        
        $queryString .= " WHERE invoice.company_id = " . ModuleModel::$company->getId();
//        $queryString .= " WHERE iq.company_id = 2463";
        if (isset($params['companyIds']) && is_array($params['companyIds']) && !empty($params['companyIds'])) {
            $index_companyid = 0;
            foreach ($params['companyIds'] as $companyId) {
                if ($index_companyid == 0) {
                    $queryString .= " AND (invoice.account_id = " . $companyId . "";

                } else {
                    $queryString .= " OR invoice.account_id = " . $companyId . "";
                }
                $index_companyid += 1;
            }
            if ($index_companyid > 0) {
                $queryString .= ") ";
            }
        }

        if (isset($params['fromDate']) && $params['fromDate'] != '' && $params['fromDate'] != null) {
            $queryString .= " AND invoice.date > " . (Helpers::__convertDateToSecond($params['fromDate']));
        }

        if (isset($params['toDate']) && $params['toDate'] != '' && $params['toDate'] != null) {
            $queryString .= " AND invoice.date < " . (Helpers::__convertDateToSecond($params['toDate']));
        }

        if (isset($params['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'invoice.account_id',
                'INVOICE_TEMPLATE_ARRAY_TEXT' => 'invoice.invoice_template_id',
                'STATUS_ARRAY_TEXT' => 'invoice.status',
                'BILL_TO_TEXT' => 'invoice.charge_to',
                'CURRENCY_TEXT' => 'invoice.currency',
                'DUE_DATE_TEXT' => 'invoice.due_date',
                'DATE_TEXT' => 'invoice.date',
            ];

            $dataType = [
                'DUE_DATE_TEXT' => 'int',
                'DATE_TEXT' => 'int',
                'BILL_TO_TEXT' => 'int'
            ];

            Helpers::__addFilterConfigConditionsQueryString($queryString, $params['filter_config_id'], $params['is_tmp'], FilterConfigExt::INVOICE_EXTRACT_FILTER_TARGET, $tableField, $dataType);
        }
        $queryString .= " GROUP BY invoice.number, invoice.reference, account.name, account.reference, item.number, item.type, item.total_tax, item.total_before_tax,";
        $queryString .= " category.name, category.external_hris_id, item.description, account.reference, item.name, item.total, invoice.sub_total, invoice.total,";
        $queryString .= " item.quantity, item.currency, item.unit_price, item.discount_enabled, item.discount, item.discount_type, tax.id, tax.name, ";
        $queryString .= " invoice.total_paid, invoice.status, invoice.date, invoice.due_date, invoice.biller_for, ee.firstname, ee.lastname, ass.order_number, ";
        $queryString .= " invoice.biller_vat, invoice.biller_address, invoice.biller_town, biller_country.name, invoice.biller_email, template.name, invoice.currency, invoice.total_before_tax, account_product_pricing.external_hris_id, product_pricing.external_hris_id";

        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY invoice.date ASC ";
                } else {
                    $queryString .= " ORDER BY invoice.date DESC ";
                }

            } else {
                $queryString .= " ORDER BY invoice.date DESC";
            }
        }

        if (ReportLogHelper::__checkResultIfExisted(ModuleModel::$company->getId(), $queryString)) {
            return ReportLogHelper::__getResultInCache(ModuleModel::$company->getId(), $queryString);
        }
        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            $execution['query'] = $queryString;
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        ReportLogHelper::__saveResultInCache(ModuleModel::$company->getId(), $queryString, $executionInfo);
        return $executionInfo;
    }
}
