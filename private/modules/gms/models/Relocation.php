<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;

use PhpParser\Node\Expr\BinaryOp\Mod;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\BackgroundActionHelpers;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\ReportLogHelper;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ServicePack;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Task;

use Phalcon\Mvc\Model\Transaction\Failed as TransationFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

use Reloday\Application\Lib\Helpers;

use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class Relocation extends \Reloday\Application\Models\RelocationExt
{

    const LIMIT_PER_PAGE = 20;

    protected $currentOwner;

    protected $currentReporter;

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('assignment_id', '\Reloday\Gms\Models\Assignment', 'id', [
            'alias' => 'Assignment',
        ]);

        $this->belongsTo('service_pack_id', '\Reloday\Gms\Models\ServicePack', 'id', [
            'alias' => 'ServicePack',
            'cache' => [
                'key' => 'SERVICE_PACK_' . $this->getServicePackId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->belongsTo('employee_id', '\Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee',
            'cache' => [
                'key' => 'EMPLOYEE_' . $this->getEmployeeId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->hasMany('id', '\Reloday\Gms\Models\RelocationServiceCompany', 'relocation_id', [
            'alias' => 'RelocationServiceCompany',
        ]);

        $this->hasMany('id', '\Reloday\Gms\Models\RelocationServiceCompany', 'relocation_id', [
            'alias' => 'RelocationServiceCompanies',
            'params' => [
                'conditions' => 'status = ' . RelocationServiceCompany::STATUS_ACTIVE,
            ],
        ]);

        $this->belongsTo('manage_user_profile_id', '\Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'ManagingContact'
        ]);

        $this->belongsTo('manage_user_profile_id', '\Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'ManageUserProfile'
        ]);

        $this->belongsTo('hr_company_id', '\Reloday\Gms\Models\Company', 'id', [
            'alias' => 'HrCompany'
        ]);

        $this->belongsTo('creator_company_id', '\Reloday\Gms\Models\Company', 'id', [
            'alias' => 'CreatorCompany',
            'cache' => [
                'key' => 'COMPANY_' . $this->getCreatorCompanyId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->hasOne('assignment_id', '\Reloday\Gms\Models\AssignmentDestination', 'id', [
            'alias' => 'AssignmentDestination'
        ]);

        $this->hasOne('assignment_id', '\Reloday\Gms\Models\AssignmentBasic', 'id', [
            'alias' => 'AssignmentBasic'
        ]);

        $this->hasMany('id', '\Reloday\Gms\Models\RelocationServiceCompany', 'relocation_id', [
            'alias' => 'RelocationServiceCompany'
        ]);

        $this->hasManyToMany(
            'id', '\Reloday\Gms\Models\RelocationServiceCompany',
            'relocation_id', 'service_company_id',
            '\Reloday\Gms\Models\ServiceCompany', 'id', [
                'alias' => 'ServiceCompany',
                'params' => [
                    'conditions' => '[\Reloday\Gms\Models\RelocationServiceCompany].status = ' . RelocationServiceCompany::STATUS_ACTIVE,
                ]
            ]
        );

        $this->hasManyToMany(
            'uuid', 'Reloday\Gms\Models\DataUserMember',
            'object_uuid', 'user_profile_uuid',
            'Reloday\Gms\Models\UserProfile', 'uuid', [
                'alias' => 'Members',
                'params' => [
                    'distinct' => 'Reloday\Gms\Models\UserProfile.id',
                    'group' => 'Reloday\Gms\Models\UserProfile.id'
                ]
            ]
        );
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
                    'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = :type_owner: AND Reloday\Gms\Models\DataUserMember.company_id = :company_id:',
                    'bind' => [
                        'type_owner' => DataUserMember::MEMBER_TYPE_REPORTER,
                        'company_id' => ModuleModel::$company->getId()
                    ],
                    'order' => 'Reloday\Gms\Models\DataUserMember.created_at DESC',
                ]
            ]
        );

        /**
         * get properties proposed
         */
        $this->hasManyToMany(
            'id',
            'Reloday\Gms\Models\HousingProposition',
            'relocation_id', 'property_id',
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

        $this->belongsTo('workflow_id', 'Reloday\Gms\Models\Workflow', 'id', [
            'alias' => 'Workflow',
            'cache' => [
                'key' => 'WORKFLOW_' . $this->getWorkflowId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->hasMany('id', '\Reloday\Gms\Models\RelocationGuide', 'relocation_id', [
            'alias' => 'RelocationGuides',
        ]);

        /**
         * get properties proposed
         */
        $this->hasManyToMany(
            'id',
            'Reloday\Gms\Models\InvoiceQuote',
            'relocation_id', 'id',
            'Reloday\Gms\Models\Payment', 'invoice_quote_id',
            [
                'alias' => 'PaymentInvoiceQuotes',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\Payment.is_deleted = :is_deleted_no:',
                    'bind' => [
                        'is_deleted_no' => ModelHelper::NO,
                    ]
                ]
            ]
        );

        /**
         * get properties proposed
         */
        $this->hasManyToMany(
            'id',
            'Reloday\Gms\Models\Expense',
            'relocation_id', 'id',
            'Reloday\Gms\Models\Payment', 'expense_id',
            [
                'alias' => 'PaymentExpenses',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\Payment.is_deleted = :is_deleted_no:',
                    'bind' => [
                        'is_deleted_no' => ModelHelper::NO,
                    ]
                ]
            ]
        );


        /**
         * get properties proposed
         */
        $this->hasMany(
            'id',
            'Reloday\Gms\Models\InvoiceableItem',
            'relocation_id',
            [
                'alias' => 'InvoiceableItems',
            ]
        );

    }

    /**
     * @param string $more_condition
     * @return array
     */
    public static function loadList($more_condition = '')
    {
        $result = [];
        $countries_arr = [];

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
            $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
            $queryBuilder->distinct(true);
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Employee', 'Relocation.employee_id = Employee.id', 'Employee');
            $queryBuilder->leftjoin('\Reloday\Gms\Models\EmployeeInContract', 'EmployeeInContract.employee_id = Employee.id', 'EmployeeInContract');
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.id = EmployeeInContract.contract_id', 'Contract');
            $queryBuilder->where('Contract.to_company_id = ' . $company->getId());
            $queryBuilder->andwhere('Relocation.active = ' . self::STATUS_ACTIVATED);
            $queryBuilder->andwhere('Assignment.archived <> ' . Assignment::ARCHIVED_YES);
            $queryBuilder->orderBy("Relocation.identify ASC");
            $relocations = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));

            return [
                'success' => true,
                'data' => $relocations,
            ];
        }

    }


    /**
     * @param string $more_condition
     * @return array
     */
    public static function simpleList($more_condition = '')
    {
        $result = [];
        $countries_arr = [];

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
            $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
            $queryBuilder->distinct(true);
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Employee', 'Relocation.employee_id = Employee.id', 'Employee');
            $queryBuilder->leftjoin('\Reloday\Gms\Models\EmployeeInContract', 'EmployeeInContract.employee_id = Employee.id', 'EmployeeInContract');
            $queryBuilder->leftjoin('\Reloday\Gms\Models\Contract', 'Contract.id = EmployeeInContract.contract_id', 'Contract');
            $queryBuilder->where('Contract.to_company_id = ' . $company->getId());
            $queryBuilder->andwhere('Relocation.active = ' . self::STATUS_ACTIVATED);
            $queryBuilder->andwhere('Assignment.archived <> ' . Assignment::ARCHIVED_YES);
            $queryBuilder->orderBy("Relocation.identify ASC");
            $queryBuilder->columns('Relocation.id, Relocation.identify, Relocation.uuid, Relocation.name');
            $relocations = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));

            return [
                'success' => true,
                'data' => $relocations,
            ];
        }

    }

    /**
     * @param string $more_condition
     * @return array
     */
    public static function countActive($more_condition = '')
    {
        $result = [];
        $countries_arr = [];

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
            $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
            $queryBuilder->distinct(true);
            $queryBuilder->join('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
            $queryBuilder->where('Relocation.creator_company_id = ' . $company->getId());
            $queryBuilder->andwhere('Relocation.active = ' . self::STATUS_ACTIVATED);
            $queryBuilder->andwhere('Assignment.archived <> ' . Assignment::ARCHIVED_YES);
            $queryBuilder->andwhere('Relocation.status <> ' . self::STATUS_STARTING_SOON);
            $queryBuilder->andwhere('Relocation.status <> ' . self::STATUS_CANCELED);
            $queryBuilder->andwhere('Relocation.status <> ' . self::STATUS_TERMINATED);
            $queryBuilder->columns('count(Relocation.id) as number_active');
            $count = $queryBuilder->getQuery()->setUniqueRow(true)->execute()->number_active;


            return $count;
        }

    }

    /**
     * @param $custom
     */
    public function __setData($custom = array())
    {
        ModelHelper::__setData($this, $custom);
        /** -- MANAGING CONTACTS --*/
        $managing_contact = Helpers::__getRequestValueWithCustom('managing_contact', $custom);
        $managing_contact_id = Helpers::__getRequestValueWithCustom('managing_contact_id', $custom);
        $manage_user_profile_id = Helpers::__getRequestValueWithCustom('manage_user_profile_id', $custom);
        $manage_user_profile_id = Helpers::__coalesce($managing_contact, $manage_user_profile_id, $managing_contact_id);
        if (is_numeric($manage_user_profile_id) && $manage_user_profile_id > 0) {
            $this->setManageUserProfileId($manage_user_profile_id);
        }
        /** --- SERVICEPACK --- **/
        $service_pack_id = Helpers::__getRequestValueWithCustom('service_pack_id', $custom);
        if (is_numeric($service_pack_id) && $service_pack_id > 0) {
            $this->setServicePackId($service_pack_id);
        } else {
            $this->setServicePackId(null);
        }
        /** @var company_id $hr_company_id */
        $company_id = Helpers::__getRequestValueWithCustom('company_id', $custom);
        $hr_company_id = Helpers::__getRequestValueWithCustom('hr_company_id', $custom);
        $hr_company_id = Helpers::__coalesce($hr_company_id, $company_id, $this->getHrCompanyId());
        if ($hr_company_id == null) {
            $hr_company_id = $this->getEmployee() ? $this->getEmployee()->getCompany()->getId() : null;
        }
        if ($hr_company_id > 0) {
            $this->setHrCompanyId($hr_company_id);
        }
        /*****CREATOR COMPANY ID***/
        $creator_company_id = ModuleModel::$company->getId();
        $this->setCreatorCompanyId($creator_company_id);
        /*****EMPLOYEE***/
        $employee_id = Helpers::__getRequestValueWithCustom('employee_id', $custom);
        $employee_id = Helpers::__coalesce($employee_id, $this->getEmployeeId());
        $this->setEmployeeId((int)$employee_id);
        /*****UUID***/
        if ($this->getUuid() == '') {
            $random = new Random();
            $uuid = $random->uuid();
            $this->setUuid($uuid);
        }
        /*****ASSIGNMENT***/

        $assignment_id = Helpers::__getRequestValueWithCustom('assignment_id', $custom);
        $assignment_id = Helpers::__coalesce($assignment_id, $this->getAssignmentId());
        $this->setAssignmentId((int)$assignment_id);

        /****NAME**/
        $name = Helpers::__getRequestValueWithCustom('name', $custom);
        $name = Helpers::__coalesce($name, $this->getName());
        $this->setName($name);
        /**** START-DATE **/
        $start_date = Helpers::__getRequestValueWithCustom('start_date', $custom);
        $start_date = Helpers::__coalesce($start_date, $this->getStartDate());
        if ($start_date != '' && strtotime($start_date) > 0) {
            $this->setStartDate($start_date);
        } else {
            $this->setStartDate(null);
        }
        /****END-DATE**/
        $end_date = Helpers::__getRequestValueWithCustom('end_date', $custom);
        $end_date = Helpers::__coalesce($end_date, $this->getEndDate());
        if ($end_date != '' && strtotime($end_date) > 0) {
            $this->setEndDate($end_date);
        } else {
            $this->setEndDate(null);
        }
        /**employee_can_view**/
        $employee_can_view = Helpers::__getRequestValueWithCustom('employee_can_view', $custom);
        $employee_can_view = Helpers::__coalesce($employee_can_view, $this->getEmployeeCanView());
        $this->setEmployeeCanView((int)(bool)$employee_can_view);

        /***ACTIVE**/
        $active = Helpers::__getRequestValueWithCustom('active', $custom);
        $active = Helpers::__coalesce($active, $this->getActive());
        if ($active == '' || is_null($active)) {
            $active = self::STATUS_ACTIVATED;
        }
        $this->setActive((int)$active);
        /***STATUS**/
        $status = Helpers::__getRequestValueWithCustom('status', $custom);
        $status = Helpers::__coalesce($status, $this->getStatus());
        if ($status !== self::STATUS_TERMINATED &&
            $active != self::STATUS_PENDING &&
            $active != self::STATUS_ARCHIVED
        ) {
            if (date_create('now') < $this->getStartDate()) {
                $datetime1 = date_create('now');
                $datetime2 = date_create($this->getStartDate());
                $interval = date_diff($datetime1, $datetime2);
                $date_difference = $interval->format('%a');
                if ((int)$date_difference <= 30) {
                    $status = self::STATUS_STARTING_SOON;
                } else {
                    $status = self::STATUS_INITIAL;
                }
            } elseif (date_create('now') < $this->getEndDate()) {
                $datetime1 = date_create('now');
                $datetime2 = date_create($this->getEndDate());
                $interval = date_diff($datetime1, $datetime2);
                $date_difference = $interval->format('%a');
                if ((int)$date_difference <= 30) {
                    $status = self::STATUS_ENDING_SOON;
                } else {
                    $status = self::STATUS_ONGOING;
                }
            }
        }
        $this->setStatus((int)$status);
    }

    /**
     * save relocation
     * @param array $custom [description]
     * @return [type]         [description]
     */
    public function __save($custom = [])
    {
        $model = $this;
        $req = new Request();
        if ($req->isPut()) {
            // Request update
            if ($model->getUuid() == '') {
                $uuid = Helpers::__getRequestValue('uuid');
                if ($uuid != '') $model = self::findFirstByUuid($uuid);
            }
            if (!$model instanceof $this) {
                return [
                    'success' => false,
                    'message' => 'RELOCATION_NOT_FOUND_TEXT',
                    'detail' => []
                ];
            }
        }

        /** -- MANAGING CONTACTS --*/
        $managing_contact = Helpers::__getRequestValueWithCustom('managing_contact', $custom);
        $managing_contact_id = Helpers::__getRequestValueWithCustom('managing_contact_id', $custom);
        $manage_user_profile_id = Helpers::__getRequestValueWithCustom('manage_user_profile_id', $custom);
        $manage_user_profile_id = Helpers::__coalesce($managing_contact, $manage_user_profile_id, $managing_contact_id);
        if (is_numeric($manage_user_profile_id) && $manage_user_profile_id > 0) {
            $model->setManageUserProfileId($manage_user_profile_id);
        }
        /** --- SERVICEPACK --- **/
        $service_pack_id = Helpers::__getRequestValueWithCustom('service_pack_id', $custom);
        if (is_numeric($service_pack_id) && $service_pack_id > 0) {
            $model->setServicePackId($service_pack_id);
        } else {
            $model->setServicePackId(null);
        }
        /** @var company_id $hr_company_id */
        $company_id = Helpers::__getRequestValueWithCustom('company_id', $custom);
        $hr_company_id = Helpers::__getRequestValueWithCustom('hr_company_id', $custom);
        $hr_company_id = Helpers::__coalesce($hr_company_id, $company_id, $model->getHrCompanyId());
        if ($hr_company_id == null) {
            $hr_company_id = $model->getEmployee() ? $model->getEmployee()->getCompany()->getId() : null;
        }
        if ($hr_company_id > 0) {
            $model->setHrCompanyId($hr_company_id);
        }
        /*****CREATOR COMPANY ID***/
        $creator_company_id = ModuleModel::$company->getId();
        $model->setCreatorCompanyId($creator_company_id);
        /*****EMPLOYEE***/
        $employee_id = Helpers::__getRequestValueWithCustom('employee_id', $custom);
        $employee_id = Helpers::__coalesce($employee_id, $model->getEmployeeId());
        $model->setEmployeeId((int)$employee_id);
        /*****UUID***/
        if ($model->getUuid() == '') {
            $random = new Random();
            $uuid = $random->uuid();
            $model->setUuid($uuid);
        }
        /*****ASSIGNMENT***/

        $assignment_id = Helpers::__getRequestValueWithCustom('assignment_id', $custom);
        $assignment_id = Helpers::__coalesce($assignment_id, $model->getAssignmentId());
        $model->setAssignmentId((int)$assignment_id);

        /****NAME**/
        $name = Helpers::__getRequestValueWithCustom('name', $custom);
        $name = Helpers::__coalesce($name, $model->getName());
        $model->setName($name);
        /**** START-DATE **/
        $start_date = Helpers::__getRequestValueWithCustom('start_date', $custom);
        $start_date = Helpers::__coalesce($start_date, $model->getStartDate());
        if ($start_date != '' && strtotime($start_date) > 0) {
            $model->setStartDate($start_date);
        } else {
            $model->setStartDate(null);
        }
        /****END-DATE**/
        $end_date = Helpers::__getRequestValueWithCustom('end_date', $custom);
        $end_date = Helpers::__coalesce($end_date, $model->getEndDate());
        if ($end_date != '' && strtotime($end_date) > 0) {
            $model->setEndDate($end_date);
        } else {
            $model->setEndDate(null);
        }
        /**employee_can_view**/
        $employee_can_view = Helpers::__getRequestValueWithCustom('employee_can_view', $custom);
        $employee_can_view = Helpers::__coalesce($employee_can_view, $model->getEmployeeCanView());
        $model->setEmployeeCanView((int)(bool)$employee_can_view);

        /***ACTIVE**/
        $active = Helpers::__getRequestValueWithCustom('active', $custom);
        $active = Helpers::__coalesce($active, $model->getActive());
        if ($active == '' || is_null($active)) {
            $active = self::STATUS_ACTIVATED;
        }
        $model->setActive((int)$active);
        /***SELF SERVICE***/
        $self_service = Helpers::__getRequestValueWithCustom('self_service', $custom);
        $self_service = Helpers::__coalesce($self_service, $model->getSelfService());

        if (!is_bool($self_service) && ($self_service === '' || is_null($self_service))) {
            $self_service = $model->getSelfService();
        }
        $model->setSelfService((int)$self_service);
        /***STATUS**/
        $status = Helpers::__getRequestValueWithCustom('status', $custom);
        $status = Helpers::__coalesce($status, $model->getStatus());
        if ($status !== self::STATUS_TERMINATED &&
            $active != self::STATUS_PENDING &&
            $active != self::STATUS_ARCHIVED
        ) {
            if (date_create('now') < $model->getStartDate()) {
                $datetime1 = date_create('now');
                $datetime2 = date_create($model->getStartDate());
                $interval = date_diff($datetime1, $datetime2);
                $date_difference = $interval->format('%a');
                if ((int)$date_difference <= 30) {
                    $status = self::STATUS_STARTING_SOON;
                } else {
                    $status = self::STATUS_INITIAL;
                }
            } elseif (date_create('now') < $model->getEndDate()) {
                $datetime1 = date_create('now');
                $datetime2 = date_create($model->getEndDate());
                $interval = date_diff($datetime1, $datetime2);
                $date_difference = $interval->format('%a');
                if ((int)$date_difference <= 30) {
                    $status = self::STATUS_ENDING_SOON;
                } else {
                    $status = self::STATUS_ONGOING;
                }
            }
        }
        $model->setStatus((int)$status);

        return $model->__quickSave();
    }

    /**
     * [getCompany description]
     * @return [type] [description]
     */
    public function getCompany()
    {
        $employee = $this->getEmployee();
        if ($employee) {
            return $employee->getCompany();
        }
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if ($this->getCreatorCompanyId() != ModuleModel::$company->getId()) return false;

        $company = ModuleModel::$company;
        if ($company) {
            $contract = Contract::findFirst([
                "conditions" => "from_company_id = :from_company_id: AND to_company_id = :to_company_id: AND status = :status:",
                "bind" => [
                    "from_company_id" => $this->getHrCompanyId(),
                    "to_company_id" => $company->getId(),
                    "status" => Contract::STATUS_ACTIVATED,
                ]
            ]);
            if ($contract) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }


    /**
     * @param $assignment
     */
    static public function createFromAssignment($assignment)
    {

        $model = new self();
        $model->setEmployeeId($assignment->getEmployeeId());
        $model->setAssignmentId($assignment->getId());
        $model->setStatus(self::STATUS_PENDING);
        $model->setActive(self::STATUS_PENDING);
        $model->setHrCompanyId($assignment->getCompanyId());
        $model->setName($model->generateNumber());
        $model->setIdentify($model->generateNumber());

        //$identify = self::NAME_PREFIX. "-" . date('YmHis') . $assignment->getCompanyId();


        $model->setCreatorCompanyId(ModuleModel::$company->getId());
        $random = new Random();
        $uuid = $random->uuid();
        $model->setUuid($uuid);

        try {
            if ($model->save()) {
                //add services
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                //$transaction->rollback(implode(';', $model->getMessages()));
                $result = [
                    'success' => false,
                    'message' => 'SAVE_RELOCATION_FAIL_TEXT',
                    'raw' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_RELOCATION_FAIL_TEXT',
                'raw' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * [checkContract description]
     * @return [type] [description]
     */
    public function checkCompany()
    {
        return Contract::ifCompanyExistInContracts($this->getHrCompanyId());
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->getActive() == self::STATUS_ARCHIVED ? false : (
        $this->getAssignment()->getArchived() == Assignment::ARCHIVED_YES ? false : true
        );
    }

    /**
     * @param string $more_condition
     * @return array
     */
    public static function getAllTasksOfServices($relocation_id, $fullInfo = false)
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->distinct(true);
        $queryBuilder->addFrom('\Reloday\Gms\Models\Task', 'Task');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.uuid = Task.object_uuid', 'RelocationServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Relocation', 'RelocationServiceCompany.relocation_id = Relocation.id', 'Relocation');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceCompany', 'RelocationServiceCompany.service_company_id = ServiceCompany.id', 'ServiceCompany');
        $queryBuilder->orderBy("Task.number ASC");
        $queryBuilder->where('Relocation.id = :relocation_id:', ['relocation_id' => $relocation_id]);
        $bindArray = [];
        $bindArray['relocation_id'] = $relocation_id;

        $queryBuilder->andWhere('RelocationServiceCompany.status = :relocation_service_active:', ['relocation_service_active' => RelocationServiceCompany::STATUS_ACTIVE]);
        $bindArray['relocation_service_active'] = RelocationServiceCompany::STATUS_ACTIVE;

        $queryBuilder->andWhere('Task.status = :task_active:', ['task_active' => Task::STATUS_ACTIVE]);

        $queryBuilder->andWhere('Task.task_type = :task_type:', ['task_type' => Task::TASK_TYPE_INTERNAL_TASK]);

        $bindArray['task_active'] = Task::STATUS_ACTIVE;
        $bindArray['task_type'] = Task::TASK_TYPE_INTERNAL_TASK;

        $queryBuilder->columns("Task.uuid, Task.object_uuid, Task.status, Task.progress, Task.number, Task.name, ServiceCompany.name as service_name");
        if ($fullInfo == false) {
            $tasks = $di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray);
        }
        return [
            'success' => true,
            'data' => $tasks,
        ];
    }


    /**
     * @return string
     */
    public function getFrontendUrl()
    {
        return ModuleModel::$app->getFrontendUrl() . "/#/app/relocation/dashboard/" . $this->getUuid();
    }

    /**
     * @return string
     */
    public function getNumber()
    {
        return $this->getIdentify();
    }

    /**
     * @return string
     */
    public function getFrontendState($options = "")
    {
        return "app.relocation.dashboard({uuid:'" . $this->getUuid() . "',  ". $options ."})";
    }

    /**
     * add Owner
     * @param $profile
     */
    public function addCreator($profile)
    {
        $result = DataUserMember::__addCreatorWithUUID($this->getUuid(), $profile, $this->getSource());
        if ($result['success'] == true) return true;
    }

    /**
     * add Owner
     * @param $profile
     */
    public function addOwner($profile)
    {
        $result = DataUserMember::__addOwnerWithUUID($this->getUuid(), $profile, $this->getSource());
        if ($result['success'] == true) {
            $this->currentOwner = $profile;
            return true;
        } else {
            return false;
        }
    }

    /**
     * add Owner
     * @param $profile
     */
    public function addReporter($profile)
    {
        $result = DataUserMember::__addReporterWithUUID($this->getUuid(), $profile, $this->getSource());
        if ($result['success'] == true) {
            $this->currentReporter = $profile;
            return true;
        } else {
            return false;
        }
    }

    /**
     * add default owner with managing contact
     * @return bool
     */
    public function addDefaultOwner()
    {
        $managingContact = $this->getManageUserProfile();
        if ($managingContact) {
            $result = DataUserMember::__addOwnerWithUUID($this->getUuid(), $managingContact, $this->getSource());
            if ($result === true || $result['success'] == true) {
                $this->currentOwner = $managingContact;
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * get object of owner
     */
    public function getDataOwner()
    {
        return $this->getOwners()->getFirst();
    }

    /**
     * get object of owner
     */
    public function getOwner()
    {
        return $this->getOwners()->getFirst();
    }

    /**
     * get object of owner
     */
    public function getDataReporter()
    {
        return $this->getReporters()->getFirst();
    }

    /**
     * @return array
     */
    public function afterSave()
    {
        parent::afterSave(); // TODO: Change the autogenerated stub
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
     * @param $query
     * @return mixed
     */
    public function getActiveRelocationServiceCompany($query = null)
    {

        $bindArray = [];
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Relocation', 'RelocationServiceCompany.relocation_id = Relocation.id', 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceCompany', 'RelocationServiceCompany.service_company_id = ServiceCompany.id', 'ServiceCompany');
        $queryBuilder->where('Relocation.id = :relocation_id:', [
            'relocation_id' => $this->getId()
        ]);
        $queryBuilder->andWhere('RelocationServiceCompany.status = :status_active: ', [
            'status_active' => RelocationServiceCompany::STATUS_ACTIVE,
        ]);

        if ($query) {
            $queryBuilder->andWhere('RelocationServiceCompany.name like :query: OR RelocationServiceCompany.number like :query: OR ServiceCompany.name like :query:', [
                'query' => '%' . $query . '%',
            ]);
        }
        return $queryBuilder->getQuery()->execute();

    }

    /**
     * @param $service_company_id
     * @return mixed
     */
    public function getRelocationServiceCompanyOfServive($service_company_id)
    {
        return $this->getRelocationServiceCompany([
            'conditions' => 'service_company_id = :service_company_id:',
            'bind' => [
                'service_company_id' => $service_company_id
            ]
        ])->getFirst();
    }

    /**
     * add services from service pack;
     * @param $service_pack_id
     * @return array
     */
    public function addServicesFromServicePack($service_pack_id)
    {
        if ($service_pack_id > 0) {
            $service_packOjbect = ServicePack::findFirstById($service_pack_id);
            if ($service_packOjbect) {
                $services = $service_packOjbect->getServiceCompany();
                return $this->addServices($services);
            }
        }
    }

    /**
     * @return array
     */
    public function getCurrentServiceIds()
    {
        $ids = [];
        $services = $this->getServiceCompany();
        foreach ($services as $service) {
            $ids[] = $service->getId();
        }
        return $ids;
    }

    /**
     * add Services
     * @param array $services
     * @return array
     */
    public function addServices($services = array())
    {


        $services = (array)$services;
        $selectedServiceIds = [];
        $currentSelectedServiceIds = $this->getCurrentServiceIds();
        $relocationServiceCompanies = [];

        if (is_array($services) && count($services) > 0) {
            $serviceArray = [];
            foreach ($services as $serviceItem) {

                if (is_object($serviceItem)) {
                    $serviceItem = (array)$serviceItem;
                }

                if (isset($serviceItem['service_company_id']) && $serviceItem['service_company_id'] && isset($serviceItem['uuid']) && $serviceItem['uuid']) {

                    $serviceCompany = ServiceCompany::findFirstById($serviceItem['service_company_id']);

                    if ($serviceCompany && $serviceCompany->belongsToGms()) {

                        $ownerProfileId = isset($serviceItem['owner']) && isset($serviceItem['owner']['id']) && Helpers::__isValidId($serviceItem['owner']['id']) ? $serviceItem['owner']['id'] : false;
                        $resultAddService = $this->addServiceItem($serviceCompany, $serviceItem, $ownerProfileId, true); //owner existed\

                        if ($resultAddService['success'] == false) {
                            $resultAddService['errorType'] = "addServiceItemErrorType";
                            return $resultAddService;
                        } else {
                            /** @var method add template task $beanQueue */
                            $relocationServiceCompany = $resultAddService['data'];
                            $relocationServiceCompany->startAddAttachment();
                            $relocationServiceCompany->startAddWorkflow();
                            $relocationServiceCompanies[] = $relocationServiceCompany;
                        }
                    }
                }
            }
        }

        $result = [
            'success' => true,
            'message' => 'CREATE_RELOCATION_SERVICE_SUCCESS_TEXT',
            'data' => $relocationServiceCompanies
        ];
        return $result;

    }

    /**
     * @param $serviceId
     * @return mixed
     */
    public function removeServiceItem($serviceId)
    {
        $bindArray = [
            "relocation_id" => $this->getId(),
            "service_company_id" => $serviceId
        ];
        $relocation_service_company = RelocationServiceCompany::findFirst([
            "conditions" => "relocation_id = :relocation_id: AND service_company_id = :service_company_id:",
            "bind" => $bindArray
        ]);

        if ($relocation_service_company) {
            return $relocation_service_company->__quickRemove();
        }
        return ['success' => true];
    }

    /**
     * @param $service_company
     * @return array
     */
    public function addServiceItem($serviceCompany, $customData, $owner_profile_id = 0, $ownerIsExisted = false)
    {
        $bindArray = [
            "relocation_id" => $this->getId(),
            "service_company_id" => $serviceCompany->getId()
        ];

        $relocation_service_company = RelocationServiceCompany::findFirst([
            "conditions" => "relocation_id = :relocation_id: AND service_company_id = :service_company_id:",
            "bind" => $bindArray
        ]);

        if (!$relocation_service_company) {
            $serviceName = isset($customData['description']) && $customData['description'] ? $customData['description'] : $customData['service_name'];
            $relocation_service_company = new RelocationServiceCompany();
            $relocation_service_company->setServiceCompanyId($serviceCompany->getId());
            $relocation_service_company->setRelocationId($this->getId());
            $relocation_service_company->setName($serviceName);
            $relocation_service_company->setUuid($customData['uuid']);

            $resultAddService = $relocation_service_company->__quickCreate();

            if ($resultAddService['success'] == true) {
                /*** add reporter ***/
                $defaultReporterProfile = $this->getDataReporter();
                /** should be Current User */
                $defaultReporterProfile = ModuleModel::$user_profile;
                if (!$defaultReporterProfile) {
                    $defaultReporterProfile = $this->getTempoCurrentReporter();
                }
                if ($defaultReporterProfile && is_object($defaultReporterProfile)) {
                    $resultAddReporter = $relocation_service_company->addReporter($defaultReporterProfile);
                    if ($resultAddReporter['success'] == false) {
                        return ['success' => false];
                    }
                }
                /*** add owner ***/

                /** user profile */
                if ($ownerIsExisted == false) {
                    $defaultOwnerProfile = false;
                    if ($owner_profile_id > 0) {
                        $defaultOwnerProfile = UserProfile::findFirstByIdCache($owner_profile_id, CacheHelper::__TIME_5_MINUTES);
                        if (!($defaultOwnerProfile && $defaultOwnerProfile->belongsToGms())) {
                            $defaultOwnerProfile = false;
                        }
                    }
                    if (!$defaultOwnerProfile) {
                        $defaultOwnerProfile = $this->getDataOwner();
                    }
                    if (!$defaultOwnerProfile) {
                        $defaultOwnerProfile = $this->getTempoCurrentOwner();
                    }

                    if ($defaultOwnerProfile && is_object($defaultOwnerProfile)) {
                        $resultAddOwner = $relocation_service_company->addOwner($defaultOwnerProfile);
                        if ($resultAddOwner['success'] == false) {
                            return ['success' => false];
                        }
                    }
                }
                return $resultAddService;
            } else {
                $result = [
                    'success' => false,
                    'message' => 'CREATE_RELOCATION_SERVICE_FAIL_TEXT',
                    'data' => $relocation_service_company
                ];
                return $result;
            }
        } else {

            if ($relocation_service_company && $relocation_service_company->isArchived()) {
                $relocation_service_company->setStatus(RelocationServiceCompany::STATUS_ACTIVE);
                return $relocation_service_company->__quickUpdate();
            }

            $result = [
                'success' => true,
                'message' => 'CREATE_RELOCATION_SERVICE_SUCCESS_TEXT',
                'data' => $relocation_service_company
            ];
            return $result;
        }

    }

    /**
     * before create
     */
    public function beforeCreate()
    {
        /**
         * set start date of relocation in creation
         */
        if (($this->getStartDate() == '') || ($this->getStartDate() == null)) {
            if ($this->getAssignment() && $this->getAssignment()->getEffectiveStartDate() != '') {
                $this->setStartDate($this->getAssignment()->getEffectiveStartDate());
            }
        }

        /**
         * set end date of relocation in creation
         */

        //var_dump($this->getEndDate() ); die();

        if (($this->getEndDate() == '') || ($this->getEndDate() == null)) {
            if ($this->getAssignment() && $this->getAssignment()->getEndDate() != '') {
                $this->setEndDate($this->getAssignment()->getEndDate());
            }
        }
        parent::beforeCreate();
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
        $queryBuilder->leftjoin('\Reloday\Gms\Models\NeedFormGabaritServiceCompany', 'NeedFormGabarit.id = NeedFormGabaritServiceCompany.need_form_gabarit_id', 'NeedFormGabaritServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = NeedFormGabaritServiceCompany.service_company_id', 'ServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.service_company_id = ServiceCompany.id', 'RelocationServiceCompany');
        $queryBuilder->where('RelocationServiceCompany.relocation_id = :relocation_id:');
        $queryBuilder->andWhere('RelocationServiceCompany.status = :status_relocation_service_company_active:');
        $queryBuilder->andWhere('NeedFormGabarit.status = :status_active:');
        $queryBuilder->andWhere('NeedFormGabaritServiceCompany.is_deleted <> :is_deleted_yes:');
        $queryBuilder->orderBy("NeedFormGabarit.number ASC");
        try {
            $relocations = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), [
                'relocation_id' => $this->getId(),
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
     * @param $options
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array(), $fullinfo = false)
    {

        $bindArray = [];
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentBasic', 'AssignmentBasic.id = Assignment.id', 'AssignmentBasic');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentDestination', 'AssignmentDestination.id = Assignment.id', 'AssignmentDestination');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Assignment.company_id = HrCompany.id', 'HrCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'Assignment.booker_company_id = BookerCompany.id', 'BookerCompany');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Assignment.employee_id = Employee.id', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'HomeCountry.id = Assignment.home_country_id', 'HomeCountry');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'DestinationCountry.id = Assignment.destination_country_id', 'DestinationCountry');

        if (isset($options['is_count_all']) && $options['is_count_all'] == true) {
            $queryBuilder->columns([
                'Relocation.id',
            ]);
        }

        $queryBuilder->where('Relocation.creator_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Assignment.archived = :assignment_not_archived:', [
            'assignment_not_archived' => Assignment::ARCHIVED_NO
        ]);

        //$queryBuilder->andwhere('Relocation.hr_company_id = Assignment.company_id');
        //$queryBuilder->andwhere('Employee.company_id = Assignment.company_id');
        //$queryBuilder->andwhere('Contract.from_company_id = Assignment.company_id');

        $queryBuilder->andwhere('Contract.status = :contract_active:', [
            'contract_active' => Contract::STATUS_ACTIVATED
        ]);

        if (isset($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Relocation.uuid', 'DataUserMember');
            $queryBuilder->andwhere("DataUserMember.user_profile_uuid = :user_profile_uuid:", ["user_profile_uuid" => $options['user_profile_uuid']]);
            $bindArray['user_profile_uuid'] = $options['user_profile_uuid'];

            if (isset($options['is_owner']) && $options['is_owner'] == true) {
                $queryBuilder->andWhere('DataUserMember.member_type_id = :_member_type_owner:', [
                    '_member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
                ]);
            }
        }

        if (isset($options['created_at_period'])) {
            if (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '1_month') {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '3_months') {
                $created_at = date('Y-m-d', strtotime('- 3 months'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '6_months') {
                $created_at = date('Y-m-d', strtotime('- 6 months'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '1_year') {
                $created_at = date('Y-m-d', strtotime('- 1 year'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            }
        }


        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Assignment.employee_id = :employee_id:", ["employee_id" => $options['employee_id']]);
            $bindArray['employee_id'] = $options['employee_id'];
        }

        if (isset($options['inlive']) && is_bool($options['inlive']) && $options['inlive'] == true) {
            $queryBuilder->andwhere("Relocation.end_date IS NULL OR Relocation.end_date >= :current_date: OR Relocation.status = :status_ongoing: OR Relocation.status = :status_todo:", [
                'current_date' => date('Y-m-d H:i:s'),
                'status_ongoing' => self::STATUS_ONGOING,
                'status_todo' => self::STATUS_INITIAL,
            ]);
        }
        /*
        if (isset($options['active']) &&
            ($options['active'] === self::STATUS_ACTIVATED || $options['active'] === self::STATUS_ARCHIVED)) {
            $queryBuilder->andwhere('Relocation.active = :status_relocation_active:', [
                'status_relocation_active' => self::STATUS_ACTIVATED
            ]);
        }*/


        if (isset($options['isArchived']) && is_bool($options['isArchived']) && $options['isArchived'] == true) {
            $queryBuilder->andwhere('Relocation.active = :status_relocation_archived:', [
                'status_relocation_archived' => self::STATUS_ARCHIVED
            ]);
        } else {
            $queryBuilder->andwhere('Relocation.active = :status_relocation_active:', [
                'status_relocation_active' => self::STATUS_ACTIVATED
            ]);
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

        if (isset($options['start_date']) && Helpers::__isDate($options['start_date'])) {
            $queryBuilder->andwhere("Relocation.start_date >= :start_date_begin:", [
                'start_date_begin' => Helpers::__getDateBegin($options['start_date']),
                //'start_date_end' => Helpers::__getNextDate($options['start_date']),
            ]);
        }


        if (isset($options['end_date']) && Helpers::__isDate($options['end_date'])) {
            $queryBuilder->andwhere("Relocation.end_date < :end_date_end:", [
                //'end_date_begin' => Helpers::__getDateBegin($options['end_date']),
                'end_date_end' => Helpers::__getNextDate($options['end_date']),
            ]);
        }

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->andwhere("Relocation.status IN ({statuses:array})", [
                'statuses' => $options['statuses'],
            ]);
        }

        if (isset($options['status']) && is_numeric($options['status'])) {
            $queryBuilder->andwhere("Relocation.status = :status:", [
                'status' => $options['status'],
            ]);
        }

        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners']) > 0) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataOwner.object_uuid = Relocation.uuid', 'DataOwner');
            $queryBuilder->andwhere("DataOwner.user_profile_uuid IN ({owners:array}) AND DataOwner.member_type_id = :member_type_owner:", [
                'owners' => $options['owners'],
                'member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
            ]);
        }


        if (isset($options['bookers']) && is_array($options['bookers']) && count($options['bookers'])) {
            $queryBuilder->andwhere("Assignment.booker_company_id IN ({bookers:array} )",
                [
                    'bookers' => $options['bookers']
                ]
            );
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Relocation.name LIKE :query: OR Employee.firstname LIKE :query: OR Employee.lastname LIKE :query: OR Employee.workemail LIKE :query:
             OR CONCAT(Employee.firstname,' ',Employee.lastname) LIKE :query:  OR HrCompany.name LIKE :query:  OR BookerCompany.name LIKE :query:
             OR HomeCountry.name LIKE :query: OR HomeCountry.cio LIKE :query:
             OR DestinationCountry.name LIKE :query: OR DestinationCountry.cio LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['owner_uuid']) && Helpers::__isValidUuid($options['owner_uuid']) && $options['owner_uuid'] != '' && ModuleModel::$user_profile->isAdminOrManager() == true) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwner.object_uuid = Relocation.uuid', 'DataUserOwner');
            $queryBuilder->andwhere("DataUserOwner.user_profile_uuid = :user_profile_uuid: and DataUserOwner.member_type_id = :_member_type_owner:", [
                "user_profile_uuid" => $options['owner_uuid'],
                "_member_type_owner" => DataUserMember::MEMBER_TYPE_OWNER
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
                'STATUS_ARRAY_TEXT' => 'Relocation.status',
                'START_DATE_TEXT' => 'Relocation.start_date',
                'END_DATE_TEXT' => 'Relocation.end_date',
                'CREATED_ON_TEXT' => 'Relocation.created_at',
                'PREFIX_DATA_OWNER_TYPE' => 'Owner.member_type_id',
                'PREFIX_DATA_REPORTER_TYPE' => 'Reporter.member_type_id',
                'PREFIX_OBJECT_UUID' => 'Relocation.uuid',
                'SERVICE_ARRAY_TEXT' => 'RelocationServiceCompany.service_company_id',
                'ARCHIVING_DATE_TEXT' => 'Relocation.updated_at',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::RELOCATION_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        if (count($orders)) {

            if (isset($options['isArchived']) && is_bool($options['isArchived']) && $options['isArchived'] == true) {
                $queryBuilder->orderBy("Relocation.updated_at DESC");
            }else{
                $queryBuilder->orderBy("Relocation.id DESC");
            }

            $order = reset($orders);

            if ($order['field'] == "reference") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.identify ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.identify DESC']);
                }
            }

            if ($order['field'] == "employee") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC', 'Employee.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC', 'Employee.lastname DESC']);
                }
            }

            if ($order['field'] == "company") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['HrCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['HrCompany.name DESC']);
                }
            }

            if ($order['field'] == "origin") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['HomeCountry.name ASC']);
                } else {
                    $queryBuilder->orderBy(['HomeCountry.name DESC']);
                }
            }

            if ($order['field'] == "destination") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['DestinationCountry.name ASC']);
                } else {
                    $queryBuilder->orderBy(['DestinationCountry.name DESC']);
                }
            }

            if ($order['field'] == "start_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.start_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.start_date DESC']);
                }
            }

            if ($order['field'] == "end_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.end_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.end_date DESC']);
                }
            }

            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.status ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.status DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.id ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.id DESC']);
                }
            }
            if ($order['field'] == "relocation_created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.created_at DESC']);
                }
            }

            if ($order['field'] == "archiving_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.updated_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Relocation.id DESC");
        }
        $queryBuilder->groupBy('Relocation.id');

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : (
        isset($options['length']) && is_numeric($options['length']) && $options['length'] > 0 ? $options['length'] : self::LIMIT_PER_PAGE);

        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }


        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $relocation_array = [];

            if (isset($options['is_count_all']) && $options['is_count_all'] == true) {
                goto end_of_return;
            }

            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $relocation) {
                    $assignment = $relocation->getAssignment();
                    $assignment_destination = $relocation->getAssignmentDestination();
                    $assignment_basic = $relocation->getAssignmentBasic();
                    $employee = $relocation->getEmployee();
                    $relocation_array[$relocation->getUuid()]['services_count'] = count($relocation->getRelocationServiceCompanies());
                    $relocation_array[$relocation->getUuid()]['name'] = $relocation->getNumber() . " " . $employee->getFullname() . " " . $relocation->getHrCompany()->getName();
                    $relocation_array[$relocation->getUuid()]['uuid'] = $relocation->getUuid();
                    $relocation_array[$relocation->getUuid()]['id'] = (int)$relocation->getId();
                    $relocation_array[$relocation->getUuid()]['number'] = $relocation->getNumber();
                    $relocation_array[$relocation->getUuid()]['start_date'] = $relocation->getStartDate();
                    $relocation_array[$relocation->getUuid()]['end_date'] = $relocation->getEndDate();
                    $relocation_array[$relocation->getUuid()]['status'] = intval($relocation->getStatus());
                    $relocation_array[$relocation->getUuid()]['active'] = intval($relocation->getActive());
                    $relocation_array[$relocation->getUuid()]['created_at'] = $relocation->getCreatedAt();
                    $relocation_array[$relocation->getUuid()]['updated_at'] = $relocation->getUpdatedAt();

                    $relocation_array[$relocation->getUuid()]['destination_country'] = ($assignment_destination && $assignment_destination->getDestinationCountry() ? $assignment_destination->getDestinationCountry()->getName() : "");
                    $relocation_array[$relocation->getUuid()]['destination_country_iso2'] = ($assignment_destination && $assignment_destination->getDestinationCountry() ? $assignment_destination->getDestinationCountry()->getCioFlag() : "");
                    $relocation_array[$relocation->getUuid()]['home_country'] = ($assignment_basic && $assignment_basic->getHomeCountry() ? $assignment_basic->getHomeCountry()->getName() : "");

                    $relocation_array[$relocation->getUuid()]['destination_country_flag'] = ($assignment_destination && $assignment_destination->getDestinationCountry() ? $assignment_destination->getDestinationCountry()->getCio() : "");
                    $relocation_array[$relocation->getUuid()]['home_country_flag'] = ($assignment_basic && $assignment_basic->getHomeCountry() ? $assignment_basic->getHomeCountry()->getCio() : "");
                    $relocation_array[$relocation->getUuid()]['home_country_iso2'] = ($assignment_basic && $assignment_basic->getHomeCountry() ? $assignment_basic->getHomeCountry()->getCioFlag() : "");


                    $relocation_array[$relocation->getUuid()]['assignment']['number'] = $assignment->getNumber();
                    $relocation_array[$relocation->getUuid()]['assignment']['order_number'] = $assignment->getOrderNumber();
                    $relocation_array[$relocation->getUuid()]['assignment']['uuid'] = $assignment->getUuid();
                    $relocation_array[$relocation->getUuid()]['assignment']['hr_assignment_owner_id'] = 0;
                    $relocation_array[$relocation->getUuid()]['assignment']['hr_owner'] = "";
                    $data_user_member_assignment_owner = DataUserMember::findFirst([
                        'conditions' => 'object_uuid = :object_uuid: AND member_type_id = :member_type_id: AND company_id = :company_id:',
                        'order' => 'created_at DESC',
                        'bind' => [
                            'object_uuid' => $assignment->getUuid(),
                            'company_id' => intval($relocation->getHrCompanyId()),
                            'member_type_id' => DataUserMember::MEMBER_TYPE_OWNER
                        ]
                    ]);
                    if ($data_user_member_assignment_owner) {
                        $hr_owner = $data_user_member_assignment_owner->getUserProfile();
                        $relocation_array[$relocation->getUuid()]['assignment']['hr_assignment_owner_id'] = $hr_owner->getId();
                        $relocation_array[$relocation->getUuid()]['assignment']['hr_owner'] = $hr_owner;
                    }
                    $relocation_array[$relocation->getUuid()]['assignment']['state'] = $assignment->getFrontendState();
                    $relocation_array[$relocation->getUuid()]['employee_name'] = $employee->getFullname();
                    $relocation_array[$relocation->getUuid()]['employee_uuid'] = $employee->getUuid();
                    $relocation_array[$relocation->getUuid()]['employee_id'] = $employee->getId();
                    $relocation_array[$relocation->getUuid()]['employee_email'] = $employee->getWorkemail();
                    $relocation_array[$relocation->getUuid()]['employee_state'] = $employee->getFrontendState();
                    $relocation_array[$relocation->getUuid()]['company_name'] = $relocation->getHrCompany()->getName();

                    $relocation_array[$relocation->getUuid()]['booker_company_name'] = $assignment->getBookerCompany() ? $assignment->getBookerCompany()->getName() : null;
                    $relocation_array[$relocation->getUuid()]['booker_company_id'] = $assignment->getBookerCompany() ? $assignment->getBookerCompany()->getId() : null;
                    $relocation_array[$relocation->getUuid()]['hr_company_id'] = intval($relocation->getHrCompanyId());
                    $data_owner = $relocation->getDataOwner();
                    $relocation_array[$relocation->getUuid()]['owner_name'] = $data_owner ? $data_owner->getFullname() : '';
                    $relocation_array[$relocation->getUuid()]['owner_uuid'] = $data_owner ? $data_owner->getUuid() : '';
                    $relocation_array[$relocation->getUuid()]['owners'] = $relocation->getOwners();


                    $relocation_array[$relocation->getUuid()]['folders'] = $relocation->getFolders();

                    if (isset($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
                        $relocation_array[$relocation->getUuid()]['worker_status_label'] = $relocation->getWorkerStatus($options['user_profile_uuid']);
                    }
                }
            }

            end_of_return:
            return [
                'success' => true,
                '$start' => $start,
                '$limit' => $limit,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_pages' => $pagination->total_pages,
                'page' => $page,
                'data' => array_values($relocation_array),
                'sql' => $queryBuilder->getQuery()->getSql(),
            ];

        } catch (\Phalcon\Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param $options
     * @return array
     */
    public static function __findWithFilterInList($options = array(), $orders = array(), $fullinfo = false)
    {

        $bindArray = [];
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentBasic', 'AssignmentBasic.id = Assignment.id', 'AssignmentBasic');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentDestination', 'AssignmentDestination.id = Assignment.id', 'AssignmentDestination');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Assignment.company_id = HrCompany.id', 'HrCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'Assignment.booker_company_id = BookerCompany.id', 'BookerCompany');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Assignment.employee_id = Employee.id', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'HomeCountry.id = Assignment.home_country_id', 'HomeCountry');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'DestinationCountry.id = Assignment.destination_country_id', 'DestinationCountry');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'AssignmentDataUserOwner.object_uuid = Assignment.uuid and AssignmentDataUserOwner.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER, 'AssignmentDataUserOwner');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserProfile', 'AssignmentDataUserOwner.user_profile_uuid = AssignmentOwner.uuid', 'AssignmentOwner');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'RelocationDataUserMember.object_uuid = Relocation.uuid and RelocationDataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER , 'RelocationDataUserMember');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserProfile', 'RelocationDataUserMember.user_profile_uuid = RelocationOwner.uuid', 'RelocationOwner');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.relocation_id = Relocation.id and RelocationServiceCompany.status = ' . RelocationServiceCompany::STATUS_ACTIVE, 'RelocationServiceCompany');
        
        $queryBuilder->columns([
            'name_combination' => 'CONCAT(Relocation.identify, " ", Employee.firstname, " ", Employee.lastname, " ", HrCompany.name)',
            'services_count' => 'Count(DISTINCT RelocationServiceCompany.id)',
            'Relocation.uuid',
            'Relocation.id',
            'number' => 'Relocation.identify',
            'Relocation.start_date',
            'Relocation.end_date',
            'Relocation.status',
            'Relocation.active',
            'Relocation.created_at',
            'Relocation.updated_at',
            'destination_country' => 'DestinationCountry.name',
            'destination_country_iso2' => 'DestinationCountry.cio_flag',
            'destination_country_flag' => 'DestinationCountry.cio',
            'home_country' => 'HomeCountry.name',
            'home_country_iso2' => 'HomeCountry.cio_flag',
            'home_country_flag' => 'HomeCountry.cio',
            'assignment_number' => 'Assignment.reference',
            'assignment_order_number' => 'Assignment.order_number',
            'assignment_uuid' => 'Assignment.uuid',
            'assignment_hr_owner_id' => 'AssignmentOwner.id',
            'assignment_hr_owner_uuid' => 'AssignmentDataUserOwner.user_profile_uuid',
            'assignment_hr_owner_external_hris_id' => 'AssignmentOwner.external_hris_id',
            'assignment_hr_owner_firstname' => 'AssignmentOwner.firstname',
            'assignment_hr_owner_lastname' => 'AssignmentOwner.lastname',
            'assignment_hr_owner_nickname' => 'AssignmentOwner.nickname',
            'assignment_hr_owner_jobtitle' => 'AssignmentOwner.jobtitle',
            'assignment_hr_owner_phonework' => 'AssignmentOwner.phonework',
            'assignment_hr_owner_workemail' => 'AssignmentOwner.workemail',
            'owner_name' => 'CONCAT(RelocationOwner.firstname, " ", RelocationOwner.lastname)',
            'owner_uuid' => 'RelocationDataUserMember.user_profile_uuid',
            'owner_id' => 'RelocationOwner.id',
            'owner_external_hris_id' => 'RelocationOwner.external_hris_id',
            'owner_firstname' => 'RelocationOwner.firstname',
            'owner_lastname' => 'RelocationOwner.lastname',
            'owner_nickname' => 'RelocationOwner.nickname',
            'owner_jobtitle' => 'RelocationOwner.jobtitle',
            'owner_phonework' => 'RelocationOwner.phonework',
            'owner_workemail' => 'RelocationOwner.workemail',
            'employee_name' => 'CONCAT(Employee.firstname, " ", Employee.lastname)',
            'Relocation.employee_id',
            'employee_uuid' => 'Employee.uuid',
            'employee_email' => 'Employee.workemail',
            'company_name' => 'HrCompany.name',
            'hr_company_id' => 'HrCompany.id',
            'booker_company_name' => 'BookerCompany.name',
            'booker_company_id' => 'BookerCompany.id',
        ]);

        $queryBuilder->where('Relocation.creator_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Contract.to_company_id = :gms_company_id:');

        $queryBuilder->andwhere('Assignment.archived = :assignment_not_archived:', [
            'assignment_not_archived' => Assignment::ARCHIVED_NO
        ]);

        //$queryBuilder->andwhere('Relocation.hr_company_id = Assignment.company_id');
        //$queryBuilder->andwhere('Employee.company_id = Assignment.company_id');
        //$queryBuilder->andwhere('Contract.from_company_id = Assignment.company_id');

        $queryBuilder->andwhere('Contract.status = :contract_active:', [
            'contract_active' => Contract::STATUS_ACTIVATED
        ]);

        if (isset($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Relocation.uuid', 'DataUserMember');
            $queryBuilder->andwhere("DataUserMember.user_profile_uuid = :user_profile_uuid:", ["user_profile_uuid" => $options['user_profile_uuid']]);
            $bindArray['user_profile_uuid'] = $options['user_profile_uuid'];

            if (isset($options['is_owner']) && $options['is_owner'] == true) {
                $queryBuilder->andWhere('DataUserMember.member_type_id = :_member_type_owner:');
            }
        }

        if (isset($options['created_at_period'])) {
            if (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '1_month') {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '3_months') {
                $created_at = date('Y-m-d', strtotime('- 3 months'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '6_months') {
                $created_at = date('Y-m-d', strtotime('- 6 months'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif (isset($options['created_at_period']) && $options['created_at_period'] != null && is_string($options['created_at_period']) && $options['created_at_period'] == '1_year') {
                $created_at = date('Y-m-d', strtotime('- 1 year'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            }
        }


        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Assignment.employee_id = :employee_id:", ["employee_id" => $options['employee_id']]);
            $bindArray['employee_id'] = $options['employee_id'];
        }

        if (isset($options['inlive']) && is_bool($options['inlive']) && $options['inlive'] == true) {
            $queryBuilder->andwhere("Relocation.end_date IS NULL OR Relocation.end_date >= :current_date: OR Relocation.status = :status_ongoing: OR Relocation.status = :status_todo:", [
                'current_date' => date('Y-m-d H:i:s'),
                'status_ongoing' => self::STATUS_ONGOING,
                'status_todo' => self::STATUS_INITIAL,
            ]);
        }
        /*
        if (isset($options['active']) &&
            ($options['active'] === self::STATUS_ACTIVATED || $options['active'] === self::STATUS_ARCHIVED)) {
            $queryBuilder->andwhere('Relocation.active = :status_relocation_active:', [
                'status_relocation_active' => self::STATUS_ACTIVATED
            ]);
        }*/


        if (isset($options['isArchived']) && is_bool($options['isArchived']) && $options['isArchived'] == true) {
            $queryBuilder->andwhere('Relocation.active = :status_relocation_archived:', [
                'status_relocation_archived' => self::STATUS_ARCHIVED
            ]);
        } else {
            $queryBuilder->andwhere('Relocation.active = :status_relocation_active:', [
                'status_relocation_active' => self::STATUS_ACTIVATED
            ]);
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

        if (isset($options['start_date']) && Helpers::__isDate($options['start_date'])) {
            $queryBuilder->andwhere("Relocation.start_date >= :start_date_begin:", [
                'start_date_begin' => Helpers::__getDateBegin($options['start_date']),
                //'start_date_end' => Helpers::__getNextDate($options['start_date']),
            ]);
        }


        if (isset($options['end_date']) && Helpers::__isDate($options['end_date'])) {
            $queryBuilder->andwhere("Relocation.end_date < :end_date_end:", [
                //'end_date_begin' => Helpers::__getDateBegin($options['end_date']),
                'end_date_end' => Helpers::__getNextDate($options['end_date']),
            ]);
        }

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses']) > 0) {
            $queryBuilder->andwhere("Relocation.status IN ({statuses:array})", [
                'statuses' => $options['statuses'],
            ]);
        }

        if (isset($options['status']) && is_numeric($options['status'])) {
            $queryBuilder->andwhere("Relocation.status = :status:", [
                'status' => $options['status'],
            ]);
        }

        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners']) > 0) {
            $queryBuilder->andwhere("RelocationOwner.uuid IN ({owners:array})", [
                'owners' => $options['owners']
            ]);
        }


        if (isset($options['bookers']) && is_array($options['bookers']) && count($options['bookers'])) {
            $queryBuilder->andwhere("Assignment.booker_company_id IN ({bookers:array} )",
                [
                    'bookers' => $options['bookers']
                ]
            );
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Relocation.name LIKE :query: OR Employee.firstname LIKE :query: OR Employee.lastname LIKE :query: OR Employee.workemail LIKE :query:
             OR CONCAT(Employee.firstname,' ',Employee.lastname) LIKE :query:  OR HrCompany.name LIKE :query:  OR BookerCompany.name LIKE :query:
             OR HomeCountry.name LIKE :query: OR HomeCountry.cio LIKE :query:
             OR DestinationCountry.name LIKE :query: OR DestinationCountry.cio LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['owner_uuid']) && Helpers::__isValidUuid($options['owner_uuid']) && $options['owner_uuid'] != '' && ModuleModel::$user_profile->isAdminOrManager() == true) {
          
            $queryBuilder->andwhere("RelocationOwner.uuid = :user_profile_uuid:", [
                "user_profile_uuid" => $options['owner_uuid']
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
                'STATUS_ARRAY_TEXT' => 'Relocation.status',
                'START_DATE_TEXT' => 'Relocation.start_date',
                'END_DATE_TEXT' => 'Relocation.end_date',
                'CREATED_ON_TEXT' => 'Relocation.created_at',
                'PREFIX_DATA_OWNER_TYPE' => 'Owner.member_type_id',
                'PREFIX_DATA_REPORTER_TYPE' => 'Reporter.member_type_id',
                'PREFIX_OBJECT_UUID' => 'Relocation.uuid',
                'SERVICE_ARRAY_TEXT' => 'RelocationServiceCompanyFilter.service_company_id',
                'ARCHIVING_DATE_TEXT' => 'Relocation.updated_at',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::RELOCATION_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        if (count($orders)) {

            if (isset($options['isArchived']) && is_bool($options['isArchived']) && $options['isArchived'] == true) {
                $queryBuilder->orderBy("Relocation.updated_at DESC");
            }else{
                $queryBuilder->orderBy("Relocation.id DESC");
            }

            $order = reset($orders);

            if ($order['field'] == "reference") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.identify ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.identify DESC']);
                }
            }

            if ($order['field'] == "employee") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC', 'Employee.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC', 'Employee.lastname DESC']);
                }
            }

            if ($order['field'] == "company") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['HrCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['HrCompany.name DESC']);
                }
            }

            if ($order['field'] == "origin") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['HomeCountry.name ASC']);
                } else {
                    $queryBuilder->orderBy(['HomeCountry.name DESC']);
                }
            }

            if ($order['field'] == "destination") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['DestinationCountry.name ASC']);
                } else {
                    $queryBuilder->orderBy(['DestinationCountry.name DESC']);
                }
            }

            if ($order['field'] == "start_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.start_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.start_date DESC']);
                }
            }

            if ($order['field'] == "end_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.end_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.end_date DESC']);
                }
            }

            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.status ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.status DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.id ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.id DESC']);
                }
            }

            if ($order['field'] == "relocation_created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.id ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.id DESC']);
                }
            }

            if ($order['field'] == "archiving_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Relocation.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Relocation.updated_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Relocation.id DESC");
        }
        $queryBuilder->groupBy('Relocation.id');

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : (
        isset($options['length']) && is_numeric($options['length']) && $options['length'] > 0 ? $options['length'] : self::LIMIT_PER_PAGE);

        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }


        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $relocation_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $relocation) {
                    $relocation_item = $relocation->toArray();
                     $relocationObject = Relocation::__findFirstByUuidCache($relocation['uuid']);
//                    $relocation_item['services_count'] = count($relocationObject->getRelocationServiceCompanies());

                    $relocation_item['folders'] =  $relocationObject ? $relocationObject->getFolders() : [];

                    $relocation_item['assignment'] = [
                        'number' => $relocation['assignment_number'],
                        'order_number' => $relocation['assignment_order_number'],
                        'uuid' => $relocation['assignment_uuid'],
                        'hr_assignment_owner_id' => $relocation['assignment_hr_owner_id'],
                        'state' => "app.assignment.dashboard({uuid:'" .  $relocation['assignment_uuid'] ."})",
                        'hr_owner' => [
                            'uuid' => $relocation['assignment_hr_owner_uuid'],
                            'id' => $relocation['assignment_hr_owner_id'],
                            'external_hris_id' => $relocation['assignment_hr_owner_external_hris_id'],
                            'firstname' => $relocation['assignment_hr_owner_firstname'],
                            'lastname' => $relocation['assignment_hr_owner_lastname'],
                            'nickname' => $relocation['assignment_hr_owner_nickname'],
                            'jobtitle' => $relocation['assignment_hr_owner_jobtitle'],
                            'phonework' => $relocation['assignment_hr_owner_phonework'],
                            'workemail' => $relocation['assignment_hr_owner_workemail'],
                        ],
                        'owners' => [
                            [
                                'uuid' => $relocation['owner_uuid'],
                                'id' => $relocation['owner_id'],
                                'external_hris_id' => $relocation['owner_external_hris_id'],
                                'firstname' => $relocation['owner_firstname'],
                                'lastname' => $relocation['owner_lastname'],
                                'nickname' => $relocation['owner_nickname'],
                                'jobtitle' => $relocation['owner_jobtitle'],
                                'phonework' => $relocation['owner_phonework'],
                                'workemail' => $relocation['owner_workemail'],
                            ]
                        ],
                    ];
                    $relocation_array[] = $relocation_item;
                }
            }

            return [
                'success' => true,
                '$start' => $start,
                '$limit' => $limit,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_pages' => $pagination->total_pages,
                'page' => $page,
                'data' => $relocation_array,
               
            ];

        } catch (\Phalcon\Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @return mixed
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
    public function getTempoCurrentReporter()
    {
        return $this->currentReporter;
    }

    /**
     * @return mixed
     */
    public function getTempoCurrentOwner()
    {
        return $this->currentOwner;
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


        $object_folder_dsp_hr = ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: AND dsp_company_id = :dsp_company_id: AND hr_company_id = :hr_company_id: AND employee_id is NULL",
            "bind" => [
                "uuid" => $this->getAssignment()->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId(),
                "hr_company_id" => $this->getAssignment()->getCompanyId()
            ]
        ]);

        $object_folder_dsp_ee = ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: AND dsp_company_id = :dsp_company_id: AND employee_id = :employee_id: AND hr_company_id is NULL",
            "bind" => [
                "uuid" => $this->getAssignment()->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId(),
                "employee_id" => $employee->getId()
            ]
        ]);


        return [
            "success" => true,
            'object_folder_dsp' => $object_folder_dsp,
            'object_folder_dsp_hr' => $object_folder_dsp_hr,
            'object_folder_dsp_ee' => $object_folder_dsp_ee,
        ];
    }

    /**
     * @param $params
     * @param $orders
     * @return array
     */
    public static function __executeReport($params, $orders = [])
    {
        $queryString = "Select DISTINCT r.identify AS \"identify\", CONCAT(ee.firstname, ' ', ee.lastname) AS \"assignee_name\", ";
        $queryString .= " ee.workemail AS \"assignee_email\", ee.phonework AS \"assignee_phone\", ass.reference AS \"assignment_reference\",";
        $queryString .= " r.employee_id AS \"assignee_id\", r.start_date AS \"start_date\", r.end_date AS \"end_date\", cc.name AS \"hr_company_name\",";
        $queryString .= " booker.name AS \"booker_company_name\", creator.fullname AS \"dsp_reporter_name\",";
        $queryString .= " owner.fullname AS \"dsp_owner_name\",";
        $queryString .= " cast(viewer.fullname as json) AS \"dsp_viewers_name\",";
//        $queryString .= " r.status AS \"status\",";
        $queryString .= " case when r.status = 0 then '" . Constant::__translateConstant('NOT_STARTED_TEXT', ModuleModel::$language) .
            "' when r.status = 2 then '" . Constant::__translateConstant('ONGOING_TEXT', ModuleModel::$language) .
            "' when r.status = 4 then '" . Constant::__translateConstant('COMPLETED_TEXT', ModuleModel::$language) .
            "'  else '" . Constant::__translateConstant('CANCELLED_TEXT', ModuleModel::$language) . "' end as status,";
        $queryString .= " cast(map_agg(rsc.name, rsc.detail_short_name) as json) AS \"services_short_name\", cast(map_agg(rsc.name, rsc.detail_status) as json),";
        $queryString .= " r.created_at as relocation_created_date, cast(map_agg(cancel_rsc.name, cancel_rsc.detail_short_name) as json) AS cancel_services_name,";
        $queryString .= " homecountry.name AS home_country_name, destinationcountry.name AS destination_country_name,";
        $queryString .= " case when r.active = -1 then '" . Constant::__translateConstant('YES_TEXT', ModuleModel::$language) .
            "'  else '" . Constant::__translateConstant('NO_TEXT', ModuleModel::$language) . "' end as archived, ";
        $queryString .= " ee.reference AS ee_reference  ";

        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['year']) && is_numeric($params['year']) && $params['year'] > 0){
            $queryString .= " , ee.firstname, ee.lastname, AssignmentRequest.created_at AS \"initiated_on\", ee.number, list_services.services_name ";
        }

        $queryString .= " ,	CASE WHEN ab.departure_hr_office_id > 0 THEN (select office.name from office where id = ab.departure_hr_office_id) ELSE '' END as origin_office ";
        $queryString .= " , CASE WHEN ad.destination_hr_office_id > 0 THEN (select office.name from office where id = ad.destination_hr_office_id) ELSE '' END as destination_office ";

        $queryString .= " FROM relocation AS r  ";
        $queryString .= "  JOIN assignment AS ass ON ass.id = r.assignment_id ";
        $queryString .= " LEFT JOIN assignment_basic AS ab ON ab.id = ass.id";
        $queryString .= " LEFT JOIN assignment_destination AS ad ON ad.id = ass.id";
        $queryString .= " LEFT JOIN country AS homecountry ON homecountry.id = ab.home_country_id";
        $queryString .= " LEFT JOIN country AS destinationcountry ON destinationcountry.id = ad.destination_country_id";
        $queryString .= "  JOIN employee AS ee ON r.employee_id = ee.id ";
        $queryString .= "  JOIN company AS cc ON r.hr_company_id = cc.id ";
        $queryString .= " LEFT JOIN company AS booker ON booker.id = ass.booker_company_id ";
        $queryString .= "  JOIN contract AS con ON con.from_company_id = cc.id AND con.to_company_id =" . ModuleModel::$company->getId();
