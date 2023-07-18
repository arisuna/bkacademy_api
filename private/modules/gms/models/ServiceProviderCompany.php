<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;
use Reloday\Application\Models\ServiceProviderTypeExt;

class ServiceProviderCompany extends \Reloday\Application\Models\ServiceProviderCompanyExt
{
    const LIMIT_PER_PAGE = 10;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub

        $this->belongsTo('country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'Country',
            'cache' => [
                'key' => 'COUNTRY_' . $this->getCountryId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\ServiceProviderBusinessDetail', 'id', [
            'alias' => 'ServiceProviderBusinessDetail'
        ]);
        $this->hasOne('id', 'Reloday\Gms\Models\ServiceProviderFinancialDetail', 'id', [
            'alias' => 'ServiceProviderFinancialDetail'
        ]);
        $this->hasManyToMany('id',
            'Reloday\Gms\Models\ServiceProviderCompanyHasServiceProviderType', 'service_provider_company_id',
            'service_provider_type_id', 'Reloday\Gms\Models\ServiceProviderType', 'id', [
                'alias' => 'ServiceProviderType',
            ]
        );
        $this->hasMany('id', 'Reloday\Gms\Models\ServiceProviderCompanyHasServiceProviderType', 'service_provider_company_id', [
            'alias' => 'ProviderTypeRelated'
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\SvpMembers', 'service_provider_company_id', [
            'alias' => 'SvpMembers',
            'params' => [
                'conditions' => 'Reloday\Gms\Models\SvpMembers.status  <> '.SvpMembers::STATUS_ARCHIVED
            ]
        ]);
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        $company = ModuleModel::$company;
        if ($company) {
            if ($this->getCompanyId() == $company->getId()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * @return \Reloday\Application\Models\ServiceProviderCompany[]
     */
    public static function getListOfMyCompany()
    {
        return self::find([
            'conditions' => 'company_id = :company_id: AND status = :status_active:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_active' => self::STATUS_ACTIVATED
            ],
            'order' => 'created_at DESC'
        ]);
    }

    /**
     * @param $options
     * @return \Reloday\Application\Models\ServiceProviderCompany[]
     */
    public static function __getSimpleServiceProvider($options = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany');
        $queryBuilder->distinct(true);

        $queryBuilder->where("ServiceProviderCompany.company_id = :company_id:", [
            'company_id' => ModuleModel::$company->getId()
        ]);

        if (isset($options['active']) && is_bool($options['active'])) {
            $queryBuilder->andwhere("ServiceProviderCompany.status = :status_active:", [
                'status_active' => $options['active'] == true ? self::STATUS_ACTIVATED : self::STATUS_ARCHIVED
            ]);
        }

        if (isset($options['country_id']) && is_numeric($options['country_id']) && $options['country_id'] > 0) {
            $queryBuilder->andwhere("ServiceProviderCompany.country_id = :country_id:", [
                'country_id' => $options['country_id']
            ]);
        }

        if (isset($options['country_ids']) && is_array($options['country_ids']) && count($options['country_ids']) > 0) {
            $queryBuilder->andwhere("ServiceProviderCompany.country_id IN ({country_ids:array})", [
                'country_ids' => $options['country_ids']
            ]);
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("ServiceProviderCompany.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        $queryBuilder->orderBy(['ServiceProviderCompany.created_at DESC']);
        $queryBuilder->groupBy(['ServiceProviderCompany.id']);

        $data = $queryBuilder->getQuery()->execute();
        $result = [];

        if (count($data) > 0){
            foreach ($data as $provider) {
                $item = $provider->toArray();
                $item['country_name'] = $provider->getCountry() ? $provider->getCountry()->getName() : '';
                $item['country_iso2'] = $provider->getCountry() ? $provider->getCountry()->getCio() : '';
                $item['type_ids'] = array_values($provider->getTypeIdList());
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @return \Reloday\Application\Models\ServiceProviderCompany[]
     */
    public static function getFullListOfMyCompany()
    {
        return self::find([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
            ],
            'order' => 'created_at DESC, status DESC'
        ]);
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __update($custom = [])
    {
        $model = $this;
        if (!($model->getId() > 0)) {
            return [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT',
            ];
        } else {
            return $model->__saveData($custom);
        }
    }

    /**
     * @param array $custom
     * @return array
     */
    public function __create($custom = [])
    {
        $model = $this;
        return $model->__saveData($custom);
    }


    /**
     * @param $custom
     * @return array
     */
    public function __saveData($custom = [])
    {
        $model = $this;
        $model->setData($custom);
        if ($model->getId() == null) {
            return $model->__quickCreate();
        } else {
            return $model->__quickUpdate();
        }
    }

    /**
     * @return array
     */
    public function getTypeIdList()
    {
        $types = $this->getServiceProviderType();
        $return = [];
        if ($types->count() > 0) {
            foreach ($types as $type) {
                $return[$type->getId()] = ['id' => $type->getId(), 'label' => $type->getLabel()];
            }
        }
        return $return;
    }

    /**
     * @param array $provider_type_ids
     * @return array
     */
    public function saveProviderTypes($provider_type_ids = [])
    {
        if (count($provider_type_ids) > 0) {
            $providersTypeToRemove = $this->getProviderTypeRelated([
                'conditions' => 'service_provider_type_id NOT IN ({ids:array})',
                'bind' => [
                    'ids' => $provider_type_ids
                ]
            ]);
            if ($providersTypeToRemove->count() > 0) {
                $result = ModelHelper::__quickRemoveCollection($providersTypeToRemove);
                if ($result['success'] == false) {
                    return [
                        'success' => false,
                        'message' => 'SAVE_TYPE_TO_SERVICE_PROVIDER_FAIL_TEXT'
                    ];
                }
            }
        }

        if (count($provider_type_ids) > 0) {
            foreach ($provider_type_ids as $type_id) {
                if (Helpers::__isValidId($type_id)) {
                    $type = ServiceProviderCompanyHasServiceProviderType::__findBySvp($type_id, $this->getId());
                    if (!$type) {
                        $type = new ServiceProviderCompanyHasServiceProviderType();
                    }
                    $type->setServiceProviderTypeId($type_id);
                    $type->setServiceProviderCompanyId($this->getId());
                    $res = $type->__quickSave();
                    if ($res['success'] == false) {
                        return [
                            'success' => false,
                            'message' => 'SAVE_TYPE_TO_SERVICE_PROVIDER_FAIL_TEXT'
                        ];
                    }
                }
            }
        }

        return ['success' => true];
    }


    /**
     * @return array
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        if (isset($options['mode']) && is_string($options['mode'])) {
            $mode = $options['mode'];
        } else {
            $mode = "large";
        }
        $bindArray = [];
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->distinct(true);
        $queryBuilder->addFrom('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceProviderCompanyHasServiceProviderType', 'ServiceProviderCompany.id = ServiceProviderCompanyHasServiceProviderType.service_provider_company_id', 'ServiceProviderCompanyHasServiceProviderType');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'ServiceProviderCompany.company_id = Company.id', 'Company');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'ServiceProviderCompany.country_id = Country.id', 'Country');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceCompanyHasServiceProvider', 'ServiceProviderCompany.id = ServiceCompanyHasServiceProvider.service_provider_company_id', 'ServiceCompanyHasServiceProvider');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceProviderFinancialDetails', 'ServiceProviderFinancialDetails.id = ServiceProviderCompany.id', 'ServiceProviderFinancialDetails');


        $queryBuilder->groupBy('ServiceProviderCompany.id');

        $queryBuilder->columns([
            'ServiceProviderCompany.id',
            'ServiceProviderCompany.reference',
            'ServiceProviderFinancialDetails.vat_number',
            'ServiceProviderCompany.uuid',
            'ServiceProviderCompany.name',
            'ServiceProviderCompany.email',
            'ServiceProviderCompany.address',
            'ServiceProviderCompany.town',
            'ServiceProviderCompany.phone',
            'ServiceProviderCompany.country_id',
            'ServiceProviderCompany.self_service',
            'Country.name as country_name',
            'Country.cio as country_iso2',
            'Company.name as company_name',
            'ServiceProviderCompany.status',
            'ServiceProviderCompany.updated_at',
            'ServiceProviderCompany.created_at',
            'country_name' => 'Country.name'
        ]);

        if (isset($options['active']) && is_bool($options['active']) && $options['active'] == true) {
            $queryBuilder->andwhere("ServiceProviderCompany.status = :status_active:", [
                'status_active' => ModelHelper::YES
            ]);
        }

        $queryBuilder->andwhere("ServiceProviderCompany.company_id = :company_id:", [
            'company_id' => ModuleModel::$company->getId()
        ]);

        if (isset($options['service_provider_type_id']) && is_numeric($options['service_provider_type_id']) && $options['service_provider_type_id'] > 0) {
            $queryBuilder->andwhere("ServiceProviderCompanyHasServiceProviderType.service_provider_type_id = :service_provider_type_id:", [
                'service_provider_type_id' => $options['service_provider_type_id']
            ]);
        }

        if (isset($options['provider_type_name']) && is_string($options['provider_type_name']) && $options['provider_type_name'] == 'landlord') {
            $queryBuilder->andwhere("ServiceProviderCompanyHasServiceProviderType.service_provider_type_id = :service_provider_type_id:", [
                'service_provider_type_id' => self::TYPE_LANDLORD
            ]);
        }

        if (isset($options['provider_type_name']) && is_string($options['provider_type_name']) && $options['provider_type_name'] == 'agent') {
            $queryBuilder->andwhere("ServiceProviderCompanyHasServiceProviderType.service_provider_type_id = :service_provider_type_id:", [
                'service_provider_type_id' => self::TYPE_REAL_ESTATE_AGENT
            ]);
        }

        if (isset($options['provider_type_name']) && is_string($options['provider_type_name']) && $options['provider_type_name'] != 'agent' && $options['provider_type_name'] != 'landlord') {
            $svpType = ServiceProviderType::findFirstByName(strtoupper($options['provider_type_name']));
            if ($svpType){
                $queryBuilder->andwhere("ServiceProviderCompanyHasServiceProviderType.service_provider_type_id = :service_provider_type_id:", [
                    'service_provider_type_id' => $svpType->getId()
                ]);
            }
        }

        if (isset($options['self_service']) && is_bool($options['self_service']) && $options['self_service'] == true) {
            $queryBuilder->andwhere("ServiceProviderCompany.self_service = :self_service_yes:", [
                'self_service_yes' => ModelHelper::YES
            ]);
        }

        if (isset($options['service_company_id']) && is_numeric($options['service_company_id']) && $options['service_company_id'] > 0) {
            $queryBuilder->andwhere("ServiceCompanyHasServiceProvider.service_company_id = :service_company_id:", [
                'service_company_id' => $options['service_company_id']
            ]);
        }

        if (isset($options['country_id']) && is_numeric($options['country_id']) && $options['country_id'] > 0) {
            $queryBuilder->andwhere("ServiceProviderCompany.country_id = :country_id:", [
                'country_id' => $options['country_id']
            ]);
        }

        if (isset($options['is_deleted']) && is_bool($options['is_deleted']) && $options['is_deleted'] == true) {
            $queryBuilder->andwhere("ServiceProviderCompany.status = :status_archived:", [
                'status_archived' => self::STATUS_ARCHIVED
            ]);
        } else {
            $queryBuilder->andwhere("ServiceProviderCompany.status <> :status_archived:", [
                'status_archived' => self::STATUS_ARCHIVED
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("ServiceProviderCompany.name LIKE :query: OR ServiceProviderCompany.reference LIKE :query:
            OR Country.name LIKE :query: OR ServiceProviderCompany.town LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['country_ids']) && is_array($options['country_ids']) && count($options['country_ids']) > 0) {
            $queryBuilder->andwhere("ServiceProviderCompany.country_id IN ({country_ids:array})", [
                'country_ids' => $options['country_ids']
            ]);
        }

        if (isset($options['service_provider_type_ids']) && is_array($options['service_provider_type_ids']) && count($options['service_provider_type_ids']) > 0) {
            $queryBuilder->andwhere("ServiceProviderCompanyHasServiceProviderType.service_provider_type_id IN ({service_provider_type_ids:array})", [
                'service_provider_type_ids' => $options['service_provider_type_ids']
            ]);
        }


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;

        if (!isset($options['page'])) {
            $page = intval($start / self::LIMIT_PER_PAGE) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        }

        if (isset($options['isFullOption']) && $options['isFullOption'] == true){
            $queryBuilder->orderBy('ServiceProviderCompany.created_at DESC');
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('ServiceProviderCompany.created_at DESC');
            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceProviderCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceProviderCompany.name DESC']);
                }
            }

            if ($order['field'] == "country_name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Country.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Country.name DESC']);
                }
            }

            if ($order['field'] == "self_service") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceProviderCompany.self_service ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceProviderCompany.self_service DESC']);
                }
            }

        }else{
            $queryBuilder->orderBy('ServiceProviderCompany.created_at DESC');
        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $itemsArray = [];

            foreach ($pagination->items as $item) {
                $itemArray = $item->toArray();
                $itemArray['id'] = intval($itemArray['id']);
                $itemArray['status'] = intval($itemArray['status']);
                $itemArray['country_id'] = intval($itemArray['country_id']);
                $itemArray['self_service'] = intval($itemArray['self_service']);

                if (isset($options['isFullOption']) && $options['isFullOption'] == true){
                    $model = self::findFirstById($itemArray['id']);
                    $itemArray['type_ids'] = array_values($model->getTypeIdList());
                }
                $itemsArray[] = $itemArray;
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => $itemsArray,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
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
