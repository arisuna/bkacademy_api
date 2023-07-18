<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\BackgroundActionHelpers;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ColorHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\ReportLogHelper;
use Reloday\Application\Models\AssignmentExt;
use Reloday\Application\Models\DataUserMemberExt;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Models\RelocationExt;
use Reloday\Application\Models\ServiceEventValueExt;
use Reloday\Gms\Help\Reminder;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\HousingProposition;
use Reloday\Gms\Models\Property;
use Reloday\Gms\Models\ObjectMap;
use Phalcon\Security\Random;
use Reloday\Application\Lib\Helpers;
use Phalcon\Mvc\Model\Relation;
use Reloday\Gms\Models\Task;

use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class RelocationServiceCompany extends \Reloday\Application\Models\RelocationServiceCompanyExt
{

    const LIMIT_PER_PAGE = 20;

    /**
     *  initialize
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo(
            'relocation_id',
            'Reloday\Gms\Models\Relocation', 'id', [
                'alias' => 'Relocation',
            ]
        );
        $this->belongsTo(
            'service_company_id',
            'Reloday\Gms\Models\ServiceCompany', 'id', [
                'alias' => 'ServiceCompany',
            ]
        );
        $this->belongsTo(
            'service_provider_company_id',
            'Reloday\Gms\Models\ServiceProviderCompany', 'id', [
                'alias' => 'ServiceProviderCompany',
            ]
        );

        $this->belongsTo(
            'dependant_id', 'Reloday\Gms\Models\Dependant', 'id', [
            'alias' => 'Dependant',
            'cache' => [
                'key' => 'DEPENDANT_' . $this->getDependantId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        /** get reporters */
        $this->hasManyToMany(
            'uuid', 'Reloday\Gms\Models\DataUserMember',
            'object_uuid', 'user_profile_uuid',
            'Reloday\Gms\Models\UserProfile', 'uuid', [
                'alias' => 'Members',
                'params' => [
                    'distinct' => true
                ]
            ]
        );
        /** ger owners */
        $this->hasManyToMany(
            'uuid', 'Reloday\Gms\Models\DataUserMember',
            'object_uuid', 'user_profile_uuid',
            'Reloday\Gms\Models\UserProfile', 'uuid', [
                'alias' => 'Owners',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER,
                ]
            ]
        );
        /** get reporters */
        $this->hasManyToMany(
            'uuid', 'Reloday\Gms\Models\DataUserMember',
            'object_uuid', 'user_profile_uuid',
            'Reloday\Gms\Models\UserProfile', 'uuid',
            [
                'alias' => 'Reporters',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_REPORTER,
                ]
            ]
        );

        /**
         * get Housing Proposition
         */
        $this->hasMany(
            'id',
            'Reloday\Gms\Models\HousingProposition',
            'relocation_service_company_id', [
                'alias' => 'HousingProposition',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\HousingProposition.is_deleted = ' . HousingProposition::IS_DELETED_NO,
                ],
                "foreignKey" => [
                    "action" => Relation::ACTION_CASCADE,
                ]
            ]
        );

        /**
         * get properties proposed
         */
        $this->hasManyToMany(
            'id',
            'Reloday\Gms\Models\HousingProposition',
            'relocation_service_company_id', 'property_id',
            'Reloday\Gms\Models\Property', 'id',
            [
                'alias' => 'Properties',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\HousingProposition.is_deleted = ' . HousingProposition::IS_DELETED_NO . ' AND  Reloday\Gms\Models\HousingProposition.status <> ' . HousingProposition::STATUS_ARCHIVED
                ],
            ]
        );

        /**
         * get properties proposed
         */
        $this->hasManyToMany(
            'id',
            'Reloday\Gms\Models\HousingProposition',
            'relocation_service_company_id', 'property_id',
            'Reloday\Gms\Models\Property', 'id',
            [
                'alias' => 'SelectedProperties',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\HousingProposition.is_deleted = :is_deleted_no: AND  Reloday\Gms\Models\HousingProposition.is_selected = :is_selected_yes:',
                    'bind' => [
                        'is_deleted_no' => HousingProposition::IS_DELETED_NO,
                        'is_selected_yes' => HousingProposition::SELECTED_YES
                    ]
                ]
            ]
        );

        $this->hasMany('uuid', 'Reloday\Gms\Models\Task', 'object_uuid', [
            'alias' => 'Tasks',
            'params' => [
                'conditions' => "task_type = ". Task::TASK_TYPE_INTERNAL_TASK ." and status <> " . Task::STATUS_ARCHIVED,
                'order' => 'Reloday\Gms\Models\Task.sequence ASC',
            ]
        ]);

        $this->hasMany('uuid', 'Reloday\Gms\Models\Task', 'object_uuid', [
            'alias' => 'AssigneeTasks',
            'params' => [
                'conditions' => "task_type = ". Task::TASK_TYPE_EE_TASK ." and status <> " . Task::STATUS_ARCHIVED,
                'order' => 'Reloday\Gms\Models\Task.sequence ASC',
            ]
        ]);

        $this->hasMany('uuid', 'Reloday\Gms\Models\EntityProgress', 'object_uuid', [
            'alias' => 'EntityProgressList',
            'params' => [
                'limit' => 1,
                'order' => 'Reloday\Gms\Models\EntityProgress.created_at DESC'
            ]
        ]);

        $this->hasMany(
            'uuid',
            'Reloday\Gms\Models\EntityProgress',
            'object_uuid', [
                'alias' => 'EntityProgressListFirstItem',
                'params' => [
                    'limit' => 1,
                    'order' => 'Reloday\Gms\Models\EntityProgress.created_at ASC'
                ],
                "foreignKey" => [
                    "action" => Relation::ACTION_CASCADE,
                ]
            ]
        );

        $this->belongsTo(
            'svp_member_id',
            'Reloday\Gms\Models\SvpMembers', 'id', [
                'alias' => 'SvpMember',
            ]
        );
    }

    /**
     * manage by the current GMS
     * @return bool
     */
    public function belongsToGms()
    {
        if ($this->getRelocation() && $this->getRelocation()->belongsToGms()) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * add Owner
     * @param $profile
     */
    public function addOwner($profile)
    {
        return DataUserMember::__addOwnerWithUUID($this->getUuid(), $profile, $this->getSource());
    }

    /**
     * add Owner
     * @param $profile
     */
    public function addReporter($profile)
    {
        return DataUserMember::__addReporterWithUUID($this->getUuid(), $profile, $this->getSource());
    }

    /**
     * @return string
     */
    public function getFrontendUrl()
    {
        return ModuleModel::$app->getFrontendUrl() . "/#/app/relocation/service/detail/" . $this->getUuid();
    }

    /**
     * please use pre calculated data
     * in before save
     * @return string
     */
    public function getSimpleFrontendUrl()
    {
        if ($this->getRelocation()) {
            $company = $this->getRelocation()->getCreatorCompany();
            if ($company) {
                return $company->getFrontendUrl() . "/#/app/relocation/service/detail/" . $this->getUuid();
            }
        }
    }

    /**
     * @return string
     */
    public function getFrontendState($options = "")
    {
        return "app.relocation.service-detail({uuid:'" . $this->getUuid() . "'})";
    }

    /**
     * get object of owner
     */
    public function getDataOwner()
    {
        return DataUserMember::getDataOwner($this->getUuid());
    }

    /**
     * get object of owner
     */
    public function getOwner()
    {
        return DataUserMember::getDataOwner($this->getUuid());
    }

    /**
     * get object of reporter
     */
    public function getDataReporter()
    {
        return $this->getReporters()->getFirst();
    }

    /**
     * save type in object map
     * @return array
     */
    public function afterSave()
    {
        parent::afterSave(); // TODO: Change the autogenerated stub
    }

    /**
     *
     */
    public static function __createNew($uuid = '', $relocation, $serviceCompany, $name = '', $depentdant_id)
    {
        //the name of relocation services of a relocation must be unique
        $checkName = self::find([
            "conditions" => "relocation_id = :id:  and name = :name: and status = :status:",
            "bind" => [
                "id" => $relocation->getId(),
                "name" => $name,
                "status" => self::STATUS_ACTIVE
            ]
        ]);

        if (count($checkName) > 0) {
            return ['success' => false, 'detail' => 'NAME_MUST_BE_UNIQUE_TEXT'];
        }

        if ($uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            $uuid = Helpers::__uuid();
        }

        $model = new self();
        $model->setUuid($uuid);
        $model->setRelocationId($relocation->getId());
        $model->setServiceCompanyId($serviceCompany->getId());
        $model->setDependantId($depentdant_id);
        $model->setStatus(self::STATUS_ACTIVE);
        $model->setWorkflowApplied(self::WORKFLOW_APPLIED_NO);
        $model->setName($name == '' ? $serviceCompany->getName() : $name);
        $model->setNumber($model->generateNumber());
        $result = $model->__quickCreate();
        return $result;
    }

    /**
     * @param $relocation_id
     * @param $service_company_id
     */
    public static function __findByRelocationAndService($relocation_id, $service_company_id)
    {
        return self::findFirst([
            'conditions' => 'relocation_id = :relocation_id: AND service_company_id = :service_company_id:',
            'bind' => [
                'relocation_id' => $relocation_id,
                'service_company_id' => $service_company_id,
            ],
            'order' => 'created_at DESC',
        ]);
    }


    /**
     * generate nickname before save
     */
    public function beforeSave()
    {
        parent::beforeSave();
    }

    /**
     * get all need form gabrits
     */
    public function getAllNeedFormGabarit()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\NeedFormGabarit', 'NeedFormGabarit');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\NeedFormGabaritServiceCompany', '(NeedFormGabarit.id = NeedFormGabaritServiceCompany.need_form_gabarit_id and NeedFormGabaritServiceCompany.service_company_id = ' . $this->getServiceCompanyId() . ')', 'NeedFormGabaritServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.service_company_id = NeedFormGabaritServiceCompany.service_company_id', 'RelocationServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\NeedFormRequest', 'NeedFormRequest.need_form_gabarit_id = NeedFormGabarit.id', 'NeedFormRequest');
        $queryBuilder->where('((RelocationServiceCompany.id = :id: AND RelocationServiceCompany.status = :status_relocation_service_company_active: AND NeedFormGabaritServiceCompany.is_deleted <> :is_deleted_yes:) or (NeedFormRequest.status = ' . NeedFormRequest::STATUS_ANSWERED . ' and NeedFormRequest.relocation_service_company_id = :id: AND NeedFormGabaritServiceCompany.is_deleted = :is_deleted_yes:))');
        $queryBuilder->andWhere('NeedFormGabarit.status = :status_active:');
        // $queryBuilder->andWhere('NeedFormGabaritServiceCompany.is_deleted <> :is_deleted_yes:');
        $queryBuilder->orderBy("NeedFormGabarit.number ASC");
        $queryBuilder->groupBy("NeedFormGabarit.id");
        try {
            $relocations = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), [
                'id' => $this->getId(),
                'status_relocation_service_company_active' => \Reloday\Gms\Models\RelocationServiceCompany::STATUS_ACTIVE,
                'status_active' => \Reloday\Gms\Models\NeedFormGabarit::STATUS_ACTIVE,
                'is_deleted_yes' => ModelHelper::YES,
            ]));
            return ['success' => true, 'data' => $relocations];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }


    /**
     *
     */
    public function getSelectedProperty()
    {
        $properties = $this->getSelectedProperties();
        if ($properties && $properties->count() > 0) {
            return $properties->getFirst();
        }
    }

    /**
     * @return mixed
     */
    public function getSelectedHousingProposition()
    {
        $propositions = $this->getHousingProposition([
            'conditions' => 'is_selected = :is_selected_yes:',
            'bind' => [
                'is_selected_yes' => HousingProposition::SELECTED_YES,
            ]
        ]);
        if ($propositions && $propositions->count() > 0) {
            return $propositions->getFirst();
        }
    }

    /**
     *
     */
    public function afterFetch()
    {
        if ($this->getName() == null || $this->getName() == '') {
            $this->setName($this->getServiceCompany()->getName());
        }
    }

    /**
     * @return int
     */
    public function getLastEntityProgressItem()
    {
        return $this->getEntityProgressList()->count() > 0 ? ($this->getEntityProgressList()->getFirst()) : null;
    }

    /**
     * @return int
     */
    public function getEntityProgressValue()
    {
        return $this->getEntityProgressList()->count() > 0 ? intval($this->getEntityProgressList()->getFirst()->getValue()) : 0;
    }

    /**
     * @return int
     */
    public function getEntityProgressStatus()
    {
        return $this->getEntityProgressList()->count() > 0 ? intval($this->getEntityProgressList()->getFirst()->getStatus()) : self::STATUS_NOT_STARTED;
    }

    /**
     * start Add Attachment to Background Service
     * @return array
     */
    public function startAddAttachment()
    {
        $beanQueue = RelodayQueue::__getQueueRelocationService();
        $dataArray = [
            'action' => BackgroundActionHelpers::METHOD_ADD_ATTACHMENT,
            'service_id' => $this->getServiceCompany()->getService()->getId(),
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'service_company_id' => $this->getServiceCompany()->getId(),
            'relocation_service_id' => $this->getId(),
        ];
        return $beanQueue->addQueue($dataArray);
    }

    /**
     * start add Workflow to Background Service
     * @return array
     */
    public function startAddWorkflow()
    {
        $beanQueue = RelodayQueue::__getQueueRelocationService();
        $dataArray = [
            'action' => BackgroundActionHelpers::METHOD_ADD_WORKFLOW,
            'service_id' => $this->getServiceCompany()->getService()->getId(),
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'service_company_id' => $this->getServiceCompany()->getId(),
            'relocation_service_id' => $this->getId(),
        ];
        return $beanQueue->addQueue($dataArray);
    }

    /**
     * start add Workflow to Background Service
     * @return array
     */
    public function startAddEmployeeWorkflow()
    {
        $beanQueue = RelodayQueue::__getQueueRelocationService();
        $dataArray = [
            'action' => BackgroundActionHelpers::METHOD_ADD_EE_WORKFLOW,
            'service_id' => $this->getServiceCompany()->getService()->getId(),
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'service_company_id' => $this->getServiceCompany()->getId(),
            'relocation_service_id' => $this->getId(),
        ];
        return $beanQueue->addQueue($dataArray);
    }

    /**
     * start add Workflow to Background Service
     * @return array
     */
    public function startPrefillData()
    {
        $beanQueue = RelodayQueue::__getQueueRelocationService();
        $dataArray = [
            'action' => BackgroundActionHelpers::METHOD_ADD_PREFILL_DATA,
            'service_id' => $this->getServiceCompany()->getService()->getId(),
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'service_company_id' => $this->getServiceCompany()->getId(),
            'relocation_service_id' => $this->getId(),
        ];
        return $beanQueue->addQueue($dataArray);
    }

    /**
     * start add Workflow to Background Service
     * @return array
     */
    public function startUpdateEntityProgress()
    {
        $beanQueue = RelodayQueue::__getQueueRelocationService();
        $dataArray = [
            'action' => BackgroundActionHelpers::METHOD_UPDATE_ENTITY_PROGRESS,
            'service_id' => $this->getServiceCompany()->getService()->getId(),
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'service_company_id' => $this->getServiceCompany()->getId(),
            'relocation_service_id' => $this->getId(),
        ];
        return $beanQueue->addQueue($dataArray);
    }

    /**
     * @return bool
     */
    public function checkMyViewPermission()
    {
        return DataUserMember::checkMyViewPermission($this->getUuid());
    }

    /**
     * @param $relocation
     */
    public function setRelocation($relocation)
    {
        $this->relocation = $relocation;
        $this->setRelocationId($relocation->getId());
    }

    /**
     * Get label for a given status
     * @param $status
     * @return mixed|string|null
     */
    public static function __getStatusLabel($status)
    {
        $statusName = null;
        $statusText = self::$status_label_list[$status];
        if ($statusText) {
            $lang = ModuleModel::$language ? ModuleModel::$language : 'en';
            $statusName = ConstantHelper::__translate($statusText, $lang) ?
                ConstantHelper::__translate($statusText, $lang) : $statusText;
        }
        return $statusName;
    }

    /**
     * @return mixed
     */
    public function getAssignment()
    {
        return $this->getRelocation() ? $this->getRelocation()->getAssignment() : null;
    }

    public static function __executeTopServiceDownloadReport($params = []){
        $queryString = "SELECT serviceCompany.uuid, serviceCompany.name, service.name as template_service, COUNT(relocationServiceCompany.id) as count
                        FROM service_company as serviceCompany
                        INNER JOIN relocation_service_company as relocationServiceCompany ON relocationServiceCompany.service_company_id = serviceCompany.id
                        INNER JOIN relocation ON relocation.id = relocationServiceCompany.relocation_id
                        INNER JOIN assignment ON assignment.id = relocation.assignment_id
                        INNER JOIN service ON service.id = serviceCompany.service_id
                        WHERE serviceCompany.company_id = " . ModuleModel::$company->getId() . " ";

        $queryString .= "AND assignment.archived = FALSE ";
        $queryString .= "AND relocation.status <> -1 ";
        $queryString .= "AND relocation.active IN (1,-1) ";
        $queryString .= "AND relocationServiceCompany.status <> -1 ";

        if (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_month') {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '3_months') {
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '6_months') {
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_year') {
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryString .= "and relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } else {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        }

        if (isset($params['hr_company_id']) && is_numeric($params['hr_company_id']) && $params['hr_company_id'] > 0) {
            $queryString .= "AND assignment.company_id = " . $params['hr_company_id'] . " ";
        }

        $queryString .= "GROUP BY serviceCompany.uuid, serviceCompany.name, service.name ";
        $queryString .= "ORDER BY COUNT(relocationServiceCompany.id) DESC ";


        if (isset($params['limit']) && is_numeric($params['limit']) && $params['limit'] > 0) {
            $queryString .= "LIMIT 10";
        }

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());

        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    /**
     * @return array
     */
    public static function __executeServiceTopReport($params)
    {


        $queryString = "SELECT serviceCompany.uuid, serviceCompany.name, COUNT(relocationServiceCompany.id) as count
                        FROM service_company as serviceCompany
                        INNER JOIN relocation_service_company as relocationServiceCompany ON relocationServiceCompany.service_company_id = serviceCompany.id
                        INNER JOIN relocation ON relocation.id = relocationServiceCompany.relocation_id
                        INNER JOIN assignment ON assignment.id = relocation.assignment_id
                        WHERE serviceCompany.company_id = " . ModuleModel::$company->getId() . " ";

        $queryString .= "AND assignment.archived = FALSE ";
        $queryString .= "AND relocation.status <> -1 ";
        $queryString .= "AND relocation.active in (1, -1) ";
        $queryString .= "AND relocationServiceCompany.status <> -1 ";

        if (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_month') {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '3_months') {
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '6_months') {
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_year') {
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryString .= "and relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } else {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        }


        if (isset($params['hr_company_id']) && is_numeric($params['hr_company_id']) && $params['hr_company_id'] > 0) {
            $queryString .= "AND assignment.company_id = " . $params['hr_company_id'] . " ";
        }

        $queryString .= "GROUP BY serviceCompany.uuid, serviceCompany.name ";
        $queryString .= "ORDER BY COUNT(relocationServiceCompany.id) DESC ";


        if (isset($params['limit']) && is_numeric($params['limit']) && $params['limit'] > 0) {
            $queryString .= "LIMIT 10";
        }


        if (ReportLogHelper::__checkResultIfExisted(ModuleModel::$company->getId(), $queryString)) {
            return ReportLogHelper::__getResultInCache(ModuleModel::$company->getId(), $queryString);
        }

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }

        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        ReportLogHelper::__saveResultInCache(ModuleModel::$company->getId(), $queryString, $executionInfo);
        return $executionInfo;
    }

    public static function __executeServicePerAccountDownloadReport($params = []){
        $queryString = " SELECT RelocationServiceCompany.name as service_name,
        Service.name as service_template,
        RelocationServiceCompany.number as service_id,
        Employee.firstname,
        Employee.lastname,
        CASE 
            WHEN RelocationServiceCompany.progress = 0 THEN 'To Do'
            WHEN RelocationServiceCompany.progress = 1 THEN 'In Progress'
            WHEN RelocationServiceCompany.progress = 3 THEN 'Completed'
            ELSE ''
        END as status,
        RelocationServiceCompany.progress_value AS progress,
        CASE
		    WHEN Relocation.active = 1 THEN 'No'
		    WHEN Relocation.active = -1 THEN 'YES'
		    ELSE ''
	    END as archived,
        Assignment.reference as asmt_id,
        Relocation.identify as relo_id,
        Company.name as account,
        CASE
            WHEN Assignment.booker_company_id > 0 THEN (
                SELECT company.name
                from company
                where id = Assignment.booker_company_id
            ) ELSE ''
        END as booker,
        Employee.reference as ee_interal_id,
        Employee.workemail,
        RelocationServiceCompany.created_at as launched_on,
        date_information.value as infomation_of_date,
        creator.fullname AS dsp_reporter_name,
        owner.fullname AS dsp_owner_name,
        viewer.fullname as dsp_viewer_name
    FROM relocation_service_company AS RelocationServiceCompany
        INNER JOIN service_company AS ServiceCompany ON ServiceCompany.id = RelocationServiceCompany.service_company_id
        INNER JOIN service as Service ON Service.id = ServiceCompany.service_id
        INNER JOIN relocation AS Relocation ON Relocation.id = RelocationServiceCompany.relocation_id
        INNER JOIN assignment AS Assignment ON Assignment.id = Relocation.assignment_id
        INNER JOIN employee AS Employee ON Employee.id = Assignment.employee_id
        INNER JOIN company AS Company ON Company.id = Employee.company_id
        INNER JOIN (
            select concat(
                    user_profile.firstname,
                    ' ',
                    user_profile.lastname
                ) as fullname,
                data_user_member.object_uuid as object_uuid,
                user_profile.id as creator_profile_id
            from data_user_member
                join user_profile on user_profile.uuid = data_user_member.user_profile_uuid
            where data_user_member.member_type_id = 5
                and user_profile.company_id = " . ModuleModel::$company->getId() . "
        ) as creator on creator.object_uuid = RelocationServiceCompany.uuid
        INNER JOIN (
            select concat(
                    user_profile.firstname,
                    ' ',
                    user_profile.lastname
                ) as fullname,
                data_user_member.object_uuid as object_uuid,
                user_profile.id as owner_profile_id
            from data_user_member
                join user_profile on user_profile.uuid = data_user_member.user_profile_uuid
            where data_user_member.member_type_id = 2
                and user_profile.company_id = " . ModuleModel::$company->getId() . "
        ) as owner on owner.object_uuid = RelocationServiceCompany.uuid
        LEFT JOIN (
            select array_agg(
                    concat(
                        user_profile.firstname,
                        ' ',
                        user_profile.lastname
                    )
                ) as fullname,
                data_user_member.object_uuid as object_uuid
            from data_user_member
                join user_profile on user_profile.uuid = data_user_member.user_profile_uuid
            where data_user_member.member_type_id = 6
                and user_profile.company_id = " . ModuleModel::$company->getId() . "
            group by data_user_member.object_uuid ) as viewer on viewer.object_uuid = RelocationServiceCompany.uuid
        LEFT JOIN (
		    SELECT relocation_service_company_id, array_join(array_agg(value), ',') as value
		    FROM (
				SELECT *
				FROM service_event_value
				ORDER BY id ASC
			)
		GROUP BY relocation_service_company_id
	) as date_information on date_information.relocation_service_company_id = RelocationServiceCompany.id";

        $queryString .= " WHERE ServiceCompany.company_id = " . ModuleModel::$company->getId() . " ";
        $queryString .= " AND Assignment.archived = false ";
        $queryString .= " AND Relocation.status <> -1 ";
        $queryString .= " AND Relocation.active IN (1, -1) ";
        $queryString .= "AND RelocationServiceCompany.status <> -1 ";

        if (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_month') {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '3_months') {
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '6_months') {
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_year') {
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryString .= "and RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } else {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        }

        if(isset($params['hr_company_id']) && $params['hr_company_id'] > 0){
            $queryString .= " AND Company.id = ".$params['hr_company_id']." ";
        }

        $queryString .= " ORDER BY launched_on DESC ";

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    public static function __executeServicePerStatusDownloadReport($params = []){

        $queryString = " SELECT RelocationServiceCompany.name as service_name,
        Service.name as service_template,
        RelocationServiceCompany.number as service_id,
        Employee.firstname,
        Employee.lastname,
        CASE 
            WHEN RelocationServiceCompany.progress = 0 THEN 'To Do'
            WHEN RelocationServiceCompany.progress = 1 THEN 'In Progress'
            WHEN RelocationServiceCompany.progress = 3 THEN 'Completed'
            ELSE ''
        END as status,
        CONCAT(RelocationServiceCompany.progress_value, '%')  AS progress,
        CASE
		    WHEN Relocation.active = 1 THEN 'No'
		    WHEN Relocation.active = -1 THEN 'YES'
		    ELSE ''
	    END as archived,
        Assignment.reference as asmt_id,
        Relocation.identify as relo_id,
        Company.name as account,
        CASE
            WHEN Assignment.booker_company_id > 0 THEN ( SELECT company.name from company where id = Assignment.booker_company_id) ELSE ''
        END as booker,
        Employee.reference as ee_interal_id, Employee.workemail, RelocationServiceCompany.created_at as launched_on,
        (SELECT from_unixtime(service_event_value.value, '%Y-%m-%d') from service_event_value where service_event_value.relocation_service_company_id = RelocationServiceCompany.id LIMIT 1 OFFSET 0 ) as start_date,
        (SELECT from_unixtime(service_event_value.value, '%Y-%m-%d') from service_event_value where service_event_value.relocation_service_company_id = RelocationServiceCompany.id LIMIT 1 OFFSET 1 ) as end_date,
        (SELECT from_unixtime(service_event_value.value, '%Y-%m-%d') from service_event_value where service_event_value.relocation_service_company_id = RelocationServiceCompany.id LIMIT 1 OFFSET 2 ) as authorise_date,
        (SELECT from_unixtime(service_event_value.value, '%Y-%m-%d') from service_event_value where service_event_value.relocation_service_company_id = RelocationServiceCompany.id LIMIT 1 OFFSET 3 ) as completion_date,
        (SELECT from_unixtime(service_event_value.value, '%Y-%m-%d') from service_event_value where service_event_value.relocation_service_company_id = RelocationServiceCompany.id LIMIT 1 OFFSET 4 ) as expiry_date,
        creator.fullname AS dsp_reporter_name, owner.fullname AS dsp_owner_name, CONCAT('[', viewer.fullname, ']')  as dsp_viewer_name
    FROM relocation_service_company AS RelocationServiceCompany
        INNER JOIN service_company AS ServiceCompany ON ServiceCompany.id = RelocationServiceCompany.service_company_id
        INNER JOIN service as Service ON Service.id = ServiceCompany.service_id
        INNER JOIN relocation AS Relocation ON Relocation.id = RelocationServiceCompany.relocation_id
        INNER JOIN assignment AS Assignment ON Assignment.id = Relocation.assignment_id
        INNER JOIN employee AS Employee ON Employee.id = Assignment.employee_id
        INNER JOIN company AS Company ON Company.id = Employee.company_id
        INNER JOIN (
            select concat(
                    user_profile.firstname,
                    ' ',
                    user_profile.lastname
                ) as fullname,
                data_user_member.object_uuid as object_uuid,
                user_profile.id as creator_profile_id
            from data_user_member
                join user_profile on user_profile.uuid = data_user_member.user_profile_uuid
            where data_user_member.member_type_id = 5
                and user_profile.company_id = " . ModuleModel::$company->getId() . "
        ) as creator on creator.object_uuid = RelocationServiceCompany.uuid
        INNER JOIN (
            select concat(
                    user_profile.firstname,
                    ' ',
                    user_profile.lastname
                ) as fullname,
                data_user_member.object_uuid as object_uuid,
                user_profile.id as owner_profile_id
            from data_user_member
                join user_profile on user_profile.uuid = data_user_member.user_profile_uuid
            where data_user_member.member_type_id = 2
                and user_profile.company_id = " . ModuleModel::$company->getId() . "
        ) as owner on owner.object_uuid = RelocationServiceCompany.uuid
        LEFT JOIN (
            select group_concat( concat(user_profile.firstname, ' ', user_profile.lastname) SEPARATOR ';') as fullname, data_user_member.object_uuid as object_uuid
            from data_user_member
                join user_profile on user_profile.uuid = data_user_member.user_profile_uuid
            where data_user_member.member_type_id = 6
                and user_profile.company_id = " . ModuleModel::$company->getId() . "
            group by data_user_member.object_uuid) as viewer on viewer.object_uuid = RelocationServiceCompany.uuid ";

        $queryString .= "WHERE ServiceCompany.company_id = " . ModuleModel::$company->getId() . " ";
        $queryString .= "AND Assignment.archived = false ";
        $queryString .= "AND Relocation.status <> -1 ";
        $queryString .= "AND Relocation.active IN (1,-1)  ";
        $queryString .= "AND RelocationServiceCompany.status <> -1 ";

        if (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_month') {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '3_months') {
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '6_months') {
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_year') {
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryString .= "and RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        } else {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "AND RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
        }

        if(isset($params['service_company_id']) && $params['service_company_id'] > 0){
            $queryString .= " AND ServiceCompany.id = ". $params['service_company_id'];
        }

        $queryString .= " ORDER BY launched_on DESC";

        $di = \Phalcon\DI::getDefault();
        $db = $di['db'];
        $data = $db->query( $queryString );
        $data->setFetchMode(\Phalcon\Db::FETCH_OBJ);
        $results = $data->fetchAll();

        return [
            'success' => true,
            'data' => $results,
        ];
    }

    /**
     * @return array
     */
    public static function __executeServicePerStatusReport($params, $isDirectSql = false)
    {
        if ($isDirectSql == true) {
            $bindArray = [];

            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany');
            $queryBuilder->distinct(true);
            $queryBuilder->columns([
                "RelocationServiceCompany.progress",
                "count" => "COUNT(RelocationServiceCompany.id)",
            ]);
            $queryBuilder->innerJoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = RelocationServiceCompany.service_company_id', 'ServiceCompany');
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = RelocationServiceCompany.relocation_id', 'Relocation');
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Assignment.id = Relocation.assignment_id', 'Assignment');
            $queryBuilder->where('ServiceCompany.company_id = ' . ModuleModel::$company->getId());
            $queryBuilder->andwhere('Assignment.archived = ' . ModelHelper::NO);
            $queryBuilder->andwhere('Relocation.status <> ' . RelocationExt::STATUS_CANCELED);
            $queryBuilder->andwhere('Relocation.active IN (' . RelocationExt::STATUS_ACTIVATED.','.RelocationExt::STATUS_ARCHIVED. ')');
            $queryBuilder->andwhere('RelocationServiceCompany.status <> ' . RelocationServiceCompany::STATUS_ARCHIVED);

            if (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] != '') {
                if ($params['created_at_period'] == '1_month') {
                    $created_at = date('Y-m-d', strtotime('- 1 month'));
                } elseif ($params['created_at_period'] == '3_months') {
                    $created_at = date('Y-m-d', strtotime('- 3 months'));
                } elseif ($params['created_at_period'] == '6_months') {
                    $created_at = date('Y-m-d', strtotime('- 6 months'));
                } elseif ($params['created_at_period'] == '1_year') {
                    $created_at = date('Y-m-d', strtotime('- 1 year'));
                } else {
                    $created_at = date('Y-m-d', strtotime('- 1 month'));
                }
                $queryBuilder->andWhere("RelocationServiceCompany.created_at >= :created_at:");
                $bindArray['created_at'] = $created_at;
            }

            if (isset($params['service_company_id']) && is_numeric($params['service_company_id']) && $params['service_company_id'] > 0) {
                $queryBuilder->andWhere("ServiceCompany.id = " . $params['service_company_id'] . " ");
            }

            $queryBuilder->groupBy("RelocationServiceCompany.progress");
            $queryBuilder->orderBy("COUNT(RelocationServiceCompany.id) DESC ");

            try {
                $items = $queryBuilder->getQuery()->execute($bindArray);
                $itemsArray = [];
                foreach ($items as $item) {
                    $statusInt = intval($item['progress']);
                    $itemsArray[] = [
                        "data" => intval($item['count']),
                        "label" => ConstantHelper::__translate(RelocationServiceCompany::$status_label_list[$statusInt], ModuleModel::$language),
                        "color" => $statusInt == 0 ? ColorHelper::YELLOW : ($statusInt == 1 ? ColorHelper::BRIGHT_BLUE : ($statusInt == 3 ? ColorHelper::GREEN : ColorHelper::GRAY))
                    ];
                }
                return [
                    'success' => true,
                    'data' => $itemsArray,
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    '$exception' => $e->getMessage(),
                    'data' => []
                ];
            }

        } else {
            $queryString = "SELECT relocationServiceCompany.progress, COUNT(relocationServiceCompany.id) as count
                        FROM relocation_service_company as relocationServiceCompany
                        INNER JOIN service_company as serviceCompany ON relocationServiceCompany.service_company_id = serviceCompany.id
                        INNER JOIN relocation ON relocation.id = relocationServiceCompany.relocation_id
                        INNER JOIN assignment ON assignment.id = relocation.assignment_id
                        WHERE serviceCompany.company_id = " . ModuleModel::$company->getId() . " ";

            $queryString .= "AND assignment.archived = FALSE ";
            $queryString .= "AND relocation.status <> -1 ";
            $queryString .= "AND relocationServiceCompany.status <> -1 ";

            if (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_month') {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
            } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '3_months') {
                $created_at = date('Y-m-d', strtotime('- 3 months'));
                $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
            } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '6_months') {
                $created_at = date('Y-m-d', strtotime('- 6 months'));
                $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
            } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_year') {
                $created_at = date('Y-m-d', strtotime('- 1 year'));
                $queryString .= "and relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
            } else {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryString .= "AND relocationServiceCompany.created_at >=  DATE('" . $created_at . "')";
            }

            if (isset($params['service_company_id']) && is_numeric($params['service_company_id']) && $params['service_company_id'] > 0) {
                $queryString .= "AND serviceCompany.id = " . $params['service_company_id'] . " ";
            }

            $queryString .= " GROUP BY relocationServiceCompany.progress";
            $queryString .= " ORDER BY COUNT(relocationServiceCompany.id) DESC ";

            if (isset($params['limit']) && is_numeric($params['limit']) && $params['limit'] > 0) {
                $queryString .= " LIMIT 10";
            }

            if (ReportLogHelper::__checkResultIfExisted(ModuleModel::$company->getId(), $queryString)) {
                return ReportLogHelper::__getResultInCache(ModuleModel::$company->getId(), $queryString);
            }


            AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
            $execution = AthenaHelper::executionQuery($queryString);
            if (!$execution["success"]) {
                return $execution;
            }

            $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
            ReportLogHelper::__saveResultInCache(ModuleModel::$company->getId(), $queryString, $executionInfo);
            return $executionInfo;
        }
    }

    /**
     * @param array $options
     * @return array
     */
    public static function __findWithFilter(array $options = array(), $orders = array(), $fullinfo = false): array
    {
        $bindArray = [];
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany');
        $queryBuilder->distinct(true);

        $queryBuilder->innerjoin('\Reloday\Gms\Models\Relocation', 'RelocationServiceCompany.relocation_id = Relocation.id ', 'Relocation');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\ServiceCompany', 'RelocationServiceCompany.service_company_id = ServiceCompany.id ', 'ServiceCompany');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentBasic', 'AssignmentBasic.id = Assignment.id', 'AssignmentBasic');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentDestination', 'AssignmentDestination.id = Assignment.id', 'AssignmentDestination');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'Assignment.booker_company_id = BookerCompany.id', 'BookerCompany');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Assignment.company_id = HrCompany.id', 'HrCompany');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Assignment.employee_id = Employee.id', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'HomeCountry.id = Assignment.home_country_id', 'HomeCountry');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'DestinationCountry.id = Assignment.destination_country_id', 'DestinationCountry');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValue.relocation_service_company_id', 'ServiceEventValue');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEvent', 'ServiceEventValue.service_event_id = ServiceEvent.id', 'ServiceEvent');

        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceProviderCompany', 'RelocationServiceCompany.service_provider_company_id = ServiceProviderCompany.id ', 'ServiceProviderCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEvent', 'ServiceEventStart.service_id = ServiceCompany.service_id AND ServiceEventStart.code = "SERVICE_START"', 'ServiceEventStart');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEventValue', 'ServiceEventStartValue.relocation_service_company_id = RelocationServiceCompany.id AND ServiceEventStartValue.service_event_id = ServiceEventStart.id', 'ServiceEventStartValue');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEvent', 'ServiceEventEnd.service_id = ServiceCompany.service_id AND ServiceEventEnd.code = "SERVICE_END"', 'ServiceEventEnd');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEventValue', 'ServiceEventEndValue.relocation_service_company_id = RelocationServiceCompany.id AND ServiceEventEndValue.service_event_id = ServiceEventEnd.id', 'ServiceEventEndValue');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwner.object_uuid = RelocationServiceCompany.uuid and DataUserOwner.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER, 'DataUserOwner');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserProfile', 'DataUserOwner.user_profile_uuid = OwnerUserProfile.uuid', 'OwnerUserProfile');
        if (isset($options['is_count_all']) && $options['is_count_all'] == true) {
            $queryBuilder->columns([
                "RelocationServiceCompany.id",
            ]);
        }else{
            $queryBuilder->columns([
                "description" => "RelocationServiceCompany.name",
                "relocation_uuid" => "Relocation.uuid",
                "relocation_cancelled" => "IF(Relocation.status = -1 , true, false)",
                "service_company_uuid" => "ServiceCompany.uuid",
                "relocation_service_company_uuid" => "RelocationServiceCompany.uuid",
                "relocation_service_name" => "IF(RelocationServiceCompany.name is not null, RelocationServiceCompany.name, ServiceCompany.name)",
                "service_started_at" => "ServiceEventValue.value",
                "service_ended_at" => "ServiceEventEndValue.value",
                'owner_name' => 'CONCAT(OwnerUserProfile.firstname, " ", OwnerUserProfile.lastname)',
                'owner_uuid' => 'DataUserOwner.user_profile_uuid',
                'owner_id' => 'OwnerUserProfile.id',
                'owner_external_hris_id' => 'OwnerUserProfile.external_hris_id',
                'owner_firstname' => 'OwnerUserProfile.firstname',
                'owner_lastname' => 'OwnerUserProfile.lastname',
                'owner_nickname' => 'OwnerUserProfile.nickname',
                'owner_jobtitle' => 'OwnerUserProfile.jobtitle',
                'owner_phonework' => 'OwnerUserProfile.phonework',
                'owner_workemail' => 'OwnerUserProfile.workemail',
                'assignment_uuid' => "Assignment.uuid",
                "employee_name" =>  'CONCAT(Employee.firstname, " ", Employee.lastname)',
                "employee_uuid" => 'Employee.uuid',
                'employee_email' =>  'Employee.workemail',
                'company_name' => 'HrCompany.name',
                'assignment_number' => 'Assignment.reference',
                'booker_company_name' => 'BookerCompany.name',
                "RelocationServiceCompany.id",
                "RelocationServiceCompany.uuid",
                "RelocationServiceCompany.name",
                "RelocationServiceCompany.number",
                "RelocationServiceCompany.status",
                "RelocationServiceCompany.is_visit_allowed",
                "RelocationServiceCompany.progress_value",
                "RelocationServiceCompany.created_at",
                "RelocationServiceCompany.updated_at",
                "RelocationServiceCompany.workflow_applied",
                "RelocationServiceCompany.relocation_id",
                "RelocationServiceCompany.service_company_id",
                "RelocationServiceCompany.service_provider_company_id",
                "RelocationServiceCompany.svp_member_id",
                "RelocationServiceCompany.dependant_id",
                "RelocationServiceCompany.data",
                "RelocationServiceCompany.events",
                "RelocationServiceCompany.search_infos",
                "RelocationServiceCompany.active_assignee_task",
                'svp_company_id' => "ServiceProviderCompany.id",
                'svp_company_uuid' => "ServiceProviderCompany.uuid",
                'svp_name' => "ServiceProviderCompany.name",
            ]);
        }

        $queryBuilder->where('Relocation.creator_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('RelocationServiceCompany.status = :relocation_service_company_status:', [
            'relocation_service_company_status' => self::STATUS_ACTIVE
        ]);
        $queryBuilder->andwhere('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Assignment.archived = :assignment_not_archived:', [
            'assignment_not_archived' => Assignment::ARCHIVED_NO
        ]);

        $queryBuilder->andwhere('Contract.status = :contract_active:', [
            'contract_active' => Contract::STATUS_ACTIVATED
        ]);

        $queryBuilder->andwhere('Relocation.active = :status_relocation_active:', [
            'status_relocation_active' => 1
        ]);

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['RelocationServiceCompany.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['RelocationServiceCompany.created_at DESC']);
                }
            } else {
                $queryBuilder->orderBy(['RelocationServiceCompany.created_at DESC']);
            }

            if ($order['field'] == "start_date") {
                $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValueStartDate.relocation_service_company_id', 'ServiceEventValueStartDate');
                $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEvent', "ServiceEventValueStartDate.service_event_id = ServiceEventStartDate.id and ServiceEventStartDate.code = 'SERVICE_START'", 'ServiceEventStartDate');
                $queryBuilder->andWhere('ServiceEventStartDate.code = \'SERVICE_START\'');

                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceEventValueStartDate.value ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceEventValueStartDate.value DESC']);
                }
            }

            if ($order['field'] == "end_date") {
                $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValueEndDate.relocation_service_company_id', 'ServiceEventValueEndDate');
                $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceEvent', "ServiceEventValueEndDate.service_event_id = ServiceEventEndDate.id and ServiceEventEndDate.code = 'SERVICE_END'", 'ServiceEventEndDate');
                $queryBuilder->andWhere('ServiceEventEndDate.code = \'SERVICE_END\'');

                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceEventValueEndDate.value ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceEventValueEndDate.value DESC']);
                }
            }

            if ($order['field'] == "employee") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC', 'Employee.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC', 'Employee.lastname DESC']);
                }
            }
        } else {
            $queryBuilder->orderBy('RelocationServiceCompany.created_at DESC');
        }

        if (isset($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = RelocationServiceCompany.uuid', 'DataUserMember');

            $queryBuilder->andwhere("DataUserMember.user_profile_uuid = :user_profile_uuid:", ["user_profile_uuid" => $options['user_profile_uuid']]);
            $bindArray['user_profile_uuid'] = $options['user_profile_uuid'];

            if (isset($options['is_owner']) && $options['is_owner'] == true) {
                $queryBuilder->andWhere('DataUserMember.member_type_id = :_member_type_owner:', [
                    '_member_type_owner' => DataUserMemberExt::MEMBER_TYPE_OWNER
                ]);
            }
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Assignment.employee_id = :employee_id:", ["employee_id" => $options['employee_id']]);
            $bindArray['employee_id'] = $options['employee_id'];
        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("Relocation.hr_company_id = :company_id:", [
                'company_id' => $options['company_id'],
            ]);
        }

        if (isset($options['hr_company_id']) && is_numeric($options['hr_company_id']) && $options['hr_company_id'] > 0) {
            $queryBuilder->andwhere("Relocation.hr_company_id = :hr_company_id:", [
                'hr_company_id' => $options['hr_company_id'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Relocation.employee_id = :employee_id:", [
                'employee_id' => $options['employee_id'],
            ]);
        }

        if (isset($options['assignees']) && is_array($options['assignees']) && count($options['assignees']) > 0) {
            $queryBuilder->andwhere("Relocation.employee_id IN ({assignees:array})", [
                'assignees' => $options['assignees'],
            ]);
        }

        if (isset($options['companies']) && is_array($options['companies']) && count($options['companies']) > 0) {
            $queryBuilder->andwhere("Relocation.hr_company_id IN ({companies:array})", [
                'companies' => $options['companies'],
            ]);
        }

        if (isset($options['country_origin_ids']) && is_array($options['country_origin_ids']) && count($options['country_origin_ids']) > 0) {
            $queryBuilder->andwhere("Assignment.home_country_id IN ({country_origin_ids:array})", [
                'country_origin_ids' => $options['country_origin_ids'],
            ]);
        }

        if (isset($options['country_destination_ids']) && is_array($options['country_destination_ids']) && count($options['country_destination_ids']) > 0) {
            $queryBuilder->andwhere("Assignment.destination_country_id IN ({country_destination_ids:array})", [
                'country_destination_ids' => $options['country_destination_ids'],
            ]);
        }

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->andwhere("RelocationServiceCompany.status IN ({statuses:array})", [
                'statuses' => $options['statuses'],
            ]);
        }

        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners']) > 0) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataOwner.object_uuid = RelocationServiceCompany.uuid', 'DataOwner');
            $queryBuilder->andwhere("DataOwner.user_profile_uuid IN ({owners:array}) AND DataOwner.member_type_id = :member_type_owner:", [
                'owners' => $options['owners'],
                'member_type_owner' => DataUserMemberExt::MEMBER_TYPE_OWNER,
            ]);
        }

        if (isset($options['bookers']) && is_array($options['bookers']) && count($options['bookers'])) {
            $queryBuilder->andwhere("Assignment.booker_company_id IN ({bookers:array} )",
                [
                    'bookers' => $options['bookers']
                ]
            );
        }

        if (isset($options['owner_uuid']) && Helpers::__isValidUuid($options['owner_uuid']) && $options['owner_uuid'] != '' && ModuleModel::$user_profile->isAdminOrManager() == true) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwnerSearch.object_uuid = RelocationServiceCompany.uuid', 'DataUserOwnerSearch');
            $queryBuilder->andwhere("DataUserOwnerSearch.user_profile_uuid = :user_profile_uuid: and DataUserOwnerSearch.member_type_id = :_member_type_owner:", [
                "user_profile_uuid" => $options['owner_uuid'],
                "_member_type_owner" => DataUserMemberExt::MEMBER_TYPE_OWNER
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("RelocationServiceCompany.name LIKE :query: OR RelocationServiceCompany.number LIKE :query: OR ServiceCompany.name LIKE :query: OR Employee.firstname LIKE :query: OR Employee.lastname LIKE :query:
             OR CONCAT(Employee.firstname,' ',Employee.lastname) LIKE :query: OR HrCompany.name LIKE :query:  OR BookerCompany.name LIKE :query:
             OR DestinationCountry.name LIKE :query: OR ServiceProviderCompany.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['relocation_id']) && is_numeric($options['relocation_id']) && $options['relocation_id'] > 0) {
            $queryBuilder->andwhere("Relocation.id = :relocation_id:", [
                'relocation_id' => $options['relocation_id'],
            ]);
        }

        if (isset($options['relocation_uuid']) && ($options['relocation_uuid']) && Helpers::__isValidUuid($options['relocation_uuid'])) {
            $queryBuilder->andwhere("Relocation.uuid = :relocation_uuid:", [
                'relocation_uuid' => $options['relocation_uuid'],
            ]);
        }

        if (isset($options['has_not_relocation_cancel']) && is_bool($options['has_not_relocation_cancel']) && $options['has_not_relocation_cancel'] == true) {
            $queryBuilder->andwhere("Relocation.status <> :relocation_cancel_status:", [
                'relocation_cancel_status' => Relocation::STATUS_CANCELED
            ]);
        }

        if (isset($options['service_in_progress']) && is_bool($options['service_in_progress']) && $options['service_in_progress'] == true) {
            $queryBuilder->andwhere("RelocationServiceCompany.progress = :progress: and RelocationServiceCompany.progress_value < 100", [
                'progress' => RelocationServiceCompany::STATUS_IN_PROCESS
            ]);
        }

        if (isset($options['filter_config_id'])) {
            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'Relocation.hr_company_id',
                'OWNER_ARRAY_TEXT' => 'Owner.user_profile_id',
                'REPORTER_ARRAY_TEXT' => 'Reporter.user_profile_id',
                'BOOKER_ARRAY_TEXT' => 'Assignment.booker_company_id',
                'ORIGIN_ARRAY_TEXT' => 'AssignmentBasic.home_country_id',
                'DESTINATION_ARRAY_TEXT' => 'AssignmentDestination.destination_country_id',
                'STATUS_ARRAY_TEXT' => 'RelocationServiceCompany.progress',
                'START_DATE_TEXT' => 'ServiceEventValue.value',
                'END_DATE_TEXT' => 'ServiceEventValue.value',
                'CREATED_ON_TEXT' => 'RelocationServiceCompany.created_at',
                'PREFIX_DATA_OWNER_TYPE' => 'Owner.member_type_id',
                'PREFIX_DATA_REPORTER_TYPE' => 'Reporter.member_type_id',
                'PREFIX_OBJECT_UUID' => 'RelocationServiceCompany.uuid',
                'SERVICE_ARRAY_TEXT' => 'RelocationServiceCompany.service_company_id',
                'PROVIDER_ARRAY_TEXT' => 'ServiceProviderCompany.id'
            ];

            $dataType = [
                'START_DATE_TEXT' => 'int',
                'END_DATE_TEXT' => 'int',
                'CREATED_ON_TEXT' => 'date',
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : (
        isset($options['length']) && is_numeric($options['length']) && $options['length'] > 0 ? $options['length'] : self::LIMIT_PER_PAGE);

        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        $queryBuilder->groupBy('RelocationServiceCompany.id');

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $servicesData = [];

            if (isset($options['is_count_all']) && $options['is_count_all'] == true) {
                goto end_of_return;
            }

            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $serviceItem) {
                    
                    $item = $serviceItem->toArray();
                    $item['selected'] = false;
                    $item['chosen'] = false;
                    $item['relocation_cancelled'] =  $item['relocation_cancelled'] == 0 ? false : true;
                    $item['service_started_at'] =  $item['service_started_at'] != '' && $item['service_started_at'] != null ?  date('Y-m-d H:i:s', $item['service_started_at']) : '';
                    $item['service_ended_at'] =  $item['service_ended_at'] != '' && $item['service_ended_at'] != null ?  date('Y-m-d H:i:s', $item['service_ended_at']) : '';
                    $item['id'] = intval($item['id']);
                    $item['status'] = intval($item['status']);
                    $item['progress_value'] = intval($item['progress_value']);
                    $item['is_visit_allowed'] = intval($item['is_visit_allowed']);
                    $item['relocation_id'] = intval($item['relocation_id']);
                    $item['service_company_id'] = intval($item['service_company_id']);
                    $item['workflow_applied'] = intval($item['workflow_applied']);
                    $item['service_provider_company_id'] = intval($item['service_provider_company_id']);
                    $item['owner'] = [
                        'uuid' => $item['owner_uuid'],
                        'id' => $item['owner_id'],
                        'external_hris_id' => $item['owner_external_hris_id'],
                        'firstname' => $item['owner_firstname'],
                        'lastname' => $item['owner_lastname'],
                        'nickname' => $item['owner_nickname'],
                        'jobtitle' => $item['owner_jobtitle'],
                        'phonework' => $item['owner_phonework'],
                        'workemail' => $item['owner_workemail'],
                        'name' => $item['owner_name'],
                    ];

                    $entity_progress = EntityProgress::findFirst([
                        "conditions" => "object_uuid = :object_uuid:",
                        "bind" => [
                            "object_uuid" => $item['uuid']
                        ],
                        "order" => "created_at DESC",
                    ]);


                    $item['progress'] = $entity_progress ?  intval($entity_progress->getValue()) : 0;
                    $item['progress_status'] = $entity_progress ?  intval($entity_progress->getStatus()) : self::STATUS_NOT_STARTED;

                    $servicesData[] = $item;
                }

            }
            end_of_return:
            return [
                'success' => true,
                '$start' => $start,
                '$limit' => $limit,
                'before' => $pagination->before,
                'data' => $servicesData,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'page' => $page,
                'sql' => $queryBuilder->getQuery()->getSql(),
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
     * @param $services
     * @return array
     */
    public static function checkServicesNameExisted($relocation, $services = [], $fieldName = 'name'){
        $listServices = [];
        $flag = true;
        foreach ($services as $service){
            $checkName = self::find([
                "conditions" => "relocation_id = :id:  and name = :name: and status = :status:",
                "bind" => [
                    "id" => $relocation->getId(),
                    "name" => isset($service[$fieldName]) ? $service[$fieldName] : null,
                    "status" => self::STATUS_ACTIVE
                ]
            ]);

            if (count($checkName) > 0) {
                $flag = false;
                $service['is_duplicate_name'] = true;
            }else{
                $service['is_duplicate_name'] = false;
            }

            //Check duplicate service name in list services
            $listNames = array_column($services, $fieldName);
            $listDuplicates = array_filter(array_count_values($listNames), function($v) { return $v > 1; });
            if(isset($listDuplicates[$service[$fieldName]])){
                $flag = false;
                $service['is_duplicate_name'] = true;
            }
            $listServices[] = $service;
        }

        return [
            'success' => $flag,
            'services' => $listServices,
        ];
    }

    /**
     * start add Workflow to Background Service
     * @return array
     */
    public function startSyncTaskOwner($owner)
    {
        $request = new Request();
        $beanQueue = RelodayQueue::__getQueueRelocationService();
        $dataArray = [
            'action' => BackgroundActionHelpers::METHOD_SYNC_OWNER,
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'owner_profile_uuid' => $owner->getUuid(),
            'uuid' => $this->getUuid(),
            'companyUuid' => ModuleModel::$company->getUuid(),
            'ip' => $request->getClientAddress(),
            'language' => ModuleModel::$system_language,
            'appUrl' => ModuleModel::$app->getFrontendUrl(),
        ];
        return $beanQueue->addQueue($dataArray);
    }

    /**
     * start add Workflow to Background Service
     * @return array
     */
    public function startRemoveTaskOwner()
    {
        $request = new Request();
        $beanQueue = RelodayQueue::__getQueueRelocationService();
        $dataArray = [
            'action' => BackgroundActionHelpers::METHOD_REMOVE_OWNER,
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'uuid' => $this->getUuid(),
            'companyUuid' => ModuleModel::$company->getUuid(),
            'ip' => $request->getClientAddress(),
            'language' => ModuleModel::$system_language,
            'appUrl' => ModuleModel::$app->getFrontendUrl(),
        ];
        return $beanQueue->addQueue($dataArray);
    }


    /**
     * @param $options
     * @return array
     */
    public static function __getRecentRelocationServices($options = []){
        $bindArray = [];
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : (
        isset($options['length']) && is_numeric($options['length']) && $options['length'] > 0 ? $options['length'] : self::LIMIT_PER_PAGE);
        $di = \Phalcon\DI::getDefault();
        $db = $di['db'];
        $sql = "SELECT h.* FROM history as h
           JOIN
            (
                select h1.object_uuid as object_uuid, h1.user_profile_uuid as user_profile_uuid, max(h1.created_at) as max_created_at
                from history as h1 where h1.user_profile_uuid = '". ModuleModel::$user_profile->getUuid() ."' 
                GROUP BY h1.object_uuid          
            ) as h2
            on (h2.object_uuid, h2.max_created_at) = (h.object_uuid, h.created_at)
            WHERE EXISTS
              (select rsc.uuid, rsc.id  
                from relocation_service_company as rsc
                where rsc.uuid = h.object_uuid 
                GROUP BY h.object_uuid)  
            order by h.created_at DESC limit $limit";

        $data = $db->query($sql);
        $data->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $results = $data->fetchAll();
        $array = [];
        foreach ($results as $history) {
            $rsc = RelocationServiceCompany::findFirstByUuid($history['object_uuid']);
            $item = $rsc->toArray();
            $serviceCompany = $rsc->getServiceCompany();
            $service = $serviceCompany->getService();
            $relocation = $rsc->getRelocation();
            $employee = $relocation->getEmployee();
            $item['number'] = $rsc->getNumber();
            $item['employee_name'] = $employee->getFullname();
            $item['employee_uuid'] = $employee->getUuid();
            $item['employee_id'] = $employee->getId();
            $item['employee_email'] = $employee->getWorkemail();
            $item['company_name'] = $relocation->getHrCompany()->getName();
            $data_owner = $rsc->getDataOwner();
            $item['owner_name'] = $data_owner ? $data_owner->getFullname() : '';
            $item['owner_uuid'] = $data_owner ? $data_owner->getUuid() : '';
            $item['service'] = $service ? $service->toArray() : [];

            $entity_progress = EntityProgress::findFirst([
                "conditions" => "object_uuid = :object_uuid:",
                "bind" => [
                    "object_uuid" => $item['uuid']
                ],
                "order" => "created_at DESC",
            ]);


            $item['progress'] = $entity_progress ?  intval($entity_progress->getValue()) : 0;
            $item['progress_status'] = $entity_progress ?  intval($entity_progress->getStatus()) : self::STATUS_NOT_STARTED;
            $item['progress_value'] = intval($item['progress_value']);


            $array[] = $item;
        }

        return [
            'success' => true,
            'data' => $array,
        ];

    }
}