//        $queryString .= "  JOIN contract AS con ON con.from_company_id = cc.id AND con.to_company_id = 1160";
        $queryString .= " LEFT JOIN (select array_agg(concat(user_profile.firstname, ' ', user_profile.lastname)) as fullname, data_user_member.object_uuid as object_uuid from data_user_member";
        $queryString .= " join user_profile on user_profile.uuid = data_user_member.user_profile_uuid where data_user_member.member_type_id = 6 and user_profile.company_id = " . ModuleModel::$company->getId() . " group by data_user_member.object_uuid) as viewer on viewer.object_uuid = r.uuid ";
//        $queryString .= " join user_profile on user_profile.uuid = data_user_member.user_profile_uuid where data_user_member.member_type_id = 6 and user_profile.company_id = 1160 group by data_user_member.object_uuid) as viewer on viewer.object_uuid = r.uuid ";
        $queryString .= " LEFT JOIN (select concat(user_profile.firstname, ' ', user_profile.lastname) as fullname, data_user_member.object_uuid as object_uuid, user_profile.id as creator_profile_id from data_user_member";
        $queryString .= " join user_profile on user_profile.uuid = data_user_member.user_profile_uuid where data_user_member.member_type_id = 5 and user_profile.company_id = " . ModuleModel::$company->getId() . ") as creator on creator.object_uuid = r.uuid ";
