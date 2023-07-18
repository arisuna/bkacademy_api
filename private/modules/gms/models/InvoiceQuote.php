<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Lib\ReportLogHelper;

class InvoiceQuote extends \Reloday\Application\Models\InvoiceQuoteExt
{
    const LIMIT_PER_PAGE = 20;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'reusable' => true,
            'cache' => [
                'key' => 'COMPANY_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->belongsTo('account_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Account',
            'reusable' => true,
            'cache' => [
                'key' => 'COMPANY_' . $this->getAccountId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee',
            'reusable' => true,
            'cache' => [
                'key' => 'EMPLOYEE_' . $this->getEmployeeId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation',
        ]);

        $this->belongsTo('invoice_quote_id', 'Reloday\Gms\Models\InvoiceQuote', 'id', [
            'alias' => 'InvoiceQuote',
        ]);

        $this->belongsTo('invoice_template_id', 'Reloday\Gms\Models\InvoiceTemplate', 'id', [
            'alias' => 'InvoiceTemplate',
            'cache' => [
                'key' => 'EMPLOYEE_' . $this->getInvoiceTemplateId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\InvoiceQuoteItem', 'invoice_quote_id', [
            'alias' => 'InvoiceQuoteItems',
        ]);

        $this->belongsTo('biller_country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'BillerCountry',
        ]);

        $this->belongsTo('office_id', 'Reloday\Gms\Models\Office', 'id', [
            'alias' => 'BillerOffice',
        ]);

        $this->belongsTo('company_country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'CompanyCountry',
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\Payment', 'invoice_quote_id', [
            'alias' => 'Payments',
            'params' => [
                "conditions" => "is_deleted = 0"
            ]
        ]);
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        if ($this->getCompanyId() == ModuleModel::$company->getId()) {
            return true;
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
        $queryBuilder->addFrom('\Reloday\Gms\Models\InvoiceQuote', 'InvoiceQuote');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'InvoiceQuote.id',
            'InvoiceQuote.number',
            'InvoiceQuote.uuid',
            'InvoiceQuote.reference',
            'InvoiceQuote.date',
            'InvoiceQuote.due_date',
            'InvoiceQuote.account_id',
            'account_uuid' => 'Account.uuid',
            'account_name' => 'Account.name',
            'InvoiceQuote.total',
            'InvoiceQuote.total_paid',
            'total_remain' => '(InvoiceQuote.total - InvoiceQuote.total_paid)',
            'InvoiceQuote.status',
            'InvoiceQuote.currency',
            'InvoiceQuote.employee_id',
            'InvoiceQuote.invoice_template_id',
            'employee_uuid' => 'Employee.uuid',
            'employee_firstname' => 'Employee.firstname',
            'employee_lastname' => 'Employee.lastname',
            'employee_workemail' => 'Employee.workemail',
            'order_number' => 'Assignment.order_number'
        ]);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Account.id = InvoiceQuote.account_id', 'Account');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Employee.id = InvoiceQuote.employee_id', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = InvoiceQuote.relocation_id', 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Assignment', 'Assignment.id = Relocation.assignment_id', 'Assignment');

        if ($options['type'] === self::TYPE_INVOICE) {
            $queryBuilder->leftJoin('\Reloday\Gms\Models\InvoiceQuote', 'InvoiceQuoteParent.id = InvoiceQuote.invoice_quote_id', 'InvoiceQuoteParent');
            $colums['quote_number'] = 'InvoiceQuoteParent.number';
        }

        $queryBuilder->where('InvoiceQuote.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andWhere('InvoiceQuote.status != :status:', [
            'status' => InvoiceQuote::STATUS_ARCHIVED
        ]);

        $queryBuilder->andWhere('InvoiceQuote.type = :type:', [
            'type' => $options['type']
        ]);

        if (count($options["companies"]) > 0) {
            $queryBuilder->andwhere('InvoiceQuote.account_id IN ({account_ids:array} )', [
                'account_ids' => $options["companies"]
            ]);
        }

        if (count($options["currencies"]) > 0) {
            $queryBuilder->andwhere('InvoiceQuote.currency IN ({currency_codes:array} )', [
                'currency_codes' => $options["currencies"]
            ]);
        }

        if (isset($options['currency']) && is_string($options['currency']) && $options['currency'] != '') {
            $queryBuilder->andwhere("InvoiceQuote.currency = :currency: OR (InvoiceQuote.currency = '' and InvoiceQuote.total = 0)", [
                'currency' => $options["currency"]
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('InvoiceQuote.reference Like :query: or InvoiceQuote.number Like :query:', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (count($options["statuses"]) > 0) {
            $queryBuilder->andwhere('InvoiceQuote.status IN ({statuses:array} )', [
                'statuses' => $options["statuses"]
            ]);
        }

        if ($options["start_date"] != null && $options["start_date"] != "") {
            $queryBuilder->andwhere('InvoiceQuote.date >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if ($options["end_date"] != null && $options["end_date"] != "") {
            $queryBuilder->andwhere('InvoiceQuote.date <= :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }

        if ($options["status"] != null && $options["status"] != "") {
            $queryBuilder->andwhere('InvoiceQuote.status = :invoice_status:', [
                'invoice_status' => $options["status"]
            ]);
        }

        if ($options["charge_to"] != null && $options["charge_to"] != "") {
            $queryBuilder->andwhere('InvoiceQuote.charge_to = :charge_to:', [
                'charge_to' => $options["charge_to"]
            ]);
        }

        if ($options["account_id"] != null && $options["account_id"] != "") {
            $queryBuilder->andwhere('InvoiceQuote.account_id = :account_id:', [
                'account_id' => $options["account_id"]
            ]);
        }

        if (isset($options['hr_company_id']) && is_numeric($options['hr_company_id']) && $options['hr_company_id'] > 0) {
            $queryBuilder->andwhere("InvoiceQuote.account_id = :hr_company_id:", [
                'hr_company_id' => $options['hr_company_id'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("InvoiceQuote.employee_id = :employee_id:", [
                'employee_id' => $options['employee_id'],
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'InvoiceQuote.account_id',
                'ASSIGNEE_ARRAY_TEXT' => 'InvoiceQuote.employee_id',
                'INVOICE_TEMPLATE_ARRAY_TEXT' => 'InvoiceQuote.invoice_template_id',
                'STATUS_ARRAY_TEXT' => 'InvoiceQuote.status',
                'BILL_TO_TEXT' => 'InvoiceQuote.charge_to',
                'CURRENCY_TEXT' => 'InvoiceQuote.currency',
                'DUE_DATE_TEXT' => 'InvoiceQuote.due_date',
                'DATE_TEXT' => 'InvoiceQuote.date',
            ];

            $dataType = [
                'DUE_DATE_TEXT' => 'int',
                'DATE_TEXT' => 'int',
                'BILL_TO_TEXT' => 'int'
            ];

            if ($options['type'] === self::TYPE_INVOICE) {
                $_taget = FilterConfigExt::INVOICE_FILTER_TARGET;
            } else if ($options['type'] === self::TYPE_QUOTE) {
                $_taget = FilterConfigExt::QUOTE_FILTER_TARGET;
            } else {
                $_taget = FilterConfigExt::CREDIT_NOTE_FILTER_TARGET;
            }
            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], $_taget, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy(['InvoiceQuote.created_at DESC']);
            if ($order['field'] == "reference") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuote.reference ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.reference DESC']);
                }
            }

            if ($order['field'] == "number") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuote.number ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.number DESC']);
                }
            }

            if ($order['field'] == "date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuote.date ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.date DESC']);
                }
            }

            if ($order['field'] == "due_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuote.due_date ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.due_date DESC']);
                }
            }

            if ($order['field'] == "account") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Account.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Account.name DESC']);
                }
            }

            if ($order['field'] == "total") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuote.total ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.total DESC']);
                }
            }

            if ($order['field'] == "due") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['total_remain ASC']);
                } else {
                    $queryBuilder->orderBy(['total_remain DESC']);
                }
            }


            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuote.status ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.status DESC']);
                }
            }


            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['InvoiceQuote.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['InvoiceQuote.created_at DESC']);
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

        $queryBuilder->groupBy('InvoiceQuote.id');

        try {
            if(!isset($options['nopaging']) || !$options['nopaging']){

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
                    'order' => isset($order) ? $order : ""
                ];
            } else {
                return [
                    'success' => true,
                    'sql' => $queryBuilder->getQuery()->getSql(),
                    'data' => ($queryBuilder->getQuery()->execute())
                ];
            }

        } catch (\Phalcon\Exception $e) {
            return [
                'success' => false,
                'detail' => [$e->getTraceAsString(), $e->getMessage()],
                //'sql' => $queryBuilder->getQuery()->getSql()
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'detail' => [$e->getTraceAsString(), $e->getMessage()],
                //'sql' => $queryBuilder->getQuery()->getSql()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'detail' => [$e->getTraceAsString(), $e->getMessage()],
                //'sql' => $queryBuilder->getQuery()->getSql()
            ];
        }
    }

    /**
     * fix bug calcultotal
     */
    public function calculTotal()
    {
        $subtotal = 0;
        $total = 0;
        $items = $this->getInvoiceQuoteItems();

        if ($items->count() > 0) {
            foreach ($items as $item) {
                if ($item->getCurrency() == null || $item->getCurrency() == '' || $item->getCurrency() == $this->getCurrency()) {
                    $subtotal += round((float)($item->getTotal()), 2);
                }
            }
        }

        $this->setSubTotal($subtotal);
        $this->setTotal($subtotal * (1 - $this->getDiscountRate()));
    }

    /**
     * please use pre calculated data
     * in before save
     * @return string
     */
    public function getSimpleFrontendUrl()
    {
        if ($this->getCompany()) {
            $company = $this->getCompany();
            if ($company) {
                return $company->getFrontendUrl() . "/#/app/invoice/detail/" . $this->getUuid();
            }
        }
    }

    /**
     * @return string
     */
    public function getFrontendState($options = "")
    {
        return "app.invoice.detail({uuid:'" . $this->getUuid() . "'})";
    }

    /**
     * @return array
     */
    public function getFrontendRoute()
    {
        return ["state" => "app.invoice.detail", "uuid" => $this->getUuid()];
    }

    /**
     * @param $params
     * @param $orders
     * @return array
     */
    public static function __executeReport($params, $orders = [])
    {
        $queryString = "SELECT DISTINCT iq.id AS id, iq.number As number, iq.reference, iq.date, iq.due_date, ac.name As account_name, ";
        $queryString .= " iq.biller_address, iq.biller_town, bc.name as biller_country_name, iq.biller_email, iq.biller_contact, iq.total, ";
        $queryString .= " iq.sub_total, iq.discount, iq.total_paid,";
        $queryString .= " CASE WHEN iq.status = 0 THEN '" . ConstantHelper::__translateForPresto("DRAFT_TEXT", ModuleModel::$language);
        $queryString .= "'  WHEN iq.status = 1 THEN '" . ConstantHelper::__translateForPresto("PENDING_APPROVAL_TEXT", ModuleModel::$language);
        $queryString .= "'  WHEN iq.status = 2 THEN '" . ConstantHelper::__translateForPresto("REJECTED_TEXT", ModuleModel::$language);
        $queryString .= "'  WHEN iq.status = 3 THEN '" . ConstantHelper::__translateForPresto("APPROVED_TEXT", ModuleModel::$language);
        $queryString .= "'  WHEN iq.status = 4 THEN '" . ConstantHelper::__translateForPresto("PARTIAL_PAID_TEXT", ModuleModel::$language);
        $queryString .= "'  WHEN iq.status = 5 THEN '" . ConstantHelper::__translateForPresto("PAID_TEXT", ModuleModel::$language);
        $queryString .= "'  ELSE '' end, iq.currency, ";
        $queryString .= " tp.vat_number, tp.name, r.identify, concat(ee.firstname, ' ', ee.lastname) as employee_name, ";
//        $queryString .= " ' ', ' ',r.identify, concat(ee.firstname, ' ', ee.lastname) as employee_name, ";
        $queryString .= " iq.total_before_tax, iq.total_tax, cast(iqt.detail as json), ac.reference as account_invoicing_reference, ";
        $queryString .= " of.name as office_name, of.invoicing_reference as office_invoicing_reference, iq.biller_for";
        $queryString .= " From invoice_quote As iq ";
        $queryString .= " Left join company As ac on ac.id = iq.account_id ";
        $queryString .= " Left join office As of on of.id = iq.office_id ";
        $queryString .= " Left join country As bc on bc.id = iq.biller_country_id ";
        $queryString .= " Left join invoice_template As tp on tp.id = iq.invoice_template_id ";
        $queryString .= " Left join relocation As r on r.id = iq.relocation_id ";
        $queryString .= " Left join employee As ee on ee.id = iq.employee_id ";
        $queryString .= " Left join (select invoice_quote_item.invoice_quote_id, ";
        $queryString .= " map_agg( case when invoice_quote_item.type = 1 then invoice_quote_item.number when invoice_quote_item.type = 0 ";
        $queryString .= " then product_pricing.name when invoice_quote_item.type = 2 then account_product_pricing.name else ' ' end, ";
        $queryString .= " concat(cast(invoice_quote_item.quantity as varchar(10)), ' - ', cast(invoice_quote_item.price as varchar(100)), ";
        $queryString .= "' ', invoice_quote_item.currency, ' - ', case when tax_rule.id > 0 then cast(tax_rule.rate as varchar(10)) else '0' end ,";
        $queryString .= " ' % - ', cast(invoice_quote_item.total as varchar(100)), ' ', invoice_quote_item.currency)  ) as detail";
        $queryString .= " from invoice_quote_item left join product_pricing on product_pricing.id = invoice_quote_item.product_pricing_id";
        $queryString .= " left join account_product_pricing on account_product_pricing.id = invoice_quote_item.account_product_pricing_id";
        $queryString .= " left join tax_rule on tax_rule.id = invoice_quote_item.tax_rule_id";
        $queryString .= " group by invoice_quote_item.invoice_quote_id) as iqt on iqt.invoice_quote_id = iq.id";
        $queryString .= " WHERE iq.company_id = " . ModuleModel::$company->getId();
//        $queryString .= " WHERE iq.company_id = 2463";
        $queryString .= " AND iq.status != " . self::STATUS_ARCHIVED;
        $queryString .= " AND iq.type = " . self::TYPE_INVOICE;
        if (isset($params['companyIds']) && is_array($params['companyIds']) && !empty($params['companyIds'])) {
            $index_companyid = 0;
            foreach ($params['companyIds'] as $companyId) {
                if ($index_companyid == 0) {
                    $queryString .= " AND (iq.account_id = " . $companyId . "";

                } else {
                    $queryString .= " OR iq.account_id = " . $companyId . "";
                }
                $index_companyid += 1;
            }
            if ($index_companyid > 0) {
                $queryString .= ") ";
            }
        }

        if (isset($params['fromDate']) && $params['fromDate'] != '' && $params['fromDate'] != null) {
            $queryString .= " AND iq.date > " . (Helpers::__convertDateToSecond($params['fromDate']));
        }

        if (isset($params['toDate']) && $params['toDate'] != '' && $params['toDate'] != null) {
            $queryString .= " AND iq.date < " . (Helpers::__convertDateToSecond($params['toDate']));
        }

        if (isset($params['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'iq.account_id',
                'INVOICE_TEMPLATE_ARRAY_TEXT' => 'iq.invoice_template_id',
                'STATUS_ARRAY_TEXT' => 'iq.status',
                'BILL_TO_TEXT' => 'iq.charge_to',
                'CURRENCY_TEXT' => 'iq.currency',
                'DUE_DATE_TEXT' => 'iq.due_date',
                'DATE_TEXT' => 'iq.date',
            ];

            $dataType = [
                'DUE_DATE_TEXT' => 'int',
                'DATE_TEXT' => 'int',
                'BILL_TO_TEXT' => 'int'
            ];

            Helpers::__addFilterConfigConditionsQueryString($queryString, $params['filter_config_id'], $params['is_tmp'], FilterConfigExt::INVOICE_EXTRACT_FILTER_TARGET, $tableField, $dataType);
        }

        $queryString .= " GROUP BY iq.id, iq.number, iq.reference, iq.date, iq.due_date, ac.name, ";
        $queryString .= " iq.biller_address, iq.biller_town, bc.name, iq.biller_email, iq.biller_contact, iq.total, ";
//        $queryString .= " iq.sub_total, iq.discount, iq.total_paid, iq.status, iq.currency, tp.vat_number, tp.name, r.identify, ee.firstname, ee.lastname, ";
        $queryString .= " iq.sub_total, iq.discount, iq.total_paid, iq.status, iq.currency, tp.vat_number, tp.name, r.identify, ee.firstname, ee.lastname, ";
        $queryString .= " iq.total_before_tax, iq.total_tax, iqt.detail, ac.reference, of.name, of.invoicing_reference, iq.biller_for";

        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY iq.date ASC ";
                } else {
                    $queryString .= " ORDER BY iq.date DESC ";
                }

            } else {
                $queryString .= " ORDER BY iq.date DESC";
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
