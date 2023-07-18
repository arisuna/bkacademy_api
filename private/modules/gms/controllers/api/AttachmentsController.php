<?php

namespace Reloday\Gms\Controllers\API;

use Http\Client\Common\Plugin\HistoryPlugin;
use Phalcon\Security\Random;
use Reloday\Application\DynamoDb\ORM\DynamoMediaCountExt;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\DynamoHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\HttpStatusCode;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Application\Lib\RelodayUrlHelper;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HistoryAction;
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
class AttachmentsController extends BaseController
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
        $nullFolder = Helpers::__getRequestValue('nullFolder');
        $folderUuid = Helpers::__getRequestValue('folderUuid');
        $query = Helpers::__getRequestValue('query');
        $limit = Helpers::__getRequestValue('limit');
        $page = Helpers::__getRequestValue('page');

        if (($uuid != '' && $type == MediaAttachment::MEDIA_OBJECT_RELOCATION_SERVICE_SPECIFIC_FIELDS) ||
            ($uuid != '' && Helpers::__isValidUuid($uuid))) {
            if ($companyUuid == '' && $employeeUuid == '') {
                $return = MediaAttachment::__findWithFilter([
                    'limit' => $limit ? $limit : 10000,
                    'page' => $page,
                    'object_uuid' => $uuid,
                    'object_name' => $objectNameRequired != '' ? $objectNameRequired : false,
                    'query' => $query
                ]);
            } else {
                $params = [
                    'limit' => $limit ? $limit : 10000,
                    'page' => $page,
                    'object_name' => $objectNameRequired != '' ? $objectNameRequired : false,
                    'object_uuid' => $uuid,
                    'query' => $query
                ];
                $sharer_uuids = [];
                if ($companyUuid != '' && Helpers::__isValidUuid($companyUuid)) {
                    $sharer_uuids[] = $companyUuid;
                }
                if ($employeeUuid != '' && Helpers::__isValidUuid($employeeUuid)) {
                    $sharer_uuids[] = $employeeUuid;
                    $sharer_uuids[] = ModuleModel::$company->getUuid();
//                    $params['sharer_uuids'] = $sharer_uuids;
                    if ($nullFolder != '') {
                        $params['nullFolder'] = ModelHelper::YES;
                    }

                    if ($folderUuid != '' && Helpers::__isValidUuid($folderUuid)) {
                        $params['folder_uuid'] = $folderUuid;
                    }
                }
                $return = MediaAttachment::__findWithFilter($params);
            }
        }
        $return['company'] = Helpers::__isValidUuid($companyUuid) ? Company::findFirstByUuidCache($companyUuid) : null;
        $return['employee'] = Helpers::__isValidUuid($employeeUuid) ? Employee::findFirstByUuidCache($employeeUuid) : null;
        if ($employeeUuid != '' && Helpers::__isValidUuid($employeeUuid) && $nullFolder == '' && $folderUuid == '') {
            $return['folders'] = Helpers::groupGetKeyValue('assignee_folder_uuid', 'assignee_folder_name', $return['data'], true);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Attachm multiple files in object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
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

        /** CHECK DRAG AND DROP MEDIA ($attachments is media uuid)*/
        if (Helpers::__isValidUuid($attachments) && !is_array($attachments)) {
            $media = Media::findFirstByUuid($attachments);
            $attachments = [$media];
        }

        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');
        $objectNameRequired = Helpers::__getRequestValue('objectNameRequired');
        $items = [];

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

        if (($uuid != '' && $type == MediaAttachment::MEDIA_OBJECT_RELOCATION_SERVICE_SPECIFIC_FIELDS) ||
            ($uuid != '' &&
                Helpers::__isValidUuid($uuid) &&
                is_array($attachments) &&
                count($attachments) > 0)) {

            if (count($attachments) > 0) {
                $this->db->begin();
                $initiation_request = AssignmentRequest::findFirstByUuid($uuid);
                if ($initiation_request) {
                    $assignment = $initiation_request->getAssignment();
                    $folder = $assignment->getHrFolder($initiation_request->getOwnerCompanyId());
                }

                foreach ($attachments as $attachment) {
                    if (isset($folder) && $folder) {
                        $attachResult_assignment_folder = MediaAttachment::__createAttachment([
                            'objectUuid' => $folder->getUuid(),
                            'file' => $attachment,
                            'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                            'userProfile' => ModuleModel::$user_profile,
                        ]);

                        $attachmentItemResult = isset($attachResult_assignment_folder) && $attachResult_assignment_folder['success'] ?  $attachResult_assignment_folder['data'] : [];
                        if($attachmentItemResult){
                            $items[] = $attachmentItemResult;
                        }
                    }

                    if (isset($company) && $company) {
                        $attachResult = MediaAttachment::__createAttachment([
                            'objectUuid' => $uuid,
                            'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                            'file' => $attachment,
                            'employeeId' => null,
                            'companyId' => $company->getId(),
                            'userProfile' => ModuleModel::$user_profile,
                            'sharerActor' => $company,
                            'is_shared' => true
                        ]);

                        $attachmentItemResult = isset($attachResult) && $attachResult['success'] ?  $attachResult['data'] : [];
                        if($attachmentItemResult){
                            $items[] = $attachmentItemResult;
                        }
                    }

                    if (isset($employee) && $employee) {
                        $attachResult = MediaAttachment::__createAttachment([
                            'objectUuid' => $uuid,
                            'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                            'file' => $attachment,
                            'employeeId' => $employee->getId(),
                            'companyId' => null,
                            'userProfile' => ModuleModel::$user_profile,
                            'sharerActor' => $employee,
                            'is_shared' => true
                        ]);

                        $attachmentItemResult = isset($attachResult) && $attachResult['success'] ?  $attachResult['data'] : [];
                        if($attachmentItemResult){
                            $items[] = $attachmentItemResult;
                        }
                    }

                    if (!isset($employee) && !isset($company) && ModuleModel::$company) {
                        $attachResult = MediaAttachment::__createAttachment([
                            'objectUuid' => $uuid,
                            'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                            'file' => $attachment,
                            'employeeId' => null,
                            'companyId' => ModuleModel::$company->getId(),
                            'userProfile' => ModuleModel::$user_profile,
                            'sharerActor' => ModuleModel::$company,
                            'is_shared' => false
                        ]);

                        $attachmentItemResult = isset($attachResult) && $attachResult['success'] ?  $attachResult['data'] : [];
                        if($attachmentItemResult){
                            $items[] = $attachmentItemResult;
                        }

                    }

                    if (is_object($attachment) && property_exists($attachment, 'extension')) {
                        $name = method_exists($attachment, 'getName') ? $attachment->getName() : $attachment->name;
                        $extension = method_exists($attachment, 'getExtension') ? $attachment->getExtension() : $attachment->extension;
                        $fileNames .= $name . "." . $extension . ";";
                    } elseif (is_object($attachment) && property_exists($attachment, 'file_extension')) {
                        $name = method_exists($attachment, 'getName') ? $attachment->getName() : $attachment->name;
                        $fileExtension = method_exists($attachment, 'getFileExtension') ? $attachment->getFileExtension() : $attachment->file_extension;
                        $fileNames .= $name . "." . $fileExtension . ";";
                    } elseif (is_array($attachment) && isset($attachment['file_extension'])) {
                        $fileNames .= $attachment['name'] . "." . $attachment['file_extension'] . ";";
                    } elseif (is_array($attachment) && isset($attachment['extension'])) {
                        $fileNames .= $attachment['name'] . "." . $attachment['extension'] . ";";
                    }

                    if ($attachResult['success'] == false) {
                        $return = ['success' => false,
                            'errorType' => 'MediaAttachmentError',
                            'detail' => $attachResult,
                            'message' => isset($attachResult['message']) ? $attachResult['message'] : 'ATTACH_FAILED_TEXT'
                        ];
                        goto end_of_function;
                    }

                    /** COUNT MEDIA ATTACHMENT */
                    $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), ['sharing' => 1]);

                    if ($resultMediaCount['success'] == false) {
                        $return = $resultMediaCount;
                        $return['message'] = "ATTACH_FAILED_TEXT";
                        goto end_of_function;
                    }
                }
                $this->db->commit();

                $arrData = [];
                if(count($items) > 0){
                    foreach ($items as $item){
                        $mediaAttachment = MediaAttachment::findFirstByUuid($item->getUuid());
                        $arrData[] = $mediaAttachment->parsedDataToArray();
                    }
                }


                $return = [
                    'success' => true,
                    'data' => $arrData,
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
            if ($type == 'relocation') {
                $type = HistoryHelper::TYPE_RELOCATION;
            } else if ($type == 'assignment') {
                $type = HistoryHelper::TYPE_ASSIGNMENT;
            } else {
                $type = RelodayObjectMapHelper::__getHistoryTypeObject($uuid);
            }

            $this->dispatcher->setParam('file_count', count($items));
            $this->dispatcher->setParam('filename', $fileNames);

            if ($type == HistoryHelper::TYPE_ASSIGNMENT) {
                $object_folder = ObjectFolder::findFirstByUuid($uuid);
                ModuleModel::$objectFolder = $object_folder;
                if ($object_folder) {
                    $object_uuid = $object_folder->getObjectUuid();
                    $assignment = Assignment::__findFirstByUuidCache($object_uuid);
                }
                if ($return['success'] == true && isset($assignment) && $assignment && $assignment->belongsToGms()) {
                    ModuleModel::$assignment = $assignment;
                    //History
                    $this->dispatcher->setParam('type', $type);
                    $this->dispatcher->setParam('return', $return);
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_ADD_ATTACHMENT, [
                        'file_name' => $fileNames
                    ]);
                    if ($object_folder->isSharedWithHRM()) {
                        $hrOwner = $assignment->getHrOwner();
                        //Send email notification to hr owner of assignment
                        if ($hrOwner) {
                            $employeeSendMail = $assignment->getEmployee();
                            $hr_company = $assignment->getCompany();
                            $beanQueue = RelodayQueue::__getQueueSendMail();
                            $dataArray = [
                                'to' => $hrOwner->getPrincipalEmail(),
                                'email' => $hrOwner->getPrincipalEmail(),
                                'language' => ModuleModel::$system_language,
                                'templateName' => EmailTemplateDefault::FILE_SHARED_BY_DSP_TO_HR,
                                'params' => [
                                    'url' => $assignment->getHrFrontendUrl($hr_company),
                                    'assignee_name' => $employeeSendMail->getFullname(),
                                    'username' => ModuleModel::$user_profile->getFullname(),
                                    'receiver_name' => $hrOwner->getFullname(),
                                    'assignment_number' => $assignment->getNumber(),
                                    'company_name' => ModuleModel::$company->getName(),
                                    'file_name' => $fileNames,
                                    'time' => date('d M Y - H:i:s'),
                                ]
                            ];
                            $return['resultsHr'] = $beanQueue->sendMail($dataArray);
                        }
                    }
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
//                            'company_name' => $employee->getCompany() ? $employee->getCompany()->getName() : "",
                            'file_name' => $fileNames,
                            'time' => date('d M Y - H:i:s'),
                        ]
                    ];
                    $resultCheck = $beanQueue->sendMail($dataArray);
                    $return['resultCheck'] = $resultCheck;
                }
            } else if ($type == HistoryModel::TYPE_RELOCATION) {
                $object_folder = ObjectFolder::findFirstByUuid($uuid);
                ModuleModel::$objectFolder = $object_folder;
                if ($object_folder) {
                    $object_uuid = $object_folder->getObjectUuid();
                    $assignment = Assignment::__findFirstByUuidCache($object_uuid);
                    if ($assignment && $assignment instanceof Assignment) {
                        $relocation = $assignment->getRelocation();
                    } else {
                        $relocation = Relocation::__findFirstByUuidCache($object_uuid, CacheHelper::__TIME_24H);
                        $assignment = $relocation->getAssignment();
                    }
                    ModuleModel::$assignment = $assignment;
                }


                if ($return['success'] == true && isset($relocation) && $relocation && $relocation->belongsToGms()) {
                    ModuleModel::$relocation = $relocation;
                    //History
                    $this->dispatcher->setParam('type', $type);
                    $this->dispatcher->setParam('return', $return);
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_ADD_ATTACHMENT,  [
                        'file_name' => $fileNames
                    ]);
                }