//        $queryString .= " join user_profile on user_profile.uuid = data_user_member.user_profile_uuid where data_user_member.member_type_id = 5 and user_profile.company_id = 1160) as creator on creator.object_uuid = r.uuid ";
        $queryString .= " LEFT JOIN (select concat(user_profile.firstname, ' ', user_profile.lastname) as fullname, data_user_member.object_uuid as object_uuid, user_profile.id as owner_profile_id from data_user_member";
        $queryString .= " join user_profile on user_profile.uuid = data_user_member.user_profile_uuid where data_user_member.member_type_id = 2 and user_profile.company_id = " . ModuleModel::$company->getId() . ") as owner on owner.object_uuid = r.uuid ";
//        $queryString .= " join user_profile on user_profile.uuid = data_user_member.user_profile_uuid where data_user_member.member_type_id = 2 and user_profile.company_id = 1160) as owner on owner.object_uuid = r.uuid ";
        $queryString .= " left join (select relocation_service_company.id, relocation_service_company.relocation_id, ";
        $queryString .= " service_company.name, case when relocation_service_company.progress = 0 then '" . Constant::__translateConstant('TODO_TEXT', ModuleModel::$language);
        $queryString .= "' when relocation_service_company.progress = 3 then '" . Constant::__translateConstant('COMPLETED_TEXT', ModuleModel::$language) . "' else '";
        $queryString .= Constant::__translateConstant('IN_PROGRESS_TEXT', ModuleModel::$language) . "' end as detail_status, case when service_company.shortname is null then ' ' else service_company.shortname end as detail_short_name ";
        $queryString .= " from relocation_service_company join service_company on service_company.id = relocation_service_company.service_company_id ";
        $queryString .= " where relocation_service_company.status = 1 ) as rsc on rsc.relocation_id = r.id";

        //Cancel serviced
        $queryString .= " LEFT JOIN (select relocation_service_company.id, relocation_service_company.relocation_id, service_company.name, ";
        $queryString .= " case when service_company.shortname is null then ' ' else service_company.shortname end as detail_short_name  ";
        $queryString .= " from relocation_service_company join service_company on service_company.id = relocation_service_company.service_company_id ";
        $queryString .= " where relocation_service_company.status = -1 ) as cancel_rsc on cancel_rsc.relocation_id = r.id";

        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['year']) && is_numeric($params['year']) && $params['year'] > 0){
            $queryString .= " LEFT JOIN assignment_request as AssignmentRequest on AssignmentRequest.relocation_id = r.id ";
            $queryString .= " LEFT JOIN ( select relocation_id, array_join(array_agg(name), ', ') as services_name from relocation_service_company group by relocation_id ) as list_services on list_services.relocation_id = r.id ";
        }

        $queryString .= " WHERE ((ass.archived = false) AND (r.active != -2)) AND r.creator_company_id =" . ModuleModel::$company->getId();
