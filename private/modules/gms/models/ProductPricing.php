<?php

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class ProductPricing extends \Reloday\Application\Models\ProductPricingExt
{
    const LIMIT_PER_PAGE = 20;
    const SERVICE_LINKED = 1;

    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\AccountProductPricing', 'product_pricing_id', [
            'alias' => 'AccountProducts'
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Company']);
        $this->belongsTo('service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', ['alias' => 'ServiceCompany']);
        $this->belongsTo('tax_rule_id', 'Reloday\Gms\Models\TaxRule', 'id', [
            'alias' => 'TaxRule',
            'params' => [
                'conditions' => 'status != :status_archived:',
                'bind' => [
                    'status_archived' => TaxRule::STATUS_DELETED
                ]
            ]
        ]);
    }

    /**
     * @return mixed
     */
    public function getActiveAccountProducts()
    {
        return $this->getAccountProducts([
            'conditions' => 'is_deleted = 0 AND is_active = 1'
        ]);
    }

    /**
     * @return mixed
     */
    public function getAllAccountProducts()
    {
        return $this->getAccountProducts();
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
    public static function getListOfMyCompany($options)
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ProductPricing', 'ProductPricing');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'ProductPricing.id',
            'ProductPricing.uuid',
            'ProductPricing.external_hris_id',
            'ProductPricing.name',
            'ProductPricing.currency',
            'ProductPricing.cost',
            'ProductPricing.description',
            'ProductPricing.price',
            'ProductPricing.tax_rule_id',
            'ProductPricing.is_deleted',
            'ProductPricing.is_active',
            'tax_rule_name' => 'TaxRule.name',
            'tax_rate' => 'TaxRule.rate',
            "service_name" => 'ServiceCompany.name'
        ]);
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = ProductPricing.service_company_id', 'ServiceCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\TaxRule', 'TaxRule.id = ProductPricing.tax_rule_id and TaxRule.status != -1', 'TaxRule');
        $queryBuilder->where('ProductPricing.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('ProductPricing.is_deleted = 0');
        $queryBuilder->andWhere('ProductPricing.is_active = 1');
        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('ProductPricing.name Like :query:', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (count($options["service_ids"]) > 0) {
            $queryBuilder->andwhere('ServiceCompany.id IN ({service_ids:array} )', [
                'service_ids' => $options["service_ids"]
            ]);
            $queryBuilder->andwhere('ProductPricing.service_linked  = :service_linked:', [
                'service_linked' => self::SERVICE_LINKED
            ]);
        }

        if (count($options["account_ids"]) > 0) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\AccountProductPricing', 'AccountProductPricing.product_pricing_id = ProductPricing.id', 'AccountProductPricing');
            $queryBuilder->andwhere('AccountProductPricing.account_id IN ({account_ids:array} )', [
                'account_ids' => $options["account_ids"]
            ]);
            $queryBuilder->andwhere('AccountProductPricing.is_deleted = 0');
            $queryBuilder->andwhere('AccountProductPricing.is_active = 1');
        }

        if ($options["account_id"] > 0) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\AccountProductPricing', 'AccountProductPricing.product_pricing_id = ProductPricing.id', 'AccountProductPricing');
            $queryBuilder->andwhere('AccountProductPricing.account_id = :account_id:', [
                'account_id' => $options["account_id"]
            ]);
            $queryBuilder->andwhere('AccountProductPricing.is_active = 1');
            $queryBuilder->andwhere('AccountProductPricing.is_deleted = 0');
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        $queryBuilder->groupBy('ProductPricing.id');

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $items = $pagination->items;

            foreach ($items as $key => $item) {
                $items[$key]->tax_rule_id = intval($item->tax_rule_id);
            }

            return [
                'success' => true,
                'sql' => $queryBuilder->getQuery()->getSql(),
                'page' => $page,
                'data' => $items,
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
}
