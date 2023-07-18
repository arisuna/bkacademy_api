<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ServicePack;
use Phalcon\Mvc\Model\Transaction\Failed as TransationFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class Policy extends \Reloday\Application\Models\PolicyExt
{

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', '\Reloday\Gms\Models\Assignment', 'policy_id', array('alias' => 'Assignment'));
        $this->belongsTo('assignment_type_id', '\Reloday\Gms\Models\AssignmentType', 'id', array('alias' => 'AssignmentType'));
        $this->belongsTo('company_id', '\Reloday\Gms\Models\Company', 'id', array('alias' => 'Company'));
        $this->hasMany('id', '\Reloday\Gms\Models\PolicyItem', 'policy_id', array('alias' => 'PolicyItem'));
    }

    /**
     * [loadlist description]
     * @return [type] [description]
     */
    public static function loadList($params = [])
    {

        $result = [];

        // Load user profile
        $company = ModuleModel::$company;

        if (!$company->getCompanyTypeId() == Company::TYPE_GMS) {
            return [
                'success' => false,
                'message' => 'COMPANY_TYPE_DIFFERENT'
            ];
        } else {

            $di = \Phalcon\DI::getDefault();

            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Policy', 'Policy');
            $queryBuilder->distinct(true);
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Policy.company_id', 'Contract');
            $queryBuilder->where('Contract.to_company_id = ' . $company->getId());
            $queryBuilder->andwhere('Policy.status <> ' . self::STATUS_ARCHIVED);

            if (isset($params['ids']) && is_array($params['ids']) && count($params['ids']) > 0) {
                $queryBuilder->andwhere('Policy.id IN ({ids:array})', [
                    'ids' => $params['ids']
                ]);
            }

            $queryBuilder->groupBy('Policy.id');
            $policies = $queryBuilder->getQuery()->execute();


            return [
                'success' => true,
                'data' => $policies,
            ];
        }
    }

    /**
     * [__save description]
     * @return [type] [description]
     */
    public function __save($custom = [])
    {
        $req = new Request();

        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            //
            $policy_id = isset($custom['policy_id']) && $custom['policy_id'] > 0 ? $custom['policy_id'] : $req->getPut('id');

            if ($policy_id > 0) {
                $model = $this->findFirstById($policy_id);
                if (!$model instanceof $this) {
                    return [
                        'success' => false,
                        'message' => 'POLICY_NOT_FOUND_TEXT',
                    ];
                }
            }
            $data = $req->getPut();
        }
        /** @var [varchar] [set uunique id] */
        $uuid = isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid());
        if ($uuid == '' || $uuid == 'DEFAULT_UUID') {
            $random = new Random;
            $uuid = $random->uuid();
            $model->setUuid($uuid);
        }
        /** @var [integer] [set status] */
        $status = isset($custom['status']) ? $custom['status'] : (isset($data['status']) ? $data['status'] : $model->getStatus());
        if ($status == null || $status == '') {
            $status = self::STATUS_ACTIVE;
        }
        $model->setStatus($status);


        $model->setName(array_key_exists('name', $data) ? $data['name'] : (isset($custom['name']) ? $custom['name'] : $model->getName()));
        $model->setCompanyId(isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId()));
        $model->setAssignmentTypeId(isset($custom['assignment_type_id']) ? $custom['assignment_type_id'] : (isset($data['assignment_type_id']) ? $data['assignment_type_id'] : $model->getAssignmentTypeId()));
        $model->setEmployeeGrade(isset($custom['employee_grade']) ? $custom['employee_grade'] : (isset($data['employee_grade']) ? $data['employee_grade'] : $model->getEmployeeGrade()));

        if ($model->getNumber() == '') {
            $number = $model->generateNumber();
            $model->setNumber($number);
        }

        try {
            if ($model->getId() == null) {
                //create model
            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_POLICY_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_POLICY_SUCCESS_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        return $this->getCompany() && $this->getCompany()->belongsToGms();
    }

    /**
     * @return string
     */
    public function generateNumber()
    {
        $count = self::count([
            'conditions' => "company_id = :company_id: AND number IS NOT NULL AND number <> ''",
            'bind' => [
                'company_id' => $this->getCompanyId(),
            ]
        ]);
        $shortKeyCompany = '';
        $company = $this->getCompany();
        if (!$company && $this->getCompanyId() > 0) {
            $company = Company::findFirstById($this->getCompanyId());
        }

        if ($company) {
            $shortKeyCompany = Helpers::getShortkeyFromWord($company->getName());
            if ($shortKeyCompany != '') $shortKeyCompany = "-" . $shortKeyCompany;
        }
        return self::NAME_PREFIX . $shortKeyCompany . "-" . strval($count + 1);
    }

    /**
     * generate number before save
     */
    public function beforeSave()
    {
        if ($this->getNumber() == '') {
            $number = $this->generateNumber();
            $this->setNumber($number);
        }
        parent::beforeSave();
    }

    /**
     * [loadlist description]
     * @return [type] [description]
     */
    public static function __findWithFilters($params = [])
    {

        $result = [];

        // Load user profile
        $company = ModuleModel::$company;

        if (!$company->getCompanyTypeId() == Company::TYPE_GMS) {
            return [
                'success' => false,
                'message' => 'COMPANY_TYPE_DIFFERENT'
            ];
        } else {

            $di = \Phalcon\DI::getDefault();

            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Policy', 'Policy');
            $queryBuilder->distinct(true);
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Policy.company_id', 'Contract');
            $queryBuilder->where('Contract.to_company_id = ' . ModuleModel::$company->getId());
            $queryBuilder->andwhere('Policy.status <> ' . self::STATUS_ARCHIVED);

            if (isset($params['ids']) && is_array($params['ids']) && count($params['ids']) > 0) {
                $queryBuilder->andwhere('Policy.id IN ({ids:array})', [
                    'ids' => $params['ids']
                ]);
            }

            if (isset($params['company_id']) && is_numeric($params['company_id']) && count($params['ids']) > 0) {
                $queryBuilder->andwhere('Policy.id IN ({ids:array})', [
                    'ids' => $params['ids']
                ]);
            }

            $queryBuilder->groupBy('Policy.id');
            $policies = $queryBuilder->getQuery()->execute();


            return [
                'success' => true,
                'data' => $policies,
            ];
        }
    }
}