//        $queryString .= " WHERE ((ass.archived = false) AND (r.active = true)) AND r.creator_company_id = 1160";
        if (isset($params['companyIds']) && is_array($params['companyIds']) && !empty($params['companyIds'])) {
            $index_companyid = 0;
            foreach ($params['companyIds'] as $companyId) {
                if ($index_companyid == 0) {
                    $queryString .= " AND (cc.id = " . $companyId . "";

                } else {
                    $queryString .= " OR cc.id = " . $companyId . "";
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
                    $queryString .= " AND (ee.id = " . $assigneeId . "";

                } else {
                    $queryString .= " OR ee.id = " . $assigneeId . "";
                }
                $index_assigneeId += 1;
            }
            if ($index_assigneeId > 0) {
                $queryString .= ") ";
            }
        }

        if (isset($params['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'cc.id',
                'ASSIGNEE_ARRAY_TEXT' => 'ee.id',
                'OWNER_ARRAY_TEXT' => 'owner.owner_profile_id',
                'REPORTER_ARRAY_TEXT' => 'creator.creator_profile_id',
                'BOOKER_ARRAY_TEXT' => 'ass.booker_company_id',
                'ORIGIN_ARRAY_TEXT' => 'homecountry.id',
                'DESTINATION_ARRAY_TEXT' => 'destinationcountry.id',
                'STATUS_ARRAY_TEXT' => 'r.status',
                'START_DATE_TEXT' => 'r.start_date',
                'END_DATE_TEXT' => 'r.end_date',
                'CREATED_ON_TEXT' => 'r.created_at',
                'ARCHIVED_TEXT' => 'r.active',
            ];

            Helpers::__addFilterConfigConditionsQueryString($queryString, $params['filter_config_id'], $params['is_tmp'], FilterConfigExt::RELOCATION_EXTRACT_FILTER_TARGET, $tableField);
        }

        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['year']) && is_numeric($params['year']) && $params['year'] > 0){
            $queryString .= " AND r.active in (1, -1) AND year(DATE(r.created_at)) = ". $params['year'];
        }

        $queryString .= "  GROUP BY r.id, r.identify, ee.firstname, ee.lastname, ee.workemail, ee.phonework, 
        ass.reference, r.employee_id, r.start_date, r.end_date, cc.name, booker.name, r.status, creator.fullname, 
        owner.fullname, viewer.fullname, r.created_at, homecountry.name, destinationcountry.name, r.active, ee.reference";

        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['year']) && is_numeric($params['year']) && $params['year'] > 0){
            $queryString .= " , ee.firstname, ee.lastname, AssignmentRequest.created_at, ee.number, list_services.services_name";
        }

        $queryString .= " , ab.departure_hr_office_id, ad.destination_hr_office_id ";

        if (count($orders) > 0) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY relocation_created_date ASC ";
                } else {
                    $queryString .= " ORDER BY relocation_created_date DESC ";
                }
            } else if ($order['field'] == "employee_name") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY assignee_name ASC ";
                } else {
                    $queryString .= " ORDER BY assignee_name DESC ";
                }
            } else {
                $queryString .= " ORDER BY relocation_created_date DESC";
            }

        } else {
            $queryString .= " ORDER BY relocation_created_date DESC";
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
    public static function __executeRelocationPerMonthReport($params)
    {

        if (isset($params['year']) && is_numeric($params['year'])) {
            $year = $params['year'];
        } else {
            $year = date('YYYY');
        }

        $queryString = "SELECT month( DATE(relocation.created_at)) as month, year(DATE(relocation.created_at)) as year, COUNT(*) as count
                        FROM relocation
                        LEFT JOIN assignment on relocation.assignment_id = assignment.id 
                        WHERE year(DATE(relocation.created_at)) = " . $year . "
                        AND relocation.active in (1, -1)
                        AND assignment.archived = false
                        AND relocation.creator_company_id = " . ModuleModel::$company->getId() . " 
                        GROUP BY month( DATE(relocation.created_at)) , year(DATE(relocation.created_at))
                        ORDER BY month( DATE(relocation.created_at))";


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

    /**
     * @return mixed
     */
    public static function __getCountActiveRelocations()
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
        $queryBuilder->columns(["uuid" => "Relocation.uuid"]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Relocation.employee_id = Employee.id', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->andWhere('Relocation.creator_company_id = :creator_company_id:', [
            'creator_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('Relocation.active = :active:', [
            'active' => Relocation::STATUS_ACTIVATED
        ]);

        return $queryBuilder->getQuery()->execute()->count();
    }

    /**
     * @return mixed
     */
    public static function __getCountCanceledRelocations()
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
        $queryBuilder->columns(["uuid" => "Relocation.uuid"]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Relocation.employee_id = Employee.id', 'Employee');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->andWhere('Relocation.creator_company_id = :creator_company_id:', [
            'creator_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('Relocation.active = :active:', [
            'active' => Relocation::STATUS_ACTIVATED
        ]);

        $queryBuilder->andWhere('Relocation.status = :status:', [
            'status' => Relocation::STATUS_CANCELED
        ]);

        return $queryBuilder->getQuery()->execute()->count();
    }

    /**
     * @return bool
     */
    public function checkActivateGuide()
    {
        if ($this->getIsActivateGuide() == self::ACTIVATE_GUIDE) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function checkActivateNews()
    {
        if ($this->getIsActivateNews() == self::ACTIVATE_NEWS) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $dependantId
     * @return bool
     */
    public function hasDependant($dependantId)
    {
        return $this->getAssignment() && $this->getAssignment()->checkIfDependantExist($dependantId);
    }


    /**
     * return mixed
     */
    public function getProfitAndLossByDefaultAccount($financial_account_id = 0)
    {
        $income = 0;
        $expense = 0;
        $expense_tax = 0;
        $invoice_tax = 0;
        $credit_tax = 0;
        $cost_of_products = 0;
        $expense_category_list = [];
        $hasTransaction = false;

        if ($financial_account_id > 0 && $financial_account_id != null) {
            $financial_account = FinancialAccount::findFirstById($financial_account_id);
        } else {
            $financial_account = FinancialAccount::findFirst([
                'conditions' => 'company_id = :company_id: and is_default = :is_default:',
                'bind' => [
                    'company_id' => ModuleModel::$company->getId(),
                    'is_default' => ModelHelper::YES
                ],
            ]);
        }

        if (!$financial_account) {
            $financial_account = FinancialAccount::findFirst([
                'conditions' => 'company_id = :company_id:',
                'bind' => [
                    'company_id' => ModuleModel::$company->getId(),
                ],
                "order" => "created_at ASC",
            ]);
        }

        if ($financial_account instanceof FinancialAccount && $financial_account->belongsToGms()) {
            $paymentInvoiceQuotes = $this->getPaymentInvoiceQuotes([
                'Reloday\Gms\Models\Payment.currency = :currency:',
                'bind' => [
                    'currency' => $financial_account->getCurrency()
                ]
            ]);

            $paymentExpenses = $this->getPaymentExpenses([
                'Reloday\Gms\Models\Payment.currency = :currency:',
                'bind' => [
                    'currency' => $financial_account->getCurrency()
                ]
            ]);

            $invoiceableItems = $this->getInvoiceableItems([
                'currency = :currency:',
                'bind' => [
                    'currency' => $financial_account->getCurrency()
                ]
            ]);

            if (count($paymentInvoiceQuotes) > 0 || count($paymentExpenses) > 0 || count($invoiceableItems) > 0) {
                $hasTransaction = true;
            }

            $expense_categories = ExpenseCategory::getListActiveOfMyCompany();

            $expense = Expense::sum([
                "column" => "total",
                "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) and expense_category_id is not null
                    and relocation_id = :relocation_id: and currency = :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "status" => Expense::STATUS_APPROVED,
                    "paid_status" => Expense::STATUS_PAID,
                    "currency" => $financial_account->getCurrency(),
                    "relocation_id" => $this->getId()
                ]
            ]);
            $expense_tax = Expense::sum([
                "column" => "total - cost",
                "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) and expense_category_id is not null
                    and relocation_id = :relocation_id: and currency = :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "status" => Expense::STATUS_APPROVED,
                    "paid_status" => Expense::STATUS_PAID,
                    "currency" => $financial_account->getCurrency(),
                    "relocation_id" => $this->getId()
                ]
            ]);
            $cost_of_products = Expense::sum([
                "column" => "cost",
                "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:)and expense_category_id is null and (product_pricing_id > 0 or account_product_pricing_id > 0)
                    and relocation_id = :relocation_id: and currency = :currency:",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "status" => Expense::STATUS_APPROVED,
                    "paid_status" => Expense::STATUS_PAID,
                    "currency" => $financial_account->getCurrency(),
                    "relocation_id" => $this->getId()
                ]
            ]);
            $invoice = InvoiceQuote::sum([
                "column" => "total_before_tax",
                "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and relocation_id = :relocation_id: ",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => InvoiceQuote::TYPE_INVOICE,
                    "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                    "currency" => $financial_account->getCurrency(),
                    "relocation_id" => $this->getId()
                ]
            ]);
            $credit = InvoiceQuote::sum([
                "column" => "total_before_tax",
                "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and relocation_id = :relocation_id: ",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                    "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                    "currency" => $financial_account->getCurrency(),
                    "relocation_id" => $this->getId()
                ]
            ]);
            $invoice_tax = InvoiceQuote::sum([
                "column" => "total_tax",
                "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and relocation_id = :relocation_id: ",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => InvoiceQuote::TYPE_INVOICE,
                    "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                    "currency" => $financial_account->getCurrency(),
                    "relocation_id" => $this->getId()
                ]
            ]);
            $credit_tax = InvoiceQuote::sum([
                "column" => "total_tax",
                "conditions" => "company_id = :company_id: and status IN ({statuses:array} ) and type = :type: and currency = :currency: 
                    and relocation_id = :relocation_id: ",
                "bind" => [
                    "company_id" => ModuleModel::$company->getId(),
                    "type" => InvoiceQuote::TYPE_CREDIT_NOTE,
                    "statuses" => [InvoiceQuote::STATUS_PAID, InvoiceQuote::STATUS_PARTIAL_PAID, InvoiceQuote::STATUS_APPROVED],
                    "currency" => $financial_account->getCurrency(),
                    "relocation_id" => $this->getId()
                ]
            ]);
            $income = $invoice - $credit;
            if ($income > 0) {
                $hasTransaction = true;
            }

            if (count($expense_categories) > 0) {
                foreach ($expense_categories as $expense_category) {
                    $value = Expense::sum([
                        "column" => "total",
                        "conditions" => "company_id = :company_id: and (status = :status: or status = :paid_status:) 
                    and currency = :currency: and expense_category_id = :expense_category_id:  and relocation_id = :relocation_id:",
                        "bind" => [
                            "company_id" => ModuleModel::$company->getId(),
                            "status" => Expense::STATUS_APPROVED,
                            "paid_status" => Expense::STATUS_PAID,
                            "currency" => $financial_account->getCurrency(),
                            "expense_category_id" => $expense_category["id"],
                            "relocation_id" => $this->getId(),
                        ]
                    ]);

                    if ($value > 0) {
                        $expense_category_list[] = [
                            "name" => $expense_category["name"]  . (($expense_category["external_hris_id"] != "" && $expense_category["external_hris_id"] != null) ? (" - " . $expense_category["external_hris_id"]) : ""),
                            "value" => $value
                        ];
                    }
                }
            }

            if (count($expense_category_list) > 0) {
                $hasTransaction = true;
            }

        }

        return [
            'income' => $income,
            'expense' => $expense,
            'expense_tax' => $expense_tax,
            'invoice' => isset($invoice) ? $invoice : 0,
            'currency' => $financial_account ? $financial_account->getCurrency() : '',
            'taxes' => $invoice_tax - $credit_tax,
            'credit' => isset($credit) ? $credit : 0,
            'cost_of_products' => $cost_of_products,
            'expense_category_list' => $expense_category_list,
            'financial_account' => $financial_account,
            'hasTransaction' => $hasTransaction,
        ];
    }

