<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Reloday\Application\Lib\CacheHelper;

class Team extends \Reloday\Application\Models\TeamExt
{
    /**
     *
     */
    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stu

        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'cache' => [
                'key' => 'COMPANY_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_24H,
            ]
        ]);

        $this->belongsTo('department_id', 'Reloday\Gms\Models\Department', 'id', [
            'alias' => 'Department',
            'cache' => [
                'key' => 'DEPARTMENT_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_24H,
            ]
        ]);

        $this->belongsTo('office_id', 'Reloday\Gms\Models\Office', 'id', [
            'alias' => 'Office',
            'cache' => [
                'key' => 'OFFICE_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_24H,
            ]
        ]);
    }

    /**
     * @param string $name_search
     * @return array
     */
    public static function loadList($name_search = '')
    {
        return [
            'success' => true,
            'message' => self::__findWithFilter()
        ];
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if ($this->getCompany()) {
            if ($this->getCompany()->belongsToGms() == true) return true;
        }
        return false;
    }

    /**
     * @param string $name_search
     * @return array
     */
    public static function __findWithFilterSimple($options = [])
    {

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Team', 'Team');
        $queryBuilder->columns([
            'id' => 'Team.id',
            'name' => 'Team.name',
            'company_id' => 'Team.company_id',
            'company_name' => 'Company.name',
            'office_name' => 'Office.name',
            'department_name' => 'Department.name'
        ]);
        $bind = array();
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = Team.company_id', 'Company');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Department', 'Department.id = Team.department_id', 'Department');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Office', 'Office.id = Team.office_id', 'Office');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Team.company_id', 'Contract');
        $queryBuilder->where("Contract.to_company_id = :gms_company_id:", [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);

        $bind['gms_company_id'] = ModuleModel::$company->getId();

        $results = $queryBuilder->getQuery()->execute($bind);
        return $results;

    }


    /**
     * @param string $name_search
     * @return array
     */
    public static function __findWithFilter($options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Team', 'Team');
        $queryBuilder->columns([
            'uuid' => 'Team.uuid',
            'id' => 'Team.id',
            'code' => 'Team.code',
            'Team.status',
            'name' => 'Team.name',
            'Team.company_id',
            'company_name' => 'Company.name',
            'office_name' => 'Office.name',
            'department_name' => 'Department.name'
        ]);
        $bindArray = array();
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = Team.company_id', 'Company');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Department', 'Department.id = Team.department_id', 'Department');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Office', 'Office.id = Team.office_id', 'Office');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Team.company_id', 'Contract');
        $queryBuilder->where("Contract.to_company_id = :gms_company_id:", [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);

        if (isset($options['status']) && is_array($options['status']) && count($options['status']) > 0) {
            $queryBuilder->andWhere("Team.status IN ({status:array})", ["status" => $options['status']]);
            $bindArray['status'] = $options['status'];
        }

        if (isset($options['status']) && is_numeric($options['status'])) {
            $queryBuilder->andWhere("Team.status = :status:", ["status" => $options['status']]);
            $bindArray['status'] = $options['status'];
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Team.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }


        if(isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids']) > 0){
            $queryBuilder->andwhere('Team.company_id IN ({company_ids:array})', [
                'company_ids' => $options['company_ids']
            ]);

            $bindArray['company_ids'] =  $options['company_ids'];
        }

        $bindArray['gms_company_id'] = ModuleModel::$company->getId();

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andWhere("Team.company_id = :company_id:", ["company_id" => $options['company_id']]);
        }

        $results = $queryBuilder->getQuery()->execute($bindArray);
        return $results;

    }

}