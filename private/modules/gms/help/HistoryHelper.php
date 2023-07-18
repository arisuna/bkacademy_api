<?php
/**
 * Created by PhpStorm.
 * User: ducvuhoang
 * Date: 11/05/2021
 * Time: 10:43
 */

namespace Reloday\Gms\Help;


use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Models\HistoryNotificationExt;
use Reloday\Application\Models\TaskExt;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HistoryAction;
use Reloday\Gms\Models\InvoiceQuote;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\SupportedLanguage;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\UserProfile;

class HistoryHelper
{
    public static function __getHistoryObject($lang = SupportedLanguage::LANG_EN, $customData = [])
    {
        if (count($customData) == 0) {
            $customData = Helpers::__getRequestValuesArray();
        }
        $object_uuid = Helpers::__getCustomValue('uuid', $customData);
        $action = Helpers::__getCustomValue('action', $customData) != null ? Helpers::__getCustomValue('action', $customData) : "HISTORY_UPDATE";
        $type = Helpers::__getCustomValue('type', $customData) != null ? Helpers::__getCustomValue('type', $customData) : History::TYPE_OTHERS;
        $taskInput = Helpers::__getCustomValue('task', $customData);
        $target_user_uuid = Helpers::__getCustomValue('target_user_uuid', $customData);
        $target_user = Helpers::__getCustomValue('target_user', $customData);
        $comment = Helpers::__getCustomValue('comment', $customData);
        $object = Helpers::__getCustomValue('object', $customData);
        $questionnaire = Helpers::__getCustomValue('questionnaire', $customData);
        $property = Helpers::__getCustomValue('property', $customData);
        $taskFile = Helpers::__getCustomValue('taskFile', $customData);
        $workflow_name = Helpers::__getCustomValue('workflow_name', $customData);
        $document_name = Helpers::__getCustomValue('document_name', $customData);
        $document_type = Helpers::__getCustomValue('document_type', $customData);
        $subtask_name = Helpers::__getCustomValue('subtask_name', $customData);
        $file_name = Helpers::__getCustomValue('file_name', $customData);

        $objectArray = [];
        if ($type == History::TYPE_OTHERS && $object_uuid != '') {
            $type = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
        }

        /*** force use task **/
        $taskObject = Task::findFirstByUuid($object_uuid);
        if ($taskObject) {
            $type = History::TYPE_TASK;
        }

        /*** end force use task **/

        if ($type == History::TYPE_TASK) {
            $mainObject = $taskObject;
            if ($mainObject && $mainObject instanceof Task) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                if(in_array($action, HistoryNotificationExt::$commentArray)){
                    $objectArray['frontend_state'] = $mainObject->getFrontendState("currentTab: 'comment'");
                }else{
                    $objectArray['frontend_state'] = $mainObject->getFrontendState();
                }
                $objectArray['number'] = $mainObject->getNumber();
                $objectArray['object_label'] = "TASK_TEXT";
            }
        } elseif ($type == History::TYPE_ASSIGNMENT) {
            $mainObject = Assignment::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof Assignment) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                if(in_array($action, HistoryNotificationExt::$commentArray)){
                    $objectArray['frontend_state'] = $mainObject->getFrontendState("currentTab: 'comment'");
                }else{
                    $objectArray['frontend_state'] = $mainObject->getFrontendState();
                }
                $objectArray['number'] = $mainObject->getReference();
                $objectArray['object_label'] = "ASSIGNMENT_TEXT";
            }
        } elseif ($type == History::TYPE_RELOCATION) {
            $mainObject = Relocation::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof Relocation) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                if(in_array($action, HistoryNotificationExt::$commentArray)){
                    $objectArray['frontend_state'] = $mainObject->getFrontendState("currentTab: 'comment'");
                }else{
                    $objectArray['frontend_state'] = $mainObject->getFrontendState();
                }
                $objectArray['number'] = $mainObject->getIdentify();
                $objectArray['object_label'] = "RELOCATION_TEXT";
            }
        } elseif ($type == History::TYPE_SERVICE) {
            $mainObject = RelocationServiceCompany::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof RelocationServiceCompany) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getSimpleFrontendUrl();
                $objectArray['number'] = $mainObject->getNumber();
                $objectArray['name'] = $mainObject->getServiceCompany()->getName();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['object_label'] = "SERVICE_TEXT";
            }
        } elseif ($type == History::TYPE_COMMENT) {
            if (isset($taskInput->uuid) && $taskInput->uuid != '') {
                $mainObject = Task::findFirstByUuid($taskInput->uuid);
                if ($mainObject && $mainObject instanceof Task) {
                    $objectArray['uuid'] = $mainObject->getUuid();
                    $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                    $objectArray['frontend_state'] = $mainObject->getFrontendState();
                    $objectArray['number'] = $mainObject->getNumber();
                    $objectArray['object_label'] = "TASK_TEXT";
                }
            }
        } elseif ($type == History::TYPE_INVOICE) {
            $mainObject = InvoiceQuote::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof InvoiceQuote) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                $objectArray['number'] = $mainObject->getReference();
                $objectArray['name'] = $mainObject->getReference();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['object_label'] = "SERVICE_TEXT";
            }
        } elseif ($type == History::TYPE_QUOTE) {
            $mainObject = InvoiceQuote::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof InvoiceQuote) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                $objectArray['number'] = $mainObject->getReference();
                $objectArray['name'] = $mainObject->getReference();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['object_label'] = "SERVICE_TEXT";
            }
        } else {
            return false;
        }
        $objectArray['type'] = $type;
        $actionObject = HistoryAction::__getActionObject($action, $type);

        $paramsTemplate = [
            'object_number' => '',
            'object_name' => '',
            'relocation_number' => '',
            'user_name' => '',
            'username' => '',
            'fullname' => '',
            'firstname' => '',
            'name' => '',
            'lastname' => '',
            'task_number' => '',
            'task_name' => '',
            'task_description' => '',
            'assignment_number' => '',
            'url' => '',
            'assignment_url' => '',
            'relocation_url' => '',
            'task_url' => '',
            'login_url' => '',
            'date' => '',
            'subject' => '',
            'assignee_name' => '',
            'time' => '',
            'servicename' => '',
            'service_name' => '',
            'eventname' => '',
            'service_event_name' => '',
            'targetuser' => '',
            'status' => '',
            'comment' => '',
            'questionnaire_name' => '',
            'property_name' => '',
            'property_visit_date_time' => '',
            'filename' => '',
        ];

        $dataMessage = [
            'action' => $action,
            'user_action' => $action,
            'language' => $lang,
            'object_uuid' => $object_uuid,
            'object' => json_encode($objectArray),
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'object_action' => ($actionObject) ? $actionObject->toArray() : "",
            'type' => $type,
            'message' => ($actionObject) ? $actionObject->getMessage() : "",
            'target_user' => $target_user,
        ];

        if ($target_user && is_object($target_user) && property_exists($target_user, "uuid")) {
            $target_user_uuid = $target_user->uuid;
        }

        if ($target_user && is_array($target_user) && key_exists("uuid", $target_user)) {
            $target_user_uuid = $target_user['uuid'];
        }

        if ($target_user_uuid && Helpers::__isValidUuid($target_user_uuid)) {
            $targetUserObject = UserProfile::findFirstByUuidCache($target_user_uuid);
            if ($targetUserObject) {
                $target_user = [
                    'id' => $targetUserObject->getId(),
                    'uuid' => $targetUserObject->getUuid(),
                    'firstname' => $targetUserObject->getFirstname(),
                    'lastname' => $targetUserObject->getLastname(),
                    'avatar_url' => $targetUserObject->getAvatarUrl()
                ];
                $paramsTemplate['targetuser'] = $targetUserObject->getFirstname() . " " . $targetUserObject->getLastname();
                $paramsTemplate['target_user'] = $target_user;


                if ($action == History::HISTORY_TAG_USER) {
                    $targetCompany = $targetUserObject->getCompany();
                    $paramsTemplate['company_name'] = $targetCompany->getName();
                }
            }
        } else {
            $paramsTemplate['targetuser'] = '';
        }


        if ($type == History::TYPE_TASK) {
            $task = $mainObject;
        } elseif ($type == History::TYPE_ASSIGNMENT) {
            $assignment = $mainObject;
            if ($assignment && is_object($assignment)) {
                $employee = $assignment->getEmployee();
            }
        } elseif ($type == History::TYPE_RELOCATION) {
            $relocation = $mainObject;
            if ($relocation && is_object($relocation)) {
                $assignment = $relocation->getAssignment();
                $employee = $assignment->getEmployee();
            }
        } elseif ($type == History::TYPE_SERVICE) {
            $service = $mainObject;
            if ($service && is_object($service)) {
                $relocation = $service->getRelocation();
                $assignment = $relocation->getAssignment();
                $employee = $assignment->getEmployee();
            }
        } elseif ($type == History::TYPE_COMMENT) {
            if ($taskInput && isset($taskInput->uuid) && $taskInput->uuid != '') {
                $task = $mainObject;
            }
        } elseif ($type == History::TYPE_INVOICE) {
            $invoice = $mainObject;
            if ($invoice && is_object($invoice)) {
                $employee = $invoice->getEmployee();
            }
        } elseif ($type == History::TYPE_QUOTE) {
            $quote = $mainObject;
            if ($quote && is_object($quote)) {
                $employee = $quote->getEmployee();
            }
        }


        if (isset($task) && $task) {

            $paramsTemplate['task_url'] = $task->getFrontendUrl();
            $paramsTemplate['task_number'] = $task->getNumber();
            $paramsTemplate['task_name'] = $task->getName();
            $paramsTemplate['task_description'] = $task->getDescription();

            if ($task->getProgress() == Task::STATUS_NOT_STARTED) {
                $paramsTemplate['status'] = ConstantHelper::__translate('TODO_STATUS_TEXT', $lang);
            }
            if ($task->getProgress() == Task::STATUS_IN_PROCESS) {
                $paramsTemplate['status'] = ConstantHelper::__translate('IN_PROGRESS_STATUS_TEXT', $lang);
            }
            if ($task->getProgress() == Task::STATUS_COMPLETED) {
                $paramsTemplate['status'] = ConstantHelper::__translate('DONE_STATUS_TEXT', $lang);
            }

            if ($task->getProgress() == Task::STATUS_AWAITING_FINAL_REVIEW) {
                $paramsTemplate['status'] =    ConstantHelper::__translate('AWAITING_FINAL_REVIEW_TEXT', $lang);
            }

            switch ($task->getIsPriority()){
                case TaskExt::TASK_PRIORITY_MEDIUM_TASK:
                    $paramsTemplate['priority'] = ConstantHelper::__translate('TASK_PRIORITY_MEDIUM_TASK_TEXT', $lang);
                    $paramsTemplate['task_priority'] = ConstantHelper::__translate('TASK_PRIORITY_MEDIUM_TASK_TEXT', $lang);
                    break;
                case TaskExt::TASK_PRIORITY_HIGH_TASK:
                    $paramsTemplate['priority'] = ConstantHelper::__translate('TASK_PRIORITY_HIGH_TASK_TEXT', $lang);
                    $paramsTemplate['task_priority'] = ConstantHelper::__translate('TASK_PRIORITY_HIGH_TASK_TEXT', $lang);
                    break;
                default:
                    //LOW = 0
                    $paramsTemplate['priority'] = ConstantHelper::__translate('TASK_PRIORITY_LOW_TASK_TEXT', $lang);
                    $paramsTemplate['task_priority'] = ConstantHelper::__translate('TASK_PRIORITY_LOW_TASK_TEXT', $lang);
                    break;
            }

            if ($task->getIsFlag() == ModelHelper::YES) {
                $paramsTemplate['flag'] = ConstantHelper::__translate('ADD_FLAG_TASK_TEXT', $lang);
                $paramsTemplate['task_flag'] = ConstantHelper::__translate('ADD_FLAG_TASK_TEXT', $lang);
            }else{
                $paramsTemplate['flag'] = ConstantHelper::__translate('REMOVE_FLAG_TASK_TEXT', $lang);
                $paramsTemplate['task_flag'] = ConstantHelper::__translate('REMOVE_FLAG_TASK_TEXT', $lang);
            }

            $paramsTemplate['status_number'] = $task->getStatus();
            $paramsTemplate['filename'] = $taskFile ? $taskFile->getName() : '';
            $paramsTemplate['due_date'] = $task->getDueAt() ? date('d/m/Y',strtotime($task->getDueAt())) : '';
            $paramsTemplate['subtask_name'] = $subtask_name ? : '' ;
            $paramsTemplate['file_name'] = $file_name ? : '' ;
            $paramsTemplate['filename'] = $file_name ? : '' ;
        }


        if (isset($assignment) && $assignment) {
            $paramsTemplate['assignment_url'] = $assignment->getFrontendUrl();
            $paramsTemplate['assignment_number'] = $assignment->getNumber();

            if ($assignment->getApprovalStatus() == Assignment::STATUS_PRE_APROVAL) {
                $paramsTemplate['status'] = ConstantHelper::__translate('PRE_APPROVAL_STATUS_TEXT', $lang);
            }
            if ($assignment->getApprovalStatus() == Assignment::STATUS_IN_APROVAL) {
                $paramsTemplate['status'] = ConstantHelper::__translate('IN_APROVAL_STATUS_TEXT', $lang);
            }
            if ($assignment->getApprovalStatus() == Assignment::STATUS_APPROVED) {
                $paramsTemplate['status'] = ConstantHelper::__translate('APPROVED_STATUS_TEXT', $lang);
            }
            if ($assignment->isTerminated() == true) {
                $paramsTemplate['status'] = ConstantHelper::__translate('TERMINATED_STATUS_TEXT', $lang);
            }
            if ($assignment->getApprovalStatus() == Assignment::STATUS_REJECTED) {
                $paramsTemplate['status'] = ConstantHelper::__translate('REJECTED_STATUS_TEXT', $lang);
            }

            $paramsTemplate['workflow_name'] = $workflow_name ?: '';
            $paramsTemplate['document_name'] = $document_name ?: '';
            $paramsTemplate['document_type'] = $document_type ?: '';
            $paramsTemplate['file_name'] = $file_name ? : '' ;
            $paramsTemplate['filename'] = $file_name ? : '' ;
        }


        if (isset($relocation) && $relocation) {
            $paramsTemplate['relocation_url'] = $relocation->getFrontendUrl();
            $paramsTemplate['relocation_number'] = $relocation->getNumber();

            if ($relocation->getStatus() == Relocation::STATUS_INITIAL) {
                $paramsTemplate['status'] = ConstantHelper::__translate('NOT_STARTED_TEXT', $lang);
            }
            if ($relocation->getStatus() == Relocation::STATUS_PENDING) {
                $paramsTemplate['status'] = ConstantHelper::__translate('NOT_STARTED_TEXT', $lang);
            }
            if ($relocation->getStatus() == Relocation::STATUS_ONGOING) {
                $paramsTemplate['status'] = ConstantHelper::__translate('ONGOING_TEXT', $lang);
            }
            if ($relocation->getStatus() == Relocation::STATUS_TERMINATED) {
                $paramsTemplate['status'] = ConstantHelper::__translate('TERMINATED_TEXT', $lang);
            }

            $paramsTemplate['workflow_name'] = $workflow_name ?: '';
            $paramsTemplate['document_name'] = $document_name ?: '';
            $paramsTemplate['document_type'] = $document_type ?: '';
            $paramsTemplate['file_name'] = $file_name ? : '' ;
            $paramsTemplate['filename'] = $file_name ? : '' ;
        }

        if (isset($service) && $service) {
            $paramsTemplate['service_url'] = $service->getFrontendUrl();
            $paramsTemplate['service_number'] = $service->getNumber();
            $paramsTemplate['service_name'] = $service->getServiceCompany() ? $service->getServiceCompany()->getName() : "";
            $paramsTemplate['servicename'] = $service->getServiceCompany() ? $service->getServiceCompany()->getName() : "";
            $paramsTemplate['file_name'] = $file_name ? : '' ;
            $paramsTemplate['filename'] = $file_name ? : '' ;
        }

        if (isset($mainObject) && $mainObject) {
            $paramsTemplate['object_url'] = $mainObject->getFrontendUrl();
            $paramsTemplate['object_number'] = method_exists($mainObject, "getNumber") ? $mainObject->getNumber() : "";
            $paramsTemplate['object_name'] = ConstantHelper::__translate($objectArray['object_label'], $lang) != false ? ConstantHelper::__translate($objectArray['object_label'], $lang) : $objectArray['object_label'];
            $paramsTemplate['file_name'] = $file_name ? : '' ;
            $paramsTemplate['filename'] = $file_name ? : '' ;
        }

        if (isset($employee) && $employee) {
            $paramsTemplate['assignee_name'] = $employee->getFullname();
            $paramsTemplate['assignee_number'] = $employee->getNumber();
            $paramsTemplate['file_name'] = $file_name ? : '' ;
            $paramsTemplate['filename'] = $file_name ? : '' ;
        }

        $paramsTemplate['time'] = date('d M Y - H:i:s');
        $paramsTemplate['date'] = date('d M Y - H:i:s');


        if (isset($objectArray['frontend_url']) && $objectArray['frontend_url'] !== '') {
            $paramsTemplate['url'] = $objectArray['frontend_url'];
        }

        $paramsTemplate['username'] = ModuleModel::$user_profile->getFullname();
        $paramsTemplate['user_name'] = ModuleModel::$user_profile->getFullname();
        $paramsTemplate['fullname'] = ModuleModel::$user_profile->getFullname();
        $paramsTemplate['name'] = ModuleModel::$user_profile->getFullname();
        $paramsTemplate['firstname'] = ModuleModel::$user_profile->getFirstname();
        $paramsTemplate['lastname'] = ModuleModel::$user_profile->getLastname();
        $paramsTemplate['email'] = ModuleModel::$user_profile->getWorkemail();
        $paramsTemplate['workemail'] = ModuleModel::$user_profile->getWorkemail();
        $paramsTemplate['avatar_url'] = ModuleModel::$user_profile->getAvatarUrl();
        $paramsTemplate['comment'] = $comment;

        if (isset($questionnaire) && $questionnaire) {
            if (is_array($questionnaire) && isset($questionnaire['name']) && $questionnaire['name']) {
                $paramsTemplate['questionnaire_name'] = $questionnaire['name'];
            }

            if (is_object($questionnaire) && $questionnaire->name) {
                $paramsTemplate['questionnaire_name'] = $questionnaire->name;
            }
        }

        if (isset($property) && $property) {
            if (is_array($property)) {
                if (isset($property['name']) && $property['name']) {
                    $paramsTemplate['property_name'] = $property['name'];
                }
                if (isset($property['property_visit_date_time']) && $property['property_visit_date_time']) {
                    $paramsTemplate['property_visit_date_time'] = $property['property_visit_date_time'];
                }
            }
            if (is_object($property)) {
                if (property_exists($property, 'name') && $property->name) {
                    $paramsTemplate['property_name'] = $property->name;
                }
                if (property_exists($property, '$dataMessageproperty_visit_date_time') && $property->property_visit_date_time) {
                    $paramsTemplate['property_visit_date_time'] = $property->property_visit_date_time;
                }
            }
        }

        $dataMessage['templateName'] = $actionObject && $actionObject->getEmailTemplateDefault() ? $actionObject->getEmailTemplateDefault()->getName() : '';
        $dataMessage['params'] = json_encode($paramsTemplate);



        return $dataMessage;
    }

    public static function __sendHistory($object, $action = "", $ip = '')
    {
        if ($object && $object instanceof Assignment){
            $customData = [
                'uuid' => $object->getUuid(),
                'type' => \Reloday\Application\Lib\HistoryHelper::TYPE_ASSIGNMENT,
                'action' => $action
            ];
        }else if ($object && $object instanceof Relocation){
            $customData = [
                'uuid' => $object->getUuid(),
                'type' => \Reloday\Application\Lib\HistoryHelper::TYPE_RELOCATION,
                'action' => $action
            ];
        }else{
            $customData = [
                'uuid' => $object->getUuid(),
                'type' => \Reloday\Application\Lib\HistoryHelper::TYPE_OTHERS,
                'action' => $action
            ];
        }

        $historyObjectData = self::__getHistoryObject(ModuleModel::$language, $customData);
        $historyObject = new History();
        $historyObject->setData($historyObjectData);
        $historyObject->setIp($ip);
        $historyObject->setCompanyUuid(ModuleModel::$company->getUuid());
        $historyObject->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $historyObject->setUuid(Helpers::__uuid());
        $historyObject->setObjectUuid($object->getUuid());

        $return = $historyObject->__quickCreate();

        return $return;
    }
}