//                if ($return['success'] == true && isset($assignment) && $assignment && $assignment->belongsToGms()) {
//                    ModuleModel::$assignment = $assignment;
//                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_ADD_ATTACHMENT);
//                }

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
//                            'company_name' => $employee->getCompany() ? $employee->getCompany()->getName() : "",
                            'file_name' => $fileNames,
                            'time' => date('d M Y - H:i:s'),
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
                                        'time' => date('d M Y - H:i:s'),
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
                            //History
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
                                            'time' => date('d M Y - H:i:s'),
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
                if (isset($service) && $service && $service->belongsToGms()) {
                    ModuleModel::$relocationServiceCompany = $service;
                    $this->dispatcher->setParam('type', $type);
                    $this->dispatcher->setParam('return', $return);
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($service, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_ADD_ATTACHMENT, [
                        'file_name' => $fileNames
                    ]);
                }
            }


            if ($type == HistoryModel::TYPE_TASK || $type == HistoryModel::TYPE_OTHERS) {
                $task = Task::findFirstByUuidCache($uuid);
                if ($return['success'] == true && isset($task) && $task && $task->belongsToGms()) {
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($task, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_ADD_ATTACHMENT, [
                        'file_name' => $fileNames
                    ]);
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


    public function listSharedWithMeAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);
//        $uuid = Helpers::__getRequestValue('uuid');
//        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $lastObject = Helpers::__getRequestValue('lastObject');
        $lastEvaluatedKey = Helpers::__getRequestValue('lastObject');
        $limit = Helpers::__getRequestValue('limit');
        $page = Helpers::__getRequestValue('page');
        $query = Helpers::__getRequestValue('query');

        $return = MediaAttachment::__findSharedWithMe([
            'limit' => $limit ?: Media::LIMIT_PER_PAGE,
            'page' => $page,
            'query' => $query,
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
//            'object_uuid' => $uuid
        ]);

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Remove Attachment
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function removeAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $canDeleteOwn = $this->canAccessResource(AclHelper::CONTROLLER_ATTACHMENTS, AclHelper::ACTION_DELETE_OWN);
        $canDeleteAll = $this->canAccessResource(AclHelper::CONTROLLER_ATTACHMENTS, AclHelper::ACTION_DELETE);

        if ($canDeleteOwn['success'] == false && $canDeleteAll['success'] == false) {
            $return = $canDeleteAll;
            goto end_of_function;
        }

        /***** check attachments permission ***/

        $data = $this->request->getJsonRawBody();
        $object_uuid = isset($data->object_uuid) ? $data->object_uuid : null;
        $object_name = isset($data->object_name) ? $data->object_name : null;
        $media_uuid = isset($data->media_uuid) ? $data->media_uuid : null;
        $type = isset($data->type) ? $data->type : null;
        $media_attachment_uuid = isset($data->media_attachment_uuid) ? $data->media_attachment_uuid : null;

        $filename = '';

        if ($media_attachment_uuid && Helpers::__getRequestValue('media_attachment_uuid')) {
            $mediaAttachment = MediaAttachment::findFirstByUuid($media_attachment_uuid);
        } else {
            if (is_null($object_uuid) || is_null($media_uuid)) {
                $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT', 'data' => $data];
                goto end_of_function;
            }
            $mediaAttachment = MediaAttachment::findFirst([
                'conditions' => 'media_uuid = :media_uuid: AND object_uuid = :object_uuid:',
                'bind' => [
                    'object_uuid' => $object_uuid,
                    'media_uuid' => $media_uuid,
                ]
            ]);
        }

        if ($mediaAttachment) {
            if ($canDeleteAll['success'] == true) {
                if ($mediaAttachment->belongsToGms() == true || (!$mediaAttachment->getUserProfileUuid() && !$mediaAttachment->getOwnerCompanyId())) {
                    $this->db->begin();

                    $filename = $mediaAttachment->getMedia()->getName() . "." . $mediaAttachment->getMedia()->getFileExtension() ;
                    $this->dispatcher->setParam('filename', $filename);
                    $object_uuid = $mediaAttachment->getObjectUuid();

                    $resultDelete = $mediaAttachment->__quickRemove();
                    if ($resultDelete['success'] == true) {
                        $this->db->commit();
                        /** COUNT MEDIA */
                        $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), ['sharing' => -1]);
                        if ($resultMediaCount['success'] == false) {
                            $return = $resultMediaCount;
                            $return['message'] = "ATTACHMENT_DELETE_FAIL_TEXT";
                            goto end_of_function;
                        }

                        $return = ['success' => true, 'message' => 'ATTACHMENT_DELETE_SUCCESS_TEXT', 'data' => $data];
                        goto end_of_function;
                    } else {
                        $return = $resultDelete;
                        $return['message'] = "ATTACHMENT_DELETE_FAIL_TEXT";
                        $this->db->rollback();
                        goto end_of_function;
                    }
                } else {
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'data' => $data];
                    goto end_of_function;
                }
            }


            if ($canDeleteOwn['success'] == true) {
                if ($mediaAttachment->belongsToUser()) {
                    //if belong to User and Delete All
                    $this->db->begin();
                    $filename = $mediaAttachment->getMedia()->getName() . "." . $mediaAttachment->getMedia()->getFileExtension() ;
                    $this->dispatcher->setParam('filename', $filename);
                    $object_uuid = $mediaAttachment->getObjectUuid();

                    $resultDelete = $mediaAttachment->__quickRemove();
                    if ($resultDelete['success'] == true) {
                        $this->db->commit();
                        /** COUNT MEDIA */
                        $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), ['sharing' => -1]);
                        if ($resultMediaCount['success'] == false) {
                            $return = $resultMediaCount;
                            $return['message'] = "ATTACHMENT_DELETE_FAIL_TEXT";
                            goto end_of_function;
                        }
                        $return = ['success' => true, 'message' => 'ATTACHMENT_DELETE_SUCCESS_TEXT', 'data' => $data];
                        goto end_of_function;
                    } else {
                        $return = $resultDelete;
                        $return['message'] = "ATTACHMENT_DELETE_FAIL_TEXT";
                        $this->db->rollback();
                        goto end_of_function;
                    }
                } else {
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'data' => $data];
                    goto end_of_function;
                }
            }
        } else {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'data' => $data];
            goto end_of_function;
        }

        end_of_function:
        if($return['success']){
            $object_folder = ObjectFolder::findFirstByUuid($object_uuid);
            if ($object_folder) {
                $objectUuid = $object_folder->getObjectUuid();
                $assignment = Assignment::__findFirstByUuidCache($objectUuid);

                if($assignment){
                    ModuleModel::$assignment = $assignment;
                    if($type == 'assignment'){
                        $this->dispatcher->setParam('type', History::TYPE_ASSIGNMENT);
                    }else if($type == 'relocation'){
                        $relocation = $assignment->getRelocation();
                        ModuleModel::$relocation = $relocation;
                        $this->dispatcher->setParam('type', History::TYPE_RELOCATION);
                    }
                }

                if(!$assignment){
                    $relocation = Relocation::__findFirstByUuidCache($objectUuid, CacheHelper::__TIME_24H);
                    if($relocation){
                        ModuleModel::$relocation = $relocation;
                        $this->dispatcher->setParam('type', History::TYPE_RELOCATION);
                    }
                }
            }else{
                $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($object_uuid);
                if($relocationServiceCompany){
                    ModuleModel::$relocationServiceCompany = $relocationServiceCompany;
                    $this->dispatcher->setParam('type', History::TYPE_SERVICE);
                }
            }


            $this->dispatcher->setParam('return', $return);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Remove multiple Attachments
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function removeMultipleAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $canDeleteOwn = $this->canAccessResource(AclHelper::CONTROLLER_ATTACHMENTS, AclHelper::ACTION_DELETE_OWN);
        $canDeleteAll = $this->canAccessResource(AclHelper::CONTROLLER_ATTACHMENTS, AclHelper::ACTION_DELETE);

        if ($canDeleteOwn['success'] == false && $canDeleteAll['success'] == false) {
            $return = $canDeleteAll;
            goto end_of_function;
        }

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        /***** check attachments permission ***/
        $media_attachment_uuids = Helpers::__getRequestValue('media_attachment_uuids');
        $object_uuid = Helpers::__getRequestValue('object_uuid');
        $type = Helpers::__getRequestValue('type');

        $fileNames = '';
        if ($media_attachment_uuids) {
            $mediaAttachments = MediaAttachment::find([
                'conditions' => 'uuid IN ({uuids:array})',
                'bind' => [
                    'uuids' => $media_attachment_uuids,
                ]
            ]);
            $this->db->begin();
            $data = [];
            foreach ($mediaAttachments as $attachment) {

                if (!$attachment->belongsToCompany()) {
                    $this->db->rollback();
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'data' => $attachment];
                    goto end_of_function;
                }
                if (!$attachment->belongsToUser() && !ModuleModel::$user_profile->isAdmin() && $canDeleteAll['success'] == false) {
                    $this->db->rollback();
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'data' => $attachment];
                    goto end_of_function;
                }

                $fileNames .= $attachment->getMedia()->getName() . "." . $attachment->getMedia()->getFileExtension() . ';';

                if(!$object_uuid){
                    $object_uuid = $attachment->getObjectUuid();
                }

                $resultDelete = $attachment->__quickRemove();
                if ($resultDelete['success'] == true) {
                    /** COUNT MEDIA */
                    $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), ['sharing' => -1]);
                    if ($resultMediaCount['success'] == false) {
                        $return = $resultMediaCount;
                        $return['message'] = "ATTACHMENT_DELETE_FAIL_TEXT";
                        goto end_of_function;
                    }
                    $data[] = $attachment;
                } else {
                    $return = $resultDelete;
                    $return['message'] = "ATTACHMENT_DELETE_FAIL_TEXT";
                    $this->db->rollback();
                    goto end_of_function;
                }
            }
            $return = ['success' => true, 'message' => 'ATTACHMENT_DELETE_SUCCESS_TEXT', 'data' => $data];

            $this->db->commit();
        }

        end_of_function:

        if($return['success']){
            $object_folder = ObjectFolder::findFirstByUuid($object_uuid);
            if ($object_folder) {
                $objectUuid = $object_folder->getObjectUuid();
                $assignment = Assignment::__findFirstByUuidCache($objectUuid);

                if($assignment){
                    ModuleModel::$assignment = $assignment;
                    if($type == 'assignment'){
                        $this->dispatcher->setParam('type', History::TYPE_ASSIGNMENT);
                    }else if($type == 'relocation'){
                        $relocation = $assignment->getRelocation();
                        ModuleModel::$relocation = $relocation;
                        $this->dispatcher->setParam('type', History::TYPE_RELOCATION);
                    }
                }

                if(!$assignment){
                    $relocation = Relocation::__findFirstByUuidCache($objectUuid, CacheHelper::__TIME_24H);
                    if($relocation){
                        ModuleModel::$relocation = $relocation;
                        $this->dispatcher->setParam('type', History::TYPE_RELOCATION);
                    }
                }
            }else{
                $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($object_uuid);
                if($relocationServiceCompany){
                    ModuleModel::$relocationServiceCompany = $relocationServiceCompany;
                    $this->dispatcher->setParam('type', History::TYPE_SERVICE);
                }
            }


            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('filename', $fileNames);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @params: uuid
     * @params: folderUuid
     */
    /**
     * @param $uuid
     * @throws \Exception
     */
    public function moveAttachmentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();

        /***** check attachments permission ***/
        if (is_null($uuid) || is_null($uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $attachment = MediaAttachment::findFirstByUuid($uuid);
        if (!$attachment) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($attachment && $attachment->belongsToUser()) {
            $folderUuid = Helpers::__getRequestValue('folder_uuid');
            $attachment->setFolderUuid($folderUuid);

            $resultUpdate = $attachment->__quickUpdate();

            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
                $return['message'] = 'FILE_MOVE_FAIL_TEXT';
                goto end_of_function;
            }

            $dataReturn = $attachment->getMediaParsedData();
            $dataReturn['media_attachment_uuid'] = $attachment->getUuid();
            $dataReturn['media_attachment_object_uuid'] = $attachment->getObjectUuid();

            $return = ['success' => true, 'message' => 'FILE_MOVE_SUCCESS_TEXT', 'data' => $dataReturn];
            goto end_of_function;

        } else {
            $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            goto end_of_function;
        }

        end_of_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * Check existed attachments on object
     * @param $uuid
     * @return mixed
     */
    public function checkExitedAttachmentsAction($uuid)
    {
        $this->checkAjaxGet();

        $type = RelodayObjectMapHelper::__getHistoryTypeObject($uuid);
        if ($type == HistoryHelper::TYPE_ASSIGNMENT) {
            $assignment = Assignment::findFirstByUuid($uuid);
            if (!$assignment) {
                $return = false;
                goto end_of_function;
            }
            $folders = $assignment->getFolders();

            if ($folders['success']) {
                if ($folders['object_folder_dsp_ee']) {
                    $eeAttachments = MediaAttachment::__findWithFilter([
                        'limit' => 1,
                        'page' => 1,
                        'object_uuid' => $folders['object_folder_dsp_ee']->getUuid(),
                    ])['data'];
                    if (count($eeAttachments) > 0) {
                        $return = true;
                        goto end_of_function;
                    }
                }

                if ($folders['object_folder_dsp_hr']) {
                    $hrAttachments = MediaAttachment::__findWithFilter([
                        'limit' => 1,
                        'page' => 1,
                        'object_uuid' => $folders['object_folder_dsp_hr']->getUuid(),
                    ])['data'];

                    if (count($hrAttachments) > 0) {
                        $return = true;
                        goto end_of_function;
                    }
                }

                $dspAttachments = MediaAttachment::__findWithFilter([
                    'limit' => 1,
                    'page' => 1,
                    'object_uuid' => $folders['object_folder_dsp']->getUuid(),
                ])['data'];

                if (count($dspAttachments) > 0) {
                    $return = true;
                    goto end_of_function;
                }
            }


        } else if ($type == HistoryHelper::TYPE_RELOCATION) {

            $relocation = Relocation::findFirstByUuid($uuid);
            if (!$relocation) {
                $return = false;
                goto end_of_function;
            }

            $folders = $relocation->getFolders();
            if ($folders['success']) {
                if ($folders['object_folder_dsp_ee']) {
                    $eeAttachments = MediaAttachment::__findWithFilter([
                        'limit' => 1,
                        'page' => 1,
                        'object_uuid' => $folders['object_folder_dsp_ee']->getUuid(),
                    ])['data'];
                    if (count($eeAttachments) > 0) {
                        $return = true;
                        goto end_of_function;
                    }
                }

                if ($folders['object_folder_dsp']) {
                    $dspAttachments = MediaAttachment::__findWithFilter([
                        'limit' => 1,
                        'page' => 1,
                        'object_uuid' => $folders['object_folder_dsp']->getUuid(),
                    ])['data'];

                    if (count($dspAttachments) > 0) {
                        $return = true;
                        goto end_of_function;
                    }
                }
            }

        } else {
            $attms = MediaAttachment::__findWithFilter([
                'limit' => 1,
                'page' => 1,
                'object_uuid' => $uuid,
            ])['data'];
            if (count($attms) > 0) {
                $return = true;
                goto end_of_function;
            }
        }

        $return = false;
        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function copyMultipleFilesAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $attachment_uuids = Helpers::__getRequestValue('attachment_uuids');

        $uuid = Helpers::__getRequestValue('uuid');
        $companyUuid = Helpers::__getRequestValue('companyUuid');
        $employeeUuid = Helpers::__getRequestValue('employeeUuid');
        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');
        $objectNameRequired = Helpers::__getRequestValue('objectNameRequired');
        $isAttachFiles = Helpers::__getRequestValue('isAttachFiles');

        //Media list return
        $mediaList = [];
        $attachList = [];

        $this->db->begin();

        if (count($attachment_uuids) == 0) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        foreach ($attachment_uuids as $attachment_uuid) {
            $attachment = MediaAttachment::findFirstByUuid($attachment_uuid);
            if (!$attachment) {
                $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
                goto end_of_function;
            }

            $media = $attachment->getMedia();
            if (!$media) {
                $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
                goto end_of_function;
            }

            // Media copy
            $prefix_copy = 'Copy of';
            $random = new Random();
            $randomUuid = $random->uuid();

            $num = 0;

            /** SAVE TO DYNAMO DATABASE */
            $newMedia = new Media();
            $newMedia->setUuid($randomUuid);
            $newMedia->setCompanyId(ModuleModel::$company->getId());
            $newMedia->setFilename($randomUuid . '.' . strtolower($media->getFileExtension()));
            $newMedia->setName($media->getName());
            $newMedia->setNameStatic($media->getNameStatic());
            $newMedia->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
            $newMedia->setFileExtension($media->getFileExtension());
            $newMedia->setFileType($media->getFileType());
            $newMedia->setMimeType($media->getMimeType());
            $newMedia->loadMediaType();
            $newMedia->setIsHosted(intval(ModelHelper::YES));
            $newMedia->setIsPrivate(intval(ModelHelper::YES));
            $newMedia->setIsDeleted(intval(ModelHelper::NO));
            $newMedia->setIsHidden(intval(ModelHelper::NO));
            if ($media->getSize()) {
                $newMedia->setSize(intval($media->getSize()));
            }

            if ($media->getFolderUuid() && $media->getUserProfileUuid() == ModuleModel::$user_profile->getUuid()) {
                $newMedia->setFolderUuid($media->getFolderUuid());
            }

            $newMedia->addDefaultFilePath();

            $existed = $newMedia->checkFileNameExisted();
            while ($existed == true) {
                if ($num < 1) {
                    $newMedia->setName($prefix_copy . ' ' . $media->getName());
                    $newMedia->setNameStatic($prefix_copy . ' ' . $media->getName());
                } else {
                    $newMedia->setName($prefix_copy . ' ' . $media->getName() . "($num)");
                    $newMedia->setNameStatic($prefix_copy . ' ' . $media->getName() . "($num)");
                }
                $existed = $newMedia->checkFileNameExisted();
                $num++;
            }

            //Create data on Dynamo
            $resultNewMedia = $newMedia->__quickCreate();
            if ($resultNewMedia['success'] == false) {
                $return = $resultNewMedia;
                $return['success'] = false;
                $return['message'] = 'FILE_COPY_FAIL_TEXT';
                $this->db->rollback();
                goto end_of_function;
            }

            $mediaItem = $newMedia->getParsedData();
            $mediaItem['can_attach_to_my_library'] = false;
            $mediaList[] = $mediaItem;

            // Copy file s3
            $fromFilePath = $media->getFilePath();
            $toFilePath = $newMedia->getFilePath();

            $resultCopyFile = RelodayS3Helper::__copyMedia($fromFilePath, $toFilePath);

            if ($resultCopyFile['success'] == false) {
                $return = $resultCopyFile;
                $return['success'] = false;
                $return['message'] = "FILE_COPY_TO_S3_FAIL_TEXT";
                $this->db->rollback();
                goto end_of_function;
            }
            /** COUNT MEDIA */
            $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), [
                'my_files' => 1,
                'total_files' => 1,
                'sizes' => $newMedia->getSize()
            ]);
            //Attach file

            if ($isAttachFiles) {
                $fileNames = '';
                if (Helpers::__isValidUuid($companyUuid)) {
                    $company = HrCompany::findFirstByUuid($companyUuid);
                }

                if (Helpers::__isValidUuid($employeeUuid)) {
                    $employee = Employee::findFirstByUuid($employeeUuid);
                    if (!$employee || !$employee->belongsToGms()) {
                        $return = ['success' => false, 'message' => 'EMPLOYEE_NOT_EXIST_TEXT', 'employee' => $employee];
                        goto end_of_function;
                    }
                }

                $initiation_request = AssignmentRequest::findFirstByUuid($uuid);
                if ($initiation_request) {
                    $assignment = $initiation_request->getAssignment();
                    $folder = $assignment->getHrFolder($initiation_request->getOwnerCompanyId());
                }
                $items = [];

                if (isset($folder) && $folder) {
                    $attachResult_assignment_folder = MediaAttachment::__createAttachment([
                        'objectUuid' => $folder->getUuid(),
                        'file' => $newMedia,
                        'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                        'userProfile' => ModuleModel::$user_profile,
                    ]);
                    $attachmentItemResult = isset($attachResult_assignment_folder) && $attachResult_assignment_folder['success'] ?  $attachResult_assignment_folder['data'] : [];
                    if($attachmentItemResult){
                        $attachList[] = $attachmentItemResult;
                    }
                }


                if (isset($company) && $company) {
                    $attachResult = MediaAttachment::__createAttachment([
                        'objectUuid' => $uuid,
                        'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                        'file' => $newMedia,
                        'employeeId' => null,
                        'companyId' => $company->getId(),
                        'userProfile' => ModuleModel::$user_profile,
                        'sharerActor' => $company,
                        'is_shared' => true
                    ]);

                    $attachmentItemResult = isset($attachResult) && $attachResult['success'] ?  $attachResult['data'] : [];
                    if($attachmentItemResult){
                        $attachList[] = $attachmentItemResult;
                    }
                }

                if (isset($employee) && $employee) {
                    $attachResult = MediaAttachment::__createAttachment([
                        'objectUuid' => $uuid,
                        'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                        'file' => $newMedia,
                        'employeeId' => $employee->getId(),
                        'companyId' => null,
                        'userProfile' => ModuleModel::$user_profile,
                        'sharerActor' => $employee,
                        'is_shared' => true
                    ]);
                    $attachmentItemResult = isset($attachResult) && $attachResult['success'] ?  $attachResult['data'] : [];
                    if($attachmentItemResult){
                        $attachList[] = $attachmentItemResult;
                    }
                }

                if (!isset($employee) && !isset($company) && ModuleModel::$company) {
                    $attachResult = MediaAttachment::__createAttachment([
                        'objectUuid' => $uuid,
                        'objectName' => $objectNameRequired != '' ? $objectNameRequired : false,
                        'file' => $newMedia,
                        'employeeId' => null,
                        'companyId' => ModuleModel::$company->getId(),
                        'userProfile' => ModuleModel::$user_profile,
                        'sharerActor' => ModuleModel::$company,
                        'is_shared' => false
                    ]);
                    $attachmentItemResult = isset($attachResult) && $attachResult['success'] ?  $attachResult['data'] : [];
                    if($attachmentItemResult){
                        $attachList[] = $attachmentItemResult;
                    }

                }

                if (is_object($newMedia) && property_exists($newMedia, 'extension')) {
                    $name = method_exists($newMedia, 'getName') ? $newMedia->getName() : $newMedia->name;
                    $extension = method_exists($attachment, 'getExtension') ? $attachment->getExtension() : $attachment->extension;
                    $fileNames .= $name . "." . $extension . ";";
                }

                if (isset($attachResult) && $attachResult['success'] == false) {
                    $this->db->rollback();
                    $return = ['success' => false,
                        'errorType' => 'MediaAttachmentError',
                        'detail' => $attachResult,
                        'message' => isset($attachResult['message']) ? $attachResult['message'] : 'ATTACH_FAILED_TEXT'
                    ];
                    goto end_of_function;
                }

                /** COUNT MEDIA ATTACHMENT */
                $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), ['sharing' => 1]);

                if ($resultMediaCount['success'] == false) {
                    $return = $resultMediaCount;
                    $return['message'] = "ATTACH_FAILED_TEXT";
                    goto end_of_function;
                }
            }

        }

        $this->db->commit();
        $return['success'] = true;
        $return['message'] = 'FILES_COPY_SUCCESS_TEXT';
        $return['mediaList'] = $mediaList;

        end_of_function:
        if ($return['success'] && $isAttachFiles) {
            if ($type == 'relocation') {
                $type = HistoryHelper::TYPE_RELOCATION;
            } else if ($type == 'assignment') {
                $type = HistoryHelper::TYPE_ASSIGNMENT;
            }
            $this->dispatcher->setParam('file_count', count($attachList));

            if ($type == HistoryHelper::TYPE_ASSIGNMENT) {
                $object_folder = ObjectFolder::findFirstByUuid($uuid);
                ModuleModel::$objectFolder = $object_folder;
                if ($object_folder) {
                    $object_uuid = $object_folder->getObjectUuid();
                    $assignment = Assignment::__findFirstByUuidCache($object_uuid);
                }
                if ($return['success'] == true && isset($assignment) && $assignment && $assignment->belongsToGms()) {
                    ModuleModel::$assignment = $assignment;
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_ADD_ATTACHMENT);
                }

                if (isset($employee) && $employee) {
                    ModuleModel::$employee = $employee;
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
//                            'company_name' => $employee->getCompany() ? $employee->getCompany()->getName() : "",
                            'file_name' => $fileNames,
                            'time' => date('d M Y - H:i:s'),
                        ]
                    ];
                    $resultCheck = $beanQueue->sendMail($dataArray);
                    $return['resultCheck'] = $resultCheck;
                }
                if (isset($assignment)) {
                    $history = \Reloday\Gms\Help\HistoryHelper::__sendHistory($assignment, History::HISTORY_ADD_ATTACHMENT, $this->request->getClientAddress());
                }

            } else if ($type == HistoryModel::TYPE_RELOCATION) {
                $object_folder = ObjectFolder::findFirstByUuid($uuid);
                if ($object_folder) {
                    $object_uuid = $object_folder->getObjectUuid();
                    $assignment = Assignment::__findFirstByUuidCache($object_uuid);
                    if ($assignment && $assignment instanceof Assignment) {
                        $relocation = $assignment->getRelocation();
                    } else {
                        $relocation = Relocation::__findFirstByUuidCache($object_uuid, CacheHelper::__TIME_24H);
                        $assignment = $relocation->getAssignment();
                    }
                    ModuleModel::$objectFolder = $object_folder;
                    ModuleModel::$assignment = $assignment;
                }

                if (isset($employee) && $employee) {
                    ModuleModel::$employee = $employee;
                }


                if ($return['success'] == true && isset($relocation) && $relocation && $relocation->belongsToGms()) {
                    ModuleModel::$relocation = $relocation;
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_ADD_ATTACHMENT);
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
//                            'company_name' => $employee->getCompany() ? $employee->getCompany()->getName() : "",
                            'file_name' => $fileNames,
                            'time' => date('d M Y - H:i:s'),
                        ]
                    ];
                    $resultCheck = $beanQueue->sendMail($dataArray);
                    $return['resultCheck'] = $resultCheck;
                }

                if (isset($relocation)) {
                    $history = \Reloday\Gms\Help\HistoryHelper::__sendHistory($relocation, History::HISTORY_ADD_ATTACHMENT, $this->request->getClientAddress());
                }


                $return['typeFile'] = $type;
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function changeThumbAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $uuid = Helpers::__getRequestValue('uuid');

        if (is_null($uuid) || !Helpers::__isValidUuid($uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $media = MediaAttachment::findFirstByUuid($uuid);

        if (!$media) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $mediaIsThumb = MediaAttachment::findFirst([
            "conditions" => "is_thumb = :is_thumb: and object_uuid = :object_uuid:",
            "bind" => [
                'object_uuid' => $media->getObjectuuid(),
                'is_thumb' => MediaAttachment::IS_THUMB_YES

            ]
        ]);
        if ($mediaIsThumb) {
            $mediaIsThumb->setIsThumb(MediaAttachment::IS_THUMB_FALSE);
            $resReset = $mediaIsThumb->__quickUpdate();
            if (!$resReset['success']) {
                $return = $resReset;
                $return['message'] = 'DATA_SAVE_FAIL_TEXT';
                goto end_of_function;
            }
        }

        $media->setIsThumb(MediaAttachment::IS_THUMB_YES);

        $res = $media->__quickUpdate();
        $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
        if (!$res['success']) {
            $return = $res;
            $return['message'] = 'DATA_SAVE_FAIL_TEXT';
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }
}
