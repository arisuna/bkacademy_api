<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Property;
use Reloday\Gms\Models\Employee;


class HousingProposition extends \Reloday\Application\Models\HousingPropositionExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('property_id',
            'Reloday\Gms\Models\Property',
            'id', [
                'alias' => 'Property',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\Property.status <> ' . Property::STATUS_DELETED
                ]
            ]);

        $this->belongsTo('employee_id',
            'Reloday\Gms\Models\Employee',
            'id', [
                'alias' => 'Employee',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\Employee.status <> ' . Employee::STATUS_ARCHIVED
                ]
            ]);

        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation',
        ]);

        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', [
            'alias' => 'RelocationServiceCompany',
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\HousingPropositionVisite', 'housing_proposition_id', [
            'alias' => 'HousingPropositionVisites',
        ]);
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        return $this->getGmsCompanyId() == ModuleModel::$company->getId() || $this->getRelocation()->belongsToGms();
    }

    /**
     * @return mixed
     */
    public function getLastVisite()
    {
        return $this->getHousingPropositionVisites()->getFirst();
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __save($custom = [])
    {

        $req = new Request();
        $model = $this;

        if (!($model->getId() > 0)) {
            if ($req->isPut()) {
                $data_id = isset($custom['housing_proposition_id']) && $custom['housing_proposition_id'] > 0 ? $custom['housing_proposition_id'] : $req->getPut('housing_proposition_id');
                if ($data_id > 0) {
                    $model = $this->findFirstById($data_id);
                    if (!$model instanceof $this) {
                        return [
                            'success' => false,
                            'message' => 'DATA_NOT_FOUND_TEXT',
                        ];
                    }
                }
            }
        }

        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
                if ($model->getUuid() == '') {
                    $random = new Random;
                    $uuid = $random->uuid();
                    $model->setUuid($uuid);
                }
            }
        }
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at'
                && $field_name != "password") {

                if (!isset($fields_numeric[$field_name])) {
                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->get($field_name));
                    $field_name_value = $field_name_value != '' ? $field_name_value : $model->get($field_name);
                    $model->set($field_name, $field_name_value);
                } else {
                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->get($field_name));
                    if ($field_name_value != '' && !is_null($field_name_value)) {
                        $model->set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/

        /****** END YOUR CODE **/
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
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * @return array
     */
    public static function __findSelectedPropertyIds()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = $di->get('modelsManager')->createBuilder();//new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->distinct(true);
        $queryBuilder->columns("HousingProposition.property_id");
        $queryBuilder->addFrom('\Reloday\Gms\Models\HousingProposition', 'HousingProposition');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = HousingProposition.relocation_id', 'Relocation');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = HousingProposition.relocation_service_company_id', 'RelocationServiceCompany');

        $queryBuilder->andwhere("HousingProposition.gms_company_id = :gms_company_id:");
        $queryBuilder->andwhere("HousingProposition.is_selected = :proposition_is_selected_yes:");
        $queryBuilder->andwhere("HousingProposition.is_deleted = :proposition_is_deleted_no:");
        $queryBuilder->andwhere("Relocation.status != :relocation_is_terminated:");
        $queryBuilder->andwhere("Relocation.active = :relocation_is_active:");
        $queryBuilder->andwhere("RelocationServiceCompany.status = :relocation_service_company_is_active:");

        $bindArray = [
            "gms_company_id" => ModuleModel::$company->getId(),
            "proposition_is_selected_yes" => Helpers::YES,
            "proposition_is_deleted_no" => Helpers::NO,
            "relocation_is_terminated" => Relocation::STATUS_TERMINATED,
            "relocation_is_active" => Relocation::STATUS_ACTIVATED,
            "relocation_service_company_is_active" => RelocationServiceCompany::STATUS_ACTIVE,
        ];

        try {
            $ids = $queryBuilder->getQuery()->execute($bindArray);
            $idArray = [];
            foreach ($ids as $id) {
                $idArray[] = (int)$id['property_id'];
            };
            return $idArray;
        } catch (\PDOException $e) {
            return [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @return array
     */
    public static function __findFirstBySelectedProperty($propertyId, $serviceId, $assigneeId)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = $di->get('modelsManager')->createBuilder();//new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->distinct(true);
        $queryBuilder->addFrom('\Reloday\Gms\Models\HousingProposition', 'HousingProposition');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = HousingProposition.relocation_id', 'Relocation');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = HousingProposition.relocation_service_company_id', 'RelocationServiceCompany');

        $queryBuilder->andwhere("HousingProposition.gms_company_id = :gms_company_id:");
        $queryBuilder->andwhere("HousingProposition.property_id = :property_id:");
        $queryBuilder->andwhere("HousingProposition.is_deleted = :proposition_is_deleted_no:");
        $queryBuilder->andwhere("HousingProposition.is_selected = :proposition_is_selected_yes:");
        $queryBuilder->andwhere("Relocation.status != :relocation_is_terminated:");
        $queryBuilder->andwhere("Relocation.active = :relocation_is_active:");
        $queryBuilder->andwhere("RelocationServiceCompany.status = :relocation_service_company_is_active:");
        $queryBuilder->andwhere("RelocationServiceCompany.id != :relocation_service_id:");

        $bindArray = [
            "gms_company_id" => ModuleModel::$company->getId(),
            'relocation_service_id' => $serviceId,
            "property_id" => $propertyId,
            "proposition_is_deleted_no" => Helpers::NO,
            "proposition_is_selected_yes" => Helpers::YES,
            "relocation_is_terminated" => Relocation::STATUS_TERMINATED,
            "relocation_is_active" => Relocation::STATUS_ACTIVATED,
            "relocation_service_company_is_active" => RelocationServiceCompany::STATUS_ACTIVE,
        ];
        $queryBuilder->limit(2);

        try {
            $data = $queryBuilder->getQuery()->execute($bindArray);
            if (count($data) > 1){
                return $data->getFirst();
            }else if(count($data) == 1){
                $housing = $data->getFirst();
                if ($housing->getEmployeeId() == $assigneeId){
                    return null;
                }else{
                    return $housing;
                }
            }else{
                return $data->getFirst();
            }
        } catch (\PDOException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array
     */
    public static function __findFirstBySelectedService($propertyId, $serviceId)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = $di->get('modelsManager')->createBuilder();//new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->distinct(true);
        $queryBuilder->addFrom('\Reloday\Gms\Models\HousingProposition', 'HousingProposition');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = HousingProposition.relocation_id', 'Relocation');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = HousingProposition.relocation_service_company_id', 'RelocationServiceCompany');

        $queryBuilder->andwhere("HousingProposition.gms_company_id = :gms_company_id:");
        $queryBuilder->andwhere("HousingProposition.property_id = :property_id:");
        $queryBuilder->andwhere("HousingProposition.is_deleted = :proposition_is_deleted_no:");
        $queryBuilder->andwhere("HousingProposition.is_selected = :proposition_is_selected_no:");
        $queryBuilder->andwhere("Relocation.status != :relocation_is_terminated:");
        $queryBuilder->andwhere("Relocation.active = :relocation_is_active:");
        $queryBuilder->andwhere("RelocationServiceCompany.status = :relocation_service_company_is_active:");
        $queryBuilder->andwhere("RelocationServiceCompany.id != :relocation_service_id:");
        $bindArray = [
            "gms_company_id" => ModuleModel::$company->getId(),
            'relocation_service_id' => $serviceId,
            "property_id" => $propertyId,
            "proposition_is_deleted_no" => Helpers::NO,
            "proposition_is_selected_no" => Helpers::NO,
            "relocation_is_terminated" => Relocation::STATUS_TERMINATED,
            "relocation_is_active" => Relocation::STATUS_ACTIVATED,
            "relocation_service_company_is_active" => RelocationServiceCompany::STATUS_ACTIVE,
        ];
        $queryBuilder->limit(1);

        try {
            return $queryBuilder->getQuery()->execute($bindArray)->getFirst();
        } catch (\PDOException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getEmployeeDetailUrl()
    {
        $company = $this->getRelocation()->getCompany();
        if ($company && $company->getApp()) {
            return $company->getApp()->getEmployeeUrl() . "#/app/my-housing-proposals/detail/" . $this->getUuid();
        }
    }
}
