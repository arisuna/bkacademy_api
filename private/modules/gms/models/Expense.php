<?php

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;

class Expense extends \Reloday\Application\Models\ExpenseExt
{
    const LIMIT_PER_PAGE = 20;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('account_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Account',
            'cache' => [
                'key' => 'COMPANY_' . $this->getAccountId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('product_pricing_id', 'Reloday\Gms\Models\ProductPricing', 'id', [
            'alias' => 'ProductPricing'
        ]);
        $this->belongsTo('account_product_pricing_id', 'Reloday\Gms\Models\AccountProductPricing', 'id', [
            'alias' => 'AccountProductPricing'
        ]);
        $this->belongsTo('bill_id', 'Reloday\Gms\Models\Bill', 'id', [
            'alias' => 'Bill'
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'cache' => [
                'key' => 'COMPANY_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('expense_category_id', 'Reloday\Gms\Models\ExpenseCategory', 'id', [
            'alias' => 'ExpenseCategory',
            'cache' => [
                'key' => 'EXPENSE_CATEGORY_' . $this->getExpenseCategoryId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation'
        ]);
        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', [
            'alias' => 'RelocationServiceCompany'
        ]);
        $this->belongsTo('service_provider_id', 'Reloday\Gms\Models\ServiceProviderCompany', 'id', [
            'alias' => 'ServiceProviderCompany'
        ]);
        $this->belongsTo('tax_rule_id', 'Reloday\Gms\Models\TaxRule', 'id', [
            'alias' => 'TaxRule'
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\Payment', 'expense_id', [
            'alias' => 'Payments',
            'params' => [
                "conditions" => "is_deleted = 0"
            ]
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
        $queryBuilder->addFrom('\Reloday\Gms\Models\Expense', 'Expense');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Expense.id',
            'Expense.uuid',
            'Expense.number',
            'Expense.expense_date',
            'Expense.expense_category_id',
            'Expense.currency',
            'Expense.cost',
            'Expense.account_id',
            'Expense.relocation_id',
            'Expense.relocation_service_company_id',
            'Expense.status',
            'Expense.total',
            'Expense.total_paid',
            'Expense.product_pricing_id',
            'Expense.account_product_pricing_id',
            'Expense.tax_rule_id',
            'Expense.bill_id',
            'Expense.note',
            'Expense.quantity',
            'tax_amount' => 'Expense.total - Expense.cost ',
            'employee_name' => 'CONCAT(Employee.firstname,\' \', Employee.lastname)',
            'product_name' => 'ProductPricing.name',
            'account_product_name' => 'AccountProductPricing.name',
            'employee_uuid' => 'Employee.uuid',
            'relocation_identify' => 'Relocation.identify',
            'bill_uuid' => 'Bill.uuid',
            'bill_number' => 'Bill.number',
            'bill_reference' => 'Bill.reference',
            'account_name' => 'Account.name',
            'category_name' => 'ExpenseCategory.name',
            'category_code' => 'ExpenseCategory.external_hris_id',
            'tax_rate' => 'TaxRule.rate',
            'tax_rule_name' => 'TaxRule.name',
            'expense_category_unit' => 'ExpenseCategory.unit',
            'expense_cost' => 'ExpenseCategory.cost',
            'provider_name' => 'ServiceProviderCompany.name',
            'service_provider_id' => 'Expense.service_provider_id'
        ]);

        $queryBuilder->leftJoin('\Reloday\Gms\Models\ExpenseCategory', 'ExpenseCategory.id = Expense.expense_category_id ', 'ExpenseCategory');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ProductPricing', 'ProductPricing.id = Expense.product_pricing_id ', 'ProductPricing');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AccountProductPricing', 'AccountProductPricing.id = Expense.account_product_pricing_id ', 'AccountProductPricing');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Company', 'Account.id = Expense.account_id', 'Account');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany.id = Expense.service_provider_id', 'ServiceProviderCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = Expense.relocation_id AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Employee.id = Relocation.employee_id ', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = Expense.relocation_service_company_id ', 'RelocationServiceCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Bill', 'Bill.id = Expense.bill_id ', 'Bill');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\TaxRule', 'TaxRule.id = Expense.tax_rule_id ', 'TaxRule');

