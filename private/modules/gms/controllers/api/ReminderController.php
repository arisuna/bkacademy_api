<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayDynamoORM;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\Event;
use Reloday\Gms\Models\EventValue;
use Reloday\Gms\Models\ReminderConfig;
use Reloday\Gms\Models\ReminderUserDeclined;
use Reloday\Gms\Models\ServiceEventValue;
use Reloday\Gms\Models\ServiceFieldValue;
use \Reloday\Gms\Models\Task as Task;
use \Reloday\Gms\Models\UserProfile as UserProfile;
use \Reloday\Gms\Models\DataUserMember as DataUserMember;
use \Reloday\Gms\Models\ModuleModel as ModuleModel;
use \Reloday\Gms\Models\HistoryOld as History;
use \Reloday\Gms\Models\MediaAttachment;
use \Reloday\Application\Models\RelodayReminderItem as RelodayReminderItem;
use Reloday\Gms\Module;
use \Firebase\JWT\JWT;
use Aws;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\Exception\DynamoDbException;
use Phalcon\Security\Random;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ReminderController extends BaseController
{
    /**
     *
     */
    public function initialize()
    {

    }

    /**
     * @Route("/reminder", paths={module="gms"}, methods={"GET"}, name="gms-reminder-index")
     */
    public function indexAction()
    {

    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getRemindersAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $params = [];
        $params['page'] = Helpers::__getRequestValue('page');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $params['company_id'] = ModuleModel::$company->getId();
            $params['object_uuid'] = $uuid;
            $params['limit'] = 50;
            $return = ReminderConfig::__findWithFilters($params);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     *
     */
    public function stopAction($reminderUuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
            'reminderUuid' => $reminderUuid
        ];

        if ($reminderUuid != '' && Helpers::__isValidUuid($reminderUuid)) {
            $reminderConfig = ReminderConfig::findFirstByUuidCache($reminderUuid);
            if ($reminderConfig && $reminderConfig->belongsToGms()) {
                $userProfileUuid = ModuleModel::$user_profile->getUuid();
                $reminderUserDeclined = new ReminderUserDeclined;
                $reminderUserDeclined->setData([
                    'user_profile_uuid' => $userProfileUuid,
                    'reminder_config_id' => $reminderConfig->getId(),
                ]);
                $return = $reminderUserDeclined->__quickCreate();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $task_uuid
     */
    public function createReminderConfigAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $task_uuid = Helpers::__getRequestValue('object_uuid');

        if ($task_uuid && Helpers::__isValidUuid($task_uuid)) {
            $task = Task::findFirstByUuidCache($task_uuid);
            if ($task && $task->belongsToGms() && $task->isActive()) {
                ModuleModel::$task = $task;
                //TODO check start date vs enddate and vs number of reminderItems;
                $reminderConfig = new ReminderConfig();

                $db = $reminderConfig->getWriteConnection();
                $db->begin();

                $reminderAt = Helpers::__getRequestValue('reminder_at');
                $reminderAtTime = Helpers::__getRequestValue('reminder_at_time');

                if (Helpers::__isTimeSecond($reminderAtTime)) {
                    $reminderAtTime = Helpers::__convertToSecond($reminderAtTime);
                } else if (Helpers::__isDate($reminderAt)) {
                    $reminderAtTime = Helpers::__convertDateToSecond($reminderAt);
                } else {
                    $reminderAtTime = Helpers::__convertToSecond($reminderAt);
                }

                $params = [
                    'relocation_id' => $task->getRelocation() ? $task->getRelocation()->getId() : ($task->getRelocationServiceCompany() ? $task->getRelocationServiceCompany()->getRelocationId() : null),
                    'relocation_service_company_id' => ($task->getRelocationServiceCompany() ? $task->getRelocationServiceCompany()->getId() : null),
                    'number' => $task->getNumber(),
                    'object_uuid' => $task_uuid,
                    'reminder_at' => $reminderAtTime,
                    'recurrence_number' => Helpers::__getRequestValue('recurrence_number'),
                    'type' => Helpers::__getRequestValue('type'),
                    'reminder_time' => Helpers::__getRequestValue('reminder_time'),
                    'reminder_time_unit' => Helpers::__getRequestValue('reminder_time_unit'),
                    'before_after' => Helpers::__getRequestValue('before_after'),
                    'recurrence_time' => Helpers::__getRequestValue('recurrence_time'),
                    'recurrence_time_unit' => Helpers::__getRequestValue('recurrence_time_unit'),
                    'service_event_id' => Helpers::__getRequestValue('service_event_id'),
                    'reminder_date' => Helpers::__getRequestValue('reminder_date'),
                    'company_id' => ModuleModel::$company->getId(),
                ];


                $reminderConfig->setData($params);

                // Check reminder base on event relocation or assignment
                $event = Event::findFirstById(Helpers::__getRequestValue('event_id'));
                if ($event) {
                    if ($event->getObjectSource() == Event::SOURCE_RELOCATION && $task->getLinkType() == Task::LINK_TYPE_RELOCATION) {
                        $object = $task->getRelocation();
                    }else if ($event->getObjectSource() == Event::SOURCE_ASSIGNMENT && $task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT) {
                        $object = $task->getAssignment();
                    } else {
                        $this->db->rollback();
                        $return = [
                            "success" => false,
                            "detail" => "event of object do not exist",
                            "message" => 'EVENT_OBJECT_IS_REQUIRED_TEXT',
                            "event" => $event,
                            "task" => $task
                        ];
                        goto end_of_function;
                    }

                    $eventValue = EventValue::__findByOjbectAndEvent($object->getId(), $event->getId());

                    if ($eventValue) {
                        $eventValue->setValue($event->getFieldType() == Event::FIELD_TYPE_DATETIME ? strtotime($object->get($event->getFieldName())) : (double)$object->get($event->getFieldName()));
                        $return = $eventValue->__quickUpdate();
                        if (!$return["success"]) {
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }

                    if (!$eventValue) {
                        $eventValue = new EventValue();
                        $eventValue->setData();
                        $eventValue->setObjectId($object->getId());
                        $eventValue->setEventId($event->getId());
                        $eventValue->setValue($event->getFieldType() == Event::FIELD_TYPE_DATETIME ? strtotime($object->get($event->getFieldName())) : (double)$object->get($event->getFieldName()));
                        $return = $eventValue->__quickCreate();
                        if (!$return["success"]) {
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }

                    $reminderConfig->setEventValueUuid($eventValue->getUuid());
                    $reminderConfig->setReminderAt($eventValue->getValue());
                }

                if ($reminderConfig->checkReminderApplyActive() == true) {
                    $reminderConfig->setStartAt($reminderConfig->getReminderStartAtTime());
                    $reminderConfig->setEndAt($reminderConfig->getReminderEndAtTime());
                }

                $reminderConfig->setBeforeAfter((int)Helpers::__getRequestValue('before_after'));


                $return = $reminderConfig->__quickSave();

                if ($return['success'] == true) {
                    //checkReminderAt for Event
                    //createReminderItem when Event Exist
                    if ($reminderConfig->getServiceEventId() > 0 && $task->getRelocationServiceCompanyId() > 0) {
                        $reminderEvent = ServiceEventValue::__findByServiceAndEvent($task->getRelocationServiceCompanyId(), $reminderConfig->getServiceEventId());
                        if ($reminderEvent && $reminderEvent->getValue() > 0) {
                            $reminderConfig->setReminderAt($reminderEvent->getValue());

                            if ($reminderConfig->checkReminderApplyActive() == true) {
                                $reminderConfig->setStartAt($reminderConfig->getReminderStartAtTime());
                                $reminderConfig->setEndAt($reminderConfig->getReminderEndAtTime());
                            }
                            $returnUpdate = $reminderConfig->__quickSave();

                            if ($returnUpdate['success'] == false) {
                                $db->rollback();
                                if (is_array($returnUpdate['detail'])) {
                                    $return['message'] = reset($returnUpdate['detail']);
                                } else {
                                    $return['message'] = 'SAVE_REMINDER_FAIL_TEXT';
                                }
                                goto end_of_function;
                            }
                        }
                    }


                    $db->commit();
                    $return['data'] = $reminderConfig;
                    $return['message'] = 'SAVE_REMINDER_SUCCESS_TEXT';
                } else {
                    $db->rollback();
                    if (isset($return['detail']) && is_array($return['detail'])) {
                        $return['message'] = reset($return['detail']);
                    } else {
                        $return['message'] = 'SAVE_REMINDER_FAIL_TEXT';
                    }
                }

                if ($reminderConfig->getId() && $reminderConfig->getStartAt() > 0) {
                    //TODO should fin another solution
                    // $scheduledEvent = $reminderConfig->createCloudWatchEvent();
                }
                $return['scheduledEvent'] = isset($scheduledEvent) ? $scheduledEvent : null;
                $return['params'] = $params;
            }
        }
        end_of_function:
        $return['startTimeOfDay'] = Helpers::__getStartTimeOfDay();
        if($return['success']){
            $this->dispatcher->setParam('return', $return);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $task_uuid
     */
    public function deleteReminderConfigAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $reminder = ReminderConfig::findFirstByUuid($uuid);
            if ($reminder && $reminder->belongsToGms()) {
                $task = Task::findFirstByUuid($reminder->getObjectUuid());
                if($task instanceof Task){
                    ModuleModel::$task = $task;
                }

                $return = $reminder->__quickRemove();
                if ($return['success'] == true) {
                    $removedScheduledEvent = $reminder->removeCloudWatchEvent();
                    $return['removedScheduledEvent'] = $removedScheduledEvent;
                    $return['message'] = 'DELETE_REMINDER_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'DELETE_REMINDER_FAIL_TEXT';
                }
            }
        }

        if($return['success']){
            $this->dispatcher->setParam('return', $return);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


}
