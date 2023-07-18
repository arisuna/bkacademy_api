<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class EntityDocument extends \Reloday\Application\Models\EntityDocumentExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 100;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('entity_uuid', 'Reloday\Gms\Models\Employee', 'uuid', [
            'alias' => 'Employee',
            'cache' => [
                'key' => 'EMPLOYEE_' . $this->getId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);

        $this->belongsTo('entity_uuid', 'Reloday\Gms\Models\Dependant', 'uuid', [
            'alias' => 'Dependant',
            'cache' => [
                'key' => 'DEPENDANT_' . $this->getEntityUuid(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);
    }

    /**
     * @return mixed
     */
    public function belongsToGms()
    {
        if ($this->getEntityName() == 'employee') {
            return $this->getEmployee() && $this->getEmployee()->belongsToGms();
        }
        if ($this->getEntityName() == 'dependant') {
            return $this->getDependant() && $this->getDependant()->belongsToGms();
        }
    }

    /**
     * @param $entityUuid
     */
    public static function __getDocumentsByEntityUuid($entityUuid)
    {
        try {
            $items = [];
            $documents = self::find([
                'conditions' => 'entity_uuid = :entity_uuid: AND is_active = :is_active_yes: AND is_deleted = :is_deleted_no:',
                'bind' => [
                    'entity_uuid' => $entityUuid,
                    'is_active_yes' => ModelHelper::YES,
                    'is_deleted_no' => ModelHelper::NO,
                ],
                'order' => 'created_at DESC'
            ]);
            foreach ($documents as $document) {
                $item = $document->toArray();
                $item['document_type_label'] = $document->getDocumentType()->getLabel();
                $items[] = $item;
            }
            return $items;
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function __countEmployeePassportExpiry()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\EntityDocument', 'EntityDocument');
        $queryBuilder->columns(["count" => "COUNT(*)"]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Employee.uuid = EntityDocument.entity_uuid', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EmployeeInContract', 'Employee.id = EmployeeInContract.employee_id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = EmployeeInContract.contract_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:');
        $queryBuilder->andWhere('Contract.status = :contract_active:');
        $queryBuilder->andWhere('Employee.status = :employee_activated:');
        $queryBuilder->andWhere('EntityDocument.is_active = :is_active_yes:');
        $queryBuilder->andWhere('EntityDocument.is_deleted = :is_deleted_no:');
        $bindArray = [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_active' => Contract::STATUS_ACTIVATED,
            'employee_activated' => intval(Employee::STATUS_ACTIVATED),
            'is_active_yes' => ModelHelper::YES,
            'is_deleted_no' => ModelHelper::NO,
        ];
        try {
            $result = $queryBuilder->getQuery()->execute($bindArray)->getFirst()->toArray();
            return $result['count'];

        } catch (\PDOException $e) {
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
        return 0;
    }

    public static function __countDependantPassportExpiry()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\EntityDocument', 'EntityDocument');
        $queryBuilder->columns(["count" => "COUNT(*)"]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Dependant', 'Dependant.uuid = EntityDocument.entity_uuid', 'Dependant');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Employee.id = Dependant.employee_id', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EmployeeInContract', 'Employee.id = EmployeeInContract.employee_id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = EmployeeInContract.contract_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:');
        $queryBuilder->andWhere('Contract.status = :contract_active:');
        $queryBuilder->andWhere('Employee.status = :employee_activated:');
        $queryBuilder->andWhere('EntityDocument.is_active = :is_active_yes:');
        $queryBuilder->andWhere('EntityDocument.is_deleted = :is_deleted_no:');
        $bindArray = [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_active' => Contract::STATUS_ACTIVATED,
            'employee_activated' => intval(Employee::STATUS_ACTIVATED),
            'is_active_yes' => ModelHelper::YES,
            'is_deleted_no' => ModelHelper::NO,
        ];
        try {
            $result = $queryBuilder->getQuery()->execute($bindArray)->getFirst()->toArray();
            return $result['count'];

        } catch (\PDOException $e) {
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
        return 0;
    }


    /**
     * @param $option
     * @return bool
     */
    public static function __getExpiredPassportOfEmployee($options = [])
    {
        $di = \Phalcon\DI::getDefault();
        $bindArray = [];
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Employee', 'Employee');
        $queryBuilder->columns([
            'Employee.id',
            'Employee.number',
            'Employee.active',
            'Employee.workemail',
            'Employee.uuid',
            'Employee.firstname',
            'Employee.lastname',
            'Employee.jobtitle',
            'Employee.phonework',
            'Employee.phonehome',
            'EntityDocument.expiry_date',
            'EntityDocument.delivery_date',
            'Employee.reference'
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EntityDocument', 'Employee.uuid = EntityDocument.entity_uuid', 'EntityDocument');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EmployeeInContract', 'Employee.id = EmployeeInContract.employee_id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = EmployeeInContract.contract_id', 'Contract');

        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andWhere('Contract.status = :contract_active:', [
            'contract_active' => Contract::STATUS_ACTIVATED
        ]);
        $queryBuilder->andWhere('EntityDocument.document_type_id = :document_type_passport:', [
            'document_type_passport' => DocumentType::TYPE_PASSPORT
        ]);
        $queryBuilder->andWhere('EntityDocument.is_active = :document_is_active:', [
            'document_is_active' => ModelHelper::YES,
        ]);
        $queryBuilder->andWhere('EntityDocument.is_deleted = :document_is_not_deleted:', [
            'document_is_not_deleted' => ModelHelper::NO,
        ]);
        $queryBuilder->andWhere('Employee.status = :employee_activated:', [
            'employee_activated' => intval(Employee::STATUS_ACTIVATED),
        ]);
        $queryBuilder->andWhere('EntityDocument.expiry_date  <= :passport_expiry_end_date: and EntityDocument.expiry_date  >= :passport_expiry_start_date:', [
            'passport_expiry_end_date' => time() + CacheHelper::__TIME_6_MONTHS,
            'passport_expiry_start_date' => time() - CacheHelper::__TIME_3_MONTHS
        ]);

        $bindArray['gms_company_id'] = ModuleModel::$company->getId();
        $bindArray['contract_active'] = Contract::STATUS_ACTIVATED;
        $bindArray['document_type_passport'] = DocumentType::TYPE_PASSPORT;
        $bindArray['document_is_active'] = ModelHelper::YES;
        $bindArray['document_is_not_deleted'] = ModelHelper::NO;
        $bindArray['employee_activated'] = intval(Employee::STATUS_ACTIVATED);
        $bindArray['passport_expiry_end_date'] = time() + CacheHelper::__TIME_6_MONTHS;
        $bindArray['passport_expiry_start_date'] = time() - CacheHelper::__TIME_3_MONTHS;

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        if ($page == 0) $page = intval($start / $limit) + 1;

        /** process order */

        $queryBuilder->groupBy('Employee.id');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $employeesArray = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $employee) {
                    $employee = $employee->toArray();
                    $employee['id'] = (int)$employee['id'];
                    $employee['active'] = (int)$employee['active'];
                    $employee['name'] = $employee['firstname'] . " " . $employee['lastname'];
                    $employee['expiry_date'] = intval($employee['expiry_date']);
                    $employeesArray[] = $employee;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => array_values($employeesArray),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'sql' => $queryBuilder->getQuery()->getSql()
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        } catch (Exception $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        }
    }


    /**
     * @param $option
     * @return bool
     */
    public static function __getExpiredVisaOfEmployee($options = [])
    {
        $di = \Phalcon\DI::getDefault();
        $bindArray = [];
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Employee', 'Employee');
        $queryBuilder->columns([
            'Employee.id',
            'Employee.number',
            'Employee.active',
            'Employee.workemail',
            'Employee.uuid',
            'Employee.firstname',
            'Employee.lastname',
            'Employee.jobtitle',
            'Employee.phonework',
            'Employee.phonehome',
            'EntityDocument.expiry_date',
            'EntityDocument.delivery_date',
            'Employee.reference'
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EntityDocument', 'Employee.uuid = EntityDocument.entity_uuid', 'EntityDocument');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EmployeeInContract', 'Employee.id = EmployeeInContract.employee_id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = EmployeeInContract.contract_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andWhere('Contract.status = :contract_active:', [
            'contract_active' => Contract::STATUS_ACTIVATED
        ]);
        $queryBuilder->andWhere('EntityDocument.document_type_id = :document_type_visa:', [
            'document_type_visa' => DocumentType::TYPE_VISA
        ]);
        $queryBuilder->andWhere('EntityDocument.is_active = :document_is_active:', [
            'document_is_active' => ModelHelper::YES,
        ]);
        $queryBuilder->andWhere('EntityDocument.is_deleted = :document_is_not_deleted:', [
            'document_is_not_deleted' => ModelHelper::NO,
        ]);
        $queryBuilder->andWhere('Employee.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('Employee.status = :employee_activated:', [
            'employee_activated' => intval(Employee::STATUS_ACTIVATED),
        ]);
        $queryBuilder->andWhere('EntityDocument.expiry_date  <= :passport_expiry_end_date: and EntityDocument.expiry_date  >= :passport_expiry_start_date:', [
            'passport_expiry_end_date' => time() + CacheHelper::__TIME_6_MONTHS,
            'passport_expiry_start_date' => time() - CacheHelper::__TIME_3_MONTHS
        ]);
        $bindArray['gms_company_id'] = ModuleModel::$company->getId();
        $bindArray['contract_active'] = Contract::STATUS_ACTIVATED;
        $bindArray['document_type_visa'] = DocumentType::TYPE_VISA;
        $bindArray['document_is_active'] = ModelHelper::YES;
        $bindArray['document_is_not_deleted'] = ModelHelper::NO;
        $bindArray['employee_activated'] = intval(Employee::STATUS_ACTIVATED);
        $bindArray['passport_expiry_end_date'] = time() + CacheHelper::__TIME_6_MONTHS;
        $bindArray['passport_expiry_start_date'] = time() - CacheHelper::__TIME_3_MONTHS;


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        if ($page == 0) $page = intval($start / $limit) + 1;

        /** process order */

        $queryBuilder->groupBy('Employee.id');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $employeesArray = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $employee) {
                    $employee = $employee->toArray();
                    $employee['id'] = (int)$employee['id'];
                    $employee['active'] = (int)$employee['active'];
                    $employee['name'] = $employee['firstname'] . " " . $employee['lastname'];
                    $employee['expiry_date'] = intval($employee['expiry_date']);
                    $employeesArray[] = $employee;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => array_values($employeesArray),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'sql' => $queryBuilder->getQuery()->getSql()
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        } catch (Exception $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        }
    }


    /**
     * @param $option
     * @return bool
     */
    public static function __getExpiredPassportOfDependant($options = [])
    {
        $di = \Phalcon\DI::getDefault();
        $bindArray = [];
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Dependant', 'Dependant');
        $queryBuilder->columns([
            'Dependant.id',
            'Dependant.private_email',
            'Dependant.uuid',
            'Dependant.firstname',
            'Dependant.lastname',
            'Dependant.work_phone',
            'Dependant.mobile_phone',
            'EntityDocument.expiry_date',
            'EntityDocument.delivery_date',
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Employee.id = Dependant.employee_id', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EntityDocument', 'Dependant.uuid = EntityDocument.entity_uuid', 'EntityDocument');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EmployeeInContract', 'Employee.id = EmployeeInContract.employee_id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = EmployeeInContract.contract_id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andWhere('Contract.status = :contract_active:', [
            'contract_active' => Contract::STATUS_ACTIVATED
        ]);
        $queryBuilder->andWhere('EntityDocument.document_type_id = :document_type_passport:', [
            'document_type_passport' => DocumentType::TYPE_PASSPORT
        ]);
        $queryBuilder->andWhere('EntityDocument.is_active = :document_is_active:', [
            'document_is_active' => ModelHelper::YES,
        ]);
        $queryBuilder->andWhere('EntityDocument.is_deleted = :document_is_not_deleted:', [
            'document_is_not_deleted' => ModelHelper::NO,
        ]);
        $queryBuilder->andWhere('Employee.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('Employee.status = :employee_activated:', [
            'employee_activated' => intval(Employee::STATUS_ACTIVATED),
        ]);
        $queryBuilder->andWhere('Dependant.status = :dependant_activated:', [
            'dependant_activated' => intval(Dependant::STATUS_ACTIVE),
        ]);
        $queryBuilder->andWhere('EntityDocument.expiry_date  <= :passport_expiry_end_date: and EntityDocument.expiry_date  >= :passport_expiry_start_date:', [
            'passport_expiry_end_date' => time() + CacheHelper::__TIME_6_MONTHS,
            'passport_expiry_start_date' => time() - CacheHelper::__TIME_3_MONTHS
        ]);
        $bindArray['gms_company_id'] = ModuleModel::$company->getId();
        $bindArray['contract_active'] = Contract::STATUS_ACTIVATED;
        $bindArray['document_type_passport'] = DocumentType::TYPE_PASSPORT;
        $bindArray['document_is_active'] = ModelHelper::YES;
        $bindArray['document_is_not_deleted'] = ModelHelper::NO;
        $bindArray['employee_activated'] = intval(Employee::STATUS_ACTIVATED);
        $bindArray['dependant_activated'] = intval(Dependant::STATUS_ACTIVE);
        $bindArray['passport_expiry_end_date'] = time() + CacheHelper::__TIME_6_MONTHS;
        $bindArray['passport_expiry_start_date'] = time() - CacheHelper::__TIME_3_MONTHS;


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        if ($page == 0) $page = intval($start / $limit) + 1;

        /** process order */

        $queryBuilder->groupBy('Dependant.id');

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $employeesArray = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $employee) {
                    $employee = $employee->toArray();
                    $employee['id'] = (int)$employee['id'];
                    $employee['active'] = (int)$employee['active'];
                    $employee['name'] = $employee['firstname'] . " " . $employee['lastname'];
                    $employee['expiry_date'] = intval($employee['expiry_date']);
                    $employeesArray[] = $employee;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => array_values($employeesArray),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'sql' => $queryBuilder->getQuery()->getSql()
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        } catch (Exception $e) {
            return ['success' => false, 'errorMessage' => [$e->getTraceAsString(), $e->getMessage()], 'options' => [$options, $bindArray, $queryBuilder->getQuery()->getSql()]];
        }
    }
}
