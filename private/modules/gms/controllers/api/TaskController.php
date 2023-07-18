<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Behavior\ObjectCacheBehavior;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\DynamodbModel;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\TextHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\RelocationExt;
use Reloday\Application\Models\RelocationServiceCompanyExt;
use Reloday\Application\Models\TaskExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController as BaseController;
use Reloday\Gms\Help\HistoryHelper;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\Comment;
use Reloday\Gms\Models\DynamoComment;
use Reloday\Gms\Models\EntityProgress;
use Reloday\Gms\Models\Event;
use Reloday\Gms\Models\EventValue;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HistoryOld;
use Reloday\Gms\Models\ReminderConfig;
use Reloday\Gms\Models\ReminderUserDeclined;
use Reloday\Gms\Models\ServiceEventValue;
use \Reloday\Gms\Models\Task as Task;
use \Reloday\Gms\Models\Relocation;
use \Reloday\Gms\Models\Assignment;
use \Reloday\Gms\Models\Employee;
use \Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\TaskChecklist;
use Reloday\Gms\Models\TaskFile;
use Reloday\Gms\Models\TaskTemplate;
use \Reloday\Gms\Models\UserProfile as UserProfile;
use \Reloday\Gms\Models\DataUserMember as DataUserMember;
use \Reloday\Gms\Models\ModuleModel as ModuleModel;
use \Reloday\Gms\Models\MediaAttachment;
use \Reloday\Gms\Models\ObjectMap;
use Reloday\Gms\Module;
use Aws;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\Exception\DynamoDbException;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TaskController extends BaseController
{
    /**
     * @param string $object_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function listAction(string $object_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        if ($object_uuid == '') {
            $this->checkAjaxIndex();
        } else {
            $this->checkAclByUuid($object_uuid, AclHelper::ACTION_INDEX);
        }

        $params = [];
        $params['task_type'] = Helpers::__getRequestValue('task_type');

        $query = Helpers::__getRequestValue('query');
        $return = ['success' => false, 'data' => [], 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($object_uuid != '') {
            $tasks = Task::__loadList([
                "isArray" => true,
                "company_id" => ModuleModel::$company->getId(),
                "object_uuid" => $object_uuid,
                "query" => $query,
                'task_type' => $params['task_type']
            ], true);

            $return['success'] = true;
            $return['data'] = $tasks;

            //Check service object
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($object_uuid);
            if ($relocationServiceCompany && $params['task_type'] == Task::TASK_TYPE_EE_TASK) {
                $serviceCompany = $relocationServiceCompany->getServiceCompany();
                $taskTemplatesAssignee = TaskTemplate::__findTaskAssigneeByUuid($serviceCompany->getUuid());
                if ($relocationServiceCompany->getActiveAssigneeTask() == ModelHelper::YES) {
                    $return['displayAssigneePopup'] = false;
                }
                if (count($tasks) > 0 && $relocationServiceCompany->getActiveAssigneeTask() == ModelHelper::NO ||
                    count($taskTemplatesAssignee) == 0 && $relocationServiceCompany->getActiveAssigneeTask() == ModelHelper::NO) {
                    $relocationServiceCompany->setActiveAssigneeTask(ModelHelper::YES);
                    $resultRsc = $relocationServiceCompany->__quickUpdate();
                    $return['displayAssigneePopup'] = false;
                }

                if (count($taskTemplatesAssignee) > 0 && count($tasks) == 0 && $relocationServiceCompany->getActiveAssigneeTask() == ModelHelper::NO) {

                    $return['displayAssigneePopup'] = true;
                }
            }

        } else {
            $params = [];
            if ($this->request->get('my') == true) {
                $params['my'] = true;
            }
            if ($this->request->get('all') == true) {
                $params['all'] = true;
            }
            if ($this->request->get('open') == true) {
                $params['open'] = true;
            }
            if ($this->request->get('completed') == true) {
                $params['completed'] = true;
            }
            if ($query) {
                $params['query'] = $query;
            }
            $return = Task::__findWithFilter(true, $params);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    public function getStartAtEndAtAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = ['success' => false, 'data' => [], 'message' => 'REMINDER_AT_TIME_NOT_FOUND_TEXT'];

        $reminder_time = Helpers::__getRequestValue('reminder_time');
        $reminder_time_unit = Helpers::__getRequestValue('reminder_time_unit');
        $before_after = Helpers::__getRequestValue('before_after');
        $reminder_at = Helpers::__getRequestValue('reminder_at');
        $type = Helpers::__getRequestValue('type');

        $reminderConfig = new ReminderConfig();
        $reminderConfig->setReminderTime($reminder_time);
        $reminderConfig->setReminderTimeUnit($reminder_time_unit);
        $reminderConfig->setBeforeAfter($before_after);
        $reminderConfig->setReminderAt($reminder_at);
        $reminderConfig->setType($type);

        $startAt = $reminderConfig->getReminderStartAtTime();
        $endAt = $reminderConfig->getReminderEndAtTime();

        if ($startAt > 0 && $endAt > 0) {
            $return = [
                'success' => true,
                'message' => 'FOUND_REMINDER_AT_TIME_TEXT',
                'data' => [
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                ],
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * add new tasks
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();
        $object_uuid = null;
        $uuid = Helpers::__getRequestValue('uuid');
        $link_type = is_numeric(Helpers::__getRequestValue('link_type')) && Helpers::__getRequestValue('link_type') > 0 ? Helpers::__getRequestValue('link_type') : Task::LINK_TYPE_INDEPENDANT;
        $object_uuid = Helpers::__getRequestValue('object_uuid') && Helpers::__getRequestValue('object_uuid') != '' && is_string(Helpers::__getRequestValue('object_uuid')) ? Helpers::__getRequestValue('object_uuid') : null;
        $relocation_uuid = Helpers::__getRequestValue('relocation_uuid');
        $assignment_uuid = Helpers::__getRequestValue('assignment_uuid');
        $task_type = Helpers::__getRequestValue('task_type');
        $isPriority = Helpers::__getRequestValue('is_priority');
        $owner_id = Helpers::__getRequestValue('owner_id');
        $owner = Helpers::__getRequestValue('owner');
        $relocation_service_company_uuid = Helpers::__getRequestValue('relocation_service_company_uuid');
        $viewers = Helpers::__getRequestValueAsArray('viewers');

        $reminders = Helpers::__getRequestValueAsArray('reminders');


        if ($link_type == '' || $object_uuid == '' || !is_null($object_uuid) || Task::__checkLinkType($link_type)) {
            if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
                $link_type = Task::LINK_TYPE_RELOCATION;
            } elseif ($assignment_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
                $link_type = Task::LINK_TYPE_ASSIGNMENT;
            } elseif ($relocation_service_company_uuid != '' && Helpers::__isValidUuid($relocation_service_company_uuid)) {
                $link_type = Task::LINK_TYPE_SERVICE;
            } else {
                $link_type = Task::LINK_TYPE_INDEPENDANT;
            }
        }

        if (!isset($task_type) || !is_numeric($task_type)) {
            $task_type = TaskExt::TASK_TYPE_INTERNAL_TASK;
        }

        if (!isset($isPriority) || !is_numeric($isPriority)) {
            $isPriority = TaskExt::TASK_PRIORITY_MEDIUM_TASK;
        }

        if (Helpers::__getRequestValue('assignment_uuid') != '') {
            $object_uuid = Helpers::__getRequestValue('assignment_uuid');
        }
        if (Helpers::__getRequestValue('relocation_uuid') != '') {
            $object_uuid = Helpers::__getRequestValue('relocation_uuid');
        }
        if (Helpers::__getRequestValue('relocation_service_company_uuid') != '') {
            $object_uuid = Helpers::__getRequestValue('relocation_service_company_uuid');
        }
        if (Helpers::__getRequestValue('object_uuid') != '') {
            $object_uuid = Helpers::__getRequestValue('object_uuid');
        }

        $sequence = 1;

        if ($object_uuid) {
            $typeObject = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);

            $tasks = Task::__loadList([
                "isArray" => true,
                "company_id" => ModuleModel::$company->getId(),
                "object_uuid" => $object_uuid,
            ]);

            if (is_array($tasks) && count($tasks) > 0) {
                $sequence = count($tasks);
            }
        } else {
            $typeObject = RelodayObjectMapHelper::__getHistoryTypeObject($uuid);
        }

        $custom_data = Helpers::__getRequestValuesArray();

        $custom_data['object_uuid'] = $object_uuid;
        $custom_data['company_id'] = ModuleModel::$company->getId();
        $custom_data['creator_id'] = ModuleModel::$user_profile->getId();

        if ($typeObject == HistoryOld::TYPE_TASK) {
            $link_type = Task::LINK_TYPE_INDEPENDANT;
            $taskParent = Task::findFirstByUuid($object_uuid);
        } elseif ($typeObject == HistoryOld::TYPE_ASSIGNMENT) {
            $link_type = Task::LINK_TYPE_ASSIGNMENT;
            $assignment = Assignment::findFirstByUuid($object_uuid);
            $custom_data['assignment_id'] = $assignment ? $assignment->getId() : null;
            $custom_data['employee_id'] = $assignment ? $assignment->getEmployeeId() : null;
            if ($task_type && $task_type == Task::TASK_TYPE_EE_TASK) {
                $custom_data['sequence'] = $assignment ? $assignment->countAssigneeTasks() + 1 : 0;
            } else {
                $custom_data['sequence'] = $assignment ? $assignment->countTasks() + 1 : 0;

            }

        } elseif ($typeObject == HistoryOld::TYPE_RELOCATION) {
            $link_type = Task::LINK_TYPE_RELOCATION;
            $relocation = Relocation::findFirstByUuid($object_uuid);

            if (!$relocation || $relocation->belongsToGms() == false) {
                $return = [
                    'success' => false,
                    'message' => 'RELOCATION_NOT_FOUND_TEXT'
                ];
                goto end;
            }
            if ($relocation->isEditable() == false) {
                $return = [
                    'success' => false,
                    'message' => 'RELOCATION_IS_CANCELLED_TEXT'
                ];
                goto end;
            }
            $custom_data['relocation_id'] = $relocation ? $relocation->getId() : null;
            $custom_data['employee_id'] = $relocation ? $relocation->getEmployeeId() : null;
            $custom_data['assignment_id'] = $relocation ? $relocation->getAssignmentId() : null;
            if ($task_type && $task_type == Task::TASK_TYPE_EE_TASK) {
                $custom_data['sequence'] = $relocation ? $relocation->countAssigneeTasks() + 1 : 0;
            } else {
                $custom_data['sequence'] = $relocation ? $relocation->countTasks() + 1 : 0;
            }

        } elseif ($typeObject == HistoryOld::TYPE_SERVICE) {
            $link_type = Task::LINK_TYPE_SERVICE;
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($object_uuid);

            if (!$relocationServiceCompany || $relocationServiceCompany->belongsToGms() == false) {
                $return = [
                    'success' => false,
                    'message' => 'SERVICE_NOT_FOUND_TEXT'
                ];
                goto end;
            }
            if ($relocationServiceCompany->isEditable() == false) {
                $return = [
                    'success' => false,
                    'message' => 'RELOCATION_SERVICE_NOT_EDITABLE_TEXT'
                ];
                goto end;
            }

            $custom_data['relocation_service_company_id'] = $relocationServiceCompany->getId();
            $custom_data['relocation_id'] = $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getId() : null;
            $custom_data['employee_id'] = $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getEmployee()->getId() : null;
            $custom_data['assignment_id'] = $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getAssignmentId() : null;

            if ($task_type && $task_type == Task::TASK_TYPE_EE_TASK) {
                $custom_data['sequence'] = $relocationServiceCompany ? $relocationServiceCompany->countAssigneeTasks() + 1 : 0;
            } else {
                $custom_data['sequence'] = $relocationServiceCompany ? $relocationServiceCompany->countTasks() + 1 : 0;
            }

        } else {
            $link_type = Task::LINK_TYPE_INDEPENDANT;
        }

        if (!Helpers::__isValidUuid($uuid)) {
            $uuid = Helpers::__uuid();
        }

        $number = Task::__createNumberTask(ModuleModel::$company->getId(), $link_type, $task_type);
        $custom_data['link_type'] = $link_type;
        $custom_data['description'] = isset($custom_data['description']) && $custom_data['description'] ? rawurldecode(base64_decode($custom_data['description'])) : '';
        $custom_data['task_type'] = $task_type;

        $task = new Task();
        $task->setUuid($uuid);
        $task->setNumber($number);
//        $task->setSequence($sequence);
        $task->setData($custom_data);
        $task->setProgress(Task::STATUS_NOT_STARTED);
        $task->setIsPriority($isPriority);
        /***** end: end owner to task ****/

        $this->db->begin();
        $resultTask = $task->__quickCreate();

        if ($resultTask['success'] == true) {
            /********** REMINDER CONFIG **********/
            if (count($reminders) > 0) {

                $relocationId = $task->getRelocation() ? $task->getRelocation()->getId() : ($task->getRelocationServiceCompany() ? $task->getRelocationServiceCompany()->getRelocationId() : null);
                $relocationServiceCompanyId = ($task->getRelocationServiceCompany() ? $task->getRelocationServiceCompany()->getId() : null);

                foreach ($reminders as $item) {
                    $reminder = new ReminderConfig();

                    $reminder->setUuid(Helpers::__uuid());
                    $reminder->setStatus(ReminderConfig::STATUS_ON_LOADING);
                    $reminder->setObjectUuid($task->getUuid());
                    $reminder->setReminderAt($item['reminder_at']);
                    $reminder->setReminderTime($item['reminder_time']);
                    $reminder->setReminderTimeUnit($item['reminder_time_unit']);
                    $reminder->setBeforeAfter($item['before_after']);
                    $reminder->setType($item['type']);
                    $reminder->setCompanyId(ModuleModel::$company->getId());
                    $reminder->setRelocationId($relocationId);
                    $reminder->setRelocationServiceCompanyId($relocationServiceCompanyId);
                    $reminder->setNumber($task->getNumber());
                    $reminder->setServiceEventId($item['service_event_id']);

                    if ($item['type'] == ReminderConfig::TYPE_BASE_ON_DATE && $reminder->checkReminderApplyActive() == true) {
                        $reminder->setStartAt($reminder->getReminderStartAtTime());
                        $reminder->setEndAt($reminder->getReminderEndAtTime());
                    }

                    if ($item['type'] == ReminderConfig::TYPE_BASE_ON_EVENT && $reminder->getServiceEventId() > 0 && $task->getRelocationServiceCompanyId() > 0) {
                        $reminderEvent = ServiceEventValue::__findByServiceAndEvent($task->getRelocationServiceCompanyId(), $reminder->getServiceEventId());
                        if ($reminderEvent && $reminderEvent->getValue() > 0) {
                            $reminder->setReminderAt($reminderEvent->getValue());

                            if ($reminder->checkReminderApplyActive() == true) {
                                $reminder->setStartAt($reminder->getReminderStartAtTime());
                                $reminder->setEndAt($reminder->getReminderEndAtTime());
                            }
                        }
                    }

                    if ($item['type'] == ReminderConfig::TYPE_BASE_ON_EVENT &&
                        isset($item['event_id']) &&
                        $item['event_id'] > 0) {
                        $event = Event::findFirstById($item['event_id']);
                        if($event){
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
                                goto end;
                            }

                            $eventValue = EventValue::__findByOjbectAndEvent($object->getId(), $event->getId());

                            if ($eventValue) {
                                $eventValue->setValue($event->getFieldType() == Event::FIELD_TYPE_DATETIME ? strtotime($object->get($event->getFieldName())) : (double)$object->get($event->getFieldName()));
                                $return = $eventValue->__quickUpdate();
                                if (!$return["success"]) {
                                    $this->db->rollback();
                                    goto end;
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
                                    goto end;
                                }
                            }

                            $reminder->setEventValueUuid($eventValue->getUuid());
                            $reminder->setReminderAt($eventValue->getValue());
                        }

                    }

                    $returnCreateReminder = $reminder->__quickSave();

                    if ($returnCreateReminder['success'] == false) {
                        $return = ['success' => false, 'returnCreateReminder' => $returnCreateReminder];
                        $this->db->rollback();
                        goto end;
                    }
                }
            }

            if (isset($custom_data['task_files']) && count($custom_data['task_files'])) {
                foreach ($custom_data['task_files'] as $item) {

                    $taskFile = new TaskFile();
                    if (!isset($item['media']) || !$item['media'] || !$item['media']['uuid']) {
                        continue;
                    }

                    $taskFile->setUuid(Helpers::__uuid());
                    $taskFile->setObjectUuid($task->getUuid());
                    $taskFile->setCompanyUuid(ModuleModel::$company->getUuid());

                    if ($item['name']) {
                        $taskFile->setName($item['name']);
                    } else {
                        $taskFile->setName($item['media']['name']);
                    }
                    if ($item['is_request_file']) {
                        $taskFile->setIsRequestFile(ModelHelper::YES);
                    } else {
                        $taskFile->setIsRequestFile(ModelHelper::NO);
                    }
                    $taskFile->setMediaUuid($item['media']['uuid']);

                    $resultTaskFile = $taskFile->__quickCreate();

                    if (!$resultTaskFile['success']) {
                        $return = $resultTaskFile;
                        $this->db->rollback();
                        $return['success'] = false;
                        $return['message'] = 'TASK_TEMPLATE_CREATE_FAILED_TEXT';
                        goto end;
                    }

                }
            }

            /********** CREATOR **********/
            $profileCreator = DataUserMember::getDataCreator($task->getUuid());
            if (!$profileCreator) {
                $resultAddCreator = $task->addCreator(ModuleModel::$user_profile);
                if ($resultAddCreator['success'] == false) {
                    $return = ['success' => false, '$resultAddCreator' => $resultAddCreator];
                    $this->db->rollback();
                    goto end;
                }
            }
            /********** REPORTER **********/

            if ($object_uuid != '') {
                $reporterProfile = DataUserMember::getDataReporter($object_uuid);
                if ($reporterProfile) {
                    $resultAddReporter = $task->addReporter($reporterProfile);
                    if ($resultAddReporter['success'] == false) {
                        $return = ['success' => false, '$resultAddReporter' => $resultAddReporter];
                        $this->db->rollback();
                        goto end;
                    }
                }
            } else {
                $resultAddReporter = $task->addReporter(ModuleModel::$user_profile);
                if ($resultAddReporter['success'] == false) {
                    $return = ['success' => false, '$resultAddReporter' => $resultAddReporter];
                    $this->db->rollback();
                    goto end;
                }
            }


            $ownerProfile = DataUserMember::getDataOwner($uuid);
            if (!$ownerProfile) {
                $ownerProfile = DataUserMember::getDataOwner($task->getObjectUuid());
                if ($ownerProfile) {
                    $resultAddOwner = $task->addOwner($ownerProfile);
                    if ($resultAddOwner['success'] == false) {
                        $return = ['success' => false, '$resultAddOwner' => $resultAddOwner];
                        $this->db->rollback();
                        goto end;
                    }
                }
            }

            if ($ownerProfile) {
                $task->setOwnerId($ownerProfile->getId());
                $resultUpdateTask = $task->__quickUpdate();
                if ($resultUpdateTask['success'] == false) {
                    $return = ['success' => false, '$resultUpdateTask' => $resultUpdateTask];
                    $this->db->rollback();
                    goto end;
                }
            } else if ($task_type != Task::TASK_TYPE_EE_TASK){
                $return = ['success' => false, 'message' => 'OWNER_REQUIRED_TEXT'];
                $this->db->rollback();
                goto end;
            }


            $this->db->commit();

            $resultUpdateTask = $task->updateServiceProgress();

            $return = [
                'success' => true,
                'message' => 'TASK_CREATE_SUCCESS_TEXT',
                'data' => $task,
            ];
        } else {
            $return = $resultTask;
        }

        end:

        if (isset($return['success']) && $return['success'] == true && isset($task)) {
            if ($link_type == Task::LINK_TYPE_SERVICE) {
                $resultSaveEntity = $task->setEntityProgress();
                if ($resultSaveEntity['success'] == true) {
                    $returnPusher = PushHelper::__sendPushReload($task->getObjectUuid());
                    $return['$pusher'] = $returnPusher;
                }
            }
            if ($task->getTaskType() == Task::TASK_TYPE_INTERNAL_TASK) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_CREATE);
            }

            ModuleModel::$task = $task;
            $this->dispatcher->setParam('return', $return);
        }
        $return['customData'] = $custom_data;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $task_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteAction(string $task_uuid)
    {
        $this->view->disable();
        $this->checkAjax('DELETE');

        if ($task_uuid == '') {
            $data = $this->request->getJsonRawBody();
            if (isset($data->uuid) && $data->uuid != '') {
                $task_uuid = $data->uuid;
            }
        }

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if (isset($task_uuid) && $task_uuid != '') {
            $task = Task::findFirstByUuid($task_uuid);
            if ($task instanceof Task && $task->belongsToGms() == true) {

                $this->checkPermissionDeleteTask($task);

                if ($task->checkMyDeletePermission() == true) {
                    $taskDeletedObjectUuid = $task->getObjectUuid();
                    $resultRemove = $task->__quickRemove();
                    if ($resultRemove['success'] == true) {

                        $resultSaveEntity = $task->setEntityProgress();
                        if ($resultSaveEntity['success'] == true && $taskDeletedObjectUuid) {
                            $returnPusher = PushHelper::__sendPushReload($taskDeletedObjectUuid);
                        }
                        $owner = DataUserMember::getDataOwner($task_uuid, ModuleModel::$company->getId());
                        $return = [
                            'uuid' => $taskDeletedObjectUuid,
                            'check_pusher' => isset($returnPusher) ? $returnPusher : false,
                            'entity_progress' => $resultSaveEntity,
                            'success' => true,
                            'message' => 'TASK_DELETE_SUCCESS_TEXT'
                        ];

                        //Delete reminder
                        $reminder = $task->getReminderConfig();
                        $resultReminderDelete = $reminder->delete();

                        //Delete checklist
                        $checklists = $task->getTaskChecklists();
                        if (count($checklists) > 0) {
                            $resultChecklistDelete = $checklists->delete();
                        }

                        //Delete task_file
                        $taskFiles = $task->getTaskFiles();
                        if (count($taskFiles) > 0) {
                            $resultTaskFileDelete = $taskFiles->delete();
                        }

                        // Rearrange task in object
                        $object = $task->getAssignment();
                        if (!$object) {
                            $object = $task->getRelocation();
                            if (!$object) {
                                $object = $task->getRelocationServiceCompany();
                            }
                        }

                        if ($object) {
                            $object->setSequenceOfTasks();
                            $object->setSequenceOfAssigneeTasks();
                        }
//                        $update_reminder_counter_queue = RelodayQueue::__getQueueUpdateReminderCounter();
//                        $dataArray = [
//                            'action' => "delete_reminder_counter",
//                            'object_uuid' => $task_uuid,
//                            'old_owner_uuid' =>isset($owner) && $owner ? $owner->getUuid() : '',
//                        ];
//                        $return['update_reminder_counter'] = $update_reminder_counter_queue->addQueue($dataArray);
                    } else {
                        $return = ['success' => false, 'message' => $task->getMessages()];
                    }
                } else {
                    $return = ['success' => false, 'message' => 'PERMISSION_NEEDED_TEX'];
                }
            }
        }
        end:
        if ($return['success'] == true) {
            ModuleModel::$task = $task;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_REMOVE);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param String $task_uuid
     * @param int $status
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function changeStatus(string $task_uuid, int $status)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $apiResults = [];
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
        if (!($task_uuid != '' && Helpers::__isValidUuid($task_uuid))) {
            goto end_of_function;
        }
        $task = Task::findFirstByUuid($task_uuid);
        if (!($task && $task->belongsToGms())) {
            goto end_of_function;
        }
        $this->checkPermissionEditTask($task);
        if ($task->checkMyEditPermission() == true) {
            if ($status == Task::STATUS_AWAITING_FINAL_REVIEW && $task->getIsFinalReview() == ModelHelper::NO) {
                goto end_of_function;
            }
            $task->setProgress($status);
            $resultSave = $task->__quickUpdate();
            if ($resultSave['success'] == true) {

                $resultUpdateProgress = $task->updateServiceProgress();

                $return = [
                    'success' => true,
                    '$resultUpdateProgress' => isset($resultUpdateProgress) ? $resultUpdateProgress : null,
                    'message' => 'TASK_EDIT_SUCCESS_TEXT',
                    'data' => $task,
                ];
            } else {
                $return['detail'] = $task;
            }
        }
        end_of_function:

        if ($return['success'] == true && isset($task)) {
            ModuleModel::$task = $task;
            $this->dispatcher->setParam('return', $return);
            if ($task->isDone()) {
                $apiResults[] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_SET_DONE);
            }

            if ($task->isInProgress()) {
                $apiResults[] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_SET_IN_PROGRESS);
            }

            if ($task->isAwaitingReview()) {
                $apiResults[] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_SET_AWAITING_FINAL_REVIEW);
            }

            if ($task->isTodo()) {
                $apiResults[] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_SET_TODO);
            }
        }

        $return['$apiResults'] = $apiResults;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function quicksaveAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit();

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT', 'detail' => $this->request->getPut()];

        $task_uuid = $this->request->getPut('uuid') != '' ? $this->request->getPut('uuid') : null;

        if ($task_uuid != '') {

            $task = Task::findFirstByUuid($task_uuid);

            if (!($task && $task->belongsToGms())) {
                $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
                goto end_of_function;
            }

            $this->checkPermissionEditTask($task);

            if ($task->checkMyEditPermission() == true) {

                $object_uuid = null;
                $link_type = is_numeric($this->request->getPost('link_type')) && $this->request->getPost('link_type') >= 0 ? $this->request->getPost('link_type') : 0;
                $object_uuid = $this->request->getPost('object_uuid') != '' ? $this->request->getPost('object_uuid') : null;

                if ($link_type >= 0 && $object_uuid != '' && in_array($link_type, [Task::LINK_TYPE_RELOCATION, Task::LINK_TYPE_ASSIGNMENT, Task::LINK_TYPE_SERVICE, Task::LINK_TYPE_INDEPENDANT])) {
                    //relocation

                } else {
                    //relocation service
                    if ($this->request->getPost('relocation_uuid') !== '') {
                        $link_type = Task::LINK_TYPE_RELOCATION;
                    } elseif ($this->request->getPost('assignment_uuid') !== '') {
                        $link_type = Task::LINK_TYPE_ASSIGNMENT;
                    } elseif ($this->request->getPost('relocation_service_company_uuid') !== '') {
                        $link_type = Task::LINK_TYPE_SERVICE;
                    } else {
                        $link_type = Task::LINK_TYPE_INDEPENDANT;
                    }
                }


                if ($this->request->getPost('assignment_uuid') != '') {
                    $object_uuid = $this->request->getPost('assignment_uuid');
                }
                if ($this->request->getPost('relocation_uuid') != '') {
                    $object_uuid = $this->request->getPost('relocation_uuid');
                }
                if ($this->request->getPost('relocation_service_company_uuid') != '') {
                    $object_uuid = $this->request->getPost('relocation_service_company_uuid');
                }

                if ($this->request->getPost('object_uuid') != '') {
                    $object_uuid = $this->request->getPost('object_uuid');
                }

                $result = $task->__save();

                if ($result instanceof Task) {
                    $return = [
                        'success' => true,
                        'message' => 'TASK_EDIT_SUCCESS_TEXT',
                        'data' => $result,
                    ];
                } else {
                    $return = $result;
                    $return['detail'] = $this->request->getPut();
                }
            }
        }

        end_of_function:

        if ($return['success'] == true && isset($task)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_UPDATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function setDoneAction(string $task_uuid)
    {
        return $this->changeStatus($task_uuid, Task::STATUS_COMPLETED);
    }

    /**
     * @return mixed
     */
    public function setInProgressAction(string $task_uuid)
    {
        return $this->changeStatus($task_uuid, Task::STATUS_IN_PROCESS);
    }

    /**
     * @return mixed
     */
    public function setAwaitingFinalReviewAction(string $task_uuid)
    {
        return $this->changeStatus($task_uuid, Task::STATUS_AWAITING_FINAL_REVIEW);
    }

    /**
     * @return mixed
     */
    public function setTodoAction(string $task_uuid)
    {
        return $this->changeStatus($task_uuid, Task::STATUS_NOT_STARTED);
    }

    /**
     * @return mixed
     */
    public function itemAction($task_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($task_uuid != '') {
            $task = Task::findFirstByUuid($task_uuid);

            if ($task && $task->getTaskType() == Task::TASK_TYPE_INTERNAL_TASK) {
                if (!DataUserMember::checkViewPermissionOfUserByProfile($task->getUuid(), ModuleModel::$user_profile)) {
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'permissionNotFound' => true];
                    goto end_of_function;
                }

                $this->checkPermissionViewTask($task);
            }

            if (($task && $task->belongsToGms() && $task->checkMyViewPermission() == true && $task->isActive()) ||
                ($task->getTaskType() == Task::TASK_TYPE_EE_TASK && $task->isActive())) {

                $dataTask = $task->toArray();
                $dataTask['formatted_description'] = TextHelper::replaceUserTag($task->getDescription(), $task->getMembers());
                $dataTask['sender'] = $task->getSenderEmailComments();
                $dataTask['breadcrumb'] = $task->getBreadCrumbs();
                $employee = $task->getEmployee();
                $assignment = $task->getMainAssignment();
                $service = $task->getRelocationServiceCompany();
                $dataTask['isEntityArchived'] = false;
                $dataTask['alert_message'] = '';
                if ($service && $service->isArchived()) {
                    $dataTask['isEntityArchived'] = true;
                    $dataTask['alert_message'] = 'TASK_BELONGS_TO_DELETED_ITEM_TEXT';
                }

                $profileOwner = $task->getSimpleProfileOwner();;
                $dataTask['owner_name'] = $profileOwner ? $profileOwner->getFullname() : '';
                $dataTask['owner_uuid'] = $profileOwner ? $profileOwner->getUuid() : '';
                $dataTask['due_at_time'] = $task->getDueAtTime();
                $dataTask['due_at'] = $task->getDueAt();
                $dataTask['started_at'] = $task->getStartedAt();
                $dataTask['ended_at'] = $task->getEndedAt();
                if (!$employee) {
                    if ($task->getParentTask()) $employee = $task->getParentTask()->getEmployee();
                }
                if (!$employee) {
                    if ($task->getTask()) $employee = $task->getTask()->getEmployee();
                }
                if (!$employee) {
                    $employee = $assignment ? $assignment->getEmployee() : null;
                }

                if (!$employee) {
                    $employeeArray = [];
                    $dataTask['employee_name'] = null;
                    $dataTask['employee_uuid'] = null;
                } else {
                    $employeeArray = $employee->parseArrayData($assignment->getRelocation() ? $assignment->getRelocation()->getId() : 0);
                    $dataTask['employee_name'] = $employee->getFullname();
                    $dataTask['employee_uuid'] = $employee->getUuid();
                }

                $relatedItem = $task->getRelatedItem();
                $dataTask['related'] = $relatedItem;


                $return = [
                    'success' => true,
                    'employee' => $employeeArray,
                    'assignment' => $assignment ? $assignment->getInfoDetailInArray() : null,
                    'relocation' => $task->getMainRelocation(),
                    'service' => $task->getMainRelocationServiceCompany(),
                    'data' => $dataTask,
                ];
            }
            $return['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
            $return['task_uuid'] = $task_uuid;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getAssignmentAction($task_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {
            $task = Task::findFirstByUuid($task_uuid);

            if ($task) {
                $this->checkPermissionIndexTask($task);
            }

            if ($task && $task->belongsToGms() && $task->checkMyViewPermission() == true) {
                $assignment = $task->getMainAssignment();

                $return = [
                    'success' => true,
                    'data' => $assignment ? $assignment->getInfoDetailInArray() : null
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getRelocationAction($task_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {
            $task = Task::findFirstByUuid($task_uuid);
            if ($task) {
                $this->checkPermissionIndexTask($task);
            }
            if ($task && $task->belongsToGms() && $task->checkMyViewPermission() == true) {
                $return = [
                    'success' => true,
                    'data' => $task->getMainRelocation(),
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getRelocationServiceCompanyAction($task_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {

            $task = Task::findFirstByUuid($task_uuid);

            if ($task) {
                $this->checkPermissionIndexTask($task);
            }

            if ($task && $task->belongsToGms() && $task->checkMyViewPermission() == true) {
                $return = [
                    'success' => true,
                    'data' => $task->getMainRelocationServiceCompany(),
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function getParentTaskAction($task_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {

            $task = Task::findFirstByUuid($task_uuid);

            if ($task && $task->getTaskType() == Task::TASK_TYPE_INTERNAL_TASK) {
                if (!DataUserMember::checkViewPermissionOfUserByProfile($task->getUuid(), ModuleModel::$user_profile)) {
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'permissionNotFound' => true];
                    goto end_of_function;
                }
                $this->checkPermissionIndexTask($task);
            }

            if ($task && $task->belongsToGms() && $task->checkMyViewPermission() == true && $task->getTaskType() == Task::TASK_TYPE_INTERNAL_TASK) {
                $return = [
                    'success' => true,
                    'data' => $task->getTask(),
                ];
            }

            if ($task && $task->getTaskType() == Task::TASK_TYPE_EE_TASK) {
                $return = [
                    'success' => true,
                    'data' => []
                ];
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function item_simpleAction($task_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        if ($task_uuid != '') {
            $task = Task::findFirstByUuid($task_uuid);
            if ($task) {
                $this->checkPermissionIndexTask($task);
            }
            if ($task && $task->belongsToGms()) {
                $taskOwner = $task->getSimpleProfileOwner();
                $task->updateServiceProgress();
                $return = [
                    'success' => true,
                    'data' => [
                        'status' => (int)$task->getStatus(),
                        'progress' => (int)$task->getProgress(),
                        'is_priority' => (int)$task->getIsPriority(),
                        'is_final_review' => (int)$task->getIsFinalReview(),
                        'is_flag' => (int)$task->getIsFlag(),
                        'is_milestone' => (int)$task->getIsMilestone(),
                        'has_file' => (int)$task->getHasFile(),
                        'due_at' => $task->getDueAt(),
                        'due_at_time' => $task->getDueAtTime(),
                        'started_at' => $task->getStartedAt(),
                        'started_at_time' => $task->getStartedAtTime(),
                        'ended_at' => $task->getEndedAt(),
                        'ended_at_time' => $task->getEndedAtTime(),
                        'description' => $task->getDescription(),
                        'owner_name' => $taskOwner ? $taskOwner->getFullName() : "",
                        'owner_uuid' => $taskOwner ? $taskOwner->getUuid() : "",
                        "sequence" => $task->getSequence(),
                        'id' => (int)$task->getId(),
                        'name' => $task->getName(),
                        'uuid' => $task->getUuid(),
                        'number' => $task->getNumber(),
                        'breadcrumb' => $task->getBreadCrumbs()
                    ]
                ];
            }
            $return['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
            $return['task_uuid'] = $task_uuid;
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * get list of subtask
     * @param $task_uuid
     */
    public function subtaskAction($task_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();


        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        if ($task_uuid != '') {
            $task = Task::findFirstByUuid($task_uuid);

            if ($task) {
                $this->checkPermissionIndexTask($task);
            }
            if ($task && $task->belongsToGms() && $task->checkMyEditPermission() == true) {
                $return = [
                    'success' => true,
                    'data' => $task->getSubTask(),
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_uuid
     * @return mixed
     */
    public function get_all_membersAction($task_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'data' => [], 'message' => 'TASK_NOT_FOUND_TEXT'];

        if ($task_uuid != '') {
            $task = Task::findFirstByUuid($task_uuid);

            if ($task && $task->belongsToGms() && $task->checkMyViewPermission() == true) {

                /** if task is linked to relocation_id $members */
                if ($task->getLinkType() == Task::LINK_TYPE_RELOCATION) {
                    if ($task->getRelocation()) {
                        $members = UserProfile::getWorkers($task->getRelocation()->getHrCompanyId());
                    } else {
                        $members = UserProfile::getWorkers(ModuleModel::$company->getId());
                    }
                } elseif ($task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT) {
                    if ($task->getAssignment()) {
                        $members = UserProfile::getWorkers($task->getAssignment()->getCompanyId());
                    } else {
                        $members = UserProfile::getWorkers(ModuleModel::$company->getId());
                    }
                } elseif ($task->getLinkType() == Task::LINK_TYPE_SERVICE) {
                    if ($task->getRelocationServiceCompany()) {
                        $members = UserProfile::getWorkers($task->getRelocationServiceCompany()->getRelocation()->getHrCompanyId());
                    } else {
                        $members = UserProfile::getWorkers(ModuleModel::$company->getId());
                    }
                } else {
                    $members = UserProfile::getWorkers(ModuleModel::$company->getId());
                }

                /** if task is likend to assignment id */
                $users_array = [];
                foreach ($members as $user) {
                    $users_array[$user->getId()] = $user->toArray();
                    $users_array[$user->getId()]['avatar'] = $user->getAvatar();
                    $users_array[$user->getId()]['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
                    $users_array[$user->getId()]['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;
                    /** check selected  */
                    $viewer_uuids = DataUserMember::getViewersUuids($task->getUuid());
                    if (isset($viewer_uuids[$user->getUuid()]) && $viewer_uuids[$user->getUuid()] != '') {
                        $users_array[$user->getId()]['selected'] = true;
                    } else {
                        $users_array[$user->getId()]['selected'] = false;
                    }
                }

                $return = [
                    'success' => true,
                    'data' => array_values($users_array)
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function report_commentsAction($task_uuid)
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        if ($task_uuid != '') {

            $task = Task::findFirstByUuid($task_uuid);
            if ($task && $task->belongsToGms()) {
                $return = Comment::__findWithFilter([
                    'limit' => 100,
                    'object_uuid' => $task->getUuid(),
                    'report' => Comment::REPORT_YES,
                ]);
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function report_statusAction($task_uuid)
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {

            $task = Task::findFirstByUuid($task_uuid);

            if ($task && $task->belongsToGms()) {
                $list = [];
                $historyObjectList = History::find([
                    "conditions" => "object_uuid = :object_uuid: and user_action IN  ({actions:array} )",
                    "bind" => [
                        "object_uuid" => $task->getUuid(),
                        "actions" => [
                            "HISTORY_SET_IN_PROGRESS", "HISTORY_SET_TODO", "HISTORY_SET_DONE", "HISTORY_CHANGE_STATUS"
                        ]
                    ]
                ]);
                $histories = [];
                if (count($historyObjectList) > 0) {
                    foreach ($historyObjectList as $listItem) {
                        $history = $listItem->parseDataToArray();
                        $history['task_uuid'] = $history['object_uuid'];
                        $history['action'] = $history['user_action'];
                        $histories[] = $history;
                    }
                }
                $return = ['success' => true, 'data' => $histories];
            } else {
                $return = ['success' => true, 'data' => []];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed save task Action
     */
    public function updateTaskAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT', 'detail' => $this->request->getPut()];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $task = Task::findFirstByUuid($uuid);


            if (!($task && $task->belongsToGms() && $task->checkMyEditPermission())) {
                $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
                goto end_of_function;
            }

            $this->checkPermissionEditTask($task);

            $object_uuid = null;
            $link_type = is_numeric(Helpers::__getRequestValue('link_type')) && Helpers::__getRequestValue('link_type') >= 0 ? Helpers::__getRequestValue('link_type') : 0;
            $object_uuid = Helpers::__getRequestValue('object_uuid') != '' ? Helpers::__getRequestValue('object_uuid') : null;

            if ($link_type >= 0 && $object_uuid != '' && in_array($link_type, Task::$types)) {
                //relocation
            } else {
                //relocation service
                if (Helpers::__getRequestValue('relocation_uuid') !== '') {
                    $link_type = Task::LINK_TYPE_RELOCATION;
                } elseif (Helpers::__getRequestValue('assignment_uuid') !== '') {
                    $link_type = Task::LINK_TYPE_ASSIGNMENT;
                } elseif (Helpers::__getRequestValue('relocation_service_company_uuid') !== '') {
                    $link_type = Task::LINK_TYPE_SERVICE;
                } else {
                    $link_type = Task::LINK_TYPE_INDEPENDANT;
                }
            }

            if (Helpers::__getRequestValue('assignment_uuid') != '') {
                $object_uuid = Helpers::__getRequestValue('assignment_uuid');
            }
            if (Helpers::__getRequestValue('relocation_uuid') != '') {
                $object_uuid = Helpers::__getRequestValue('relocation_uuid');
            }
            if (Helpers::__getRequestValue('relocation_service_company_uuid') != '') {
                $object_uuid = Helpers::__getRequestValue('relocation_service_company_uuid');
            }

            if (Helpers::__getRequestValue('object_uuid') != '') {
                $object_uuid = Helpers::__getRequestValue('object_uuid');
            }
            $dataParams = Helpers::__getRequestValuesArray();

            if(isset($dataParams['description']) && $dataParams['description'] == 'bnVsbA=='){ // 'bnVsbA==' is base64_encode(null)
                $dataParams['description'] = null;
            }

            if (isset($dataParams['description']) && $dataParams['description'] != null && $dataParams['description'] != '') {
                $dataParams['description'] = rawurldecode(base64_decode($dataParams['description']));
            }

            if ($dataParams['is_final_review'] == 0 && $dataParams['progress'] == Task::STATUS_AWAITING_FINAL_REVIEW) {
                $dataParams['progress'] = Task::STATUS_IN_PROCESS;
            }

            $task->setData($dataParams);
            $task->setDescription($dataParams['description']);

            $resultOrTask = $task->__quickUpdate();

            if ($resultOrTask['success'] == true) {
                if ($task->isDueAtChanged() == true) {
                    $task->sendToCreateReminderQueue(); //send to create reminder queue
                }
                $dataTask = $task->toArray();
                $dataTask['formatted_description'] = TextHelper::replaceUserTag($task->getDescription(), $task->getMembers());
                $return = [
                    'success' => true,
                    'message' => 'TASK_EDIT_SUCCESS_TEXT',
                    'data' => $dataTask
                ];
            } else {
                $return = $resultOrTask;
            }
        }

        $return['raw'] = Helpers::__getRequestValuesArray();
        $return['check_time'] = time();

        end_of_function:

        if ($return['success'] == true && isset($task) && $task) {
            ModuleModel::$task = $task;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_UPDATE);
        }


        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param string $object_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function issuesAction($object_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT', 'POST']);
        $object_uuid = $object_uuid != '' ? $object_uuid : Helpers::__getRequestValue('object_uuid');
        if ($object_uuid == '') {
            $this->checkAclIndex();
        } else {
            $this->checkAclByUuid($object_uuid, AclHelper::ACTION_EDIT);
        }

        $isArray = Helpers::__getRequestValue('isArray');
        $companyId = Helpers::__getRequestValue('company_id');
        if ($companyId == '') {
            $companyId = ModuleModel::$company->getId();
        }

        $report_relocation = Helpers::__getRequestValue('report_relocation');
        if($report_relocation == true){
            $listObjectUuid = [];
            array_push($listObjectUuid, $object_uuid);

            $service_ids = Helpers::__getRequestValue('service_ids');
            for ($i = 0; $i < count($service_ids); $i++){
                if($service_ids[$i] > 0){
                    $service = RelocationServiceCompany::findFirstById($service_ids[$i]);
                    array_push($listObjectUuid, $service->getUuid());
                }
            }
        }

        $params = [];
        $return = ['success' => false, 'data' => [], 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {

            if($report_relocation == true){
                $tasks = Task::__loadList([
                    "list_object_uuid" => $listObjectUuid,
                    "isArray" => $isArray,
                    "task_type" => 'all'
                ], true);
            }else{
                $tasks = Task::__loadList([
                    "company_id" => $companyId,
                    "object_uuid" => $object_uuid,
                    "isArray" => $isArray,
                ], true);
            }

            $return = ['success' => true, 'data' => $tasks];
        } else {
            $params = [];
            if ($this->request->get('my') == true) {
                $params['my'] = true;
            }
            if ($this->request->get('all') == true) {
                $params['all'] = true;
            }
            if ($this->request->get('open') == true) {
                $params['open'] = true;
            }
            if ($this->request->get('completed') == true) {
                $params['completed'] = true;
            }

            /****** owners ****/
            $owners = Helpers::__getRequestValue('owners');
            $ownerUuids = [];
            if (is_array($owners) && count($owners) > 0) {
                foreach ($owners as $owner) {
                    $owner = (array)$owner;
                    if (isset($owner['uuid'])) {
                        $ownerUuids[] = $owner['uuid'];
                    }
                }
            }
            $params['owners'] = $ownerUuids;

            /****** relocations ****/
            $relocations = Helpers::__getRequestValue('relocations');
            $relocationUuids = [];

            if (is_array($relocations) && count($relocations) > 0) {
                foreach ($relocations as $relocation) {
                    $relocation = (array)$relocation;
                    if (isset($relocation['id'])) {
                        $relocationUuids[] = $relocation['id'];
                    }
                }
            }
            $params['relocations'] = $relocationUuids;
            /****** assignees ****/
            $assignees = Helpers::__getRequestValue('assignees');
            $assigneeUuids = [];

            if (is_array($assignees) && count($assignees) > 0) {
                foreach ($assignees as $assignee) {
                    $assignee = (array)$assignee;
                    if (isset($assignee['id'])) {
                        $assigneeUuids[] = $assignee['id'];
                    }
                }
            }
            $params['assignees'] = $assigneeUuids;
            /****** statues ****/
            $statuses = Helpers::__getRequestValue('statuses');
            $statusArray = [];
            if (is_array($statuses) && count($statuses) > 0) {
                foreach ($statuses as $item) {
                    $item = (array)$item;
                    if (isset($item['value'])) {
                        $statusArray[] = $item['value'];
                    }
                }
            }
            $params['statuses'] = $statusArray;
            /****** services ****/
            $services = Helpers::__getRequestValue('services');
            $servicesIds = [];

            if (is_array($services) && count($services) > 0) {
                foreach ($services as $item) {
                    $item = (array)$item;
                    if (isset($item['id'])) {
                        $servicesIds[] = $item['id'];
                    }
                }
            }
            $params['services'] = $servicesIds;

            /****** due_date ****/
            $due_date = Helpers::__getRequestValue('due_date');
            if (Helpers::__isDate($due_date)) {
                $params['due_date'] = $due_date;
            }
            /****** query ****/
            $params['query'] = Helpers::__getRequestValue('query');
            /****** start ****/
            $params['start'] = Helpers::__getRequestValue('start');
            $params['page'] = Helpers::__getRequestValue('page');

            if (ModuleModel::$user_profile->isAdminOrManager() == false) {
                $params['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
            }
//            $orders = Helpers::__getRequestValue('orders');
//            $ordersConfig = Helpers::__getApiOrderConfig($orders);
            /***** new filter ******/
            $params['employee_id'] = Helpers::__getRequestValue('employee_id');
            $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');
            $params['owner_uuid'] = Helpers::__getRequestValue('owner_uuid');

            $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
            $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

            $order = Helpers::__getRequestValueAsArray('sort');
            $ordersConfig = Helpers::__getApiOrderConfig([$order]);
            $orders = Helpers::__getRequestValue('orders');
            if ($orders && is_array($orders)) {
                $orders = reset($orders);
                if (is_object($orders)) {
                    $ordersConfig = [];
                    $ordersConfig[] = [
                        "field" => strtolower($orders->column),
                        "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                    ];
                }
            }

            //Task Type
            $params['task_type'] = Helpers::__getRequestValue('task_type');

            /*** search **/
            $return = Task::__findWithFilter(true, $params, $ordersConfig);
            if ($return['success'] == true) {
                $return['recordsFiltered'] = ($return['total_items']);
                $return['recordsTotal'] = ($return['total_items']);
                $return['length'] = ($return['total_items']);
                $return['draw'] = Helpers::__getRequestValue('draw');
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed save task Action
     */
    public function getRemindersListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $task = Task::findFirstByUuid($uuid);

            if ($task) {
                $this->checkPermissionViewTask($task);
            }

            if ($task && $task->belongsToGms() && $task->isActive() == true) {
                $reminderConfigList = $task->getReminderConfig();

                $reminderConfigArray = [];

                foreach ($reminderConfigList as $item) {
                    $reminderConfigArray[$item->getUuid()] = $item->toArray();
                    $reminderConfigArray[$item->getUuid()]['reminder_at'] = intval($item->getReminderAt());
                    $reminderConfigArray[$item->getUuid()]['event_name'] = $item->getServiceEvent() ? $item->getServiceEvent()->getLabel() : '';
                    $event_value = $item->getEventValue();
                    if($event_value){
                        $reminderConfigArray[$item->getUuid()]['event_name'] = $event_value->getEvent() ? $event_value->getEvent()->getLabel() : '';
                    }
                }
                $return = [
                    'success' => true,
                    'data' => array_values($reminderConfigArray)
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $task
     */
    public function checkPermissionDeleteTask($task)
    {
        AclHelper::__setUserProfile(ModuleModel::$user_profile);
        $checkDelete = AclHelper::__checkPermissionDetailGms($this->dispatcher->getControllerName(), AclHelper::ACTION_DELETE);
        if ($checkDelete['success'] == false) {
            if ($task->getObjectUuid() != '') {
                $controller = AclHelper::__getControllerByUuid($task->getObjectUuid());
                $checkDelete = AclHelper::__checkPermissionDetailGms($controller, AclHelper::ACTION_MANAGE_TASK);
            }
        }
        if ($checkDelete['success'] == false) {
            exit(json_encode($checkDelete));
        }
    }

    /**
     * @param $task
     */
    public function checkPermissionEditTask($task)
    {
        AclHelper::__setUserProfile(ModuleModel::$user_profile);
        $checkEdit = AclHelper::__checkPermissionDetailGms($this->dispatcher->getControllerName(), AclHelper::ACTION_EDIT);
        if ($checkEdit['success'] == false) {
            if ($task->getObjectUuid() != '') {
                $controller = AclHelper::__getControllerByUuid($task->getObjectUuid());
                $checkEdit = AclHelper::__checkPermissionDetailGms($controller, AclHelper::ACTION_MANAGE_TASK);
            }
        }
        if ($checkEdit['success'] == false) {
            exit(json_encode($checkEdit));
        }
    }

    /**
     * @param $task
     */
    public function checkPermissionViewTask($task)
    {
        AclHelper::__setUserProfile(ModuleModel::$user_profile);
        $checkView = AclHelper::__checkPermissionDetailGms($this->dispatcher->getControllerName(), AclHelper::ACTION_INDEX);

        if ($checkView['success'] == false) {
            if ($task->getObjectUuid() != '') {
                $controller = AclHelper::__getControllerByUuid($task->getObjectUuid());
                $checkView = AclHelper::__checkPermissionDetailGms($controller, AclHelper::ACTION_MANAGE_TASK);
            }
        }
        if ($checkView['success'] == false) {
            exit(json_encode($checkView));
        }

    }

    /**
     * @param $task
     */
    public function checkPermissionIndexTask($task)
    {
        AclHelper::__setUserProfile(ModuleModel::$user_profile);
        $checkIndex = AclHelper::__checkPermissionDetailGms($this->dispatcher->getControllerName(), AclHelper::ACTION_INDEX);
        if ($checkIndex['success'] == false) {
            if ($task->getObjectUuid() != '') {
                $controller = AclHelper::__getControllerByUuid($task->getObjectUuid());
                $checkIndex = AclHelper::__checkPermissionDetailGms($controller, AclHelper::ACTION_MANAGE_TASK);
            }
        }
        if ($checkIndex['success'] == false) {
            exit(json_encode($checkIndex));
        }
    }

    /**
     * @param $relocation_uuid
     * @return mixed
     */
    public function getAllTasksServicesListAction($relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($relocation_uuid != '') {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->checkCompany()) {
                $results = $relocation->getAllTasksOfServices($relocation->getId());
                $this->response->setJsonContent($results);
                return $this->response->send();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function bulkAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $task_uuid = Helpers::__getRequestValue("uuid");

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT', 'detail' => $task_uuid];

        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {

            $task = Task::findFirstByUuid($task_uuid);

            if (!($task && $task->belongsToGms() && $task->checkMyEditPermission())) {
                $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
                goto end_of_function;
            }
            $this->checkPermissionEditTask($task);

            $this->db->begin();
            $update = false;

            /*** add progress **/
            if (Helpers::__existRequestValue("progress")) {
                $progress = intval(Helpers::__getRequestValue("progress"));
                $isFinalReviewPosting = intval(Helpers::__getRequestValue("is_final_review"));

                if ($progress >= 0) {
                    $task->setProgress($progress);
                    $update = true;
                }

                if($isFinalReviewPosting == ModelHelper::YES && $progress == Task::STATUS_AWAITING_FINAL_REVIEW){

                }else{
                    if($task->getTaskType() == Task::TASK_TYPE_EE_TASK
                        && $task->getIsFinalReview() == ModelHelper::NO
                        && $progress == Task::STATUS_AWAITING_FINAL_REVIEW)
                    {
                        $update = false;
                        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
                        goto end_of_function;
                    }
                }
            }

            /*** add is_final_review **/
            if (Helpers::__existRequestValue("is_final_review")) {
                $is_final_review = intval(Helpers::__getRequestValue("is_final_review"));

                if ($is_final_review >= 0) {
                    $task->setIsFinalReview($is_final_review);
                    $update = true;
                }

                if ($is_final_review == 0 && $task->getProgress() == TaskExt::STATUS_AWAITING_FINAL_REVIEW) {
                    $task->setProgress(TaskExt::STATUS_IN_PROCESS);
                }
            }

            /*** add is_flag **/
            if (Helpers::__existRequestValue("is_flag")) {
                $is_flag = intval(Helpers::__getRequestValue("is_flag"));

                if ($is_flag >= 0) {
                    $task->setIsFlag($is_flag);
                    $update = true;
                }
            }

            /*** add is_priority **/
            if (Helpers::__existRequestValue("is_priority")) {
                $is_priority = intval(Helpers::__getRequestValue("is_priority"));

                if ($is_priority >= 0) {
                    $task->setIsPriority($is_priority);
                    $update = true;
                }
            }

            if (Helpers::__existRequestValue("is_milestone")) {
                $is_milestone = intval(Helpers::__getRequestValue("is_milestone"));

                if ($is_milestone >= 0) {
                    $task->setIsMilestone($is_milestone);
                    $update = true;
                }
            }

            /*** add date due at **/
            $due_at = Helpers::__getRequestValue("due_at");
            if ($due_at != '' && Helpers::__isDate($due_at, "Y-m-d")) {
                $task->setDueAt($due_at);
                $update = true;
            }
            /*** add date due at **/
            $started_at = Helpers::__getRequestValue("started_at");
            if ($started_at != '' && Helpers::__isDate($started_at, "Y-m-d")) {
                $task->setStartedAt($started_at);
                $update = true;
            }
            /*** add date due at **/
            $ended_at = Helpers::__getRequestValue("ended_at");
            if ($ended_at != '' && Helpers::__isDate($ended_at, "Y-m-d")) {
                $task->setEndedAt($ended_at);
                $update = true;
            }

            /*** add reporter profile **/
            $report_user_profile_uuid = Helpers::__getRequestValue("report_user_profile_uuid");
            if ($report_user_profile_uuid != '' && Helpers::__isValidUuid($report_user_profile_uuid)) {
                $profile = UserProfile::findFirstByUuid($report_user_profile_uuid);
                $reporter = DataUserMember::getDataReporter($task_uuid);

                if (($profile && $reporter && $profile->getUuid() != $reporter->getUuid() && $profile->belongsToGms())) {
                    $resultDeleteReporter = DataUserMember::deleteReporters($task_uuid);
                    if ($resultDeleteReporter['success'] == false) {
                        $return = [
                            'success' => false,
                            'message' => 'SET_REPORTER_FAIL_TEXT',
                            'detail' => $resultDeleteReporter
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }

                    $resultAddReporter = DataUserMember::addReporter($task_uuid, $profile, "object");
                    if ($resultAddReporter['success'] == false) {
                        $return = [
                            'success' => false,
                            'message' => 'SET_REPORTER_FAIL_TEXT',
                            'detail' => $resultAddReporter
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $return = [
                        "success" => true,
                        "message" => "TASK_EDIT_SUCCESS_TEXT"
                    ];
                } elseif ($profile && !$reporter && $profile->belongsToGms()) {
                    $resultAddReporter = DataUserMember::addReporter($task_uuid, $profile, "object");
                    if ($resultAddReporter['success'] == false) {
                        $return = [
                            'success' => false,
                            'message' => 'SET_REPORTER_FAIL_TEXT',
                            'detail' => $resultAddReporter
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                } else {
                    $return = [
                        "success" => true,
                        "message" => "TASK_EDIT_SUCCESS_TEXT"
                    ];
                }
            }
            /** @var fix bug $owner_user_profile_uuid */
            $owner_user_profile_uuid = Helpers::__getRequestValue("owner_user_profile_uuid");
            if ($owner_user_profile_uuid != '' && Helpers::__isValidUuid($owner_user_profile_uuid)) {
                $update = true;
                $profile = UserProfile::findFirstByUuid($owner_user_profile_uuid);
                $owner = DataUserMember::getDataOwner($task_uuid);
                if (!$profile || !$profile->belongsToGms()) {
                    $return = [
                        'success' => false,
                        'params' => $owner,
                        'message' => 'MEMBER_NOT_FOUND_TEXT',
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }


                if ($profile && $owner && $profile->getUuid() == $owner->getUuid()) {
                    $return = ['success' => true, 'message' => 'NO_CHANGE_OF_OWNER_TEXT'];
                    $this->db->rollback();
                    goto end_of_function;
                }


                //delete current owner
                $returnDeleteOwner = DataUserMember::deleteOwners($task_uuid);

                if ($returnDeleteOwner['success'] == false) {
                    $return = [
                        'success' => false,
                        'params' => $returnDeleteOwner,
                        'message' => 'SET_OWNER_FAIL_TEXT',
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                //add new owner
                $returnAddOwner = DataUserMember::addOwner($task_uuid, $profile, DataUserMember::MEMBER_TYPE_OBJECT_TEXT);
                if ($returnAddOwner['success'] == false) {
                    $return = [
                        'success' => false,
                        'resultDelete' => $returnDeleteOwner,
                        'params' => $returnAddOwner,
                        'message' => 'SET_OWNER_FAIL_TEXT',
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }
                $return = [
                    "success" => true,
                    "message" => "TASK_EDIT_SUCCESS_TEXT"
                ];
//                $update_reminder_counter_queue = RelodayQueue::__getQueueUpdateReminderCounter();
//                $dataArray = [
//                    'action' => "update_reminder_counter",
//                    'object_uuid' => $task_uuid,
//                    'old_owner_uuid' => $owner->getUuid(),
//                    'new_owner_uuid' => $profile->getUuid()
//                ];
//                $return['update_reminder_counter'] = $update_reminder_counter_queue->addQueue($dataArray);

                $task->setOwnerId($profile->getId());
                $update = true;

            }

            /*** add viewer profile **/
            $viewers = Helpers::__getRequestValue("viewers");
            if (is_array($viewers) && count($viewers) > 0) {
                foreach ($viewers as $viewer) {
                    if ($viewer != '' && Helpers::__isValidUuid($viewer)) {
                        $user = UserProfile::findFirstByUuid($viewer);

                        if ($user && ($user->belongsToGms() || $user->manageByGms())) {
                            $data_user_member = DataUserMember::findFirst([
                                "conditions" => "object_uuid = :object_uuid: AND user_profile_uuid = :user_profile_uuid:",
                                'bind' => [
                                    'object_uuid' => $task_uuid,
                                    'user_profile_uuid' => $user->getUuid(),
                                ]
                            ]);

                            if (!$data_user_member) {
                                $returnCreate = DataUserMember::addViewer($task_uuid, $user);

                                if ($returnCreate['success'] == false) {
                                    $return = ['success' => false, 'data' => $user, 'message' => 'VIEWER_ADDED_FAIL_TEXT', 'detail' => $returnCreate];
                                    $this->db->rollback();
                                    goto end_of_function;
                                }
                                $return = [
                                    "success" => true,
                                    "message" => "TASK_EDIT_SUCCESS_TEXT"
                                ];
                            } else {
                                $return = [
                                    "success" => true,
                                    "message" => "TASK_EDIT_SUCCESS_TEXT"
                                ];
                            }
                        }
                    }
                }
            }

            /*** need update data **/
            if ($update) {
                $return = $task->__quickUpdate();
                if ($return['success'] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }

                $refreshTask = Task::findFirstByUuid($task_uuid);
                $pollingCount = 1;
                while ($refreshTask->getUpdatedAt() != $task->getUpdatedAt() && $pollingCount < 10) {
                    $pollingCount++;
                    $refreshTask = Task::findFirstByUuid($task_uuid);
                }
                $resultSaveEntity = $task->updateServiceProgress();
                $updateProgress = [];
                if ($resultSaveEntity['success'] && $task->getObjectUuid()) {
                    PushHelper::__sendPushReload($task->getObjectUuid());
                    $serviceRelocationCompany = RelocationServiceCompany::__findFirstByUuidCache($task->getObjectUuid());
                    if ($serviceRelocationCompany && method_exists($serviceRelocationCompany, 'startUpdateEntityProgress')) {
                        $updateProgress = $serviceRelocationCompany->startUpdateEntityProgress();
                    }
                }

                $return = [
                    'success' => true,
                    'updateProgress' => $updateProgress,
                    'entity_progress' => $resultSaveEntity,
                    'message' => 'TASK_EDIT_SUCCESS_TEXT',
                    'data' => $task,
                ];
            }
            $this->db->commit();
        }
        end_of_function:

        if ($return['success'] == true && isset($task) && $task) {
            ModuleModel::$task = $task;
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_UPDATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createSubTaskAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();
        $object_uuid = null;

        $taskUuid = Helpers::__getRequestValue('task_uuid') && Helpers::__getRequestValue('task_uuid') != '' && is_string(Helpers::__getRequestValue('task_uuid')) ? Helpers::__getRequestValue('task_uuid') : null;
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $mainTask = Task::__getTaskByUuid($taskUuid);

        if (Helpers::__isValidUuid($taskUuid) && $mainTask) {
            $custom_data = Helpers::__getRequestValuesArray();
            $custom_data['task_id'] = $mainTask->getId();
            $custom_data['object_uuid'] = $mainTask->getUuid();


            $task = new TaskChecklist();
            $task->setData($custom_data);
            $resultTask = $task->__quickCreate();

            if ($resultTask['success'] == true) {
                $this->dispatcher->setParam('subtask_name', $task->getName());
                $return = [
                    'success' => true,
                    'message' => 'TASK_UPDATE_SUCCESS_TEXT',
                    'data' => $task,
                ];
            } else {
                $return = $resultTask;
            }
        }

        end:

        if ($return['success'] == true && isset($mainTask) && $mainTask && isset($task) && $task) {
            ModuleModel::$task = $mainTask;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($mainTask, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_CREATE_SUBTASK, [
                'subtask_name' => $task->getName()
            ]);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $object_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getSubTasksAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $taskUuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'data' => [], 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($taskUuid != '' && Helpers::__isValidUuid($taskUuid)) {
            $tasks = TaskChecklist::find([
                "conditions" => 'object_uuid = :object_uuid:',
                "bind" => [
                    'object_uuid' => $taskUuid
                ],
                'order' => 'sequence ASC'
            ]);

            $taskArr = [];
            foreach ($tasks as $task) {
                $taskArr[] = $task->parsedDataToArray();
            }

            $return = ['success' => true, 'data' => $taskArr];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param string $task_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteSubTaskAction($subTaskUuid = "")
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if (isset($subTaskUuid) && $subTaskUuid != '') {

            $childTask = TaskChecklist::findFirstByUuid($subTaskUuid);

            if ($childTask instanceof TaskChecklist) {

                $mainTask = Task::__getTaskByUuid($childTask->getObjectUuid());
                $this->dispatcher->setParam('subtask_name', $childTask->getName());

                $this->checkPermissionEditTask($mainTask);

                $taskDeletedObjectUuid = $childTask->getObjectUuid();
                $resultRemove = $childTask->__quickRemove();
                if ($resultRemove['success'] == true) {
                    $return = [
                        'uuid' => $taskDeletedObjectUuid,
                        'check_pusher' => isset($returnPusher) ? $returnPusher : false,
                        'success' => true,
                        'message' => 'TASK_DELETE_SUCCESS_TEXT'
                    ];
                } else {
                    $return = ['success' => false, 'message' => 'TASK_DELETE_FAIL_TEXT'];
                }
            }
        }
        end:

        if ($return['success'] == true && isset($mainTask) && $mainTask) {
            ModuleModel::$task = $mainTask;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($mainTask, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_DELETE_SUBTASK);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed save task Action
     */
    public function updateSubTaskAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $subTaskUuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (isset($subTaskUuid) && $subTaskUuid != '' && Helpers::__isValidUuid($subTaskUuid)) {
            $childTask = TaskChecklist::findFirstByUuid($subTaskUuid);

            if ($childTask instanceof TaskChecklist) {

                $mainTask = Task::__getTaskByUuid($childTask->getObjectUuid());
                $this->checkPermissionEditTask($mainTask);

                $childTask->setName(Helpers::__getRequestValue('name'));
                $childTask->setProgress(Helpers::__getRequestValue('progress') != null ? Helpers::__getRequestValue('progress') : 1);
                $result = $childTask->__quickUpdate();

                if ($result['success'] == true) {
                    $this->dispatcher->setParam('subtask_name', $childTask->getName());
                    $return = [
                        'uuid' => $childTask->getObjectUuid(),
                        'success' => true,
                        'message' => 'TASK_UPDATE_SUCCESS_TEXT'
                    ];
                } else {
                    $return = ['success' => false, 'message' => 'TASK_UPDATE_FAIL_TEXT'];
                }
            }
        }

        end_of_function:

        if ($return['success'] == true && isset($mainTask) && $mainTask) {
            ModuleModel::$task = $mainTask;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($mainTask, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_UPDATE_SUBTASK);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * pre Create Option
     */
    public function preCreateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $taskUuid = Helpers::__uuid();
        $createNewObjectMap = RelodayObjectMapHelper::__createObject(
            $taskUuid,
            RelodayObjectMapHelper::TABLE_TASK,
            false
        );

        $resultAddCreator = DataUserMember::addCreator($taskUuid, ModuleModel::$user_profile, RelodayObjectMapHelper::TABLE_TASK);

        $return = [
            'success' => true,
            '$resultAddCreator' => $resultAddCreator,
            'data' => $taskUuid,
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Update sequence of task
     */
    public function setSequenceOfItemsAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $itemIds = Helpers::__getRequestValue('itemIds');

        $i = 1;
        foreach ($itemIds as $id) {
            $task = Task::findFirstById($id);

            $this->checkPermissionEditTask($task);

            if (!$task instanceof Task) {
                goto end_of_function;
            }
            $task->setSequence($i);
            $resultUpdate = $task->__quickUpdate();

            if (!$resultUpdate['success']) {
                $return = $resultUpdate;
                goto  end_of_function;
            }
            $i++;
        }

        $return['success'] = true;
        $return['message'] = 'ORDER_SUCCESSFULLY_TEXT';

        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function changeTaskFinalReviewAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        $is_final_review = Helpers::__getRequestValue('is_final_review');

        $task = Task::findFirstByUuid($uuid);
        if (!$task) {
            goto end_of_function;
        }
        $task->setIsFinalReview((int)$is_final_review);
        $return = $task->__quickUpdate();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return void
     */
    public function generateAssigneeTasksAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $objectUuid = Helpers::__getRequestValue('object_uuid');
        $isGenerate = Helpers::__getRequestValue('isGenerate');

        $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($objectUuid);
        if (!$relocationServiceCompany) {
            goto end_of_function;
        }

        $relocation = $relocationServiceCompany->getRelocation();
        $serviceCompany = $relocationServiceCompany->getServiceCompany();
        $count = 0;

        $this->db->begin();
        //TABLE task_template ee
        $tasksAssigneeList = $serviceCompany->getTasksTemplateAssignee();
        $lastObject = false;
        if (isset($isGenerate) && $isGenerate == true &&
            $tasksAssigneeList->count() > 0 &&
            $relocationServiceCompany->getActiveAssigneeTask() == ModelHelper::NO) {
            $owner = ModuleModel::$user_profile;

            foreach ($tasksAssigneeList as $taskTemplateItem) {
                $count++;
                $custom = $taskTemplateItem->toArray();
                $custom['relocation_service_company_id'] = $relocationServiceCompany->getId();
                $custom['link_type'] = TaskExt::LINK_TYPE_SERVICE;
                $custom['object_uuid'] = $relocationServiceCompany->getUuid();
                $custom['company_id'] = ModuleModel::$company->getId();
                $custom['owner_id'] = ModuleModel::$user_profile->getId(); //get owner of task
                $custom['number'] = TaskExt::__generateNumber(ModuleModel::$company->getId(), TaskExt::LINK_TYPE_SERVICE);
                $custom['sequence'] = $taskTemplateItem->getSequence();

                $taskObject = new Task();
                $taskObject->setData($custom);
                $taskObject->setRelocationServiceCompanyId($relocationServiceCompany->getId());
                $taskObject->setRelocationId($relocation->getId());
                $taskObject->setAssignmentId($relocation->getAssignmentId());
                $taskObject->setEmployeeId($relocation->getEmployeeId());

                $taskResult = $taskObject->__quickCreate();

                if ($taskResult['success'] == true) {
                    //add owner
                    if ($owner) {
                        DataUserMember::__addOwnerToObject($taskObject->getUuid(), $owner, 'task', ModuleModel::$company);
                    }
                    //Task Files
                    $taskFiles = $taskTemplateItem->getTaskFiles();
                    if (count($taskFiles) > 0) {
                        foreach ($taskFiles as $taskFile) {
                            $itemTaskFile = $taskFile->toArray();
                            unset($itemTaskFile['created_at']);
                            unset($itemTaskFile['updated_at']);
                            unset($itemTaskFile['uuid']);
                            unset($itemTaskFile['object_uuid']);
                            unset($itemTaskFile['id']);

                            $newTaskFile = new \Reloday\Application\Models\TaskFileExt();
                            $newTaskFile->setData($itemTaskFile);
                            $newTaskFile->setUuid(Helpers::__uuid());
                            $newTaskFile->setObjectUuid($taskObject->getUuid());

                            $resultTaskFile = $newTaskFile->__quickSave();

                            if (!$resultTaskFile['success']) {
                                $return = $resultTaskFile;
                                $return['data'] = $newTaskFile;
                                $return['message'] = 'TASK_GENERATE_FAILED_TEXT';
                                $this->db->rollback();
                                goto end_of_function;
                            }
                        }
                    }

                    // Service status
                    $lastObject = $taskObject;
                } else {
                    $this->db->rollback();
                    $return = [
                        'success' => false,
                        'message' => 'TASK_GENERATE_FAILED_TEXT'
                    ];
                    goto end_of_function;
                }
            }
        }


        $relocationServiceCompany->setActiveAssigneeTask(ModelHelper::YES);
        $resultRsc = $relocationServiceCompany->__quickUpdate();

        if (!$resultRsc['success']) {
            $return = $resultRsc;
            $return['message'] = 'TASK_GENERATE_FAILED_TEXT';
            $this->db->rollback();
            goto end_of_function;
        }

        $this->db->commit();
        $return['success'] = true;
        $return['message'] = 'TASK_GENERATE_SUCCESS_TEXT';

        if($lastObject){
            $resultUpdateProgress = $lastObject->updateServiceProgress();
            $return['$resultUpdateProgress'] = $resultUpdateProgress;
        }


        end_of_function:
        if($return['success']){
            ModuleModel::$relocationServiceCompany = $relocationServiceCompany;
            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('task_count', $count);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function setPriorityAction(string $task_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
        $is_priority = Helpers::__getRequestValue('is_priority');

        if (!($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) || !isset($is_priority)) {
            goto end_of_function;
        }
        $task = Task::findFirstByUuid($task_uuid);
        if (!($task && $task->belongsToGms())) {
            goto end_of_function;
        }
        $this->checkPermissionEditTask($task);
        if ($task->checkMyEditPermission()) {
            if (!is_numeric($is_priority)) {
                goto end_of_function;
            }
            $task->setIsPriority($is_priority);
            $resultSave = $task->__quickUpdate();
            if ($resultSave['success']) {
                $return = [
                    'success' => true,
                    'message' => 'TASK_EDIT_SUCCESS_TEXT',
                    'data' => $task,
                ];
            } else {
                $return['detail'] = $task;
            }
        }
        end_of_function:

        if($return['success'] && isset($task)){
            ModuleModel::$task = $task;
            $this->dispatcher->setParam('return', $return);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function setFlagAction(string $task_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
        $is_flag = Helpers::__getRequestValue('is_flag');

        if (!($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) || !isset($is_flag)) {
            goto end_of_function;
        }
        $task = Task::findFirstByUuid($task_uuid);
        if (!($task && $task->belongsToGms())) {
            goto end_of_function;
        }
        $this->checkPermissionEditTask($task);
        if ($task->checkMyEditPermission()) {
            if (!is_numeric($is_flag)) {
                goto end_of_function;
            }
            $task->setIsFlag($is_flag);
            $resultSave = $task->__quickUpdate();
            if ($resultSave['success']) {
                $return = [
                    'success' => true,
                    'message' => 'TASK_EDIT_SUCCESS_TEXT',
                    'data' => $task,
                ];
            } else {
                $return['detail'] = $task;
            }
        }
        end_of_function:

        if($return['success'] && isset($task)){
            ModuleModel::$task = $task;
            $this->dispatcher->setParam('return', $return);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function setMilestoneAction(string $task_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl('manage_milestone', $this->router->getControllerName());

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_TASK_TEXT'];
        $is_milestone = Helpers::__getRequestValue('is_milestone');

        if (!($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) || !isset($is_milestone)) {
            goto end_of_function;
        }
        $task = Task::findFirstByUuid($task_uuid);
        if (!($task && $task->belongsToGms())) {
            goto end_of_function;
        }
        $this->checkPermissionEditTask($task);
        if ($task->checkMyEditPermission()) {
            if (!is_numeric($is_milestone)) {
                goto end_of_function;
            }
            $task->setIsMilestone($is_milestone);
            $resultSave = $task->__quickUpdate();
            if ($resultSave['success']) {
                $return = [
                    'success' => true,
                    'message' => 'TASK_EDIT_SUCCESS_TEXT',
                    'data' => $task,
                ];
            } else {
                $return['detail'] = $task;
            }
        }
        end_of_function:

        if($return['success'] && isset($task)){
            ModuleModel::$task = $task;
            $this->dispatcher->setParam('return', $return);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getRecentTasksAction(){
        $this->view->disable();
        $this->checkAjaxPost();

        $limit = Helpers::__getRequestValue('limit');

        $return = Task::__getRecentTasks([
            'limit' => $limit ?: 10,
        ]);


        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
