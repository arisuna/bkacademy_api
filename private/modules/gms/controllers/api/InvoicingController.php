<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\AccountProductPricing;
use Reloday\Gms\Models\Bill;
use Reloday\Gms\Models\Expense;
use Reloday\Gms\Models\ExpenseCategory;
use Reloday\Gms\Models\FinancialAccount;
use Reloday\Gms\Models\InvoiceableItem;
use Reloday\Gms\Models\InvoiceQuote;
use Reloday\Gms\Models\InvoiceQuoteItem;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ProductPricing;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Transaction;
use Reloday\Gms\Models\TransactionType;
use Reloday\Gms\Models\Constant;
use Reloday\Gms\Models\Company;
use Reloday\Application\Lib\ConstantHelper;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class InvoicingController extends BaseController
{
    public function getDashboardStatisticAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $financial_account_id = intval(Helpers::__getRequestValue('financial_account_id'));
        $numOfMonths = intval(Helpers::__getRequestValue("num_of_months"));
        $start_date = Helpers::__getRequestValue("start_date");
        $end_date = Helpers::__getRequestValue("end_date");

        if ($start_date) {
            $start_date = doubleval($start_date);
        } else {
            $start_date = strtotime('-30 days', time());
        }

        if ($end_date) {
            $end_date = doubleval($end_date) + 86400;
        } else {
            $end_date = time();
        }

        $months = [];
        $cashflowindata = [];
        $cashflowoutdata = [];
        $incomedata = [];
        $expensedata = [];
        $times = [];

        $financial_account = FinancialAccount::findFirstById($financial_account_id);
        $expense_categories = ExpenseCategory::getListActiveOfMyCompany();
        $account_ids = [];
        $accounts = Helpers::__getRequestValue('companies');
        if (count($accounts) > 0) {
            foreach ($accounts as $account) {
                $account_ids[] = $account->id;
            }
        }
        $relocation_ids = [];
        $relocations = Helpers::__getRequestValue('relocations');
        if (count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $relocation_ids[] = $relocation->id;
            }
        }

        if ($financial_account instanceof FinancialAccount && $financial_account->belongsToGms()) {
            $last_time = $end_date;
            $start_time = strtotime('first day of ' . date('F Y', $end_date));
            $i = 1;
            $continue = true;
            while ($continue) {
                if ($start_time <= $start_date) {
                    $start_time = $start_date;
                    $continue = false;
                }
                $months[] = date('F Y', $start_time);
                // get data
                if (count($accounts) == 0) {
                    $cashflowin = Transaction::sum([
                        "column" => "amount",
                        "conditions" => "company_id = :company_id: and is_deleted = 0 and direction = :direction: and transaction_type_id = :transaction_type_id: 
                    and date >= :start_date: and date < :end_date: and financial_account_id = :financial_account_id:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "direction" => Transaction::DIRECTION_IN,
                            "transaction_type_id" => TransactionType::TYPE_PAYMENT,
                            "start_date" => $start_time,
                            "end_date" => $last_time,
                            "financial_account_id" => $financial_account_id
                        ]
                    ]);
                    $cashflowindata[] = $cashflowin > 0 ? $cashflowin : 0;
                    $cashflowout = Transaction::sum([
                        "column" => "amount",
                        "conditions" => "company_id = :company_id: and is_deleted = 0 and direction = :direction: and transaction_type_id = :transaction_type_id: 
                    and date >= :start_date: and date < :end_date: and financial_account_id = :financial_account_id:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "direction" => Transaction::DIRECTION_OUT,
                            "transaction_type_id" => TransactionType::TYPE_PAYMENT,
                            "start_date" => $start_time,
                            "end_date" => $last_time,
                            "financial_account_id" => $financial_account_id
                        ]
                    ]);
                    $cashflowoutdata[] = $cashflowout > 0 ? $cashflowout : 0;
                    if (count($relocation_ids) == 0) {
                        $income = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: ",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_INVOICE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time
                            ]
                        ]);
                        $credit_note = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: ",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time
                            ]
                        ]);
                        $expense = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: ",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "paid_status" => Expense::STATUS_PAID,
                                "status" => Expense::STATUS_APPROVED,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                            ]
                        ]);
                    } else {
                        $income = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: and relocation_id IN ({relocation_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_INVOICE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "relocation_ids" => $relocation_ids,
                            ]
                        ]);
                        $credit_note = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: and relocation_id IN ({relocation_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "relocation_ids" => $relocation_ids,
                            ]
                        ]);

                        $expense = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "paid_status" => Expense::STATUS_PAID,
                                "status" => Expense::STATUS_APPROVED,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "relocation_ids" => $relocation_ids,
                                "link_type" => Expense::LINK_TYPE_RELOCATION
                            ]
                        ]);
                    }
                } else {
                    $cashflowin = Transaction::sum([
                        "column" => "amount",
                        "conditions" => "company_id = :company_id: and is_deleted = 0 and direction = :direction: and transaction_type_id = :transaction_type_id: 
                    and date >= :start_date: and date < :end_date: and financial_account_id = :financial_account_id: and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "direction" => Transaction::DIRECTION_IN,
                            "transaction_type_id" => TransactionType::TYPE_PAYMENT,
                            "start_date" => $start_time,
                            "end_date" => $last_time,
                            "financial_account_id" => $financial_account_id,
                            "account_ids" => $account_ids,
                        ]
                    ]);
                    $cashflowindata[] = $cashflowin > 0 ? $cashflowin : 0;
                    $cashflowout = Transaction::sum([
                        "column" => "amount",
                        "conditions" => "company_id = :company_id: and is_deleted = 0 and direction = :direction: and transaction_type_id = :transaction_type_id: 
                    and date >= :start_date: and date < :end_date: and financial_account_id = :financial_account_id: and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "direction" => Transaction::DIRECTION_OUT,
                            "transaction_type_id" => TransactionType::TYPE_PAYMENT,
                            "start_date" => $start_time,
                            "end_date" => $last_time,
                            "financial_account_id" => $financial_account_id,
                            "account_ids" => $account_ids,
                        ]
                    ]);
                    $cashflowoutdata[] = $cashflowout > 0 ? $cashflowout : 0;
                    if (count($relocation_ids) == 0) {
                        $income = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_INVOICE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "account_ids" => $account_ids,
                            ]
                        ]);
                        $credit_note = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "account_ids" => $account_ids,
                            ]
                        ]);

                        $expense = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "status" => Expense::STATUS_APPROVED,
                                "paid_status" => Expense::STATUS_PAID,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "account_ids" => $account_ids,
                            ]
                        ]);
                    } else {
                        $income = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_INVOICE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "account_ids" => $account_ids,
                                "relocation_ids" => $relocation_ids,
                            ]
                        ]);
                        $credit_note = InvoiceQuote::sum([
                            "column" => "total_before_tax",
                            "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                                "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "account_ids" => $account_ids,
                                "relocation_ids" => $relocation_ids,
                            ]
                        ]);

                        $expense = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "status" => Expense::STATUS_APPROVED,
                                "paid_status" => Expense::STATUS_PAID,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_time,
                                "end_date" => $last_time,
                                "account_ids" => $account_ids,
                                "relocation_ids" => $relocation_ids,
                                "link_type" => Expense::LINK_TYPE_RELOCATION
                            ]
                        ]);
                    }
                }

                $income = $income > 0 ? $income : 0;
                $credit_note = $credit_note > 0 ? $credit_note : 0;
                $expense = $expense > 0 ? $expense : 0;
                $incomedata[] = $income - $credit_note;
                $expensedata[] = $expense;
                //
                $times[] = [
                    "start_date" => date('Y-m-d H:i:s', $start_time),
                    "end_date" => date('Y-m-d H:i:s', $last_time)
                ];
                $last_time = $start_time;
                $start_time = strtotime(date('Y-m-01 00:00:00', $start_time) . " -$i months");

            }
        }

        $expense_name = [];
        $expense_value = [];

        if (count($account_ids) == 0) {
            if (count($relocation_ids) == 0) {
                $expense_total = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: ",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                    ]
                ]);
            } else {
                $expense_total = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "relocation_ids" => $relocation_ids,
                        "link_type" => Expense::LINK_TYPE_RELOCATION
                    ]
                ]);
            }
        } else {
            if (count($relocation_ids) == 0) {
                $expense_total = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency:  and account_id IN ({account_ids:array})",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "account_ids" => $account_ids,
                    ]
                ]);
            } else {
                $expense_total = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "account_ids" => $account_ids,
                        "relocation_ids" => $relocation_ids,
                        "link_type" => Expense::LINK_TYPE_RELOCATION
                    ]
                ]);
            }
        }
        $expense_total = $expense_total > 0 ? $expense_total : 0;
        if (count($expense_categories) > 0) {
            foreach ($expense_categories as $expense_category) {

                if (count($account_ids) == 0) {
                    if (count($relocation_ids) == 0) {
                        $value = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id:",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "status" => Expense::STATUS_APPROVED,
                                "paid_status" => Expense::STATUS_PAID,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_date,
                                "end_date" => $end_date,
                                "expense_category_id" => $expense_category["id"],
                            ]
                        ]);
                    } else {
                        $value = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id: and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "status" => Expense::STATUS_APPROVED,
                                "paid_status" => Expense::STATUS_PAID,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_date,
                                "end_date" => $end_date,
                                "expense_category_id" => $expense_category["id"],
                                "relocation_ids" => $relocation_ids,
                                "link_type" => Expense::LINK_TYPE_RELOCATION
                            ]
                        ]);
                    }
                } else {
                    if (count($relocation_ids) == 0) {
                        $value = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id:  and account_id IN ({account_ids:array})",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "status" => Expense::STATUS_APPROVED,
                                "paid_status" => Expense::STATUS_PAID,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_date,
                                "end_date" => $end_date,
                                "expense_category_id" => $expense_category["id"],
                                "account_ids" => $account_ids,
                            ]
                        ]);
                    } else {
                        $value = Expense::sum([
                            "column" => "cost",
                            "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                            "bind" => [
                                "company_id" => ModuleModel::$company->getId(),
                                "status" => Expense::STATUS_APPROVED,
                                "paid_status" => Expense::STATUS_PAID,
                                "currency" => $financial_account->getCurrency(),
                                "start_date" => $start_date,
                                "end_date" => $end_date,
                                "expense_category_id" => $expense_category["id"],
                                "account_ids" => $account_ids,
                                "relocation_ids" => $relocation_ids,
                                "link_type" => Expense::LINK_TYPE_RELOCATION
                            ]
                        ]);
                    }
                }
                $value = $value > 0 ? $value : 0;
                if ($expense_total > 0) {

                    if ($value > 0) {
                        $expense_name[] = $expense_category["name"] . (($expense_category["external_hris_id"] != "" && $expense_category["external_hris_id"] != null) ? (" - " . $expense_category["external_hris_id"]) : "");
                        $expense_value[] = floatval(number_format((float)$value / $expense_total * 100, 2, '.', ''));
                    }
                } else {
                    $expense_name[] = $expense_category["name"] . (($expense_category["external_hris_id"] != "" && $expense_category["external_hris_id"] != null) ? (" - " . $expense_category["external_hris_id"]) : "");
                    $expense_value[] = 0;
                }
            }
        }
        if (count($account_ids) == 0) {
            if (count($relocation_ids) == 0) {
                $cost_of_product = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                    ]
                ]);
            } else {
                $cost_of_product = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "relocation_ids" => $relocation_ids,
                        "link_type" => Expense::LINK_TYPE_RELOCATION
                    ]
                ]);
            }
        } else {
            if (count($relocation_ids) == 0) {
                $cost_of_product = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)  and account_id IN ({account_ids:array})",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "account_ids" => $account_ids,
                    ]
                ]);
            } else {
                $cost_of_product = Expense::sum([
                    "column" => "cost",
                    "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId(),
                        "status" => Expense::STATUS_APPROVED,
                        "paid_status" => Expense::STATUS_PAID,
                        "currency" => $financial_account->getCurrency(),
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "account_ids" => $account_ids,
                        "relocation_ids" => $relocation_ids,
                        "link_type" => Expense::LINK_TYPE_RELOCATION
                    ]
                ]);
            }
        }

        $cost_of_product = $cost_of_product > 0 ? $cost_of_product : 0;
        if ($expense_total > 0) {
            $expense_value[] = floatval(number_format((float)$cost_of_product / $expense_total * 100, 2, '.', ''));
        } else {
            $expense_value[] = 0;

        }

        $this->response->setJsonContent([
            'success' => true,
            'months' => array_reverse($months),
            'cashflowoutdata' => array_reverse($cashflowoutdata),
            'cashflowindata' => array_reverse($cashflowindata),
            'incomedata' => array_reverse($incomedata),
            'expensedata' => array_reverse($expensedata),
            'expense_name' => $expense_name,
            'expense_value' => $expense_value,
            'time' => array_reverse($times),
        ]);
        $this->response->send();
    }

    public function getReportStatisticAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();
        $account_ids = [];
        $relocation_ids = [];
        $accounts = Helpers::__getRequestValue('companies');
        if (count($accounts) > 0) {
            foreach ($accounts as $account) {
                $account_ids[] = $account->id;
            }
        }

        $relocations = Helpers::__getRequestValue('relocations');
        if (count($relocations) > 0) {
            foreach ($relocations as $relocation) {
                $relocation_ids[] = $relocation->id;
            }
        }

        $this->response->setJsonContent($this->getReportStatistic($account_ids, $relocation_ids));
        $this->response->send();
    }

    public function getRelocationInvoicingReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $uuid = Helpers::__getRequestValue('uuid');

        $relocation = Relocation::findFirstByUuid($uuid);

        if (!$relocation instanceof Relocation || !$relocation->belongsToGms()) {
            $result = [
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT',
                'relocation' => $relocation
            ];
            goto end_of_function;
        }

        $quotes = InvoiceQuote::find([
            "conditions" => "status != :archived_status: and relocation_id = :relocation_id: and type = :type:",
            "bind" => [
                "archived_status" => InvoiceQuote::STATUS_ARCHIVED,
                "type" => InvoiceQuote::TYPE_QUOTE,
                "relocation_id" => $relocation->getId(),
            ],
            'order' => 'created_at DESC'
        ]);
        $quoteArray = [];
        if (count($quotes) > 0){
            foreach ($quotes as $quote){
                $item = $quote->toArray();
                $item['account_name'] = $quote->getAccount() ? $quote->getAccount()->getName() : null;
                $item['employee_firstname'] = $quote->getEmployee() ? $quote->getEmployee()->getFirstname() : null;
                $item['employee_lastname'] = $quote->getEmployee() ? $quote->getEmployee()->getLastname() : null;

                $quoteArray[] = $item;
            }
        }

        $invoices = InvoiceQuote::find([
            "conditions" => "status != :archived_status: and relocation_id = :relocation_id: and type = :type:",
            "bind" => [
                "archived_status" => InvoiceQuote::STATUS_ARCHIVED,
                "type" => InvoiceQuote::TYPE_INVOICE,
                "relocation_id" => $relocation->getId(),
            ],
            'order' => 'created_at DESC'
        ]);
        $invoiceArray = [];
        if (count($invoices) > 0){
            foreach ($invoices as $invoice){
                $item = $invoice->toArray();
                $item['account_name'] = $invoice->getAccount() ? $invoice->getAccount()->getName() : null;
                $item['employee_firstname'] = $invoice->getEmployee() ? $invoice->getEmployee()->getFirstname() : null;
                $item['employee_lastname'] = $invoice->getEmployee() ? $invoice->getEmployee()->getLastname() : null;

                $invoiceArray[] = $item;
            }
        }

        $expenses = Expense::find([
            "conditions" => "status != :archived_status: and relocation_id = :relocation_id:",
            "bind" => [
                "archived_status" => Expense::STATUS_ARCHIVED,
                "relocation_id" => $relocation->getId(),
            ],
            'order' => 'created_at DESC'
        ]);
        $expense_array = [];
        if (count($expenses) > 0) {
            foreach ($expenses as $expense) {
                $item = $expense->toArray();
                $item["category_code"] = $expense->getExpenseCategory() instanceof ExpenseCategory ? $expense->getExpenseCategory()->getExternalHrisId() : null;
                $item["category_name"] = $expense->getExpenseCategory() instanceof ExpenseCategory ? $expense->getExpenseCategory()->getName() : null;
                $item["product_name"] = $expense->getProductPricing() instanceof ProductPricing ? $expense->getProductPricing()->getName() : null;
                $item["account_product_name"] = $expense->getAccountProductPricing() instanceof AccountProductPricing ? $expense->getAccountProductPricing()->getName() : null;
                $item["bill_uuid"] = $expense->getBill() ? $expense->getBill()->getUuid() : null;
                $item["bill_number"] = $expense->getBill() ? $expense->getBill()->getNumber() : null;
                $item["bill_reference"] = $expense->getBill() ? $expense->getBill()->getReference() : null;
                $expense_array[] = $item;
            }
        }
        $invoiceableItems = InvoiceableItem::find([
            "conditions" => "relocation_id = :relocation_id:",
            "bind" => [
                "relocation_id" => $relocation->getId(),
            ],
            'order' => 'created_at DESC'
        ]);

        $invoiceableItemsArray = [];
        if (count($invoiceableItems) > 0) {
            foreach ($invoiceableItems as $invoiceableItem) {
                $item = $invoiceableItem->toArray();
                $expense = $invoiceableItem->getExpense();
                $item["invoiceable_item_uuid"] = $invoiceableItem->getUuid(); //$invoiceableItem->getInvoiceableItem() instanceof InvoiceableItem ? $invoiceableItem->getInvoiceableItem()->getUuid() : null;
                $item["expense_uuid"] = $expense ? $expense->getUuid() : null;
                $item["expense_number"] = $expense ? $expense->getNumber() : null;
                $item["category_name"] = $expense && $expense->getExpenseCategory() instanceof ExpenseCategory ? $expense->getExpenseCategory()->getName() : null;

                $invoiceQuoteItem = $invoiceableItem->getInvoiceQuoteItem();
                $invoice = $invoiceableItem->getInvoice();
                $item["invoice"] = $invoice ? $invoice : null;
                $item["invoice_uuid"] = $invoice ? $invoice->getUuid() : null;
                $item["invoice_status"] = $invoice ? $invoice->getStatus() : null;
                $item["invoice_number"] = $invoice ? $invoice->getNumber() : null;

                $item['account_name'] = $invoiceableItem->getAccount() ? $invoiceableItem->getAccount()->getName() : null;
                $invoiceableItemsArray[] = $item;
            }
        }

