<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Exception;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\DependantExt;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Models\ObjectMapExt;
use Reloday\Application\Models\RelocationExt;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\AssignmentBasic;
use Reloday\Gms\Models\AssignmentDestination;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectMap;
use Reloday\Gms\Models\Company;

use \Phalcon\Mvc\Model\Transaction\Failed as TransationFailed;
use \Phalcon\Mvc\Model\Transaction\Exception as TransactionException;
use \Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

use Phalcon\Security\Random;
use Reloday\Application\Lib\Helpers;

use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Regex as RegexValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\Date as DateValidator;

class Assignment extends \Reloday\Application\Models\AssignmentExt
{
    const LIMIT_PER_PAGE = 20;

    static $searchSizes = ["small", "medium", "large", "xlarge"];

    public $error_message;

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();

        $this->hasOne('id', 'Reloday\Gms\Models\AssignmentBasic', 'id', [
            'alias' => 'AssignmentBasic',
        ]);
        $this->hasOne('id', 'Reloday\Gms\Models\AssignmentDestination', 'id', [
            'alias' => 'AssignmentDestination',
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\AssignmentCompanyData', 'assignment_id', [
            'alias' => 'AssignmentCompanyDataItems'
        ]);


        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
        ]);
        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee',
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\Relocation', 'assignment_id', [
            'alias' => 'Relocation',
            'params' => [
                'conditions' => "Reloday\Gms\Models\Relocation.creator_company_id = :current_dsp_company_id: AND Reloday\Gms\Models\Relocation.active != :relocation_deleted:",
                'bind' => [
                    'relocation_deleted' => Relocation::STATUS_DELETED,
                    'current_dsp_company_id' => ModuleModel::$company->getId()
                ]
            ]
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\Relocation', 'assignment_id', [
            'alias' => 'Relocations',
        ]);

        $this->belongsTo('assignment_type_ud', 'Reloday\Gms\Models\AssignmentType', 'id', [
            'alias' => 'AssignmentType',
        ]);

        $this->hasManyToMany(
            'uuid', 'Reloday\Gms\Models\DataUserMember',
            'object_uuid', 'user_profile_uuid',
            'Reloday\Gms\Models\UserProfile', 'uuid',
            [
                'alias' => 'Members',
                'params' => [
                    'distinct' => true
                ]
            ]
        );


        $this->belongsTo('home_country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'HomeCountry',
            'cache' => [
                'key' => 'COUNTRY_' . $this->getHomeCountryId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);
        $this->belongsTo('destination_country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'DestinationCountry',
            'cache' => [
                'key' => 'COUNTRY_' . $this->getDestinationCountryId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);

        /** get owners */
        $this->hasManyToMany(
            'uuid', 'Reloday\Gms\Models\DataUserMember',
            'object_uuid', 'user_profile_uuid',
            'Reloday\Gms\Models\UserProfile', 'uuid',
            [
                'alias' => 'Owners',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = :type_owner: AND Reloday\Gms\Models\DataUserMember.company_id = :company_id:',
                    'bind' => [
                        'type_owner' => DataUserMember::MEMBER_TYPE_OWNER,
                        'company_id' => ModuleModel::$company->getId()
                    ],
                    'order' => 'Reloday\Gms\Models\DataUserMember.created_at DESC',
                    'limit' => 1,
                ],
                'cache' => [
                    'key' => CacheHelper::getCacheNameObjectOwners($this->getUuid()),
                    'lifetime' => 86400
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
                    'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = :type_reporter: AND Reloday\Gms\Models\DataUserMember.company_id = :company_id:',
                    'bind' => [
                        'type_reporter' => DataUserMember::MEMBER_TYPE_REPORTER,
                        'company_id' => ModuleModel::$company->getId()
                    ],
                    'order' => 'Reloday\Gms\Models\DataUserMember.created_at DESC',
                ],

            ]
        );

        $this->hasManyToMany('id', 'Reloday\Gms\Models\AssignmentDependant', 'assignment_id', 'dependant_id',
            'Reloday\Gms\Models\Dependant', 'id', [
                'alias' => 'Dependants',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\Dependant.status = :status_active:',
                    'bind' => [
                        'status_active' => DependantExt::STATUS_ACTIVE
                    ]
                ]
            ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\AssignmentInContract', 'assignment_id', 'contract_id', 'Reloday\Gms\Models\Contract', 'id', [
            'alias' => 'AllContracts',
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\AssignmentInContract', 'assignment_id', 'contract_id', 'Reloday\Gms\Models\Contract', 'id', [
            'alias' => 'ActiveContracts',
            'params' => [
                'conditions' => 'Reloday\Gms\Models\Contract.status = :contract_active: AND Reloday\Gms\Models\Contract.to_company_id = :current_dsp_id:',
                'bind' => [
                    'contract_active' => Contract::STATUS_ACTIVATED,
                    'current_dsp_id' => ModuleModel::$company->getId()
                ],
                'order' => 'Reloday\Gms\Models\AssignmentInContract.created_at DESC',
                'limit' => 1,
            ],
        ]);

        $this->belongsTo('workflow_id', 'Reloday\Gms\Models\Workflow', 'id', [
            'alias' => 'Workflow',
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\AssignmentRequest', 'assignment_id', [
            'alias' => 'ActiveAssignmentRequests',
            'params' => [
                'conditions' => 'company_id = :current_dsp_id: and status = :request_accepted:',
                'bind' => [
                    'current_dsp_id' => ModuleModel::$company->getId(),
                    'request_accepted' => AssignmentRequest::STATUS_ACCEPTED
                ],
                'limit' => 1,
                'distinct' => true
            ]
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\AssignmentCompanyData', 'assignment_id', [
            'alias' => 'AssignmentCompanyCustomFields',
        ]);
    }

    /**
     * @return bool
     */
    public function beforeValidation()
    {
        return parent::beforeValidation();
    }

    /**
     * get object of owner
     */
    public function getAssignmentRequest()
    {
        return $this->getActiveAssignmentRequests()->getFirst();
    }

    /**
     * Load list init
     * @param string $search_value
     * @param string $conditions
     * @param array $options
     * @return array
     */
    public static function loadList($search_value = '', $conditions = '', $options = [])
    {
        $result = Contract::__getAllOfCurrentGMS();
        if ($result['success'] == true) {
            $contracts = $result['data'];
            $assignments_array = [];
            if ($contracts && count($contracts)) {
                $contract_ids = [];
                foreach ($contracts as $contract) {
                    $contract_ids[] = $contract->getId();
                }
                if ($search_value) {
                    $bind_array = [
                        'contract_ids' => $contract_ids,
                        'search_value' => '%' . $search_value . '%',
                        'archived_yes' => Assignment::ARCHIVED_YES
                    ];
                } else {
                    $bind_array = [
                        'contract_ids' => $contract_ids,
                        'archived_yes' => Assignment::ARCHIVED_YES
                    ];
                }

                // Make query condition
                $criteria = [
                    'conditions' =>
                        'archived <> :archived_yes: AND
                        contract_id IN ({contract_ids:array}) ' . ($search_value ? " AND name LIKE :search_value: " : "" . ($conditions != '' ? " AND " . $conditions : "")),
                    'bind' => $bind_array
                ];

                // If we have another options
                if (!empty($options)) {
                    $criteria = array_merge($criteria, $options);
                }

                $assignments = self::find($criteria);

                if (count($assignments)) {
                    foreach ($assignments as $item) {
                        $assignments_array[$item->getId()] = $item->toArray();
                        $assignments_array[$item->getId()]['can_create_relocation'] = $item->canCreateRelocation();
                        $assignments_array[$item->getId()]['company_name'] = $item->getCompany()->getName();
                        $assignments_array[$item->getId()]['employee_uuid'] = $item->getEmployee()->getUuid();
                        $assignments_array[$item->getId()]['employee_name'] = $item->getEmployee()->getFirstname() . " " . $item->getEmployee()->getLastname();
                        $assignments_array[$item->getId()]['home_country'] = ($item->getAssignmentBasic()
                            && $item->getAssignmentBasic()->getHomeCountry()) ? $item->getAssignmentBasic()->getHomeCountry()->getName() : "";
                        $assignments_array[$item->getId()]['destination_country'] = ($item->getAssignmentDestination()
                            && $item->getAssignmentDestination()->getDestinationCountry()) ? $item->getAssignmentDestination()->getDestinationCountry()->getName() : "";
                    }

                    return [
                        'success' => true,
                        'data' => array_values($assignments_array)
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'
                    ];
                }

            } else {
                return [
                    'success' => false,
                    'message' => 'CONTRACT_NOT_FOUND_TEXT'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'ERROR_CONTRACT_NOT_FOUND_TEXT',
                'detail' => $result,
            ];
        }
    }

    /**
     * @return array
     */
    public static function loadFromUserProfileUuid($user_profile_uuid)
    {
        if (!ModuleModel::$company->getCompanyTypeId() == Company::TYPE_GMS) {
            return [
                'success' => false,
                'message' => 'COMPANY_TYPE_DIFFERENT_TEXT'
            ];
        } else {
            $assignments_array = [];
            $di = \Phalcon\DI::getDefault();
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
            $queryBuilder->where('Contract.to_company_id = ' . ModuleModel::$company->getId());
            $queryBuilder->andwhere('Contract.status = ' . Contract::STATUS_ACTIVATED);
            $queryBuilder->andwhere('Assignment.archived <> ' . self::ARCHIVED_YES);
            $queryBuilder->andwhere("DataUserMember.user_profile_uuid = '" . $user_profile_uuid . "'");
            $assignments = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));

            if (count($assignments)) {
                foreach ($assignments as $assigment) {
                    $assignments_array[$assigment->getId()] = $assigment->toArray();
                    $assignments_array[$assigment->getId()]['company_name'] = $assigment->getCompany()->getName();
                    $assignments_array[$assigment->getId()]['employee_uuid'] = $assigment->getEmployee()->getUuid();

                    $assignments_array[$assigment->getId()]['employee_name'] = $assigment->getEmployee()->getFirstname() . " " . $assigment->getEmployee()->getLastname();
                    $assignments_array[$assigment->getId()]['employee_uuid'] = $assigment->getEmployee()->getUuid();
                    //$assignments_array[$assigment->getId()]['type_name'] = ($assigment->getAssignmentType()) ? $assigment->getAssignmentType()->getName() : null;
                }
            }
            return [
                'success' => true,
                'data' => $assignments_array,
            ];
        }


        $di = \Phalcon\DI::getDefault();

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('Reloday\Gms\Models\Assignment', 'Assignment');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = ' . $company->getId());
        $queryBuilder->andwhere('Policy.status <> ' . self::STATUS_ARCHIVED);
        $policies = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));


    }

    /**
     * @param array $custom : app_id, profile_id, created_by
     * @return array|CompanyExt|Company
     */
    public function __save($custom = [])
    {
        $model = $this;
        $data = [];
        //@TODO check assignment is ACCESSIBLE FOR UPDATE/CREATE

        if (!($model->getId() > 0)) {
            $data['archived'] = self::ARCHIVED_NO;
            $data['approval_status'] = self::APPROVAL_STATUS_DEFAULT;
            if (!isset($data['status'])) {
                $data['status'] = self::STATUS_INACTIVATED;
            }
            $data['cost_projection_needed'] = self::COST_PROJECTION_NEED_YES;
            $data['us_green_card_holder'] = self::US_GREENCARD_NO;
        }
        /**
         * check rules to updates status
         */

        /** check status */
        $status = (isset($custom['status']) ? $custom['status'] : (isset($data['status']) ? $data['status'] : $model->getStatus()));
        if (($status == null || empty($status) || $status == self::STATUS_INACTIVATED) && !($model->getId() > 0)) {
            //AUTO ACTIVATED
            $status = self::STATUS_ACTIVE;
        }
        if ($model->statusCheckBeforeSave($status) == false) {
            $result = [
                'success' => false,
                'details' => $model->getErrorMessage(),
                'status' => $status,
                'message' => 'ASSIGNMENT_STATUS_NOT_VALIDATE_TEXT',
            ];
            return $result;
        } else {
            $model->set('status', $status);
        }

        /** check approval status */

        $approval_status = Helpers::__getRequestValueWithCustom('approval_status', $custom);
        $approval_status = Helpers::__coalesce($approval_status, $model->getApprovalStatus());

        /** default == approved */
        if (($approval_status == null ||
                $approval_status == self::APPROVAL_STATUS_DEFAULT)
            && !($model->getId() > 0)) {
            $approval_status = self::APPROVAL_STATUS_APPROVED;
        }

        if ($model->getId() > 0) {
            if ($model->approvalStatusCheckBeforeSave($approval_status, $model->getApprovalStatus()) == false) {
                $result = [
                    'success' => false,
                    'message' => 'ASSIGNMENT_APPROVAL_STATUS_NOT_VALIDATE_TEXT',
                ];
                return $result;
            } else {
                $model->set('approval_status', $approval_status);
            }
        } else {
            //if create approval
            $approval_status = self::APPROVAL_STATUS_APPROVED;
            $model->set('approval_status', $approval_status);
        }

        //EmployeeId
        $company_id = $model->getCompanyId();
        if (isset($data['employee'])) {
            $employee = $data['employee'];
            $employee_id = $data['employee']['id'];
            $company_id = $data['employee']['company_id'];
        } elseif (isset($custom['employee_id'])) {
            $employee_id = $custom['employee_id'];
        } elseif (isset($data['employee_id'])) {
            $employee_id = $data['employee_id'];
        } elseif (isset($custom['employee'])) {
            $employee = $custom['employee'];
            $employee_id = $custom['employee']['id'];
            $company_id = $custom['employee']['company_id'];
        } else {
            $employee_id = $model->getEmployeeId();
        }

        if (!isset($employee) && $employee_id > 0) {
            $employee = Employee::findFirstById($employee_id);
            if ($employee) {
                $company_id = $employee->getCompanyId();
            }
        }


        if ($company_id == null) {
            $company_id = (isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId()));
        }

        if ($company_id) {
            $contract = Contract::__findContractOfCompany($company_id);
        }
        $random = new Random;
        if ($model->getUuid() == '' || $model->getUuid() == null) {
            $uuid = isset($custom['uuid']) && $custom['uuid'] != '' ? $custom['uuid'] : (isset($data['uuid']) && $data['uuid'] != '' ? $data['uuid'] : $random->uuid());
            $model->setUuid($uuid);
        }

        //$data['policy_id'] = 0;
        $policy_id = isset($custom['policy_id']) && is_numeric($custom['policy_id']) && $custom['policy_id'] > 0 ? $custom['policy_id'] :
            (isset($data['policy_id']) && is_numeric($data['policy_id']) && $data['policy_id'] > 0 ? $data['policy_id'] : null);

        if (!is_null($policy_id) && $policy_id > 0) {
            $model->setPolicyId($policy_id);
        }

        $assignment_type_id = isset($custom['assignment_type_id']) ? $custom['assignment_type_id'] : (isset($data['assignment_type_id']) ? $data['assignment_type_id'] : null);
        if ($assignment_type_id == null) {
            $assignment_type_id = isset($custom['assignment_type']) ? $custom['assignment_type'] : (isset($data['assignment_type']) ? $data['assignment_type'] : $model->get('assignment_type_id'));
        }
        //if ($assignment_type_id > 0) {$model->setAssignmentTypeId($assignment_type_id);

        $model->setName(isset($custom['name']) ? $custom['name'] : (isset($data['name']) ? $data['name'] : $model->getName()));
        $model->setEmployeeId(isset($custom['employee_id']) ? $custom['employee_id'] : (isset($data['employee_id']) ? $data['employee_id'] : $employee_id));
        $model->setCompanyId(isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $company_id));
        $model->setBookerCompanyId(isset($custom['booker_company_id']) ? $custom['booker_company_id'] : (isset($data['booker_company_id']) ? $data['booker_company_id'] : $model->getBookerCompanyId()));
        $model->setOrderNumber(isset($custom['order_number']) ? $custom['order_number'] : (isset($data['order_number']) ? $data['order_number'] : $model->getOrderNumber()));
        $model->setHrAssignmentOwnerId(isset($custom['hr_assignment_owner_id']) ? $custom['hr_assignment_owner_id'] : (isset($data['hr_assignment_owner_id']) ? $data['hr_assignment_owner_id'] : $model->getHrAssignmentOwnerId()));


        if (isset($contract) && $contract) {
            $model->set('contract_id', $contract->getId());
        }

        /** reference */
        if ($model->getReference() == '') {
            $reference = $model->generateNumber();
            $model->setReference($reference);
        }

        if ($model->getName() == '') {
            if (!isset($reference)) {
                $reference = $model->generateNumber();
            }
            $model->setName($reference);
        }

        $effective_start_date = isset($custom['effective_start_date']) ? $custom['effective_start_date'] : (isset($data['effective_start_date']) ? $data['effective_start_date'] : $model->get('effective_start_date'));
        if ($effective_start_date != '' && !is_null($effective_start_date)) {
            $model->set('effective_start_date', $effective_start_date);
        } else {
            if (isset($custom['effective_start_date']) || isset($data['effective_start_date'])) {
                $model->set('effective_start_date', null);
            }
        }

        $end_date = isset($custom['end_date']) ? $custom['end_date'] : (isset($data['end_date']) ? $data['end_date'] : $model->get('end_date'));
        if ($end_date != '') {
            $model->set('end_date', $end_date);
        } else {
            if (isset($custom['end_date']) || isset($data['end_date'])) {
                $model->set('end_date', null);
            }
        }

        $estimated_start_date = isset($custom['estimated_start_date']) ? $custom['estimated_start_date'] :
            (isset($data['estimated_start_date']) ? $data['estimated_start_date'] : $model->get('estimated_start_date'));
        if ($estimated_start_date != '') {
            $model->set('estimated_start_date', $estimated_start_date);
        } else {
            if (isset($custom['estimated_start_date']) || isset($data['estimated_start_date'])) {
                $model->set('estimated_start_date', null);
            }
        }

        $estimated_end_date = isset($custom['estimated_end_date']) ? $custom['estimated_end_date'] : (isset($data['estimated_end_date']) ? $data['estimated_end_date'] : $model->get('estimated_end_date'));
        if ($estimated_end_date != '') {
            $model->set('estimated_end_date', $estimated_end_date);
        } else {
            if (isset($custom['estimated_end_date']) || isset($data['estimated_end_date'])) {
                $model->set('estimated_end_date', null);
            }
        }

        $model->set('cost_projection_needed',
            isset($custom['cost_projection_needed']) ? $custom['cost_projection_needed'] : (isset($data['cost_projection_needed']) ? $data['cost_projection_needed'] : $model->get('cost_projection_needed'))
        );

        $model->set('archived',
            isset($custom['archived']) ? $custom['archived'] : (isset($data['archived']) ? $data['archived'] : $model->get('archived'))
        );

        $model->set('archived',
            isset($custom['archived']) ? $custom['archived'] : (isset($data['archived']) ? $data['archived'] : $model->get('archived'))
        );

        $customDestination = isset($custom['destination']) ? $custom['destination'] : "";
        $customBasic = isset($custom['basic']) ? $custom['basic'] : "";

        $destination_city = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_city', $customDestination), $model->getDestinationCity());
        if ($destination_city != '') {
            $model->set('destination_city', $destination_city);
        }
        $home_city = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_city', $customBasic), $model->getHomeCity());
        if ($home_city != '') {
            $model->set('home_city', $home_city);
        }

        $home_country_id = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_country_id', $customBasic), $model->getHomeCountryId());
        if ($home_country_id > 0) {
            $model->set('home_country_id', $home_country_id);
        }

        $destination_country_id = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_country_id', $customDestination), $model->getDestinationCountryId());
        if ($destination_country_id > 0) {
            $model->set('destination_country_id', $destination_country_id);
        }

        /** load object basic and destination */
        if ($model->getId() == null) {
            $assginmentBasic = new AssignmentBasic();
            $assginmentDestination = new AssignmentDestination();
        } else {
            $assginmentBasic = $model->getAssignmentBasic();
            $assginmentDestination = $model->getAssignmentDestination();

            if (!$assginmentBasic) $assginmentBasic = new AssignmentBasic();
            if (!$assginmentDestination) $assginmentDestination = new AssignmentDestination();
        }

        $model->setData($custom);
        $assginmentDestination->setData($customDestination);
        $assginmentBasic->setData($customBasic);


        try {

            $transactionManager = new TransactionManager();
            $transactionDb = $transactionManager->get();
            $model->setTransaction($transactionDb);
            $assginmentDestination->setTransaction($transactionDb);
            $assginmentBasic->setTransaction($transactionDb);

            $resultAssignment = $model->__quickSave();

            if ($resultAssignment['success'] == false) {
                if ($transactionManager->has()) {
                    $transactionDb->rollback('Can not Save Assignment - ' . reset($resultAssignment['detail']));
                }
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'method' => __CLASS__ . ":" . __FUNCTION__,
                    'success' => false,
                    'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }

            //save new reference
            $assginmentBasic->setId($model->getId());
            $resultBasic = $assginmentBasic->__quickSave();
            if ($resultBasic['success'] == false) {
                $transactionDb->rollback('SAVE_ASSIGNMENT_BASIC_FAIL_TEXT : ' . $resultBasic['detail']);
                return $resultBasic;
            }

            $assginmentDestination->setId($model->getId());
            $resultDestination = $assginmentDestination->__quickSave();
            if ($resultDestination['success'] == false) {
                $transactionDb->rollback('SAVE_ASSIGNMENT_DESTINATION_FAIL_TEXT : ' . $resultDestination['detail']);
                return $resultDestination;
            }

            if ($assginmentDestination->hasUpdated('destination_country_id') || $assginmentDestination->hasUpdated('destination_city')) {
                if ($assginmentDestination->hasUpdated('destination_country_id')) {
                    $model->setDestinationCountryId($assginmentDestination->getDestinationCountryId());
                }
                if ($assginmentDestination->hasUpdated('destination_city')) {
                    $model->setDestinationCity($assginmentDestination->getDestinationCity());
                }
            }

            if ($assginmentBasic->hasUpdated('home_country_id') || $assginmentBasic->hasUpdated('home_city')) {
                if ($this->hasUpdated('home_country_id')) {
                    $model->setHomeCountryId($assginmentBasic->getHomeCountryId());
                }
                if ($assginmentBasic->hasUpdated('home_city')) {
                    $model->setHomeCity($this->getHomeCity());
                }
            }

            $resultAssignment = $model->__quickUpdate();
            if ($resultAssignment['success'] == false) {
                if ($transactionManager->has()) {
                    $transactionDb->rollback('Can not Save Assignment - ' . reset($resultAssignment['detail']));
                }
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'method' => __CLASS__ . ":" . __FUNCTION__,
                    'success' => false,
                    'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }

            if ($model->hasChanged('home_country_id') || $model->hasChanged('home_city') || $model->hasChanged('destination_country_id') || $model->hasChanged('destination_city')) {

            } else {

            }


            $transactionDb->commit();
            return $model;
        } catch (TransactionException $e) {
            $result = [
                'method' => __CLASS__ . ":" . __FUNCTION__,
                'success' => false,
                'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (\PDOException $e) {
            $result = [
                'method' => __CLASS__ . ":" . __FUNCTION__,
                'success' => false,
                'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'method' => __CLASS__ . ":" . __FUNCTION__,
                'success' => false,
                'message' => 'SAVE_ASSIGNMENT_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * [newReference description]
     * @return [type] [description]
     */
    public function createNewReference()
    {
        if ($this->getReference() == '') {
            $this->setReference($this->generateNumber());
        }
    }

    /**
     * [getDetail description]
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public static function getDetail($id)
    {
        $model = self::findFirstById($id);
        if ($model && $model instanceof self) {
            if ($model->belongsToGms() == true) {
                $assignment = $model->toArray();
                $basic = $model->getAssignmentBasic();
                if ($basic) {
                    $assignment['basic'] = $basic->toArray();
                    $assignment['basic']['home_country'] = ($basic->getHomeCountry()) ? $basic->getHomeCountry()->getName() : null;
                }
                $destination = $model->getAssignmentDestination();
                if ($destination) {
                    $assignment['destination'] = $destination->toArray();
                    $assignmentArray['destination']['office'] = ($destination->getOffice()) ? $destination->getOffice()->getName() : null;
                    $assignment['destination']['destination_country'] = ($destination->getDestinationCountry()) ? $destination->getDestinationCountry()->getName() : null;
                }
                $employee = $model->getEmployee();
                $assignment['employee'] = $employee->toArray();
                $assignment['employee']['avatar'] = $employee->getAvatar();
                $assignment['company'] = $model->getCompany();
                $assignment['attachments'] = MediaAttachment::__get_attachments_from_uuid($model->getUuid());

                $assignment['can_create_relocation'] = ($model->canCreateRelocation());
                $assignment['can_approve'] = ($model->canApprove());
                $assignment['has_relocation'] = ($model->hasRelocation());

                $policy = $model->getPolicy();
                if ($policy) {
                    $assignment['policy'] = $policy;
                } else {
                    $assignment['policy'] = null;
                }
                //$assignment['assignment_type'] = $model->getAssignmentType();
                //$assignment['type_name'] = ($model->getAssignmentType()) ? $model->getAssignmentType()->getName() : null;

                return $assignment;
            } else {
                return false;
            }
        }
    }

    /**
     * @return mixed
     */
    public function getInfoDetailInArray()
    {
        $relocation = $this->getRelocation();
        $relocationId = $relocation && $relocation->getId() ? $relocation->getId() : 0;

        $lang = ModuleModel::$language;

        $assignmentArray = $this->toArray();
        $basic = $this->getAssignmentBasic();
        if ($basic) {
            $assignmentArray['basic'] = $basic->toArray();
            $assignmentArray['basic']['home_country'] = ($basic->getHomeCountry()) ? $basic->getHomeCountry()->getName() : null;
            $assignmentArray['basic']['office'] = ($basic->getOffice()) ? $basic->getOffice()->getName() : null;
            $assignmentArray['home_country'] = ($basic->getHomeCountry()) ? $basic->getHomeCountry()->toArray() : null;
            if ($basic->getHomeCityGeonameid()) {
                $city = City::findFirstByGeonameIdCache($basic->getHomeCityGeonameid());
                if ($city instanceof City) {
                    $assignmentArray['basic']['host_home_city'] = $city->getName();
                }
            }

            if ($assignmentArray['home_country']) {
                $assignmentArray['home_country']['name'] = ($basic->getHomeCountry()->getName()) ? $basic->getHomeCountry()->getName() : null;
            }

            if ($assignmentArray['home_city'] == '') {
                $assignmentArray['home_city'] = $basic->getHomeCity();
            }
        }
        $destination = $this->getAssignmentDestination();
        if ($destination) {
            $assignmentArray['destination'] = $destination->toArray();
            $assignmentArray['destination']['destination_country'] = ($destination->getDestinationCountry()) ? $destination->getDestinationCountry()->getName() : null;
            $assignmentArray['destination']['office'] = ($destination->getOffice()) ? $destination->getOffice()->getName() : null;
            if ($assignmentArray['destination_city'] == '') {
                $assignmentArray['destination_city'] = $destination->getDestinationCity();
            }

            if ($destination->getDestinationCityGeonameid()) {
                $cityDestination = City::findFirstByGeonameIdCache($destination->getDestinationCityGeonameid());
                if ($cityDestination instanceof City) {
                    $assignmentArray['destination']['host_destination_city'] = $cityDestination->getName();
                }
            }
        }

        if ($this->getDestinationCountryId() > 0) {
            $assignmentArray['destination_country'] = $this->getDestinationCountry()->parsedDataToArray($lang);
        } elseif ($destination) {
            $assignmentArray['destination_country'] = $destination->getDestinationCountry()->parsedDataToArray($lang);
        }


        if ($this->getHomeCountryId() > 0) {
            $assignmentArray['home_country'] = $this->getHomeCountry()->parsedDataToArray($lang);
        } elseif ($basic) {
            $assignmentArray['home_country'] = $basic->getHomeCountry()->parsedDataToArray($lang);
        }


        $employee = $this->getEmployee();
        $assignmentArray['employee'] = $employee->toArray();
        $assignmentArray['employee']['avatar'] = $employee->getAvatar();
        $assignmentArray['employee']['is_editable'] = $employee->isEditable();
        $assignmentArray['employee']['company_name'] = $employee->getCompany()->getName();
        $assignmentArray['employee']['company_uuid'] = $employee->getCompany()->getUuid();
        $assignmentArray['employee']['office_name'] = $employee->getOffice() ? $employee->getOffice()->getName() : "";
        $assignmentArray['employee']['team_name'] = $employee->getTeam() ? $employee->getTeam()->getName() : "";
        $assignmentArray['employee']['department_name'] = $employee->getDepartment() ? $employee->getDepartment()->getName() : "";
        $assignmentArray['employee']['citizenships'] = $employee->parseCitizenships();
        $assignmentArray['employee']['documents'] = EntityDocument::__getDocumentsByEntityUuid($employee->getUuid());
        $assignmentArray['employee']['support_contacts'] = EmployeeSupportContact::__getSupportContacts($employee->getId(), $relocationId);
        $assignmentArray['employee']['buddy_contacts'] = EmployeeSupportContact::__getBuddyContacts($employee->getId());
        $assignmentArray['employee']['spoken_languages'] = $employee->parseSpokenLanguages();
        $assignmentArray['employee']['birth_country_name'] = $employee->getBirthCountry() ? $employee->getBirthCountry()->getName() : "";
        $assignmentArray['employee']['last_login'] = $employee->getUserLogin() ? $employee->getUserLogin()->getLastconnectAt() : "";
        $assignmentArray['employee']['first_login'] = $employee->getUserLogin() ? $employee->getUserLogin()->getFirstconnectAt() : "";
        $assignmentArray['employee']['hasLogin'] = $employee->hasLogin();
        $assignmentArray['employee']['hasUserLogin'] = $employee->getUserLogin() ? true : false;
        $assignmentArray['employee']['login_email'] = $employee->getUserLogin() ? $employee->getUserLogin()->getEmail() : null;
        $assignmentArray['employee']['sex_label'] = $employee->getSexLabel();
        $assignmentArray['company'] = $this->getCompany();
        $assignmentArray['company_name'] = $this->getCompany()->getName();
        $assignmentArray['booker_company_name'] = $this->getBookerCompany() ? $this->getBookerCompany()->getName() : '';
        //$assignmentArray['attachments'] = MediaAttachment::__get_attachments_from_uuid($this->getUuid());

        $assignmentArray['can_create_relocation'] = ($this->canCreateRelocation());
        $assignmentArray['can_approve'] = ($this->canApprove());
        $assignmentArray['has_relocation'] = ($this->hasRelocation());
        $assignmentArray['approval_status'] = intval($this->getApprovalStatus());
        $policy = $this->getPolicy();
        if ($policy) {
            $assignmentArray['policy'] = $policy;
        } else {
            $assignmentArray['policy'] = null;
        }
        $assignmentArray['company_id'] = intval($this->getCompanyId());
        $assignmentArray['assignment_type'] = $this->getAssignmentType();
        //$assignmentArray['type_name'] = ($this->getAssignmentType()) ? $this->getAssignmentType()->getName() : null;
        $assignmentArray['is_direct'] = !(ModelHelper::__intval($this->getBookerCompanyId()) > 0 && $this->getBookerCompany());
        $assignmentArray['is_booker'] = (ModelHelper::__intval($this->getBookerCompanyId()) > 0 && $this->getBookerCompany());
        $assignmentArray['broker_company_name'] = (ModelHelper::__intval($this->getBookerCompanyId()) > 0 && $this->getBookerCompany()) ? $this->getBookerCompany()->getName() : '';
        $assignmentArray['has_dependants'] = $this->hasDependants() ? 1 : 0;
        $dependants = $this->getEmployee()->getDependants()->toArray();
        if (count($dependants)) {
            foreach ($dependants as $key => $dependant) {
                if ($this->checkIfDependantExist($dependant['id'])) {
                    $dependants[$key]['selected'] = true;
                } else {
                    $dependants[$key]['selected'] = false;
                }
                $dependants[$key]['birth_country'] = '';
                if ($dependant['birth_country_id'] > 0) {
                    $birth_country = Country::findFirstByIdCache($dependant['birth_country_id']);
                    if ($birth_country) {
                        $dependants[$key]['birth_country'] = $birth_country->getName();
                    }
                }
                if ($dependant['citizenships'] != '' && Helpers::__isJsonValid($dependant['citizenships'])) {
                    $dependants[$key]['citizenships'] = json_decode($dependant['citizenships'], true);
                }
                if ($dependant['spoken_languages'] != '' && Helpers::__isJsonValid($dependant['spoken_languages'])) {
                    $dependants[$key]['spoken_languages'] = json_decode($dependant['spoken_languages'], true);
                }
                $dependants[$key]['relation_label'] = Dependant::$relation_to_label[$dependant["relation"]]["label"];
            }
        }

        $assignmentArray['dependants'] = $dependants;

        $assignmentArray['data'] = $this->getDataItems();

        $hrContactId = $this->getDataField('hr_contact_id');
        if (Helpers::__isValidId($hrContactId)) {
            $assignmentArray['hr_contact_id'] = $hrContactId;
            $hrContact = Contact::findFirstById($hrContactId);
            if ($hrContact && $hrContact->belongsToGms()) {
                $assignmentArray['hr_contact'] = $hrContact;
                $assignmentArray['hr_contact_name'] = $hrContact->getFirstname() . " " . $hrContact->getLastname();
            }
        }

        return $assignmentArray;
    }


    /** check if the current gms can manage the Assignment */
    public function manageByGms()
    {

    }

    /**
     * @param $status
     * @return bool
     */
    public function OLDapprovalStatusCheckBeforeSave($approval_status)
    {

        if (($this->getStatus() == self::STATUS_DRAFT || $this->getArchived() == self::ARCHIVED_YES) && $approval_status != $this->getApprovalStatus()) {
            return false;
            /** can not change approval status of draft or archived */
        }
        $current_approval_status = (int)$this->getApprovalStatus();
        $approval_status = intval($approval_status);
        if ($this->getId() > 0 && $this->getApprovalStatus() >= 0 && $approval_status !== null) {
            /** if status is not changed */
            if ($approval_status == intval(self::STATUS_PRE_APROVAL)) {
                if ($this->getApprovalStatus() == self::STATUS_APPROVED) {
                    if ($this->hasRelocation()) return false;
                    else return true;
                } elseif ($this->getApprovalStatus() !== intval(self::STATUS_DRAFT)) {
                    //update to PRE APPROVAL
                    return false;
                }
            }
            if ($approval_status == intval(self::STATUS_IN_APROVAL)) {
                //update to IN APPROVAL
                if ($current_approval_status !== intval(self::STATUS_DRAFT) &&
                    $current_approval_status !== intval(self::STATUS_PRE_APROVAL)
                ) {
                    return false;
                }
            }
            if ($approval_status == intval(self::STATUS_APPROVED)) {
                //update to APPROVED
                //var_dump( $current_approval_status ); die();
                if (intval($current_approval_status) !== intval(self::STATUS_DRAFT) &&
                    intval($current_approval_status) !== intval(self::STATUS_PRE_APROVAL) &&
                    intval($current_approval_status) !== intval(self::STATUS_IN_APROVAL) &&
                    intval($current_approval_status) !== intval(self::STATUS_APPROVED) &&
                    $current_approval_status !== null
                ) {
                    return false;
                }
            }

            if ($approval_status == intval(self::STATUS_REJECTED)) {
                // update to REJECTED
                if ($current_approval_status !== intval(self::STATUS_DRAFT) &&
                    $current_approval_status !== intval(self::STATUS_PRE_APROVAL) &&
                    $current_approval_status !== intval(self::STATUS_IN_APROVAL)
                ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param $status
     * @return bool
     */
    public function statusCheckBeforeSave($status)
    {
        if ($this->getArchived() == self::ARCHIVED_YES) {
            $this->setErrorMessage("ASSIGNMENT archived");
            return false;
            /** can not change approval status of draft or archived */
        }

        /* change to NOT DRAFT TO DRAFT => IMPOSSIBLE*/
        if ($status == self::STATUS_DRAFT && $this->getStatus() == null) {
            $this->setErrorMessage("Status of ASSIGNMENT switch from NULL to  DRAFT");
            return true;
        }

        /* change to STATUS DONT CHANGE => ALWAY POSSIBLE*/
        if ($status == $this->getStatus()) {
            return true;
        }
        /** status not draft but not active */
        if ($status != self::STATUS_DRAFT && $status != self::STATUS_ACTIVE) {
            $this->setErrorMessage("ASSIGNMENT Status switch to UNDEFINED STATUS");
            return false;
        }

        if ($this->getId() > 0 && $this->getStatus() >= 0 && $status !== null) {
            /** draft to draft */
            if ($status == self::STATUS_DRAFT) {
                if ($this->getStatus() != self::STATUS_DRAFT) {
                    //update to PRE APPROVAL
                    $this->setErrorMessage("Relocation Status change from NOT DRAFT TO DRAFT");
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * [getDetail description]
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public static function getDetailByUuid($uuid)
    {
        $model = self::findFirstByUuid($uuid);
        if ($model && $model instanceof self) {
            if ($model->checkContract() == true) {
                $assignment = $model->toArray();
                $basic = $model->getAssignmentBasic();
                $assignment['basic'] = $basic ? $basic->toArray() : [];
                if ($basic) {
                    $assignment['basic']['home_country'] = ($basic->getHomeCountry()) ? $basic->getHomeCountry()->getName() : null;
                }
                $destination = $model->getAssignmentDestination();
                $assignment['destination'] = $destination ? $destination->toArray() : [];
                if ($destination) {
                    $assignment['destination']['destination_country'] = ($destination->getDestinationCountry()) ? $destination->getDestinationCountry()->getName() : null;
                }
                $employee = $model->getEmployee();
                $assignment['employee'] = $employee->toArray();
                $assignment['employee']['avatar'] = $employee->getAvatar();
                $assignment['company'] = $model->getCompany();
                $assignment['upload_policy'] = [
                    'attachments' => MediaAttachment::__get_attachments_from_uuid($model->getUuid(), 'assignment_upload_policy'),
                    'group' => 'assignment_upload_policy',
                ];


                $assignment['upload_document'] = [
                    'attachments' => MediaAttachment::__get_attachments_from_uuid($model->getUuid(), 'assignment_upload_document'),
                    'group' => 'assignment_upload_document',
                ];

                $assignment['upload_basic'] = [
                    'attachments' => MediaAttachment::__get_attachments_from_uuid($model->getUuid(), 'assignment_upload_basic'),
                    'group' => 'assignment_upload_basic',
                ];

                $assignment['upload_destination'] = [
                    'attachments' => MediaAttachment::__get_attachments_from_uuid($model->getUuid(), 'assignment_upload_destination'),
                    'group' => 'assignment_upload_destination',
                ];

                $policy = $model->getPolicy();
                if ($policy) {
                    $assignment['policy'] = $policy;
                } else {
                    $assignment['policy'] = null;
                }
                //$assignment['assignment_type'] = $model->getAssignmentType();
                //$assignment['type_name'] = ($model->getAssignmentType()) ? $model->getAssignmentType()->getName() : null;

                return $assignment;
            } else {
                return false;
            }
        }
    }

    /**
     * [checkContract description]
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if ($this->getContract()) {
            return $this->getContract()->isActive() && $this->getContract()->getToCompanyId() == ModuleModel::$company->getId();
        } else {
            return false;
        }
    }

    /**
     * [checkContract description]
     * @return [type] [description]
     */
    public function checkContract()
    {
        $result = Contract::__getAllOfCurrentGMS();
        if ($result['success'] == true) {
            $contracts = $result['data'];
            if ($contracts && count($contracts)) {
                $contract_ids = [];
                foreach ($contracts as $contract) {
                    $contract_ids[$contract->getId()] = $contract->getUuid();
                }
            }
            if (isset($contract_ids[$this->getContractId()])) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @return bool
     */
    public function checkMyViewPermission()
    {
        return DataUserMember::checkMyViewPermission($this->getUuid());
    }

    /**
     * @return bool
     */
    public function checkMyEditPermission()
    {
        return DataUserMember::checkMyEditPermission($this->getUuid());
    }

    /**
     * @return bool
     */
    public function checkMyDeletePermission()
    {
        return DataUserMember::checkMyDeletePermission($this->getUuid());
    }

    /**
     * [checkContract description]
     * @return [type] [description]
     */
    public function checkCompany()
    {
        return Contract::ifCompanyExistInContracts($this->getCompanyId());
    }


    /**
     * @param $employee_id
     * @return \Reloday\Application\Models\Assignment
     */
    public static function getLastActiveAssignment($employee_id)
    {
        $assignment = self::findFirst([
            'conditions' => 'status = :status: AND
                end_date <= :current_date: AND
                approval_status IN ({approval_status:array}) AND
                employee_id = :employee_id: AND is_terminated = :is_terminated_no:',
            'bind' => [
                'status' => self::STATUS_ACTIVE,
                'current_date' => date('Y-m-d'),
                'approval_status' => [self::APPROVAL_STATUS_APPROVED],
                'employee_id' => $employee_id,
                'is_terminated_no' => ModelHelper::NO
            ],
            'order' => 'end_date DESC'
        ]);

        return $assignment;
    }

    /**
     * @return \Reloday\Application\Models\Assignment
     */
    public static function getCurrentActiveAssignment($employee_id)
    {
        $assignment = self::findFirst([
            'conditions' => "status = :status: AND
                ( ( end_date IS NULL OR end_date = '' ) OR ( estimated_end_date >= :current_date: ) )
                approval_status = :approval_status: AND
                employee_id = :employee_id:",
            'bind' => [
                'status' => self::STATUS_ACTIVE,
                'current_date' => date('Y-m-d'),
                'approval_status' => self::APPROVAL_STATUS_APPROVED,
                'employee_id' => $employee_id
            ],
            'order' => 'created_at DESC'
        ]);

        return $assignment;
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

    /**
     * @return string
     */
    public function getFrontendUrl()
    {
        return ModuleModel::$app->getFrontendUrl() . RelodayUrlHelper::__getAssignmentSuffix($this->getUuid());
    }

    /**
     *
     */
    public function setErrorMessage($message)
    {
        $this->error_message = $message;
    }

    /**
     * @return mixed
     */
    public function getErrorMessage()
    {
        return $this->error_message;
    }

    /**
     * add Owner
     * @param $profile
     */
    public function addOwner($profile)
    {
        return DataUserMember::__addNewMemberFromUuid($this->getUuid(), $profile, $this->getSource(), DataUserMember::MEMBER_TYPE_OWNER);
    }

    /**
     * @param $profile
     * @return mixed
     */
    public function addHrOwner($profile)
    {
        return DataUserMember::__addNewMemberFromUuid($this->getUuid(), $profile, $this->getSource(), DataUserMember::MEMBER_TYPE_OWNER, $this->getCompanyId());
    }

    /**
     * add Owner
     * @param $profile
     */
    public function addReporter($profile)
    {
        return DataUserMember::__addNewMemberFromUuid($this->getUuid(), $profile, $this->getSource(), DataUserMember::MEMBER_TYPE_REPORTER);
    }


    /**
     * add Owner
     * @param $profile
     */
    public function addCreator($profile)
    {
        $resultSave = DataUserMember::addCreator($this->getUuid(), $profile);
        if ($resultSave['success'] == true) {
            $return = ['success' => true, 'data' => [], 'message' => 'CREATOR_ADDED_SUCCESS_TEXT'];
        } else {
            $return = ['success' => true, 'data' => [], 'message' => 'CREATOR_ADDED_FAIL_TEXT'];
        }
        return $return;
    }

    /**
     * @return string
     */
    public function getNumber()
    {
        return $this->getReference();
    }

    /**
     * @return string
     */
    public function getFrontendState($options = "")
    {
        return "app.assignment.dashboard({uuid:'" . $this->getUuid() . "', ". $options ."})";
    }

    /**
     * @param $worker_uuid
     */
    public function getWorkerStatus($worker_uuid)
    {
        $worker = DataUserMember::findFirst([
            'conditions' => 'object_uuid = :assignment_uuid: AND user_profile_uuid = :user_profile_uuid:',
            'order' => 'member_type_id ASC',
            'bind' => [
                'user_profile_uuid' => $worker_uuid,
                'assignment_uuid' => $this->getUuid()
            ],
        ]);
        if ($worker) {
            return ($worker->getMemberType()) ? $worker->getMemberType()->getCode() : "";
        }
    }

    /**
     *
     */
    public function beforeSave()
    {
        if ($this->getReference() == '') {
            $this->setReference($this->generateNumber());
        }
    }

    /**
     * @param $options
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {

        if (isset($options['mode']) && is_string($options['mode'])) {
            $mode = $options['mode'];
        } else {
            $mode = "large";
        }
        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
        $queryBuilder->distinct(true);

        if(isset($options['is_count_all']) && $options['is_count_all'] == true){
            $queryBuilder->columns([
                'Assignment.id',
            ]);
        }else{
            $queryBuilder->columns([
                'Assignment.id',
                'Assignment.uuid',
                'Assignment.reference',
                'Assignment.external_hris_id',
                'Assignment.order_number',
                'Assignment.company_id',
                'Assignment.booker_company_id',
                'Assignment.assignment_type_id',
                'Assignment.employee_id',
                'Assignment.policy_id',
                'Assignment.effective_start_date',
                'Assignment.estimated_start_date',
                'Assignment.end_date',
                'Assignment.estimated_end_date',
                'Assignment.cost_projection_needed',
                'Assignment.status',
                'Assignment.archived',
                'Assignment.approval_status',
                'Assignment.name',
                'Assignment.home_city',
                'home_country_id' => 'OriginCountry.id',
                'Assignment.destination_city',
                'destination_country_id' => 'DestinationCountry.id',
                'Assignment.hr_assignment_owner_id',
                'Assignment.created_at',
                'Assignment.updated_at',
                'Assignment.workflow_id',
                'Assignment.is_initiated',
                'Employee.firstname',
                'Employee.lastname',
                'Employee.workemail',
                'HrCompany.name as company_name',
                'Employee.uuid as employee_uuid',
                'relocation_id' => 'Relocation.id',
                'Assignment.destination_city_geonameid',
                'Assignment.cost_center',
                'Assignment.payroll',
                'Assignment.policy_exception',
                'Assignment.budget_cap_amount',
                'Assignment.home_city_geonameid',
                'Assignment.is_terminated',
                'employee_email' =>  'Employee.workemail',
                'employee_name' => 'CONCAT(Employee.firstname, " ", Employee.lastname)',
                'booker_company_name' => 'Booker.name',
                'home_country' => 'OriginCountry.name',
                'home_country_iso2' => 'OriginCountry.cio_flag',
                'destination_country' => 'DestinationCountry.name',
                'destination_country_iso2' => 'DestinationCountry.cio_flag',
                'owner_name' => 'CONCAT(AssignmentOwner.firstname, " ", AssignmentOwner.lastname)',
                'owner_uuid' => 'AssignmentDataUserOwner.user_profile_uuid',
                'can_create_relocation' => 'IF(Assignment.status = 0 OR Assignment.archived = 1 OR Assignment.is_terminated = 1 OR Assignment.approval_status = 0  OR Assignment.approval_status = 1  OR Assignment.approval_status = -1, false, true)',
                'create_relocation_status' =>  'IF(Assignment.status = 0 OR Assignment.archived = 1 OR Assignment.is_terminated = 1 OR Assignment.approval_status = 0  OR Assignment.approval_status = 1  OR Assignment.approval_status = -1, 1, 0)',
                'relocation_archived_uuid' =>  'RelocationArchived.uuid',
            ]);

        }
        // if ($mode == "small") {
        // }

        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'HrCompany.id = Assignment.company_id', 'HrCompany');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Employee.id = Assignment.employee_id', 'Employee');

        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentBasic', 'AssignmentBasic.id = Assignment.id', 'AssignmentBasic');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Company', 'Assignment.booker_company_id = Booker.id', 'Booker');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Country', 'AssignmentBasic.home_country_id = OriginCountry.id', 'OriginCountry');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentDestination', 'AssignmentDestination.id = Assignment.id', 'AssignmentDestination');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Country', 'AssignmentDestination.destination_country_id = DestinationCountry.id', 'DestinationCountry');

        $queryBuilder->leftjoin('\Reloday\Gms\Models\Relocation', 'Relocation.assignment_id = Assignment.id AND Relocation.creator_company_id = ' . ModuleModel::$company->getId() . ' AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\AssignmentRequest', 'AssignmentRequest.assignment_id = Assignment.id AND AssignmentRequest.company_id = ' . ModuleModel::$company->getId(), 'AssignmentRequest');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'AssignmentDataUserOwner.object_uuid = Assignment.uuid and AssignmentDataUserOwner.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER . ' and AssignmentDataUserOwner.company_id = '. ModuleModel::$company->getId(), 'AssignmentDataUserOwner');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserProfile', 'AssignmentDataUserOwner.user_profile_uuid = AssignmentOwner.uuid', 'AssignmentOwner');

        $queryBuilder->leftjoin('\Reloday\Gms\Models\Relocation', 'RelocationArchived.assignment_id = Assignment.id AND RelocationArchived.creator_company_id = ' . ModuleModel::$company->getId() . ' AND RelocationArchived.active = ' . Relocation::STATUS_ARCHIVED, 'RelocationArchived');


        $queryBuilder->where('Employee.company_id = Assignment.company_id');

        /** user want to see assignment request: assignment in contract only make sense if gms accepted that request
         * so If user want to see assignment request, we will not join with assignment in contract
         */
        if (isset($options['has_request']) && is_bool($options['has_request']) && $options['has_request'] == true) {
            $queryBuilder->andwhere("AssignmentRequest.id > 0");
            $queryBuilder->andwhere('AssignmentRequest.company_id = ' . ModuleModel::$company->getId());
            $queryBuilder->andwhere('AssignmentRequest.status = ' . AssignmentRequest::STATUS_PENDING . ' OR AssignmentRequest.status = ' . AssignmentRequest::STATUS_REJECTED);
//            $queryBuilder->andwhere('Contract.status = ' . Contract::STATUS_ACTIVATED);
            $queryBuilder->andWhere("Assignment.approval_status = " . Assignment::STATUS_IN_APROVAL . " OR Assignment.approval_status = " . Assignment::STATUS_APPROVED);
            $queryBuilder->andWhere("Assignment.status = " . Assignment::STATUS_ACTIVE);
            $queryBuilder->andwhere("Assignment.archived = :archived_no:", [
                'archived_no' => Assignment::ARCHIVED_NO
            ]);
            $queryBuilder->andwhere("Relocation.id IS NULL");
        } else {
            $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
            $queryBuilder->andwhere('Contract.to_company_id = :gms_company_id:', [
                'gms_company_id' => intval(ModuleModel::$company->getId())
            ]);
            $queryBuilder->andwhere('Contract.from_company_id = Assignment.company_id');

            $queryBuilder->andwhere('Contract.status = :contract_active:', [
                'contract_active' => Contract::STATUS_ACTIVATED
            ]);
        }

        $queryBuilder->andwhere('Assignment.archived = :assignment_not_archived:', [
            'assignment_not_archived' => Assignment::ARCHIVED_NO
        ]);
        $queryBuilder->orderBy(['Assignment.id DESC']);

//        $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwnerForQuery.object_uuid = Assignment.uuid', 'DataUserOwnerForQuery');
//
//        $queryBuilder->andwhere("DataUserOwnerForQuery.member_type_id = :member_type_owner:",
//            [
//                'member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
//            ]
//        );
//        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserProfile', 'Owner.uuid = DataUserOwnerForQuery.user_profile_uuid', 'Owner');
        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "approval_status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.approval_status ASC', 'Relocation.id ASC', 'AssignmentRequest.updated_at DESC', 'AssignmentRequest.status ASC', 'Assignment.created_at DESC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.approval_status DESC', 'Relocation.id DESC', 'AssignmentRequest.updated_at DESC', 'AssignmentRequest.status DESC', 'Assignment.created_at DESC']);
                }
            }

            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.is_terminated ASC', 'Assignment.created_at DESC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.is_terminated DESC', 'Assignment.created_at DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.created_at DESC']);
                }
            }


            if ($order['field'] == "employee") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC', 'Employee.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC', 'Employee.lastname DESC']);
                }
            }

            if ($order['field'] == "estimated_start_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.estimated_start_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.estimated_start_date DESC']);
                }
            }

            if ($order['field'] == "estimated_end_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.estimated_end_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.estimated_end_date DESC']);
                }
            }

            if ($order['field'] == "effective_start_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.effective_start_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.effective_start_date DESC']);
                }
            }

            if ($order['field'] == "end_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.end_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.end_date DESC']);
                }
            }
        }

        if (isset($options['has_relocation']) && is_bool($options['has_relocation']) && $options['has_relocation'] == true) {
            $queryBuilder->andwhere("Relocation.id > 0");
        }

        if (isset($options['has_relocation']) && is_bool($options['has_relocation']) && $options['has_relocation'] == false) {
            $queryBuilder->andwhere("Relocation.id IS NULL");
        }

        if (isset($options['active']) && is_bool($options['active'])) {
//            $queryBuilder->andwhere("Assignment.end_date IS NULL OR Assignment.end_date >= :current_date:", [
//                'current_date' => date('Y-m-d H:i:s'),
//            ]);
            if ($options['active'] == true) {
                $queryBuilder->andwhere('Assignment.is_terminated = :is_terminate_no:', [
                    'is_terminate_no' => ModelHelper::NO
                ]);
            } else {
                $queryBuilder->andwhere('Assignment.is_terminated = :is_terminate_yes:', [
                    'is_terminate_yes' => ModelHelper::YES
                ]);
            }

        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("Assignment.company_id = :company_id:", [
                'company_id' => $options['company_id'],
            ]);
        }

        if (isset($options['hr_company_id']) && is_numeric($options['hr_company_id']) && $options['hr_company_id'] > 0) {
            $queryBuilder->andwhere("Assignment.company_id = :hr_company_id:", [
                'hr_company_id' => $options['hr_company_id'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Assignment.employee_id = :employee_id:", [
                'employee_id' => $options['employee_id'],
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Assignment.name LIKE :query: OR CONCAT(Employee.firstname,' ',Employee.lastname) LIKE :query: 
            OR Employee.workemail LIKE :query: OR HrCompany.name LIKE :query: OR OriginCountry.name LIKE :query: OR OriginCountry.cio LIKE :query:
            OR Booker.name LIKE :query:
             OR DestinationCountry.name LIKE :query: OR DestinationCountry.cio LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
            $bindArray['query'] = '%' . $options['query'] . '%';
        }

        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners']) &&
            isset($options['user_profile_uuid']) && Helpers::__isValidUuid($options['user_profile_uuid'])) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataOwners.object_uuid = Assignment.uuid', 'DataOwners');
            $queryBuilder->andwhere("DataOwners.user_profile_uuid IN ({owners:array} ) AND DataOwners.member_type_id = :member_type_owner:",
                [
                    'owners' => $options['owners'],
                    'member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
                ]
            );

            $queryBuilder->andwhere("DataUserMember.user_profile_uuid = :user_profile_uuid:",
                [
                    'user_profile_uuid' => $options['user_profile_uuid'],
                ]
            );
        }

        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners']) && (!isset($options['user_profile_uuid']) || $options['user_profile_uuid'] == '')) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataOwner.object_uuid = Assignment.uuid', 'DataOwner');
            $queryBuilder->andwhere("DataOwner.user_profile_uuid IN ({owners:array} ) AND DataOwner.member_type_id = :member_type_owner:",
                [
                    'owners' => $options['owners'],
                    'member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
                ]
            );
        }

        if (isset($options['user_profile_uuid']) && Helpers::__isValidUuid($options['user_profile_uuid']) && (!isset($options['owners']) || count($options['owners']) == 0)) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
            $queryBuilder->andwhere("DataUserMember.user_profile_uuid = :user_profile_uuid:",
                [
                    'user_profile_uuid' => $options['user_profile_uuid'],
                ]
            );
        }

        if (isset($options['owner_uuid']) && Helpers::__isValidUuid($options['owner_uuid']) && ModuleModel::$user_profile->isAdminOrManager() == true) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwner.object_uuid = Assignment.uuid', 'DataUserOwner');
            $queryBuilder->andwhere("DataUserOwner.user_profile_uuid = :user_profile_uuid: AND DataUserOwner.member_type_id = :member_type_owner:",
                [
                    'user_profile_uuid' => $options['owner_uuid'],
                    'member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
                ]
            );
        }

        if (isset($options['bookers']) && is_array($options['bookers']) && count($options['bookers'])) {
            $queryBuilder->andwhere("Assignment.booker_company_id IN ({bookers:array} )",
                [
                    'bookers' => $options['bookers']
                ]
            );
        }


        if (isset($options['assignees']) && is_array($options['assignees']) && count($options['assignees']) > 0) {
            $queryBuilder->andwhere("Assignment.employee_id IN ({assignees:array})", [
                'assignees' => $options['assignees'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Assignment.employee_id = :employee_id:", [
                'employee_id' => $options['employee_id'],
            ]);
        }

        if (isset($options['companies']) && is_array($options['companies']) && count($options['companies']) > 0) {
            $queryBuilder->andwhere("Assignment.company_id IN ({companies:array})", [
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

        if (isset($options['approval_statuses']) && is_array($options['approval_statuses']) && count($options['approval_statuses']) > 0) {
            $queryBuilder->andwhere("Assignment.approval_status IN ({array_approval_statuses:array})", [
                'array_approval_statuses' => $options['approval_statuses'],
            ]);
        }


        if (isset($options['filter_config_id'])) {


            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'Assignment.company_id',
                'ASSIGNEE_ARRAY_TEXT' => 'Assignment.employee_id',
                'OWNER_ARRAY_TEXT' => 'Owner.user_profile_id',
                'REPORTER_ARRAY_TEXT' => 'Reporter.user_profile_id',
                'BOOKER_ARRAY_TEXT' => 'Assignment.booker_company_id',
                'ORIGIN_ARRAY_TEXT' => 'AssignmentBasic.home_country_id',
                'DESTINATION_ARRAY_TEXT' => 'AssignmentDestination.destination_country_id',
                'STATUS_TEXT' => 'Assignment.is_terminated',
                'EFFECTIVE_START_DATE_TEXT' => 'Assignment.effective_start_date',
                'EFFECTIVE_END_DATE_TEXT' => 'Assignment.end_date',
                'CREATED_ON_TEXT' => 'Assignment.created_at',
                'PREFIX_DATA_OWNER_TYPE' => 'Owner.member_type_id',
                'PREFIX_DATA_REPORTER_TYPE' => 'Reporter.member_type_id',
                'PREFIX_OBJECT_UUID' => 'Assignment.uuid',
                'POLICY_ARRAY_TEXT' => 'Assignment.policy_id',
                'HAS_RELOCATION_TEXT' => 'Relocation.id',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::ASSIGNMENT_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }
        $queryBuilder->groupBy('Assignment.id');
        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $assignments_array = [];

            if(isset($options['is_count_all']) && $options['is_count_all'] == true){
               goto end_of_return;
            }

            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $assigment) {
                    $assignmentArrayItem = $assigment->toArray();
                    $assignmentArrayItem['can_create_relocation'] = $assignmentArrayItem['create_relocation_status'] == self::CREATE_RELOCATION_TODO;
                    $assignments_array[] = $assignmentArrayItem;
                }

            }
            end_of_return:
            return [
                'success' => true,
                // 'query' => $queryBuilder->getQuery()->getSql(),
                'page' => $page,
                'data' => ($assignments_array),
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
     * @return mixed
     */
    public function getSimpleProfileOwner()
    {
        $owners = $this->getOwners();
        if ($owners && $owners->count() > 0) return $owners->getFirst();
    }

    /**
     *
     */
    public function getGmsOwner()
    {
        $owners = $this->getOwners([
            'conditions' => 'Reloday\Gms\Models\UserProfile.company_id = :company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId()
            ]]);
        if ($owners && $owners->count() > 0) return $owners->getFirst();
    }

    /**
     *
     */
    public function getHrOwner()
    {
        $owner = DataUserMember::getDataOwner($this->getUuid(), $this->getCompanyId());
        return $owner;
    }

    /**
     *
     */
    public function getHrReporter()
    {
        $owner = DataUserMember::getDataReporter($this->getUuid(), $this->getCompanyId());
        return $owner;
    }

    /**
     *
     */
    public function getHrMembers()
    {
        $members = DataUserMember::__getDataMembers($this->getUuid(), $this->getCompanyId());
        return $members;
    }


    /**
     * @RTODO run after create
     * @return array
     */
    public function confirmAddDependants()
    {
        $assignmentDependants = AssignmentDependant::find([
            'conditions' => 'assignment_uuid  = :assignment_uuid:',
            'bind' => [
                'assignment_uuid' => $this->getUuid()
            ]
        ]);

        if ($assignmentDependants->count() > 0) {
            return ModelHelper::__quickUpdateCollection($assignmentDependants, ['assignment_id' => $this->getId()]);
        }
        return ['success' => true];
    }


    /**
     * @param $dependantModel
     */
    public function addDependant($dependantModel)
    {
        if ($this->checkIfDependantExist($dependantModel->getId()) == false) {
            $assignmentDependant = new AssignmentDependant();
            $assignmentDependant->setData([
                'employee_id' => $dependantModel->getEmployeeId(),
                'assignment_id' => $this->getId(),
                'assignment_uuid' => $this->getUuid(),
                'dependant_id' => $dependantModel->getId(),
            ]);
            return $assignmentDependant->__quickCreate();
        } else {
            return ['success' => true];
        }
    }

    /**
     * @param $dependantModel
     */
    public function removeDependant($dependantModel)
    {
        if ($this->checkIfDependantExist($dependantModel->getId()) == true) {
            $assignmentDependant = AssignmentDependant::findFirst([
                'conditions' => 'assignment_id  = :assignment_id: AND dependant_id = :dependant_id:',
                'bind' => [
                    'assignment_id' => $this->getId(),
                    'dependant_id' => $dependantModel->getId()
                ]
            ]);
            if ($assignmentDependant) {
                return $assignmentDependant->__quickRemove();
            }
        }
        return ['success' => true];
    }

    /**
     * @return mixed
     */
    public function removeAllDependants()
    {
        $assignmentDependants = AssignmentDependant::find([
            'conditions' => 'assignment_id  = :assignment_id:',
            'bind' => [
                'assignment_id' => $this->getId(),
            ]
        ]);
        if ($assignmentDependants) {
            return ModelHelper::__quickRemoveCollection($assignmentDependants);
        }
    }

    /**
     * @return array
     */
    public function getAllDependants()
    {
        $employee = $this->getEmployee();
        $dependants = [];
        if ($employee) {
            $dependants = $employee->getDependants()->toArray();
            if (count($dependants)) {
                foreach ($dependants as $key => $dependant) {
                    if ($this->checkIfDependantExist($dependant['id'])) {
                        $dependants[$key]['selected'] = true;
                    }
                }
            }
        }
        return $dependants;
    }

    /**
     * @return string
     */
    public function getGmsFolderUuid()
    {
        return $this->getUuid() . "_" . ModuleModel::$company->getUuid();
    }

    /**
     * @return string
     */
    public function getCompanyFolderUuid($companyUuid = null)
    {
        if ($companyUuid == null) {
            $companyUuid = ModuleModel::$company->getUuid();
        }
        return $this->getUuid() . "_" . $companyUuid;
    }

    /**
     * @param array $custom
     * @return array|void
     */
    public function setData($custom = [])
    {
        if (!($this->getId() > 0)) {
            $company_id = Helpers::__getRequestValueWithCustom("company_id", $custom);
            if ($company_id > 0 && $this->getCompanyId() == null) {
                $this->setCompanyId($company_id);
            }
            $contract = Contract::__findContractOfCompany($this->getCompanyId());
            if ($contract) {
//                $this->setContractId($contract->getId());
            }
        }
        return parent::setData($custom);
    }


    /**
     * check if we can create new relocation
     * @return bool
     */
    public function canCreateRelocation()
    {
        $result = parent::canCreateRelocation();

        if ($this->getRelocation() && $this->getRelocation()->getActive() != RelocationExt::STATUS_DELETED) {
            return false;
        } else {
            return $result;
        }
    }

    /**
     * @return int
     */
    public static function __countActiveAssignments()
    {
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
            $queryBuilder->where('Contract.to_company_id = ' . ModuleModel::$company->getId());
            $queryBuilder->andwhere('Contract.status = ' . Contract::STATUS_ACTIVATED);
            $queryBuilder->andWhere("Assignment.status = " . Assignment::STATUS_ACTIVE);
            $queryBuilder->andwhere("Assignment.archived = :archived_no:", [
                'archived_no' => Assignment::ARCHIVED_NO
            ]);
            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 10,
                'page' => 1
            ]);
            $paginate = $paginator->getPaginate();
            $return = $paginate->total_items;
        } catch (\PDOException $e) {
            $return = 0;
        } catch (Exception $e) {
            $return = 0;
        }
        return $return;
    }


    /**
     * @return int
     */
    public static function __countActiveRequests()
    {
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\AssignmentRequest', 'AssignmentRequest');
            $queryBuilder->columns(['AssignmentRequest.id']);
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'AssignmentRequest.assignment_id = Assignment.id', 'Assignment');
            $queryBuilder->andwhere('AssignmentRequest.company_id = ' . ModuleModel::$company->getId());
            //$queryBuilder->andwhere('AssignmentRequest.message is not null');
            $queryBuilder->andWhere("Assignment.status = " . Assignment::STATUS_ACTIVE);
            $queryBuilder->andWhere("Assignment.is_terminated = " . ModelHelper::NO);
            $queryBuilder->andwhere("Assignment.archived = :archived_no:", [
                'archived_no' => Assignment::ARCHIVED_NO
            ]);
            $queryBuilder->groupBy('AssignmentRequest.id');
            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 10,
                'page' => 1
            ]);
            $paginate = $paginator->getPaginate();
            $return = $paginate->total_items;
        } catch (\PDOException $e) {
            $return = 0;
        } catch (Exception $e) {
            $return = 0;
        }
        return $return;
    }

    /**
     * @return int
     */
    public static function __countPendingRequests()
    {
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\AssignmentRequest', 'AssignmentRequest');
            $queryBuilder->columns(['AssignmentRequest.id']);
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'AssignmentRequest.assignment_id = Assignment.id', 'Assignment');
            $queryBuilder->andwhere('AssignmentRequest.company_id = ' . ModuleModel::$company->getId());
            //$queryBuilder->andwhere('AssignmentRequest.message is not null');
            //$queryBuilder->andwhere("AssignmentRequest.id > 0");
            $queryBuilder->andWhere("Assignment.is_terminated = " . ModelHelper::NO);
            $queryBuilder->andWhere("Assignment.status = " . Assignment::STATUS_ACTIVE);
            $queryBuilder->andwhere("Assignment.archived = :archived_no:", [
                'archived_no' => Assignment::ARCHIVED_NO
            ]);

            $queryBuilder->andwhere("AssignmentRequest.status = :pending_status:", [
                'pending_status' => AssignmentRequest::STATUS_PENDING
            ]);

            $queryBuilder->groupBy('AssignmentRequest.id');
            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 10,
                'page' => 1
            ]);
            $paginate = $paginator->getPaginate();
            $return = $paginate->total_items;
        } catch (\PDOException $e) {
            $return = 0;
        } catch (Exception $e) {
            $return = 0;
        }
        return $return;
    }

    /**
     * @return mixed
     */
    public function getContract()
    {
        return $this->getActiveContracts() ? $this->getActiveContracts()->getFirst() : false;
    }

    /**
     * @return bool
     */
    public function getActiveAssignmentRequest()
    {
        return $this->getActiveAssignmentRequests() ? $this->getActiveAssignmentRequests()->getFirst() : false;
    }

    /**
     * @param $contract
     */
    public function addToContract(Contract $contract)
    {
        //don't need to add in the contract if it's exist in the contract
        if ($this->existInContract($contract) == true) {
            return ['success' => true];
        }
        //add the contract
        if ($contract->isActive() == true || $contract->belongsToGms()) {
            $assignmentInContract = new AssignmentInContract();
            $assignmentInContract->setAssignmentId($this->getId());
            $assignmentInContract->setContractId($contract->getId());
            return $assignmentInContract->__quickCreate();
        }
        return ['success' => false];
    }


    /**
     * @param $contract
     */
    public function removeFromContract(Contract $contract)
    {
        //remove from this contract
        if ($this->existInContract($contract) == false) {
            return ['success' => true];
        }
        //add the contract
        if ($contract->isActive() == true || $contract->belongsToGms()) {
            $assignmentInContract = AssignmentInContract::findFirst([
                'conditions' => 'assignment_id = :assignment_id: AND contract_id = :contract_id:',
                'bind' => [
                    'assignment_id' => $this->getId(),
                    'contract_id' => $contract->getId(),
                ]
            ]);
            return $assignmentInContract->__quickRemove();
        }
        return ['success' => false];
    }

    /**
     * @param \Reloday\Gms\Models\Contract $contract
     * @return bool
     */
    public function existInContract(Contract $contract)
    {
        $existInContract = AssignmentInContract::findFirst([
            'conditions' => 'assignment_id = :assignment_id: AND contract_id = :contract_id:',
            'bind' => [
                'assignment_id' => $this->getId(),
                'contract_id' => $contract->getId()
            ]
        ]);
        if ($existInContract) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        if ($this->isArchived() == true) return false;
        if ($this->getCompany() && $this->getCompany()->isEditable() == true) {
            return true;
        }
        $activeContract = ModuleModel::$company->getActiveContract($this->getCompany()->getId());
        if (!$activeContract) return false;
        return Contract::__hasPermissionFromContractId($activeContract->getId(), 'assignment', 'edit');
    }


    /**
     * @param array $custom : app_id, profile_id, created_by
     * @return array|CompanyExt|Company
     */
    public function createSingleOne($custom = [])
    {
        $this->setData($custom);

        $model = $this;
        $data = [];

        $data['archived'] = self::ARCHIVED_NO;
        $data['approval_status'] = self::APPROVAL_STATUS_DEFAULT;
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_INACTIVATED;
        }
        $data['cost_projection_needed'] = self::COST_PROJECTION_NEED_YES;
        $data['us_green_card_holder'] = self::US_GREENCARD_NO;


        $status = (isset($custom['status']) ? $custom['status'] : (isset($data['status']) ? $data['status'] : $model->getStatus()));
        if (($status == null || empty($status) || $status == self::STATUS_INACTIVATED) && !($model->getId() > 0)) {
            $status = self::STATUS_ACTIVE;
        }

        if ($model->statusCheckBeforeSave($status) == false) {
            $result = [
                'success' => false,
                'details' => $model->getErrorMessage(),
                'status' => $status,
                'message' => 'ASSIGNMENT_STATUS_NOT_VALIDATE_TEXT',
            ];
            return $result;
        } else {
            $model->setStatus($status);
        }

        $approval_status = Helpers::__getRequestValueWithCustom('approval_status', $custom);
        $approval_status = Helpers::__coalesce($approval_status, $model->getApprovalStatus());

        /** default == approved */
        if (($approval_status == null || $approval_status == self::APPROVAL_STATUS_DEFAULT) && !($model->getId() > 0)) {
            $approval_status = self::APPROVAL_STATUS_APPROVED;
        }

        $model->setApprovalStatus($approval_status);

        //EmployeeId
        $company_id = $model->getCompanyId();
        if (isset($data['employee'])) {
            $employee = $data['employee'];
            $employee_id = $data['employee']['id'];
            $company_id = $data['employee']['company_id'];
        } elseif (isset($custom['employee_id'])) {
            $employee_id = $custom['employee_id'];
        } elseif (isset($data['employee_id'])) {
            $employee_id = $data['employee_id'];
        } elseif (isset($custom['employee'])) {
            $employee = $custom['employee'];
            $employee_id = $custom['employee']['id'];
            $company_id = $custom['employee']['company_id'];
        } else {
            $employee_id = $model->getEmployeeId();
        }

        if (!isset($employee) && $employee_id > 0) {
            $employee = Employee::findFirstById($employee_id);
            if ($employee) {
                $company_id = $employee->getCompanyId();
            }
        }

        /*** company ***/
        if ($company_id == null) {
            $company_id = (isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId()));
        }
        /*** uuid ***/
        if ($model->getUuid() == '' || $model->getUuid() == null) {
            $uuid = isset($custom['uuid']) && $custom['uuid'] != '' ? $custom['uuid'] : (isset($data['uuid']) && $data['uuid'] != '' ? $data['uuid'] : ApplicationModel::uuid());
            $model->setUuid($uuid);
        }

        //$data['policy_id'] = 0;
        $policy_id = isset($custom['policy_id']) && is_numeric($custom['policy_id']) && $custom['policy_id'] > 0 ? $custom['policy_id'] :
            (isset($data['policy_id']) && is_numeric($data['policy_id']) && $data['policy_id'] > 0 ? $data['policy_id'] : null);

        if (!is_null($policy_id) && $policy_id > 0) {
            $model->setPolicyId($policy_id);
        }

        $assignment_type_id = isset($custom['assignment_type_id']) ? $custom['assignment_type_id'] : (isset($data['assignment_type_id']) ? $data['assignment_type_id'] : null);
        if ($assignment_type_id == null) {
            $assignment_type_id = isset($custom['assignment_type']) ? $custom['assignment_type'] : (isset($data['assignment_type']) ? $data['assignment_type'] : $model->get('assignment_type_id'));
        }
        if ($assignment_type_id > 0) $model->setAssignmentTypeId($assignment_type_id);

        $model->setName(isset($custom['name']) ? $custom['name'] : (isset($data['name']) ? $data['name'] : $model->getName()));
        $model->setEmployeeId(isset($custom['employee_id']) ? $custom['employee_id'] : (isset($data['employee_id']) ? $data['employee_id'] : $employee_id));
        $model->setCompanyId(isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $company_id));
        $model->setBookerCompanyId(isset($custom['booker_company_id']) ? $custom['booker_company_id'] : (isset($data['booker_company_id']) ? $data['booker_company_id'] : $model->getBookerCompanyId()));
        $model->setOrderNumber(isset($custom['order_number']) ? $custom['order_number'] : (isset($data['order_number']) ? $data['order_number'] : $model->getOrderNumber()));
        $model->setHrAssignmentOwnerId(isset($custom['hr_assignment_owner_id']) ? $custom['hr_assignment_owner_id'] : (isset($data['hr_assignment_owner_id']) ? $data['hr_assignment_owner_id'] : $model->getHrAssignmentOwnerId()));


        /** reference */
        if ($model->getReference() == '') {
            $reference = $model->generateNumber();
            $model->setReference($reference);
        }

        if ($model->getName() == '') {
            if (!isset($reference)) {
                $reference = $model->generateNumber();
            }
            $model->setName($reference);
        }


        $effective_start_date = isset($custom['effective_start_date']) ? $custom['effective_start_date'] : (isset($data['effective_start_date']) ? $data['effective_start_date'] : $model->get('effective_start_date'));
        if ($effective_start_date != '' && !is_null($effective_start_date)) {
            $model->set('effective_start_date', $effective_start_date);
        } else {
            if (isset($custom['effective_start_date']) || isset($data['effective_start_date'])) {
                $model->set('effective_start_date', null);
            }
        }

        $end_date = isset($custom['end_date']) ? $custom['end_date'] : (isset($data['end_date']) ? $data['end_date'] : $model->get('end_date'));
        if ($end_date != '') {
            $model->set('end_date', $end_date);
        } else {
            if (isset($custom['end_date']) || isset($data['end_date'])) {
                $model->set('end_date', null);
            }
        }

        $estimated_start_date = isset($custom['estimated_start_date']) ? $custom['estimated_start_date'] :
            (isset($data['estimated_start_date']) ? $data['estimated_start_date'] : $model->get('estimated_start_date'));
        if ($estimated_start_date != '') {
            $model->set('estimated_start_date', $estimated_start_date);
        } else {
            if (isset($custom['estimated_start_date']) || isset($data['estimated_start_date'])) {
                $model->set('estimated_start_date', null);
            }
        }

        $estimated_end_date = isset($custom['estimated_end_date']) ? $custom['estimated_end_date'] : (isset($data['estimated_end_date']) ? $data['estimated_end_date'] : $model->get('estimated_end_date'));
        if ($estimated_end_date != '') {
            $model->set('estimated_end_date', $estimated_end_date);
        } else {
            if (isset($custom['estimated_end_date']) || isset($data['estimated_end_date'])) {
                $model->set('estimated_end_date', null);
            }
        }

        $model->set('cost_projection_needed',
            isset($custom['cost_projection_needed']) ? $custom['cost_projection_needed'] : (isset($data['cost_projection_needed']) ? $data['cost_projection_needed'] : $model->get('cost_projection_needed'))
        );

        $model->set('archived',
            isset($custom['archived']) ? $custom['archived'] : (isset($data['archived']) ? $data['archived'] : $model->get('archived'))
        );

        $model->set('archived',
            isset($custom['archived']) ? $custom['archived'] : (isset($data['archived']) ? $data['archived'] : $model->get('archived'))
        );

        $customDestination = isset($custom['destination']) ? $custom['destination'] : null;
        $customBasic = isset($custom['basic']) ? $custom['basic'] : null;

        $destination_city = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_city', $customDestination), $model->getDestinationCity());
        if ($destination_city != '') {
            $model->set('destination_city', $destination_city);
        }
        $home_city = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_city', $customBasic), $model->getHomeCity());
        if ($home_city != '') {
            $model->set('home_city', $home_city);
        }

        $home_country_id = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_country_id', $customBasic), $model->getHomeCountryId());
        if ($home_country_id > 0) {
            $model->set('home_country_id', $home_country_id);
        }

        $destination_country_id = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_country_id', $customDestination), $model->getDestinationCountryId());
        if ($destination_country_id > 0) {
            $model->set('destination_country_id', $destination_country_id);
        }

        $home_city_geonameid = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_city_geonameid', $customDestination), $model->getHomeCityGeonameid());
        if ($home_city_geonameid > 0) {
            $model->setHomeCityGeonameid($home_city_geonameid);
        }

        $destination_city_geonameid = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_city_geonameid', $customDestination), $model->getDestinationCityGeonameid());
        if ($destination_city_geonameid > 0) {
            $model->setDestinationCityGeonameid($destination_city_geonameid);
        }

        $resultSave = $model->__quickCreate();

        return $resultSave;
    }


    /**
     * @param array $custom : app_id, profile_id, created_by
     * @return array|CompanyExt|Company
     */
    public function updateSingleOne($custom = [])
    {
        $this->setData($custom);
        $model = $this;
        $data = [];
        $status = (isset($custom['status']) ? $custom['status'] : (isset($data['status']) ? $data['status'] : $model->getStatus()));

        if ($model->statusCheckBeforeSave($status) == false) {
            $result = [
                'success' => false,
                'errorType' => 'assignmentStatusNotValidate',
                'errorDetails' => $model->getErrorMessage(),
                'status' => $status,
                'message' => 'ASSIGNMENT_STATUS_NOT_VALIDATE_TEXT',
            ];
            return $result;
        } else {
            $model->setStatus($status);
        }

        $approval_status = Helpers::__getCustomValue('approval_status', $custom);
        $approval_status = Helpers::__coalesce($approval_status, $model->getApprovalStatus());

        /** default == approved */
        if (($approval_status == null || $approval_status == self::APPROVAL_STATUS_DEFAULT) && !($model->getId() > 0)) {
            $approval_status = self::APPROVAL_STATUS_APPROVED;
        }

        $model->setApprovalStatus($approval_status);

        //EmployeeId
        $company_id = $model->getCompanyId();
        if (isset($data['employee'])) {
            $employee = $data['employee'];
            $employee_id = $data['employee']['id'];
            $company_id = $data['employee']['company_id'];
        } elseif (isset($custom['employee_id'])) {
            $employee_id = $custom['employee_id'];
        } elseif (isset($data['employee_id'])) {
            $employee_id = $data['employee_id'];
        } elseif (isset($custom['employee'])) {
            $employee = $custom['employee'];
            $employee_id = $custom['employee']['id'];
            $company_id = $custom['employee']['company_id'];
        } else {
            $employee_id = $model->getEmployeeId();
        }

        if (!isset($employee) && $employee_id > 0) {
            $employee = Employee::findFirstById($employee_id);
            if ($employee) {
                $company_id = $employee->getCompanyId();
            }
        }

        /*** company ***/
        if ($company_id == null) {
            $company_id = (isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId()));
        }
        /*** uuid ***/
        if ($model->getUuid() == '' || $model->getUuid() == null) {
            $uuid = isset($custom['uuid']) && $custom['uuid'] != '' ? $custom['uuid'] : (isset($data['uuid']) && $data['uuid'] != '' ? $data['uuid'] : ApplicationModel::uuid());
            $model->setUuid($uuid);
        }

        //$data['policy_id'] = 0;
        $policy_id = isset($custom['policy_id']) && is_numeric($custom['policy_id']) && $custom['policy_id'] > 0 ? $custom['policy_id'] :
            (isset($data['policy_id']) && is_numeric($data['policy_id']) && $data['policy_id'] > 0 ? $data['policy_id'] : null);

        if (!is_null($policy_id) && $policy_id > 0) {
            $model->setPolicyId($policy_id);
        }

        $assignment_type_id = isset($custom['assignment_type_id']) ? $custom['assignment_type_id'] : (isset($data['assignment_type_id']) ? $data['assignment_type_id'] : null);
        if ($assignment_type_id == null) {
            $assignment_type_id = isset($custom['assignment_type']) ? $custom['assignment_type'] : (isset($data['assignment_type']) ? $data['assignment_type'] : $model->get('assignment_type_id'));
        }
        if ($assignment_type_id > 0) $model->setAssignmentTypeId($assignment_type_id);

        $model->setName(isset($custom['name']) ? $custom['name'] : (isset($data['name']) ? $data['name'] : $model->getName()));
        $model->setEmployeeId(isset($custom['employee_id']) ? $custom['employee_id'] : (isset($data['employee_id']) ? $data['employee_id'] : $employee_id));
        $model->setCompanyId(isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $company_id));
        $model->setBookerCompanyId(isset($custom['booker_company_id']) ? $custom['booker_company_id'] : (isset($data['booker_company_id']) ? $data['booker_company_id'] : $model->getBookerCompanyId()));
        $model->setOrderNumber(isset($custom['order_number']) ? $custom['order_number'] : (isset($data['order_number']) ? $data['order_number'] : $model->getOrderNumber()));
        $model->setHrAssignmentOwnerId(isset($custom['hr_assignment_owner_id']) ? $custom['hr_assignment_owner_id'] : (isset($data['hr_assignment_owner_id']) ? $data['hr_assignment_owner_id'] : $model->getHrAssignmentOwnerId()));


        /** reference */
        if ($model->getReference() == '') {
            $reference = $model->generateNumber();
            $model->setReference($reference);
        }

        if ($model->getName() == '') {
            if (!isset($reference)) {
                $reference = $model->generateNumber();
            }
            $model->setName($reference);
        }


        $effective_start_date = isset($custom['effective_start_date']) ? $custom['effective_start_date'] : (isset($data['effective_start_date']) ? $data['effective_start_date'] : $model->get('effective_start_date'));
        if ($effective_start_date != '' && !is_null($effective_start_date)) {
            $model->set('effective_start_date', $effective_start_date);
        } else {
            if (isset($custom['effective_start_date']) || isset($data['effective_start_date'])) {
                $model->set('effective_start_date', null);
            }
        }

        $end_date = isset($custom['end_date']) ? $custom['end_date'] : (isset($data['end_date']) ? $data['end_date'] : $model->get('end_date'));
        if ($end_date != '') {
            $model->set('end_date', $end_date);
        } else {
            if (isset($custom['end_date']) || isset($data['end_date'])) {
                $model->set('end_date', null);
            }
        }

        $estimated_start_date = isset($custom['estimated_start_date']) ? $custom['estimated_start_date'] :
            (isset($data['estimated_start_date']) ? $data['estimated_start_date'] : $model->get('estimated_start_date'));
        if ($estimated_start_date != '') {
            $model->set('estimated_start_date', $estimated_start_date);
        } else {
            if (isset($custom['estimated_start_date']) || isset($data['estimated_start_date'])) {
                $model->set('estimated_start_date', null);
            }
        }

        $estimated_end_date = isset($custom['estimated_end_date']) ? $custom['estimated_end_date'] : (isset($data['estimated_end_date']) ? $data['estimated_end_date'] : $model->get('estimated_end_date'));
        if ($estimated_end_date != '') {
            $model->set('estimated_end_date', $estimated_end_date);
        } else {
            if (isset($custom['estimated_end_date']) || isset($data['estimated_end_date'])) {
                $model->set('estimated_end_date', null);
            }
        }

        $model->set('cost_projection_needed',
            isset($custom['cost_projection_needed']) ? $custom['cost_projection_needed'] : (isset($data['cost_projection_needed']) ? $data['cost_projection_needed'] : $model->get('cost_projection_needed'))
        );

        $model->set('archived',
            isset($custom['archived']) ? $custom['archived'] : (isset($data['archived']) ? $data['archived'] : $model->get('archived'))
        );

        $model->set('archived',
            isset($custom['archived']) ? $custom['archived'] : (isset($data['archived']) ? $data['archived'] : $model->get('archived'))
        );

        $customDestination = isset($custom['destination']) ? $custom['destination'] : null;
        $customBasic = isset($custom['basic']) ? $custom['basic'] : null;

        $destination_city = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_city', $customDestination), $model->getDestinationCity());
        if ($destination_city != '') {
            $model->set('destination_city', $destination_city);
        }
        $home_city = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_city', $customBasic), $model->getHomeCity());
        if ($home_city != '') {
            $model->set('home_city', $home_city);
        }

        $home_country_id = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_country_id', $customBasic), $model->getHomeCountryId());
        if ($home_country_id > 0) {
            $model->set('home_country_id', $home_country_id);
        }

        $destination_country_id = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_country_id', $customDestination), $model->getDestinationCountryId());
        if ($destination_country_id > 0) {
            $model->set('destination_country_id', $destination_country_id);
        }

        $home_city_geonameid = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('home_city_geonameid', $customDestination), $model->getHomeCityGeonameid());
        if ($home_city_geonameid > 0) {
            $model->setHomeCityGeonameid($home_city_geonameid);
        }

        $destination_city_geonameid = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('destination_city_geonameid', $customDestination), $model->getDestinationCityGeonameid());
        if ($destination_city_geonameid > 0) {
            $model->setDestinationCityGeonameid($destination_city_geonameid);
        }

        $resultSave = $model->__quickUpdate();

        return $resultSave;
    }

    /**
     * @return array
     */
    public function createAutoInitiationRequest()
    {
        $currentActiveContract = ModuleModel::$company->getActiveContract($this->getCompanyId());
        if ($currentActiveContract) {
            $assignmentRequest = new AssignmentRequest();
            $assignmentRequest->setData([
                'owner_company_id' => $this->getCompanyId(),
                'subject' => 'New Request',
                'message' => '',
                'sent_at' => date('Y-m-d H:i:s'),
                'assignment_id' => $this->getId(),
                'company_id' => ModuleModel::$company->getId(),
                'contract_id' => $currentActiveContract->getId(),
                'user_profile_id' => ModuleModel::$user_profile->getId(),
                'status' => AssignmentRequest::STATUS_ACCEPTED
            ]);

            $resultInsert = $assignmentRequest->__quickCreate();
            if ($resultInsert['success'] == false) {
                $return = [
                    'success' => false,
                    'errorType' => 'cannotCreateRequest',
                    'message' => 'REQUEST_SENT_FAIL_TEXT',
                ];
                return $return;
            }
            /*** all initiated **/

            $this->setIsInitiated(ModelHelper::YES);
            $this->setCreateRelocationStatus(Assignment::CREATE_RELOCATION_IN_PROGRESS);
            $resultUpdate = $this->__quickUpdate();
            if ($resultUpdate['success'] == false) {
                $return = [
                    'success' => false,
                    'details' => $resultUpdate,
                    'errorType' => 'cannotUpdateAssignment',
                    'message' => 'REQUEST_SENT_FAIL_TEXT',
                ];
                return $return;
            }

            return ['success' => true];
        }
        return ['success' => false];
    }

    /**
     * Get label for a given status
     * @param $status
     * @return mixed|string|null
     */
    public static function __getStatusLabel($status)
    {
        $statusName = null;
        $statusText = self::$status_text[$status];
        if ($statusText) {
            $lang = ModuleModel::$language ? ModuleModel::$language : 'en';
            $statusName = ConstantHelper::__translate($statusText, $lang) ?
                ConstantHelper::__translate($statusText, $lang) : $statusText;
        }
        return $statusName;
    }

    /**
     * Get label for a given status
     * @param $status
     * @return mixed|string|null
     */
    public function getCompletedLabelTranslate()
    {
        $lang = ModuleModel::$language ? ModuleModel::$language : 'en';

        return ConstantHelper::__translate((new self)::COMPLETED_TEXT, $lang) ?
            ConstantHelper::__translate((new self)::COMPLETED_TEXT, $lang) : (new self)::COMPLETED_TEXT;

    }

    /**
     * @return array
     */
    public function getFolders()
    {
        $employee = $this->getEmployee();
        if (!$employee) {
            return [
                "success" => false,
                "detail" => "employee not exist"
            ];
        }
        $object_folder_dsp = ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: and hr_company_id is null and employee_id is null and dsp_company_id = :dsp_company_id:",
            "bind" => [
                "uuid" => $this->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId()
            ]
        ]);
        $object_folder_dsp_ee = ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: and hr_company_id is null and employee_id = :employee_id: and dsp_company_id = :dsp_company_id:",
            "bind" => [
                "uuid" => $this->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId(),
                "employee_id" => $employee->getId()
            ]
        ]);
        $object_folder_dsp_hr = ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: and hr_company_id = :hr_company_id: and employee_id is null and dsp_company_id = :dsp_company_id:",
            "bind" => [
                "uuid" => $this->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId(),
                "hr_company_id" => $employee->getCompanyId()
            ]
        ]);
        return [
            "success" => true,
            'object_folder_dsp' => $object_folder_dsp,
            'object_folder_dsp_ee' => $object_folder_dsp_ee,
            'object_folder_dsp_hr' => $object_folder_dsp_hr
        ];
    }

    /**
     * @return \Phalcon\Mvc\Model\ResultInterface|\Reloday\Application\Models\ObjectFolder
     */
    public function getHrFolder()
    {
        $employee = $this->getEmployee();
        $object_folder_dsp_hr = ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: and hr_company_id = :hr_company_id: and employee_id is null and dsp_company_id = :dsp_company_id:",
            "bind" => [
                "uuid" => $this->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId(),
                "hr_company_id" => $employee->getCompanyId()
            ]
        ]);
        return $object_folder_dsp_hr;
    }

    /**
     *
     */
    public function getMyDspFolder()
    {
        return ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: and hr_company_id is null and employee_id is null and dsp_company_id = :dsp_company_id:",
            "bind" => [
                "uuid" => $this->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId()
            ]
        ]);
    }

    /**
     * @param $params
     * @param $orders
     * @return array
     */
    public static function __executeReport($params, $orders = [])
    {
        $queryString = "SELECT DISTINCT ass.id AS asgt_id, ass.uuid AS asgt_uuid, ass.company_id AS hr_company, ";
        $queryString .= " hrcompany.name AS hr_company_name, bcompany.name AS booker_company_name, ass.reference AS asgt_reference,";
        $queryString .= " ee.marital_status AS marital_status, concat(de.firstname, ' ', de.lastname) AS partner_name, ec.citizenship_names AS partner_citizenship, ";
        $queryString .= " ass.estimated_start_date AS estimated_start_date,";
        $queryString .= " ass.estimated_end_date AS estimated_end_date, ass.effective_start_date AS effective_start_date,";
        $queryString .= " ass.end_date AS effective_end_date, ab.home_country_id AS home_country, ab.home_city AS home_city,";
        $queryString .= " ad.destination_country_id AS destination_country, ad.destination_city AS destination_city,";
        $queryString .= " ad.destination_hr_office_id AS destination_office, ad.destination_job_title AS destination_job_title,";
        $queryString .= " ab.departure_hr_office_id AS home_hr_office, ";
        $queryString .= " home_hr_name.field_value AS home_hr_name,";
        $queryString .= " home_hr_email.field_value AS home_hr_email,";
        $queryString .= " home_hr_phone.field_value AS home_hr_phone,";
        $queryString .= " ad.destination_hr_office_id AS destination_hr_office,";
        $queryString .= " destination_hr_name.field_value AS destination_hr_name,";
        $queryString .= " destination_hr_email.field_value AS destination_hr_email,";
        $queryString .= " destination_hr_phone.field_value AS destination_hr_phone,";
        $queryString .= " CONCAT(ee.firstname, ' ', ee.lastname) AS assignee_name, ee.workemail AS assignee_email,";
        $queryString .= " ee.phonework AS assignee_phone, homecountry.name AS home_country_name, destinationcountry.name AS destination_country_name,";
        $queryString .= " hr_owner.field_value AS hr_assignment_owner_name,";
        $queryString .= " dpoffice.name AS home_hr_office_name,";
        $queryString .= " destoffice.name AS destination_office_name, cast(creator.fullname as json) AS dsp_reporter_name,";
        $queryString .= " CONCAT(owner.firstname, ' ', owner.lastname) AS dsp_owner_name, cast(dataviewer.fullname as json) AS dsp_viewers_name,";
        $queryString .= " rc.identify AS relocation_number, ass.order_number AS order_number, ass.created_at AS assignment_created_date,";
        $queryString .= " case when ass.is_terminated = true then '" . Constant::__translateConstant('COMPLETED_TEXT', ModuleModel::$language) . "'  else '" . Constant::__translateConstant('ACTIVE_TEXT', ModuleModel::$language) . "' end as status, ";
        $queryString .= " ad.destination_job_grade AS destination_job_grade, ";
        $queryString .= " ee.reference AS ee_reference ";
        $queryString .= " FROM assignment as ass ";
        //home_hr_name
        $queryString .= " LEFT JOIN (select concat(contact.firstname, ' ', contact.lastname) as field_value, assignment_company_data.assignment_id ";
        $queryString .= " from assignment_company_data join contact on cast(contact.id as varchar) = assignment_company_data.field_value";
        $queryString .= " where assignment_company_data.field_name = 'home_hr_contact_id' and contact.id is not null and assignment_company_data.company_id = " . ModuleModel::$company->getId() . ") as home_hr_name on home_hr_name.assignment_id = ass.id";
        //home_hr_email
        $queryString .= " LEFT JOIN (select assignment_company_data.field_value, assignment_company_data.assignment_id";
        $queryString .= " from assignment_company_data where assignment_company_data.field_name = 'home_hr_email' and assignment_company_data.company_id = " . ModuleModel::$company->getId() . ")";
        $queryString .= " as home_hr_email on home_hr_email.assignment_id = ass.id";
        //home_hr_phone
        $queryString .= " LEFT JOIN (select assignment_company_data.field_value, assignment_company_data.assignment_id";
        $queryString .= " from assignment_company_data where assignment_company_data.field_name = 'home_hr_phone' and assignment_company_data.company_id = " . ModuleModel::$company->getId() . ")";
        $queryString .= " as home_hr_phone on home_hr_phone.assignment_id = ass.id";
        //destination_hr_name
        $queryString .= " LEFT JOIN (select concat(contact.firstname, ' ', contact.lastname) as field_value, assignment_company_data.assignment_id";
        $queryString .= " from assignment_company_data join contact on cast(contact.id as varchar) = assignment_company_data.field_value";
        $queryString .= " where assignment_company_data.field_name = 'destination_hr_contact_id' and contact.id  is not null and assignment_company_data.company_id = " . ModuleModel::$company->getId() . ") as destination_hr_name on destination_hr_name.assignment_id = ass.id";
        //destination_hr_email
        $queryString .= " LEFT JOIN (select assignment_company_data.field_value, assignment_company_data.assignment_id";
        $queryString .= " from assignment_company_data where assignment_company_data.field_name = 'destination_hr_email' and assignment_company_data.company_id = " . ModuleModel::$company->getId() . ")";
        $queryString .= " as destination_hr_email on destination_hr_email.assignment_id = ass.id";
        //destination_hr_phone
        $queryString .= " LEFT JOIN (select assignment_company_data.field_value, assignment_company_data.assignment_id";
        $queryString .= " from assignment_company_data where assignment_company_data.field_name = 'destination_hr_phone' and assignment_company_data.company_id = " . ModuleModel::$company->getId() . ")";
        $queryString .= " as destination_hr_phone on destination_hr_phone.assignment_id = ass.id ";

        $queryString .= " JOIN assignment_in_contract AS aic ON aic.assignment_id = ass.id ";
        $queryString .= " JOIN contract AS c ON c.id = aic.contract_id";
        $queryString .= " JOIN employee AS ee ON ass.employee_id = ee.id";
        $queryString .= " JOIN company AS hrcompany ON ass.company_id = hrcompany.id";
        $queryString .= " LEFT JOIN company AS bcompany ON ass.booker_company_id = bcompany.id";
        $queryString .= " LEFT JOIN assignment_basic AS ab ON ab.id = ass.id";
        $queryString .= " LEFT JOIN assignment_destination AS ad ON ad.id = ass.id";
        $queryString .= " LEFT JOIN country AS homecountry ON homecountry.id = ab.home_country_id";
        $queryString .= " LEFT JOIN country AS destinationcountry ON destinationcountry.id = ad.destination_country_id";
        //partner
        $queryString .= " left join ( select assignment_dependant.assignment_id, dependant.firstname,";
        $queryString .= " dependant.lastname, dependant.id from assignment_dependant join dependant";
        $queryString .= " on dependant.id = assignment_dependant.dependant_id";
        $queryString .= " where dependant.status = 1 and dependant.relation='1')";
        $queryString .= " as de on de.assignment_id = ass.id ";


        $queryString .= " LEFT JOIN (select array_agg(employee_citizenship.name) as citizenship_names,employee_citizenship.id  from (with dataset as (select case when citizenships is not null and citizenships <> '' then cast(json_parse(citizenships) as array(varchar))  else cast(json_parse('[" . "\"\"" . "]') as array(varchar)) end as citizenship_array, id from dependant ) select t.citizenship, dataset.id, nationality.name from dataset CROSS JOIN UNNEST(dataset.citizenship_array) as t(citizenship) join nationality on nationality.code = t.citizenship group by dataset.id, t.citizenship, nationality.name) as employee_citizenship  group by employee_citizenship.id) AS ec ON ec.id = de.id ";
        $queryString .= " LEFT JOIN office AS dpoffice ON dpoffice.id = ab.departure_hr_office_id";
        $queryString .= " LEFT JOIN office AS destoffice ON destoffice.id = ad.destination_hr_office_id";
        //viewers
        $queryString .= " LEFT JOIN (select array_agg(concat(user_profile.firstname, ' ',user_profile.lastname)) as fullname, data_user_member.object_uuid from user_profile join data_user_member";
        $queryString .= " on data_user_member.user_profile_uuid = user_profile.uuid where data_user_member.company_id= " . ModuleModel::$company->getId();
//        $queryString .= " on data_user_member.user_profile_uuid = user_profile.uuid where data_user_member.company_id= 32 ";
        $queryString .= " and data_user_member.member_type_id = 6 group by data_user_member.object_uuid) as dataviewer on dataviewer.object_uuid = ass.uuid";

        $queryString .= " LEFT JOIN (select array_agg(concat(user_profile.firstname, ' ',user_profile.lastname)) as fullname, data_user_member.object_uuid from user_profile join data_user_member";
        $queryString .= " on data_user_member.user_profile_uuid = user_profile.uuid where data_user_member.company_id= " . ModuleModel::$company->getId();
        $queryString .= " and data_user_member.member_type_id = 5 group by data_user_member.object_uuid, user_profile.firstname, user_profile.lastname) as creator on creator.object_uuid = ass.uuid";

        // hr owner
        $queryString .= " LEFT JOIN (select concat(contact.firstname, ' ', contact.lastname) as field_value, assignment_company_data.assignment_id ";
        $queryString .= " from assignment_company_data join contact on cast(contact.id as varchar) = assignment_company_data.field_value";
        $queryString .= " where assignment_company_data.field_name = 'hr_contact_id' and contact.id is not null and assignment_company_data.company_id = " . ModuleModel::$company->getId() . ") as hr_owner on hr_owner.assignment_id = ass.id";

        //dsp owner
        $queryString .= " LEFT JOIN data_user_member AS dataowner ON dataowner.object_uuid = ass.uuid AND dataowner.member_type_id = 2 AND dataowner.company_id = " . ModuleModel::$company->getId();
        $queryString .= " LEFT JOIN user_profile AS owner ON dataowner.user_profile_uuid = owner.uuid and owner.company_id = " . ModuleModel::$company->getId();

        $queryString .= " LEFT JOIN relocation AS rc ON rc.assignment_id = ass.id and rc.active = 1 and rc.creator_company_id = " . ModuleModel::$company->getId() . " ";
//        $queryString .= " LEFT JOIN relocation AS rc ON rc.assignment_id = ass.id and rc.active = 1 and rc.creator_company_id = 32 ";
        $queryString .= " WHERE (c.to_company_id = " . ModuleModel::$company->getId() . ") AND (ass.archived = false)";
//        $queryString .= " WHERE (c.to_company_id = 32) AND (ass.archived = false)";
        if (isset($params['companyIds']) && is_array($params['companyIds']) && !empty($params['companyIds'])) {
            $index_companyid = 0;
            foreach ($params['companyIds'] as $companyId) {
                if ($index_companyid == 0) {
                    $queryString .= " AND (ass.company_id = " . $companyId . "";

                } else {
                    $queryString .= " OR ass.company_id = " . $companyId . "";
                }
                $index_companyid += 1;
            }
            if ($index_companyid > 0) {
                $queryString .= ") ";
            }
        }

        if (isset($params['assigneeIds']) && is_array($params['assigneeIds']) && !empty($params['assigneeIds'])) {
            $index_assigneeId = 0;
            foreach ($params['assigneeIds'] as $assigneeId) {
                if ($index_assigneeId == 0) {
                    $queryString .= " AND (ass.employee_id = " . $assigneeId . "";

                } else {
                    $queryString .= " OR ass.employee_id = " . $assigneeId . "";
                }
                $index_assigneeId += 1;
            }
            if ($index_assigneeId > 0) {
                $queryString .= ") ";
            }
        }

        if (isset($params['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'ass.company_id',
                'ASSIGNEE_ARRAY_TEXT' => 'ass.employee_id',
                'OWNER_ARRAY_TEXT' => 'dataowner.user_profile_id',
                'BOOKER_ARRAY_TEXT' => 'ass.booker_company_id',
                'ORIGIN_ARRAY_TEXT' => 'homecountry.id',
                'DESTINATION_ARRAY_TEXT' => 'destinationcountry.id',
                'STATUS_TEXT' => 'ass.is_terminated',
                'ESTIMATED_START_DATE_TEXT' => 'ass.estimated_start_date',
                'ESTIMATED_END_DATE_TEXT' => 'ass.estimated_end_date',
                'EFFECTIVE_START_DATE_TEXT' => 'ass.effective_start_date',
                'EFFECTIVE_END_DATE_TEXT' => 'ass.end_date',
                'CREATED_ON_TEXT' => 'ass.created_at',
            ];

            $dataType = [
                'STATUS_TEXT' => 'boolean',
            ];

            Helpers::__addFilterConfigConditionsQueryString($queryString, $params['filter_config_id'], $params['is_tmp'], FilterConfigExt::ASSIGNMENT_EXTRACT_FILTER_TARGET, $tableField, $dataType);
        }

        $queryString .= " GROUP BY ass.id, ass.uuid, ass.company_id, hrcompany.name, bcompany.name, ass.reference, ass.is_terminated,";
        $queryString .= " ee.marital_status, ab.partner_name, ab.partner_nationality_code, ass.estimated_start_date,";
        $queryString .= " ass.estimated_end_date, ass.effective_start_date, ass.end_date, ab.home_country_id, ab.home_city, ad.destination_country_id,";
        $queryString .= " ad.destination_city, ad.destination_hr_office_id, ad.destination_job_title, ab.departure_hr_office_id, ab.home_hr_name,";
        $queryString .= " ab.home_hr_email, ab.home_hr_phone, ad.destination_hr_office_id, ad.destination_hr_name, ad.destination_hr_email,";
        $queryString .= " ee.firstname, ee.lastname, ee.workemail, ee.phonework, homecountry.name, destinationcountry.name, hr_owner.field_value,";
        $queryString .= " dpoffice.name, destoffice.name, creator.fullname, owner.firstname, owner.lastname,";
        $queryString .= " dataviewer.fullname, rc.identify, ass.order_number, ass.created_at, de.firstname, de.lastname, ec.citizenship_names, ";
        $queryString .= " home_hr_name.field_value, home_hr_email.field_value, home_hr_phone.field_value, destination_hr_name.field_value, destination_hr_email.field_value, destination_hr_phone.field_value, ad.destination_job_grade, ee.reference ";

        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY assignment_created_date ASC ";
                } else {
                    $queryString .= " ORDER BY assignment_created_date DESC ";
                }
            } else if ($order['field'] == "employee_name") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY assignee_name ASC ";
                } else {
                    $queryString .= " ORDER BY assignee_name DESC ";
                }
            } else {
                $queryString .= " ORDER BY assignment_created_date DESC";
            }


        } else {
            $queryString .= " ORDER BY assignment_created_date DESC";
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
    public function getCompanyCustomData()
    {
        $data = [];
        $customFields = $this->getAssignmentCompanyCustomFields();
        if ($customFields->count()) {
            foreach ($customFields as $customField) {
                if (in_array($customField->getFieldName(), AssignmentCompanyData::$fields)) {
                    $data[$customField->getFieldName()] = $customField->getFieldValue();
                }
            }
        }
        return $data;
    }

    /**
     * get assignment company data items
     * @return array
     */
    public function getDataItems()
    {
        $data = [];
        $items = $this->getAssignmentCompanyDataItems([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId()
            ]
        ]);
        foreach ($items as $item) {

            $data[$item->getFieldName()] = $item->getFieldValue();

            if (is_numeric($item->getFieldValue())) {
                if (intval($item->getFieldValue()) == $item->getFieldValue()) {
                    $data[$item->getFieldName()] = intval($item->getFieldValue());
                } else {
                    $data[$item->getFieldName()] = floatval($item->getFieldValue());
                }
            }
        }
        return $data;
    }

    /**
     * @param $fieldName
     * @return array
     */
    public function getDataField($fieldName)
    {
        $items = $this->getAssignmentCompanyDataItems([
            'conditions' => 'company_id = :company_id: AND field_name = :field_name:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'field_name' => $fieldName
            ]
        ]);


        if ($items && $items->count() > 0) {
            $item = $items->getFirst();
            return $item->getFieldValue();
        };
    }

    /**
     * @param $options
     * @param $orders
     * @return array
     */
    public static function __getAssignmentOngoing($options = [], $orders = []){
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
        $queryBuilder->where("DataUserMember.user_profile_uuid = :user_profile_uuid: and DataUserMember.member_type_id = " . DataUserMember::MEMBER_TYPE_OWNER, [
            'user_profile_uuid' => $options['user_profile_uuid']
        ]);
        $queryBuilder->andwhere('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andwhere('Contract.from_company_id = Assignment.company_id');

        $queryBuilder->andwhere('Contract.status = :contract_active:', [
            'contract_active' => Contract::STATUS_ACTIVATED
        ]);

        $queryBuilder->andwhere("Assignment.archived = :archived:", [
            'archived' => Assignment::ARCHIVED_NO
        ]);

        $queryBuilder->andwhere('Assignment.is_terminated = :is_terminate_no:', [
            'is_terminate_no' => ModelHelper::NO
        ]);

        $created_at_period = isset($options['created_at_period']) && $options['created_at_period'] != '' ?$options['created_at_period'] : null;

        if ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_month') {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
        } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '3_months') {
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
        } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '6_months') {
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
        } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_year') {
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
        }

        $queryBuilder->groupBy('Assignment.id');
        $queryBuilder->orderBy(['Assignment.created_at DESC']);

        try {
            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => $options['limit'] ?: 1,
                'page' => $options['page'] ?: 1
            ]);
            $paginate = $paginator->getPaginate();
            $assignmentArray = [];
//            foreach ($paginate->items as $item) {
//                $assignmentArray[] = [
//                    'id' => $item->getId(),
//                    'name' => $item->getNumber(),
//                    'uuid' => $item->getUuid(),
//                    'employee_name' => $item->getEmployee()->getFullname(),
//                    'is_terminated' => $item->getIsTerminated(),
//                    'employee_uuid' => $item->getEmployee()->getUuid(),
//                    'company_name' => $item->getCompany()->getName(),
//                    'state' => $item->getFrontendState(),
//                    'assignment_type' => ($item->getAssignmentType()) ? $item->getAssignmentType()->getName() : "",
//                    'worker_status_label' => $item->getWorkerStatus($options['user_profile_uuid']),
//                    'approval_status' => $item->approval_status,
//                ];
//            }
            $return = ['success' => true, 'count' => $paginate->total_items];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        return $return;
    }
}