        $queryBuilder->where('Expense.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andWhere('Expense.status <> :status:', [
            'status' => self::STATUS_ARCHIVED
        ]);

        if (count($options["assignees"]) > 0) {
            $queryBuilder->andwhere('Employee.id IN ({assignees:array} )', [
                'assignees' => $options["assignees"]
            ]);
        }

        if (count($options["exclude_items"]) > 0) {
            $queryBuilder->andwhere('Expense.uuid NOT IN ({exclude_items:array} )', [
                'exclude_items' => $options["exclude_items"]
            ]);
        }

        if (count($options["products"]) > 0) {
            $queryBuilder->andwhere('ProductPricing.id IN ({products:array}) OR AccountProductPricing.product_pricing_id IN ({products:array})', [
                'products' => $options["products"]
            ]);
        }

        if ($options["not_belong_to_other_bill"]) {
            if ($options['bill_id'] > 0) {
                $queryBuilder->andwhere('(Expense.bill_id = :bill_id: or  Expense.bill_id is null)', [
                    'bill_id' => $options["bill_id"]
                ]);
            } else {
                $queryBuilder->andwhere('Expense.bill_id is null');
            }
        }

        if ($options["currency"] != "" && $options["currency"] != null) {
            $queryBuilder->andwhere('(Expense.currency = :currency:)', [
                'currency' => $options["currency"]
            ]);
        }


        if (count($options["relocations"]) > 0) {
            $queryBuilder->andwhere('Relocation.id IN ({relocation_ids:array} )', [
                'relocation_ids' => $options["relocations"]
            ]);
        }

        if (count($options["companies"]) > 0) {
            $queryBuilder->andwhere('Account.id IN ({account_ids:array} ) and (Expense.chargeable_type = :chargeable_account: or Expense.chargeable_type = :chargeable_assignee: or Expense.chargeable_type = :chargeable_none:)', [
                'account_ids' => $options["companies"],
                'chargeable_account' => Expense::CHARGE_TO_ACCOUNT,
                'chargeable_assignee' => Expense::CHARGE_TO_EMPLOYEE,
                'chargeable_none' => Expense::CHARGE_TO_NONE
            ]);
        }

        if (count($options["providers"]) > 0) {
            $queryBuilder->andwhere('Expense.service_provider_id IN ({providers:array} ) and Expense.is_payable = 1', [
                'providers' => $options["providers"]
            ]);
        }

        if (count($options["categories"]) > 0) {
            $queryBuilder->andwhere('Expense.expense_category_id IN ({categories:array} )', [
                'categories' => $options["categories"]
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('Expense.number Like :query:', [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['start_date']) && is_numeric($options['start_date']) && $options['start_date'] > 0) {
            $queryBuilder->andwhere('Expense.expense_date >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if (isset($options['end_date']) && is_numeric($options['end_date']) && $options['end_date'] > 0) {
            $queryBuilder->andwhere('Expense.expense_date < :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }

        if (count($options["status"]) > 0) {
            $queryBuilder->andwhere('Expense.status IN ({statuses:array} )', [
                'statuses' => $options["status"]
            ]);
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy(['Expense.expense_date DESC']);
            if ($order['field'] == "ref") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Expense.identify ASC']);
                } else {
                    $queryBuilder->orderBy(['Expense.identify DESC']);
                }
            }

            if ($order['field'] == "date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Expense.expense_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Expense.expense_date DESC']);
                }
            }

            if ($order['field'] == "category") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ExpenseCategory.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ExpenseCategory.name DESC']);
                }
            }

            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Expense.status ASC']);
                } else {
                    $queryBuilder->orderBy(['Expense.status DESC']);
                }
            }

            if ($order['field'] == "account") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Account.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Account.name DESC']);
                }
            }

            if ($order['field'] == "assignee_relocation") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC', 'Employee.lastname ASC', 'Relocation.identify ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC', 'Employee.lastname DESC', 'Relocation.identify DESC']);
                }
            }

            if ($order['field'] == "cost_currency") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Expense.cost ASC', 'Expense.currency ASC']);
                } else {
                    $queryBuilder->orderBy(['Expense.cost DESC', 'Expense.currency DESC']);
                }
            }

            if ($order['field'] == "total") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Expense.total ASC']);
                } else {
                    $queryBuilder->orderBy(['Expense.total DESC']);
                }
            }

            if ($order['field'] == "total_paid") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Expense.total_paid ASC']);
                } else {
                    $queryBuilder->orderBy(['Expense.total_paid DESC']);
                }
            }

            if ($order['field'] == "provider") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceProviderCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceProviderCompany.name DESC']);
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

        $queryBuilder->groupBy('Expense.id');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            return [
                'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'page' => $page,
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
     * @throws \Phalcon\Security\Exception
     */
    public function generateInvoiceableItems()
    {

        $result = ['success' => true];

        $expense_category = $this->getExpenseCategory();
        $invoiceable_item = new InvoiceableItem();
        $invoiceable_item->setCompanyId(ModuleModel::$company->getId());
        $invoiceable_item->setNumber($invoiceable_item->generateNumber());

        if($expense_category) {
            if ($this->getChargeableType() != self::CHARGE_TO_NONE && $expense_category->getCostPriceEnable() == ExpenseCategory::COST_PRICE_ENABLE) {
                if ($expense_category->getPrice() > 0) {

                    $invoiceable_item->setUuid(Helpers::__uuid());
                    $invoiceable_item->setExpenseId($this->getId());
                    $invoiceable_item->setTaxRuleId($this->getTaxRuleId());
                    $invoiceable_item->setQuantity($this->getQuantity());
                    $invoiceable_item->setPrice($this->getQuantity() * $expense_category->getPrice());
                    $invoiceable_item->setTotal($invoiceable_item->getPrice() * (1 + (($this->getTaxInclude() == self::TAX_INCLUDE && $this->getTaxRule() instanceof TaxRule) ? $this->getTaxRule()->getRate() / 100 : 0)));
                    $invoiceable_item->setCurrency($this->getCurrency());
                    $invoiceable_item->setAccountId($this->getAccountId());
                    $invoiceable_item->setRelocationId($this->getRelocationId());
                    $invoiceable_item->setChargeTo($this->getChargeableType());
                    if ($this->getChargeableType() == Expense::CHARGE_TO_EMPLOYEE) {
                        $invoiceable_item->setEmployeeId($this->getRelocation() instanceof Relocation ? $this->getRelocation()->getEmployeeId() : null);
                        $invoiceable_item->setAccountId($this->getRelocation() instanceof Relocation ? $this->getRelocation()->getHrCompanyId() : null);
                    }
                    $invoiceable_item->setTaxRate(($this->getTaxInclude() == self::TAX_INCLUDE && $this->getTaxRule() instanceof TaxRule) ? $this->getTaxRule()->getRate() : 0);
                    $invoiceable_item->setUnitPrice($expense_category->getPrice());
                    $invoiceable_item->setTotalTax($invoiceable_item->getPrice() * $invoiceable_item->getTaxRate() / 100);
                    $invoiceable_item->setExpenseCategoryId($expense_category->getId());
                    $invoiceable_item->setExpenseCategoryName($expense_category->getName());
                    $invoiceable_item->setDescription($this->getNote());
                    $result = $invoiceable_item->__quickCreate();
                    if ($result['success'] == false) {
                        $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                        goto end_of_function;
                    }
                }
            } else if ($this->getChargeableType() != self::CHARGE_TO_NONE && $expense_category->getCostPriceEnable() != ExpenseCategory::COST_PRICE_ENABLE) {

                $invoiceable_item->setUuid(Helpers::__uuid());
                $invoiceable_item->setExpenseId($this->getId());
                $invoiceable_item->setTaxRuleId($this->getTaxRuleId());
                $invoiceable_item->setQuantity($this->getQuantity());
                $invoiceable_item->setPrice($this->getPrice());

                if ($this->getQuantity() > 0) {
                    $invoiceable_item->setQuantity($this->getQuantity());
                } else {
                    $invoiceable_item->setQuantity(1);
                }
                $invoiceable_item->setUnitPrice($this->getPrice());


                $invoiceable_item->setTotal($invoiceable_item->getPrice() * (1 + (($this->getTaxInclude() == self::TAX_INCLUDE && $this->getTaxRule() instanceof TaxRule) ? $this->getTaxRule()->getRate() / 100 : 0)));
                $invoiceable_item->setCurrency($this->getCurrency());
                $invoiceable_item->setAccountId($this->getAccountId());
                $invoiceable_item->setRelocationId($this->getRelocationId());
                $invoiceable_item->setChargeTo($this->getChargeableType());
                if ($this->getChargeableType() == self::CHARGE_TO_EMPLOYEE) {
                    $invoiceable_item->setEmployeeId($this->getRelocation() instanceof Relocation ? $this->getRelocation()->getEmployeeId() : null);
                    $invoiceable_item->setAccountId($this->getRelocation() instanceof Relocation ? $this->getRelocation()->getHrCompanyId() : null);
                }
                $invoiceable_item->setTaxRate(($this->getTaxInclude() == self::TAX_INCLUDE && $this->getTaxRule() instanceof TaxRule) ? $this->getTaxRule()->getRate() : 0);
                $invoiceable_item->setTotalTax($invoiceable_item->getPrice() * $invoiceable_item->getTaxRate() / 100);
                $invoiceable_item->setExpenseCategoryId($expense_category->getId());
                $invoiceable_item->setExpenseCategoryName($expense_category->getName());
                $invoiceable_item->setDescription($this->getNote());
                $result = $invoiceable_item->__quickCreate();
                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_EXPENSE_FAIL_TEXT';
                    goto end_of_function;
                }
            }
        }
        end_of_function:
        return $result;
    }

}
