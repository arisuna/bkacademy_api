<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Behavior\ObjectCacheBehavior;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\TextHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\TaskTemplateExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController as BaseController;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\ServiceEvent;
use Reloday\Gms\Models\Event;
use \Reloday\Gms\Models\Task;
use \Reloday\Gms\Models\TaskTemplate;
use \Reloday\Gms\Models\TaskTemplateReminder;
use \Reloday\Gms\Models\ServiceCompany;
use \Reloday\Gms\Models\TaskFile;
use \Reloday\Gms\Models\UserProfile as UserProfile;
use \Reloday\Gms\Models\DataUserMember as DataUserMember;
use \Reloday\Gms\Models\ModuleModel as ModuleModel;
use \Reloday\Gms\Models\ObjectMap;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Workflow;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TaskTemplateController extends BaseController
{
    /**
     * @return mixed
     */
    public function getAction($task_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($task_uuid != '') {
            $task = TaskTemplate::findFirstByUuid($task_uuid);

            if ($task && $this->checkMyPermissionView($task) == true) {

                $dataTask = $task->toArray();
                if ($task->getHasFile() == 1) {
                    $taskFiles = [];
                    $files = $task->getFiles() ? $task->getFiles() : [];
                    foreach ($files as $file) {
                        $item = $file->parsedDataToArray();
                        $item['isEdit'] = false;
                        $taskFiles[] = $item;
                    }
                    $dataTask['task_files'] = $taskFiles;
                }
                $dataTask['reminders'] = $task->getReminders();

                $return = [
                    'success' => true,
                    'data' => $dataTask,
                ];
            }
        }

        end_of_function:
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
        // $this->checkAclCreate();

        $taskUuid = Helpers::__uuid();
//        $createNewObjectMap = RelodayObjectMapHelper::__createObject(
//            $taskUuid,
//            RelodayObjectMapHelper::TABLE_TASK,
//            false
//        );
//
//        $resultAddCreator = DataUserMember::addCreator($taskUuid, ModuleModel::$user_profile, RelodayObjectMapHelper::TABLE_TASK);

        $return = [
            'success' => true,
            'data' => $taskUuid,
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Create Action
     * @throws \Phalcon\Security\Exception
     * @throws \Exception
     */
    public function attachFileAction()
    {

        $this->view->disable();
        $this->checkAjaxPutPost();
        // $this->checkAclCreate(self::CONTROLLER_NAME);

        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        $attachment = Helpers::__getRequestValueAsArray('attachment');

        /** CHECK DRAG AND DROP MEDIA ($attachments is media uuid)*/
        if (Helpers::__isValidUuid($attachment['uuid'])) {
            $media = Media::findFirstByUuid($attachment['uuid']);
        }

        $fileNames = '';
        if ($uuid != '' &&
            Helpers::__isValidUuid($uuid) &&
            $media instanceof Media) {
            $this->db->begin();

            /** REMOVE IF EXISTED */
            $existedFile = TaskFile::__getFileByObjectUuid($uuid);
            if ($existedFile) {
                $delete = $existedFile->__quickRemove();
                if ($delete['success'] == false) {
                    $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
                    goto end_of_function;
                }
            }

            $fileUuid = Helpers::__uuid();
            $taskFile = new TaskFile();

            $taskFile->setUuid($fileUuid);
            $taskFile->setFileName($media->getFileName());
            $taskFile->setFileExtension($media->getFileExtension());
            $taskFile->setFileType($media->getFileType());
            $taskFile->setMimeType($media->getMimeType());
            $taskFile->setName($media->getName());
            $taskFile->setObjectUuid($uuid);
            $taskFile->setCompanyUuid(ModuleModel::$company->getUuid());
            $taskFile->addDefaultFilePath();

            $return = $taskFile->__quickCreate();

            if (!$return['success']) {
                $this->db->rollback();
                goto end_of_function;
            }

            $this->db->commit();
            $return ['message'] = 'ATTACH_SUCCESS_TEXT';
            goto end_of_function;
        }

        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createTaskTemplateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = ['success' => false, 'message' => 'OBJECT_NOT_FOUND_TEXT'];

        $object_uuid = Helpers::__getRequestValue('object_uuid');
        $label = Helpers::__getRequestValue("label");
        $service = new ServiceCompany();

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $taskItem = Helpers::__getRequestValuesArray();
            $taskItem['description'] = rawurldecode(base64_decode($taskItem['description']));
            $tasks = TaskTemplate::__findWithFilters([
                'object_uuid' => $object_uuid,
                'task_type' => $taskItem['task_type'] ?: 1
            ]);

            $sequence = 1;
            if ($tasks['success'] !== false && count($tasks['data']) > 0) {
                $sequence = count($tasks['data']) + 1;
            }
            $task = new TaskTemplate();
            $task->setData($taskItem);
            $task->setSequence($sequence);
            if ($label == "SERVICE" || $label == "SERVICES") {
                $this->checkAclCreate(AclHelper::CONTROLLER_SERVICE);
                $service = ServiceCompany::findFirstByUuid($object_uuid);
                if (!$service instanceof ServiceCompany || !$service->belongsToGms()) {
                    goto end_of_function;
                }
                $task->setObjectType(TaskTemplateExt::SERVICE_TYPE);
            } else if ($label == "ASSIGNMENT_WORKFLOW" || $label == "RELOCATION_WORKFLOW") {
                $this->checkAclUpdate(AclHelper::CONTROLLER_WORKFLOW);
                $workflow = Workflow::findFirstByUuid($object_uuid);
                if (!$workflow instanceof Workflow || !$workflow->belongsToGms()) {
                    goto end_of_function;
                }
                if ($label == "ASSIGNMENT_WORKFLOW") {
                    $task->setObjectType(TaskTemplateExt::ASSIGNMENT_WORKFLOW_TYPE);
                } else {
                    $task->setObjectType(TaskTemplateExt::RELOCATION_WORKFLOW_TYPE);
                }
            }

            $this->db->begin();
            $resultTask = $task->__quickCreate();

            if (!$resultTask['success']) {
                $this->db->rollback();
                $return = $resultTask;
                $return['message'] = 'TASK_TEMPLATE_CREATE_FAILED_TEXT';
                goto end_of_function;
            }
            if (isset($taskItem['reminders']) && count($taskItem['reminders'])) {
                foreach ($taskItem['reminders'] as $reminder) {
                    $task_template_reminder = new TaskTemplateReminder();
                    $task_template_reminder->setObjectUuid($task->getUuid());
                    $task_template_reminder->setData($reminder);
                    $resultTaskTemplateReminder = $task_template_reminder->__quickCreate();

                    if (!$resultTaskTemplateReminder['success']) {
                        $return = $resultTaskTemplateReminder;
                        $this->db->rollback();
                        $return['message'] = 'TASK_TEMPLATE_CREATE_FAILED_TEXT';
                        goto end_of_function;
                    }

                    $task->beforeCreate();
                }
            }
            if (isset($taskItem['task_files']) && count($taskItem['task_files'])) {
                foreach ($taskItem['task_files'] as $item) {

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

                    if ($item['is_request_file'] == 1) {
                        $taskFile->setIsRequestFile(ModelHelper::YES);
                    } else {
                        $taskFile->setIsRequestFile(ModelHelper::NO);
                    }

                    $taskFile->setMediaUuid($item['media']['uuid']);

                    $resultTaskFile = $taskFile->__quickCreate();

                    if (!$resultTaskFile['success']) {
                        $return = $resultTaskFile;
                        $this->db->rollback();
                        $return['message'] = 'TASK_TEMPLATE_CREATE_FAILED_TEXT';
                        goto end_of_function;
                    }
                }
            }

            $this->db->commit();
            $return = [
                'success' => true,
                'data' => $task,
                'message' => 'TASK_TEMPLATE_CREATE_SUCCESS_TEXT',
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function updateTaskTemplateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();


        $uuid = Helpers::__getRequestValue('uuid');

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        $taskItem = Helpers::__getRequestValuesArray();
        $taskItem['description'] = rawurldecode(base64_decode($taskItem['description']));
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $task = TaskTemplate::findFirstByUuid($uuid);
            if (!$task instanceof TaskTemplate || !$this->checkMyPermissionEdit($task)) {
                goto end_of_function;
            }
            if ($taskItem['name'] == '' || $taskItem['name'] == null) {
                unset($taskItem['name']);
            }
            $task->setData($taskItem);
            $this->db->begin();
            $resultTask = $task->__quickUpdate();

            if ($resultTask['success'] == false) {
                $this->db->rollback();
                $return = $resultTask;
                $return['message'] = 'TASK_TEMPLATE_UPDATE_FAILED_TEXT';
                goto end_of_function;
            }
            $this->db->commit();
            $return = [
                'success' => true,
                'data' => $task,
                'message' => 'TASK_TEMPLATE_UPDATE_SUCCESS_TEXT',
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function deleteTaskTemplateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $uuid = Helpers::__getRequestValue('uuid');

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        $taskItem = Helpers::__getRequestValuesArray();
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $task = TaskTemplate::findFirstByUuid($uuid);
            if (!$task instanceof TaskTemplate || !$this->checkMyPermissionEdit($task)) {
                goto end_of_function;
            }

            $this->db->begin();
            $resultTask = $task->__quickRemove();

            if ($resultTask['success'] == false) {
                $this->db->rollback();
                $return = $resultTask;
                $return['message'] = 'TASK_TEMPLATE_DELETE_FAILED_TEXT';
                goto end_of_function;
            }
            $this->db->commit();
            $return = [
                'success' => true,
                'data' => $task,
                'message' => 'TASK_TEMPLATE_DELETE_SUCCESS_TEXT',
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function addReminderAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();


        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $task = TaskTemplate::findFirstByUuid($uuid);
            if (!$task instanceof TaskTemplate || !$this->checkMyPermissionEdit($task)) {
                goto end_of_function;
            }

            $reminderItem = Helpers::__getRequestValuesArray();

            $task_template_reminder = new TaskTemplateReminder();
            $task_template_reminder->setObjectUuid($task->getUuid());
            $task_template_reminder->setData($reminderItem);
            $resultTaskTemplateReminder = $task_template_reminder->__quickCreate();

            if ($resultTaskTemplateReminder['success'] == false) {
                $return = $resultTaskTemplateReminder;
                $return['message'] = 'REMINDER_CREATE_FAILED_TEXT';
                goto end_of_function;
            }

            $return = [
                'success' => true,
                'data' => $task,
                'message' => 'REMINDER_CREATE_SUCCESS_TEXT',
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function deleteReminderAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();


        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $task_template_reminder = TaskTemplateReminder::findFirstByUuid($uuid);
            if (!$task_template_reminder instanceof TaskTemplateReminder) {
                goto end_of_function;
            }
            $task = $task_template_reminder->getTaskTemplate();
            if (!$task instanceof TaskTemplate || !$this->checkMyPermissionEdit($task)) {
                goto end_of_function;
            }
            $resultTaskTemplateReminder = $task_template_reminder->__quickRemove();

            if ($resultTaskTemplateReminder['success'] == false) {
                $return = $resultTaskTemplateReminder;
                $return['message'] = 'REMINDER_DELETE_FAILED_TEXT';
                goto end_of_function;
            }

            $return = [
                'success' => true,
                'data' => $task,
                'message' => 'REMINDER_DELETE_SUCCESS_TEXT',
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Update sequence of task templates
     */
    public function setSequenceOfItemsAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $itemIds = Helpers::__getRequestValue('itemIds');

        $i = 1;
        foreach ($itemIds as $id) {
            $task = TaskTemplate::findFirstById($id);
            if (!$task instanceof TaskTemplate || !$this->checkMyPermissionEdit($task)) {
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

    public function checkMyPermissionEdit(TaskTemplate $task): bool
    {
        if ($task->getObjectType() == TaskTemplateExt::SERVICE_TYPE) {
            $this->checkAclCreate(AclHelper::CONTROLLER_SERVICE);
            $service = ServiceCompany::findFirstByUuid($task->getObjectUuid());
            if (!$service instanceof ServiceCompany || !$service->belongsToGms()) {
                return false;
            }

            return true;
        }

        $this->checkAclUpdate(AclHelper::CONTROLLER_WORKFLOW);
        $workflow = Workflow::findFirstByUuid($task->getObjectUuid());
        if (!$workflow instanceof Workflow || !$workflow->belongsToGms()) {
            return false;
        }

        return true;
    }

    public function checkMyPermissionView(TaskTemplate $task): bool
    {
        if ($task->getObjectType() == TaskTemplateExt::SERVICE_TYPE) {
            $this->checkAclIndex(AclHelper::CONTROLLER_SERVICE);
            $service = ServiceCompany::findFirstByUuid($task->getObjectUuid());
            if (!$service instanceof ServiceCompany || !$service->belongsToGms()) {
                return false;
            }

            return true;
        }

        $this->checkAclIndex(AclHelper::CONTROLLER_WORKFLOW);
        $workflow = Workflow::findFirstByUuid($task->getObjectUuid());
        if (!$workflow instanceof Workflow || !$workflow->belongsToGms()) {
            return false;
        }

        return true;
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function listTaskTemplatesFromObjectAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        $params = [];
        $params['object_uuid'] = $uuid;
        $params['has_file'] = Helpers::__getRequestValue('has_file');
        $params['task_type'] = Helpers::__getRequestValue('task_type');
        $params['query'] = Helpers::__getRequestValue('query');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $object = Workflow::findFirstByUuid($uuid);
            if (!$object) {
                $service = ServiceCompany::findFirstByUuid($uuid);
            }

            if (!$object) {
                goto end_of_function;
            }

            if ($object) {
                $tasks = TaskTemplate::__findWithFilters($params);
                $tasks_arr = [];
                if ($tasks['success'] && count($tasks['data']) > 0) {
                    foreach ($tasks['data'] as $task) {
//                        $taskItem = $task->toArray();
                        $taskItem = $task;
                        $taskItem['tmp_id'] = $task['id'];

                        $reminderTemplate = TaskTemplateReminder::find([
                            "conditions" => "object_uuid = :task_uuid:",
                            'bind' => [
                                'task_uuid' => $task['uuid'] ?: '',
                            ]
                        ]);

                        if ($reminderTemplate) {
                            $taskItem['reminders'] = $reminderTemplate->toArray();

                            $i = 0;
                            foreach ($taskItem['reminders'] as $reminder) {
                                if ($reminder['service_event_id'] > 0) {
                                    $serviceEvent = ServiceEvent::findFirstById($reminder['service_event_id']);
                                    $taskItem['reminders'][$i]['event_name'] = $serviceEvent->getLabel();
                                } else if ($reminder['event_id'] > 0) {
                                    $event = Event::findFirstById($reminder['event_id']);
                                    $taskItem['reminders'][$i]['event_name'] = $event->getLabel();
                                }
                                $i++;
                            }
                        }

                        $tasks_arr[] = $taskItem;
                    }
                }
                $return = [
                    'success' => true,
                    'data' => ($tasks_arr)
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function changeTaskFinalReviewAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        $is_final_review = Helpers::__getRequestValue('is_final_review');

        $taskTemplate = TaskTemplate::findFirstByUuid($uuid);
        if (!$taskTemplate) {
            goto end_of_function;
        }
        $taskTemplate->setIsFinalReview((int)$is_final_review);
        $return = $taskTemplate->__quickUpdate();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function setMilestoneAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        $is_milestone = Helpers::__getRequestValue('is_milestone');

        if (!($uuid != '' && Helpers::__isValidUuid($uuid)) || !isset($is_milestone)) {
            goto end_of_function;
        }

        $task = TaskTemplate::findFirstByUuid($uuid);
        if (!$task instanceof TaskTemplate || !$this->checkMyPermissionEdit($task)) {
            goto end_of_function;
        }
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
            $return['message'] = 'TASK_TEMPLATE_UPDATE_FAILED_TEXT';
            $return['detail'] = $task;
        }
        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
