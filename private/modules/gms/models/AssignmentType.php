<?php


namespace Reloday\Gms\Models;

use \Reloday\Application\Models\AssignmentTypeExt as AssignmentTypeExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Company;
use Phalcon\Http\Request;
use Reloday\Gms\Models\AssignmentTypeHasServicePack;

class AssignmentType extends AssignmentTypeExt
{

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\Assignment', 'assignment_type_id', [
            'alias' => 'Assignment'
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\Policy', 'assignment_type_id', [
            'alias' => 'Policy'
        ]);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company'
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\AssignmentTypeHasServicePack', 'assignment_type_id', 'service_pack_id', 'Reloday\Gms\Models\ServicePack', 'id', [
            'alias' => 'service_packs'
        ]);
    }

    /**
     * [getAllCurrentAssignmentType description]
     * @return [type] [description]
     */
    public static function getAllCurrentAssignmentType($company_id = '')
    {

        $current_company_gms = ModuleModel::$company;
        $companies = Company::__loadAllCompaniesOfGms();
        $company_ids = [];


        if ($companies) {

            foreach ($companies as $company) {
                $company_ids[] = $company->getId();
            }

            if (is_numeric($company_id) && $company_id > 0) {
                if (in_array($company_id, $company_ids)) {
                    $assignments = self::find([
                        'conditions' => 'company_id = :company_id: AND status != :status:',
                        'bind' => [
                            'company_id' => $company_id,
                            'status' => self::STATUS_ARCHIVED
                        ]
                    ]);
                    return $assignments;
                } else {
                    return null;
                }

            } else {
                $assignments = self::find([
                    'conditions' => 'company_id IN({company_ids:array}) AND status != :status:',
                    'bind' => [
                        'company_ids' => $company_ids,
                        'status' => self::STATUS_ARCHIVED
                    ]
                ]);
            }

            return $assignments;
        }

    }

    /**
     * [__save description]
     * @param  [type] $custom [description]
     * @return [type]         [description]
     */
    public function __save($custom = [])
    {
        $req = new Request();
        $model = $this;
        $data = $req->getPost();
        if ($req->isPut()) {
            // Request update
            $model = $this->findFirst($req->getPut('id'));
            if (!$model instanceof $this) {
                return [
                    'success' => false,
                    'message' => 'ASSIGNMENT_NOT_FOUND_TEXT',
                    'detail' => []
                ];
            }
            $data = $req->getPut();
        }

        $model->setName(isset($custom['name']) ? $custom['name'] : (isset($data['name']) ? $data['name'] : $model->getName()));
        $model->setReference(isset($custom['reference']) ? $custom['reference'] : (isset($data['reference']) ? $data['reference'] : $model->getReference()));
        $model->setCompanyId(isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId()));
        $model->setStatus(isset($data['status']) ? (int)$data['status'] : $model->getStatus());
        $service_packs_data = isset($custom['service_packs']) ?
            $custom['service_packs'] : isset($data['service_packs']) ?
                $data['service_packs'] : [];
        $service_packs_array = [];


        if (count($service_packs_data)) {
            foreach ($service_packs_data as $service_pack) {
                $service_packs_array[] = $service_pack;
            }
        }

        if (count($service_packs_array)) {
            $service_pack_to_save = [];
            foreach ($service_packs_array as $service_pack) {
                $servicePack = ServicePack::findFirstById($service_pack['id']);
                if ($servicePack) {
                    $service_pack_to_save[] = $servicePack;
                }
            }
            $model->service_packs = $service_pack_to_save;
        } else {
            $res = $model->getService_packs()->delete();
            if ($model->getId() > 0) {
                $relations = AssignmentTypeHasServicePack::find(["assignment_type_id = " . $model->getId()]);
                try {
                    if (!$relations->delete()) {
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                        ];
                        return $result;
                    }
                } catch (\PDOException $e) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                        'detail' => $e->getMessage(),
                    ];
                    return $result;
                }
            }
        }


        try {
            if ($model->getId() == null) {


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
                    'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }

    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        $company = ModuleModel::$company;
        if ($company) {
            $contract = Contract::findFirst([
                "conditions" => "from_company_id = :from_company_id: AND to_company_id = :to_company_id: AND status = :status:",
                "bind" => [
                    "from_company_id" => $this->getCompanyId(),
                    "to_company_id" => $company->getId(),
                    "status" => Contract::STATUS_ACTIVATED,
                ]
            ]);
            if ($contract || $company->getId() == $this->getCompanyId()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * archived
     * @return array|bool
     */
    public function archive()
    {
        try {
            if ($this->delete()) {
                return true;
            } else {
                $msg = [];
                foreach ($this->getMessages() as $message) {
                    $msg[$this->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'DELETE_ASSIGNMENT_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'DELETE_ASSIGNMENT_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }
}