<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class Payment extends \Reloday\Application\Models\PaymentExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('bill_id', 'Reloday\Gms\Models\Bill', 'id', ['alias' => 'Bill']);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'cache' => [
                'key' => 'COMPANY_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('invoice_quote_id', 'Reloday\Gms\Models\InvoiceQuote', 'id', ['alias' => 'InvoiceQuote']);
        $this->belongsTo('payment_method_id', 'Reloday\Gms\Models\PaymentMethod', 'id', [
            'alias' => 'PaymentMethod',
            'cache' => [
                'key' => 'PAYMENT_METHOD_' . $this->getPaymentMethodId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('transaction_id', 'Reloday\Gms\Models\Transaction', 'id', [
            'alias' => 'TransactionInvoicing',
            'params' => [
                "conditions" => "is_deleted = 0"
            ]
        ]);
        $this->belongsTo('financial_account_id', 'Reloday\Gms\Models\FinancialAccount', 'id', [
            'alias' => 'FinancialAccount',
            'cache' => [
                'key' => 'FINANCIAL_ACCOUNT_' . $this->getFinancialAccountId(),
                'lifetime' => CacheHelper::__TIME_24H
            ],
        ]);
        $this->belongsTo('expense_id', 'Reloday\Gms\Models\Expense', 'id', ['alias' => 'Expense']);
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
}
