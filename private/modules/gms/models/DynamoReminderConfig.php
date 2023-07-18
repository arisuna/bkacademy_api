<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 2/12/20
 * Time: 2:32 PM
 */

namespace Reloday\Gms\Models;

use Aws\DynamoDb\Exception\DynamoDbException;
use Reloday\Application\DynamoDb\ORM\DynamoReminderConfigExt;
use Reloday\Application\Lib\Helpers;
use Phalcon\Di;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\EventValue;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ServiceEvent;
use Reloday\Gms\Models\Task;

class DynamoReminderConfig extends DynamoReminderConfigExt
{
    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 20;
    const MIN_PER_PAGE = 5;

    public function getServiceEvent()
    {
        if ($this->getServiceEventId() > 0) {
            $serviceEvent = ServiceEvent::findFirstById($this->getServiceEventId());
            return $serviceEvent;
        }
        return false;
    }

    public function getRelocationServiceCompany()
    {
        if ($this->getRelocationServiceCompanyId() > 0) {
            $serviceEvent = RelocationServiceCompany::findFirstById($this->getRelocationServiceCompanyId());
            return $serviceEvent;
        }
        return false;
    }

    public static function findFirstByUuid($reminderUuid)
    {
        try {
            $reminderConfig = RelodayDynamoORM::factory('\Reloday\Gms\Models\DynamoReminderConfig')
                ->findOne($reminderUuid);
            return $reminderConfig;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getEventValue()
    {
        if ($this->getEventValueUuid() != "") {
            $eventValue = EventValue::findFirstByUuid($this->getEventValueUuid());
            return $eventValue;
        }
        return false;
    }

    public function getEmployee()
    {
        if ($this->getEmployeeId() > 0) {
            $eventValue = Employee::findFirstById($this->getEmployeeId());
            return $eventValue;
        }
        return false;
    }

    public function getAssignment()
    {
        if ($this->getAssignmentId() > 0) {
            $eventValue = Assignment::findFirstById($this->getAssignmentId());
            return $eventValue;
        }
        return false;
    }

    public function getTask()
    {
        return Task::findFirstByUuid($this->getObjectUuid());
    }


    /**
     * @return mixed
     */
    public function getParsedData()
    {
        $item = $this->toArray();
        $userProfileUuid = ModuleModel::$user_profile->getUuid();
        $reminderUserDeclined = RelodayDynamoORM::factory('\Reloday\Gms\Models\DynamoReminderUserDeclined')
            ->index('ReminderConfigUuid')
            ->where('reminder_config_uuid', $this->getUuid())
            ->filter('user_profile_uuid', $userProfileUuid)
            ->findFirst();
        $item["is_declined"] = false;
        if ($reminderUserDeclined instanceof DynamoReminderUserDeclined) {
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

    public static function countByTime($startTimeDay, $endTimeDay){
        try {
            $dynamoReminder = RelodayDynamoORM::factory('\Reloday\Gms\Models\DynamoReminderConfig')
                ->index('CompanyIdStartAt')
                ->whereWithExpression('company_id = :company_id AND start_at BETWEEN :d1 AND :d2')
                ->filterWithExpression("owner_profile_uuid = :owner_profile_uuid");

            $bind[':company_id']["N"] = (string)intval(ModuleModel::$company->getId());
            $bind[':d1']["N"] = (string)$startTimeDay;
            $bind[':d2']["N"] = (string)$endTimeDay;
            $bind[':owner_profile_uuid']["S"] = ModuleModel::$user_profile->getUuid();

            $data = $dynamoReminder->setExpressionAttributeValues($bind)
                ->findMany(['ScanIndexForward' => false]);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            $return = [
                'bind' => $bind,
                'success' => false,
                'exception' => $e,
                'count' => 0,
            ];
            goto end_of_function;
        }

        $return = [
            'success' => true,
            'start' => $startTimeDay,
            'end' => $endTimeDay,
            'count' => $dynamoReminder->getCount(),
        ];
        end_of_function:
        return $return;
    }
}