//    /**
//     * @return bool|\Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\Article|\Reloday\Application\Models\Article[]
//     */
//    public function getRelocationNews(){
//        if ($this->getArticleIds()){
//            $articleIds = json_decode($this->getArticleIds());
//            $news = Article::find([
//                'conditions' => 'id IN ({article_ids:array})',
//                'bind' => [
//                    'article_ids' => $articleIds
//                ]
//            ]);
//
//            return $news;
//        }
//        return false;
//    }

    /**
     * @param $options
     * @return array
     */
    public static function __getRecentRelocations($options = []){
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
              (select r.uuid, r.id  
                from relocation as r 
                where r.uuid = h.object_uuid 
                GROUP BY h.object_uuid)  
            order by h.created_at DESC limit $limit";

        $data = $db->query($sql);
        $data->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $results = $data->fetchAll();
        $relocation_array = [];
        foreach ($results as $history) {
            $relocation = Relocation::findFirstByUuid($history['object_uuid']);
            $item = $relocation->toArray();
            $assignment = $relocation->getAssignment();
            $employee = $relocation->getEmployee();
            $item['number'] = $relocation->getNumber();
            $item['employee_name'] = $employee->getFullname();
            $item['employee_uuid'] = $employee->getUuid();
            $item['employee_id'] = $employee->getId();
            $item['employee_email'] = $employee->getWorkemail();
            $item['company_name'] = $relocation->getHrCompany()->getName();
            $item['booker_company_name'] = $assignment->getBookerCompany() ? $assignment->getBookerCompany()->getName() : null;
            $item['booker_company_id'] = $assignment->getBookerCompany() ? $assignment->getBookerCompany()->getId() : null;
            $data_owner = $relocation->getDataOwner();
            $item['owner_name'] = $data_owner ? $data_owner->getFullname() : '';
            $item['owner_uuid'] = $data_owner ? $data_owner->getUuid() : '';
            $item['owners'] = $relocation->getOwners();

            $relocation_array[] = $item;
        }

        return [
            'success' => true,
            'data' => $relocation_array,
        ];

    }

    /**
     * @return void
     */
    public static function __getRelocationOngoing(){

    }
}
