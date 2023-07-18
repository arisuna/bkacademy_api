<?php

namespace Reloday\Gms\Help;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\TaskExt;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HousingProposition;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Task;

class SendHistoryHelper
{
    public static function __sendHistory(\Phalcon\Mvc\Dispatcher $dispatcher){
        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();
        $return = $dispatcher->getParam('return');
        $request = new  \Phalcon\Http\Request();
        if ($return['success']) {
            //Assingment
            if($controller == AclHelper::CONTROLLER_ASSIGNMENT){
                $assignment = ModuleModel::$assignment;
                if($assignment){
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl()
                    ];

                    if($action == 'create'){
                        $historyObjectUuids = [$assignment->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_CREATE;
                        $assignment->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                    }
                    if($action == 'update'){
                        $historyObjectUuids = [$assignment->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_UPDATE;
                        $assignment->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                    }
                    if($action == 'changeApprovalStatus'){
                        $historyObjectUuids = [$assignment->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_CHANGE_STATUS;
                        $assignment->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                    }
                    if($action == 'terminate'){
                        $historyObjectUuids = [$assignment->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_TERMINATE;
                        $assignment->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, ['uuid' => $assignment->getUuid()]);
                    }
                    if ($action == 'applyWorkflow') {
                        $workflow_name = $dispatcher->getParam('workflow_name');
                        $workflowParams = $customParams;

                        $workflowParams['workflow_name'] = $workflow_name;
                        $workflowParams['historyObjectUuids'] = [$assignment->getUuid()];

                        $actionHistory = History::HISTORY_WORKFLOW_LAUNCHED;
                        $assignment->sendHistoryToQueue($actionHistory, $workflowParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                            'uuid' => $assignment->getUuid(),
                            'workflow_name' => $workflow_name,
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                            'uuid' => $assignment->getUuid(),
                            'workflow_name' => $workflow_name,
                        ]);

                        $listTaskUuids = $dispatcher->getParam('listTaskUuids');
                        if($listTaskUuids && is_array($listTaskUuids)){
                            foreach ($listTaskUuids as $taskUuid){
                                $taskObj = Task::findFirstByUuid($taskUuid);
                                if($taskObj){
                                    $taskParams = $customParams;
                                    $taskParams['historyObjectUuids'] = [$taskUuid];
                                    $taskObj->sendHistoryToQueue(History::HISTORY_CREATE, $taskParams);
                                }
                            }
                        }
                    }
                    if($action == 'bulk'){
                        $reporter = $dispatcher->getParam('reporter');
                        if($reporter && is_object($reporter)){
                            $customOwnerParams = $customParams;
                            $customOwnerParams['historyObjectUuids'] = [$assignment->getUuid()];
                            $customOwnerParams['target_user_uuid'] = $reporter->getUuid();
                            $customOwnerParams['object_uuid'] = $assignment->getUuid();
                            $actionHistory = History::HISTORY_SET_REPORTER;
                            DataUserMember::__sendHistoryToQueue($actionHistory, $customOwnerParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                'uuid' => $assignment->getUuid(),
                                'target_user_uuid' => $reporter->getUuid()
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                'uuid' => $assignment->getUuid(),
                                'target_user_uuid' => $reporter->getUuid()
                            ]);
                        }

                        $owner = $dispatcher->getParam('owner');
                        if($owner && is_object($owner)){
                            $customOwnerParams = $customParams;
                            $customOwnerParams['historyObjectUuids'] = [$assignment->getUuid()];
                            $customOwnerParams['target_user_uuid'] = $owner->getUuid();
                            $customOwnerParams['object_uuid'] = $assignment->getUuid();
                            $actionHistory = History::HISTORY_SET_OWNER;
                            DataUserMember::__sendHistoryToQueue($actionHistory, $customOwnerParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                'uuid' => $assignment->getUuid(),
                                'target_user_uuid' => $owner->getUuid()
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                'uuid' => $assignment->getUuid(),
                                'target_user_uuid' => $owner->getUuid()
                            ]);
                        }

                        $viewers = $dispatcher->getParam('viewers');
                        if($viewers && is_array($viewers) && count($viewers) > 0){
                            foreach ($viewers as $viewer){
                                $customViewerParams = $customParams;
                                $customViewerParams['historyObjectUuids'] = [$assignment->getUuid()];
                                $customViewerParams['target_user_uuid'] = $viewer->getUuid();
                                $customViewerParams['object_uuid'] = $assignment->getUuid();
                                $actionHistory = History::HISTORY_ADD_VIEWER;
                                DataUserMember::__sendHistoryToQueue($actionHistory, $customViewerParams);

                                $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                    'uuid' => $assignment->getUuid(),
                                    'target_user_uuid' => $viewer->getUuid()
                                ]);
                                $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                    'uuid' => $assignment->getUuid(),
                                    'target_user_uuid' => $viewer->getUuid()
                                ]);
                            }

                        }

