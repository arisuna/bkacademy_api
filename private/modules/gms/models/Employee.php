<?php


namespace Reloday\Gms\Models;

use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\LanguageCode;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Lib\ReportLogHelper;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\Contract;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Regex as RegexValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Filter;
use Reloday\Application\Lib\RelodayFilter;

use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class Employee extends \Reloday\Application\Models\EmployeeExt
{

    const LIMIT_PER_PAGE = 20;

    /**
     * belong to UserLogin
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('user_login_id', 'Reloday\Gms\Models\UserLogin', 'id', [
            'alias' => 'UserLogin',
            'params' => [
                'conditions' => 'Reloday\Gms\Models\UserLogin.status = :user_login_active:',
                'bind' => [
                    'user_login_active' => UserLogin::STATUS_ACTIVATED
                ]
            ]
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\EmployeeInContract', 'employee_id', 'contract_id', 'Reloday\Gms\Models\Contract', 'id', [
            'alias' => 'Contracts',
            'params' => [
                'conditions' => 'Reloday\Gms\Models\Contract.status = :contract_status_active:',
                'bind' => [
                    'contract_status_active' => Contract::STATUS_ACTIVATED,
                ]
            ]
        ]);

        /** use only when it's connected */
        if (!is_null(ModuleModel::$company)) {
            $this->hasManyToMany('id', 'Reloday\Gms\Models\EmployeeInContract', 'employee_id', 'contract_id', 'Reloday\Gms\Models\Contract', 'id', [
                'alias' => 'ActiveContracts',
                'reusable' => true,
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\Contract.status = :contract_status_active: AND Reloday\Gms\Models\Contract.to_company_id = :current_dsp_id:',
                    'bind' => [
                        'contract_status_active' => Contract::STATUS_ACTIVATED,
                        'current_dsp_id' => (int)ModuleModel::$company->getId(),
                    ],
                    'limit' => 1,
                ],
                'cache' => [
                    'key' => '__CACHE_EMPLOYEE_CONTRACT_FROM_' . $this->getCompanyId() . "_TO_" . ModuleModel::$company->getId() . "__",
                    'life' => CacheHelper::__TIME_24H,
                ]
            ]);
        }

        $this->hasMany('id', 'Reloday\Gms\Models\Dependant', 'employee_id', [
            'alias' => 'Dependants',
            'params' => [
                'conditions' => '[Reloday\Gms\Models\Dependant].status = :status_active:',
                'bind' => [
                    'status_active' => self::STATUS_ACTIVATED
                ]
            ]
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\EmployeeDocument', 'employee_id', [
            'alias' => 'EmployeeDocuments'
        ]);
    }

    /**
     * [getBuddyContact description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function getBuddyContactList($company_id)
    {
        //list HR employees and user
        //
        $contacts = [];
        if ($company_id > 0) {

            $company = Company::findFirstById($company_id);

            if ($company) {
                $employees = $company->getEmployee();
                $profiles = $company->getUserProfile();

                if (count($employees)) {
                    foreach ($employees as $employee) {
                        $contacts[] = [
                            'id' => $employee->getUserLoginId(),
                            'user_login_id' => $employee->getUserLoginId(),
                            'firstname' => $employee->getFirstname(),
                            'lastname' => $employee->getLastname(),
                            'email' => $employee->getWorkemail(),
                        ];
                    }
                }

                if (count($profiles)) {
                    foreach ($profiles as $profile) {
                        $contacts[] = [
                            'id' => $profile->getUserLoginId(),
                            'user_login_id' => $profile->getUserLoginId(),
                            'firstname' => $profile->getFirstname(),
                            'lastname' => $profile->getLastname(),
                            'email' => $profile->getWorkemail()
                        ];
                    }
                }
            }
        }
        return $contacts;
    }

    /**
     * [getSupportContact description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function getSupportContactList($company_id)
    {
        //list HR employees and user
        //
        $contacts = [];
        if ($company_id > 0) {

            $company = Company::findFirstById($company_id);
            $current_gms_company = ModuleModel::$company;
            if ($company) {
                $profiles = $company->getUserProfile();
                if (count($profiles)) {
                    foreach ($profiles as $profile) {
                        $contacts[] = [
                            'id' => $profile->getUserLoginId(),
                            'user_login_id' => $profile->getUserLoginId(),
                            'firstname' => $profile->getFirstname(),
                            'lastname' => $profile->getLastname(),
                            'email' => $profile->getWorkemail()
                        ];
                    }
                }
            }
            if ($current_gms_company) {
                $profiles = $current_gms_company->getUserProfile();
                if (count($profiles)) {
                    foreach ($profiles as $profile) {
                        $contacts[] = [
                            'id' => $profile->getUserLoginId(),
                            'user_login_id' => $profile->getUserLoginId(),
                            'firstname' => $profile->getFirstname(),
                            'lastname' => $profile->getLastname(),
                            'email' => $profile->getWorkemail()
                        ];
                    }
                }
            }
        }
        return $contacts;
    }

    /**
     * @return bool
     */
    public function manageByGms()
    {
        return $this->belongsToGms();
    }

    /**
     * check is belong to GMS
     * @return bool
     */
    public function belongsToGms()
    {
        $contract = $this->getContract();
        if ($contract) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * get Avatar Object
     * @return array|null
     */
    public function getAvatar()
    {
//        $avatar = MediaAttachment::__getLastAttachment($this->getUuid(), "avatar");
        $avatar = ObjectAvatar::__getAvatar($this->getUuid());
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }

    /**
     *
     */
    public function getFrontendState($options = "")
    {
        return "app.employees-page.view.information({nickname:'" . $this->getNickname() . "'})";
    }


    /**
     * get employee information by number
     * @param $number
     * @return bool|mixed
     */
    public function getEmployeeByNumber($number)
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Employee', 'Employee');
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\EmployeeInContract', 'EmployeeInContract.employee_id = Employee.id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'EmployeeInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:');
        $queryBuilder->andWhere('Contract.status = :contract_activated:');
        $queryBuilder->andWhere('Employee.number = :employee_number:');
        //$queryBuilder->limit(1); //@bug in phalcon
        $bindArray = [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
            'employee_number' => $number,
        ];
        //var_dump( $queryBuilder->getPhql() ); die();
        $employees = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));

        if (count($employees)) {
            return $employees->getFirst();
        }
        return false;
    }

    /**
     * @return bool
     */
    public static function __getSimpleListByCompany($hr_company_id, $exceptEmployeeId = null)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Employee', 'Employee');
        $queryBuilder->columns([
            'Employee.id',
            'Employee.uuid',
            'Employee.workemail',
            'Employee.firstname',
            'Employee.lastname'
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\EmployeeInContract', 'EmployeeInContract.employee_id = Employee.id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'EmployeeInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
        ]);
        $queryBuilder->andWhere('Contract.status = :contract_activated:', [
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
        ]);
        $queryBuilder->andWhere('Contract.from_company_id = :hr_company_id:', [
            'hr_company_id' => $hr_company_id,
        ]);
        $queryBuilder->andWhere('Employee.company_id = :hr_company_id:', [
            'hr_company_id' => $hr_company_id,
        ]);

        $bindArray = [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
            'hr_company_id' => $hr_company_id,
        ];

        if ($exceptEmployeeId > 0) {
            $queryBuilder->andWhere('Employee.id != :except_employee_id:', [
                'except_employee_id' => $exceptEmployeeId,
            ]);
            $bindArray['except_employee_id'] = $exceptEmployeeId;
        }

        $employees = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));

        if (count($employees)) {
            return $employees;
        }
        return false;
    }

    /**
     * @param $option
     * @return bool
     */
    public static function searchSimpleList($options = array())
    {
        $di = \Phalcon\DI::getDefault();
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
            'Employee.phonework',
            'Employee.address',
            'Employee.town',
            'Employee.birth_country_id',
            'Employee.company_id',
            'Company.name as company_name'
        ]);
        $queryBuilder->distinct(true);

        $queryBuilder->innerJoin('\Reloday\Gms\Models\EmployeeInContract', 'EmployeeInContract.employee_id = Employee.id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'EmployeeInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = Employee.company_id', 'Company');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())]);
        $queryBuilder->andWhere('Contract.status = :contract_activated:', ['contract_activated' => intval(Contract::STATUS_ACTIVATED),]);
        $queryBuilder->andWhere('Employee.status = :employee_activated:', ['employee_activated' => intval(self::STATUS_ACTIVATED)]);
        $bindArray = [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
            'employee_activated' => intval(self::STATUS_ACTIVATED)
        ];

        $user_profile_uuid = ModuleModel::$user_profile->getUuid();

        if (ModuleModel::$user_profile->isGmsAdmin() == false) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Employee.id = Assignment.employee_id', 'Assignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Assignment.id = Relocation.assignment_id', 'Relocation');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Assignment.uuid = DataUserMemberAssignment.object_uuid', 'DataUserMemberAssignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Relocation.uuid = DataUserMemberRelocation.object_uuid', 'DataUserMemberRelocation');
            $queryBuilder->andWhere('Assignment.archived = :assignment_not_archived:', ['assignment_not_archived' => intval(Assignment::ARCHIVED_NO)]);
            $queryBuilder->andWhere('Relocation.active = :relocation_activated:', ['relocation_activated' => intval(Relocation::STATUS_ACTIVATED)]);
            $queryBuilder->andWhere('DataUserMemberAssignment.user_profile_uuid = :user_profile_uuid:  OR DataUserMemberRelocation.user_profile_uuid = :user_profile_uuid:', ['user_profile_uuid' => $user_profile_uuid]);

            $bindArray['assignment_not_archived'] = intval(Assignment::ARCHIVED_NO);
            $bindArray['relocation_activated'] = intval(Relocation::STATUS_ACTIVATED);
            $bindArray['user_profile_uuid'] = $user_profile_uuid;
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $search = $options['query'];
            $queryBuilder->andwhere("Employee.firstname LIKE :keyword: OR Employee.lastname LIKE :keyword: OR Employee.workemail LIKE :keyword: OR Company.name LIKE :keyword:", ['keyword' => "%$search%"]);
            $bindArray['keyword'] = "%$search%";
        }

        if (isset($options['active']) && $options['active'] == self::ACTIVE_YES) {
            $queryBuilder->andwhere("Employee.active = 1");
        }

        if (isset($options['keyword']) && is_string($options['keyword']) && $options['keyword'] != '') {
            $search = $options['keyword'];
            $queryBuilder->andwhere("Employee.firstname LIKE :keyword: OR Employee.lastname LIKE :keyword: OR Employee.workemail LIKE :keyword: OR Company.name LIKE :keyword:", ['keyword' => "%$search%"]);
            $bindArray['keyword'] = "%$search%";
        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("Employee.company_id = :company_id: AND Contract.from_company_id = :company_id:", ['company_id' => $options['company_id']]);
            $bindArray['company_id'] = $options['company_id'];
        }
        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        if ($page == 0) {
            $page = intval($start / $limit) + 1;
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page
            ]);
            $pagination = $paginator->getPaginate();

            $employeesArray = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $employee) {
                    $employee = $employee->toArray();
                    $employee['company_name'] = $employee['company_name'];
                    $employee['name'] = $employee['firstname'] . " " . $employee['lastname'];
                    $employeesArray[] = $employee;
                }
            }
            return [
                'success' => true,
                'options' => $options,
                'limit_per_page' => $limit,
                'page' => $page,
                'data' => array_values($employeesArray),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'query' => $queryBuilder->getQuery()->getSql(),
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
     * @param $option
     * @return bool
     */
    public static function __findWithFilterInList($options = array(), $orders = array())
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Employee', 'Employee');
        $queryBuilder->columns([
            'Employee.uuid',
            'Employee.id',
            'Employee.number',
            'Employee.jobtitle',
            'Employee.address',
            'Employee.login_status',
            'Employee.town',
            'Employee.country_id as country_id',
            'Employee.active',
            'Employee.workemail',
            'Employee.phonework',
            'Employee.uuid',
            'Employee.firstname',
            'Employee.lastname',
            'contract_id' => 'Contract.id',
            'Company.name as company_name',
            'Employee.company_id',
            'Employee.reference',
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\EmployeeInContract', 'EmployeeInContract.employee_id = Employee.id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'EmployeeInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = Employee.company_id', 'Company');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('Contract.status = :contract_activated:', [
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
        ]);
        $queryBuilder->andWhere('Employee.status = :employee_activated:', [
            'employee_activated' => intval(self::STATUS_ACTIVATED),
        ]);
        $bindArray = [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
            'employee_activated' => intval(self::STATUS_ACTIVATED),
        ];

        if (isset($options['user_profile_uuid']) && Helpers::__isValidUuid($options['user_profile_uuid'])) {

            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Employee.id = Assignment.employee_id', 'Assignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Assignment.id = Relocation.assignment_id and Relocation.active = ' . intval(Relocation::STATUS_ACTIVATED), 'Relocation');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'Relocation.id = RelocationServiceCompany.relocation_id', 'RelocationServiceCompany');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Assignment.uuid = DataUserMemberAssignment.object_uuid', 'DataUserMemberAssignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Relocation.uuid = DataUserMemberRelocation.object_uuid', 'DataUserMemberRelocation');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'RelocationServiceCompany.uuid = DataUserMemberRelocationService.object_uuid', 'DataUserMemberRelocationService');

            $queryBuilder->andWhere("Assignment.archived = :assignment_not_archived:", [
                'assignment_not_archived' => intval(Assignment::ARCHIVED_NO)
            ]);

            $queryBuilder->andWhere("DataUserMemberAssignment.user_profile_uuid = :user_profile_uuid: OR DataUserMemberRelocation.user_profile_uuid = :user_profile_uuid: OR DataUserMemberRelocationService.user_profile_uuid = :user_profile_uuid:", [
                'user_profile_uuid' => $options['user_profile_uuid']
            ]);

            $bindArray['assignment_not_archived'] = intval(Assignment::ARCHIVED_NO);
            $bindArray['user_profile_uuid'] = $options['user_profile_uuid'];
        }

        if (isset($options['keyword']) && is_string($options['keyword']) && $options['keyword'] != '') {
            $search = $options['keyword'];
            $queryBuilder->andWhere("Employee.firstname LIKE :keyword: OR Employee.lastname LIKE :keyword: OR CONCAT(Employee.firstname, ' ' , Employee.lastname) LIKE :keyword: OR Employee.workemail LIKE :keyword: OR Company.name LIKE :keyword: OR Employee.reference LIKE :keyword:", [
                'keyword' => "%$search%"
            ]);
            $bindArray['keyword'] = "%$search%";
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $search = $options['query'];
            $queryBuilder->andwhere("Employee.firstname LIKE :query: OR Employee.lastname LIKE :query: OR CONCAT(Employee.firstname, ' ' , Employee.lastname) LIKE :query: OR Employee.workemail LIKE :query: OR Company.name LIKE :query: OR Employee.jobtitle LIKE :query: OR Employee.number LIKE :query: OR Employee.reference LIKE :query:", [
                'query' => "%$search%"
            ]);
            $bindArray['query'] = "%$search%";
        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("Employee.company_id = :company_id: AND Contract.from_company_id = :company_id:", [
                'company_id' => $options['company_id']
            ]);
            $bindArray['company_id'] = $options['company_id'];
        }

        if (isset($options['booker_company_id']) && is_numeric($options['booker_company_id']) && $options['booker_company_id'] > 0) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Employee.id = AssignmentBCK.employee_id', 'AssignmentBCK');
            $queryBuilder->andwhere("AssignmentBCK.booker_company_id = :booker_company_id:", [
                'booker_company_id' => $options['booker_company_id']
            ]);
            $queryBuilder->andWhere("AssignmentBCK.archived = :assignment_not_archived_bck:", [
                'assignment_not_archived_bck' => intval(Assignment::ARCHIVED_NO)
            ]);
            $bindArray['assignment_not_archived_bck'] = intval(Assignment::ARCHIVED_NO);
            $bindArray['booker_company_id'] = $options['booker_company_id'];
        }

        if (isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids'])) {
            $queryBuilder->andwhere("Employee.company_id IN ({company_ids:array} )", [
                'company_ids' => $options['company_ids'],
            ]);
        }

        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andwhere("Employee.id IN ({ids:array} )", [
                'ids' => $options['ids'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andWhere('Employee.id = :employee_id:', [
                'employee_id' => $options['employee_id']
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'Employee.company_id',
                'STATUS_ARRAY_TEXT' => 'Employee.login_status',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::ASSIGNEE_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('Employee.firstname ASC');

            if ($order['field'] == "firstname") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.created_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Employee.firstname ASC");
        }

        $queryBuilder->groupBy('Employee.uuid');


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        if ($page == 0) {
            $page = intval($start / $limit) + 1;
        }
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
                    $data = $employee->toArray();
                    if (isset($options['filter_config_id'])) {
                        $employeeObj = self::findFirstById($data['id']);
                        //Check has login
                        if ($employeeObj->hasLogin() && $employeeObj->getLoginStatus() != self::LOGIN_STATUS_HAS_ACCESS) {
                            if ($employeeObj) {
                                $employeeObj->setLoginStatus(self::LOGIN_STATUS_HAS_ACCESS);
                                $resultUpdate = $employeeObj->__quickUpdate();
                                $data['login_status'] = self::LOGIN_STATUS_HAS_ACCESS;
                            }
                        }
                    }

                    $data['name'] = $data['firstname'] . " " . $data['lastname'];
                    $data['is_editable'] = HrCompany::__checkIsEditableById($data['company_id']) || Contract::__hasPermissionFromContractId($data['contract_id'], 'employee', 'edit');
                    $employeesArray[] = $data;
                }
            }
            return [
                'success' => true,
                'options' => $options,
                'limit_per_page' => $limit,
                'page' => $page,
                'data' => $employeesArray,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_pages' => $pagination->total_pages,
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
     * @param $option
     * @return bool
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Employee', 'Employee');
        $queryBuilder->columns([
            'Employee.uuid',
            'Employee.id',
            'Employee.number',
            'Employee.jobtitle',
            'Employee.address',
            'Employee.login_status',
            'Employee.town',
            'Employee.country_id as country_id',
            'Employee.active',
            'Employee.workemail',
            'Employee.phonework',
            'Employee.uuid',
            'Employee.firstname',
            'Employee.lastname',
            'contract_id' => 'Contract.id',
            'Company.name as company_name',
            'Employee.company_id',
            'Employee.reference',
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerJoin('\Reloday\Gms\Models\EmployeeInContract', 'EmployeeInContract.employee_id = Employee.id', 'EmployeeInContract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'EmployeeInContract.contract_id = Contract.id', 'Contract');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = Employee.company_id', 'Company');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('Contract.status = :contract_activated:', [
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
        ]);
        $queryBuilder->andWhere('Employee.status = :employee_activated:', [
            'employee_activated' => intval(self::STATUS_ACTIVATED),
        ]);
        $bindArray = [
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
            'employee_activated' => intval(self::STATUS_ACTIVATED),
        ];

        if (isset($options['user_profile_uuid']) && Helpers::__isValidUuid($options['user_profile_uuid'])) {

            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Employee.id = Assignment.employee_id', 'Assignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Assignment.id = Relocation.assignment_id and Relocation.active = ' . intval(Relocation::STATUS_ACTIVATED), 'Relocation');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'Relocation.id = RelocationServiceCompany.relocation_id', 'RelocationServiceCompany');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Assignment.uuid = DataUserMemberAssignment.object_uuid', 'DataUserMemberAssignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Relocation.uuid = DataUserMemberRelocation.object_uuid', 'DataUserMemberRelocation');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'RelocationServiceCompany.uuid = DataUserMemberRelocationService.object_uuid', 'DataUserMemberRelocationService');

            $queryBuilder->andWhere("Assignment.archived = :assignment_not_archived:", [
                'assignment_not_archived' => intval(Assignment::ARCHIVED_NO)
            ]);

            $queryBuilder->andWhere("DataUserMemberAssignment.user_profile_uuid = :user_profile_uuid: OR DataUserMemberRelocation.user_profile_uuid = :user_profile_uuid: OR DataUserMemberRelocationService.user_profile_uuid = :user_profile_uuid:", [
                'user_profile_uuid' => $options['user_profile_uuid']
            ]);

            $bindArray['assignment_not_archived'] = intval(Assignment::ARCHIVED_NO);
            $bindArray['user_profile_uuid'] = $options['user_profile_uuid'];
        }

        if (isset($options['keyword']) && is_string($options['keyword']) && $options['keyword'] != '') {
            $search = $options['keyword'];
            $queryBuilder->andWhere("Employee.firstname LIKE :keyword: OR Employee.lastname LIKE :keyword: OR CONCAT(Employee.firstname, ' ' , Employee.lastname) LIKE :keyword: OR Employee.workemail LIKE :keyword: OR Company.name LIKE :keyword: OR Employee.reference LIKE :keyword:", [
                'keyword' => "%$search%"
            ]);
            $bindArray['keyword'] = "%$search%";
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $search = $options['query'];
            $queryBuilder->andwhere("Employee.firstname LIKE :query: OR Employee.lastname LIKE :query: OR CONCAT(Employee.firstname, ' ' , Employee.lastname) LIKE :query: OR Employee.workemail LIKE :query: OR Company.name LIKE :query: OR Employee.jobtitle LIKE :query: OR Employee.number LIKE :query: OR Employee.reference LIKE :query:", [
                'query' => "%$search%"
            ]);
            $bindArray['query'] = "%$search%";
        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("Employee.company_id = :company_id: AND Contract.from_company_id = :company_id:", [
                'company_id' => $options['company_id']
            ]);
            $bindArray['company_id'] = $options['company_id'];
        }

        if (isset($options['booker_company_id']) && is_numeric($options['booker_company_id']) && $options['booker_company_id'] > 0) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Employee.id = AssignmentBCK.employee_id', 'AssignmentBCK');
            $queryBuilder->andwhere("AssignmentBCK.booker_company_id = :booker_company_id:", [
                'booker_company_id' => $options['booker_company_id']
            ]);
            $queryBuilder->andWhere("AssignmentBCK.archived = :assignment_not_archived_bck:", [
                'assignment_not_archived_bck' => intval(Assignment::ARCHIVED_NO)
            ]);
            $bindArray['assignment_not_archived_bck'] = intval(Assignment::ARCHIVED_NO);
            $bindArray['booker_company_id'] = $options['booker_company_id'];
        }

        if (isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids'])) {
            $queryBuilder->andwhere("Employee.company_id IN ({company_ids:array} )", [
                'company_ids' => $options['company_ids'],
            ]);
        }

        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andwhere("Employee.id IN ({ids:array} )", [
                'ids' => $options['ids'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andWhere('Employee.id = :employee_id:', [
                'employee_id' => $options['employee_id']
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'Employee.company_id',
                'STATUS_ARRAY_TEXT' => 'Employee.login_status',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::ASSIGNEE_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('Employee.firstname ASC');

            if ($order['field'] == "firstname") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.created_at DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy("Employee.firstname ASC");
        }

        $queryBuilder->groupBy('Employee.uuid');


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        if ($page == 0) {
            $page = intval($start / $limit) + 1;
        }
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
                    $data = $employee->toArray();

                    $data['name'] = $data['firstname'] . " " . $data['lastname'];
                    $data['is_editable'] = HrCompany::__checkIsEditableById($data['company_id']) || Contract::__hasPermissionFromContractId($data['contract_id'], 'employee', 'edit');
                    $employeesArray[] = $data;
                }
            }
            return [
                'success' => true,
                'options' => $options,
                'limit_per_page' => $limit,
                'page' => $page,
                'data' => $employeesArray,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_pages' => $pagination->total_pages,
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
     * @param $dependantArray
     */
    public function addDependant($dependantArray)
    {
        $dependantModel = new Dependant();
        $dependantModel->setData($dependantArray);
        $dependantModel->setEmployeeId($this->getId());
        return $dependantModel->__quickCreate();
    }

    /**
     * @return string
     */
    public function getLoginUrl()
    {
        $di = \Phalcon\DI::getDefault();
        $url_login = (($di->getShared('appConfig')->domain->ssl == true) ? 'https' : 'http') . "://" . $di->getShared('appConfig')->domain->assignee . "/#/login";
        return $url_login;
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        if ($this->isArchived()) return false;
        return HrCompany::__checkIsEditableById($this->getCompanyId()) || $this->getContract() && $this->getContract()->hasPermission('employee', 'edit');
    }

    /**
     * @return bool
     */
    public function getContract()
    {
        $contracts = $this->getActiveContracts();
        return $contracts ? $contracts->getFirst() : false;
    }

    /**
     * @param $params
     * @param $orders
     * @return array
     */
    public static function __executeReport($params, $orders = [])
    {

        $dateFormat = ModuleModel::$company->getAthenaDateFormat();
//        $timezone_offset = Helpers::__getHeaderValue(Helpers::TIMEZONE_OFFSET) * 60;
        $timezone_offset = 0; //UTC 0
        $queryString = "SELECT DISTINCT ee.id AS id, ";
        $queryString .= " ee.number AS identify, ";
        $queryString .= " ee.firstname AS firstname,";
        $queryString .= " ee.lastname AS lastname, ";
        $queryString .= " ee.birth_place AS place_of_birth,";
        $queryString .= " bc.name AS country_of_birth, ";
        $queryString .= " ee.country_name AS country, ";
        $queryString .= " ee.birth_date AS birth_date,";
        $queryString .= " ec.citizenship_names AS citizenship,";
        $queryString .= " ee.spoken_languages AS spoken_languages,";
        $queryString .= " ee.school_grade AS school_grade, ";
        $queryString .= " ee.marital_status AS marital_status, ";
        $queryString .= " ee.workemail AS workemail, ";
        $queryString .= " ee.privateemail AS privateemail,";
        $queryString .= " ee.phonework AS phonework, ";
        $queryString .= " ee.phonehome AS phonehome, ";
        $queryString .= " ee.mobilehome AS mobilehome, ";
        $queryString .= " sp.name AS support_contact,";
        $queryString .= " CONCAT(bd.firstname, ' ', bd.lastname) AS buddy_contact, ";
        $queryString .= " cc.name AS company, ";
        $queryString .= " off.name AS office, ";
        $queryString .= " dep.name AS department,";
        $queryString .= " team.name AS team, ";
        $queryString .= " ee.jobtitle AS jobtitle,";
        $queryString .= " cast(dp.detail as json) as dependents,";
        $queryString .= " ee.active AS active, ";
        $queryString .= " CONCAT(ee.firstname, ' ', ee.lastname) AS assignee_name,";
        //passport
        $queryString .= " passport.number AS passport_number,";
        $queryString .= " passport.name AS passport_name,";
        $queryString .= " passport.expiry_date AS passport_expiry_date,";
        $queryString .= " passport.delivery_date AS passport_delivery_date,";
        $queryString .= " passport.delivery_country_name AS passport_delivery_country_name,";
        //visa
        $queryString .= " visa.number AS visa_number,";
        $queryString .= " visa.name AS visa_name,";
        $queryString .= " visa.expiry_date AS visa_expiry_date,";
        $queryString .= " visa.delivery_date AS visa_delivery_date,";
        $queryString .= " visa.estimated_approval_date AS visa_estimated_approval_date,";
        $queryString .= " visa.approval_date AS visa_approval_date,";
        $queryString .= " visa.delivery_country_name AS visa_delivery_country_name,";
        //social_security
        $queryString .= " social_security.number AS social_security_number,";
        $queryString .= " social_security.name AS social_security_name,";
        $queryString .= " social_security.expiry_date AS social_security_expiry_date,";
        $queryString .= " social_security.delivery_date AS social_security_delivery_date,";
        //education
        $queryString .= " education.number AS education_number,";
        $queryString .= " education.name AS education_name,";
        $queryString .= " education.expiry_date AS education_expiry_date,";
        $queryString .= " education.delivery_date AS education_delivery_date,";
        //curriculum
        $queryString .= " curriculum.number AS curriculum_number,";
        $queryString .= " curriculum.name AS curriculum_name,";
        $queryString .= " curriculum.expiry_date AS curriculum_expiry_date,";
        $queryString .= " curriculum.delivery_date AS curriculum_delivery_date,";
        //driving_license
        $queryString .= " driving_license.number AS driving_license_number,";
        $queryString .= " driving_license.name AS driving_license_name,";
        $queryString .= " driving_license.expiry_date AS driving_license_expiry_date,";
        $queryString .= " driving_license.delivery_date AS driving_license_delivery_date,";
        //other_document
        $queryString .= " other_document.number AS other_document_number,";
        $queryString .= " other_document.name AS other_document_name,";
        $queryString .= " other_document.expiry_date AS other_document_expiry_date,";
        $queryString .= " other_document.delivery_date AS other_document_delivery_date, ";
        $queryString .= " ee.created_at AS ee_created_date, ";
        $queryString .= " ee.reference AS ee_reference ";

        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] != ''){
            $queryString .= " , ee.sex, ul.lastconnect_at, ul.firstconnect_at ";
        }

        $queryString .= " FROM employee AS ee  ";
        //support_contact
        $queryString .= " LEFT JOIN (select array_agg(concat(user_profile.firstname, ' ', user_profile.lastname))";
        $queryString .= " as name, employee_support_contact.employee_id from employee_support_contact";
        $queryString .= " join user_profile on employee_support_contact.contact_user_profile_id = user_profile.id";
        $queryString .= " where employee_support_contact.is_buddy = false group by  employee_support_contact.employee_id) as sp on sp.employee_id = ee.id ";

        $queryString .= " LEFT JOIN user_profile AS bd ON ee.buddy_contact_user_profile_id = bd.id ";
        $queryString .= " JOIN company AS cc ON ee.company_id = cc.id ";
        $queryString .= " JOIN contract AS con ON con.from_company_id = cc.id AND con.to_company_id = " . ModuleModel::$company->getId();
//        $queryString .= " JOIN contract AS con ON con.from_company_id = cc.id AND con.to_company_id = 1160";
        $queryString .= " JOIN employee_in_contract AS ei ON con.id = ei.contract_id AND ei.employee_id = ee.id ";
        $queryString .= " LEFT JOIN office AS off ON ee.office_id = off.id ";
        $queryString .= " LEFT JOIN department AS dep ON ee.department_id = dep.id ";
        $queryString .= " LEFT JOIN team AS team ON ee.team_id = team.id ";
        $queryString .= " LEFT JOIN country AS bc ON ee.birth_country_id = bc.id";
        $queryString .= " LEFT JOIN (select array_agg(employee_citizenship.name) as citizenship_names,employee_citizenship.id  from (with dataset as (select cast(json_parse(citizenships) as array(varchar)) as citizenship_array, id from employee where json_array_length(citizenships) > 0) select t.citizenship, dataset.id, nationality.name from dataset CROSS JOIN UNNEST(dataset.citizenship_array) as t(citizenship) join nationality on nationality.code = t.citizenship group by dataset.id, t.citizenship, nationality.name) as employee_citizenship  group by employee_citizenship.id) AS ec ON ec.id = ee.id";
        $queryString .= " left join(select map_agg(concat(dependant.firstname, ' ', dependant.lastname) , case  when dependant.relation = '2' then '" . ConstantHelper::__translate("CHILD_TEXT", ModuleModel::$language);
        $queryString .= "' when dependant.relation = '1' then '" . ConstantHelper::__translate("SPOUSE_TEXT", ModuleModel::$language);
        $queryString .= "' when dependant.relation = '3' then '" . ConstantHelper::__translate("COMMON_LAW_PARTNER_TEXT", ModuleModel::$language);
        $queryString .= "' when dependant.relation = '4' then '" . ConstantHelper::__translate("OTHER_TEXT", ModuleModel::$language);
        $queryString .= "' else ' ' end) as detail, dependant.employee_id from dependant where dependant.status = 1 group by dependant.employee_id) as dp on dp.employee_id = ee.id";
        //Get list visa
        $queryString .= " left join (select array_agg(case when edoc.number is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else edoc.number end) as number";
        $queryString .= ", array_agg(edoc.name) as name, edoc.entity_uuid";
        $queryString .= ", array_agg(case when edoc.expiry_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.expiry_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as expiry_date";
        $queryString .= ", array_agg(case when edoc.delivery_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.delivery_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as delivery_date";
        $queryString .= ", array_agg(case when edoc.estimated_approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.estimated_approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as estimated_approval_date";
        $queryString .= ", array_agg(case when edoc.approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as approval_date";
        $queryString .= ", array_agg(case when country.id is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else country.name end) as delivery_country_name";
        $queryString .= " from entity_document as edoc  left join country as country on country.id = edoc.delivery_country_id";
        $queryString .= " where edoc.document_type_id = 2 AND edoc.is_deleted = false AND edoc.is_active = true group by edoc.entity_uuid)";
        $queryString .= " as visa on visa.entity_uuid = ee.uuid ";
        //Get list passport
        $queryString .= " left join (select array_agg(case when edoc.number is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else edoc.number end) as number";
        $queryString .= ", array_agg(edoc.name) as name, edoc.entity_uuid";
        $queryString .= ", array_agg(case when edoc.expiry_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.expiry_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as expiry_date";
        $queryString .= ", array_agg(case when edoc.delivery_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.delivery_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as delivery_date";
        $queryString .= ", array_agg(case when edoc.estimated_approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.estimated_approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as estimated_approval_date";
        $queryString .= ", array_agg(case when edoc.approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as approval_date";
        $queryString .= ", array_agg(case when country.id is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else country.name end) as delivery_country_name";
        $queryString .= " from entity_document as edoc  left join country as country on country.id = edoc.delivery_country_id";
        $queryString .= " where edoc.document_type_id = 1 AND edoc.is_deleted = false AND edoc.is_active = true group by edoc.entity_uuid)";
        $queryString .= " as passport on passport.entity_uuid = ee.uuid ";
        //Get list social_security
        $queryString .= " left join (select array_agg(case when edoc.number is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else edoc.number end) as number";
        $queryString .= ", array_agg(edoc.name) as name, edoc.entity_uuid";
        $queryString .= ", array_agg(case when edoc.expiry_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.expiry_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as expiry_date";
        $queryString .= ", array_agg(case when edoc.delivery_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.delivery_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as delivery_date";
        $queryString .= ", array_agg(case when edoc.estimated_approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.estimated_approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as estimated_approval_date";
        $queryString .= ", array_agg(case when edoc.approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as approval_date";
        $queryString .= ", array_agg(case when country.id is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else country.name end) as delivery_country_name";
        $queryString .= " from entity_document as edoc  left join country as country on country.id = edoc.delivery_country_id";
        $queryString .= " where edoc.document_type_id = 3 AND edoc.is_deleted = false AND edoc.is_active = true group by edoc.entity_uuid)";
        $queryString .= " as social_security on social_security.entity_uuid = ee.uuid ";
        //Get list education
        $queryString .= " left join (select array_agg(case when edoc.number is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else edoc.number end) as number";
        $queryString .= ", array_agg(edoc.name) as name, edoc.entity_uuid";
        $queryString .= ", array_agg(case when edoc.expiry_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.expiry_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as expiry_date";
        $queryString .= ", array_agg(case when edoc.delivery_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.delivery_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as delivery_date";
        $queryString .= ", array_agg(case when edoc.estimated_approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.estimated_approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as estimated_approval_date";
        $queryString .= ", array_agg(case when edoc.approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as approval_date";
        $queryString .= ", array_agg(case when country.id is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else country.name end) as delivery_country_name";
        $queryString .= " from entity_document as edoc  left join country as country on country.id = edoc.delivery_country_id";
        $queryString .= " where edoc.document_type_id = 4 AND edoc.is_deleted = false AND edoc.is_active = true group by edoc.entity_uuid)";
        $queryString .= " as education on education.entity_uuid = ee.uuid ";
        //Get list curriculum
        $queryString .= " left join (select array_agg(case when edoc.number is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else edoc.number end) as number";
        $queryString .= ", array_agg(edoc.name) as name, edoc.entity_uuid";
        $queryString .= ", array_agg(case when edoc.expiry_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.expiry_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as expiry_date";
        $queryString .= ", array_agg(case when edoc.delivery_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.delivery_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as delivery_date";
        $queryString .= ", array_agg(case when edoc.estimated_approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.estimated_approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as estimated_approval_date";
        $queryString .= ", array_agg(case when edoc.approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as approval_date";
        $queryString .= ", array_agg(case when country.id is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else country.name end) as delivery_country_name";
        $queryString .= " from entity_document as edoc  left join country as country on country.id = edoc.delivery_country_id";
        $queryString .= " where edoc.document_type_id = 5 AND edoc.is_deleted = false AND edoc.is_active = true group by edoc.entity_uuid)";
        $queryString .= " as curriculum on curriculum.entity_uuid = ee.uuid ";
        //Get list driving_license
        $queryString .= " left join (select array_agg(case when edoc.number is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else edoc.number end) as number";
        $queryString .= ", array_agg(edoc.name) as name, edoc.entity_uuid";
        $queryString .= ", array_agg(case when edoc.expiry_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.expiry_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as expiry_date";
        $queryString .= ", array_agg(case when edoc.delivery_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.delivery_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as delivery_date";
        $queryString .= ", array_agg(case when edoc.estimated_approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.estimated_approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as estimated_approval_date";
        $queryString .= ", array_agg(case when edoc.approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as approval_date";
        $queryString .= ", array_agg(case when country.id is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else country.name end) as delivery_country_name";
        $queryString .= " from entity_document as edoc  left join country as country on country.id = edoc.delivery_country_id";
        $queryString .= " where edoc.document_type_id = 6 AND edoc.is_deleted = false AND edoc.is_active = true group by edoc.entity_uuid)";
        $queryString .= " as driving_license on driving_license.entity_uuid = ee.uuid ";
        //Get list Other
        $queryString .= " left join (select array_agg(case when edoc.number is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else edoc.number end) as number";
        $queryString .= ", array_agg(edoc.name) as name, edoc.entity_uuid";
        $queryString .= ", array_agg(case when edoc.expiry_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.expiry_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as expiry_date";
        $queryString .= ", array_agg(case when edoc.delivery_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.delivery_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as delivery_date";
        $queryString .= ", array_agg(case when edoc.estimated_approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.estimated_approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as estimated_approval_date";
        $queryString .= ", array_agg(case when edoc.approval_date is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else date_format(from_unixtime(CAST(edoc.approval_date AS bigint)+ " . $timezone_offset . "), '" . $dateFormat . "') end ) as approval_date";
        $queryString .= ", array_agg(case when country.id is null then '" . ConstantHelper::__translate("NO_DATA_TEXT", ModuleModel::$language) . "' else country.name end) as delivery_country_name";
        $queryString .= " from entity_document as edoc  left join country as country on country.id = edoc.delivery_country_id";
        $queryString .= " where edoc.document_type_id = 8 AND edoc.is_deleted = false AND edoc.is_active = true group by edoc.entity_uuid)";
        $queryString .= " as other_document on other_document.entity_uuid = ee.uuid ";

        //Get last login
        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] != ''){
            $queryString .= " LEFT JOIN user_login AS ul ON ee.workemail = ul.email ";
        }

        $queryString .= " WHERE true ";

        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] != ''){
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
            $queryString .= " AND ee.status = 1 AND ee.created_at >= DATE('" . $created_at . "') ";
        }

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
                'CREATED_ON_TEXT' => 'ee.created_at',
                'STATUS_TEXT' => 'ee.active',
                'HAS_VISA_TEXT' => 'visa.number',
                'HAS_PASSPORT_TEXT' => 'passport.number',
                'HAS_DEPENDENT_TEXT' => 'dp.detail',
                'HAS_SOCIAL_SECURITY_TEXT' => 'ee.social_security_number',
            ];
            $dataType = [
                'STATUS_TEXT' => 'boolean',
            ];
            Helpers::__addFilterConfigConditionsQueryString($queryString, $params['filter_config_id'], $params['is_tmp'], FilterConfigExt::ASSIGNEE_EXTRACT_FILTER_TARGET, $tableField, $dataType);
        }

        $queryString .= " GROUP BY ee.id, ee.number, ee.firstname, ee.lastname, ee.birth_place, bc.name, ee.country_name, ee.birth_date, ec.citizenship_names, ee.spoken_languages, ee.school_grade, ee.marital_status,";
        $queryString .= " ee.workemail, ee.privateemail, ee.phonework , ee.phonehome , ee.mobilehome , sp.name, bd.firstname, bd.lastname, cc.name, off.name, dep.name, ";
        $queryString .= " team.name, ee.jobtitle, ee.active, dp.detail, ee.created_at, passport.number, ";
        $queryString .= " passport.name, passport.expiry_date, passport.delivery_date, passport.delivery_country_name, ";
        $queryString .= " visa.number, visa.name, visa.expiry_date, visa.delivery_date, visa.estimated_approval_date, visa.approval_date, visa.delivery_country_name, ";
        $queryString .= " social_security.number, social_security.name, social_security.expiry_date, social_security.delivery_date, ";
        $queryString .= " education.number, education.name, education.expiry_date, education.delivery_date, ";
        $queryString .= " curriculum.number, curriculum.name, curriculum.expiry_date, curriculum.delivery_date, ";
        $queryString .= " driving_license.number, driving_license.name, driving_license.expiry_date, driving_license.delivery_date, ";
        $queryString .= " other_document.number, other_document.name, other_document.expiry_date, other_document.delivery_date, ee.reference ";

        if(isset($params['is_download_report']) && $params['is_download_report'] == true && isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] != ''){
            $queryString .= " , ee.sex, ul.lastconnect_at, ul.firstconnect_at ";
        }

        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY ee_created_date ASC ";
                } else {
                    $queryString .= " ORDER BY ee_created_date DESC ";
                }
            } else if ($order['field'] == "employee_name") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY assignee_name ASC ";
                } else {
                    $queryString .= " ORDER BY assignee_name DESC ";
                }
            } else {
                $queryString .= " ORDER BY ee_created_date DESC";
            }


        } else {
            $queryString .= " ORDER BY ee_created_date DESC";
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
    public static function __executeActiveInactiveAssigneeReport($params = [])
    {
        $queryString = "Select Count(Distinct(Assignee.id))";
        $queryString .= ", CASE WHEN Assignee.active = true then '" . Constant::__translateConstant('YES_TEXT', ModuleModel::$language);
        $queryString .= "' Else '" . Constant::__translateConstant('NO_TEXT', ModuleModel::$language);
        $queryString .= "' End as \"" . Constant::__translateConstant('ACTIVE_TEXT', ModuleModel::$language) . "\"";
        $queryString .= "from employee As Assignee ";
        $queryString .= "inner join employee_in_contract as EmployeeInContract On EmployeeInContract.employee_id = Assignee.id ";
        $queryString .= "inner join contract As Contract On Contract.id = EmployeeInContract.contract_id ";
        $queryString .= "where Assignee.status = 1 and Contract.to_company_id =" . ModuleModel::$company->getId() . " ";

        if (isset($params['created_at']) && is_numeric($params['created_at']) && $params['created_at'] > 0) {
            $created_at = Helpers::__convertSecondToDate($params['created_at'], "Y-m-d");
            $queryString .= "and Assignee.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_month') {
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryString .= "and Assignee.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '3_months') {
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryString .= "and Assignee.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '6_months') {
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryString .= "and Assignee.created_at >=  DATE('" . $created_at . "')";
        } elseif (isset($params['created_at_period']) && is_string($params['created_at_period']) && $params['created_at_period'] == '1_year') {
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryString .= "and Assignee.created_at >=  DATE('" . $created_at . "')";
        }

        $queryString .= " group by Assignee.active";
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
     * @return array
     */
    public static function __executeNumberCreatedAssineeReport()
    {
        $now = date('Y-m-d');
        $i = 0;

        // 1 month
        $last_date = date('Y-m-d', strtotime($now . ' - 1 month'));
        $from = $last_date;
        $to = date('Y-m-d', strtotime($from . ' + 7 day'));
        $time_from = \DateTime::createFromFormat('Y-m-d', $from);
        $label_from = $time_from->format('d-m');
        $time_to = \DateTime::createFromFormat('Y-m-d', $to);
        $label_to = $time_to->format('d-m');
        $label = $label_from . " -> " . $label_to;
        $queryString = "Select count(*) ,0 as condition ,'" . $label . "' as label, " . $i . " as order_by from employee where created_at < DATE('" . $to . "') and created_at >= DATE('" . $from . "') and company_id = " . ModuleModel::$company->getId() . " and status = 1";
        $from = $to;
        while ($from < $now) {
            $i++;
            $to = date('Y-m-d', strtotime($from . ' + 7 day'));
            if (strtotime($to) > strtotime($now)) {
                $to = $now;
            }
            $time_from = \DateTime::createFromFormat('Y-m-d', $from);
            $label_from = $time_from->format('d-m');
            $time_to = \DateTime::createFromFormat('Y-m-d', $to);
            $label_to = $time_to->format('d-m');
            $label = $label_from . " -> " . $label_to;
            $queryString .= " UNION Select count(*) ,0 as condition ,'" . $label . "' as label, " . $i . " as order_by from employee where created_at < DATE('" . $to . "') and created_at >= DATE('" . $from . "') and company_id = " . ModuleModel::$company->getId() . " and status = 1";
            $from = $to;
        }

        // 6 month
        $last_date = date('Y-m-01', strtotime($now . ' - 6 months'));
        $from = $last_date;
        while ($from < $now) {
            $i++;
            $to = date('Y-m-01', strtotime($from . ' + 1 month'));
            $time = \DateTime::createFromFormat('Y-m-d', $from);
            $label = $time->format('F-Y');
            $queryString .= " UNION Select count(*) ,1 as condition ,'" . $label . "' as label, " . $i . " as order_by from employee where created_at < DATE('" . $to . "') and created_at >= DATE('" . $from . "') and company_id = " . ModuleModel::$company->getId() . " and status = 1";
            $from = $to;
        }
        // 1 year
        $last_date = date('Y-m-01', strtotime($now . ' - 1 year'));
        $from = $last_date;
        while ($from < $now) {
            $i++;
            $to = date('Y-m-01', strtotime($from . ' + 1 month'));
            $time = \DateTime::createFromFormat('Y-m-d', $from);
            $label = $time->format('F-Y');
            $queryString .= " UNION Select count(*) ,2 as condition ,'" . $label . "' as label, " . $i . " as order_by from employee where created_at < DATE('" . $to . "') and created_at >= DATE('" . $from . "') and company_id = " . ModuleModel::$company->getId() . " and status = 1";
            $from = $to;
        }
        $queryString .= " Order by order_by asc";
        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    /**
     * @param $params
     * @return array
     */
    public static function __OLD_executeReport($params)
    {
        $queryString = "select Assignee.number AS \"" . Constant::__translateConstant('NUMBER_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", Assignee.reference As \"" . Constant::__translateConstant('EE_INTERNAL_ID_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", CONCAT(Assignee.firstname, ' ', Assignee.lastname) AS \"" . Constant::__translateConstant('EMPLOYEE_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", format_datetime(Assignee.created_at, 'dd/MM/yyyy') AS \"" . Constant::__translateConstant('CREATED_ON_TEXT', ModuleModel::$language) . "\"";
        $queryString .= ", CASE WHEN Assignee.active = true then '" . Constant::__translateConstant('YES_TEXT', ModuleModel::$language);
        $queryString .= "' Else '" . Constant::__translateConstant('NO_TEXT', ModuleModel::$language);
        $queryString .= "' End as \"" . Constant::__translateConstant('ACTIVE_TEXT', ModuleModel::$language) . "\"";
        $queryString .= "from employee As Assignee ";
        $queryString .= " WHERE  Assignee.company_id = " . ModuleModel::$company->getId() . " and Assignee.status = 1";
        if (isset($params["end_date"])) {
            $queryString .= " and Assignee.created_at >= DATE('" . $params["end_date"] . "') ";
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
    public static function __executeVisaExpiringReport($params)
    {
        $endDate = time() + 30 * 86400;
        $startDate = time();

        if (isset($params['visa_expire_period']) && $params['visa_expire_period'] != '') {

            if (isset($params['visa_expire_period']) && $params['visa_expire_period'] == '1_month') {
                $endDate = time() + 30 * 86400;
                $startDate = time();
            } else if (isset($params['visa_expire_period']) && $params['visa_expire_period'] == '3_months') {
                $endDate = time() + 30 * 3 * 86400;
                $startDate = time();
            } else if (isset($params['visa_expire_period']) && $params['visa_expire_period'] == '6_months') {
                $endDate = time() + 30 * 6 * 86400;
                $startDate = time();
            } else {
                $endDate = time() + 30 * 86400;
                $startDate = time();
            }
        } else {
            $endDate = 0;
            $startDate = 0;

            if (isset($params['from_date']) && Helpers::__isTimeSecond($params['from_date'])) {
                $startDate = $params['from_date'];
            }

            if (isset($params['to_date']) && Helpers::__isTimeSecond($params['to_date'])) {
                $endDate = $params['to_date'];
            }
        }


        $queryString = "";

        $queryString .= " SELECT DISTINCT Assignee.uuid, entityDocument.expiry_date, CONCAT(Assignee.firstname, ' ',Assignee.lastname) as full_name, 'EMPLOYEE_TEXT' as label, Relocation.uuid as assignment_uuid, Relocation.identify as assignment_number
        FROM entity_document as entityDocument
        INNER JOIN employee as Assignee ON Assignee.uuid = entityDocument.entity_uuid
        INNER JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
        INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id
        INNER JOIN assignment AS Assignment ON Assignment.employee_id = Assignee.id
        INNER JOIN relocation as Relocation on Relocation.assignment_id = Assignment.id and Relocation.creator_company_id = " . ModuleModel::$company->getId() . "
        WHERE contract.to_company_id = " . ModuleModel::$company->getId() . "
            AND Assignee.status = 1
            AND Assignment.archived = false
            AND Assignment.is_terminated = false
            AND entityDocument.document_type_id = 2 and entityDocument.is_deleted = false ";

        if ($endDate > 0) {
            $queryString .= " AND entityDocument.expiry_date <= " . $endDate . " ";
        }

        if ($startDate > 0) {
            $queryString .= " AND entityDocument.expiry_date >= " . $startDate . " ";
        }


        $queryString .= " UNION ";

        $queryString .= "SELECT DISTINCT Dependant.uuid, entityDocument.expiry_date, CONCAT(Dependant.firstname, ' ',Dependant.lastname) as full_name, 'DEPENDANT_TEXT' as label, Relocation.uuid as assignment_uuid, Relocation.identify as assignment_number
        FROM entity_document as entityDocument
        INNER JOIN dependant as Dependant ON Dependant.uuid = entityDocument.entity_uuid
        INNER JOIN employee as Assignee ON Assignee.id = Dependant.employee_id
        INNER JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
        INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id
        INNER JOIN assignment AS Assignment ON Assignment.employee_id = Assignee.id
        INNER JOIN relocation as Relocation on Relocation.assignment_id = Assignment.id and Relocation.creator_company_id = " . ModuleModel::$company->getId() . "
        WHERE contract.to_company_id = " . ModuleModel::$company->getId() . "
            AND Assignee.status = 1
            AND Assignment.archived = false
            AND Assignment.is_terminated = false
            AND Dependant.status != -1
            AND Assignee.status != -1
            AND entityDocument.document_type_id = 2 and entityDocument.is_deleted = false ";


        if ($endDate > 0) {
            $queryString .= " AND entityDocument.expiry_date <= " . $endDate . " ";
        }

        if ($startDate > 0) {
            $queryString .= " AND entityDocument.expiry_date >= " . $startDate . " ";
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

    /**
     * @param $params
     * @return array
     */
    public
    static function __executeVisaExpiringToDownloadReport($params)
    {
        if (isset($params['visa_expire_period']) && $params['visa_expire_period'] != '') {
            $startDate = time();
            if (isset($params['visa_expire_period']) && $params['visa_expire_period'] == '1_month') {
                $endDate = time() + 30 * 86400;
            } else if (isset($params['visa_expire_period']) && $params['visa_expire_period'] == '3_months') {
                $endDate = time() + 30 * 3 * 86400;
            } else if (isset($params['visa_expire_period']) && $params['visa_expire_period'] == '6_months') {
                $endDate = time() + 30 * 6 * 86400;
            } else {
                $endDate = time() + 30 * 86400;
            }
        } else {
            $endDate = 0;
            $startDate = 0;

            if (isset($params['from_date']) && Helpers::__isTimeSecond($params['from_date'])) {
                $startDate = $params['from_date'];
            }

            if (isset($params['to_date']) && Helpers::__isTimeSecond($params['to_date'])) {
                $endDate = $params['to_date'];
            }
        }

        $queryString = '';

        $queryString .= " SELECT DISTINCT Assignee.firstname,
            Assignee.lastname,
            'EMPLOYEE_TEXT' as label,
            '' as dependant_firstname,
            '' as dependant_lastname,
            '' as dependant_relation,
            Company.name as company_name,
            entityDocument.expiry_date,
            entityDocument.delivery_date,
            entityDocument.estimated_approval_date,
            entityDocument.approval_date,
            CASE
                WHEN entityDocument.delivery_country_id > 0 THEN (SELECT country.name from country where id = entityDocument.delivery_country_id)
                ELSE ''
            END as issue_by,
            Assignee.number as ee_number, Assignee.reference as ee_reference, Assignee.workemail,
            Assignment.reference as asmt_reference, Relocation.identify as relo_identify,
            CASE
                WHEN Assignment.booker_company_id > 0 THEN (SELECT company.name from company where id = Assignment.booker_company_id)
                ELSE ''
            END as booker_name, entityDocument.name as document_name, entityDocument.number as document_number
            FROM entity_document as entityDocument
            INNER JOIN employee as Assignee ON Assignee.uuid = entityDocument.entity_uuid
            INNER JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
            INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id and contract.to_company_id = " . ModuleModel::$company->getId() . "
            INNER JOIN assignment AS Assignment ON Assignment.employee_id = Assignee.id
            INNER JOIN relocation as Relocation on Relocation.assignment_id = Assignment.id
            LEFT JOIN company as Company on Company.id = Assignment.company_id
            INNER JOIN assignment_in_contract as AIC on AIC.assignment_id = Assignment.id
            and Relocation.creator_company_id = " . ModuleModel::$company->getId() . "
        WHERE contract.to_company_id = " . ModuleModel::$company->getId() . "
            AND Assignee.status = 1
            AND Assignment.archived = false
            AND Assignment.is_terminated = false
            AND entityDocument.document_type_id = ".DocumentType::TYPE_VISA."
            and entityDocument.is_deleted = false
            AND entityDocument.expiry_date <= " . $endDate . "
            AND entityDocument.expiry_date >= " . $startDate . "
        UNION
        SELECT DISTINCT Assignee.firstname,
            Assignee.lastname,
            'DEPENDANT_TEXT' as label,
            Dependant.firstname as dependant_firstname,
            Dependant.lastname as dependant_lastname,
            Dependant.relation as dependant_relation,
            Company.name as company_name,
            entityDocument.expiry_date,
            entityDocument.delivery_date,
            entityDocument.estimated_approval_date,
            entityDocument.approval_date,
            CASE
                WHEN entityDocument.delivery_country_id > 0 THEN (SELECT country.name from country where id = entityDocument.delivery_country_id)
                ELSE ''
            END as issue_by,
            Assignee.number as ee_number, Assignee.reference as ee_reference, Assignee.workemail,
            Assignment.reference as asmt_reference, Relocation.identify as relo_identify,
            CASE
                WHEN Assignment.booker_company_id > 0 THEN (SELECT company.name from company where id = Assignment.booker_company_id)
                ELSE ''
            END as booker_name, entityDocument.name as document_name, entityDocument.number as document_number
        FROM entity_document as entityDocument
            INNER JOIN dependant as Dependant ON Dependant.uuid = entityDocument.entity_uuid
            INNER JOIN employee as Assignee ON Assignee.id = Dependant.employee_id
            INNER JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
            INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id and contract.to_company_id = " . ModuleModel::$company->getId() . "
            INNER JOIN assignment AS Assignment ON Assignment.employee_id = Assignee.id
            INNER JOIN relocation as Relocation on Relocation.assignment_id = Assignment.id
            LEFT JOIN company as Company on Company.id = Assignee.company_id
            INNER JOIN assignment_in_contract as AIC on AIC.assignment_id = Assignment.id
            and Relocation.creator_company_id = " . ModuleModel::$company->getId() . "
        WHERE contract.to_company_id = " . ModuleModel::$company->getId() . "
            AND Assignee.status = 1
            AND Assignment.archived = false
            AND Assignment.is_terminated = false
            AND Dependant.status != -1
            AND Assignee.status != -1
            AND entityDocument.document_type_id = ".DocumentType::TYPE_VISA."
            and entityDocument.is_deleted = false
            AND entityDocument.expiry_date <= " . $endDate . "
            AND entityDocument.expiry_date >= " . $startDate ;

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());

        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    /**
     * get all passport to expire
     * @return array
     */
    public
    static function executePassportExpiringReport()
    {
        $now = date('Y-m-d');
        $i = 0;

        // 1 week
        $to = date('Y-m-d', strtotime($now . ' + 7 days'));

        $queryString = "Select '0' as condition , Assignee.uuid, CONCAT(Assignee.firstname, ' ',Assignee.lastname), 'EMPLOYEE_TEXT', Assignee.passport_expiry_date, Assignment.name, Assignment.uuid ";
        $queryString .= " from employee as Assignee left join assignment as Assignment on Assignment.employee_id = Assignee.id where Assignee.passport_expiry_date < DATE('" . $to . "') and Assignee.passport_expiry_date >= DATE('" . $now;
        $queryString .= "') and Assignee.company_id = " . ModuleModel::$company->getId() . " and Assignee.status = 1  and Assignment.status = 1";

        $queryString .= " UNION Select '0' as condition , Dependant.uuid, CONCAT(Dependant.firstname, ' ',Dependant.lastname), 'DEPENDANT_TEXT', Dependant.passport_expiry_date, Assignment.name, Assignment.uuid ";
        $queryString .= " from dependant as Dependant left join assignment_dependant as AssignmentDependant on AssignmentDependant.dependant_id = Dependant.id";
        $queryString .= " left join assignment as Assignment on Assignment.id = AssignmentDependant.assignment_id where Dependant.passport_expiry_date < DATE('" . $to . "') and Dependant.passport_expiry_date >= DATE('" . $now;
        $queryString .= "') and Assignment.company_id = " . ModuleModel::$company->getId() . " and Dependant.status = 1  and Assignment.status = 1";
        // 6 month
        $to = date('Y-m-d', strtotime($now . ' + 30 days'));
        $queryString .= " UNION Select '1' as condition , Assignee.uuid, CONCAT(Assignee.firstname, ' ',Assignee.lastname), 'EMPLOYEE_TEXT', Assignee.passport_expiry_date, Assignment.name, Assignment.uuid ";
        $queryString .= " from employee as Assignee left join assignment as Assignment on Assignment.employee_id = Assignee.id where Assignee.passport_expiry_date < DATE('" . $to . "') and Assignee.passport_expiry_date >= DATE('" . $now;
        $queryString .= "') and Assignee.company_id = " . ModuleModel::$company->getId() . " and Assignee.status = 1  and Assignment.status = 1";

        $queryString .= " UNION Select '1' as condition , Dependant.uuid, CONCAT(Dependant.firstname, ' ',Dependant.lastname), 'DEPENDANT_TEXT', Dependant.passport_expiry_date, Assignment.name, Assignment.uuid ";
        $queryString .= " from dependant as Dependant left join assignment_dependant as AssignmentDependant on AssignmentDependant.dependant_id = Dependant.id";
        $queryString .= " left join assignment as Assignment on Assignment.id = AssignmentDependant.assignment_id where Dependant.passport_expiry_date < DATE('" . $to . "') and Dependant.passport_expiry_date >= DATE('" . $now;
        $queryString .= "') and Assignment.company_id = " . ModuleModel::$company->getId() . " and Dependant.status = 1  and Assignment.status = 1";

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    public static function executePassportExpiringReportV2($passport_expire_period = '', $options = [])
    {
        $now = Helpers::__getStartTimeOfDay(time());

        if ($passport_expire_period == '6_months') {
            $to = Helpers::__getStartTimeOfDay($now + (60 * 24 * 60 * 181)); // 6 months = 181 day
        } elseif ($passport_expire_period == '3_months') {
            $to = Helpers::__getStartTimeOfDay($now + (60 * 24 * 60 * 91)); // 3 months = 91 day
        } else {
            $to = Helpers::__getStartTimeOfDay($now + (60 * 24 * 60 * 31)); // 1 month = 31 day
        }

        if($passport_expire_period == "" && count($options) > 0){
            $now = $options['from_date'];
            $to = $options['to_date'];
        }

        $queryString = "SELECT Assignee.uuid,
            CONCAT(Assignee.firstname, ' ', Assignee.lastname),
            'EMPLOYEE_TEXT',
            EntityDocument.expiry_date,
            Assignment.name as ASSIGNMENT_NAME,
            Assignment.uuid as ASSIGNMENT_UUID,
            Relocation.uuid as RELOCATION_UUID,
            Relocation.name as RELOCATION_NAME
        FROM entity_document as EntityDocument
            INNER JOIN employee as Assignee ON Assignee.uuid = EntityDocument.entity_uuid
            LEFT JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
            INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id
            AND contract.to_company_id = ".ModuleModel::$company->getId()."
            INNER JOIN assignment as Assignment ON Assignment.employee_id = Assignee.id
            INNER JOIN relocation as Relocation ON Relocation.assignment_id = Assignment.id
            INNER JOIN assignment_in_contract as AIC on AIC.assignment_id = Assignment.id and Relocation.creator_company_id = ".ModuleModel::$company->getId()."
        WHERE EntityDocument.expiry_date >= ".$now."
            AND EntityDocument.expiry_date <= ".$to."
            AND EntityDocument.document_type_id = ".DocumentType::TYPE_PASSPORT."
            AND Assignee.status = 1
            AND Assignment.status = 1
            AND EntityDocument.is_active = true
            AND EntityDocument.is_deleted = false
            AND Assignee.company_id IN (
                SELECT Contract.from_company_id
                FROM contract as Contract
                WHERE Contract.to_company_id = ".ModuleModel::$company->getId()."
                    and Contract.status = 1
            )
            AND Assignment.id IN (
                SELECT assignment_id
                FROM assignment_in_contract
                WHERE contract_id in (
                        SELECT id
                        FROM contract as Contract
                        WHERE Contract.to_company_id = ".ModuleModel::$company->getId()." AND Contract.status = 1
                    )
            )
        UNION
        SELECT Dependant.uuid,
            CONCAT(Dependant.firstname, ' ', Dependant.lastname),
            'DEPENDANT_TEXT',
            EntityDocument.expiry_date,
            Assignment.name as ASSIGNMENT_NAME,
            Assignment.uuid as ASSIGNMENT_UUID,
            Relocation.uuid as RELOCATION_UUID,
            Relocation.name as RELOCATION_NAME
        FROM entity_document as EntityDocument
            INNER JOIN dependant as Dependant ON Dependant.uuid = EntityDocument.entity_uuid
            INNER JOIN employee as Assignee ON Assignee.id = Dependant.employee_id
            INNER JOIN assignment_dependant as AssignmentDependant ON AssignmentDependant.dependant_id = Dependant.id
            AND Assignee.id = Dependant.employee_id
            LEFT JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
            INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id and contract.to_company_id = ".ModuleModel::$company->getId()."
            INNER JOIN assignment AS Assignment ON Assignment.employee_id = Assignee.id
            INNER JOIN relocation as Relocation on Relocation.assignment_id = Assignment.id
            INNER JOIN company as Company on Company.id = Assignee.company_id
            LEFT JOIN assignment_in_contract as AIC on AIC.assignment_id = Assignment.id and Relocation.creator_company_id = ".ModuleModel::$company->getId()."
        WHERE EntityDocument.expiry_date >= ".$now."
            AND EntityDocument.expiry_date <= ".$to."
            AND EntityDocument.document_type_id = ".DocumentType::TYPE_PASSPORT."
            AND Dependant.status = 1
            AND Assignment.status = 1
            AND EntityDocument.is_active = true
            AND EntityDocument.is_deleted = false
            AND Assignment.company_id IN (
                SELECT Contract.from_company_id
                FROM contract as Contract
                WHERE Contract.to_company_id = ".ModuleModel::$company->getId()." and Contract.status = 1
            )
            AND Assignment.id IN (
                SELECT assignment_id
                FROM assignment_in_contract
                WHERE contract_id in (
                        SELECT id
                        FROM contract as Contract
                        WHERE Contract.to_company_id = ".ModuleModel::$company->getId()." AND Contract.status = 1
                    )
            )";

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }


    /**
     * @param $params
     * @return array
     */
    public
    static function __executePassportExpiringToDownloadReport($params)
    {
        if (isset($params['passport_expire_period']) && $params['passport_expire_period'] != '') {
            $startDate = time();
            if (isset($params['passport_expire_period']) && $params['passport_expire_period'] == '1_month') {
                $endDate = time() + 30 * 86400;
            } else if (isset($params['passport_expire_period']) && $params['passport_expire_period'] == '3_months') {
                $endDate = time() + 30 * 3 * 86400;
            } else if (isset($params['passport_expire_period']) && $params['passport_expire_period'] == '6_months') {
                $endDate = time() + 30 * 6 * 86400;
            } else {
                $endDate = time() + 30 * 86400;
            }
        } else {
            $endDate = 0;
            $startDate = 0;

            if (isset($params['from_date']) && Helpers::__isTimeSecond($params['from_date'])) {
                $startDate = $params['from_date'];
            }

            if (isset($params['to_date']) && Helpers::__isTimeSecond($params['to_date'])) {
                $endDate = $params['to_date'];
            }
        }

        $queryString = '';

        $queryString .= " SELECT DISTINCT Assignee.firstname,
            Assignee.lastname,
            'EMPLOYEE_TEXT' as label,
            '' as dependant_firstname,
            '' as dependant_lastname,
            '' as dependant_relation,
            (SELECT Company.name FROM company as Company WHERE Company.id = Assignee.company_id) as company_name,
            entityDocument.name as document_name, entityDocument.number as document_number,
            entityDocument.expiry_date,
            entityDocument.delivery_date,
            entityDocument.estimated_approval_date,
            entityDocument.approval_date,
            CASE
                WHEN entityDocument.delivery_country_id > 0 THEN (
                    SELECT country.name
                    from country
                    where id = entityDocument.delivery_country_id
                ) ELSE ''
            END as issue_by,
            Assignee.number as ee_number,
            Assignee.reference as ee_reference,
            Assignee.workemail,
            Assignment.reference as asmt_reference,
            Relocation.identify as relo_identify,
            CASE
                WHEN Assignment.booker_company_id > 0 THEN (
                    SELECT company.name
                    from company
                    where id = Assignment.booker_company_id
                ) ELSE ''
            END as booker_name,
            entityDocument.name as document_name,
            entityDocument.number as document_number
        FROM entity_document as EntityDocument
            INNER JOIN employee as Assignee ON Assignee.uuid = EntityDocument.entity_uuid
            LEFT JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
            INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id AND contract.to_company_id = ".ModuleModel::$company->getId()."
            INNER JOIN assignment as Assignment ON Assignment.employee_id = Assignee.id
            INNER JOIN relocation as Relocation ON Relocation.assignment_id = Assignment.id
            INNER JOIN assignment_in_contract as AIC on AIC.assignment_id = Assignment.id AND Relocation.creator_company_id = ".ModuleModel::$company->getId()."
        WHERE EntityDocument.expiry_date >= ".$startDate."
            AND EntityDocument.expiry_date <= ".$endDate."
            AND EntityDocument.document_type_id = ".DocumentType::TYPE_PASSPORT."
            AND Assignee.status = 1
            AND Assignment.status = 1
            AND EntityDocument.is_active = true
            AND EntityDocument.is_deleted = false
            AND Assignee.company_id IN (
                SELECT Contract.from_company_id
                FROM contract as Contract
                WHERE Contract.to_company_id = ".ModuleModel::$company->getId()." AND Contract.status = 1
            )
            AND Assignment.id IN (
                SELECT assignment_id
                FROM assignment_in_contract
                WHERE contract_id in (
                        SELECT id
                        FROM contract as Contract
                        WHERE Contract.to_company_id = ".ModuleModel::$company->getId()." AND Contract.status = 1
                    )
            )
        UNION
        SELECT DISTINCT Assignee.firstname,
            Assignee.lastname,
            'DEPENDANT_TEXT' as label,
            Dependant.firstname as dependant_firstname,
            Dependant.lastname as dependant_lastname,
            Dependant.relation as dependant_relation,
            (SELECT Company.name FROM company as Company WHERE Company.id = Assignee.company_id) as company_name,
            entityDocument.name as document_name, entityDocument.number as document_number,
            entityDocument.expiry_date,
            entityDocument.delivery_date,
            entityDocument.estimated_approval_date,
            entityDocument.approval_date,
            CASE
                WHEN entityDocument.delivery_country_id > 0 THEN (
                    SELECT country.name
                    from country
                    where id = entityDocument.delivery_country_id
                ) ELSE ''
            END as issue_by,
            Assignee.number as ee_number,
            Assignee.reference as ee_reference,
            Assignee.workemail,
            Assignment.reference as asmt_reference,
            Relocation.identify as relo_identify,
            CASE
                WHEN Assignment.booker_company_id > 0 THEN (
                    SELECT company.name
                    from company
                    where id = Assignment.booker_company_id
                ) ELSE ''
            END as booker_name,
            entityDocument.name as document_name,
            entityDocument.number as document_number
        FROM entity_document as EntityDocument
            INNER JOIN dependant as Dependant ON Dependant.uuid = EntityDocument.entity_uuid
            INNER JOIN employee as Assignee ON Assignee.id = Dependant.employee_id
            INNER JOIN assignment_dependant as AssignmentDependant ON AssignmentDependant.dependant_id = Dependant.id AND Assignee.id = Dependant.employee_id
            LEFT JOIN employee_in_contract as employeeInContract ON employeeInContract.employee_id = Assignee.id
            INNER JOIN contract as Contract ON employeeInContract.contract_id = contract.id and contract.to_company_id = ".ModuleModel::$company->getId()."
            INNER JOIN assignment as Assignment ON Assignment.employee_id = Assignee.id
            INNER JOIN relocation as Relocation ON Relocation.assignment_id = Assignment.id
            INNER JOIN company as Company on Company.id = Assignee.company_id
            LEFT JOIN assignment_in_contract as AIC on AIC.assignment_id = Assignment.id AND Relocation.creator_company_id = ".ModuleModel::$company->getId()."
        WHERE EntityDocument.expiry_date >= ".$startDate."
            AND EntityDocument.expiry_date <= ".$endDate."
            AND EntityDocument.document_type_id = ".DocumentType::TYPE_PASSPORT."
            AND Dependant.status = 1
            AND Assignment.status = 1
            AND EntityDocument.is_active = true
            AND EntityDocument.is_deleted = false
            AND Assignment.company_id IN (
                SELECT Contract.from_company_id
                FROM contract as Contract
                WHERE Contract.to_company_id = ".ModuleModel::$company->getId()."
                    and Contract.status = 1
            )
            AND Assignment.id IN (
                SELECT assignment_id
                FROM assignment_in_contract
                WHERE contract_id in (
                        SELECT id
                        FROM contract as Contract
                        WHERE Contract.to_company_id = ".ModuleModel::$company->getId()."
                            AND Contract.status = 1
                    )
            )";

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());

        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    public static function __executeHrContactToDownloadReport($params = [])
    {

        $queryString = "SELECT contact.id, contact.company_id, contact.email, contact.firstname, contact.lastname, contact.telephone, contact.mobile, contact.jobtitle, array_agg(HrC.OG) as Organisations";
        $queryString .= " FROM contact";
        $queryString .= " LEFT JOIN (SELECT company.name as OG, data_contact_member.* FROM company LEFT JOIN data_contact_member on company.id = data_contact_member.object_id WHERE data_contact_member.contact_id > 0) as HrC on HrC.contact_id = contact.id";
        $queryString .= " WHERE contact.company_id = " . ModuleModel::$company->getId() . " AND contact.is_deleted = false";
        $queryString .= " GROUP BY contact.id, contact.company_id, contact.email, contact.firstname, contact.lastname,contact.telephone,contact.mobile, contact.jobtitle";
        $queryString .= " ORDER BY contact.firstname ASC";

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    /**
     * Get full data of ee
     */
    public function parseArrayData($relocationId = 0)
    {
        $employeeArray = $this->toArray();
        $employeeArray['company_name'] = $this->getCompany()->getName();
        $employeeArray['company_uuid'] = $this->getCompany()->getUuid();
        $employeeArray['is_editable'] = $this->isEditable();
        $employeeArray['avatar'] = $this->getAvatar();
        $employeeArray['office_name'] = $this->getOffice() ? $this->getOffice()->getName() : "";
        $employeeArray['team_name'] = $this->getTeam() ? $this->getTeam()->getName() : "";
        $employeeArray['department_name'] = $this->getDepartment() ? $this->getDepartment()->getName() : "";
        $employeeArray['citizenships'] = $this->parseCitizenships();
        $employeeArray['documents'] = EntityDocument::__getDocumentsByEntityUuid($this->getUuid());
        $employeeArray['support_contacts'] = EmployeeSupportContact::__getSupportContacts($this->getId(), $relocationId);
        $employeeArray['buddy_contacts'] = EmployeeSupportContact::__getBuddyContacts($this->getId());
        $employeeArray['spoken_languages'] = $this->parseSpokenLanguages();
        $employeeArray['birth_country_name'] = $this->getBirthCountry() ? $this->getBirthCountry()->getName() : "";
        $employeeArray['hasLogin'] = $this->hasLogin();
        $employeeArray['last_login'] = $this->getUserLogin() ? $this->getUserLogin()->getLastconnectAt() : "";
        $employeeArray['first_login'] = $this->getUserLogin() ? $this->getUserLogin()->getFirstconnectAt() : "";
        $employeeArray['hasUserLogin'] = $this->getUserLogin() ? true : false;
        $employeeArray['login_email'] = $this->getUserLogin() ? $this->getUserLogin()->getEmail() : null;
        return $employeeArray;
    }

    /**
     * @return boolean
     */
    public function hasLogin()
    {
        if ($this->getUserLogin()) {
            $userLogin = $this->getUserLogin();
            $resultCognito = $userLogin->isConvertedToUserCognito();
            if ($resultCognito) {
                $cognitoLogin = $userLogin->getCognitoLogin();
                if ($cognitoLogin && ($cognitoLogin['userStatus'] == CognitoClient::UNCONFIRMED || $cognitoLogin['userStatus'] == CognitoClient::FORCE_CHANGE_PASSWORD)) {
                    return false;
                } else {
                    return true;
                }
            }
        }
        return false;
    }

    public static function __convertLanguage($lang = ''){
        if($lang == ''){
            return '';
        }
        $lang = json_decode($lang);
        if(is_array($lang) && count($lang) > 0) {
            $result = [];
            $allLang = LanguageCode::getAllTranslation('en');
            for($i = 0; $i < count($lang); $i++){
                foreach ($allLang as $key => $value){
                    if($key == $lang[$i]){
                        array_push($result, $value);
                        continue;
                    }
                }
            }

            if(count($result) > 0){
                $lang = json_encode($result);
                $lang = str_replace('\"', '', $lang);
                $lang = str_replace('"', '', $lang);
            }
        }
        return $lang;
    }
}
