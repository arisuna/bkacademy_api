<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayCachePrefixHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;


class Contact extends \Reloday\Application\Models\ContactExt
{
    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 10;
    const LIMIT_PER_PAGE_20 = 20;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\Company', 'uuid', [
            'alias' => 'HrCompany',
            'cache' => [
                'key' => '__COMPANY__' . $this->getObjectUuid(),
                'lifetime' => CacheHelper::__TIME_24H,
            ]
        ]);
        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\Company', 'uuid', [
            'alias' => 'BookerCompany',
            'cache' => [
                'key' => '__COMPANY__' . $this->getObjectUuid(),
                'lifetime' => CacheHelper::__TIME_24H,
            ]
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\DataContactMember', 'contact_id', 'object_uuid', 'Reloday\Gms\Models\Company', 'uuid', [
            'alias' => 'Companies',
        ]);
    }

    /**
     * @param $full_info
     * @param $options
     * @param $order
     * @return array
     * //TODO use CLOUDSEARCH for FULLTEXT SEARCH
     */
    public static function __findWithFilter($options)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Contact', 'Contact');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Company', 'Contact.company_id = Company.id', 'Company');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\DataContactMember', 'DataContactMember.contact_id = Contact.id', 'DataContactMember');
        $queryBuilder->where('Contact.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId(),
        ]);

        $queryBuilder->andWhere('Contact.is_deleted = :is_deleted_no:', [
            'is_deleted_no' => ModelHelper::NO
        ]);

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Contact.firstname LIKE :query: OR Contact.lastname LIKE :query: OR Contact.email LIKE :query:", ['query' => '%' . $options['query'] . '%']);
        }

        if (isset($options['uuid']) && is_string($options['uuid']) && $options['uuid'] != '') {
            $queryBuilder->andWhere('Contact.object_uuid = :object_uuid: OR DataContactMember.object_uuid = :object_uuid:', [
                'object_uuid' => $options['uuid']
            ]);
        }

        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] :  intval($start / $limit) + 1;

        $queryBuilder->orderBy('Contact.created_at DESC');
        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            return [
                'success' => true,
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
        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        return $this->getCompanyId() == ModuleModel::$company->getId();
    }

    /**
     * @param $email
     * @return \Phalcon\Mvc\Model\ResultInterface|\Reloday\Application\Models\Contact
     */
    public static function findFirstByEmailCompany($email)
    {
        return self::findFirst([
            'conditions' => 'email = :email: AND company_id = :company_id:',
            'bind' => [
                'email' => $email,
                'company_id' => ModuleModel::$company->getId(),
            ]
        ]);
    }

    /**
     * @param $uuid
     * @return array
     */
    public static function __findByUuid($uuid)
    {
        return self::__findWithFilter([
            'uuid' => $uuid,
            'limit' => 1000
        ]);
    }

    /**
     * @param $id
     * @return UserProfile
     */
    public static function __findFirstByEmailCache(String $email)
    {
        return self::findFirst([
            'conditions' => 'email = :email: AND company_id = :current_dsp_id:',
            'bind' => [
                'email' => $email,
                'current_dsp_id' => ModuleModel::$company->getId(),
            ],
            'limit' => 1,
            'cache' => [
                'key' => '__CONTACT_' . $email . "__" . ModuleModel::$company->getId(),
                'lifetime' => CacheHelper::__TIME_30_SECONDES
            ],
        ]);
    }

    /**
     * @return array
     */
    public static function __findHrContacts($options = [], $orders = [])
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Contact', 'Contact');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\DataContactMember', '(DataContactMember.contact_id = Contact.id) OR (DataContactMember.contact_id = Contact.id AND DataContactMember.object_id is NULL)', 'DataContactMember');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Company', 'HrCompany.id = DataContactMember.object_id', 'HrCompany');
        $queryBuilder->distinct(true);
        $queryBuilder->where('Contact.company_id = :contact_company_id:', [
            'contact_company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andWhere('Contact.is_deleted = :is_deleted_no:', [
            'is_deleted_no' => ModelHelper::NO
        ]);
        if(isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids']) > 0){
            $queryBuilder->andWhere('DataContactMember.object_id IN ({company_ids:array})', [
                'company_ids' => $options['company_ids']
            ]);
        }
