<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CloudWatchHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class ReminderConfig extends \Reloday\Application\Models\ReminderConfigExt
{
    const LIMIT_PER_PAGE = 10;

    /**
     *
     */
    public function initialize()
    {

        parent::initialize();

        $this->belongsTo('service_event_id', 'Reloday\Gms\Models\ServiceEvent', 'id', [
            'alias' => 'ServiceEvent',
        ]);

        $this->belongsTo('event_value_uuid', 'Reloday\Gms\Models\EventValue', 'uuid', [
            'alias' => 'EventValue',
        ]);

        $this->belongsTo('id', 'Reloday\Gms\Models\ReminderItem', 'reminder_config_id', [
            'alias' => 'ReminderItem',
        ]);

        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', [
            'alias' => 'RelocationServiceCompany',
        ]);
    }

    /**
     *
     */
    public function sendRequestCreateReminderItem()
    {
        //TODO send request create reminder items
        /*
        $relodayQueue = new RelodayQueue(getenv('QUEUE_CREATE_REMINDER'));
        $dataArray = [
            'action' => "createReminderFromTask",
            'taskUuid' => $this->getUuid(),
            'due_at' => $this->getDueAt(),
        ];
        $resultCheck = $relodayQueue->addQueue($dataArray);
        return $resultCheck;
        */
    }

    /**
     *
     */
    public function removeAllItemsInDynamoDb()
    {
        //TODO remove all items of reminders if exists
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        return ModuleModel::$user_profile->getCompanyId() == $this->getCompanyId();
    }

    /**
     * @param $params
     */
    public static function __findWithFilters($options = [], $orders = [])
    {
        if (isset($options['mode']) && is_string($options['mode'])) {
            $mode = $options['mode'];
        } else {
            $mode = "large";
        }
        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ReminderConfig', 'ReminderConfig');
        $queryBuilder->distinct(true);

        $queryBuilder->innerjoin('\Reloday\Gms\Models\Task', 'Task.uuid = ReminderConfig.object_uuid', 'Task');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Task.uuid = DataUserMember.object_uuid AND DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER, 'DataUserMember');

        //$queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = ReminderConfig.company_id', 'Company');
        //$queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = ReminderConfig.relocation_id', 'Relocation');

        $queryBuilder->where("ReminderConfig.company_id = :company_id:", [
            'company_id' => ModuleModel::$company->getId(),
        ]);
        $queryBuilder->andWhere("Task.status <> :status_archived:", [
            'status_archived' => Task::STATUS_DELETED
        ]);
        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ReminderConfig.status ASC', 'ReminderConfig.updated_at DESC']);
                } else {
                    $queryBuilder->orderBy(['ReminderConfig.updated_at DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ReminderConfig.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['ReminderConfig.created_at DESC']);
                }
            }

            if ($order['field'] == "start_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ReminderConfig.start_at ASC']);
                } else {
                    $queryBuilder->orderBy(['ReminderConfig.start_at DESC']);
                }
            }
        }

        if (isset($options['active']) && is_bool($options['active']) && $options['active'] == true) {
            $queryBuilder->andwhere("ReminderConfig.start_at IS NULL OR ReminderConfig.end_at >= :current_date:", [
                'current_date' => time(),
            ]);
        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("ReminderConfig.company_id = :company_id:", [
                'company_id' => $options['company_id'],
            ]);
        }

        if (isset($options['start_at']) && is_numeric($options['start_at']) && $options['start_at'] > 0 && isset($options['end_at']) && is_numeric($options['end_at']) && $options['end_at'] > 0) {
            $queryBuilder->andwhere("(ReminderConfig.start_at BETWEEN :start_at: AND :end_at:) OR (ReminderConfig.reminder_at BETWEEN :start_at: AND :end_at: AND ReminderConfig.before_after = :before_after_exact_time:)", [
                'start_at' => $options['start_at'],
                'end_at' => $options['end_at'],
                'before_after_exact_time' => self::EXACTLY
            ]);
        }


        if (isset($options['object_uuid']) && is_string($options['object_uuid']) && $options['object_uuid'] != '') {
            $queryBuilder->andwhere("ReminderConfig.object_uuid = :object_uuid:", [
                'object_uuid' => $options['object_uuid'],
            ]);
        }

        if (isset($options['relocation_id']) && is_numeric($options['relocation_id']) && $options['relocation_id'] > 0) {
            $queryBuilder->andwhere("ReminderConfig.relocation_id = :relocation_id:", [
                'relocation_id' => $options['relocation_id'],
            ]);
        }

        if (isset($options['owner_profile_uuid']) && is_string($options['owner_profile_uuid']) && $options['owner_profile_uuid'] != '') {
            $queryBuilder->andwhere("DataUserMember.user_profile_uuid = :owner_profile_uuid:", [
                'owner_profile_uuid' => $options['owner_profile_uuid'],
            ]);
        }


        if (isset($options['relocation_service_company_id']) && is_numeric($options['relocation_service_company_id']) && $options['relocation_service_company_id'] > 0) {
            $queryBuilder->andwhere("ReminderConfig.relocation_service_company_id = :relocation_service_company_id:", [
                'relocation_service_company_id' => $options['relocation_service_company_id'],
            ]);
        }

        $queryBuilder->andWhere('ReminderConfig.status != :status_archived:', [
            'status_archived' => self::STATUS_ARCHIVED
        ]);

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }


        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $items = [];

            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $itemArray = $item->getParsedData();
                    $itemArray['reminder_at'] = intval($item->getReminderAt());
                    $itemArray['event_name'] = $item->getServiceEvent() ? $item->getServiceEvent()->getLabel() : '';

                    $task = $item->getTask();
                    if ($task) {
                        $employee = $task->getEmployee();
                    }
                    $itemArray['task_name'] = $task ? $task->getName() : null;
                    $itemArray['task_progress'] = $task ? $task->getProgress() : null;
                    $itemArray['task_is_flag'] = $task ? $task->getIsFlag() : null;
                    $itemArray['employee_uuid'] = $employee ? $employee->getUuid() : null;
                    $itemArray['employee_name'] = $employee ? $employee->getFullname() : null;
                    $items[] = $itemArray;
                }
            }

            return [
                'success' => true,
//                '$sql' => $queryBuilder->getQuery()->getSql(),
                'page' => $page,
                'data' => $items,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
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
    public function getParsedData()
    {
        $item = $this->toArray();
        $userProfileUuid = ModuleModel::$user_profile->getUuid();
        $reminderUserDeclined = ReminderUserDeclined::findFirst([
            "conditions" => "user_profile_uuid = :user_profile_uuid: and reminder_config_id = :reminder_config_id:",
            "bind" => [
                "user_profile_uuid" => $userProfileUuid,
                'reminder_config_id' => $this->getId(),
            ]
        ]);
        $item["is_declined"] = false;
        if ($reminderUserDeclined instanceof ReminderUserDeclined) {
            $item["is_declined"] = true;
        }
        $item['event_name'] = $this->getServiceEvent() ? $this->getServiceEvent()->getLabel() : '';
        $task = Task::findFirstByUuid($this->getObjectUuid());
        $item['isActive'] = false;
        if ($task instanceof Task && $task->isActive()) {
            $item['isActive'] = $this->isActive();
        }
        $item['isExpired'] = $this->isExpired();

        return $item;
    }

    /**
     * @param $startTimeDay
     * @param $endTimeDay
     * @return array
     */
    public static function __countByTime(int $startTimeDay, int $endTimeDay, String $userProfileUuid)
    {
        $count = 0;
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\ReminderConfig', 'ReminderConfig');
            $queryBuilder->distinct(true);
            $queryBuilder->columns([
                "DataUserMember.user_profile_uuid",
                "count" => "COUNT(*)"
            ]);
            //$queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = ReminderConfig.company_id', 'Company');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Task', 'Task.uuid = ReminderConfig.object_uuid', 'Task');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Task.uuid = DataUserMember.object_uuid', 'DataUserMember');

            $queryBuilder->where('ReminderConfig.company_id = :company_id:');
            $queryBuilder->andWhere('ReminderConfig.status != :status_archived:');
            $queryBuilder->andWhere('(ReminderConfig.start_at between :start_at: AND :end_at:) OR (ReminderConfig.reminder_at between :start_at: AND :end_at: AND ReminderConfig.before_after = :before_after_exact_time: )');
            $queryBuilder->andWhere('DataUserMember.user_profile_uuid = :user_profile_uuid:');
            $queryBuilder->andWhere('DataUserMember.member_type_id = :member_type_id:');
            $bindArray = [
                'company_id' => ModuleModel::$company->getId(),
                'status_archived' => self::STATUS_ARCHIVED,
                'start_at' => $startTimeDay,
                'end_at' => $endTimeDay,
                'user_profile_uuid' => $userProfileUuid,
                'member_type_id' => DataUserMember::MEMBER_TYPE_OWNER,
                'before_after_exact_time' => self::EXACTLY
            ];
//
//            var_dump($bindArray);
           $sql = $queryBuilder->getQuery()->getSql();
//            var_dump($sql);
//            die();
            $result = $queryBuilder->getQuery()->execute($bindArray)->getFirst()->toArray();
            $count = $result['count'];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            $return = [
                'success' => false,
                'exception' => $e,
                'count' => 0,
            ];
            goto end_of_function;
        }

        $return = [
            'success' => true,
            //'$sql' => $sql,
            //'$bind' => $bindArray,
            'start' => $startTimeDay,
            'end' => $endTimeDay,
            'count' => $count,
        ];
        end_of_function:
        return $return;
    }

}