                        $isHistoryUpdate = $dispatcher->getParam('isHistoryUpdate');
                        if($isHistoryUpdate && is_bool($isHistoryUpdate)){
                            $customUpdateParams = $customParams;
                            $customUpdateParams['historyObjectUuids'] = [$assignment->getUuid()];
                            $actionHistory = History::HISTORY_UPDATE;
                            $assignment->sendHistoryToQueue($actionHistory, $customUpdateParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                'uuid' => $assignment->getUuid(),
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, $actionHistory, [
                                'uuid' => $assignment->getUuid(),
                            ]);

                        }
                    }
                }
            }

            //Relocation
            if ($controller == AclHelper::CONTROLLER_RELOCATION) {
                $relocation = ModuleModel::$relocation;
                if ($relocation) {
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl(),
                    ];

                    if ($action == 'addServices') {
                        $relocationServiceCompanies = ModuleModel::$relocationServiceCompanies;
                        if ($relocationServiceCompanies && is_array($relocationServiceCompanies)) {
                            foreach ($relocationServiceCompanies as $relocationServiceCompany) {
//                                sleep(1);
                                $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid(), $relocation->getUuid()];
                                $actionHistory = History::HISTORY_CREATE;

                                $relocationServiceCompany->sendHistoryToQueue($actionHistory, $customParams);

                                $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_CREATE, [
                                    'uuid' => $relocationServiceCompany->getUuid(),
                                ]);
                                $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_CREATE, [
                                    'uuid' => $relocationServiceCompany->getUuid(),
                                ]);
                            }
                        }
                    }
                    if ($action == 'desactivateService') {
                        $relocationServiceCompany = ModuleModel::$relocationServiceCompany;
                        if ($relocationServiceCompany) {
                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_REMOVE, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                            ]);

                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_REMOVE, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                            ]);
