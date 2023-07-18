<?php

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\ModelHelper;

class AccountProductPricing extends \Reloday\Application\Models\AccountProductPricingExt
{
    const LIMIT_PER_PAGE = 20;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('account_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Account'
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company'
        ]);
        $this->belongsTo('product_pricing_id', 'Reloday\Gms\Models\ProductPricing', 'id', [
            'alias' => 'ProductPricing'
        ]);
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
     * @return \Reloday\Gms\Models\Workflow[]
     */
    public static function getList($options)
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Company', 'Account');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\AccountProductPricing', 'AccountProductPricing.account_id = Account.id', 'AccountProductPricing');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\CompanyType', 'CompanyType.id = Account.company_type_id', 'CompanyType');
        $queryBuilder->distinct(true);

        $queryBuilder->innerJoin('\Reloday\Gms\Models\ProductPricing', 'ProductPricing.id = AccountProductPricing.product_pricing_id', 'ProductPricing');
        $queryBuilder->andWhere('ProductPricing.company_id = :company_id:', [
            "company_id" => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andWhere('ProductPricing.is_deleted = 0');
        $queryBuilder->andWhere('Account.status = :account_status:', [
            'account_status' => Company::STATUS_ACTIVATED
        ]);
        $queryBuilder->andWhere('AccountProductPricing.is_deleted = 0');

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('Account.name Like :query:', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }
        $queryBuilder->groupBy('Account.id');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $account_array_list = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $account) {
                    $queryBuilder->columns([
                        'Account.id',
                        'Account.uuid',
                        'Account.name',
                        'type' => 'CompanyType.name',
                        'number_of_account_prices' => 'count(AccountProductPricing.id)',
                    ]);
                    $account_array = $account->toArray();
                    $account_array['type'] = CompanyType::findFirstById($account->getCompanyTypeId())->getName();
                    $account_array['number_of_account_prices'] = AccountProductPricing::count([
                        "conditions" => "is_deleted = 0 and account_id = :account_id: and company_id = :company_id:",
                        "bind" => [
                            "account_id" => $account->getId(),
                            "company_id" => ModuleModel::$company->getId()
                        ]
                    ]);
                    $account_array['number_of_active_account_prices'] = AccountProductPricing::count([
                        "conditions" => "is_deleted = 0 and account_id = :account_id: and company_id = :company_id: and is_active = 1",
                        "bind" => [
                            "account_id" => $account->getId(),
                            "company_id" => ModuleModel::$company->getId()
                        ]
                    ]);
                    $account_array['number_of_archive_account_prices'] = AccountProductPricing::count([
                        "conditions" => "is_deleted = 0 and account_id = :account_id: and company_id = :company_id: and is_active = 0",
                        "bind" => [
                            "account_id" => $account->getId(),
                            "company_id" => ModuleModel::$company->getId()
                        ]
                    ]);
                    $account_array_list[] = $account_array;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => $account_array_list,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
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
     */
    public static function __findWithFilter($options = [])
    {

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\AccountProductPricing', 'AccountProductPricing');

        $bind = [];
        $queryBuilder->where('AccountProductPricing.is_deleted = :is_deleted:');
        $bind['is_deleted'] = ModelHelper::NO;


        if (isset($options['query']) && $options['query'] != '') {
            $queryBuilder->andWhere('AccountProductPricing.name LIKE :query:');
            $bind['query'] = "%" . $options['query'] . "%";
        }

        if (isset($options['account_id']) && is_numeric($options['account_id']) && $options['account_id'] > 0) {
            $queryBuilder->andWhere('AccountProductPricing.account_id = :account_id:');
            $bind['account_id'] = $options['account_id'];
        }

        if (isset($options['company_id']) && $options['company_id'] && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andWhere('AccountProductPricing.company_id = :company_id:');
            $bind['company_id'] = $options['company_id'];
        } else {
            $queryBuilder->andWhere('AccountProductPricing.company_id = :company_id:');
            $bind['company_id'] = ModuleModel::$company->getId();
        }

        try {
            $items = $queryBuilder->getQuery()->execute($bind);
            $itemsArray = [];
            foreach ($items as $item) {
                $itemToArray = $item->toArray();
                $itemToArray['product_name'] = $item->getProductPricing()->getName();
                $itemToArray['account_name'] = $item->getAccount()->getName();
                $itemToArray['tax_rate'] = $item->getTaxRule() ? $item->getTaxRule()->getRate() : 0;
                $itemsArray[] = $itemToArray;
            }
            return $itemsArray;
        } catch (\Exception $e) {

            return [];
        }

    }
}
