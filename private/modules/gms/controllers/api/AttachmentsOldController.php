<?php

namespace Reloday\Gms\Controllers\API;

use Http\Client\Common\Plugin\HistoryPlugin;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\HrCompany;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectFolder;
use Reloday\Gms\Models\ObjectMap;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Subscription;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\AssignmentRequest;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AttachmentsOldController extends BaseController
{

    /**
     * @param $uuid
     */
    public function listAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_ATTACHMENTS);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid == '') {
            $uuid = Helpers::__getRequestValue('uuid');
        }
        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');
        $objectNameRequired = Helpers::__getRequestValue('objectNameRequired');
        $companyUuid = Helpers::__getRequestValue('companyUuid');
        $employeeUuid = Helpers::__getRequestValue('employeeUuid');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            if ($companyUuid == '' && $employeeUuid == '') {
                $return = MediaAttachment::__findWithFilter([
                    'limit' => 1000,
                    'object_uuid' => $uuid,
                    'object_name' => $objectNameRequired != '' ? $objectNameRequired : false,
//                    'is_shared' => false,
//                    'sharer_uuid' => ModuleModel::$company->getUuid()
                ]);
            } else {
                $sharer_uuids = [];
                $sharer_uuids[] = ModuleModel::$company->getUuid();
                if ($companyUuid != '' && Helpers::__isValidUuid($companyUuid)) {
                    $sharer_uuids[] = $companyUuid;
                }
                if ($employeeUuid != '' && Helpers::__isValidUuid($employeeUuid)) {
                    $sharer_uuids[] = $employeeUuid;
                }
                $return = MediaAttachment::__findWithFilter([
                    'limit' => 1000,
//                    'is_shared' => true,
                    'object_name' => $objectNameRequired != '' ? $objectNameRequired : false,
                    'object_uuid' => $uuid,
//                    'sharer_uuids' => $sharer_uuids
                ]);
            }
        }
        $return['company'] = Helpers::__isValidUuid($companyUuid) ? Company::findFirstByUuidCache($companyUuid) : null;
        $return['employee'] = Helpers::__isValidUuid($employeeUuid) ? Employee::findFirstByUuidCache($employeeUuid) : null;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Attachm multiple files in object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function attachMultipleFilesAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclCreate(AclHelper::CONTROLLER_ATTACHMENTS);

        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $companyUuid = Helpers::__getRequestValue('companyUuid');
        $employeeUuid = Helpers::__getRequestValue('employeeUuid');
        $attachments = Helpers::__getRequestValue('attachments');
        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');
        $objectNameRequired = Helpers::__getRequestValue('objectNameRequired');

        if (Helpers::__isValidUuid($companyUuid)) {
            $company = HrCompany::findFirstByUuid($companyUuid);
            if ($company && $company->isHr()) {
                $activeContract = ModuleModel::$company->getActiveContract($company->getId());
                if (!$activeContract) {
                    $return = ['success' => false, 'message' => 'CONTRACT_DOES_NOT_EXIST_TEXT', 'company' => $company];
                    goto end_of_function;
                }
                $subscription = Subscription::findFirstByCompanyId(ModuleModel::$company->getId());
                if (!$subscription instanceof Subscription) {
                    $return = ['success' => false, 'message' => 'SUBSCRIPTION_DOES_NOT_EXIST_TEXT', 'company' => $company];
                    goto end_of_function;
                }
            }
        }

        if (Helpers::__isValidUuid($employeeUuid)) {
            $employee = Employee::findFirstByUuid($employeeUuid);
            if (!$employee || !$employee->belongsToGms()) {
                $return = ['success' => false, 'message' => 'EMPLOYEE_NOT_EXIST_TEXT', 'employee' => $employee];
                goto end_of_function;
            }
        }

        $fileNames = '';

        if ($uuid != '' &&
            Helpers::__isValidUuid($uuid) &&
            is_array($attachments) &&
            count($attachments) > 0) {

            if (count($attachments) > 0) {
                $this->db->begin();
                $initiation_request = AssignmentRequest::findFirstByUuid($uuid);
                if ($initiation_request) {
                    $assignment = $initiation_request->getAssignment();
                    $folder = $assignment->getHrFolder($initiation_request->getOwnerCompanyId());
                }
                $items = [];
                foreach ($attachments as $attachment) {
                    $attachResult = MediaAttachment::__createAttachment([
                        'objectUuid' => $uuid,
                        'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                        'file' => $attachment,
                        'userProfile' => ModuleModel::$user_profile,
                    ]);

                    if (isset($folder) && $folder) {
                        $attachResult_assignment_folder = MediaAttachment::__createAttachment([
                            'objectUuid' => $folder->getUuid(),
                            'file' => $attachment,
                            'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                            'userProfile' => ModuleModel::$user_profile,
                        ]);
                    }

                    if (is_object($attachment) && property_exists($attachment, 'extension')) {
                        $fileNames .= $attachment->name . "." . $attachment->extension . ";";
                    } elseif (is_object($attachment) && property_exists($attachment, 'file_extension')) {
                        $fileNames .= $attachment->name . "." . $attachment->file_extension . ";";
                    } elseif (is_array($attachment) && isset($attachment['file_extension'])) {
                        $fileNames .= $attachment['name'] . "." . $attachment['file_extension'] . ";";
                    } elseif (is_array($attachment) && isset($attachment['extension'])) {
                        $fileNames .= $attachment['name'] . "." . $attachment['extension'] . ";";
                    }


                    if ($attachResult['success'] == true) {
                        //share to my own company
                        $mediaAttachment = $attachResult['data'];
                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $uuid], ModuleModel::$company);
                        if ($shareResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                            goto end_of_function;
                        }


                        if (isset($company) && $company) {
                            $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $uuid], $company);
                            if ($shareResult['success'] == false) {
                                $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                                goto end_of_function;
                            }

                            $updateResult = $mediaAttachment->setForceShared();
                            if ($updateResult['success'] == false) {
                                $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $updateResult];
                                goto end_of_function;
                            }
                        }

                        if (isset($employee) && $employee) {
                            $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $uuid], $employee);
                            if ($shareResult['success'] == false) {
                                $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                                goto end_of_function;
                            }

                            $updateResult = $mediaAttachment->setForceShared();
                            if ($updateResult['success'] == false) {
                                $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $updateResult];
                                goto end_of_function;
                            }
                        }


                        $items[] = $mediaAttachment;


                    } else {
                        $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $attachResult];
                        goto end_of_function;
                    }
                }
                $this->db->commit();
                $return = [
                    'success' => true,
                    'data' => $items,
                    'message' => 'ATTACH_SUCCESS_TEXT',
                    'employee' => isset($employee) ? $employee : null,
                ];
                goto end_of_function;
            }
            /*
            $return = MediaAttachment::__createAttachments([
                'objectUuid' => $uuid,
                'objectName' => $type,
                'isShared' => $shared,
                'fileList' => $attachments,
                'userProfile' => ModuleModel::$user_profile,
                'entityUuid' => $entity_uuid
            ]);
            */
        }
        end_of_function:

        if ($return['success'] == true) {
            $type = RelodayObjectMapHelper::__getHistoryTypeObject($uuid);
            if ($type == HistoryModel::TYPE_ASSIGNMENT) {
                $assignment = Assignment::__findFirstByUuidCache($uuid, CacheHelper::__TIME_24H);
                if ($return['success'] == true && isset($assignment) && $assignment && $assignment->belongsToGms()) {
                    ModuleModel::$assignment = $assignment;
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_ADD_ATTACHMENT);
                }

                if ($return['success'] == true && isset($employee) && isset($assignment) && $assignment && $assignment->belongsToGms()) {
                    $beanQueue = RelodayQueue::__getQueueSendMail();
                    $dataArray = [
                        'to' => $employee->getPrincipalEmail(),
                        'email' => $employee->getPrincipalEmail(),
                        'language' => ModuleModel::$system_language,
                        'templateName' => EmailTemplateDefault::FILE_SHARED_WITH_EE,
                        'params' => [
                            'url' => $employee->getDocumentsEmployeeUrl(),
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'assignee_name' => $employee->getFullname(),
                            'assignment_number' => $assignment->getNumber(),
                            'company_name' => ModuleModel::$company->getName(),
                            'file_name' => $fileNames,
                            'time' => date('Y-m-d H:i:s')
                        ]
                    ];
                    $resultCheck = $beanQueue->sendMail($dataArray);
                    $return['resultCheck'] = $resultCheck;
                }
            } else if ($type == HistoryModel::TYPE_RELOCATION) {
                $relocation = Relocation::__findFirstByUuidCache($uuid, CacheHelper::__TIME_24H);
                if ($return['success'] == true && isset($relocation) && $relocation && $relocation->belongsToGms()) {
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_ADD_ATTACHMENT);
                }

                if ($relocation) {
                    $assignment = $relocation->getAssignment();
                    ModuleModel::$relocation = $relocation;
                }

                if ($return['success'] == true && isset($assignment) && $assignment && $assignment->belongsToGms()) {
                    ModuleModel::$assignment = $assignment;
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_ADD_ATTACHMENT);
                }

                if ($return['success'] == true && isset($employee) && isset($assignment) && $assignment && $assignment->belongsToGms()) {
                    $beanQueue = RelodayQueue::__getQueueSendMail();
                    $dataArray = [
                        'to' => $employee->getPrincipalEmail(),
                        'email' => $employee->getPrincipalEmail(),
                        'language' => ModuleModel::$system_language,
                        'templateName' => EmailTemplateDefault::FILE_SHARED_WITH_EE,
                        'params' => [
                            'url' => $employee->getDocumentsEmployeeUrl(),
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'assignee_name' => $employee->getFullname(),
                            'assignment_number' => $assignment->getNumber(),
                            'company_name' => ModuleModel::$company->getName(),
                            'file_name' => $fileNames,
                            'time' => date('Y-m-d H:i:s')
                        ]
                    ];
                    $resultCheck = $beanQueue->sendMail($dataArray);
                    $return['resultCheck'] = $resultCheck;
                }


                $return['typeFile'] = $type;
            } else {
                $initiation_request = AssignmentRequest::findFirstByUuid($uuid);
                if ($initiation_request) {
                    $assignment = $initiation_request->getAssignment();
//                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($initiation_request, HistoryModel::TYPE_ASSIGNMENT_REQUEST, HistoryModel::HISTORY_ADD_ATTACHMENT);
                    if ($assignment) {
                        $hr_members = $assignment->getHrMembers();
                        $employee = $assignment->getEmployee();

                        $hr_company = $assignment->getCompany();
                        if (count($hr_members) > 0) {
                            $resultChecks = [];
                            $beanQueue = RelodayQueue::__getQueueSendMail();
                            foreach ($hr_members as $hr_member) {
                                $dataArray = [
                                    'to' => $hr_member->getPrincipalEmail(),
                                    'email' => $hr_member->getPrincipalEmail(),
                                    'language' => ModuleModel::$system_language,
                                    'templateName' => EmailTemplateDefault::FILE_SHARED_BY_DSP_TO_HR,
                                    'params' => [
                                        'url' => $assignment->getHrFrontendUrl($hr_company),
                                        'username' => ModuleModel::$user_profile->getFullname(),
                                        'assignee_name' => $employee->getFullname(),
                                        'receiver_name' => $hr_member->getFullname(),
                                        'assignment_number' => $assignment->getNumber(),
                                        'company_name' => ModuleModel::$company->getName(),
                                        'file_name' => $fileNames,
                                        'time' => date('Y-m-d H:i:s')
                                    ]
                                ];
                                $resultChecks[] = $beanQueue->sendMail($dataArray);
                            }
                            $return['resultCheck'] = $resultChecks;
                        }
                    }
                } else {
                    $object_folder = ObjectFolder::findFirstByUuid($uuid);
                    if ($object_folder && $object_folder->getDspCompanyId() == ModuleModel::$company->getId() && $object_folder->getHrCompanyId() > 0) {
                        $assignment = $object_folder->getAssignment();
                        if ($assignment) {
                            $hr_members = $assignment->getHrMembers();
                            $employee = $assignment->getEmployee();
                            $hr_company = $assignment->getCompany();
                            if (count($hr_members) > 0) {
                                $resultChecks = [];
                                $beanQueue = RelodayQueue::__getQueueSendMail();
                                foreach ($hr_members as $hr_member) {
                                    $dataArray = [
                                        'to' => $hr_member->getPrincipalEmail(),
                                        'email' => $hr_member->getPrincipalEmail(),
                                        'language' => ModuleModel::$system_language,
                                        'templateName' => EmailTemplateDefault::FILE_SHARED_BY_DSP_TO_HR,
                                        'params' => [
                                            'url' => $assignment->getHrFrontendUrl($hr_company),
                                            'assignee_name' => $employee->getFullname(),
                                            'username' => ModuleModel::$user_profile->getFullname(),
                                            'receiver_name' => $hr_member->getFullname(),
                                            'assignment_number' => $assignment->getNumber(),
                                            'company_name' => ModuleModel::$company->getName(),
                                            'file_name' => $fileNames,
                                            'time' => date('Y-m-d H:i:s')
                                        ]
                                    ];
                                    $resultChecks[] = $beanQueue->sendMail($dataArray);
                                }
                                $return['resultCheck'] = $resultChecks;
                            }
                        }
                    }
                }
            }


            if ($type == HistoryModel::TYPE_SERVICE) {
                $service = RelocationServiceCompany::__findFirstByUuidCache($uuid, CacheHelper::__TIME_24H);
                if ($return['success'] == true && isset($service) && $service && $service->belongsToGms()) {
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($service, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_ADD_ATTACHMENT);
                }
            }


            if ($type == HistoryModel::TYPE_TASK || $type == HistoryModel::TYPE_OTHERS) {
                $task = Task::findFirstByUuidCache($uuid);
                if ($return['success'] == true && isset($task) && $task && $task->belongsToGms()) {
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_ADD_ATTACHMENT);
                }
            }

            /** check employee model */
            if (isset($employee)) {
                ModuleModel::$employee = $employee;
            } else {
                $employee = Employee::findFirstByUuidCache($uuid);
                if ($employee) {
                    ModuleModel::$employee = $employee;
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Attachment only 1 file to object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function attachSingleFileAction()
    {

        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclCreate(AclHelper::CONTROLLER_ATTACHMENTS);

        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $file = Helpers::__getRequestValueAsArray('file');
        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');
        $objectNameRequired = Helpers::__getRequestValue('objectNameRequired');

        if ($uuid != '' &&
            Helpers::__isValidUuid($uuid) &&
            (is_array($file) || is_object($file))) {

            $return = MediaAttachment::__createAttachment([
                'objectUuid' => $uuid,
                'file' => $file,
                'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                'userProfile' => ModuleModel::$user_profile,
            ]);
            //$return = MediaAttachment::__create_attachment_from_uuid($uuid, $file, $type, $shared, ModuleModel::$user_profile);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Attachment only 1 file to object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function attachAvatarFileAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclCreate(AclHelper::CONTROLLER_ATTACHMENTS);

        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $file = Helpers::__getRequestValueAsArray('file');

        if ($uuid != '' &&
            Helpers::__isValidUuid($uuid) &&
            (is_array($file) || is_object($file))) {

            $return = MediaAttachment::__createAttachment([
                'objectUuid' => $uuid,
                'file' => $file,
                'objectName' => MediaAttachment::MEDIA_GROUP_AVATAR,
                'userProfile' => ModuleModel::$user_profile,
            ]);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Attachment only 1 file to object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function attachLogoFileAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclCreate(AclHelper::CONTROLLER_ATTACHMENTS);

        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $file = Helpers::__getRequestValueAsArray('file');

        if ($uuid != '' &&
            Helpers::__isValidUuid($uuid) &&
            (is_array($file) || is_object($file))) {

            $return = MediaAttachment::__createAttachment([
                'objectUuid' => $uuid,
                'file' => $file,
                'objectName' => MediaAttachment::MEDIA_GROUP_LOGO,
                'userProfile' => ModuleModel::$user_profile,
            ]);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
