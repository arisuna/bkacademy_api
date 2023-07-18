<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use \Firebase\JWT\JWT;
use \Reloday\Application\Lib\Helpers;
use \Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Models\SupportedLanguageExt;

class  HistoryOld extends HistoryModel
{
    /**
     * HistoryOld action
     * @var
     */
    static $historyAction;
    /**
     * @param string $lang
     * @param bool $parseDynamoDb
     * @param array $customData
     * @return array
     */
    /**
     * params template
     * @var array
     */
    static $paramsTemplate = [
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
    ];


    public static function __getHistoryObject($lang = SupportedLanguageExt::LANG_EN, $parseDynamoDb = true, $customData = [])
    {
        if (count($customData) == 0) {
            $customData = Helpers::__getRequestValuesArray();
        }
        $object_uuid = Helpers::__getCustomValue('uuid', $customData);
        $action = Helpers::__getCustomValue('action', $customData) != null ? Helpers::__getCustomValue('action', $customData) : "HISTORY_UPDATE";
        $type = Helpers::__getCustomValue('type', $customData) != null ? Helpers::__getCustomValue('type', $customData) : self::TYPE_OTHERS;
        $taskInput = Helpers::__getCustomValue('task', $customData);
        $target_user_uuid = Helpers::__getCustomValue('target_user_uuid', $customData);
        $target_user = Helpers::__getCustomValue('target_user', $customData);
        $comment = Helpers::__getCustomValue('comment', $customData);
        $object = Helpers::__getCustomValue('object', $customData);
        $questionnaire = Helpers::__getCustomValue('questionnaire', $customData);
        $property = Helpers::__getCustomValue('property', $customData);

        if ($object_uuid != '') {
            $type = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
        }

        $objectArray = [];
        if ($type == HistoryOld::TYPE_OTHERS && $object_uuid != '') {
            $type = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
        }

        /*** force use task **/
        $taskObject = Task::findFirstByUuid($object_uuid);
        if ($taskObject) {
            $type = self::TYPE_TASK;
        }
        /*** end force use task **/

        if ($type == self::TYPE_TASK) {
            $mainObject = $taskObject;
            if ($mainObject && $mainObject instanceof Task) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['number'] = $mainObject->getNumber();
                $objectArray['object_label'] = "TASK_TEXT";
            }
        } elseif ($type == HistoryOld::TYPE_ASSIGNMENT) {
            $mainObject = Assignment::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof Assignment) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['number'] = $mainObject->getReference();
                $objectArray['object_label'] = "ASSIGNMENT_TEXT";
            }
        } elseif ($type == HistoryOld::TYPE_RELOCATION) {
            $mainObject = Relocation::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof Relocation) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['number'] = $mainObject->getIdentify();
                $objectArray['object_label'] = "RELOCATION_TEXT";
            }
        } elseif ($type == HistoryOld::TYPE_SERVICE) {
            $mainObject = RelocationServiceCompany::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof RelocationServiceCompany) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getSimpleFrontendUrl();
                $objectArray['number'] = $mainObject->getNumber();
                $objectArray['name'] = $mainObject->getServiceCompany()->getName();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['object_label'] = "SERVICE_TEXT";
            }
        } elseif ($type == HistoryOld::TYPE_COMMENT) {
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
        } elseif ($type == HistoryOld::TYPE_INVOICE) {
            $mainObject = InvoiceQuote::findFirstByUuid($object_uuid);
            if ($mainObject && $mainObject instanceof InvoiceQuote) {
                $objectArray['uuid'] = $mainObject->getUuid();
                $objectArray['frontend_url'] = $mainObject->getFrontendUrl();
                $objectArray['number'] = $mainObject->getReference();
                $objectArray['name'] = $mainObject->getReference();
                $objectArray['frontend_state'] = $mainObject->getFrontendState();
                $objectArray['object_label'] = "SERVICE_TEXT";
            }
        } elseif ($type == HistoryOld::TYPE_QUOTE) {
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
        self::$historyAction = $actionObject;

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
        ];

        $dataMessage = [
            'action' => $action,
            'user_action' => $action,
            'language' => $lang,
            'object_uuid' => $object_uuid,
            'object' => $objectArray,
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'user' => [
                'firstname' => ModuleModel::$user_profile->getFirstname(),
                'lastname' => ModuleModel::$user_profile->getLastname(),
                'email' => ModuleModel::$user_profile->getWorkemail(),
                'workemail' => ModuleModel::$user_profile->getWorkemail(),
                'avatar_url' => ModuleModel::$user_profile->getAvatarUrl(),
            ],
            'object_action' => ($actionObject) ? $actionObject->toArray() : "",
            'assignment' => [],
            'relocation' => [],
            'task' => [],
            'service' => [],
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
                $dataMessage['target_user'] = $target_user;


                if ($action == self::HISTORY_TAG_USER) {
                    $targetCompany = $targetUserObject->getCompany();
                    $paramsTemplate['company_name'] = $targetCompany->getName();
                    $dataMessage['company_name'] = $targetCompany->getName();
                }
            }
        } else {
            $paramsTemplate['targetuser'] = '';
        }


        if ($type == HistoryOld::TYPE_TASK) {
            $task = $mainObject;
        } elseif ($type == HistoryOld::TYPE_ASSIGNMENT) {
            $assignment = $mainObject;
            if ($assignment && is_object($assignment)) {
                $employee = $assignment->getEmployee();
            }
        } elseif ($type == HistoryOld::TYPE_RELOCATION) {
            $relocation = $mainObject;
            if ($relocation && is_object($relocation)) {
                $assignment = $relocation->getAssignment();
                $employee = $assignment->getEmployee();
            }
        } elseif ($type == HistoryOld::TYPE_SERVICE) {
            $service = $mainObject;
            if ($service && is_object($service)) {
                $relocation = $service->getRelocation();
                $assignment = $relocation->getAssignment();
                $employee = $assignment->getEmployee();
            }
        } elseif ($type == HistoryOld::TYPE_COMMENT) {
            if ($taskInput && isset($taskInput->uuid) && $taskInput->uuid != '') {
                $task = $mainObject;
            }
        } elseif ($type == HistoryOld::TYPE_INVOICE) {
            $invoice = $mainObject;
            if ($invoice && is_object($invoice)) {
                $employee = $invoice->getEmployee();
            }
        } elseif ($type == HistoryOld::TYPE_QUOTE) {
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

            $dataMessage['task'] = [
                'uuid' => $task->getUuid(),
                'name' => $task->getName(),
                'number' => $task->getNumber(),
            ];

            if ($task->getStatus() == Task::STATUS_NOT_STARTED) {
                $paramsTemplate['status'] = ConstantHelper::__translate('TODO_STATUS_TEXT', $lang);
            }
            if ($task->getStatus() == Task::STATUS_IN_PROCESS) {
                $paramsTemplate['status'] = ConstantHelper::__translate('IN_PROGRESS_STATUS_TEXT', $lang);
            }
            if ($task->getStatus() == Task::STATUS_COMPLETED) {
                $paramsTemplate['status'] = ConstantHelper::__translate('DONE_STATUS_TEXT', $lang);
            }
            $paramsTemplate['status_number'] = $task->getStatus();
        }


        if (isset($assignment) && $assignment) {
            $paramsTemplate['assignment_url'] = $assignment->getFrontendUrl();
            $paramsTemplate['assignment_number'] = $assignment->getNumber();
            $dataMessage['assignment'] = [
                'uuid' => $assignment->getUuid(),
                'url' => $assignment->getFrontendUrl(),
                'number' => $assignment->getNumber(),
            ];

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
        }


        if (isset($relocation) && $relocation) {
            $paramsTemplate['relocation_url'] = $relocation->getFrontendUrl();
            $paramsTemplate['relocation_number'] = $relocation->getNumber();
            $dataMessage['relocation'] = [
                'uuid' => $relocation->getUuid(),
                'url' => $relocation->getFrontendUrl(),
                'number' => $relocation->getNumber(),
            ];

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
        }

        if (isset($service) && $service) {
            $paramsTemplate['service_url'] = $service->getFrontendUrl();
            $paramsTemplate['service_number'] = $service->getNumber();
            $paramsTemplate['service_name'] = $service->getServiceCompany() ? $service->getServiceCompany()->getName() : "";
            $paramsTemplate['servicename'] = $service->getServiceCompany() ? $service->getServiceCompany()->getName() : "";
            $dataMessage['service'] = [
                'uuid' => $service->getUuid(),
                'url' => $service->getFrontendUrl(),
                'name' => $service->getServiceCompany()->getName(),
                'number' => $service->getNumber(),
            ];
        }

        if (isset($mainObject) && $mainObject) {
            $paramsTemplate['object_url'] = $mainObject->getFrontendUrl();
            $paramsTemplate['object_number'] = method_exists($mainObject, "getNumber") ? $mainObject->getNumber() : "";
            $paramsTemplate['object_name'] = ConstantHelper::__translate($objectArray['object_label'], $lang) != false ? ConstantHelper::__translate($objectArray['object_label'], $lang) : $objectArray['object_label'];
        }

        if (isset($employee) && $employee) {
            $paramsTemplate['assignee_name'] = $employee->getFullname();
            $paramsTemplate['assignee_number'] = $employee->getNumber();

            $dataMessage['employee'] = [
                'uuid' => $employee->getUuid(),
                'name' => $employee->getFirstname() . " " . $employee->getLastname(),
                'avatar_url' => $employee->getAvatarUrl(),
                'number' => $employee->getNumber(),
            ];
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
                if (property_exists($property, 'property_visit_date_time') && $property->property_visit_date_time) {
                    $paramsTemplate['property_visit_date_time'] = $property->property_visit_date_time;
                }
            }
        }

        $dataMessage['templateName'] = $actionObject && $actionObject->getEmailTemplateDefault() ? $actionObject->getEmailTemplateDefault()->getName() : '';
        $dataMessage['params'] = $paramsTemplate;


        if ($parseDynamoDb == true) {
            foreach ($dataMessage as $key => $object) {
                if (is_array($object)) {
                    foreach ($object as $kk => $item) {
                        if ($item && is_string($item)) {
                            $object[$kk] = ['S' => (string)$item];
                        } elseif ($item && is_bool($item)) {
                            $object[$kk] = ['BOOL' => $item];
                        } elseif ($item && is_numeric($item)) {
                            $object[$kk] = ['N' => (string)$item];
                        } else {
                            unset($object[$kk]);
                        }
                    }
                    $dataMessage[$key] = $object;
                }
            }
        }
        return $dataMessage;
    }

    /**
     * @param $params
     */
    public static function __parseParams($params = [])
    {
        if (!is_null($params)) {
            $params = Helpers::__parseDynamodbObjectParams($params);
            foreach (self::$paramsTemplate as $key => $value) {
                if (!key_exists($key, $params)) {
                    $params[$key] = '';
                }
            }
        }
        return $params;
    }

    /**
     * @param $params
     */
    public static function __parseDynamoParams($params = [])
    {
        if (is_array($params)) {
            foreach ($params as $kk => $item) {
                if ($item && is_string($item)) {
                    $params[$kk] = ['S' => (string)$item];
                } elseif ($item && is_bool($item)) {
                    $params[$kk] = ['BOOL' => $item];
                } elseif ($item && is_numeric($item)) {
                    $params[$kk] = ['N' => (string)$item];
                } else {
                    unset($params[$kk]);
                }
            }
            return $params;
        }
    }
}