//        $queryBuilder->andWhere('Contact.firstname is NOT NULL AND Contact.lastname IS NOT NULL');



        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Contact.firstname LIKE :query: OR Contact.lastname LIKE :query: OR Contact.email LIKE :query:", ['query' => '%' . $options['query'] . '%']);
        }

//        if (isset($options['uuid']) && is_string($options['uuid']) && $options['uuid'] != '') {
//            $queryBuilder->andwhere("DataContactMember.object_uuid = :uuid:", ['uuid' => $options['uuid']]);
//        }

        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE_20;

        if (!isset($options['page'])) {
            $page = intval($start / self::LIMIT_PER_PAGE) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('Contact.created_at DESC');
            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Contact.firstname ASC']);
                } else {
                    $queryBuilder->orderBy(['Contact.firstname DESC']);
                }
            }

            if ($order['field'] == "email") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Contact.email ASC']);
                } else {
                    $queryBuilder->orderBy(['Contact.email DESC']);
                }
            }

            if ($order['field'] == "company_name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['HrCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['HrCompany.name DESC']);
                }
            }

        }else{
            $queryBuilder->orderBy('Contact.created_at DESC');
        }

        try {
//            $paginator = new PaginatorQueryBuilder([
//                "builder" => $queryBuilder,
//                "limit" => $limit,
//                "page" => $page,
//            ]);
//            $pagination = $paginator->getPaginate();


            if (isset($options['isPagination']) && $options['isPagination'] == true){
                $paginator = new PaginatorQueryBuilder([
                    "builder" => $queryBuilder,
                    "limit" => $limit,
                    "page" => $page,
                ]);
                $pagination = $paginator->getPaginate();
                $data_array = [];
                if (count($pagination->items) > 0) {
                    foreach ($pagination->items as $item) {
                        $companies = $item->getCompanies();
                        $organisation = "";
//                        $found = false;
                        if (count($companies) > 0) {
                            foreach ($companies as $key => $company) {
//                                if ($company->getCompanyTypeId() == CompanyType::TYPE_HR) {
//                                    $found = true;
//                                }
                                if( $key === count($companies) -1){
                                    $organisation .= $company->getName();
                                }else{
                                    $organisation .= $company->getName() .  "; ";

                                }

                            }
                        }
                        $array = $item->toArray();
                        $array["organisation"] = $organisation;
                        $data_array[] = $array;
                    }
                }

                return [
                    'success' => true,
                    'page' => $page,
                    'rawSQL' => $queryBuilder->getQuery()->getSql(),
                    'data' => $data_array,
                    'before' => $pagination->before,
                    'next' => $pagination->next,
                    'last' => $pagination->last,
                    'current' => $pagination->current,
                    'total_items' => $pagination->total_items,
                    'total_pages' => $pagination->total_pages
                ];

            }else{
                $data_array = [];
                $items = $queryBuilder->getQuery()->execute();
                if (count($items) > 0) {
                    foreach ($items as $item) {
                        $companies = $item->getCompanies();
                        $organisation = "";
                        $found = false;
                        if (count($companies) > 0) {
                            foreach ($companies as $company) {
                                if ($company->getCompanyTypeId() == CompanyType::TYPE_HR) {
                                    $found = true;
                                    $organisation .= $company->getName() . "; ";
                                }
                            }
                        }
                        if ($found) {
                            $array = $item->toArray();
                            $array["organisation"] = $organisation;
                            $data_array[] = $array;
                        }
                    }
                }

                return [
                    'success' => true,
                    'page' => $page,
                    'total_raws' => count($items),
                    'total_items' => count($data_array),
                    'rawSQL' => $queryBuilder->getQuery()->getSql(),
                    'data' => $data_array,
                ];
            }
//            $pagination = $paginator->getPaginate();
        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @return mixed
     */
    public function getLinkedCompanies()
    {
        return $this->getCompanies([
            'conditions' => 'status = :status_active:',
            'bind' => [
                'status_active' => Company::STATUS_ACTIVATED,
            ],
            'cache' => [
                'key' => '_CONTACT_' . $this->getId() . '_COMPANIES_',
                'lifetime' => CacheHelper::__TIME_30_SECONDES
            ]
        ]);
    }

}
