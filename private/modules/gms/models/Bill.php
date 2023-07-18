<?php

namespace Reloday\Gms\Models;

use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\FilterConfigExt;

class Bill extends \Reloday\Application\Models\BillExt
{

    const LIMIT_PER_PAGE = 20;

    const STATUS_UNPAID = 1;
    const STATUS_PARTIAL_PAID = 2;
    const STATUS_PAID = 3;
    const STATUS_DRAFT = 0;
    const STATUS_PENDING = 4;
    const STATUS_REJECTED = 5;
    const STATUS_ARCHIVED = -1;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'cache' => [
                'key' => 'COMPANY_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);

        $this->belongsTo('service_provider_company_id', 'Reloday\Gms\Models\ServiceProviderCompany', 'id', [
            'alias' => 'ServiceProviderCompany',
            'params' => [
                "conditions" => "status != :status:",
                "bind" => [
                    "status" => Expense::STATUS_ARCHIVED
                ]
            ]
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\Expense', 'bill_id', [
            'alias' => 'Expenses',
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\Payment', 'bill_id', [
            'alias' => 'Payments',
            'params' => [
                "conditions" => "is_deleted = 0"
            ]
        ]);
        $this->belongsTo('provider_country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'ProviderCountry',
            'reusable' => true,
            'cache' => [
                'key' => 'COUNTRY_' . $this->getProviderCountryId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
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
        $queryBuilder->addFrom('\Reloday\Gms\Models\Bill', 'Bill');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Bill.id',
            'Bill.uuid',
            'Bill.reference',
            'Bill.number',
            'Bill.date',
            'Bill.due_date',
            'Bill.service_provider_company_id',
            'provider_name' => 'ServiceProviderCompany.name',
            'Bill.total',
            'Bill.total_paid',
            'total_remain' => '(Bill.total - Bill.total_paid)',
            'Bill.status',
            'Bill.currency'
        ]);
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany.id = Bill.service_provider_company_id', 'ServiceProviderCompany');

        $queryBuilder->where('Bill.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andWhere('Bill.status != :status:', [
            'status' => Bill::STATUS_ARCHIVED
        ]);

        if (count($options["companies"]) > 0) {
            $queryBuilder->andwhere('Bill.service_provider_company_id IN ({companies:array} )', [
                'companies' => $options["companies"]
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('Bill.reference Like :query: OR Bill.number Like :query:', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }

        if (count($options["statuses"]) > 0) {
            $queryBuilder->andwhere('Bill.status IN ({statuses:array} )', [
                'statuses' => $options["statuses"]
            ]);
        }

        if (isset($options['provider_id']) && is_numeric($options['provider_id']) && $options['provider_id'] > 0) {
            $queryBuilder->andwhere("Bill.service_provider_company_id = :provider_id:", [
                'provider_id' => $options['provider_id'],
            ]);
        }

        if ($options["start_date"] != null && $options["start_date"] != "") {
            $queryBuilder->andwhere('Bill.date >= :start_date:', [
                'start_date' => $options["start_date"]
            ]);
        }

        if ($options["end_date"] != null && $options["end_date"] != "") {
            $queryBuilder->andwhere('Bill.date <= :end_date:', [
                'end_date' => $options["end_date"]
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'PROVIDER_ARRAY_TEXT' => 'Bill.service_provider_company_id',
                'STATUS_ARRAY_TEXT' => 'Bill.status',
                'CURRENCY_TEXT' => 'Bill.currency',
                'DUE_DATE_TEXT' => 'Bill.due_date',
            ];

            $dataType = [
                'DUE_DATE_TEXT' => 'int',
                'DATE_TEXT' => 'int',
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::BILL_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy(['Bill.date DESC', 'Bill.created_at DESC']);
            if ($order['field'] == "reference") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Bill.reference ASC']);
                } else {
                    $queryBuilder->orderBy(['Bill.reference DESC']);
                }
            }
            if ($order['field'] == "number") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Bill.number ASC']);
                } else {
                    $queryBuilder->orderBy(['Bill.number DESC']);
                }
            }

            if ($order['field'] == "date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Bill.date ASC', 'Bill.created_at DESC']);
                } else {
                    $queryBuilder->orderBy(['Bill.date DESC', 'Bill.created_at DESC']);
                }
            }

            if ($order['field'] == "due_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Bill.due_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Bill.due_date DESC', 'Bill.created_at DESC']);
                }
            }

            if ($order['field'] == "provider") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceProviderCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceProviderCompany.name DESC']);
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
                    $queryBuilder->orderBy(['Bill.status ASC']);
                } else {
                    $queryBuilder->orderBy(['Bill.status DESC']);
                }
            }

        }

        $queryBuilder->groupBy('Bill.id');

        if(!isset($options['nopaging']) || !$options['nopaging']){

                $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
                if (!isset($options['page'])) {
                    $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
                    $page = intval($start / $limit) + 1;
                } else {
                    $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
                }

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
        } else {
            try {

                return [
                    'success' => true,
                    'sql' => $queryBuilder->getQuery()->getSql(),
                    'data' => ($queryBuilder->getQuery()->execute())
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

    /**
     * fix bug calcultotal
     */
    public function calculTotal()
    {

    }

    /**
     * @param $reference
     * @return \Phalcon\Mvc\Model\ResultInterface|\Reloday\Application\Models\Bill
     */
    public static function __findBillByReference($reference)
    {
        return self::findFirst([
            'conditions' => 'reference = :reference: AND company_id = :company_id:',
            'bind' => [
                'reference' => $reference,
                'company_id' => ModuleModel::$company->getId()
            ]
        ]);
    }
}
