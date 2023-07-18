<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;


use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Http\Request;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class ServicePack extends \Reloday\Application\Models\ServicePackExt
{
    public function initialize()
    {
        parent::initialize();

        $this->hasManyToMany('id', 'Reloday\Gms\Models\ServicePackHasService', 'service_pack_id', 'service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', [
            'alias' => 'service_companies'
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\ServicePackHasService', 'service_pack_id', [
            'alias' => 'ServicePackHasServiceRelations'
        ]);


        $this->hasMany('id', 'Reloday\Gms\Models\ServicePackHasService', 'service_pack_id', [
            'alias' => 'ServicePackHasService'
        ]);


        $this->hasManyToMany('id', 'Reloday\Gms\Models\ServicePackHasService', 'service_pack_id', 'service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', [
            'alias' => 'ServiceCompany'
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\ServicePackHasService', 'service_pack_id', 'service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', [
            'alias' => 'ServiceCompanies',
            'params' => [
                'order' => 'position ASC',
            ]
        ]);

        $this->belongsTo('owner_company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'OwnerCompany'
        ]);

        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'TargetCompany'
        ]);
    }

    /**
     * @param Request $req
     * @return array
     */
    public static function __findWithFilter($options = [])
    {
        $data = [];
        // 1. Load list service pack
        if (isset($options['is_active']) && $options['is_active'] == true) {
            $items = self::find([
                'conditions' => 'owner_company_id = :owner_company_id:  AND status <> :status_deleted:',
                'bind' => [
                    'owner_company_id' => ModuleModel::$company->getId(),
                    'status_deleted' => self::STATUS_DELETED
                ],
                'order' => 'status DESC, created_at DESC'
            ]);
        } elseif (isset($options['is_archived']) && $options['is_archived'] == true) {
            $items = self::find([
                'conditions' => 'owner_company_id = :owner_company_id:  AND status = :status_deleted:',
                'bind' => [
                    'owner_company_id' => ModuleModel::$company->getId(),
                    'status_deleted' => self::STATUS_DELETED
                ],
                'order' => 'status DESC, created_at DESC'
            ]);
        } else {
            $items = self::find([
                'conditions' => 'owner_company_id = :owner_company_id:',
                'bind' => [
                    'owner_company_id' => ModuleModel::$company->getId(),
                ],
                'order' => 'status DESC, created_at DESC'
            ]);
        }

        if (count($items)) {
            foreach ($items as $servicePack) {
                $data[$servicePack->getId()] = $servicePack->toArray();
                $data[$servicePack->getId()]['service'] = $servicePack->getServiceCompanyByActive(true, false);
                $data[$servicePack->getId()]['count_services'] = count($data[$servicePack->getId()]['service']);
            }
        }
        return array_values($data);
    }

    public static function __findWithFilters(array $options = [], $orders = []): array
    {
        $data = [];
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ServicePack', 'ServicePack');
        $queryBuilder->distinct(true);
        $queryBuilder->where('ServicePack.owner_company_id = :owner_company_id:', [
            'owner_company_id' => ModuleModel::$company->getId()
        ]);

        if (isset($options['status'])) {
            $queryBuilder->andWhere('ServicePack.status = :status:', [
                'status' => $options['status']
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("ServicePack.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServicePack.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['ServicePack.updated_at DESC']);
                }
            } else {
                $queryBuilder->orderBy(['ServicePack.updated_at DESC']);
            }

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServicePack.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ServicePack.name DESC']);
                }
            }

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServicePack.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ServicePack.name DESC']);
                }
            }
        } else {
            $queryBuilder->orderBy(["ServicePack.created_at DESC"]);
        }

        $queryBuilder->groupBy('ServicePack.id');

        try {
            if (isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0) {
                $limit = $options['limit'];
                $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
                $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

                if ($page == 0) {
                    $page = intval($start / $limit) + 1;
                }

                $paginator = new PaginatorQueryBuilder([
                    "builder" => $queryBuilder,
                    "limit" => $limit,
                    "page" => $page
                ]);

                $pagination = $paginator->getPaginate();

                return [
                    'success' => true,
                    'options' => $options,
                    'limit_per_page' => $limit,
                    'page' => $page,
                    'data' => $pagination->items,
                    'before' => $pagination->before,
                    'next' => $pagination->next,
                    'last' => $pagination->last,
                    'current' => $pagination->current,
                    'total_items' => $pagination->total_items,
                    'total_pages' => $pagination->total_pages,
                    'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                ];
            }

            $items = $queryBuilder->getQuery()->execute();

            return [
                'success' => true,
                'data' => $items,
                'total_pages' => 0
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param array $service_ids
     * @return array
     */
    public function addServiceCompanies($service_ids = array())
    {
        $serviceCompanies = [];
        if ($this->getId() > 0) {
            foreach ($this->getServiceCompanies() as $k => $serviceSelected) {
                if (!in_array($serviceSelected->getId(), $service_ids)) {
                    $serviceHasPack = ServicePackHasService::findFirst([
                        'conditions' => 'service_company_id = :service_company_id: AND service_pack_id = :service_pack_id:',
                        'bind' => [
                            'service_company_id' => $serviceSelected->getId(),
                            'service_pack_id' => $this->getId()
                        ]
                    ]);
                    if ($serviceHasPack) {
                        $resultDelete = $serviceHasPack->__quickRemove();
                        if ($resultDelete['success'] == false) return $resultDelete;
                    }
                }
            }
        }

        if (count($service_ids)) {
            foreach ($service_ids as $k => $service_company_id) {
                $service = ServiceCompany::findFirstById($service_company_id);
                $serviceHasPack = false;
                if ($this->getId() > 0) {
                    $serviceHasPack = ServicePackHasService::findFirst([
                        'conditions' => 'service_company_id = :service_company_id: AND service_pack_id = :service_pack_id:',
                        'bind' => [
                            'service_company_id' => $service->getId(),
                            'service_pack_id' => $this->getId()
                        ]
                    ]);
                }
                if ($service && $service->belongsToGms() && !$serviceHasPack) {
                    $serviceCompanies[$k] = $service;
                }
            }
        }
        $this->service_companies = $serviceCompanies;
        $result = $this->__quickSave();
        if ($result['success'] == false) {
            $result['service_companies'] = $this->service_companies;
        }
        return $result;
    }


    /**
     * @param array $service_ids
     * @return array
     */
    public function __old__addServiceCompanies($service_ids = array())
    {
        $serviceRelations = [];
        if (count($service_ids)) {
            foreach ($service_ids as $k => $service_company_id) {
                $service = ServiceCompany::findFirstById($service_company_id);
                if ($service && $service->belongsToGms()) {
                    $servicePackToService = new ServicePackHasService();
                    $servicePackToService->setServiceCompanyId($service->getId());
                    $servicePackToService->setServiceName($service->getName());
                    $serviceRelations[$k] = $servicePackToService;
                }
            }
        }
        $this->ServicePackHasService = $serviceRelations;
        return $this->__quickUpdate();
    }


    /**
     * @param bool $active
     * @param bool $simple
     * @return mixed
     */
    public function getServiceCompanyByActive($active = true, $simple = false)
    {
        $params = [];
        if ($active == true) {
            $params = [
                'conditions' => 'status = :status:',
                'bind' => [
                    'status' => ServiceCompany::STATUS_ACTIVATED
                ]
            ];
        }
        if ($simple == true) {
            $params['column'] = 'name, id';
        }
        return $this->getServiceCompanies($params);
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        if ($this->getOwnerCompanyId() == ModuleModel::$company->getId()) return true;
        else return false;
    }

}