<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\ModuleModel as ModuleModel;
use Reloday\Application\Lib\Helpers as Helpers;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\MemberType;
use Reloday\Gms\Models\ObjectMap;
use Phalcon\Utils\Slug as PhpSlug;
use \Phalcon\Mvc\Model\Transaction\Failed as TransationFailed;
use \Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Reloday\Gms\Module;
use Reloday\Application\Lib\JWTEncodedHelper;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;


class Task extends \Reloday\Application\Models\TaskExt
{
    public $due_at_time = 0;
    const LIMIT_PER_PAGE = 20;
    const FULL_INFO_YES = true;
    const FULL_INFO_NO = false;

    /**
     * initialize all for all
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('owner_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'UserProfile'
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\Task', 'parent_task_id', [
            'alias' => 'SubTask'
        ]);

        $this->hasManyToMany('uuid', 'Reloday\Gms\Models\DataUserMember', 'object_uuid', 'user_profile_uuid', 'Reloday\Gms\Models\UserProfile', 'uuid', [
            'alias' => 'Members',
            'params' => ['distinct' => true]
        ]);

        $this->hasManyToMany('uuid', 'Reloday\Gms\Models\DataUserMember', 'object_uuid', 'user_profile_uuid', 'Reloday\Gms\Models\UserProfile', 'uuid', [
            'alias' => 'Reporters',
            'params' => [
                'distinct' => true,
                'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = :type:',
                'bind' => [
                    'type' => DataUserMember::MEMBER_TYPE_REPORTER
                ]
            ],
            'cache' => [
                'key' => CacheHelper::getCacheNameTaskReporter($this->getUuid()),
                'lifetime' => 86400
            ]
        ]);

        $this->hasManyToMany('uuid', 'Reloday\Gms\Models\DataUserMember', 'object_uuid', 'user_profile_uuid', 'Reloday\Gms\Models\UserProfile', 'uuid', [
            'alias' => 'Owners',
            'params' => [
                'distinct' => true,
                'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = :type:',
                'bind' => [
                    'type' => DataUserMember::MEMBER_TYPE_OWNER
                ]
            ],
            'cache' => [
                'key' => CacheHelper::getCacheNameTaskOwner($this->getUuid()),
                'lifetime' => 86400
            ]

        ]);
        /**
         * assignment
         */
        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\Assignment', 'uuid', [
            'alias' => 'Assignment'
        ]);

        /**
         * assignment
         */
        $this->belongsTo('assignment_id', 'Reloday\Gms\Models\Assignment', 'id', [
            'alias' => 'MainAssignment'
        ]);
        /**
         * relocation
         */
        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\Relocation', 'uuid', [
            'alias' => 'Relocation'
        ]);


        /**
         * relocation
         */
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'MainRelocation'
        ]);

        /**
         * relocation service company
         */
        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\RelocationServiceCompany', 'uuid', [
            'alias' => 'RelocationServiceCompany'
        ]);

        /**
         * relocation service company
         */
        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', [
            'alias' => 'MainRelocationServiceCompany'
        ]);


        /**
         * relocation
         */
        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee',
            'cache' => [
                'key' => 'EMPLOYEE_' . $this->getEmployeeId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        /**
         * Task
         */
        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\Task', 'uuid', [
            'alias' => 'Task'
        ]);


        /**
         * Reminder
         */
        $this->hasMany('uuid', 'Reloday\Gms\Models\ReminderConfig', 'object_uuid', [
            'alias' => 'ReminderConfig',
        ]);

        /**
         * CreatorUserProfile
         */
        $this->belongsTo('creator_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'CreatorUserProfile',
            'cache' => [
                'key' => 'USER_PROFILE_' . $this->getCreatorId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        /**
         * parent task
         */
        $this->belongsTo('parent_task_id', 'Reloday\Gms\Models\Task', 'id', [
            'alias' => 'ParentTask'
        ]);

        $this->addBehavior(new SoftDelete([
            'field' => 'status',
            'value' => self::STATUS_ARCHIVED
        ]));
    }


    /**
     * @param array $custom (condition of search)
     */
    public static function __loadList($custom = [])
    {
        $params = null;
        $conditions = "status <> :status_archived:";
        $bindArray = ['status_archived' => self::STATUS_ARCHIVED];
        //search by object_uuid
        if (isset($custom['object_uuid']) && $custom['object_uuid'] != '') {
            $conditions .= " AND object_uuid = :object_uuid:";
            $bindArray['object_uuid'] = $custom['object_uuid'];
        }

        if(isset($custom['list_object_uuid']) && count($custom['list_object_uuid']) > 0){
            $conditions .= "AND object_uuid IN ({list_object_uuid:array})";
            $bindArray['list_object_uuid'] = $custom['list_object_uuid'];
        }

        if (isset($custom['task_type']) && $custom['task_type'] ) {
            if ($custom['task_type'] != 'all') {
                $conditions .= " AND task_type = :task_type:";
                $bindArray['task_type'] = $custom['task_type'];
            }
        } else {
            $conditions .= " AND task_type = :task_type:";
            $bindArray['task_type'] = self::TASK_TYPE_INTERNAL_TASK;
        }

        if (isset($custom['company_id']) && $custom['company_id'] != '') {
            $conditions .= " AND company_id = :company_id:";
            $bindArray['company_id'] = $custom['company_id'];
        }

        if (isset($custom['ids']) && count($custom['ids']) > 0) {
            $conditions .= " AND id IN ({ids:array})";
            $bindArray['ids'] = $custom['ids'];
        }

        if (isset($custom['query']) && $custom['query'] != '') {
            $conditions .= " AND (name like :query: OR number like :query:)";
            $bindArray['query'] = '%' . $custom['query'] . '%';
        }

        $params = [
            'conditions' => $conditions,
            'bind' => $bindArray,
            'order' => 'task_type ASC, sequence ASC' // position ASC, created_at ASC
        ];

        //var_dump($params); die();

        if (count($custom) > 0) {
            $tasks = self::find($params);
            $tasks_array = [];
            if ($tasks->count() > 0) {
                foreach ($tasks as $task) {
                    $tasks_array[$task->getUuid()] = $task->toArray();
                    $tasks_array[$task->getUuid()]['progress'] = (int)$task->getProgress();
                    $tasks_array[$task->getUuid()]['due_at_time'] = $task->getDueAtTime();
                    $tasks_array[$task->getUuid()]['owner_uuid'] = ($task->getSimpleProfileOwner()) ? $task->getSimpleProfileOwner()->getUuid() : "";
                    $tasks_array[$task->getUuid()]['owner_name'] = ($task->getSimpleProfileOwner()) ? $task->getSimpleProfileOwner()->getFirstname() . " " . $task->getSimpleProfileOwner()->getLastname() : null;
                    $tasks_array[$task->getUuid()]['creator_name'] = ($task->getCreatorUserProfile()) ? $task->getCreatorUserProfile()->getFirstname()." ".$task->getCreatorUserProfile()->getLastname() : null;
                    $tasks_array[$task->getUuid()]['related'] = $task->getRelatedItem();
                    $tasks_array[$task->getUuid()]['data_member_owner_name'] = ($task->getOwners()->getFirst()) ? $task->getOwners()->getFirst()->getFirstName()." ".$task->getOwners()->getFirst()->getLastName() : null;
                    $tasks_array[$task->getUuid()]['data_member_reporter_name'] = ($task->getReporters()->getFirst()) ? $task->getReporters()->getFirst()->getFirstName()." ".$task->getReporters()->getFirst()->getLastName() : null;

                    $tasks_array[$task->getUuid()]['relocation_service_id'] = (self::getRelocationServiceByObjectUuid($task->getObjectUuid())) ? self::getRelocationServiceByObjectUuid($task->getObjectUuid())->getId() : null;
                    $tasks_array[$task->getUuid()]['is_done'] = $task->isDone();
                }
            }
            if (isset($custom['isArray']) && $custom['isArray'] == true) {
                return array_values($tasks_array);
            } else {
                return ($tasks_array);
            }
        }
    }

    /**
     * @param bool $full_info
     * @return mixed
     */
    public static function __loadListFromViewer($full_info = true, $options, $order = [])
    {
        $options['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
        return self::__findWithFilter($full_info, $options, $order);
    }

    /**
     * @param $full_info
     * @param $options
     * @param $order
     * @return array
     * //TODO use CLOUDSEARCH for FULLTEXT SEARCH
     */
    public static function __findWithFilter($full_info = true, $options, $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Task', 'Task');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Task.employee_id = Employee.id', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Task', 'Task.parent_task_id = ParentTask.id', 'ParentTask');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = Task.relocation_service_company_id', 'RelocationServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelatedRelocationServiceCompany.uuid = Task.object_uuid', 'RelatedRelocationServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Assignment', 'RelatedAssignment.uuid = Task.object_uuid', 'RelatedAssignment');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'RelatedRelocation.uuid = Task.object_uuid', 'RelatedRelocation');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = RelatedRelocationServiceCompany.service_company_id', 'ServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Assignment', 'Assignment.id = Task.assignment_id', 'Assignment');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = Task.relocation_id', 'Relocation');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwner.object_uuid = Task.uuid and DataUserOwner.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER, 'DataUserOwner');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserProfile', 'DataUserOwner.user_profile_uuid = OwnerUserProfile.uuid', 'OwnerUserProfile');
        $queryBuilder->distinct(true);

        if (isset($options['is_count_all']) && $options['is_count_all'] == true) {
            $queryBuilder->columns([
                'Task.id'
            ]);
        }

        $queryBuilder->columns([
            'Task.id',
            'Task.uuid',
            'Task.sequence',
            'Task.number',
            'Task.name',
            'Task.description',
            'Task.due_at',
            'Task.started_at',
            'Task.ended_at',
            'Task.created_at',
            'Task.updated_at',
            'Task.status',
            'Task.is_archive',
            'Task.progress',
            'Task.link_type',
            'Task.employee_id',
            'Task.relocation_id',
            'Task.assignment_id',
            'Task.relocation_service_company_id',
            'Task.parent_task_id',
            'Task.task_template_company_id',
            'Task.creator_id',
            'Task.object_uuid',
            'Task.company_id',
            'Task.is_priority',
            'Task.is_flag',
            'Task.is_milestone',
            'Task.is_final_review',
            'Task.task_type',
            'Task.has_file',
            'owner_name' => 'CONCAT(OwnerUserProfile.firstname, " ", OwnerUserProfile.lastname)',
            'owner_uuid' => 'DataUserOwner.user_profile_uuid',
            'owner_id' => 'OwnerUserProfile.id',
            "employee_name" =>  'CONCAT(Employee.firstname, " ", Employee.lastname)',
            "employee_uuid" => 'Employee.uuid',
            "related_item_name" => 'If(Task.link_type = 1, RelatedAssignment.reference, IF(Task.link_type = 2, RelatedRelocation.identify, IF(Task.link_type = 3, ServiceCompany.name, IF(Task.link_type = 4, ParentTask.number, ""))))',
            "related_item_number" => 'IF(Task.link_type = 3, RelatedRelocationServiceCompany.number, "")',
            "related_item_uuid" => 'If(Task.link_type = 1, RelatedAssignment.uuid, IF(Task.link_type = 2, RelatedRelocation.uuid, IF(Task.link_type = 3, ServiceCompany.uuid, IF(Task.link_type = 4, ParentTask.uuid, ""))))',
        ]);
        $queryBuilder->where('Task.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andwhere("Task.status <> :status_archived:", [
            'status_archived' => self::STATUS_ARCHIVED
        ]);
        /** can not load tast canceled */
        $queryBuilder->andwhere("Task.progress <> :status_canceled:", [
            'status_canceled' => self::STATUS_STOP_PROGRESS
        ]);

        $queryBuilder->andwhere("Task.is_archive = :archive_no:", [
            'archive_no' => ModelHelper::NO
        ]);

        $queryBuilder->andwhere("Task.parent_task_id is NULL");

        if (
            isset($options['due_date_begin']) && is_string($options['due_date_begin']) && $options['due_date_begin'] != '' &&
            isset($options['due_date_end']) && is_string($options['due_date_end']) && $options['due_date_end'] != ''
        ) {
            $queryMust[] = ["range" => ["due_at" => [
                "gte" => strtotime($options['due_date_begin']),
                "lt" => strtotime($options['due_date_end'])
            ]]];
            $queryBuilder->andwhere("Task.due_at >= :due_date_begin: AND Task.due_at < :due_date_end:", [
                'due_date_begin' => $options['due_date_begin'],
                'due_date_end' => $options['due_date_end'],
            ]);
        }

        $bindArray = [];
        $bindArray['company_id'] = ModuleModel::$company->getId();
        $bindArray['link_type_task'] = self::LINK_TYPE_TASK;
        $bindArray['status_archived'] = self::STATUS_ARCHIVED;

        if (isset($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMemberSearch.object_uuid = Task.uuid', 'DataUserMemberSearch');
            $queryBuilder->andwhere("DataUserMemberSearch.user_profile_uuid = :user_profile_uuid:", ["user_profile_uuid" => $options['user_profile_uuid']]);
            $bindArray['user_profile_uuid'] = $options['user_profile_uuid'];
        }


        if (isset($options['my']) && $options['my'] == true) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataOwner.object_uuid = Task.uuid', 'DataOwner');
            $queryBuilder->andwhere("DataOwner.member_type_id = :member_type_id:", ["member_type_id" => DataUserMember::MEMBER_TYPE_OWNER]);
            $bindArray['member_type_id'] = DataUserMember::MEMBER_TYPE_OWNER;
        }

        if (isset($options['owner_uuid']) && Helpers::__isValidUuid($options['owner_uuid']) && $options['owner_uuid'] != '' && ModuleModel::$user_profile->isAdminOrManager() == true) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwnerSearch.object_uuid = Task.uuid', 'DataUserOwnerSearch');
            $queryBuilder->andwhere("DataUserOwnerSearch.user_profile_uuid = :user_profile_uuid: and DataUserOwnerSearch.member_type_id = :_member_type_owner:", [
                "user_profile_uuid" => $options['owner_uuid'],
                "_member_type_owner" => DataUserMember::MEMBER_TYPE_OWNER
            ]);
        }


        if (isset($options['open']) && $options['open'] == true) {
            $queryBuilder->andWhere("Task.progress IN ({progress_status:array})", ['progress_status' => [self::STATUS_IN_PROCESS, self::STATUS_NOT_STARTED]]);
            $bindArray['progress_status'] = [self::STATUS_IN_PROCESS, self::STATUS_NOT_STARTED];
        }

        if (isset($options['completed']) && $options['completed'] == true) {
            $queryBuilder->andwhere("Task.progress = :progress:", ['progress' => self::STATUS_COMPLETED]);
            $bindArray['progress'] = self::STATUS_COMPLETED;
        }

        if (isset($options['task_type'])) {
            $queryBuilder->andwhere("Task.task_type = :task_type:", ['task_type' => $options['task_type']]);
        } else {
            $queryBuilder->andwhere("Task.task_type = :task_type:", ['task_type' => self::TASK_TYPE_INTERNAL_TASK]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Task.name LIKE :query: OR Task.number LIKE :query:
            OR CONCAT(Employee.firstname,' ',Employee.lastname) LIKE :query:
            OR RelocationServiceCompany.name LIKE :query:",
                ['query' => '%' . $options['query'] . '%']);
        }

        if (isset($options['due_date']) && is_string($options['due_date']) && $options['due_date'] != '') {
            $due_at_begin = Helpers::__getDateBegin($options['due_date']);
            $due_at_end = Helpers::__getNextDate($options['due_date']);
            $queryBuilder->andwhere("Task.due_at >= :due_at_begin: AND Task.due_at < :due_at_end:",
                ['due_at_begin' => $due_at_begin, 'due_at_end' => $due_at_end]
            );
        }

        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners'])) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataOwners.object_uuid = Task.uuid', 'DataOwners');
            $queryBuilder->andwhere("DataOwners.user_profile_uuid IN ({owners:array} ) AND DataOwners.member_type_id = :member_type_owner:", [
                'owners' => $options['owners'],
                'member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
            ]);
        }


        if (isset($options['assignees']) && is_array($options['assignees']) && count($options['assignees'])) {
            $queryBuilder->andwhere("Task.employee_id IN ({assignees:array})", [
                'assignees' => $options['assignees'],
            ]);
        }

        if (isset($options['relocations']) && is_array($options['relocations']) && count($options['relocations'])) {
            $queryBuilder->andwhere("Task.employee_id IN ({assignees:array})", [
                'assignees' => $options['assignees'],
            ]);
        }

        if (isset($options['statuses']) && is_array($options['statuses']) && count($options['statuses'])) {
            $queryBuilder->andwhere("Task.progress IN ({statuses:array})", [
                'statuses' => $options['statuses'],
            ]);
        }

        if (isset($options['services']) && is_array($options['services']) && count($options['services'])) {
            $queryBuilder->andwhere("RelocationServiceCompany.service_company_id IN ({services:array})", [
                'services' => $options['services'],
            ]);
        }

        if (isset($options['hr_company_id']) && is_numeric($options['hr_company_id']) && $options['hr_company_id'] > 0) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'AssignmentAccount.id = Task.assignment_id', 'AssignmentAccount');
            $queryBuilder->andwhere("AssignmentAccount.company_id = :hr_company_id:", [
                'hr_company_id' => $options['hr_company_id'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Task.employee_id = :employee_id:", [
                'employee_id' => $options['employee_id'],
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'Assignment.company_id',
                'OWNER_ARRAY_TEXT' => 'Owner.user_profile_id',
                'REPORTER_ARRAY_TEXT' => 'Reporter.user_profile_id',
                'SERVICE_ARRAY_TEXT' => 'RelocationServiceCompany.service_company_id',
                'STATUS_ARRAY_TEXT' => 'Task.progress',
                'START_DATE_TEXT' => 'Task.started_at',
                'END_DATE_TEXT' => 'Task.ended_at',
                'DUE_DATE_TEXT' => 'Task.due_at',
                'PREFIX_DATA_OWNER_TYPE' => 'Owner.member_type_id',
                'PREFIX_DATA_REPORTER_TYPE' => 'Reporter.member_type_id',
                'PREFIX_OBJECT_UUID' => 'Task.uuid',
                'PRIORITY_ARRAY_TEXT' => 'Task.is_priority',
                'FLAG_TEXT' => 'Task.is_flag',
                'MILESTONE_TEXT' => 'Task.is_milestone',
            ];

            $dataType = [
                'FLAG_TEXT' => 'int',
                'MILESTONE_TEXT' => 'int',
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::TASK_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }


        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / self::LIMIT_PER_PAGE) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        // $tasks = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('Task.created_at DESC');
            if ($order['field'] == "progress") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Task.progress ASC', 'Task.created_at DESC']);
                } else {
                    $queryBuilder->orderBy(['Task.progress DESC', 'Task.created_at DESC']);
                }
            }

            if ($order['field'] == "number") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Task.number ASC', 'Task.created_at DESC']);
                } else {
                    $queryBuilder->orderBy(['Task.number DESC', 'Task.created_at DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Task.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Task.created_at DESC']);
                }
            }

            if ($order['field'] == "due_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Task.due_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Task.due_at DESC']);
                }
            }

            if ($order['field'] == "started_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Task.started_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Task.started_at DESC']);
                }
            }

            if ($order['field'] == "ended_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Task.ended_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Task.ended_at DESC']);
                }
            }

            if ($order['field'] == "is_priority") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Task.is_priority ASC']);
                } else {
                    $queryBuilder->orderBy(['Task.is_priority DESC']);
                }
            }

        } else {
            $queryBuilder->orderBy('Task.created_at DESC');
        }


        $queryBuilder->groupBy('Task.id');
        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => self::LIMIT_PER_PAGE,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $tasks_array = [];

            if (isset($options['is_count_all']) && $options['is_count_all'] == true) {
                goto end_of_return;
            }

            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $task) {
                    $item = $task->toArray();
                    $item['due_at_time'] = strtotime($item["due_at"]);
                    $item['id'] = intval($item["id"]);
                    $item['sequence'] = intval($item["sequence"]);
                    $item['status'] = intval($item["status"]);
                    $item['is_archive'] = intval($item["is_archive"]);
                    $item['progress'] = intval($item["progress"]);
                    $item['link_type'] = intval($item["link_type"]);
                    $item['employee_id'] = intval($item["employee_id"]);
                    $item['relocation_id'] = intval($item["relocation_id"]);
                    $item['assignment_id'] = intval($item["assignment_id"]);
                    $item['relocation_service_company_id'] = intval($item["relocation_service_company_id"]);
                    $item['parent_task_id'] = intval($item["parent_task_id"]);
                    $item['task_template_company_id'] = intval($item["task_template_company_id"]);
                    $item['owner_id'] = intval($item["owner_id"]);
                    $item['company_id'] = intval($item["company_id"]);
                    $item['is_priority'] = intval($item["is_priority"]);
                    $item['is_flag'] = intval($item["is_flag"]);
                    $item['is_milestone'] = intval($item["is_milestone"]);
                    $item['is_final_review'] = intval($item["is_final_review"]);
                    $item['task_type'] = intval($item["task_type"]);
                    $item['has_file'] = intval($item["has_file"]);
                    switch ($item["link_type"]){
                        case self::LINK_TYPE_ASSIGNMENT:
                            $item['related']['state'] = "app.assignment.dashboard({uuid:'" . $item["object_uuid"] . "'})";
                            break;
                        case self::LINK_TYPE_RELOCATION:
                            $item['related']['state'] = "app.relocation.dashboard({uuid:'" . $item["object_uuid"] . "'})";
                            break;
                        case self::LINK_TYPE_SERVICE:
                            $item['related']['state'] = "app.relocation.service-detail({uuid:'" . $item["object_uuid"] . "'})";
                            break;
                        case self::LINK_TYPE_TASK:
                            $item['related']['state'] = "app.tasks.page({uuid:'" . $item["object_uuid"] . "'})";
                            break;
                        default:
                            $item['related']['state'] = "";
                            break;
                    }
                    $item['related']['name'] = $item["related_item_name"];

                    $tasks_array[$item['uuid']] = $item;
                }
            }

            end_of_return:
            return [
                'success' => true,
//                'query' => $queryBuilder->getQuery()->getSql(),
                'page' => $page,
                'data' => array_values($tasks_array),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
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
     *
     */
    public function afterFetch()
    {
        parent::afterFetch(); // TODO: Change the autogenerated stub
        if ($this->getDueAt() != '') {
            $this->due_at_time = strtotime($this->getDueAt());
        }
    }

    /**
     * @return false|int
     */
    public function getDueAtTime()
    {
        if ($this->getDueAt() != '') {
            return strtotime($this->getDueAt());
        }
    }

    /**
     * @return false|int
     */
    public function getStartedAtTime()
    {
        if ($this->getStartedAt() != '') {
            return strtotime($this->getStartedAt());
        }
    }

    /**
     * @return false|int
     */
    public function getEndedAtTime()
    {
        if ($this->getEndedAt() != '') {
            return strtotime($this->getEndedAt());
        }
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
        if ($req->isPut()) {
            if (!($model->getId() > 0)) {
                $task_uuid = isset($custom['uuid']) && $custom['uuid'] > 0 ? $custom['uuid'] : $req->getPut('uuid');
                if ($task_uuid != '') {
                    $model = $this->findFirstByUuid($task_uuid);
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
            $uuid = isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid();
            if ($uuid == '') {
                $uuid = ApplicationModel::uuid();
            }
            if ($uuid != '') {
                $model->setUuid($uuid);
            }
        }

        $company_id = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('company_id', $custom), $model->getCompanyId());
        $link_type = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('link_type', $custom), $model->getLinkType());
        $object_uuid = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('object_uuid ', $custom), $model->getObjectUuid());
        $sequence = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('sequence ', $custom), $model->getSequence());

        if ($company_id == 0 || is_null($company_id)) {
            $result = [
                'success' => false,
                'message' => 'COMPANY_REQUIRED_TEXT',
                'raw' => $custom,
                'method' => __METHOD__,
                'infos' => $company_id
            ];
            return $result;
        }

        if (is_null($link_type)) {
            $result = [
                'success' => false,
                'message' => 'LINK_TYPE_REQUIRED_TEXT',
                'raw' => $custom,
                'method' => __METHOD__,
                'infos' => $link_type,
            ];
            return $result;
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
                && $field_name != "password"
                && $field_name != "started_at"
                && $field_name != "due_at"
                && $field_name != "ended_at"
                && $field_name != "reminder_date"
                && $field_name != "reminder_time"
            ) {

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

        /*** STARTED AT **/
        $started_at_time = Helpers::__getRequestValueWithCustom('started_at_time', $custom);
        if ($started_at_time != '') {
            $started_at = date('Y-m-d H:i:s', strtotime($started_at_time));
            $model->setStartedAt($started_at);
        } else {
            if (Helpers::__existRequestValueWithCustom('started_at_time', $custom)) {
                $model->setStartedAt(null);
            }
        }

        $started_at = Helpers::__getRequestValueWithCustom('started_at', $custom);
        if ($started_at != '' && strtotime($started_at) != false) {
            $started_at = date('Y-m-d H:i:s', strtotime($started_at));
            $model->setStartedAt($started_at);
        } else {
            if (Helpers::__existRequestValueWithCustom('started_at', $custom)) {
                $model->setStartedAt(null);
            }
        };

        /*** DUE AT **/
        $due_at_time = Helpers::__getRequestValueWithCustom('due_at_time', $custom);
        if ($due_at_time != '') {
            $due_at = date('Y-m-d H:i:s', strtotime($due_at_time));
            $model->setDueAt($due_at);
        } else {
            if (Helpers::__existRequestValueWithCustom('due_at_time', $custom)) {
                $model->setDueAt(null);
            }
        }

        $due_at = Helpers::__getRequestValueWithCustom('due_at', $custom);
        if ($due_at != '' && strtotime($due_at) != false) {
            $due_at = date('Y-m-d H:i:s', strtotime($due_at));
            $model->setDueAt($due_at);
        } else {
            if (Helpers::__existRequestValueWithCustom('due_at', $custom)) {
                $model->setDueAt(null);
            }
        }


        /*** DUE AT **/
        $ended_at_time = Helpers::__getRequestValueWithCustom('ended_at_time', $custom);
        if ($ended_at_time != '') {
            $ended_at = date('Y-m-d H:i:s', strtotime($ended_at_time));
            $model->setEndedAt($ended_at);
        } else {
            if (Helpers::__existRequestValueWithCustom('ended_at_time', $custom)) {
                $model->setEndedAt(null);
            }
        }

        $ended_at = Helpers::__getRequestValueWithCustom('ended_at', $custom);
        if ($ended_at != '' && strtotime($ended_at) != false) {
            $ended_at = date('Y-m-d H:i:s', strtotime($ended_at));
            $model->setEndedAt($ended_at);
        } else {
            if (Helpers::__existRequestValueWithCustom('ended_at', $custom)) {
                $model->setEndedAt(null);
            }
        }
        /**** REMINDER DATE **/
        $reminder_date_converted = Helpers::__getRequestValueWithCustom('reminder_date_converted', $custom);
        if ($reminder_date_converted != '') {
            $reminder_date = date('Y-m-d H:i:s', strtotime($reminder_date_converted));
            $model->setReminderDate($reminder_date);
        } else {
            if (Helpers::__existRequestValueWithCustom('reminder_date_converted', $custom)) {
                $model->setReminderDate(null);
            }
        }

        $reminder_date = Helpers::__getRequestValueWithCustom('reminder_date', $custom);
        if ($reminder_date != '' && strtotime($reminder_date) != false) {
            $reminder_date = date('Y-m-d H:i:s', strtotime($reminder_date));
            $model->setReminderDate($reminder_date);
        } else {
            if (Helpers::__existRequestValueWithCustom('reminder_date', $custom)) {
                $model->setReminderDate(null);
            }
        }
        /**** REMINDER TILE **/
        $reminder_time = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('reminder_time', $custom), $model->getReminderTime());
        if (is_numeric($reminder_time) && $reminder_time > 0) {
            $model->setReminderTime($reminder_time);
        }

        $recurrence_time = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('recurrence_time', $custom), $model->getRecurrenceTime());
        if (is_numeric($recurrence_time) && $recurrence_time > 0) {
            $model->setRecurrenceTime($recurrence_time);
        }


        $progress = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('progress', $custom), $model->getProgress());
        if ($progress == null) {
            $progress = self::STATUS_NOT_STARTED;
        }
        //** set date with process */
        if ($model->getProgress() !== self::STATUS_COMPLETED) {
            if ($progress != self::STATUS_COMPLETED) {
                $model->setEndedAt(null);
            }
        } else {
            if ($progress == self::STATUS_COMPLETED) {
                $model->setEndedAt(date('Y-m-d'));
            }
        }

        /** @var process $status */
        $status = Helpers::__coalesce(Helpers::__getRequestValueWithCustom('status', $custom), $model->getStatus());
        if ($status == null) {
            $status = self::STATUS_DRAFT;
        }
        /** set number of task */
        if ($model->getNumber() == '') {
            $taskType = $model->getTaskType() ? $model->getTaskType() : self::TASK_TYPE_INTERNAL_TASK;
            $number = self::__createNumberTask($company_id, $link_type, $taskType);
            $model->setNumber($number);
        }
        /** company_id */
        if ($company_id > 0) {
            $model->setCompanyId($company_id);
        }
        if ($link_type >= 0) {
            $model->setLinkType($link_type);
        }

        /****** END YOUR CODE **/
        try {

            if ($model->getId() == null) {
                $isCreate = true;
            } else {
                $isCreate = false;
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
                    'message' => 'TASK_SAVE_FAIL_TEXT',
                    'raw' => $msg,
                    'method' => __METHOD__,
                    'infos' => $model
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'TASK_SAVE_FAIL_TEXT',
                'raw' => $e->getMessage(),
                'method' => __METHOD__,
                'infos' => $model
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'TASK_SAVE_FAIL_TEXT',
                'raw' => $e->getMessage(),
                'method' => __METHOD__,
                'infos' => $model
            ];
            return $result;
        }
    }


    /**
     * @return bool
     * check exist in cloud
     * @deprecated
     */
    public static function __existInCloud($task_uuid)
    {
        return false;
    }

    /**
     * @return bool
     * @deprecated
     */
    public function transfertToCloud()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getSenderEmailComments()
    {
        return "task_" . base64_encode($this->getUuid()) . "@" . getenv('SENDER_COMMENT_DOMAIN');
    }

    /**
     * URL sender mail of profile
     * @param $profile
     * @return string
     */
    public function getUserSenderEmailComments($profile)
    {
        $prefix = PhpSlug::generate($profile->getFirstname() . " " . $profile->getLastname());
        $user_uuid_base64 = base64_encode($profile->getUuid());
        return $prefix . "+" . $user_uuid_base64 . "+" . $this->getSenderEmailComments();
    }

    /**
     * @param $email
     * @return string
     */
    public function getSenderEmailCommentWithEmail($email)
    {
        return "task_" . base64_encode($this->getUuid()) . "_" . base64_encode($email) . "@" . getenv('SENDER_COMMENT_DOMAIN');
    }

    /**
     * @return string
     */
    public function getFrontendUrl()
    {
        return ModuleModel::$app->getFrontendUrl() . "/#/app/tasks/page/" . $this->getUuid();
    }

    /**
     * return the real reminder date (reminderdate vs event date)
     * @return string
     */
    public function getRealReminderDate()
    {
        return $this->getReminderDate();
    }

    /**
     * @return bool
     */
    public function checkReminderApplyActive()
    {
        if ($this->getBeforeAfter() == null ||
            $this->getReminderDate() == null ||
            $this->getReminderTime() == null ||
            $this->getReminderTimeUnit() == null
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @return int
     */
    public function getReminderQuantityTimeInSecond()
    {
        switch ($this->getReminderTimeUnit()) {
            case self::MINUTE :
                return intval($this->getReminderTime()) * 60;
                break;

            case self::HOUR :
                return intval($this->getReminderTime()) * 60 * 60;
                break;

            case self::DAY :
                return intval($this->getReminderTime()) * 60 * 60 * 24;
                break;
            case self::WEEK :
                return intval($this->getReminderTime()) * 60 * 60 * 24 * 7;
                break;

            case self::MONTH :
                return intval($this->getReminderTime()) * 60 * 60 * 24 * 7 * 30;
                break;

            case self::YEAR :
                return intval($this->getReminderTime()) * 60 * 60 * 24 * 7 * 365;
                break;
            default:
                return 0;
                break;
        }
    }

    /**
     * @return false|int
     */
    public function getReminderBeginAt()
    {
        if ($this->checkReminderApplyActive() == false) return 0;

        if ($this->getBeforeAfter() == self::AFTER) {
            return strtotime($this->getRealReminderDate());
        } else {
            return strtotime($this->getRealReminderDate()) - intval($this->getReminderQuantityTimeInSecond());
        }
    }

    /**
     * @return false|int
     */
    public function getReminderEndAt()
    {
        if ($this->checkReminderApplyActive() == false) return 0;

        if ($this->getBeforeAfter() == self::AFTER) {
            return strtotime($this->getRealReminderDate()) + intval($this->getReminderQuantityTimeInSecond());
        } else {
            return strtotime($this->getRealReminderDate());
        }
    }

    /**
     * @return int
     */
    public function getReminderRecurrenceSecond()
    {
        if ($this->checkReminderApplyActive() == false) return 0;

        switch ($this->getRecurrenceTimeUnit()) {
            case self::MINUTE :
                return intval($this->getRecurrenceTime()) * 60;
                break;

            case self::HOUR :
                return intval($this->getRecurrenceTime()) * 60 * 60;
                break;

            case self::DAY :
                return intval($this->getRecurrenceTime()) * 60 * 60 * 24;
                break;
            case self::WEEK :
                return intval($this->getRecurrenceTime()) * 60 * 60 * 24 * 7;
                break;

            case self::MONTH :
                return intval($this->getRecurrenceTime()) * 60 * 60 * 24 * 7 * 30;
                break;

            case self::YEAR :
                return intval($this->getRecurrenceTime()) * 60 * 60 * 24 * 7 * 365;
                break;
            default:
                return 0;
                break;
        }
    }

    /**
     * @return int
     */
    public function getRecurrenceNumber()
    {
        if ($this->getReminderRecurrenceSecond() >= ($this->getReminderEndAt() - $this->getReminderBeginAt())) {
            return 1;
        } else {
            return intval(($this->getReminderEndAt() - $this->getReminderBeginAt()) / $this->getReminderRecurrenceSecond());
        }
    }

    /**
     * getDynamo Array Data
     * @return array
     */
    public function getDynamoArrayData()
    {
        $array = $this->toArray();
        $array['frontend_url'] = $this->getFrontendUrl();
        $return = [];
        foreach ($array as $key => $value) {
            if ($value != null && $value != '') $return[$key] = ['S' => $value];
        }
        return $return;
    }

    /**
     * get JWT encoded Data
     * @return string
     */
    public function getJWTEncodedData()
    {
        return JWTEncodedHelper::encode($this->toArray());
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
    public function addCreator($profile)
    {
        return DataUserMember::__addCreatorWithUUID($this->getUuid(), $profile, $this->getSource());
    }

    /**
     * add Owner
     * @param $profile
     */
    public function addDefaultOwner()
    {
        $profile = $this->getUserProfile();
        $return = ['success' => false, 'data' => [], 'message' => 'OWNER_PROFILE_NOT_FOUND_TEXT'];
        if ($profile) {
            return DataUserMember::__addOwnerWithUUID($this->getUuid(), $profile, $this->getSource());
        }
        return $return;
    }

    /**
     * @param $profile
     */
    public function addReporter($profile)
    {
        return DataUserMember::__addReporterWithUUID($this->getUuid(), $profile, $this->getSource());
    }

    /**
     * @param $profile
     */
    public function addViewer($profile)
    {
        $data_user_member_manager = new DataUserMember();
        $result = $data_user_member_manager->addNewMember($this, $profile, DataUserMember::MEMBER_TYPE_VIEWER);
        if ($result == true) {
            $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_ADDED_SUCCESS_TEXT'];
        } else {
            $return = ['success' => false, 'data' => [], 'message' => 'VIEWER_ADDED_FAIL_TEXT'];
        }
        return $return;
    }

    /**
     * get object of owner
     */
    public function getDataOwner()
    {
        $owners = $this->getMembers([
            'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = :type:',
            'bind' => [
                'type' => DataUserMember::MEMBER_TYPE_OWNER
            ],
            'limit' => 1,
        ]);
        $item = array();
        if ($owners->count() > 0) {
            $user = $owners->getFirst();
            $item = $user->toArray();
            $item['avatar'] = $user->getAvatar();
            $item['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
            $item['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;
            return $user;
        } else {
            return null;
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
     * get object of reporter
     */
    public function getDataReporter()
    {
        $reporters = $this->getReporters([
            'conditions' => 'Reloday\Gms\Models\DataUserMember.member_type_id = :type:',
            'bind' => [
                'type' => DataUserMember::MEMBER_TYPE_REPORTER
            ],
            'limit' => 1,
        ]);
        $item = array();
        if ($reporters->count() > 0) {
            $user = $reporters->getFirst();
            $item = $user->toArray();
            $item['avatar'] = $user->getAvatar();
            $item['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
            $item['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;
            return $user;
        } else {
            return null;
        }
    }

    /**
     * @param $profile
     * @return bool
     */
    public function deleteReporter($profile)
    {
        return DataUserMember::__deleteMember($this, $profile, DataUserMember::MEMBER_TYPE_REPORTER);
    }

    /**
     * @param $profile
     * @return bool
     */
    public function deleteOwner($profile)
    {
        return DataUserMember::__deleteMember($this, $profile, DataUserMember::MEMBER_TYPE_OWNER);
    }

    /**
     * get linked activity
     * @return mixed
     */
    public function getLinkedActivity()
    {
        if ($this->getLinkType() == self::LINK_TYPE_ASSIGNMENT) {
            return $this->getAssignment();
        }

        if ($this->getLinkType() == self::LINK_TYPE_RELOCATION) {
            return $this->getRelocation();
        }

        if ($this->getLinkType() == self::LINK_TYPE_SERVICE) {
            return $this->getRelocationServiceCompany();
        }
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
     * @return string
     */
    public function getFrontendState($options = "")
    {
        return "app.tasks.page({uuid:'" . $this->getUuid() . "', ". $options ."})";
    }


    /**
     * check is belong to GMS
     * @return [type] [description]isActive
     */
    public function belongsToGms()
    {
        $company = ModuleModel::$company;
        if ($company) {
            if ($company->getId() == $this->getCompanyId()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * @return bool
     */
    public function checkMyViewPermission()
    {
        if (ModuleModel::$user_profile->isAdminOrManager() == true) {
            return true;
        }
        return $this->checkViewPermissionOfUserByUuid(ModuleModel::$user_profile->getUuid());
    }

    /**
     * @return bool
     */
    public function checkMyEditPermission()
    {
        if (ModuleModel::$user_profile->isAdminOrManager() == true || $this->getTaskType() == self::TASK_TYPE_EE_TASK) {
            return true;
        }
        return $this->checkEditPermissionOfUserByUuid(ModuleModel::$user_profile->getUuid());
    }

    /**
     * @return bool
     */
    public function checkMyDeletePermission()
    {
        if (ModuleModel::$user_profile->isAdminOrManager() == true) {
            return true;
        }
        return $this->checkDeletePermissionOfUserByUuid(ModuleModel::$user_profile->getUuid());
    }

    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkViewPermissionOfUser($user_login_id = 0)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_login_id = :user_login_id: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_login_id' => $user_login_id,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_INITIATOR,
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_VIEWER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param $user_profile_uuid
     * @return bool
     */
    public function checkViewPermissionOfUserByUuid($user_profile_uuid)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: 
            AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_INITIATOR,
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_VIEWER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkEditPermissionOfUser($user_login_id = 0)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_login_id = :user_login_id: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_login_id' => $user_login_id,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkEditPermissionOfUserByUuid($user_profile_uuid)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_VIEWER,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param int $user_login_id
     */
    public function checkDeletePermissionOfUser($user_login_id = 0)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_login_id = :user_login_id: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_login_id' => $user_login_id,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param int $user_login_id
     */
    public function checkDeletePermissionOfUserByUuid($user_profile_uuid)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkApproverPermissionOfUser($user_login_id = 0)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_login_id = :user_login_id: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_login_id' => $user_login_id,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkApproverPermissionOfUserByUuid($user_profile_uuid = 0)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }


    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkNotifyPermissionOfUser($user_login_id = 0)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_login_id = :user_login_id: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_login_id' => $user_login_id,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE,
                    DataUserMember::MEMBER_TYPE_APPROVER,
                    DataUserMember::MEMBER_TYPE_VIEWER,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }

    /**
     * @param int $user_login_id
     * @return bool
     */
    public function checkNotifyPermissionOfUserByUuid($user_profile_uuid)
    {
        $count = DataUserMember::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid: AND member_type_id IN ({member_type_ids:array}) AND object_uuid = :object_uuid:',
            'bind' => [
                'user_profile_uuid' => $user_profile_uuid,
                'member_type_ids' => [
                    DataUserMember::MEMBER_TYPE_OWNER,
                    DataUserMember::MEMBER_TYPE_ASSIGNEE,
                    DataUserMember::MEMBER_TYPE_APPROVER,
                    DataUserMember::MEMBER_TYPE_VIEWER,
                    DataUserMember::MEMBER_TYPE_REPORTER,
                ],
                'object_uuid' => $this->getUuid()
            ]
        ]);
        if ($count > 0) return true;
        return false;
    }


    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public static function __quick_create($custom = [])
    {
        $model = new self();
        $data = [];
        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            $uuid = isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid());
            if ($uuid == '') {
                $random = new Random;
                $uuid = $random->uuid();
            }
            if ($uuid != '') {
                $model->setUuid($uuid);
            }
        }

        /** @var  $company_id */
        $company_id = isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId());
        $link_type = isset($custom['link_type']) ? $custom['link_type'] : (isset($data['link_type']) ? $data['link_type'] : $model->getLinkType());
        $object_uuid = isset($custom['object_uuid']) ? $custom['object_uuid'] : (isset($data['object_uuid']) ? $data['object_uuid'] : $model->getObjectUuid());

        if ($company_id == 0 || is_null($company_id)) {
            $result = [
                'success' => false,
                'message' => 'COMPANY_REQUIRED_TEXT',
                'raw' => $custom,
                'infos' => $company_id
            ];
            return $result;
        }

        if (is_null($link_type)) {
            $result = [
                'success' => false,
                'message' => 'LINK_TYPE_REQUIRED_TEXT',
                'raw' => $custom,
                'infos' => $link_type,
            ];
            return $result;
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
                && $field_name != "password"
                && $field_name != "started_at"
                && $field_name != "due_at"
                && $field_name != "ended_at"
                && $field_name != "reminder_date"
            ) {

                if (!isset($fields_numeric[$field_name])) {
                    $model->set(
                        $field_name,
                        isset($custom[$field_name]) ? $custom[$field_name] :
                            (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name))
                    );
                } else {
                    $field_name_value = isset($custom[$field_name]) ? $custom[$field_name] :
                        (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name));
                    if (is_numeric($field_name_value) && $field_name_value != '' && !is_null($field_name_value) && !empty($field_name_value)) {
                        $model->set($field_name, $field_name_value);
                    }
                }
            }
        }


        /****** YOUR CODE ***/
        $started_at_time = isset($data['started_at_time']) ? $data['started_at_time'] : (isset($custom['started_at_time']) ? $custom['started_at_time'] : '');
        if ($started_at_time != '') {
            $started_at = date('Y-m-d H:i:s', strtotime($started_at_time));
            $model->setStartedAt($started_at);
        } else {
            $started_at = isset($data['started_at']) ? $data['started_at'] : (isset($custom['started_at']) ? $custom['started_at'] : '');
            if ($started_at != '' && strtotime($started_at) != false) {
                $started_at = date('Y-m-d H:i:s', strtotime($started_at));
                $model->setStartedAt($started_at);
            } elseif ($model->getStartedAt() == '') {
                $model->setStartedAt(date('Y-m-d H:i:s'));
            }
        }
        $due_at_time = isset($data['due_at_time']) ? $data['due_at_time'] : (isset($custom['due_at_time']) ? $custom['due_at_time'] : '');
        if ($due_at_time != '') {
            $due_at = date('Y-m-d H:i:s', strtotime($due_at_time));
            $model->setDueAt($due_at);
        } else {
            $due_at = isset($data['due_at']) ? $data['due_at'] : (isset($custom['due_at']) ? $custom['due_at'] : '');
            if ($due_at != '' && strtotime($due_at) != false) {
                $due_at = date('Y-m-d', strtotime($due_at));
                $model->setDueAt($due_at);
            } elseif ($model->getDueAt() == '') {
                $model->setDueAt(date('Y-m-d'));
            }
        }

        $ended_at_time = isset($data['ended_at_time']) ? $data['ended_at_time'] : (isset($custom['ended_at_time']) ? $custom['ended_at_time'] : '');
        if ($ended_at_time != '') {
            $ended_at = date('Y-m-d H:i:s', strtotime($ended_at_time));
            $model->setEndedAt($ended_at);
        } else {
            $ended_at = isset($data['ended_at']) ? $data['ended_at'] : (isset($custom['ended_at']) ? $custom['ended_at'] : '');
            if ($ended_at != '' && strtotime($ended_at) != false) {
                $ended_at = date('Y-m-d', strtotime($ended_at));
                $model->setEndedAt($ended_at);
            } elseif ($model->getEndedAt() == '') {
                $model->setEndedAt(date('Y-m-d'));
            }
        }
        $reminder_date_converted = isset($data['reminder_date_converted']) ? $data['reminder_date_converted'] : (isset($custom['reminder_date_converted']) ? $custom['reminder_date_converted'] : '');
        if ($reminder_date_converted != '') {
            $reminder_date = date('Y-m-d H:i:s', strtotime($reminder_date_converted));
            $model->setReminderDate($reminder_date);
        } else {
            $reminder_date = isset($data['reminder_date']) ? $data['reminder_date'] : (isset($custom['reminder_date']) ? $custom['reminder_date'] : '');
            if ($reminder_date != '' && strtotime($reminder_date) != false) {
                $reminder_date = date('Y-m-d', strtotime($reminder_date));
                $model->setReminderDate($reminder_date);
            } elseif ($model->getReminderDate() == '') {
                $model->setReminderDate(date('Y-m-d'));
            }
        }

        $progress = isset($data['progress']) ? $data['progress'] : (isset($custom['progress'])
            ? $custom['progress'] : $model->getProgress());
        if ($progress == null) {
            $progress = self::STATUS_NOT_STARTED;
        }

        //** set date with process */
        if ($model->getProgress() !== self::STATUS_COMPLETED) {
            if ($progress != self::STATUS_COMPLETED) {
                $model->setEndedAt(null);
            }
        } else {
            if ($progress == self::STATUS_COMPLETED) {
                //set null end_et
                $model->setEndedAt(date('Y-m-d'));
            }
        }


        /** @var process $status */
        $status = isset($data['status']) ? $data['status'] : (isset($custom['status']) ? $custom['status'] : $model->getStatus());
        if ($status == null) {
            $status = self::STATUS_DRAFT;
        }


        /** started_at */
        if ($company_id > 0) {
            $model->setCompanyId($company_id);
        }
        if ($link_type >= 0) {
            $model->setLinkType($link_type);
        }

        /** set number of task */
        $number = isset($data['number']) ? $data['number'] : (isset($custom['number']) ? $custom['number'] : $model->getNumber());
        if ($number == '') {
            $taskType = $model->getTaskType() ? $model->getTaskType() : self::TASK_TYPE_INTERNAL_TASK;
            $number = self::__createNumberTask($company_id, $link_type, $taskType);
            $model->setNumber($number);
        }
        /****** END YOUR CODE **/
        try {
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'TASK_CREATE_FAIL_TEXT',
                    'raw' => $msg,
                    'infos' => $custom
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'TASK_CREATE_FAIL_TEXT',
                'raw' => $e->getMessage(),
                'infos' => $data
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'TASK_CREATE_FAIL_TEXT',
                'raw' => $e->getMessage(),
                'infos' => $custom
            ];
            return $result;
        }
    }

    /**
     *
     */
    public function getBreadCrumbs()
    {
        $breadcrumb = [];

        if ($this->getLinkType() == self::LINK_TYPE_ASSIGNMENT) {
            if ($this->getAssignment()) {
                $breadcrumb[] = [
                    'state' => "app.assignment.dashboard({uuid:'" . $this->getObjectUuid() . "'})",
                    'name' => $this->getAssignment()->getReference()
                ];
            }
        };

        if ($this->getLinkType() == self::LINK_TYPE_RELOCATION) {
            if ($this->getRelocation()) {

                if ($this->getRelocation()->getAssignment()) {
                    $breadcrumb[] = [
                        'state' => "app.assignment.dashboard({uuid:'" . $this->getRelocation()->getAssignment()->getUuid() . "'})",
                        'name' => $this->getRelocation()->getAssignment()->getNumber()
                    ];
                }

                $breadcrumb[] = [
                    'state' => "app.relocation.dashboard({uuid:'" . $this->getObjectUuid() . "'})",
                    'name' => $this->getRelocation()->getIdentify()
                ];
            }
        }


        if ($this->getLinkType() == self::LINK_TYPE_SERVICE) {
            if ($this->getRelocationServiceCompany()) {

                if ($this->getRelocationServiceCompany()->getRelocation()->getAssignment()) {
                    $breadcrumb[] = [
                        'state' => "app.assignment.dashboard({uuid:'" . $this->getRelocationServiceCompany()->getRelocation()->getAssignment()->getUuid() . "'})",
                        'name' => $this->getRelocationServiceCompany()->getRelocation()->getAssignment()->getNumber()
                    ];
                }

                if ($this->getRelocationServiceCompany()->getRelocation()) {
                    $breadcrumb[] = [
                        'state' => "app.relocation.dashboard({uuid:'" . $this->getRelocationServiceCompany()->getRelocation()->getUuid() . "'})",
                        'name' => $this->getRelocationServiceCompany()->getRelocation()->getIdentify()
                    ];
                }

                $breadcrumb[] = [
                    'state' => "app.relocation.service-detail({uuid:'" . $this->getObjectUuid() . "'})",
                    'name' => $this->getRelocationServiceCompany()->getServiceCompany()->getName()
                ];
            }
        }

        if ($this->getLinkType() == self::LINK_TYPE_TASK) {
//            $breadcrumb = $this->getBreadCrumbs();
            $breadcrumb[] = [
                'state' => "app.tasks.list",
                'name' => 'ALL_TASKS_TEXT',
            ];
//            $breadcrumb[] = [
//                'state' => "app.tasks.page({uuid:'" . $this->getUuid() . "'})",
//                'name' => $this->getNumber(),
//            ];
        }

        return $breadcrumb;
    }

    /**
     * get related item
     * @return array
     */
    public function getRelatedItem()
    {
        $related_action = [];

        if ($this->getLinkType() == self::LINK_TYPE_ASSIGNMENT) {
            if ($this->getAssignment()) {
                $related_action = [
                    'state' => "app.assignment.dashboard({uuid:'" . $this->getObjectUuid() . "'})",
                    'name' => $this->getAssignment()->getReference()
                ];
            }
        };

        if ($this->getLinkType() == self::LINK_TYPE_RELOCATION) {
            if ($this->getRelocation()) {
                $related_action = [
                    'state' => "app.relocation.dashboard({uuid:'" . $this->getObjectUuid() . "'})",
                    'name' => $this->getRelocation()->getIdentify()
                ];
            }
        }


        if ($this->getLinkType() == self::LINK_TYPE_SERVICE) {
            if ($this->getRelocationServiceCompany()) {
                $related_action = [
                    'state' => "app.relocation.service-detail({uuid:'" . $this->getObjectUuid() . "'})",
                    'name' => $this->getRelocationServiceCompany()->getServiceCompany()->getName()
                ];
            }
        }

        if ($this->getLinkType() == self::LINK_TYPE_TASK) {
            $related_action = [
                'state' => "app.tasks.page({uuid:'" . $this->getUuid() . "'})",
                'name' => $this->getNumber(),
            ];
        }

        return $related_action;
    }

    /**
     *
     */
    public function tempo__createReporterIfCreateMode()
    {

    }

    /**
     * @return mixed
     */
    public static function __getDi()
    {
        return \Phalcon\DI::getDefault();
    }

    /**
     * @return mixed
     */
    public function getForceRelocation()
    {
        $relocation = $this->getRelocation();
        if (!$relocation) {
            $relocation_service_company = $this->getRelocationServiceCompany();
            if ($relocation_service_company) {
                $relocation = $relocation_service_company->getRelocation();
                if ($relocation) {
                    return $relocation;
                }
            }
        } else {
            return $relocation;
        }
    }

    /**
     * Get label for a given status
     * @param $status
     * @return mixed|string|null
     */
    public static function __getStatusLabel($status)
    {
        $statusName = null;
        $statusText = self::$progress_status_text[$status];
        if ($statusText) {
            $lang = ModuleModel::$language ? ModuleModel::$language : 'en';
            $statusName = ConstantHelper::__translate($statusText, $lang) ?
                ConstantHelper::__translate($statusText, $lang) : $statusText;
        }
        return $statusName;
    }

    /**
     *
     */
    public function updateServiceProgress()
    {
        $resultSaveEntity = $this->setEntityProgress();
        /*** check entity progress **/
        if ($resultSaveEntity['success'] && $this->getObjectUuid()) {
            PushHelper::__sendPushReload($this->getObjectUuid());
            if (Helpers::__isValidUuid($this->getObjectUuid())) {

                $lastEntityProgressInserted = $resultSaveEntity['data'];
                $serviceRelocationCompany = RelocationServiceCompany::__findFirstByUuidCache($this->getObjectUuid());

                if ($serviceRelocationCompany) {
                    $oldProgressStatus = $serviceRelocationCompany->getProgress();
                    $lastEntityProgressCalculated = $serviceRelocationCompany->getLastEntityProgressItem();
                    if ($lastEntityProgressCalculated && $lastEntityProgressCalculated->getId() != $lastEntityProgressInserted->getId()) {

                        $serviceRelocationCompany->setProgress($lastEntityProgressInserted->getStatus());
                        $serviceRelocationCompany->setProgressValue(intval($lastEntityProgressInserted->getValue()));
                        $resultUpdata = $serviceRelocationCompany->__quickUpdate();

                    } else {

                        $serviceRelocationCompany->setProgress($serviceRelocationCompany->getEntityProgressStatus());
                        $serviceRelocationCompany->setProgressValue(intval($serviceRelocationCompany->getEntityProgressValue()));
                        $resultUpdata = $serviceRelocationCompany->__quickUpdate();
                    }

                    if ($resultUpdata['success'] == false) {
                        return $resultUpdata;
                    }

                    /* if change progress status **/
                    if ($oldProgressStatus != $serviceRelocationCompany->getProgress()) {
                        $apiResults[] = NotificationServiceHelper::__addNotification(
                            $serviceRelocationCompany,
                            HistoryModel::TYPE_SERVICE,
                            HistoryModel::HISTORY_CHANGE_STATUS
                        );
                    }
                }
            }
        }

        return $resultSaveEntity;
    }

    public function getMainAssignment()
    {
        return Assignment::findFirstById($this->getAssignmentId());
    }

    private static function getRelocationServiceByObjectUuid($object_uuid = ''){
        if($object_uuid != ''){
            $relocationService = RelocationServiceCompany::findFirst([
                'conditions' => 'uuid = :object_uuid:',
                'bind' => [
                    'object_uuid' => $object_uuid,
                ]
            ]);
            if($relocationService){
                return $relocationService;
            }

        }

        return false;
    }

    /**
     * @param $options
     * @return array
     */
    public static function __getRecentTasks($options = []){
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
              (select t.uuid, t.id  
                from task as t
                where t.uuid = h.object_uuid 
                GROUP BY h.object_uuid)  
            order by h.created_at DESC limit $limit";

        $data = $db->query($sql);
        $data->setFetchMode(\Phalcon\Db::FETCH_ASSOC);
        $results = $data->fetchAll();
        $array = [];
        foreach ($results as $history) {
            $task = Task::findFirstByUuid($history['object_uuid']);
            $item = $task->toArray();

            $employee = $task->getEmployee();

            $item['due_at_time'] = strtotime($item["due_at"]);
            $item['id'] = intval($item["id"]);
            $item['sequence'] = intval($item["sequence"]);
            $item['status'] = intval($item["status"]);
            $item['is_archive'] = intval($item["is_archive"]);
            $item['progress'] = intval($item["progress"]);
            $item['link_type'] = intval($item["link_type"]);
            $item['employee_id'] = intval($item["employee_id"]);
            $item['relocation_id'] = intval($item["relocation_id"]);
            $item['assignment_id'] = intval($item["assignment_id"]);
            $item['relocation_service_company_id'] = intval($item["relocation_service_company_id"]);
            $item['parent_task_id'] = intval($item["parent_task_id"]);
            $item['task_template_company_id'] = intval($item["task_template_company_id"]);
            $item['owner_id'] = intval($item["owner_id"]);
            $item['company_id'] = intval($item["company_id"]);
            $item['is_priority'] = intval($item["is_priority"]);
            $item['is_flag'] = intval($item["is_flag"]);
            $item['is_milestone'] = intval($item["is_milestone"]);
            $item['is_final_review'] = intval($item["is_final_review"]);
            $item['task_type'] = intval($item["task_type"]);
            $item['has_file'] = intval($item["has_file"]);
            $item['employee_name'] = $employee->getFullname();
            $data_owner = $task->getDataOwner();
            $item['owner_name'] = $data_owner ? $data_owner->getFullname() : '';
            $item['owner_uuid'] = $data_owner ? $data_owner->getUuid() : '';
            $item['related'] = $task->getRelatedItem();

            $array[] = $item;
        }

        return [
            'success' => true,
            'data' => $array,
        ];

    }
}
