<?php

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\FilterConfigExt;

class Transaction extends \Reloday\Application\Models\TransactionExt
{
    const LIMIT_PER_PAGE = 20;
	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\Payment', 'transaction_id', ['alias' => 'Payments']);
        $this->belongsTo('account_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Account']);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Company']);
        $this->belongsTo('service_provider_company_id', 'Reloday\Gms\Models\ServiceProviderCompany', 'id', ['alias' => 'ServiceProviderCompany']);
        $this->belongsTo('transaction_type_id', 'Reloday\Gms\Models\TransactionType', 'id', ['alias' => 'TransactionType']);
        $this->belongsTo('financial_account_id', 'Reloday\Gms\Models\FinancialAccount', 'id', ['alias' => 'FinancialAccount']);

        $this->belongsTo('bill_id', 'Reloday\Gms\Models\Bill', 'id', ['alias' => 'Bill']);

        $this->belongsTo('expense_id', 'Reloday\Gms\Models\Expense', 'id', ['alias' => 'Expense']);

        $this->belongsTo('invoice_quote_id', 'Reloday\Gms\Models\InvoiceQuote', 'id', ['alias' => 'InvoiceQuote']);
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
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Transaction', 'Transaction');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Transaction.id',
            'Transaction.uuid',
            'Transaction.reference',
            'Transaction.date',
            'Transaction.description',
            'Transaction.amount',
            'Transaction.currency',
            'Transaction.direction',
            'transaction_type' => 'TransactionType.name',
            'payment_method' => 'PaymentMethod.label',
            'bill_reference' => 'Bill.reference',
            'invoice_quote_reference' => 'InvoiceQuote.reference',
            'expense_reference' => 'Expense.number',
            'account_name' => 'Account.name',
            'provider_name' => 'ServiceProviderCompany.name',
        ]);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Payment', 'Payment.transaction_id = Transaction.id', 'Payment');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\TransactionType', 'TransactionType.id = Transaction.transaction_type_id', 'TransactionType');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\PaymentMethod', 'PaymentMethod.id = Payment.payment_method_id', 'PaymentMethod');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Bill', 'Bill.id = Payment.bill_id', 'Bill');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\InvoiceQuote', 'InvoiceQuote.id = Payment.invoice_quote_id', 'InvoiceQuote');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Expense', 'Expense.id = Payment.expense_id', 'Expense');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Company', 'Account.id = Transaction.account_id', 'Account');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany.id = Transaction.service_provider_company_id', 'ServiceProviderCompany');

        $queryBuilder->where('Transaction.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Transaction.is_deleted = 0');

        if($options["financial_account_id"] > 0) {
            $queryBuilder->andwhere('Transaction.financial_account_id = :financial_account_id:', [
                'financial_account_id' => $options["financial_account_id"]
            ]);
        }

        if (count($options["types"]) > 0) {
            $queryBuilder->andwhere('Transaction.direction IN ({types:array} )', [
                'types' => $options["types"]
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('(Transaction.reference Like :query: or Transaction.description Like :query:)', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if ($options["start_date"] != null && $options["start_date"] != "") {
            $queryBuilder->andwhere('Transaction.date >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if ($options["end_date"] != null && $options["end_date"] != "") {
            $queryBuilder->andwhere('Transaction.date <= :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'TYPE_ARRAY_TEXT' => 'Transaction.direction',
                'PAYMENT_METHOD_ARRAY_TEXT' => 'PaymentMethod.id',
                'DATE_TEXT' => 'Transaction.date',
            ];

            $dataType = [
                'DATE_TEXT' => 'int'
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::TRANSACTION_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy(['Transaction.date DESC']);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Transaction.date ASC']);
                } else {
                    $queryBuilder->orderBy(['Transaction.date DESC']);
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

        $queryBuilder->groupBy('Transaction.id');

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
}