//                            sleep(1);

                            $customParams['historyObjectUuids'] = [$relocation->getUuid()];
                            $actionHistory = History::HISTORY_REMOVE;
                            $relocationServiceCompany->sendHistoryToQueue($actionHistory, $customParams);

                        }
                    }

                    if ($action == 'create') {
                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];

                        $actionHistory = History::HISTORY_CREATE;
                        $relocation->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_CREATE, [
                            'uuid' => $relocation->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_CREATE, [
                            'uuid' => $relocation->getUuid(),
                        ]);
                    }
                    if ($action == 'edit') {
                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];

                        $actionHistory = History::HISTORY_UPDATE;
                        $relocation->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_UPDATE, [
                            'uuid' => $relocation->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_UPDATE, [
                            'uuid' => $relocation->getUuid(),
                        ]);
                    }
                    if ($action == 'changeStatus') {
                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];

                        $actionHistory = History::HISTORY_CHANGE_STATUS;
                        $relocation->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_CHANGE_STATUS, [
                            'uuid' => $relocation->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_CHANGE_STATUS, [
                            'uuid' => $relocation->getUuid(),
                        ]);
                    }

                    if ($action == 'cancel') {
                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];

                        $actionHistory = History::HISTORY_CANCEL_RELOCATION;
                        $relocation->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);
                    }
                    if ($action == 'remove') {
                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];

                        $actionHistory = History::HISTORY_DELETE_RELOCATION;
                        $relocation->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);
                    }
                    if ($action == 'unarchive') {
                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];

                        $actionHistory = History::HISTORY_UNARCHIVE_RELOCATION;
                        $relocation->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);
                    }
                    if ($action == 'archive') {
                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];
                        $actionHistory = History::HISTORY_ARCHIVE_RELOCATION;
                        $relocation->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                        ]);
                    }

                    if ($action == 'applyWorkflow') {
                        $workflow_name = $dispatcher->getParam('workflow_name');
                        $workflowParams = $customParams;

                        $workflowParams['workflow_name'] = $workflow_name;
                        $workflowParams['historyObjectUuids'] = [$relocation->getUuid()];

                        $actionHistory = History::HISTORY_WORKFLOW_LAUNCHED;
                        $relocation->sendHistoryToQueue($actionHistory, $workflowParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                            'workflow_name' => $workflow_name,
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                            'uuid' => $relocation->getUuid(),
                            'workflow_name' => $workflow_name,
                        ]);

                        $listTaskUuids = $dispatcher->getParam('listTaskUuids');
                        if($listTaskUuids && is_array($listTaskUuids)){
                            foreach ($listTaskUuids as $taskUuid){
                                $taskObj = Task::findFirstByUuid($taskUuid);
                                if($taskObj){
                                    $taskParams = $customParams;
                                    $taskParams['historyObjectUuids'] = [$taskUuid];
                                    $taskObj->sendHistoryToQueue(History::HISTORY_CREATE, $taskParams);
                                }

                            }
                        }
                    }

                    if($action == 'bulk'){
                        $reporter = $dispatcher->getParam('reporter');
                        if($reporter && is_object($reporter)){
                            $customOwnerParams = $customParams;
                            $customOwnerParams['historyObjectUuids'] = [$relocation->getUuid()];
                            $customOwnerParams['target_user_uuid'] = $reporter->getUuid();
                            $customOwnerParams['object_uuid'] = $relocation->getUuid();
                            $actionHistory = History::HISTORY_SET_REPORTER;
                            DataUserMember::__sendHistoryToQueue($actionHistory, $customOwnerParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                                'target_user_uuid' => $reporter->getUuid()
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                                'target_user_uuid' => $reporter->getUuid()
                            ]);
                        }

                        $owner = $dispatcher->getParam('owner');
                        if($owner && is_object($owner)){
                            $customOwnerParams = $customParams;
                            $customOwnerParams['historyObjectUuids'] = [$relocation->getUuid()];
                            $customOwnerParams['target_user_uuid'] = $owner->getUuid();
                            $customOwnerParams['object_uuid'] = $relocation->getUuid();
                            $actionHistory = History::HISTORY_SET_OWNER;
                            DataUserMember::__sendHistoryToQueue($actionHistory, $customOwnerParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                                'target_user_uuid' => $owner->getUuid()
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                                'target_user_uuid' => $owner->getUuid()
                            ]);
                        }

                        $viewers = $dispatcher->getParam('viewers');
                        if($viewers && is_array($viewers) && count($viewers) > 0){
                            foreach ($viewers as $viewer){
                                $customViewerParams = $customParams;
                                $customViewerParams['historyObjectUuids'] = [$relocation->getUuid()];
                                $customViewerParams['target_user_uuid'] = $viewer->getUuid();
                                $customViewerParams['object_uuid'] = $relocation->getUuid();
                                $actionHistory = History::HISTORY_ADD_VIEWER;
                                DataUserMember::__sendHistoryToQueue($actionHistory, $customViewerParams);

                                $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                    'uuid' => $relocation->getUuid(),
                                    'target_user_uuid' => $viewer->getUuid()
                                ]);
                                $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                    'uuid' => $relocation->getUuid(),
                                    'target_user_uuid' => $viewer->getUuid()
                                ]);
                            }

                        }

                        $isHistoryUpdate = $dispatcher->getParam('isHistoryUpdate');
                        if($isHistoryUpdate && is_bool($isHistoryUpdate)){
                            $customUpdateParams = $customParams;
                            $customUpdateParams['historyObjectUuids'] = [$relocation->getUuid()];
                            $actionHistory = History::HISTORY_UPDATE;
                            $relocation->sendHistoryToQueue($actionHistory, $customUpdateParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                            ]);

                        }

                        $isHistoryChangeStatus = $dispatcher->getParam('isHistoryChangeStatus');
                        if($isHistoryChangeStatus && is_bool($isHistoryChangeStatus)){
                            $customStatusParams = $customParams;
                            $customStatusParams['historyObjectUuids'] = [$relocation->getUuid()];
                            $actionHistory = History::HISTORY_CHANGE_STATUS;
                            $relocation->sendHistoryToQueue($actionHistory, $customStatusParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, $actionHistory, [
                                'uuid' => $relocation->getUuid(),
                            ]);

                        }

                    }
                }
            }

            //Attachment Service
            if($controller == AclHelper::CONTROLLER_ATTACHMENTS){
                if($return['success']){
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl()
                    ];
                    if($action == 'attachMultipleFiles'){
                        $type = $dispatcher->getParam('type');
                        $filename = $dispatcher->getParam('filename') ?: '';
                        $customParams['file_name'] = $filename;
                        $customParams['filename'] = $filename;
                        if(isset($type) && $type){
                            switch ($type){
                                case History::TYPE_ASSIGNMENT:
                                    $assignment = ModuleModel::$assignment;
                                    if($assignment){
                                        $customParams['historyObjectUuids'] = [$assignment->getUuid()];
                                        $assignment->sendHistoryToQueue(History::HISTORY_ADD_ATTACHMENT, $customParams);
                                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, History::HISTORY_ADD_ATTACHMENT, ['uuid' => $assignment->getUuid(), 'file_name' => $filename, 'filename' => $filename]);
                                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, History::HISTORY_ADD_ATTACHMENT, ['uuid' => $assignment->getUuid()]);
                                    }
                                    break;
                                case History::TYPE_RELOCATION:
                                    $relocation = ModuleModel::$relocation;
                                    if($relocation){
                                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];
                                        $relocation->sendHistoryToQueue(History::HISTORY_ADD_ATTACHMENT, $customParams);
                                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_ADD_ATTACHMENT, ['uuid' => $relocation->getUuid(), 'file_name' => $filename, 'filename' => $filename]);
                                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_ADD_ATTACHMENT, ['uuid' => $relocation->getUuid()]);
                                    }
                                    break;
                                case History::TYPE_SERVICE:
                                    $relocationServiceCompany = ModuleModel::$relocationServiceCompany;
                                    if($relocationServiceCompany){
                                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                                        $relocationServiceCompany->sendHistoryToQueue(History::HISTORY_ADD_ATTACHMENT, $customParams);
                                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_ADD_ATTACHMENT, ['uuid' => $relocationServiceCompany->getUuid(), 'file_name' => $filename, 'filename' => $filename]);
                                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_ADD_ATTACHMENT, ['uuid' => $relocationServiceCompany->getUuid()]);
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }

                    if($action == 'remove' || $action == 'removeMultiple'){
                        $type = $dispatcher->getParam('type');
                        $filename = $dispatcher->getParam('filename') ?: '';
                        $customParams['file_name'] = $filename;
                        $customParams['filename'] = $filename;
                        if(isset($type) && $type){
                            switch ($type){
                                case History::TYPE_ASSIGNMENT:
                                    $assignment = ModuleModel::$assignment;
                                    if($assignment){
                                        $customParams['historyObjectUuids'] = [$assignment->getUuid()];
                                        $assignment->sendHistoryToQueue(History::HISTORY_REMOVE_ATTACHMENT, $customParams);
                                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, History::HISTORY_REMOVE_ATTACHMENT, ['uuid' => $assignment->getUuid(), 'file_name' => $filename, 'filename' => $filename]);
                                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, History::HISTORY_REMOVE_ATTACHMENT, ['uuid' => $assignment->getUuid()]);
                                    }
                                    break;
                                case History::TYPE_RELOCATION:
                                    $relocation = ModuleModel::$relocation;
                                    if($relocation){
                                        $customParams['historyObjectUuids'] = [$relocation->getUuid()];
                                        $relocation->sendHistoryToQueue(History::HISTORY_REMOVE_ATTACHMENT, $customParams);
                                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_REMOVE_ATTACHMENT, ['uuid' => $relocation->getUuid(), 'file_name' => $filename, 'filename' => $filename]);
                                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_REMOVE_ATTACHMENT, ['uuid' => $relocation->getUuid()]);
                                    }
                                    break;
                                case History::TYPE_SERVICE:
                                    $relocationServiceCompany = ModuleModel::$relocationServiceCompany;
                                    if($relocationServiceCompany){
                                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                                        $relocationServiceCompany->sendHistoryToQueue(History::HISTORY_REMOVE_ATTACHMENT, $customParams);
                                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_REMOVE_ATTACHMENT, ['uuid' => $relocationServiceCompany->getUuid(), 'file_name' => $filename, 'filename' => $filename]);
                                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_REMOVE_ATTACHMENT, ['uuid' => $relocationServiceCompany->getUuid()]);
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            }

            //Relocation Service
            if($controller == AclHelper::CONTROLLER_RELOCATION_SERVICE){
                $relocationServiceCompany = ModuleModel::$relocationServiceCompany;
                if($relocationServiceCompany){
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl()
                    ];

                    if($action == 'saveEvents'){
                        $eventname = $dispatcher->getParam('eventname');
                        $customParams['eventname'] = $eventname;
                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];

                        $relocationServiceCompany->sendHistoryToQueue(History::HISTORY_SAVE_SERVICE_EVENTS, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_SAVE_SERVICE_EVENTS, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_SAVE_SERVICE_EVENTS, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                        ]);
                    }

                    if($action == 'saveInfos'){
                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];

                        $relocationServiceCompany->sendHistoryToQueue(History::HISTORY_UPDATE, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_UPDATE, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_UPDATE, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                        ]);
                    }

                    if($action == 'saveServiceProvider'){
                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];

                        $relocationServiceCompany->sendHistoryToQueue(History::HISTORY_SET_SVP, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_SET_SVP, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::HISTORY_SET_SVP, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                        ]);
                    }

                    if($action == 'bulk'){
                        $reporter = $dispatcher->getParam('reporter');
                        if($reporter && is_object($reporter)){
                            $customOwnerParams = $customParams;
                            $customOwnerParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                            $customOwnerParams['target_user_uuid'] = $reporter->getUuid();
                            $customOwnerParams['object_uuid'] = $relocationServiceCompany->getUuid();
                            $actionHistory = History::HISTORY_SET_REPORTER;
                            DataUserMember::__sendHistoryToQueue($actionHistory, $customOwnerParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'target_user_uuid' => $reporter->getUuid()
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'target_user_uuid' => $reporter->getUuid()
                            ]);
                        }

                        $owner = $dispatcher->getParam('owner');
                        if($owner && is_object($owner)){
                            $customOwnerParams = $customParams;
                            $customOwnerParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                            $customOwnerParams['target_user_uuid'] = $owner->getUuid();
                            $customOwnerParams['object_uuid'] = $relocationServiceCompany->getUuid();
                            $actionHistory = History::HISTORY_SET_OWNER;
                            DataUserMember::__sendHistoryToQueue($actionHistory, $customOwnerParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'target_user_uuid' => $owner->getUuid()
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'target_user_uuid' => $owner->getUuid()
                            ]);
                        }

                        $viewers = $dispatcher->getParam('viewers');
                        if($viewers && is_array($viewers) && count($viewers) > 0){
                            foreach ($viewers as $viewer){
                                $customViewerParams = $customParams;
                                $customViewerParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                                $customViewerParams['target_user_uuid'] = $viewer->getUuid();
                                $customViewerParams['object_uuid'] = $relocationServiceCompany->getUuid();
                                $actionHistory = History::HISTORY_ADD_VIEWER;
                                DataUserMember::__sendHistoryToQueue($actionHistory, $customViewerParams);

                                $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                    'uuid' => $relocationServiceCompany->getUuid(),
                                    'target_user_uuid' => $viewer->getUuid()
                                ]);
                                $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                    'uuid' => $relocationServiceCompany->getUuid(),
                                    'target_user_uuid' => $viewer->getUuid()
                                ]);
                            }

                        }

                        $isHistoryUpdate = $dispatcher->getParam('isHistoryUpdate');
                        if($isHistoryUpdate && is_bool($isHistoryUpdate)){
                            $customUpdateParams = $customParams;
                            $customUpdateParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                            $actionHistory = History::HISTORY_UPDATE;
                            $relocationServiceCompany->sendHistoryToQueue($actionHistory, $customUpdateParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                            ]);

                        }

                    }
                }

                $needFormRequest = ModuleModel::$needFormRequest;
                if($needFormRequest){
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl()
                    ];

                    $relocationServiceCompany = $needFormRequest->getRelocationServiceCompany();
                    if($action == 'sendNeedAssessmentFormOfRelocation'){
                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                        $customParams['questionnaire'] = [
                            'name' => $needFormRequest->getFormName()
                        ];

                        $relocationServiceCompany->sendHistoryToQueue(History::QUESTIONNAIRE_SENT, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::QUESTIONNAIRE_SENT, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                            'questionnaire' => $customParams['questionnaire']
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::QUESTIONNAIRE_SENT, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                            'questionnaire' => $customParams['questionnaire']
                        ]);
                    }
                }
            }

            //HomeSearch
            if($controller == AclHelper::CONTROLLER_HOME_SEARCH){
                $housingProposition = ModuleModel::$housingProposition;
                $relocationServiceCompany = ModuleModel::$housingProposition->getRelocationServiceCompany();

                if($relocationServiceCompany && $housingProposition){
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl()
                    ];

                    if($action == 'changeStatusHousingProposition'){
                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                        switch ($housingProposition->getStatus()){
                            case HousingProposition::STATUS_SUGGESTED:
                                $actionHistory = History::PROPERTY_SUGGESTED;
                                break;
                            case HousingProposition::STATUS_ACCEPTED:
                                $actionHistory = History::ASSIGNEE_WANTED_TO_VISIT_PROPERTY;
                                break;
                            case HousingProposition::STATUS_DECLINED:
                                $actionHistory = History::ASSIGNEE_DID_NOT_WANT_TO_VISIT_PROPERTY;
                                break;
                            default:
                                $actionHistory = '';
                                break;
                        }

                        if($actionHistory != ''){
                            $customParams['property'] = $housingProposition->getProperty() ? $housingProposition->getProperty()->toArray() : false;
                            $relocationServiceCompany->sendHistoryToQueue($actionHistory, $customParams);
                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'property' => $customParams['property']
                            ]);

                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, $actionHistory, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'property' => $customParams['property']
                            ]);
                        }

                    }
                    if($action == 'addVisiteEvent'){
                        $visite = ModuleModel::$housingVisiteEvent;
                        if($visite){
                            $offset = $visite->getTimezoneOffset() ? $visite->getTimezoneOffset() : 0;
                            $offsetUtc = 'UTC ' . ($offset > 0 ? '+' : '') . ($offset / 60);

                            $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                            $customParams['property'] = $housingProposition->getProperty() ? $housingProposition->getProperty()->toArray() : false;
                            $customParams['property']['property_visit_date_time'] = date('d/m/Y H:i:s', ($visite->getStart() + + $offset * 60)) . ' ' . $offsetUtc;
                            $relocationServiceCompany->sendHistoryToQueue(History::PROPERTY_VISIT_SET, $customParams);
                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::PROPERTY_VISIT_SET, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'property' => $customParams['property']
                            ]);

                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::PROPERTY_VISIT_SET, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'property' => $customParams['property']
                            ]);
                        }

                    }
                    if($action == 'changeSelectedProposition'){
                        $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                        $customParams['property'] = $housingProposition->getProperty() ? $housingProposition->getProperty()->toArray() : false;
                        $relocationServiceCompany->sendHistoryToQueue(History::PROPERTY_SET_AS_FINAL, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::PROPERTY_SET_AS_FINAL, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                            'property' => $customParams['property']
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::PROPERTY_SET_AS_FINAL, [
                            'uuid' => $relocationServiceCompany->getUuid(),
                            'property' => $customParams['property']
                        ]);
                    }

                }



            }

            //Task
            if ($controller == AclHelper::CONTROLLER_TASK) {
                $time = time();
                //create task
                $task = ModuleModel::$task;
                if ($task) {
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl()
                    ];

                    if ($action == 'create') {
                        $historyObjectUuids = [$task->getUuid()];

                        if ($task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT || $task->getLinkType() == Task::LINK_TYPE_SERVICE || $task->getLinkType() == Task::LINK_TYPE_RELOCATION) {
                            $historyObjectUuids[] = $task->getObjectUuid();
                        }
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = $task->getTaskType() == Task::TASK_TYPE_EE_TASK ? History::HISTORY_ASSIGNEE_TASK_CREATE : History::HISTORY_CREATE;

                        $task->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);

                        if($task->getTaskType() == TaskExt::TASK_TYPE_EE_TASK){
                            $employee = Employee::findFirstById($task->getEmployeeId());
                            $queueSendMail = RelodayQueue::__getQueueSendMail();
                            if ($employee) {
                                $dataArray = [
                                    'action' => "sendMail",
                                    'sender_name' => 'Notification',
                                    'to' => $employee->getPrincipalEmail(),
                                    'email' => $employee->getPrincipalEmail(),
                                    'language' => ModuleModel::$system_language,
                                    'templateName' => EmailTemplateDefault::CREATE_ASSIGNEE_TASK,
                                    'params' => [
                                        'username' => ModuleModel::$user_profile->getFullname(),
                                        'company_name' => ModuleModel::$user_profile->getCompanyName(),
                                        //target of Action
                                        'target_assignee_name' => $employee->getFullname(),
                                        'assignee_name' => $employee->getFullname(),
                                        'task_priority' => $task->getTaskPriority(ModuleModel::$system_language),
                                        'task_url' => $employee->getEmployeeUrl() . "/#/app/my-tasks?task=" . $task->getUuid(),
                                        'task_name' => $task->getName(),
                                        'task_number' => $task->getNumber(),
                                        'date' => date('d M Y - H:i:s'),
                                    ]
                                ];
                                $resultCheck = $queueSendMail->addQueue($dataArray);
                            }
                        }
                    }
                    if ($action == 'delete') {
                        $historyObjectUuids = [$task->getUuid()];

                        if ($task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT ||
                            $task->getLinkType() == Task::LINK_TYPE_SERVICE ||
                            $task->getLinkType() == Task::LINK_TYPE_RELOCATION) {
                            $historyObjectUuids[] = $task->getObjectUuid();
                        }
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = $task->getTaskType() == Task::TASK_TYPE_EE_TASK ? History::HISTORY_ASSIGNEE_TASK_DELETE : History::HISTORY_REMOVE;

                        $task->sendHistoryToQueue($actionHistory, $customParams);


                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);

                        if($task->getTaskType() == TaskExt::TASK_TYPE_EE_TASK){
                            $employee = Employee::findFirstById($task->getEmployeeId());
                            $queueSendMail = RelodayQueue::__getQueueSendMail();
                            if ($employee) {
                                $dataArray = [
                                    'action' => "sendMail",
                                    'sender_name' => 'Notification',
                                    'to' => $employee->getPrincipalEmail(),
                                    'email' => $employee->getPrincipalEmail(),
                                    'language' => ModuleModel::$system_language,
                                    'templateName' => EmailTemplateDefault::DELETE_ASSIGNEE_TASK,
                                    'params' => [
                                        'username' => ModuleModel::$user_profile->getFullname(),
                                        'company_name' => ModuleModel::$user_profile->getCompanyName(),
                                        //target of Action
                                        'target_assignee_name' => $employee->getFullname(),
                                        'assignee_name' => $employee->getFullname(),
                                        'task_priority' => $task->getTaskPriority(ModuleModel::$system_language),
                                        'task_name' => $task->getName(),
                                        'task_number' => $task->getNumber(),
                                        'date' => date('d M Y - H:i:s'),
                                    ]
                                ];
                                $resultCheck = $queueSendMail->addQueue($dataArray);
                            }
                        }
                    }
                    if ($action == 'updateTask'){
                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_UPDATE;

                        $task->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, History::HISTORY_UPDATE, [
                            'uuid' => $task->getUuid(),
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, History::HISTORY_UPDATE, [
                            'uuid' => $task->getUuid(),
                        ]);
                    }
                    if ($action == 'setDone' || $dispatcher->getActionName() == 'setTodo' || $dispatcher->getActionName() == 'setInProgress' || $dispatcher->getActionName() == 'setAwaitingFinalReview') {
                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_SET_TODO;
                        if ($task->isDone()) {
                            $actionHistory = HistoryModel::HISTORY_SET_DONE;
                        }

                        if ($task->isInProgress()) {
                            $actionHistory = History::HISTORY_SET_IN_PROGRESS;
                        }

                        if ($task->isAwaitingReview()) {
                            $actionHistory = HistoryModel::HISTORY_SET_AWAITING_FINAL_REVIEW;
                        }

                        $task->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                    }
                    if ($action == 'createSubTask'){
                        $subtask_name  = $dispatcher->getParam('subtask_name');
                        $customParams['subtask_name'] = $subtask_name ?: '';
                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $action = History::HISTORY_CREATE_SUBTASK;
                        $task->sendHistoryToQueue($action, $customParams);
                    }
                    if ($action == 'updateSubTask'){
                        $subtask_name  = $dispatcher->getParam('subtask_name');
                        $customParams['subtask_name'] = $subtask_name ?: '';

                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_UPDATE_SUBTASK;

                        $task->sendHistoryToQueue($actionHistory, $customParams);
                    }
                    if ($action == 'deleteSubTask'){
                        $subtask_name  = $dispatcher->getParam('subtask_name');
                        $customParams['subtask_name'] = $subtask_name ?: '';

                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_DELETE_SUBTASK;
                        $task->sendHistoryToQueue($actionHistory, $customParams);
                    }

                    if ($action == 'setPriority'){
                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;
                        switch ($task->getIsPriority()){
                            case Task::TASK_PRIORITY_LOW_TASK:
                                $actionHistory = History::HISTORY_CHANGE_PRIORITY_LOW;
                                break;
                            case Task::TASK_PRIORITY_HIGH_TASK:
                                $actionHistory = History::HISTORY_CHANGE_PRIORITY_HIGH;
                                break;
                            default:
                                $actionHistory = History::HISTORY_CHANGE_PRIORITY_MEDIUM;
                                break;
                        }

                        $task->sendHistoryToQueue($actionHistory, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                    }

                    if ($action == 'setFlag'){
                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;
                        $actionHistory = $task->getIsFlag() == ModelHelper::YES ? History::HISTORY_CHANGE_FLAG : History::HISTORY_CHANGE_UNFLAG;
                        $task->sendHistoryToQueue($actionHistory, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                    }

                    if ($action == 'setMilestone'){
                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;
                        $actionHistory = $task->getIsMilestone() == ModelHelper::YES ? History::HISTORY_CHANGE_MILESTONE_ON : History::HISTORY_CHANGE_MILESTONE_OFF;
                        $task->sendHistoryToQueue($actionHistory, $customParams);
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                        ]);
                    }
                }
            }
            //Task File
            if ($controller == AclHelper::CONTROLLER_TASK_FILE) {
                $task = ModuleModel::$task;
                $taskFile = ModuleModel::$taskFile;
                $customParams = [
                    'companyUuid' => ModuleModel::$company->getUuid(),
                    'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                    'language' => ModuleModel::$system_language,
                    'ip' => $request->getClientAddress(),
                    'appUrl' => ModuleModel::$app->getFrontendUrl(),
                    'taskFileName' => $taskFile->getName()
                ];

                if ($task && $taskFile) {
                    if ($dispatcher->getActionName() == 'save') {
                        //History
                        $historyObjectUuids = [$task->getUuid()];

                        if ($task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT || $task->getLinkType() == Task::LINK_TYPE_SERVICE || $task->getLinkType() == Task::LINK_TYPE_RELOCATION) {
                            $historyObjectUuids[] = $task->getObjectUuid();
                        }
                        $customParams['historyObjectUuids'] = $historyObjectUuids;
                        $actionHistory = $task->getTaskType() == Task::TASK_TYPE_EE_TASK ? History::HISTORY_ASSIGNEE_TASK_ADD_ATTACHMENT : History::HISTORY_ADD_ATTACHMENT;

                        $task->sendHistoryToQueue($actionHistory, $customParams);

                        // Notification
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                            'filename' => $taskFile->getName(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                            'filename' => $taskFile->getName(),
                        ]);

                        //Send mail
                        $employee = Employee::findFirstById($task->getEmployeeId());
                        $queueSendMail = RelodayQueue::__getQueueSendMail();
                        if ($employee && $task->getTaskType() == Task::TASK_TYPE_EE_TASK) {
                            $dataArray = [
                                'action' => "sendMail",
                                'sender_name' => 'Notification',
                                'to' => $employee->getPrincipalEmail(),
                                'email' => $employee->getPrincipalEmail(),
                                'language' => ModuleModel::$system_language,
                                'templateName' => EmailTemplateDefault::FILE_SHARED_BY_DSP_HR_TO_ASSIGNEE_TASK,
                                'params' => [
                                    'username' => ModuleModel::$user_profile->getFullname(),
                                    'company_name' => ModuleModel::$user_profile->getCompanyName(),
                                    //target of Action
                                    'filename' => $taskFile->getName(),
                                    'assignee_name' => $employee->getFullname(),
                                    'task_priority' => $task->getTaskPriority(ModuleModel::$system_language),
                                    'task_url' => $employee->getEmployeeUrl() . "/#/app/my-tasks?task=" . $task->getUuid(),
                                    'task_name' => $task->getName(),
                                    'task_number' => $task->getNumber(),
                                    'date' => date('d M Y - H:i:s'),
                                ]
                            ];
                            $resultCheck = $queueSendMail->addQueue($dataArray);
                        }
                    }
                    if ($dispatcher->getActionName() == 'delete') {
                        //History
                        $historyObjectUuids = [$task->getUuid()];

                        if ($task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT || $task->getLinkType() == Task::LINK_TYPE_SERVICE || $task->getLinkType() == Task::LINK_TYPE_RELOCATION) {
                            $historyObjectUuids[] = $task->getObjectUuid();
                        }
                        $customParams['historyObjectUuids'] = $historyObjectUuids;
                        $actionHistory = $task->getTaskType() == Task::TASK_TYPE_EE_TASK ? History::HISTORY_ASSIGNEE_TASK_DELETE_ATTACHMENT : History::HISTORY_REMOVE_ATTACHMENT;

                        $task->sendHistoryToQueue($actionHistory, $customParams);

                        // Notification
                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                            'filename' => $taskFile->getName(),
                        ]);

                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, $actionHistory, [
                            'uuid' => $task->getUuid(),
                            'filename' => $taskFile->getName(),
                        ]);
                    }
                }
            }

            //Comment
            if ($controller == AclHelper::CONTROLLER_COMMENTS) {
                $commentObject = ModuleModel::$comment;
                if ($commentObject) {
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl(),
                    ];

                    if ($action == 'createComment') {
                        $task = ModuleModel::$task;
                        if ($task) {
                            //History
                            $customParams['historyObjectUuids'] = [$task->getUuid()];

                            $actionHistory = History::HISTORY_ADD_COMMENT;
                            $task->sendHistoryToQueue($actionHistory, $customParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, History::HISTORY_ADD_COMMENT, [
                                'uuid' => $task->getUuid(),
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, History::HISTORY_ADD_COMMENT, [
                                'uuid' => $task->getUuid(),
                            ]);

                            if($task->getTaskType() == Task::TASK_TYPE_EE_TASK){
                                //Send mail
                                $employee = Employee::findFirstById($task->getEmployeeId());
                                $queueSendMail = RelodayQueue::__getQueueSendMail();
                                if ($employee) {
                                    $dataArray = [
                                        'action' => "sendMail",
                                        'sender_name' => 'Notification',
                                        'to' => $employee->getPrincipalEmail(),
                                        'email' => $employee->getPrincipalEmail(),
                                        'language' => ModuleModel::$system_language,
                                        'templateName' => EmailTemplateDefault::DSP_HR_COMMENT_ASSIGNEE_TASK,
                                        'params' => [
                                            'username' => ModuleModel::$user_profile->getFullname(),
                                            'company_name' => ModuleModel::$user_profile->getCompanyName(),
                                            'user_company_name' => ModuleModel::$user_profile->getCompanyName(),
                                            //target of Action
                                            'comment' => $commentObject ? $commentObject->getMessage() : '',
                                            'assignee_name' => $employee->getFullname(),
                                            'task_priority' => $task->getTaskPriority(ModuleModel::$system_language),
                                            'task_url' => $employee->getEmployeeUrl() . "/#/app/my-tasks?task=" . $task->getUuid(),
                                            'task_name' => $task->getName(),
                                            'task_number' => $task->getNumber(),
                                            'date' => date('d M Y - H:i:s'),
                                            'time' => date('d M Y - H:i:s'),
                                        ]
                                    ];
                                    $resultCheck = $queueSendMail->addQueue($dataArray);
                                }
                            }

                        }

                        //Assignment
                        $assignment = ModuleModel::$assignment;
                        if($assignment){
                            $customParams['historyObjectUuids'] = [$assignment->getUuid()];

                            $actionHistory = History::HISTORY_ADD_COMMENT;
                            $assignment->sendHistoryToQueue($actionHistory, $customParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($assignment, History::TYPE_ASSIGNMENT, History::HISTORY_ADD_COMMENT, [
                                'uuid' => $assignment->getUuid(),
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($assignment, History::TYPE_ASSIGNMENT, History::HISTORY_ADD_COMMENT, [
                                'uuid' => $assignment->getUuid(),
                            ]);
                        }
                        //Relocation
                        $relocation = ModuleModel::$relocation;
                        if($relocation){
                            $customParams['historyObjectUuids'] = [$relocation->getUuid()];

                            $actionHistory = History::HISTORY_ADD_COMMENT;
                            $relocation->sendHistoryToQueue($actionHistory, $customParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_ADD_COMMENT, [
                                'uuid' => $relocation->getUuid(),
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocation, History::TYPE_RELOCATION, History::HISTORY_ADD_COMMENT, [
                                'uuid' => $relocation->getUuid(),
                            ]);
                        }

                        $housingProposition = ModuleModel::$housingProposition;
                        if($housingProposition){
                            $relocationServiceCompany = $housingProposition->getRelocationServiceCompany();
                            $customParams['historyObjectUuids'] = [$relocationServiceCompany->getUuid()];
                            $customParams['property'] = $housingProposition->getProperty() ? $housingProposition->getProperty()->toArray() : false;
                            $actionHistory = History::PROPERTY_COMMENTED;
                            $relocationServiceCompany->sendHistoryToQueue($actionHistory, $customParams);

                            $notifyUser = NotificationServiceHelper::__addNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::PROPERTY_COMMENTED, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'property' => $customParams['property']
                            ]);
                            $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($relocationServiceCompany, History::TYPE_SERVICE, History::PROPERTY_COMMENTED, [
                                'uuid' => $relocationServiceCompany->getUuid(),
                                'property' => $customParams['property']
                            ]);
                        }
                    }
                }
            }

            //Reminder
            if ($controller == AclHelper::CONTROLLER_REMINDER){
                $task = ModuleModel::$task;
                if ($task) {
                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl()
                    ];

                    if ($action == 'createReminderConfig') {
                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_SET_REMINDER;

                        $task->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, History::HISTORY_SET_REMINDER, [
                            'uuid' => $task->getUuid(),
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, History::HISTORY_SET_REMINDER, [
                            'uuid' => $task->getUuid(),
                        ]);
                    }

                    if ($action == 'deleteReminderConfig') {

                        $historyObjectUuids = [$task->getUuid()];
                        $customParams['historyObjectUuids'] = $historyObjectUuids;

                        $actionHistory = History::HISTORY_STOP_REMINDER;

                        $task->sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser($task, History::TYPE_TASK, History::HISTORY_STOP_REMINDER, [
                            'uuid' => $task->getUuid(),
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser($task, History::TYPE_TASK, History::HISTORY_STOP_REMINDER, [
                            'uuid' => $task->getUuid(),
                        ]);
                    }

                }
            }

            //DataUserMember
            if ($controller == 'data-member'){
                $object_uuid = $dispatcher->getParam('object_uuid');
                $target_user_uuid = $dispatcher->getParam('target_user_uuid');
                $target_user = $dispatcher->getParam('target_user');

                $object = Assignment::findFirstByUuid($object_uuid);
                if(!$object){
                    $object = Relocation::findFirstByUuid($object_uuid);
                }

                if(!$object){
                    $object = RelocationServiceCompany::findFirstByUuid($object_uuid);
                }

                if(!$object){
                    $object = Task::findFirstByUuid($object_uuid);
                }
                if ($object && $target_user_uuid){
                    switch ($action){
                        case 'setOwner':
                            $actionHistory = HistoryModel::HISTORY_SET_OWNER;
                            break;
                        case 'setReporter':
                            $actionHistory = HistoryModel::HISTORY_SET_REPORTER;
                            break;
                        case 'setViewer':
                            $actionHistory = HistoryModel::HISTORY_ADD_VIEWER;
                            break;
                        case 'removeViewer':
                            $actionHistory = HistoryModel::HISTORY_REMOVE_VIEWER;
                            break;
                        case 'removeOwner':
                            $actionHistory = HistoryModel::HISTORY_REMOVE_OWNER;
                            break;
                        default:
                            $actionHistory = '';
                            break;
                    }

                    $customParams = [
                        'companyUuid' => ModuleModel::$company->getUuid(),
                        'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                        'language' => ModuleModel::$system_language,
                        'ip' => $request->getClientAddress(),
                        'appUrl' => ModuleModel::$app->getFrontendUrl(),
                        'target_user_uuid' => $target_user_uuid,
                        'object_uuid' => $object_uuid,
                        'historyObjectUuids' => [$object_uuid]
                    ];

                    if($actionHistory){
                        DataUserMember::__sendHistoryToQueue($actionHistory, $customParams);

                        $notifyUser = NotificationServiceHelper::__addNotificationForUser(['uuid' => $object_uuid], History::TYPE_OTHERS, $actionHistory, [
                            'target_user' => $target_user
                        ]);
                        $sendPushUser = NotificationServiceHelper::__sendPushNotificationForUser(['uuid' => $object_uuid], History::TYPE_OTHERS, $actionHistory, [
                            'target_user' => $target_user
                        ]);
                    }

                }
            }
        }
    }
}