//        $profitAndLoss = $relocation->getProfitAndLossByDefaultAccount();

        $result = [
            'success' => true,
            'quotes' => $quoteArray,
            'expenses' => $expense_array,
            'invoices' => $invoiceArray,
            'invoiceables' => $invoiceableItemsArray,
//            'profitAndLoss' => $profitAndLoss,
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function getServiceInvoicingReportAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $uuid = Helpers::__getRequestValue('uuid');

        $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($uuid);

        if (!$relocationServiceCompany instanceof RelocationServiceCompany || !$relocationServiceCompany->belongsToGms()) {
            $result = [
                'success' => false,
                'message' => 'RELOCATION_SERVICE_COMPANY_NOT_FOUND_TEXT',
                'relocation' => $relocationServiceCompany
            ];
            goto end_of_function;
        }

        $expenses = Expense::find([
            "conditions" => "status != :archived_status: AND relocation_id = :relocation_id: AND relocation_service_company_id = :relocation_service_company_id: ",
            "bind" => [
                "archived_status" => Expense::STATUS_ARCHIVED,
                "relocation_id" => $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getId() : null,
                "relocation_service_company_id" => $relocationServiceCompany->getId()
            ],
            'order' => 'created_at DESC'
        ]);
        $expense_array = [];
        if (count($expenses) > 0) {
            foreach ($expenses as $expense) {
                $item = $expense->toArray();
                $item["category_code"] = $expense->getExpenseCategory() instanceof ExpenseCategory ? $expense->getExpenseCategory()->getExternalHrisId() : null;
                $item["category_name"] = $expense->getExpenseCategory() instanceof ExpenseCategory ? $expense->getExpenseCategory()->getName() : null;
                $item["product_name"] = $expense->getProductPricing() instanceof ProductPricing ? $expense->getProductPricing()->getName() : null;
                $item["account_product_name"] = $expense->getAccountProductPricing() instanceof AccountProductPricing ? $expense->getAccountProductPricing()->getName() : null;
                $item["bill_uuid"] = $expense->getBill() ? $expense->getBill()->getUuid() : null;
                $item["bill_number"] = $expense->getBill() ? $expense->getBill()->getNumber() : null;
                $item["bill_reference"] = $expense->getBill() ? $expense->getBill()->getReference() : null;
                $expense_array[] = $item;
            }
        }

        $result = [
            'success' => true,
            'expenses' => $expense_array,
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function getRelocationProfitAndLossAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $uuid = Helpers::__getRequestValue('uuid');
        $financial_account_id = Helpers::__getRequestValue('financial_account_id');

        $relocation = Relocation::findFirstByUuid($uuid);

        if (!$relocation instanceof Relocation || !$relocation->belongsToGms()) {
            $result = [
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT',
                'relocation' => $relocation
            ];
            goto end_of_function;
        }

        $profitAndLoss = $relocation->getProfitAndLossByDefaultAccount($financial_account_id);

        $result = [
            'success' => true,
            'data' => $profitAndLoss,
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function generateRelocationReportExcelAction()
    {
        $this->view->disable();
        $this->checkAclIndex();
        $uuid = Helpers::__getRequestValue('uuid');
        $financial_account_id = Helpers::__getRequestValue('financial_account_id');

        $relocation = Relocation::findFirstByUuid($uuid);

        if (!$relocation instanceof Relocation || !$relocation->belongsToGms()) {
            $result = [
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT',
                'relocation' => $relocation
            ];
            goto end_of_function;
        }
        $result = $relocation->getProfitAndLossByDefaultAccount($financial_account_id);
       
        $background_color = new Color('EEEEEE');
        $color_text = new Color('888888');
                
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle("A1:O100")->getFont()->setName('Arial')->setSize(10);
        
        $sheet->getStyle("A1")->getFont()->setBold(true);        
        $sheet->setCellValue('A1',Constant::__translateConstant('REPORT_PROFIT_LOSS_TEXT', ModuleModel::$language));

        $sheet->getStyle("A3:B3")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A3', Constant::__translateConstant('RELOCATION_TEXT', ModuleModel::$language));
        $sheet->getStyle("A3")->getFont()->setColor($color_text);
        $sheet->setCellValue('B3', $relocation->getIdentify());

        $sheet->getStyle("A4:B4")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A4', Constant::__translateConstant('ASSIGNEE_TEXT', ModuleModel::$language));
        $sheet->getStyle("A4")->getFont()->setColor($color_text);
        $sheet->setCellValue('B4', $relocation->getEmployee()->getFullname());

        $sheet->getStyle("A5:B5")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A5', Constant::__translateConstant('ACCOUNT_TEXT', ModuleModel::$language));
        $sheet->getStyle("A5")->getFont()->setColor($color_text);
        $sheet->setCellValue('B5', $relocation->getHrCompany()->getName());

        $sheet->getStyle("A6:B6")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A6', Constant::__translateConstant('BOOKER_TEXT', ModuleModel::$language));
        $sheet->getStyle("A6")->getFont()->setColor($color_text);
        $sheet->setCellValue('B6', $relocation->getAssignment()->getBookerCompany() ? $relocation->getAssignment()->getBookerCompany()->getName() : 'N/A');

        $index = 8;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('INCOME_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format(($result["income"] + $result["taxes"]), 2). " ". $result["currency"] );
        
        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('COST_OF_PRODUCTS_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["cost_of_products"], 2). " ". $result["currency"] );

        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('TAXES_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["taxes"], 2). " ". $result["currency"] );

        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('GROSS_PROFIT_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["income"] - $result["cost_of_products"], 2). " ". $result["currency"] );

         $index++;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('GROSS_PROFIT_DESCRIPTION_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, (($result["income"]+ $result["taxes"]) > 0 ? (number_format(($result["income"] - $result["cost_of_products"]) / ($result["income"]+ $result["taxes"]) * 100, 2)) : 0). " %");

        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('EXPENSES_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["expense"], 2). " ". $result["currency"] );

        $index++;
        if(count($result["expense_category_list"]) > 0){
            foreach($result["expense_category_list"] as $expense){
                $sheet->setCellValue('A'.$index, $expense["name"]);
                $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
                $sheet->setCellValue('B'.$index, number_format($expense["value"], 2). " ". $result["currency"] );
                $index ++;
            }          
        }
        $index = $index++;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('TAX_ON_EXPENSES_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["expense_tax"], 2). " ". $result["currency"] );
        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('NET_PROFIT_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["income"] - $result["cost_of_products"] - ($result["expense"] - $result["expense_tax"]), 2). " ". $result["currency"] );

        $index ++;

        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('NET_PROFIT_DESCRIPTION_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index,  (($result["income"]+ $result["taxes"]) > 0 ? number_format(($result["income"] - $result["cost_of_products"] - ($result["expense"] - $result["expense_tax"])) / ($result["income"]+ $result["taxes"]) * 100, 2) : 0). " %");

        
        $sheet->getColumnDimension("A")->setWidth(25);
        $sheet->getColumnDimension("B")->setWidth(25);
                

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd->ms-excel');
        header('Content-Disposition: attachment; filename="finance-report-relocation-"'.$relocation->getIdentify().'".xlsx"');
        $writer->save("php://output");
        exit;
        return $spreadsheet;
        end_of_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST, 'Data Not Found');
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function generateReportExcelAction()
    {
        $this->view->disable();
        $this->checkAclIndex();
        $account_ids = explode(',', Helpers::__getRequestValue('companies'));
        $relocation_ids = explode(',', Helpers::__getRequestValue('relocations'));
        $result = $this->getReportStatistic($account_ids, $relocation_ids);
        $phpDateFormat = ModuleModel::$company->getPhpDateFormat();
       
        $background_color = new Color('EEEEEE');
        $color_text = new Color('888888');
                
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle("A1:O100")->getFont()->setName('Arial')->setSize(10);
        
        $sheet->getStyle("A1")->getFont()->setBold(true);        
        $sheet->setCellValue('A1',Constant::__translateConstant('REPORT_PROFIT_LOSS_TEXT', ModuleModel::$language));

        $sheet->getStyle("A3:B3")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A3', Constant::__translateConstant('RELOCATION_TEXT', ModuleModel::$language));
        $sheet->getStyle("A3")->getFont()->setColor($color_text);
         if (count($relocation_ids) > 0) {
            $index_column = 2;
            $index_row = 3;
            foreach ($relocation_ids as $relocation) {
                $relocation_object = Relocation::findFirstById($relocation);
                if($relocation_object){
                    $sheet->getCellByColumnAndRow($index_column, $index_row)->setValue($relocation_object->getIdentify());
                    $sheet->getCellByColumnAndRow($index_column, $index_row)->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
                    $sheet->getColumnDimension($sheet->getCellByColumnAndRow($index_column, $index_row)->getColumn())->setWidth(25);
                    $index_column ++;
                }
            }
            if($index_column == 2){
                $sheet->setCellValue('B3', 'N/A' );
            }
        } else {
            $sheet->setCellValue('B3', 'N/A' );
        }
        $index = 4;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue("A".$index, Constant::__translateConstant('ACCOUNT_TEXT', ModuleModel::$language));
        $sheet->getStyle("A".$index)->getFont()->setColor($color_text);
         if (count($account_ids) > 0) {
            $index_column = 2;
            foreach ($account_ids as $account) {
                $account_object = Company::findFirstById($account);
                if($account_object){
                    $sheet->getCellByColumnAndRow($index_column, $index)->setValue($account_object->getName());
                    $sheet->getCellByColumnAndRow($index_column, $index)->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
                    $sheet->getColumnDimension($sheet->getCellByColumnAndRow($index_column, $index)->getColumn())->setWidth(25);
                    $index_column ++;
                }
            }
            if($index_column == 2){
                $sheet->setCellValue("B".$index, 'N/A' );
            }
        } else {
            $sheet->setCellValue("B".$index, 'N/A' );
        }

        $start_date = intval(Helpers::__getRequestValue("start_date"));
        $end_date = intval(Helpers::__getRequestValue("end_date"));
        $index++;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('START_DATE_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index,  date($phpDateFormat, $start_date));

        $index++;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('END_DATE_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index,  date($phpDateFormat, $end_date) );

        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('INCOME_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format(($result["income"] + $result["taxes"]), 2). " ". $result["currency"] );
        
        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('COST_OF_PRODUCTS_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["cost_of_products"], 2). " ". $result["currency"] );

        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('TAXES_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["taxes"], 2). " ". $result["currency"] );

        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('GROSS_PROFIT_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["income"] - $result["cost_of_products"], 2). " ". $result["currency"] );

        $index++;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('GROSS_PROFIT_DESCRIPTION_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, (($result["income"]+ $result["taxes"]) > 0 ? (number_format(($result["income"] - $result["cost_of_products"]) / ($result["income"]+ $result["taxes"]) * 100, 2)) : 0). " %");

        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('EXPENSES_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["expense"], 2). " ". $result["currency"] );

        $index++;
        if(count($result["expense_category"]) > 0){
            foreach($result["expense_category"] as $expense){
               
                $sheet->setCellValue('A'.$index, $expense["name"]);
                $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
                $sheet->setCellValue('B'.$index, number_format($expense["value"], 2). " ". $result["currency"] );
                $index ++;
            }          
        }
        $index ++;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('TAX_ON_EXPENSES_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["expense_tax"], 2). " ". $result["currency"] );
        $index = $index+2;
        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('NET_PROFIT_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index, number_format($result["income"] - $result["cost_of_products"] - ($result["expense"] - $result["expense_tax"]), 2). " ". $result["currency"] );

        $index ++;

        $sheet->getStyle("A".$index.":B".$index)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->setStartColor($background_color);
        $sheet->setCellValue('A'.$index, Constant::__translateConstant('NET_PROFIT_DESCRIPTION_TEXT', ModuleModel::$language));
        $sheet->getStyle('A'.$index)->getFont()->setColor($color_text);
        $sheet->setCellValue('B'.$index,  (($result["income"]+ $result["taxes"]) > 0 ? number_format(($result["income"] - $result["cost_of_products"] - ($result["expense"] - $result["expense_tax"])) / ($result["income"]+ $result["taxes"]) * 100, 2) : 0). " %");

        
        $sheet->getColumnDimension("A")->setWidth(25);
        $sheet->getColumnDimension("B")->setWidth(25);
                

        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd->ms-excel');
        header('Content-Disposition: attachment; filename="finance-report.xlsx"');
        $writer->save("php://output");
        exit;
        return $spreadsheet;
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST, 'Data Not Found');
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }



    public function getReportStatistic($account_ids = [], $relocation_ids = [])
    {

        $financial_account_id = intval(Helpers::__getRequestValue('financial_account_id'));
        $start_date = intval(Helpers::__getRequestValue("start_date"));
        $end_date = intval(Helpers::__getRequestValue("end_date")) + 86400;
        $income = 0;
        $expense = 0;
        $cost_of_products = 0;
       
        $expense_category_list = [];
        

        $financial_account = FinancialAccount::findFirstById($financial_account_id);
        if ($financial_account instanceof FinancialAccount && $financial_account->belongsToGms()) {
            $expense_categories = ExpenseCategory::getListActiveOfMyCompany();
            if (count($account_ids) == 0 || (count($account_ids) == 1 && $account_ids[0] == null)) {
                if (count($relocation_ids) == 0|| (count($relocation_ids) == 1 && $relocation_ids[0] == null)) {
                    $expense = Expense::sum([
                        "column" => "total",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                        ]
                    ]);
                    $expense_tax = Expense::sum([
                        "column" => "total - cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                        ]
                    ]);
                    $cost_of_products = Expense::sum([
                        "column" => "cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                        ]
                    ]);

                    $invoice = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: ",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                        ]
                    ]);

                    $invoice_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: ",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                        ]
                    ]);

                    $credit = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: ",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                        ]
                    ]);

                    $credit_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: ",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                        ]
                    ]);
                } else {
                    $expense = Expense::sum([
                        "column" => "total",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "relocation_ids" => $relocation_ids,
                            "link_type" => Expense::LINK_TYPE_RELOCATION
                        ]
                    ]);
                    $expense_tax = Expense::sum([
                        "column" => "total-cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "relocation_ids" => $relocation_ids,
                            "link_type" => Expense::LINK_TYPE_RELOCATION
                        ]
                    ]);

                    $cost_of_products = Expense::sum([
                        "column" => "cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "relocation_ids" => $relocation_ids,
                            "link_type" => Expense::LINK_TYPE_RELOCATION
                        ]
                    ]);

                    $invoice = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);

                    $credit = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);

                    $invoice_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);

                    $credit_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date: and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);
                }
            } else {
                if (count($relocation_ids) == 0) {
                    $expense = Expense::sum([
                        "column" => "total",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids
                        ]
                    ]);
                    $expense_tax = Expense::sum([
                        "column" => "total - cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids
                        ]
                    ]);


                    $cost_of_products = Expense::sum([
                        "column" => "cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids
                        ]
                    ]);

                    $invoice = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                        ]
                    ]);

                    $credit = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                        ]
                    ]);

                    $invoice_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                        ]
                    ]);

                    $credit_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                        ]
                    ]);
                } else {
                    $expense = Expense::sum([
                        "column" => "total",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                            "relocation_ids" => $relocation_ids,
                            "link_type" => Expense::LINK_TYPE_RELOCATION
                        ]
                    ]);
                    $expense_tax = Expense::sum([
                        "column" => "total - cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is not null
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                            "relocation_ids" => $relocation_ids,
                            "link_type" => Expense::LINK_TYPE_RELOCATION
                        ]
                    ]);

                    $cost_of_products = Expense::sum([
                        "column" => "cost",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)  and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                            "relocation_ids" => $relocation_ids,
                            "link_type" => Expense::LINK_TYPE_RELOCATION
                        ]
                    ]);

                    $invoice = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);

                    $credit = InvoiceQuote::sum([
                        "column" => "total_before_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);

                    $invoice_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_INVOICE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);

                    $credit_tax = InvoiceQuote::sum([
                        "column" => "total_tax",
                        "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and date >= :start_date: and date < :end_date:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array})",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                            "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                            "currency" => $financial_account->getCurrency(),
                            "start_date" => $start_date,
                            "end_date" => $end_date,
                            "account_ids" => $account_ids,
                            "relocation_ids" => $relocation_ids,
                        ]
                    ]);
                }
            }
            if (count($expense_categories) > 0) {
                foreach ($expense_categories as $expense_category) {
                    if (count($account_ids) == 0|| (count($account_ids) == 1 && $account_ids[0] == null)) {
                        if (count($relocation_ids) == 0|| (count($relocation_ids) == 1 && $relocation_ids[0] == null)) {
                            $value = Expense::sum([
                                "column" => "total",
                                "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id:",
                                "bind" => [
                                    "company_id" => ModuleModel::$company->getId(),
                                    "status" => Expense::STATUS_APPROVED,
                                    "paid_status" => Expense::STATUS_PAID,
                                    "currency" => $financial_account->getCurrency(),
                                    "start_date" => $start_date,
                                    "end_date" => $end_date,
                                    "expense_category_id" => $expense_category["id"],
                                ]
                            ]);
                        } else {
                            $value = Expense::sum([
                                "column" => "total",
                                "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id: and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                                "bind" => [
                                    "company_id" => ModuleModel::$company->getId(),
                                    "status" => Expense::STATUS_APPROVED,
                                    "paid_status" => Expense::STATUS_PAID,
                                    "currency" => $financial_account->getCurrency(),
                                    "start_date" => $start_date,
                                    "end_date" => $end_date,
                                    "expense_category_id" => $expense_category["id"],
                                    "relocation_ids" => $relocation_ids,
                                    "link_type" => Expense::LINK_TYPE_RELOCATION
                                ]
                            ]);
                        }
                    } else {
                        if (count($relocation_ids) == 0) {
                            $value = Expense::sum([
                                "column" => "total",
                                "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id:  and account_id IN ({account_ids:array})",
                                "bind" => [
                                    "company_id" => ModuleModel::$company->getId(),
                                    "status" => Expense::STATUS_APPROVED,
                                    "paid_status" => Expense::STATUS_PAID,
                                    "currency" => $financial_account->getCurrency(),
                                    "start_date" => $start_date,
                                    "end_date" => $end_date,
                                    "expense_category_id" => $expense_category["id"],
                                    "account_ids" => $account_ids,
                                ]
                            ]);
                        } else {
                            $value = Expense::sum([
                                "column" => "total",
                                "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and expense_date >= :start_date: and expense_date < :end_date: and currency = :currency: and expense_category_id = :expense_category_id:  and account_id IN ({account_ids:array}) and relocation_id IN ({relocation_ids:array}) and link_type = :link_type:",
                                "bind" => [
                                    "company_id" => ModuleModel::$company->getId(),
                                    "status" => Expense::STATUS_APPROVED,
                                    "paid_status" => Expense::STATUS_PAID,
                                    "currency" => $financial_account->getCurrency(),
                                    "start_date" => $start_date,
                                    "end_date" => $end_date,
                                    "expense_category_id" => $expense_category["id"],
                                    "account_ids" => $account_ids,
                                    "relocation_ids" => $relocation_ids,
                                    "link_type" => Expense::LINK_TYPE_RELOCATION
                                ]
                            ]);
                        }
                    }
                    if ($value > 0) {
                        $expense_category_list[] = [
                            "name" => $expense_category["name"]  . (($expense_category["external_hris_id"] != "" && $expense_category["external_hris_id"] != null) ? (" - " . $expense_category["external_hris_id"]) : ""),
                            "value" => $value
                        ];
                    }
                }
            }

            $income = $invoice - $credit;

        }

        return [
            'success' => true,
            'income' => $income,
            'expense' => $expense,
            'expense_tax' => $expense_tax,
            'invoice' => $invoice,
            'credit' => $credit,
            'taxes' => $invoice_tax - $credit_tax,
            'cost_of_products' => $cost_of_products,
            "currency" => $financial_account->getCurrency(),
            'expense_category' => $expense_category_list
        ];
    }
}
