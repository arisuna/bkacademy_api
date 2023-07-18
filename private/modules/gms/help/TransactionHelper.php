<?php

namespace Reloday\Gms\Help;

use Reloday\Gms\Models\Bill;
use Reloday\Gms\Models\Expense;
use Reloday\Gms\Models\HrCompany;
use Reloday\Gms\Models\InvoiceQuote;
use Reloday\Gms\Models\UserProfile;

class TransactionHelper
{
    /**
     * @param $email
     */
    public static function __getTargetItem($uuid)
    {
        $targetItem = InvoiceQuote::findFirstByUuid($uuid);

        $targetItemArray = [];
        if ($targetItem) {
            $targetItemArray = $targetItem->toArray();
            $targetItemArray['account_name'] = $targetItem->getAccount()->getName();
            if ($targetItem->getType() == InvoiceQuote::TYPE_CREDIT_NOTE) {
                $targetItemArray['type'] = 'CREDIT_NOTE_TEXT';
            }
            if ($targetItem->getType() == InvoiceQuote::TYPE_INVOICE) {
                $targetItemArray['type'] = 'INVOICE_TEXT';
            }
            if ($targetItem->getType() == InvoiceQuote::TYPE_QUOTE) {
                $targetItemArray['type'] = 'QUOTE_TEXT';
            }
            $targetItemArray["type"] = "CREDIT_NOTE_TEXT";
        }

        if (!($targetItem && $targetItem->belongsToGms())) {
            $targetItem = Bill::findFirstByUuid($uuid);
            if ($targetItem && $targetItem->belongsToGms()) {
                $targetItemArray = $targetItem->toArray();
                $targetItemArray['type'] = 'BILL_TEXT';
                $targetItemArray['account_name'] = $targetItem->getServiceProviderCompany()->getName();
            }
        }

        if (!($targetItem && $targetItem->belongsToGms())) {
            $targetItem = Expense::findFirstByUuid($uuid);
            if ($targetItem && $targetItem->belongsToGms()) {
                $targetItemArray = $targetItem->toArray();
                $targetItemArray['type'] = 'EXPENSE_TEXT';
                $targetItemArray['account_name'] = $targetItem->getServiceProviderCompany()->getName();
            }
        }

        if (!($targetItem && $targetItem->belongsToGms())) {
            return false;
        } else {
            return $targetItemArray;
        }
    }


}