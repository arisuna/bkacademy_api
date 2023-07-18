<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security;
use Phalcon\Security\Random;
use Reloday\Application\Application;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\EmployeeActivityHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\LanguageCode;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\UserActionHelpers;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\CompanySettingDefaultExt;
use Reloday\Application\Models\NationalityExt;
use Reloday\Application\Models\RelocationExt;
use Reloday\Application\Models\RelocationGuideExt;
use Reloday\Application\Models\ReportExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Help\EmployeeHelper;
use Reloday\Gms\Help\HistoryHelper;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\App;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\AssignmentRequest;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\Comment;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\EmployeeSupportContact;
use Reloday\Gms\Models\EntityDocument;
use Reloday\Gms\Models\EventValue;
use Reloday\Gms\Models\Guide;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HousingProposition;
use Reloday\Gms\Models\ListOrderSetting;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\NeedAssessmentItems;
use Reloday\Gms\Models\NeedAssessments;
use Reloday\Gms\Models\NeedAssessmentsRelocation;
use Reloday\Gms\Models\ObjectFolder;
use Reloday\Gms\Models\Property;
use \Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationGuide;
use \Reloday\Gms\Models\RelocationServiceCompany;
use \Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ReminderConfig;
use Reloday\Gms\Models\Report;
use Reloday\Gms\Models\Service;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\ServiceField;
use Reloday\Gms\Models\ServiceFieldValue;
use Reloday\Gms\Models\ServicePack;
use Reloday\Gms\Models\SupportedLanguage;
use \Reloday\Gms\Models\Task as Task;
use \Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\TaskChecklist;
use Reloday\Gms\Models\TaskFile;
use Reloday\Gms\Models\TaskTemplate;
use Reloday\Gms\Models\TaskWorkflow;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Gms\Models\Workflow;
use Reloday\Gms\Models\Event;
use Reloday\Gms\Models\DocumentTemplate;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class RelocationController extends BaseController
{
    /**
     * [indexAction description]
     * @return [type] [description]
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $search = Relocation::loadList();
        //load assignment list
        $results = [];


        if ($search['success'] == true) {
            $relocations = $search['data'];

            if (count($relocations)) {
                foreach ($relocations as $relocation) {
                    $ass_destination = $relocation->getAssignment()->getAssignmentDestination();
                    $results[] = [
                        'id' => $relocation->getId(),
                        'uuid' => $relocation->getUuid(),
                        'employee_uuid' => $relocation->getEmployee()->getUuid(),
                        'identify' => $relocation->getIdentify(),
                        'employee_name' => $relocation->getEmployee()->getFirstname() . " " . $relocation->getEmployee()->getLastname(),
                        'company_name' => $relocation->getEmployee()->getCompany()->getName(),
                        'host_country' => ($ass_destination && $ass_destination->getDestinationCountry() ? $ass_destination->getDestinationCountry()->getName() : ""),
                        'start_date' => $relocation->getStartDate(),
                        'end_date' => $relocation->getEndDate(),
                        'status' => $relocation->getStatus(),
                    ];
                }
            }
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        return $this->response->send();

    }

    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('create', $this->router->getControllerName());

        $data = Helpers::__getRequestValuesArray();
        $ownerProfile = Helpers::__getRequestValueAsArray('owner');

        $assignment_id = 0;
        if (isset($data['assignment']['id']) && $data['assignment']['id'] > 0) {
            $assignment_id = $data['assignment']['id'];
        } elseif (isset($data['assignment_id']) && $data['assignment_id'] > 0) {
            $assignment_id = $data['assignment_id'];
        } elseif (isset($data->assignment->id) && $data->assignment->id > 0) {
            $assignment_id = $data->assignment->id;
        }
        //check assignment
        $return = ['success' => false, 'message' => 'RELOCATION_CREATE_FAIL_TEXT', 'raw' => $data];

        $assignment = Assignment::findFirstById($assignment_id);
        if (!$assignment) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $data];
            goto end_of_function;
        }

        if ($assignment &&
            $assignment->canCreateRelocation() == true) {

            $dataRelocation = [
                'hr_company_id' => $assignment->getCompanyId(),
                'assignment_id' => $assignment->getId()
            ];


            $relocation = new Relocation();
            $relocation->setHrCompanyId($assignment->getCompanyId());
            $relocation->setCreatorCompanyId(ModuleModel::$company->getId());
            $relocation->setEmployeeId($assignment->getEmployeeId());
            $number = $relocation->generateNumber();
            $relocation->setName($number);
            $relocation->setIdentify($number);


            if ($ownerProfile && isset($ownerProfile['id'])) {
                $ownerProfile = UserProfile::__findFirstByIdWithCache($ownerProfile['id']);
            } else {
                $ownerProfile = $relocation->getDataOwner();
            }


            if ($ownerProfile && $ownerProfile->belongsToGms()) {
                $relocation->setManageUserProfileId($ownerProfile->getId());
            }

            $currentActiveContract = ModuleModel::$company->getActiveContract($assignment->getCompanyId());
            if (!$currentActiveContract) {
                $return = [
                    'success' => false,
                    'data' => $currentActiveContract,
                    'message' => 'CONTRACT_NOT_FOUND_TEXT',
                ];
                goto end_of_function;
            }


            $this->db->begin();
            $relocation->__setData($dataRelocation);


            // create relocation guides at first time
            $suggestionGuides = Guide::__findSuggestionGuidesFilters([
                'destination_country_id' => $relocation->getAssignment()->getAssignmentDestination()->getDestinationCountryId(),
                'destination_city' => $relocation->getAssignment()->getAssignmentDestination()->getDestinationCity(),
                'gms_company_id' => ModuleModel::$company->getId(),
                'hr_company_id' => $relocation->getHrCompanyId()
            ]);
            $guides = [];
            if ($suggestionGuides['success']) {
                $guides = $suggestionGuides['data'];
            }

            if (count($guides) > 0) {
                $relocation->setIsActivateGuide(ModelHelper::YES);
            }

            $selfServiceDefault = ModuleModel::$company->getCompanySettingValue(CompanySettingDefaultExt::DISPLAY_SELF_SERVICE);
            if ($selfServiceDefault == ModelHelper::YES) {
                $relocation->setSelfService(ModelHelper::YES);
            } else {
                $relocation->setSelfService(ModelHelper::NO);
            }

            $resultModel = $relocation->__quickCreate();

            if (!$assignment->belongsToGms()) {
                $resultAddContract = $assignment->addToContract($currentActiveContract);
                if ($resultAddContract['success'] == false) {
                    $return = [
                        'success' => false,
                        'errorType' => 'cannotAddAssignmentToContract',
                        'message' => 'REQUEST_SENT_FAIL_TEXT',
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }
            }


            if ($resultModel['success'] == true) {

                if ($relocation->addCreator(ModuleModel::$user_profile) == false) {
                    $return['success'] = false;
                    $return['detail'] = "Can not add default creator";
                    $this->db->rollback();
                    goto end_of_function;
                }

                if ($relocation->addReporter(ModuleModel::$user_profile) == false) {
                    $return['success'] = false;
                    $return['detail'] = "Can not add default reporter";
                    $this->db->rollback();
                    goto end_of_function;
                }

                if ($relocation->addDefaultOwner() == false) {
                    $return['success'] = false;
                    $return['detail'] = "Can not add default owner";
                    $this->db->rollback();
                    goto end_of_function;
                }


                $services = Helpers::__getRequestValueAsArray("services");
                $servicesArray = [];
                foreach ($services as $service) {
                    if (isset($service['is_selected']) && $service['is_selected'] == true) {
                        $servicesArray[] = $service;
                    }
                }

                $resultAddServices = $relocation->addServices($servicesArray);
                if ($resultAddServices['success'] == false) {
                    $return = $resultAddServices;
                    $this->db->rollback();
                    goto end_of_function;
                }
                ModuleModel::$relocationServiceCompanies = $resultAddServices['data'];


                /** update assignment */
                $assignment->setCreateRelocationStatus(Assignment::CREATE_RELOCATION_DONE);
                $resultUpdateAssignment = $assignment->__quickUpdate();
                if ($resultUpdateAssignment['success'] == false) {
                    $return = $resultUpdateAssignment;
                    $return['detail'] = "Can not update assignment";
                    $this->db->rollback();
                    goto end_of_function;
                }

                /** updateIninitionRequest if Exists */
                $activeInitiationRequest = $assignment->getActiveAssignmentRequest();
                if ($activeInitiationRequest instanceof AssignmentRequest) {
                    $activeInitiationRequest->setStatus(AssignmentRequest::STATUS_ACCEPTED);
                    $activeInitiationRequest->setConfirmedAt(date('Y-m-d H:i:s'));
                    $activeInitiationRequest->setRelocationId($relocation->getId());
                    $resultUpdateAssignment = $activeInitiationRequest->__quickUpdate();

                    if ($resultUpdateAssignment['success'] == false) {
                        $return = $resultUpdateAssignment;
                        $return['detail'] = "Can not update assignment";
                        $this->db->rollback();
                        goto end_of_function;
                    }
                }

                $random = new Random();

                // create object folder for dsp
                $object_folder_dsp = new ObjectFolder();
                $object_folder_dsp->setUuid($random->uuid());
                $object_folder_dsp->setObjectUuid($relocation->getUuid());
                $object_folder_dsp->setObjectName($relocation->getSource());
                $object_folder_dsp->setRelocationId($relocation->getId());
                $object_folder_dsp->setDspCompanyId(ModuleModel::$company->getId());
                $result = $object_folder_dsp->__quickCreate();
                if (!$result['success']) {
                    $return = [
                        'success' => false,
                        'detail' => $object_folder_dsp
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }


                if (count($guides) > 0) {
                    foreach ($guides as $guide) {
                        RelocationGuideExt::__addRelocationGuide($relocation->getId(), $guide['id'], $relocation->getEmployeeId());
                    }
                }


                if (count($guides) > 0) {
                    $relocation->setIsActivateGuide(ModelHelper::YES);
                    $resultUpdateRelocation = $relocation->__quickUpdate();
                }

                $defaultUserContact = UserProfile::__getDefaultSupportContact();
                if ($defaultUserContact && $defaultUserContact->getId() > 0) {
                    $resultUpdateRelocation = EmployeeSupportContact::__addRelocationSupportContact($defaultUserContact, $relocation);
                }

                $this->db->commit();

                $return = [
                    'success' => true,
                    'message' => 'RELOCATION_CREATE_SUCCESS_TEXT',
                    'data' => $relocation,
                    'services' => $services,
                    'object_folder_dsp' => $object_folder_dsp
                ];

            } else {
                $return = $resultModel;
            }
        } else {
            if ($assignment->canHaveRelocation()) {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_APPROVED_TEXT', 'data' => $data];
                goto end_of_function;
            }
            if ($assignment->hasRelocation()) {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_HAVE_ACTIVE_RELOCATION_TEXT', 'data' => $data];
                goto end_of_function;
            }
//            var_dump($assignment->canCreateRelocation());die();
            if (!$assignment->canCreateRelocation()) {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_ACTIVE_TEXT', 'data' => $data];

                goto end_of_function;
            }
        }


        end_of_function:

        if ($return['success'] == true && isset($relocation)) {
            ModuleModel::$relocation = $relocation;
            ModuleModel::$hrCompany = $relocation->getHrCompany();
            // ModuleModel::$relocationServiceCompanies = $services;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_CREATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @return [type] [description]
     */
    public function detailAction($uID = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if (Helpers::__checkId($uID)) {
            $relocation = Relocation::findFirstById($uID);
        } else if (Helpers::__checkUuid($uID)) {
            $relocation = Relocation::findFirstByUuid($uID);
        }

        if (isset($relocation) && $relocation && $relocation->belongsToGms()) {
            $relocationData = $relocation->toArray();
            $relocationData['number'] = $relocation->getNumber();
            $relocationData['employee_name'] = $relocation->getEmployee()->getFullname();
            $relocationData['employee_uuid'] = $relocation->getEmployee()->getUuid();
            $relocationData['is_cancelled'] = $relocation->isCancelled();
            $relocationData['folders'] = $relocation->getFolders();
            $relocationData['assignment'] = $relocation->getAssignment();
            $return = ['success' => true, 'data' => $relocationData];
        } else {
            $return['detail'] = 'CONTRACT_DESACTIVATED_TEXT';
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [itemAction description]
     * @return [type] [description]
     */
    public function itemAction($relocation_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);

            if ($relocation && $relocation->belongsToGms() && !$relocation->isDeleted()) {
                if (!DataUserMember::checkViewPermissionOfUserByProfile($relocation->getUuid(), ModuleModel::$user_profile)) {
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'permissionNotFound' => true];
                    goto end_of_function;
                }
                $relocationData = $relocation->toArray();
                $company = $relocation->getCompany();
                $relocationData['number'] = $relocation->getNumber();
                $relocationData['employee'] = $relocation->getEmployee();
                $relocationData['employee_name'] = $relocation->getEmployee()->getFullname();
                $relocationData['employee_uuid'] = $relocation->getEmployee()->getUuid();
                $relocationData['company'] = $company;
                $relocationData['company_id'] = $company->getId();
                $relocationData['company_name'] = $company->getName();
                $relocationData['service_pack'] = $relocation->getServicePack();
                $relocationData['service_pack_name'] = $relocation->getServicePack() ? $relocation->getServicePack()->getName() : '';
                $relocationData['service_count'] = $relocation->countRelocationServiceCompanies();
                $relocationData['gmsFolderUuid'] = $relocation->getGmsFolderUuid();
                $relocationData['employeeFolderUuid'] = $relocation->getEmployeeFolderUuid();
                $relocationData['is_cancelled'] = $relocation->isCancelled();
                $relocationData['folders'] = $relocation->getFolders();
                $relocationData['owner_name'] = $relocation->getOwner() ? $relocation->getOwner()->getFullname() : '';
                $relocationData['owner_uuid'] = $relocation->getOwner() ? $relocation->getOwner()->getUuid() : '';
                $assignment = $relocation->getAssignment();
                $relocationData['assignment_uuid'] = $assignment? $assignment->getUuid() : '';
                $relocationData['assignment_name'] = $assignment? $assignment->getName() : '';
                $relocationData['booker_company_id'] = $assignment && $assignment->getBookerCompany() ? $assignment->getBookerCompany()->getId() : null;
                $relocationData['booker_company_name'] = $assignment && $assignment->getBookerCompany() ? $assignment->getBookerCompany()->getName() : null;
                $relocationData['is_archived'] = $relocation->isArchived();
                $return = ['success' => true, 'data' => $relocationData];
            } else {
                $return['detail'] = 'CONTRACT_DESACTIVATED_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_uuid
     */
    public function getAccountOfRelocationAction($relocation_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $company = $relocation->getHrCompany();
                $companyArray = $company->toArray();
                $companyArray['country_name'] = $company->getCountryName();
                $return = ['success' => true, 'data' => $companyArray];
            } else {
                $return['message'] = 'RELOCATION_NOT_FOUND_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getAssignmentAction(string $relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $assignment = $relocation->getAssignment();
                if ($assignment) {

                    $basic = $assignment->getAssignmentBasic();
                    $destination = $assignment->getAssignmentDestination();

                    $basicArray = [];
                    $destinationArray = [];
                    if ($destination) {
                        $destinationArray = $destination->toArray();
                        $destinationArray['country_name'] = $destination->getDestinationCountry() ? $destination->getDestinationCountry()->getName() : "";
                    }

                    if ($basic) {
                        $basicArray = $basic->toArray();
                        $basicArray['country_name'] = $basic->getHomeCountry() ? $basic->getHomeCountry()->getName() : "";
                    }

                    $assignmentArray = $assignment->getInfoDetailInArray();
                    $assignmentArray['folders'] = $assignment->getFolders();

                    $return = [
                        'success' => true,
                        'data' => $assignmentArray,
                        'basic' => $basicArray,
                        'destination' => $destinationArray,
                    ];
                }
            }
        }
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function getAssgigneeAction($relocation_uuid = null)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $employee = $relocation->getEmployee();
                if ($employee) {
                    $loginStatus = $employee->updateLoginStatus();
                    $employeeArray = $employee->toArray();
                    $employeeArray['company_name'] = ($employee->getCompany()) ? $employee->getCompany()->getName() : "";
                    $employeeArray['company_uuid'] = ($employee->getCompany()) ? $employee->getCompany()->getUuid() : "";
                    $employeeArray['is_editable'] = $employee->isEditable();
                    $employeeArray['office_name'] = $employee->getOffice() ? $employee->getOffice()->getName() : "";
                    $employeeArray['team_name'] = $employee->getTeam() ? $employee->getTeam()->getName() : "";
                    $employeeArray['department_name'] = $employee->getDepartment() ? $employee->getDepartment()->getName() : "";
                    $employeeArray['citizenships'] = $employee->parseCitizenships();
                    $employeeArray['documents'] = EntityDocument::__getDocumentsByEntityUuid($employee->getUuid());
                    $employeeArray['support_contacts'] = EmployeeSupportContact::__getSupportContacts($employee->getId(), $relocation->getId());
                    $employeeArray['buddy_contacts'] = EmployeeSupportContact::__getBuddyContacts($employee->getId());
                    $employeeArray['spoken_languages'] = $employee->parseSpokenLanguages();
                    $employeeArray['birth_country_name'] = $employee->getBirthCountry() ? $employee->getBirthCountry()->getName() : "";
                    $employeeArray['last_login'] = $employee->getUserLogin() ? $employee->getUserLogin()->getLastconnectAt() : "";
                    $employeeArray['first_login'] = $employee->getUserLogin() ? $employee->getUserLogin()->getFirstconnectAt() : "";
                    $employeeArray['hasLogin'] = $employee->hasLogin();
                    $employeeArray['hasUserLogin'] = $employee->getUserLogin() ? true : false;
                    $employeeArray['login_email'] = $employee->getUserLogin() ? $employee->getUserLogin()->getEmail() : null;
                    $employeeArray['login_status'] = $loginStatus;
                    $employeeArray['dependants'] = $employee->getDependants() ? $employee->getDependants()->toArray() : [];
                    $return = ['success' => true, 'data' => $employeeArray];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [editAction description]
     * @return [type] [description]
     */
    public function editAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);
        $this->checkAclEdit();

        $dataInput = Helpers::__getRequestValuesArray();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $this->db->begin();
                $return = $relocation->__save($dataInput);

                if ($return['success'] == false) {
                    $this->db->rollback();
                    $return['message'] = 'RELOCATION_UPDATE_FAIL_TEXT';
                    $return['detail'] = reset($return['detail']);
                    goto end_of_function;
                }

                //Event
                $eventList = Event::find([
                    'conditions' =>'object_source = :object_source:',
                    'bind' => [
                        'object_source' => Event::SOURCE_RELOCATION
                    ]
                ]);
                $resultQueueEvents = [];
                if ($eventList->count() > 0) {
                    $resultSaveEventTimes = [];
                    foreach ($eventList as $eventItem) {
                        $eventObject = EventValue::__findByObjectIdAndEventWithCheck($relocation->getId(), $eventItem->getId());

                        $eventObject->setData([
                            'object_id' => $relocation->getId(),
                            'event_id' => $eventItem->getId(),
                            'value' => $relocation->get($eventItem->getFieldName()) ? strtotime($relocation->get($eventItem->getFieldName())) : 0
                        ]);
                        $checkReminder = false;
                        if (($eventObject->hasSnapshotData() == true && $eventObject->hasChanged()) || $eventObject->hasSnapshotData() == false) {
                            $checkReminder = true;
                        }

                        $resultSaveEventTime = $eventObject->__quickSave();

                        if ($resultSaveEventTime['success'] == false) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'SAVE_EVENTS_FAIL_TEXT',
                                'detail' => $resultSaveEventTime['detail']
                            ];
                            goto end_of_function;
                        }

                        if ($checkReminder == true) {
                            $resultSaveReminderConfig = $eventObject->updateRelocationReminderConfigData();
                            if ($resultSaveReminderConfig['success'] == false) {
                                $this->db->rollback();
                                $return = [
                                    'success' => false,
                                    'message' => 'SAVE_EVENTS_FAIL_TEXT',
                                    'detail' => $resultSaveReminderConfig['detail']
                                ];
                                goto end_of_function;
                            }
                        }
                    }
                }

                $this->db->commit();

            }
        }

        end_of_function:

        if ($return['success'] == true && isset($relocation)) {
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_UPDATE);
            $return['valueExist'] = Helpers::__existRequestValue('self_service');
            if (!Helpers::__existRequestValue('self_service')) {
                ModuleModel::$relocation = $relocation;
                $this->dispatcher->setParam('return', $return);
            }
        }

        if ($return['success'] == true && isset($relocation)) {
            if (Helpers::__existRequestValue('self_service') && count($dataInput) == 2) {
                $selfService = Helpers::__getRequestValue('self_service');
                if ($selfService == true || $selfService == 1)
                    $return['message'] = 'SET_RELOCATION_SELF_SERVICE_VISIBLE_SUCCESS_TEXT';
                if ($selfService == false || $selfService == 0)
                    $return['message'] = 'SET_RELOCATION_SELF_SERVICE_NOT_VISIBLE_SUCCESS_TEXT';
            }
        }

        if ($return['success'] == false && isset($relocation)) {
            if (Helpers::__existRequestValue('self_service') && count($dataInput) == 2) {
                $return['message'] = 'SET_RELOCATION_SELF_SERVICE_FAIL_TEXT';
            }
        }

        $return['method'] = __METHOD__;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * [editAction description]
     * @return [type] [description]
     */
    public function changeStatusAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclChangeStatus();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {

                $status = Helpers::__getRequestValue('status');
                if (Relocation::__validateStatus($status)) {
                    $relocation->setStatus($status);
                    $return = $relocation->__quickUpdate();
                    if ($return['success'] == true) {
                        $return['message'] = 'RELOCATION_CHANGE_STATUS_SUCCESS_TEXT';
                    } else {
                        $return['message'] = 'RELOCATION_CHANGE_STATUS_FAIL_TEXT';
                    }
                }
            }
        }
        end_of_function:
        if ($return['success'] == true && isset($relocation)) {
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_CHANGE_STATUS);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * archive a relocation
     */
    public function archiveAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('archive', 'relocation');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if($relocation->getStatus() != Relocation::STATUS_CANCELED  && $relocation->getStatus() != Relocation::STATUS_TERMINATED){
                $return['message'] = 'CAN_NOT_ARCHIVE_RELOCATION_TEXT';
                goto end_of_function;
            }

            if ($relocation && $relocation->belongsToGms()) {
                $return = $relocation->quickArchive();
                if ($return['success'] == true) $return['message'] = "RELOCATION_ARCHIVE_SUCCESS_TEXT";
                else $return['message'] = "RELOCATION_ARCHIVE_FAIL_TEXT";
            }
        }

        end_of_function:

        if ($return['success'] == true && isset($relocation)) {
            $queueRelocation = RelodayQueue::__getQueueRelocation();
            $return['queueArchive'] = $queueRelocation->addQueue([
                'action' => UserActionHelpers::METHOD_ARCHIVE,
                'uuid' => $relocation->getUuid()
            ]);
        }

        if ($return['success'] == true && isset($relocation)) {
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_ARCHIVE_RELOCATION);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * archive a relocation
     */
    public function unarchiveAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('archive', 'relocation');

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $return = $relocation->quickUnarchive();
                if ($return['success'] == true) $return['message'] = "RELOCATION_UNARCHIVE_SUCCESS_TEXT";
                else $return['message'] = "RELOCATION_UNARCHIVE_FAIL_TEXT";
            }
        }

        end_of_function:

        if ($return['success'] == true && isset($relocation)) {
            $queueRelocation = RelodayQueue::__getQueueRelocation();
            $return['queueUnarchive'] = $queueRelocation->addQueue([
                'action' => UserActionHelpers::METHOD_UNARCHIVE,
                'uuid' => $relocation->getUuid()
            ]);
        }

        if ($return['success'] == true && isset($relocation)) {
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * remove a relocation
     */
    public function removeAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclDelete();

        $password = Helpers::__getRequestValue('password');
        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user_login->getEmail(), $password);
        if ($checkPassword['success'] == false) {
            $return = $checkPassword;
            $return['message'] = 'PASSWORD_INCORRECT_TEXT';
            goto end_of_function;
        }

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $relocationGuides = $relocation->getRelocationGuides();
                if ($relocationGuides) {
                    $relocationGuides->delete();
                }

                $return = $relocation->__quickRemove();
                if ($return['success'] == true) $return['message'] = "RELOCATION_DELETE_SUCCESS_TEXT";
                else $return['message'] = "RELOCATION_DELETE_FAIL_TEXT";
            }
        }

        end_of_function:
        if ($return['success'] == true && isset($relocation)) {
            $queueRelocation = RelodayQueue::__getQueueRelocation();
            $return['queueDelete'] = $queueRelocation->addQueue([
                'action' => 'remove',
                'uuid' => $relocation->getUuid()
            ]);
        }

        if ($return['success'] == true && isset($relocation)) {
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_DELETE_RELOCATION);

        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $relocation_uuid
     * @return mixed
     */
    public function quickviewAction(string $relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($relocation_uuid != '') {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->checkCompany()) {
                $data = $relocation->toArray();
                $data['gmsFolderUuid'] = $relocation->getGmsFolderUuid();
                $data['employeeFolderUuid'] = $relocation->getEmployeeFolderUuid();
                $return = ['success' => true, 'data' => $data];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_uuid
     * @return mixed
     */
    public function getServicesAction(string $relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if (!($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid))) {
            goto end_of_function;
        }

        $relocation = Relocation::findFirstByUuid($relocation_uuid);
        if (!($relocation && $relocation->checkCompany())) {
            goto end_of_function;
        }

        $services = $relocation->getActiveRelocationServiceCompany();
        $services_arr = [];
        foreach ($services as $service) {
            $services_arr[] = [
                'uuid' => $service->getUuid(),
                'id' => (int)$service->getId(),
                'name' => $service->getServiceCompany()->getName(),
                'state' => $service->getFrontendState()
            ];
        }
        $return = [
            'success' => true,
            'data' => $services_arr
        ];


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_uuid
     * @return mixed
     */
    public function getAllTasksServicesListAction($relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

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


//    /**
//     * @param $relocation_uuid
//     * @return mixed
//     */
//    public function getAllServicesAction($relocation_uuid)
//    {
//        $this->view->disable();
//        $this->checkAjax(['GET', 'PUT']);
//        $this->checkAcl('index', $this->router->getControllerName());
//
//        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
//        if ($relocation_uuid != '') {
//            $relocation = Relocation::findFirstByUuid($relocation_uuid);
//            if ($relocation && $relocation->checkCompany()) {
//
//                $servicesRelocation = $relocation->getActiveRelocationServiceCompany();
//                $servicesCompany = ServiceCompany::getFullListOfMyCompany();
//                $servicesArray = [];
//
//                foreach ($servicesCompany as $serviceCompany) {
//
//                    $item = [
//                        'id' => $serviceCompany->getId(),
//                        'code' => $serviceCompany->getCode(),
//                        'status' => $serviceCompany->getStatus(),
//                        'uuid' => $serviceCompany->getUuid(),
//                        'name' => $serviceCompany->getName(),
//                        'selected' => false,
//                        'relocation_id' => $relocation->getId(),
//                        'relocation_uuid' => $relocation->getUuid(),
//                        'relocation_service_company_uuid' => ''
//                    ];
//
//                    $item['svp_company_id'] = null;
//                    $item['svp_company_uuid'] = null;
//                    $item['svp_name'] = null;
//
//                    foreach ($servicesRelocation as $serviceRelocationItem) {
//
//                        if ($serviceRelocationItem->getServiceCompanyId() == $serviceCompany->getId()) {
//                            if ($serviceRelocationItem->isActive()) {
//                                $item['selected'] = true;
//                                if ($serviceRelocationItem->getServiceProviderCompany()) {
//                                    $item['svp_company_id'] = $serviceRelocationItem->getServiceProviderCompany()->getId();
//                                    $item['svp_company_uuid'] = $serviceRelocationItem->getServiceProviderCompany()->getUuid();
//                                    $item['svp_name'] = $serviceRelocationItem->getServiceProviderCompany()->getName();
//                                }
//                                $item['progress'] = $serviceRelocationItem->getEntityProgressValue();
//                            } else {
//                                $item['selected'] = false;
//                                $item['progress'] = 0;
//                            }
//                            $item['frontend_state'] = $serviceRelocationItem->getFrontendState();
//                            $item['relocation_uuid'] = $serviceRelocationItem->getRelocationId();
//                            $item['relocation_service_company_uuid'] = $serviceRelocationItem->getUuid();
//                            $item['description'] = $serviceRelocationItem->getName();
//                        }
//                    }
//
//
//                    if ($serviceCompany->isArchived()) {
//                        if (isset($item['relocation_service_company_uuid']) &&
//                            $item['relocation_service_company_uuid'] != "" &&
//                            Helpers::__isValidUuid($item['relocation_service_company_uuid']) &&
//                            isset($item['selected']) && $item['selected'] == true
//                        ) {
//                            $servicesArray[$serviceCompany->getUuid()] = $item;
//                        }
//                    } else {
//                        $servicesArray[$serviceCompany->getUuid()] = $item;
//                    }
//
//                };
//
//
//                $this->response->setJsonContent([
//                    'success' => true,
//                    'data' => ($servicesArray)
//                ]);
//                return $this->response->send();
//
//            }
//        }
//        $this->response->setJsonContent($return);
//        return $this->response->send();
//    }

    /**
     * @param $relocation_uuid
     * @return mixed
     */
    public function getAllServicesAction(string $relocation_uuid = null)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::__findFirstByUuidCache($relocation_uuid, CacheHelper::__TIME_24H);
            if ($relocation && $relocation->checkCompany()) {
                $query = Helpers::__getRequestValue('query');
                $servicesRelocation = $relocation->getActiveRelocationServiceCompany($query);
                $servicesArray = [];
                $orderSetting = [];
                foreach ($servicesRelocation as $serviceRelocationItem) {
                    $orderSetting[] = intval($serviceRelocationItem->getId());
                    $serviceCompany = ServiceCompany::__findFirstByIdWithCache($serviceRelocationItem->getServiceCompanyId(), CacheHelper::__TIME_24H);
                    $item = [
                        'id' => $serviceCompany->getId(),
                        'code' => $serviceCompany->getCode(),
                        'status' => $serviceCompany->getStatus(),
                        'uuid' => $serviceCompany->getUuid(),
                        'name' => $serviceCompany->getName(),
                        'selected' => false,
                        'relocation_id' => $relocation->getId(),
                        'relocation_uuid' => $relocation->getUuid(),
                        'relocation_service_company_uuid' => ''
                    ];
                    $item['svp_company_id'] = null;
                    $item['svp_company_uuid'] = null;
                    $item['svp_name'] = null;

                    if ($serviceRelocationItem->isActive()) {
                        $item['selected'] = true;
                        $svpCompany = $serviceRelocationItem->getServiceProviderCompany();
                        if ($svpCompany) {
                            $item['svp_company_id'] = intval($svpCompany->getId());
                            $item['svp_company_uuid'] = $svpCompany->getUuid();
                            $item['svp_name'] = $svpCompany->getName();
                        }
                        $item['progress'] = $serviceRelocationItem->getEntityProgressValue();
                    } else {
                        $item['selected'] = false;
                        $item['progress'] = 0;
                    }

                    $item['progress_status'] = intval($serviceRelocationItem->getProgress());


                    $item['frontend_state'] = $serviceRelocationItem->getFrontendState();
                    $item['relocation_uuid'] = $serviceRelocationItem->getRelocationId();
                    $item['relocation_service_company_uuid'] = $serviceRelocationItem->getUuid();
                    $item['relocation_service_company_id'] = $serviceRelocationItem->getId();
                    $item['description'] = $serviceRelocationItem->getName();
                    $item['number'] = $serviceRelocationItem->getNumber();

                    if ($serviceCompany && $serviceCompany->isArchived()) {
                        if (isset($item['relocation_service_company_uuid']) && $item['relocation_service_company_uuid'] != "" &&
                            Helpers::__isValidUuid($item['relocation_service_company_uuid']) &&
                            isset($item['selected']) && $item['selected'] == true
                        ) {
                            $servicesArray[] = $item;
                        }
                    } else {
                        $servicesArray[] = $item;
                    }
                }

                // List order setting
                if (count($orderSetting) > 0) {
                    $check = ListOrderSetting::checkOrderSetting([
                        'uuid' => $relocation->getUuid(),
                        'list_type' => ListOrderSetting::TYPE_SERVICE,
                        'object_type' => ListOrderSetting::OBJECT_TYPE_RELOCATION,
                        'orderSetting' => json_encode($orderSetting)
                    ]);
                }

                if (count($servicesArray) > 0) {
                    $listOrderSetting = ListOrderSetting::findFirst(
                        [
                            'conditions' => 'object_type = :object_type: AND object_uuid = :object_uuid: AND list_type = :list_type:',
                            'bind' => [
                                'object_type' => ListOrderSetting::OBJECT_TYPE_RELOCATION,
                                'object_uuid' => $relocation->getUuid(),
                                'list_type' => ListOrderSetting::TYPE_SERVICE,
                            ]
                        ]
                    );
                    $_order = json_decode($listOrderSetting->getOrderSetting());

                    if (is_array($_order) && count($_order) != count($orderSetting)) {
                        $listOrderSetting->setOrderSetting(json_encode($orderSetting, true));
                        $resultSaveOrderSetting = $listOrderSetting->__quickSave();

                        $_order = json_decode($listOrderSetting->getOrderSetting());
                    }
                    if (!is_array($_order) && !$listOrderSetting->getOrderSetting()) {
                        $listOrderSetting->setOrderSetting(json_encode($orderSetting, true));
                        $resultSaveOrderSetting = $listOrderSetting->__quickSave();

                        $_order = json_decode($listOrderSetting->getOrderSetting());
                    }

                    if (is_array($_order) && sizeof($_order) > 0) {
                        Helpers::__reArrangeArray($servicesArray, $_order, 'relocation_service_company_id');
                    }
                }

                $this->response->setJsonContent([
                    'success' => true,
                    'data' => $servicesArray,
                ]);
                return $this->response->send();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Load all needs assessment belong this relocation dependent on service
     * @param string $uuid
     */
    public function needAssessmentListAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($uuid != '') {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $result = $relocation->getAllNeedFormGabarit();
                if ($result['success'] == true) {
                    $need_assessment_array = [];
                    foreach ($result['data'] as $item) {
                        $need_assessment_array[$item->getId()] = $item->toArray();
//                        $need_assessment_array[$item->getId()]['service_name'] = $item->getServiceCompany()->getName();
                        $need_assessment_array[$item->getId()]['category_name'] = "";

                        $request = $item->getNeedFormRequestOfRelocation($relocation->getId());
                        if ($request) {
                            $need_assessment_array[$item->getId()]['request_uuid'] = $request->getUuid();
                            $need_assessment_array[$item->getId()]['request_status'] = $request->getStatus();
                            $need_assessment_array[$item->getId()]['sent_on'] = $request->getSentOn();
                            $need_assessment_array[$item->getId()]['sent_on_time'] = is_string($request->getSentOn()) ? strtotime($request->getSentOn()) : $request->getSentOn();

                            $need_assessment_array[$item->getId()]['relocation_service_company_id'] = $request->getRelocationServiceCompanyId();
                            $need_assessment_array[$item->getId()]['relocation_service_company_uuid'] = $request->getRelocationServiceCompany()->getUuid();
                        } else {
                            $need_assessment_array[$item->getId()]['request_uuid'] = '';
                            $need_assessment_array[$item->getId()]['request_status'] = 0;
                            $need_assessment_array[$item->getId()]['sent_on'] = '';
                            $need_assessment_array[$item->getId()]['sent_on_time'] = null;
                            $need_assessment_array[$item->getId()]['relocation_service_company_id'] = 0;
                            $need_assessment_array[$item->getId()]['relocation_service_company_uuid'] = '';
                        }

//                        $relocation_service_company = $relocation->getRelocationServiceCompanyOfServive($item->getServiceCompanyId());
//                        if ($relocation_service_company) {
//                            $need_assessment_array[$item->getId()]['relocation_service_company_id'] = $relocation_service_company->getId();
//                            $need_assessment_array[$item->getId()]['relocation_service_company_uuid'] = $relocation_service_company->getUuid();
//                        }
                        $need_assessment_array[$item->getId()]['relocation_uuid'] = $relocation->getUuid();
                    }
                    $return = ['success' => true, 'data' => $need_assessment_array];
                } else {
                    $return = $result;
                }
            }
        }

        end:
        if ($return['success'] == false) {
            $this->response->setStatusCode(404, 'Not Found');
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function searchRelocationAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_RELOCATION, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION_SERVICE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['employee_id'] = Helpers::__getRequestValue('employee_id');
        $params['status'] = Helpers::__getRequestValue('status');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['length'] = Helpers::__getRequestValue('length');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['simple'] = Helpers::__getRequestValue('simple');
        $params['active'] = Helpers::__getRequestValue('active');
        $params['isArchived'] = Helpers::__getRequestValue('isArchived');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['employee_id'] = Helpers::__getRequestValue('employee_id');
        $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');
        $params['owner_uuid'] = Helpers::__getRequestValue('owner_uuid');
        //var_dump( $params['active'] ); die();

        if ($params['active'] === null || $params['active'] === '') {
            //active default
            $params['active'] = Relocation::STATUS_ACTIVATED;
        }

        //var_dump( $params['active'] ); die();

        /****** destination ****/
        $destinations = Helpers::__getRequestValue('destinations');
        $country_destination_ids = [];

        if (is_array($destinations) && count($destinations) > 0) {
            foreach ($destinations as $item) {
                $item = (array)$item;
                if (isset($item['id'])) {
                    $country_destination_ids[] = $item['id'];
                }
            }
        }
        $params['country_destination_ids'] = $country_destination_ids;


        /****** origin ****/
        $origins = Helpers::__getRequestValue('origins');
        $country_origin_ids = [];

        if (is_array($origins) && count($origins) > 0) {
            foreach ($origins as $item) {
                $item = (array)$item;
                if (isset($item['id'])) {
                    $country_origin_ids[] = $item['id'];
                }
            }
        }
        $params['country_origin_ids'] = $country_origin_ids;

        /**** bookers ***/
        $bookers = Helpers::__getRequestValue('bookers');
        $bookersIds = [];
        if (is_array($bookers) && count($bookers) > 0) {
            foreach ($bookers as $booker) {
                $booker = (array)$booker;
                if (isset($booker['id'])) {
                    $bookersIds[] = $booker['id'];
                }
            }
        }

        $hrCompanyId = Helpers::__getRequestValue('hr_company_id');
        $hrCompany = Company::__findFirstByIdWithCache($hrCompanyId);
        if ($hrCompany) {
            if ($hrCompany->getCompanyTypeId() == Company::TYPE_BOOKER) {
                $bookersIds[] = $hrCompanyId;
                unset($params['hr_company_id']);
            } else if ($hrCompany->getCompanyTypeId() == Company::TYPE_HR) {
                $params['company_id'] = $hrCompanyId;
            }
        }


        $company_id = Helpers::__getRequestValue('company_id');
        $company = Company::findFirstById($company_id);
        if ($company instanceof Company && $company->getCompanyTypeId() == Company::TYPE_BOOKER) {
            $bookersIds[] = $company_id;
        } else {
            $params['company_id'] = $company_id;
        }


        /****** companies ****/
        $companies = Helpers::__getRequestValue('companies');
        $companiesIds = [];

        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $item) {
                $item = (array)$item;
                if (isset($item['id'])) {
                    $company = Company::findFirstById($item['id']);
                    if ($company instanceof Company && $company->getCompanyTypeId() == Company::TYPE_HR) {
                        $companiesIds[] = $item['id'];
                    } else if ($company instanceof Company && $company->getCompanyTypeId() == Company::TYPE_BOOKER) {
                        $bookersIds[] = $item['id'];
                    }
                }
            }
        }
        $params['companies'] = $companiesIds;
        $params['bookers'] = $bookersIds;
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
        /****** owners ****/
        $owners = Helpers::__getRequestValue('owners');
        $ownersUuids = [];

        if (is_array($owners) && count($owners) > 0) {
            foreach ($owners as $owner) {
                $owner = (array)$owner;
                if (isset($owner['uuid'])) {
                    $ownersUuids[] = $owner['uuid'];
                }
            }
        }
        $params['owners'] = $ownersUuids;
        /****** start date ****/
        $start_date = Helpers::__getRequestValue('start_date');
        if (Helpers::__isDate($start_date)) {
            $params['start_date'] = $start_date;
        }
        /****** end date ****/
        $end_date = Helpers::__getRequestValue('end_date');
        if (Helpers::__isDate($end_date)) {
            $params['end_date'] = $end_date;
        }
        /****** end date ****/


        /**** admin or not admin ***/
        if (ModuleModel::$user_profile->isAdminOrManager() == false) {
            $params['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
        }
        /***** new filter ******/
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


        $return = Relocation::__findWithFilterInList($params, $ordersConfig);

        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }

        $return['params'] = $params;
        $return['ordersConfig'] = $ordersConfig;

        if ($return['success'] == true) {
            $this->response->setJsonContent($return);
            $this->response->send();
        } else {
            $this->response->setJsonContent($return);
            $this->response->send();
        }

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function inviteAssigneeAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());

        $relocation_uuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'data' => [], 'message' => 'INVITE_ASSIGNEE_FAIL_TEXT'];

        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $employee = $relocation->getEmployee();
                if ($employee && $employee->manageByGms()) {
                    $return = EmployeeHelper::__reInvite($employee, EmailTemplateDefault::ASSIGNEE_LOGIN);
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * remove service
     * @return mixed
     */
    public function desactivateServiceAction(string $uuid = null)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();
        $relocationServiceCompany = false;

//        $password = Helpers::__getRequestValue('password');
//        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user_login->getEmail(), $password);
//        if ($checkPassword['success'] == false) {
//            $return = $checkPassword;
//            $return['message'] = 'PASSWORD_INCORRECT_TEXT';
//            goto end_of_function;
//        }

        $return = [
            'success' => false,
            'message' => 'RELOCATION_NOT_FOUND_TEXT'
        ];

        if ($uuid == '' || is_null($uuid)) {
            $uuid = Helpers::__getRequestValue('uuid');
        }

        if (Helpers::__isValidUuid($uuid)) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($uuid);
        }

        if (!$relocationServiceCompany) {
            $return = [
                'success' => false,
                'data' => $relocationServiceCompany,
                'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        if ($relocationServiceCompany->belongsToGms() == false) {
            $return = [
                'success' => false,
                'data' => $relocationServiceCompany,
                'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        if ($relocationServiceCompany->isEditable() == false) {
            $return = [
                'success' => false,
                'message' => 'RELOCATION_SERVICE_NOT_EDITABLE_TEXT'
            ];
            goto end_of_function;
        }

        $this->db->begin();
        $return = $relocationServiceCompany->archive();
        if ($return['success'] == true) {
            $this->db->commit();
            $return['success'] = true;
            $return['message'] = 'REMOVE_SERVICE_SUCCESS_TEXT';
        } else {
            $this->db->rollback();
            $return['success'] = false;
            $return['message'] = 'REMOVE_SERVICE_FAIL_TEXT';
        }

        end_of_function:

        if ($return['success'] == true && isset($relocationServiceCompany)) {

            $queueRelocation = RelodayQueue::__getQueueRelocationService();
            $return['queueDelete'] = $queueRelocation->addQueue([
                'action' => 'remove',
                'uuid' => $relocationServiceCompany->getUuid()
            ]);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocationServiceCompany, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_REMOVE, [
                'uuid' => $relocationServiceCompany->getUuid(),
            ]);

            ModuleModel::$relocationServiceCompany = $relocationServiceCompany;
            ModuleModel::$relocation = $relocationServiceCompany->getRelocation();
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocationServiceCompany, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_REMOVE);
            $this->dispatcher->setParam('return', $return);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * activate re - activate service
     * @return mixed
     */
    public function activateServiceAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $data = Helpers::__getRequestValues();

        $relocation_service_company_uuid = Helpers::__getRequestValue('relocation_service_company_uuid');
        $relocation_uuid = Helpers::__getRequestValue('relocation_uuid');
        $service_company_uuid = Helpers::__getRequestValue('uuid');
        $ownerInput = Helpers::__getRequestValueAsArray('owner');
        $description = Helpers::__getRequestValue('description');
        $dependant_id = Helpers::__getRequestValue('dependant_id');
        $relocationServiceCompany = false;
        $relocation = false;

        if (Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
        }

        if (!$relocation) {
            $return = ['success' => true, 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($relocation && $relocation->isEditable() == false) {
            $return = ['success' => true, 'message' => 'RELOCATION_NOT_EDITABLE_TEXT'];
            goto end_of_function;
        }

        if (Helpers::__isValidUuid($relocation_service_company_uuid)) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);
        }


        if ($relocationServiceCompany && $relocationServiceCompany->belongsToGms() == true) {

            if ($relocationServiceCompany->isArchived()) {
                $this->db->begin();
                $return = $relocationServiceCompany->reactivate();
                if ($return['success'] == true) {
                    $return['message'] = 'ADD_SERVICE_SUCCESS_TEXT';
                    $relocation = $relocationServiceCompany->getRelocation();
                    if (!isset($ownerInput['id']) || !$ownerInput['id'] > 0) {
                        $this->db->rollback();
                        $return['success'] = false;
                        $return['message'] = 'OWNER_REQUIRED_TEXT';
                        goto end_of_function;
                    }
                    $ownerProfileDefault = UserProfile::findFirstByIdCache($ownerInput['id'], CacheHelper::__TIME_5_MINUTES);
                    //add OWNER
                    if ($ownerProfileDefault) {
                        $res = $relocationServiceCompany->addOwner($ownerProfileDefault);
                        if ($res['success'] = false) {
                            $this->db->rollback();
                            $return['success'] = false;
                            $return['detail'] = $res;
                            $return['details'] = 'Owner Not Found';
                            $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                            goto end_of_function;
                        }
                    }
                    $reporterProfileDefault = ModuleModel::$user_profile;
                    if ($reporterProfileDefault) {
                        $res = $relocationServiceCompany->addReporter($reporterProfileDefault);
                        if ($res['success'] = false) {
                            $this->db->rollback();
                            $return['success'] = false;
                            $return['details'] = 'Reporter Not Found';
                            $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                            goto end_of_function;
                        }
                    }
                    $return['$startAddAttachment'] = $relocationServiceCompany->startAddAttachment();
                    $return['$startAddWorkflow'] = $relocationServiceCompany->startAddWorkflow();

                    //add OWNER
                    $this->db->commit();
                    $return['owner'] = $ownerProfileDefault;
                    $return['reporter'] = $reporterProfileDefault;
                    $return['message'] = 'ADD_SERVICE_SUCCESS_TEXT';
                    goto end_of_function;
                } else {
                    $this->db->rollback();
                    $return['success'] = false;
                    $return['details'] = 'Can not add new service';
                    $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                    goto end_of_function;
                }
            } else {
                $return = ['success' => true, 'message' => 'ADD_SERVICE_SUCCESS_TEXT'];
            }
        } else {

            if ($relocation_uuid != '' && $service_company_uuid != '') {
                if ($relocation && $relocation->checkCompany()) {
                    $serviceCompany = ServiceCompany::findFirstByUuid($service_company_uuid);
                    if ($serviceCompany && $serviceCompany->belongsToGms()) {


                        $this->db->begin();
                        if (Helpers::__isValidId($dependant_id)) {
                            $return = RelocationServiceCompany::__createNew(null, $relocation, $serviceCompany, $description, $dependant_id);
                        } else {
                            $return = RelocationServiceCompany::__createNew(null, $relocation, $serviceCompany, $description, null);
                        }

                        if ($return['success'] == true) {
                            $relocationServiceCompany = $return['data'];
                            if (!isset($ownerInput['id']) || !$ownerInput['id'] > 0) {
                                $this->db->rollback();
                                $return['success'] = false;
                                $return['details'] = 'Owner not found';
                                $return['message'] = 'OWNER_REQUIRED_TEXT';
                                goto end_of_function;
                            }
                            $ownerProfileDefault = UserProfile::findFirstByIdCache($ownerInput['id']);

                            //add OWNER
                            if ($ownerProfileDefault) {
                                $resultAddOwner = $relocationServiceCompany->addOwner($ownerProfileDefault);
                                if ($resultAddOwner['success'] = false) {
                                    $this->db->rollback();
                                    $return['success'] = false;
                                    $return['owner'] = $ownerInput;
                                    $return['details'] = 'canNotAddOwner';
                                    $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }


                            /*
                             * reporter of relocation
                            $reporterProfileDefault = $relocation->getDataReporter();
                            */
                            $reporterProfileDefault = ModuleModel::$user_profile;
                            //addReporter
                            if ($reporterProfileDefault) {
                                $resultAddReporter = $relocationServiceCompany->addReporter($reporterProfileDefault);
                                if ($resultAddReporter['success'] = false) {
                                    $this->db->rollback();
                                    $return['success'] = false;
                                    $return['details'] = 'canNotAddReporter';
                                    $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }

                            $relocationServiceCompany->startAddAttachment();
                            $relocationServiceCompany->startAddWorkflow();
                            $relocationServiceCompany->startPrefillData();


                            // end prefill
                            $this->db->commit();
                            //$return['owner'] = $ownerProfileDefault;
                            //$return['reporter'] = $reporterProfileDefault;
                            $return['message'] = 'ADD_SERVICE_SUCCESS_TEXT';
                            $return['data'] = $relocationServiceCompany;

                            goto end_of_function;

                        } else {
                            $this->db->rollback();
                            if ($return['detail'] == 'NAME_MUST_BE_UNIQUE_TEXT') {
                                $return['success'] = false;
                                $return['details'] = 'Service name must be unique';
                                $return['message'] = 'NAME_MUST_BE_UNIQUE_TEXT';
                            } else {
                                $return['success'] = false;
                                $return['details'] = 'Can not create new Service';
                                $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                            }
                            goto end_of_function;
                        }
                    }
                }
            }
        }


        end_of_function:

        if ($return['success'] == true && isset($relocationServiceCompany)) {
            ModuleModel::$relocationServiceCompany = $relocationServiceCompany;
            ModuleModel::$relocation = $relocationServiceCompany->getRelocation();
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocationServiceCompany, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_CREATE);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Apply workflow to Relocation
     * @Route("/assignment", paths={module="gms"}, methods={"POST"}, name="apply-workflow")
     */
    public function applyWorkflowAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('edit', $this->router->getControllerName());

        $uuid = Helpers::__getRequestValue("uuid");
        $workflow_id = Helpers::__getRequestValue("workflow");
        $tasks = Helpers::__getRequestValue("tasks");
        $assigneeTasks = Helpers::__getRequestValue("assigneeTasks");
        $taskType = Helpers::__getRequestValue("taskType");
        $relocation = false;
        $workflow = false;
        $listTaskUuids = [];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
        }

        if (!$relocation) {
            $return = [
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        if ($relocation->belongsToGms() == false || $relocation->checkMyEditPermission() == false) {
            $return = [
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT',
                'data' => $relocation
            ];
            goto end_of_function;
        }

        if (Helpers::__isValidId($workflow_id)) {
            $workflow = Workflow::findFirstById($workflow_id);
        }

        if (!$workflow || $workflow->belongsToGms() == false) {
            $return = [
                'success' => false,
                'message' => 'WORKFLOW_NOT_FOUND_TEXT',
                'data' => $workflow
            ];
            goto end_of_function;
        }


        $workflow_uuid = $workflow->getUuid();
//        $task_workflows = TaskWorkflow::getByWorkflowUuid($workflow_uuid);
        // update: task_template
        if ($taskType) {
            $taskTemplates = TaskTemplate::getByWorkflowUuid($workflow_uuid, $taskType);
        } else {
            $taskTemplates = TaskTemplate::getByWorkflowUuid($workflow_uuid, Task::TASK_TYPE_INTERNAL_TASK);
        }

        $task_created_list = [];
        $task_deleted_list = [];
        $tasks_list = [];
        $count = 0;
        $this->db->begin();
        if ($taskTemplates->count() > 0) {

            foreach ($taskTemplates as $taskTemplate) {
                $count++;
                $custom = $taskTemplate->toArray();
                $custom['assignment_id'] = $relocation->getAssignmentId();
                $custom['relocation_id'] = $relocation->getId();
                $custom['link_type'] = Task::LINK_TYPE_RELOCATION;
                $custom['object_uuid'] = $uuid;
                $custom['company_id'] = ModuleModel::$company->getId();
                $custom['owner_id'] = ModuleModel::$user_profile->getId(); //get owner of task
                $custom['creator_id'] = ModuleModel::$user_profile->getId(); //get creator of task
                if ($taskTemplate->getTaskType() == TaskTemplate::IS_ASSIGNEE_TASK) {
                    $numberExistedTasks = $relocation->countAssigneeTasks();
                } else {
                    $numberExistedTasks = $relocation->countTasks();
                }
                $taskTemplateSequence = $taskTemplate->getSequence() ? $taskTemplate->getSequence() : 0;

                $taskObject = new Task();
                $taskObject->setData($custom);
                $taskObject->setEmployeeId($relocation->getEmployeeId());
                $taskObject->setSequence($numberExistedTasks + $taskTemplateSequence);
                $taskResult = $taskObject->__quickCreate();
                $listTaskUuids[] = $taskObject->getUuid();
                $task_created_list[] = $taskObject;
                if ($taskResult['success'] == true) {
                    /// add reporter of RELOCATION
                    $reporter = $relocation->getDataReporter();
                    if ($reporter) {
                        $taskObject->addReporter($reporter);
                    }
                    /// add owner of RELOCATION
                    $owner = $relocation->getDataOwner();
                    if ($owner) {
                        $resultAddOwner = $taskObject->addOwner($owner);
                        if (!$resultAddOwner["success"]) {
                            $this->db->rollback();
                            $return = ['success' => false, 'detailAddOwner' => $resultAddOwner, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                            $this->response->setJsonContent($return);
                            return $this->response->send();
                        }
                        $resultAddCreator = $taskObject->addCreator(ModuleModel::$user_profile);
                        if (!$resultAddCreator["success"]) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'detailAddCreator' => $resultAddCreator,
                                'message' => 'TASK_LIST_CREATE_FAILED_TEXT'
                            ];
                            $this->response->setJsonContent($return);
                            return $this->response->send();
                        }
                    }

//                    $checklists = TaskWorkflow::find([
//                        "conditions" => "object_uuid = :object_uuid: AND link_type = :link_type:",
//                        "bind" => [
//                            "object_uuid" => $task_workflow->getUuid(),
//                            "link_type" => TaskWorkflow::TASK_WORKFLOW
//                        ]
//                    ]);

                    $checklists = \Reloday\Gms\Models\TaskTemplateChecklist::find([
                        "conditions" => "object_uuid = :object_uuid:",
                        "bind" => [
                            "object_uuid" => $taskTemplate->getUuid(),
                        ]
                    ]);

                    $tasks_list[$taskObject->getUuid()] = $taskObject->toArray();
                    $tasks_list[$taskObject->getUuid()]['checklist'][] = $checklists;
                    if (count($checklists) > 0) {
                        foreach ($checklists as $checklist) {
                            $taskItemObject = new TaskChecklist();
                            $taskItemObject->setData();
                            $taskItemObject->setObjectUuid($taskObject->getUuid());
                            $taskItemObject->setName($checklist->getName());
                            $taskItemObject->setTaskId($taskObject->getId());
                            $taskItemObject->setSequence($checklist->getSequence());

                            $taskItemResult = $taskItemObject->__quickCreate();
                            if ($taskItemResult['success'] == false) {
                                $this->db->rollback();
                                $return = ['success' => false, 'detail' => $taskItemResult, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT', 'raw' => $taskObject];
                                $this->response->setJsonContent($return);
                                return $this->response->send();
                            }
                            $tasks_list[$taskObject->getUuid()]['checklist_create'][] = $taskItemObject;
                        }
                    }

                    $reminders = $taskTemplate->getTaskTemplateReminders();

                    if (count($reminders) > 0) {
                        foreach ($reminders as $reminder) {
                            $reminderConfig = new ReminderConfig();

                            $reminderConfig->setUuid(Helpers::__uuid());
                            $reminderConfig->setRelocationId($relocation->getId());
                            $reminderConfig->setNumber($taskObject->getNumber());
                            $reminderConfig->setObjectUuid($taskObject->getUuid());
                            $reminderConfig->setType(ReminderConfig::TYPE_BASE_ON_EVENT);
                            $reminderConfig->setReminderTime($reminder->getReminderTime());
                            $reminderConfig->setReminderTimeUnit($reminder->getReminderTimeUnit());
                            $reminderConfig->setBeforeAfter($reminder->getBeforeAfter());
                            $reminderConfig->setIsSent(0);
                            $reminderConfig->setStatus(ReminderConfig::STATUS_ON_LOADING);
                            $event = $reminder->getEvent();
                            if ($event) {
                                $eventValue = EventValue::__findByOjbectAndEvent($relocation->getId(), $event->getId());
                                if (!$eventValue) {
                                    $eventValue = new EventValue();
                                    if ($event->getObjectSource() == Event::SOURCE_RELOCATION) {
                                        $object = $relocation;
                                    }else {
                                        $this->db->rollback();
                                        $return = [
                                            "success" => false,
                                            "detail" => "event of object do not exist",
                                            "event" => $event,
                                            "task" => $taskObject
                                        ];
                                        goto end_of_function;
                                    }
                                    $eventValue->setObjectId($object->getId());
                                    $eventValue->setEventId($event->getId());
                                    $eventValue->setValue($event->getFieldType() == Event::FIELD_TYPE_DATETIME ? strtotime($object->get($event->getFieldName())) : $object->get($event->getFieldName()));
                                    $eventValue->setData();
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
                            $reminderConfig->setCompanyId(ModuleModel::$company->getId());
                            $reminderConfig->setCreatedAt(time());
                            $reminderConfig->setUpdatedAt(time());

                            $return = $reminderConfig->__quickCreate();
                            if (!$return["success"]) {
                                $this->db->rollback();
                                goto end_of_function;
                            }
                        }
                    }

                    //Task Files
                    $taskFiles = $taskTemplate->getTaskFiles();
                    if (count($taskFiles) > 0) {
                        foreach ($taskFiles as $taskFile) {
                            $itemTaskFile = $taskFile->toArray();
                            unset($itemTaskFile['created_at']);
                            unset($itemTaskFile['updated_at']);
                            unset($itemTaskFile['uuid']);
                            unset($itemTaskFile['object_uuid']);
                            unset($itemTaskFile['id']);

                            $newTaskFile = new TaskFile();
                            $newTaskFile->setData($itemTaskFile);
                            $newTaskFile->setUuid(Helpers::__uuid());
                            $newTaskFile->setObjectUuid($taskObject->getUuid());

                            $resultTaskFile = $newTaskFile->__quickSave();

                            if (!$resultTaskFile['success']) {
                                $return = $resultTaskFile;
                                $return['data'] = $newTaskFile;
                                $this->db->rollback();
                                goto end_of_function;
                            }
                        }
                    }

                } else {
                    $this->db->rollback();
                    $return = ['success' => false, 'data' => $tasks_list, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT', 'raw' => $taskObject];
                    $this->response->setJsonContent($return);
                    return $this->response->send();
                }
            }
        }
        $isRemove = false;
        if (is_array($tasks) && count($tasks) > 0) {
            $customHistoryParams = [
                'companyUuid' => ModuleModel::$company->getUuid(),
                'currentUserUuid' => ModuleModel::$user_profile->getUuid(),
                'language' => ModuleModel::$system_language,
                'ip' => $this->request->getClientAddress(),
                'appUrl' => ModuleModel::$app->getFrontendUrl(),
                'historyObjectUuids' => [$relocation->getUuid()]
            ];

            foreach ($tasks as $task) {
                if ($task->keep == 0) {
                    $isRemove = true;
                    $taskToRemove = Task::findFirstByUuid($task->uuid);
                    $resultRemove = $taskToRemove->__quickRemove();
                    $task_deleted_list[] = $taskToRemove;

                    if ($resultRemove['success'] == false) {
                        $return = ['success' => false, 'detail' => $resultRemove, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                        $this->db->rollback();
                        $this->response->setJsonContent($return);
                        return $this->response->send();
                    }

                    $taskToRemove->sendHistoryToQueue(History::HISTORY_REMOVE, $customHistoryParams);
                }
            }
        }
        if (is_array($assigneeTasks) && count($assigneeTasks) > 0) {
            $customHistoryParams = [
                'companyUuid' => ModuleModel::$company->getUuid(),
                'currentUserUuid' => ModuleModel::$user_profile->getUuid(),
                'language' => ModuleModel::$system_language,
                'ip' => $this->request->getClientAddress(),
                'appUrl' => ModuleModel::$app->getFrontendUrl(),
                'historyObjectUuids' => [$relocation->getUuid()]
            ];

            foreach ($assigneeTasks as $task) {
                if ($task->keep == 0) {
                    $isRemove = true;
                    $taskToRemove = Task::findFirstByUuid($task->uuid);
                    $resultRemove = $taskToRemove->__quickRemove();
                    if ($resultRemove['success'] == false) {
                        $return = ['success' => false, 'detail' => $resultRemove, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                        $this->db->rollback();
                        $this->response->setJsonContent($return);
                        return $this->response->send();
                    }

                    $taskToRemove->sendHistoryToQueue(History::HISTORY_ASSIGNEE_TASK_DELETE, $customHistoryParams);
                }
            }
        }

        $attachments = MediaAttachment::__get_attachments_from_uuid($workflow_uuid);
        if (count($attachments) > 0) {
            $folders = $relocation->getFolders();
            if (!$folders['success']) {
                $this->db->rollback();
                $return = ['success' => false, 'folders' => $folders, 'message' => 'ATTACHMENT_SAVE_FAILED_TEXT'];
                goto end_of_function;
            }

            foreach ($attachments as $media) {
                $attachment = MediaAttachment::__findByObjectAndMediaId($folders['object_folder_dsp']->getUuid(), $media['id']);
                if ($attachment) {
                    goto end_of_attachment;
                }
            }

            $resultAttach = MediaAttachment::__createAttachments([
                'objectUuid' => $folders['object_folder_dsp']->getUuid(),
                'fileList' => $attachments,
            ]);
            if (!$resultAttach["success"]) {
                if (!isset($resultAttach["detail"]) ||
                    (isset($resultAttach["detail"]) && isset($resultAttach["detail"]['message']) && $resultAttach["detail"]['message'] != "MEDIA_ALREADY_ATTACHED_TEXT")) {
                    $this->db->rollback();
                    $return = ['success' => false, 'detail' => $resultAttach, 'message' => 'ATTACHMENT_SAVE_FAILED_TEXT'];
                    goto end_of_function;
                }
            }
        }
        end_of_attachment:

        /** set workflow applied = true */

        $relocation->setWorkflowId($workflow_id);
        $resultSaveRelocation = $relocation->__quickUpdate();
        if ($resultSaveRelocation["success"] == false) {
            $return = [
                'success' => false,
                'relocation' => $resultSaveRelocation,
                'message' => 'DATA_SAVE_FAILED_TEXT
                '];
            goto end_of_function;
        }

        $this->db->commit();

        if ($isRemove) {
            //SET SEQUENCE AGAIN
            $relocation->setSequenceOfTasks();
            $relocation->setSequenceOfAssigneeTasks();
        }

        $return = [
            'success' => true,
            'data' => $tasks_list,
            'message' => 'TASK_LIST_CREATE_SUCCESS_TEXT',
            'template' => $taskTemplates,
            'attachment' => $attachments
        ];


        end_of_function:
        if ($return['success'] == true && isset($relocation)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_APPLY_WORKFLOW);
            ModuleModel::$relocation = $relocation;
            ModuleModel::$task_deleted_list = $task_deleted_list;
            ModuleModel::$task_created_list = $task_created_list;
            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('workflow_name', $workflow ? $workflow->getName() : '');
            $this->dispatcher->setParam('listTaskUuids', $listTaskUuids);
            $this->dispatcher->setParam('isAssigneeTask', $taskType == Task::TASK_TYPE_EE_TASK);
            $this->dispatcher->setParam('task_count', $count);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @param string $id [description]ee
     * @return [type]     [description]ee
     */
    public function getDependantsAction($uuid = '')
    {
        $this->checkAjax('GET');
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $assignment = $relocation->getAssignment();
                $dependants = $assignment->getDependants();
                $dependant_array = [];
                if (count($dependants) > 0) {
                    foreach ($dependants as $dependant) {
                        if ($dependant->getStatus() == Dependant::STATUS_ACTIVE) {
                            $item = $dependant->toArray();
                            $relation = $dependant->getRelation() == Dependant::DEPENDANT_SPOUSE ? 'SPOUSE_TEXT' :
                                ($dependant->getRelation() == Dependant::DEPENDANT_CHILD ? 'CHILD_TEXT' :
                                    ($dependant->getRelation() == Dependant::DEPENDANT_COMMON_LAW_PARTNER ? 'PARTNER_TEXT' : 'OTHER_TEXT'));
                            $item['relation'] = $relation;
                            $dependant_array[] = $item;
                        }
                    }
                }
                $result = [
                    'success' => true,
                    'data' => $dependant_array,
                ];
            } else {
                $result = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Get ALl Service Artivated
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getServicesActiveAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $serviceArray = [];
        // Load list service
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {

                $services = ServiceCompany::getListActiveOfMyCompany();

                foreach ($services as $service) {
                    $serviceArray[$service->getId()] = $service->toArray();
                    unset($serviceArray[$service->getId()]['updated_at']);
                    unset($serviceArray[$service->getId()]['created_at']);
                    unset($serviceArray[$service->getId()]['description']);
                    unset($serviceArray[$service->getId()]['includes']);
                    unset($serviceArray[$service->getId()]['excludes']);
                    unset($serviceArray[$service->getId()]['notes']);

                    $serviceArray[$service->getId()]['number_providers'] = $service->countActiveServiceProviderCompany();

                    $relocationServices = RelocationServiceCompany::find([
                        "conditions" => "status = :status: and relocation_id = :id:  and service_company_id = :service_company_id:",
                        "bind" => [
                            "status" => RelocationServiceCompany::STATUS_ACTIVE,
                            "id" => $relocation->getId(),
                            "service_company_id" => $service->getId()
                        ]
                    ]);

                    $serviceArray[$service->getId()]['count'] = count($relocationServices);

                    $serviceArray[$service->getId()]['service_template_name'] = $service->getServiceTemplateName();

                }
                $result = [
                    'success' => true,
                    'data' => array_values($serviceArray),
                ];
            } else {

                $relocationTemporaryModel = RelodayObjectMapHelper::__getObjectWithCache($uuid);

                if ($relocationTemporaryModel &&
                    $relocationTemporaryModel->isRelocation() &&
                    $relocationTemporaryModel->isCreated() == false) {

                    $services = ServiceCompany::getListActiveOfMyCompany();

                    foreach ($services as $service) {
                        $serviceArray[$service->getId()] = $service->toArray();
                        unset($serviceArray[$service->getId()]['updated_at']);
                        unset($serviceArray[$service->getId()]['created_at']);
                        unset($serviceArray[$service->getId()]['description']);
                        unset($serviceArray[$service->getId()]['includes']);
                        unset($serviceArray[$service->getId()]['excludes']);
                        unset($serviceArray[$service->getId()]['notes']);

                        $serviceArray[$service->getId()]['number_providers'] = 0;
                        $serviceArray[$service->getId()]['need_spouse'] = false;
                        $serviceArray[$service->getId()]['need_child'] = false;
                        $serviceArray[$service->getId()]['need_dependant'] = false;

                        $serviceArray[$service->getId()]['count'] = 0;
                        if ($service->getServiceId() == Service::PARTNER_SUPPORT_SERVICE) {
                            $serviceArray[$service->getId()]['need_spouse'] = true;
                        }
                        if ($service->getServiceId() == Service::SCHOOL_SEARCH_SERVICE) {
                            $serviceArray[$service->getId()]['need_child'] = true;
                        }
                        if ($service->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                            $serviceArray[$service->getId()]['need_dependant'] = true;
                        }
                        $serviceArray[$service->getId()]['service_template_name'] = $service->getServiceTemplateName();

                    }
                    $result = [
                        'success' => true,
                        'data' => array_values($serviceArray),
                    ];

                } else {
                    $result = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
                }
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * [detailAction description]
     * @return [type] [description]
     */
    public function getRemindersAction($relocationUuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if (!Helpers::__isValidUuid($relocationUuid)) {
            $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
            goto end;
        }

        $relocation = Relocation::findFirstByUuid($relocationUuid);
        if ($relocation && $relocation->belongsToGms() && $relocation->checkMyViewPermission()) {
            $params = [
                'limit' => 1000,
                'relocation_id' => $relocation->getId(),
            ];
            $return = ReminderConfig::__findWithFilters($params);
        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Use after create Relocation
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addAttachmentsServiceAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclIndex();

        $uuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }

        $relocation = Relocation::findFirstByUuid($uuid);
        if (!($relocation && $relocation->checkCompany())) {
            goto end_of_function;
        }

        $services = $relocation->getActiveRelocationServiceCompany();
        $hasTransaction = false;
        $this->db->begin();

        foreach ($services as $service) {
            $attachments = MediaAttachment::__load_attachments($service->getServiceCompany()->getUuid());

            if (count($attachments) > 0) {
                $resultAttach = MediaAttachment::__createAttachments([
                    'objectUuid' => $service->getUuid(),
                    'fileList' => $attachments,
                ]);

                if (!$resultAttach["success"]) {
                    $this->db->rollback();
                    $return = ['success' => false, 'detail' => $resultAttach, 'message' => 'ADD_SERVICE_FAIL_TEXT'];
                    goto end_of_function;
                }
            }

        }
        if ($hasTransaction == true) {
            $this->db->commit();
        }

        $return = ['success' => true];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * archive a relocation
     */
    public function cancelAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclDelete();

        $password = Helpers::__getRequestValue('password');
        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user_login->getEmail(), $password);
        if ($checkPassword['success'] == false) {
            $return = $checkPassword;
            $return['message'] = 'PASSWORD_INCORRECT_TEXT';
            goto end_of_function;
        }

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $return = $relocation->quickCancel();
                if ($return['success'] == true) $return['message'] = "RELOCATION_CANCEL_SUCCESS_TEXT";
                else $return['message'] = "RELOCATION_CANCEL_FAIL_TEXT";
            }
        }

        end_of_function:
        if ($return['success'] == true && isset($relocation)) {
            $queueRelocation = RelodayQueue::__getQueueRelocation();
            $return['queueDelete'] = $queueRelocation->addQueue([
                'action' => UserActionHelpers::METHOD_CANCEL,
                'uuid' => $relocation->getUuid()
            ]);
        }

        if ($return['success'] == true && isset($relocation)) {
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_CANCEL_RELOCATION);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function preCreateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $newUuid = Helpers::__uuid();
        $createNewObjectMap = RelodayObjectMapHelper::__createObject(
            $newUuid,
            RelodayObjectMapHelper::TABLE_RELOCATION,
            false
        );
        $return = [
            'success' => true,
            'data' => $newUuid
        ];
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

        $relocation_uuid = Helpers::__getRequestValue("uuid");
        if (Helpers::__existRequestValue("status")) {
            $this->checkAclChangeStatus();
        }

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_RELOCATION_TEXT', 'detail' => $relocation_uuid];

        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {

            $relocation = Relocation::findFirstByUuid($relocation_uuid);

            if (!($relocation && $relocation->belongsToGms() && $relocation->checkMyEditPermission())) {
                $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_RELOCATION_TEXT'];
                goto end_of_function;
            }

            $this->db->begin();
            $update = false;

            if($relocation->getStatus() == Relocation::STATUS_CANCELED){
                $return = ['success' => false, 'message' => 'CANNOT_EDIT_RELOCATION_TEXT'];
                goto end_of_function;
            }

            /*** add status **/
            if (Helpers::__existRequestValue("status")) {
                $status = intval(Helpers::__getRequestValue("status"));
                if (Relocation::__validateStatus($status)) {
                    $relocation->setStatus($status);
                    $update = true;
                    $this->dispatcher->setParam('isHistoryChangeStatus', true);
                }
            }

            /*** add date due at **/
            $start_date = Helpers::__getRequestValue("start_date");
            if ($start_date != '' && Helpers::__isDate($start_date, "Y-m-d")) {
                $relocation->setStartDate($start_date);
                $update = true;
                $this->dispatcher->setParam('isHistoryUpdate', true);
            }

            /*** add date due at **/
            $end_date = Helpers::__getRequestValue("end_date");
            if ($end_date != '' && Helpers::__isDate($end_date, "Y-m-d")) {
                $relocation->setEndDate($end_date);
                $update = true;
                $this->dispatcher->setParam('isHistoryUpdate', true);
            }

            /*** add reporter profile **/
            $report_user_profile_uuid = Helpers::__getRequestValue("report_user_profile_uuid");
            if ($report_user_profile_uuid != '' && Helpers::__isValidUuid($report_user_profile_uuid)) {
                $update = true;
                $profile = UserProfile::findFirstByUuid($report_user_profile_uuid);
                $reporter = DataUserMember::getDataReporter($relocation_uuid);
                if (!$profile || !$profile->belongsToGms()) {
                    $return = [
                        'success' => false,
                        'params' => $reporter,
                        'message' => 'REPORTER_NOT_FOUND_TEXT',
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }


                if ($profile && $reporter && $profile->getUuid() == $reporter->getUuid()) {
                    // If same reporter do nothing
                    $return = [
                        "success" => true,
                        "message" => "SET_REPORTER_SUCCESS_TEXT"
                    ];
                } else {
                    //delete current reporter if exist
                    if ($reporter) {
                        $returnDeleteReporter = DataUserMember::deleteReporters($relocation_uuid);
                        if ($returnDeleteReporter == false) {
                            $return = [
                                'success' => false,
                                'params' => $returnDeleteReporter,
                                'message' => 'SET_REPORTER_FAIL_TEXT',
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }

                    //add new reporter
                    $returnAddReporter = DataUserMember::addReporter(
                        $relocation_uuid,
                        $profile,
                        DataUserMember::MEMBER_TYPE_OBJECT_TEXT
                    );
                    if ($returnAddReporter['success'] == false) {
                        $return = [
                            'success' => false,
                            'resultDelete' => $returnDeleteReporter,
                            'params' => $returnAddReporter,
                            'message' => 'SET_REPORTER_FAIL_TEXT',
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $this->dispatcher->setParam('reporter', $profile);
                }
            }

            /** @var fix bug $owner_user_profile_uuid */
            $owner_user_profile_uuid = Helpers::__getRequestValue("owner_user_profile_uuid");
            if ($owner_user_profile_uuid != '' && Helpers::__isValidUuid($owner_user_profile_uuid)) {
                $update = true;
                $profile = UserProfile::findFirstByUuid($owner_user_profile_uuid);
                $owner = DataUserMember::getDataOwner($relocation_uuid);
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
                    // If same owner do nothing
                    $return = [
                        "success" => true,
                        "message" => "SET_OWNER_SUCCESS_TEXT"
                    ];
                } else {
                    //delete current owner
                    if ($owner) {
                        $returnDeleteOwner = DataUserMember::deleteOwners($relocation_uuid);
                        if ($returnDeleteOwner['success'] == false) {
                            $return = [
                                'success' => false,
                                'params' => $returnDeleteOwner,
                                'message' => 'SET_OWNER_FAIL_TEXT',
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }

                    //add new owner
                    $returnAddOwner = DataUserMember::addOwner($relocation_uuid, $profile, DataUserMember::MEMBER_TYPE_OBJECT_TEXT);
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
                    $this->dispatcher->setParam('owner', $profile);
                }
            }

            /*** add viewer profile **/
            $viewers = Helpers::__getRequestValue("viewers");
            if (is_array($viewers) && count($viewers) > 0) {
                $viewerObjs = [];
                foreach ($viewers as $viewer) {
                    if ($viewer != '' && Helpers::__isValidUuid($viewer)) {
                        $user = UserProfile::findFirstByUuid($viewer);

                        if ($user && ($user->belongsToGms() || $user->manageByGms())) {
                            $data_user_member = DataUserMember::findFirst([
                                "conditions" => "object_uuid = :object_uuid: AND user_profile_uuid = :user_profile_uuid: AND member_type_id = :member_type_id:",
                                'bind' => [
                                    'object_uuid' => $relocation_uuid,
                                    'user_profile_uuid' => $user->getUuid(),
                                    'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                                ]
                            ]);

                            if (!$data_user_member) {
                                $returnCreate = DataUserMember::addViewer($relocation_uuid, $user);

                                if ($returnCreate['success'] == false) {
                                    $return = ['success' => false, 'data' => $user, 'message' => 'VIEWER_ADDED_FAIL_TEXT', 'detail' => $returnCreate];
                                    $this->db->rollback();
                                    goto end_of_function;
                                }
                                $viewerObjs[] = $user;
                            }
                        }
                    }
                }
                $this->dispatcher->setParam('viewers', $viewerObjs);

                $return = [
                    'success' => true,
                    'message' => 'TASK_ADD_VIEWERS_SUCCESS_TEXT'
                ];
            }

            /*** need update data **/
            if ($update) {
                $return = $relocation->__quickUpdate();
                if ($return['success'] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }

                $refreshRelocation = Relocation::findFirstByUuid($relocation_uuid);
                $pollingCount = 1;
                while ($refreshRelocation->getUpdatedAt() != $refreshRelocation->getUpdatedAt() && $pollingCount < 10) {
                    $pollingCount++;
                    $refreshRelocation = Relocation::findFirstByUuid($relocation_uuid);
                }

                $return = [
                    'success' => true,
                    'message' => 'RELOCATION_EDIT_SUCCESS_TEXT',
                    'data' => $relocation,
                ];
            }
            $this->db->commit();
        }
        end_of_function:

        if ($return['success'] == true) {
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);
            if (Helpers::__existRequestValue("status")) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_CHANGE_STATUS);
            }
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_UPDATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Add multiple services to RE
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addServicesAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $servicePackId = Helpers::__getRequestValue('servicePackId');
        $services = Helpers::__getRequestValueAsArray('services');

        $relocationServiceCompany = false;
        $relocation = false;

        if (Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
        }

        if (!$relocation || $relocation->belongsToGms() == false) {
            $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($relocation && $relocation->isEditable() == false) {
            $return = ['success' => false, 'message' => 'RELOCATION_NOT_EDITABLE_TEXT'];
            goto end_of_function;
        }

        $relocationServiceCompanyItems = [];

        if ($relocation && $relocation->checkCompany()) {

            $employee = $relocation->getEmployee();

            if (is_array($services) && count($services) > 0) {
                $this->db->begin();
                //Update Service Pack Id to Relocation
                $servicePack = ServicePack::findFirstById($servicePackId);
                if ($servicePack) {
                    $relocation->setServicePackId($servicePack->getId());
                    $resultRelo = $relocation->__quickUpdate();
                }

                $checkExisted = RelocationServiceCompany::checkServicesNameExisted($relocation, $services, 'description');
                if ($checkExisted['success'] == false) {
                    $return['success'] = false;
                    $return['details'] = 'Service name must be unique';
                    $return['message'] = 'SERVICE_WITH_SAME_NAME_ALREADY_LAUNCHED_TEXT';
                    $return['services'] = $checkExisted['services'];
                    goto end_of_function;
                }

                foreach ($services as $serviceItem) {
                    $serviceCompany = ServiceCompany::findFirstById($serviceItem['service_company_id']);
                    $participant_id = isset($serviceItem['participant_id']) ? $serviceItem['participant_id'] : null;
                    $newUuid = $serviceItem['uuid'];
                    $description = $serviceItem['description'];

                    if ($serviceCompany && $serviceCompany->belongsToGms()) {
                        if (Helpers::__isValidId($participant_id)) {

                            $dependant = Dependant::findFirstById($participant_id);

                            if ($dependant && $dependant->belongsToGms() && $relocation->hasDependant($participant_id)) {
                                $return = RelocationServiceCompany::__createNew($newUuid, $relocation, $serviceCompany, $description, $participant_id);
                            } else if ($employee->getId() == $participant_id) {
                                $return = RelocationServiceCompany::__createNew($newUuid, $relocation, $serviceCompany, $description, null);
                            } else {
                                $return = ['success' => false, 'message' => 'DEPENDANT_NOT_FOUND_TEXT'];
                                $this->db->rollback();
                                goto end_of_function;
                            }
                        } else {
                            $return = RelocationServiceCompany::__createNew($newUuid, $relocation, $serviceCompany, $description, null);
                        }

                        if ($return['success'] == true) {

                            $relocationServiceCompany = $return['data'];
                            $reporterProfileDefault = ModuleModel::$user_profile;
                            //addReporter
                            if ($reporterProfileDefault) {
                                $resultAddReporter = $relocationServiceCompany->addReporter($reporterProfileDefault);
                                if ($resultAddReporter['success'] = false) {
                                    $this->db->rollback();
                                    $return['success'] = false;
                                    $return['details'] = 'canNotAddReporter';
                                    $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }

                            $relocationServiceCompany->startAddAttachment();
                            $relocationServiceCompany->startAddWorkflow();
                            $relocationServiceCompany->startPrefillData();

                            $relocationServiceCompanyItems[] = $relocationServiceCompany;

                        } else {
                            $this->db->rollback();
                            if ($return['detail'] == 'NAME_MUST_BE_UNIQUE_TEXT') {
                                $return['success'] = false;
                                $return['details'] = 'Service name must be unique';
                                $return['message'] = 'SERVICE_WITH_SAME_NAME_ALREADY_LAUNCHED_TEXT';
                            } else {
                                $return['success'] = false;
                                $return['details'] = 'Can not create new Service';
                                $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                            }
                            goto end_of_function;
                        }
                    }
                }
                $this->db->commit();
                $return = ['success' => true, 'message' => 'ADD_SERVICE_SUCCESS_TEXT', 'data' => $relocationServiceCompanyItems];
            } else {
                goto end_of_function;
            }
        }

        end_of_function:

        if ($return['success'] == true && isset($relocationServiceCompanyItems) && count($relocationServiceCompanyItems) > 0) {
            $return['$apiResults'] = [];
            ModuleModel::$relocationServiceCompanies = $relocationServiceCompanyItems;
            ModuleModel::$relocation = $relocation;
            $this->dispatcher->setParam('return', $return);

            foreach ($relocationServiceCompanyItems as $relocationServiceCompanyItem){
                $return['$apiResults'] = NotificationServiceHelper::__addNotification(['uuid' => $relocationServiceCompanyItem->getUuid()], HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_CREATE);
            }

//            ModuleModel::$relocationServiceCompanies = $relocationServiceCompanyItems;
//           $this->dispatcher->setParam('return', $return);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param string $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getParticipantsAction($uuid = '')
    {
        $this->checkAjax('GET');
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $assignment = $relocation->getAssignment();
                $dependants = $assignment->getDependants();
                $dependant_array = [];
                $employee = $assignment->getEmployee()->toArray();
                $employee['relation'] = 'EMPLOYEE_TEXT';
                $dependant_array[] = $employee;
                if (count($dependants) > 0) {
                    foreach ($dependants as $dependant) {
                        if ($dependant->getStatus() == Dependant::STATUS_ACTIVE) {
                            $item = $dependant->toArray();
                            $relation = $dependant->getRelation() == Dependant::DEPENDANT_SPOUSE ? 'SPOUSE_TEXT' :
                                ($dependant->getRelation() == Dependant::DEPENDANT_CHILD ? 'CHILD_TEXT' :
                                    ($dependant->getRelation() == Dependant::DEPENDANT_COMMON_LAW_PARTNER ? 'PARTNER_TEXT' : 'OTHER_TEXT'));
                            $item['relation'] = $relation;
                            $dependant_array[] = $item;
                        }
                    }
                }
                $result = [
                    'success' => true,
                    'data' => $dependant_array,
                ];
            } else {
                $result = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
            }
        } else {

        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $relocation_uuid
     * @return mixed
     */
    public function getAllFormTemplatesAction(string $relocation_uuid = null)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::__findFirstByUuidCache($relocation_uuid, CacheHelper::__TIME_24H);
            if ($relocation && $relocation->checkCompany()) {
                $options = [];
                $options['query'] = Helpers::__getRequestValue('query');
                $options['status'] = DocumentTemplate::STATUS_ACTIVE;
                $options['limit'] = 1000;
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => "name",
                    "order" => 'asc'
                ];
                $result = DocumentTemplate::__findWithFilter($options, $ordersConfig);
                $this->response->setJsonContent($result);
                return $this->response->send();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function getReportsAction($relocation_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('report');

        $res = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!$relocation_uuid) {
            goto end_of_function;
        }

        $relocation = Relocation::findFirstByUuid($relocation_uuid);
        if (!$relocation || !$relocation->belongsToGms()) {
            goto end_of_function;
        }

        $listEx = Report::listNeedRemove($relocation_uuid, ReportExt::TYPE_DSP_EXPORT_RELOCATION);

        foreach ($listEx as $reportEx) {
            if ($reportEx instanceof Report) {
                $res = $reportEx->__quickRemove();
            }
        }

        $result = Report::loadList([
            'object_uuid' => $relocation_uuid,
            'type' => ReportExt::TYPE_DSP_EXPORT_RELOCATION
        ]);

        if ($result['success']) {
            $res = [
                'success' => true,
                'message' => '',
                'data' => $result['data'],
            ];
        } else {
            $res = $result;
        }

        end_of_function:
        $this->response->setJsonContent($res);
        $this->response->send();
    }

    public function getReportDetailAction($reportID)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('report');

        $res = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];

        $report = Report::findFirstById($reportID);

        if ($report) {
            $res = [
                'success' => true,
                'message' => 'REPORT_FOUND_TEXT',
                'data' => $report
            ];
        }

        end_of_function:
        $this->response->setJsonContent($res);
        $this->response->send();
    }

    public function exportReportAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('report');

        $res = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        $this->db->begin();

        $relocationUuid = Helpers::__getRequestValue("object_uuid");
        $params = Helpers::__getRequestValueAsArray("params");

        if (!$relocationUuid || !Helpers::__isValidUuid($relocationUuid)) {
            $this->db->rollback();
            goto end_of_function;
        }

        $relocation = Relocation::findFirstByUuid($relocationUuid);
        if (!$relocation || !$relocation->belongsToGms()) {
            $this->db->rollback();
            goto end_of_function;
        }

        $canExportReport = Report::canExportReport($relocationUuid, ReportExt::TYPE_DSP_EXPORT_RELOCATION);
        if (!$canExportReport) {
            $res = ['success' => false, 'message' => 'REPORT_QUEUE_LIMIT_EXCEED_PLEASE_WAIT_TEXT'];
            $this->db->rollback();
            goto end_of_function;
        }

        $now = time();
        $companyUuid = ModuleModel::$company->getUuid();
        $random = new Random();
        $report = new Report();
        $report->setData();
        $report->setCompanyUuid($companyUuid);
        $report->setName($relocation->getName() . $now . '.xlsx');
        $report->setObjectUuid($relocationUuid);
        $report->setCreatorUuid(ModuleModel::$user_profile->getUuid());
        $report->setStatus(ReportExt::STATUS_IN_PROCESS);
        $report->setExpiredAt(date('Y-m-d H:i:s', $now + ReportExt::EXPIRED_TIME));
        $report->setParams(json_encode($params));
        $report->setType(ReportExt::TYPE_DSP_EXPORT_RELOCATION);

        $result = $report->__quickCreate();

        if ($result['success']) {
            $dataArray = $relocation->toArray();
            $viewers = DataUserMember::__getDataViewers($relocationUuid);
            $company = $relocation->getCompany();
            $dataArray['number'] = $relocation->getNumber();
            $dataArray['employee'] = $relocation->getEmployee()->toArray();
            $dataArray['employee_name'] = $relocation->getEmployee()->getFullname();
            $dataArray['company'] = $company->toArray();
            $dataArray['company_id'] = $company->getId();
            $dataArray['service_pack_name'] = $relocation->getServicePack() ? $relocation->getServicePack()->getName() : '';
            $dataArray['service_count'] = $relocation->countRelocationServiceCompanies();
            $dataArray['is_cancelled'] = $relocation->isCancelled();
            $dataArray['owner_name'] = $relocation->getOwner() ? $relocation->getOwner()->getFullname() : '';
            $dataArray['owner_uuid'] = $relocation->getOwner() ? $relocation->getOwner()->getUuid() : '';
            $reporter = DataUserMember::__getDataReporter($relocationUuid, ModuleModel::$company->getId());
            $dataArray['reporter_name'] = $reporter ? $reporter->getFullname() : '';

            $assignment = $relocation->getAssignment();
            if ($assignment) {
                $assignmentArr = $assignment->getInfoDetailInArray();
                $dataArray['assignment_uuid'] = $relocation->getAssignment() ? $relocation->getAssignment()->getUuid() : '';
                $dataArray['assignment_name'] = $relocation->getAssignment() ? $relocation->getAssignment()->getName() : '';
                $dataArray['employee'] = $assignmentArr['employee'];
                $dataArray['basic'] = $assignmentArr['basic'];
                $dataArray['destination'] = $assignmentArr['destination'];
                $dataArray['booker_company_name'] = $assignmentArr['booker_company_name'];
                $dataArray['order_number'] = $assignmentArr['order_number'];

                $dependants = $assignment->getDependants()->toArray();

                if (isset($dataArray['employee']['uuid'])) {
                    $commentItem = Comment::findFirst([
                        'conditions' => 'object_uuid = :object_uuid: and company_uuid = :company_uuid:',
                        'bind' => [
                            'object_uuid' => $dataArray['employee']['uuid'],
                            'company_uuid' => ModuleModel::$company->getUuid(),
                        ],
                        'order' => 'created_at DESC'
                    ]);
                    if ($commentItem instanceof Comment) {
                        $dataArray['employee']['notes'] = $commentItem->getMessage();
                    }
                }

                if (is_array($dataArray['employee']['spoken_languages']) && count($dataArray['employee']['spoken_languages']) > 0) {
                    foreach ($dataArray['employee']['spoken_languages'] as $key => $spoken_language) {
                        $dataArray['employee']['spoken_languages'][$key] = LanguageCode::getLanguageFromCode($spoken_language);
                    }
                }

                if (is_array($dataArray['employee']['citizenships']) && count($dataArray['employee']['citizenships']) > 0) {
                    foreach ($dataArray['employee']['citizenships'] as $key => $citizenship) {
                        $nationality = NationalityExt::findFirstByCode($citizenship);
                        $dataArray['employee']['citizenships'][$key] = $nationality->getTranslationByLanguage()->getValue();
                    }
                }

                foreach ($dependants as $dependant) {
                    $dataArray['employee']['dependants'][] = $dependant['firstname'] . ' ' . $dependant['lastname'];
                }
            }
            if ($dataArray['employee']['marital_status']) {
                $marital_status = Attributes::findFirst(["code='" . Attributes::MARITAL_STATUS . "'"]);
                if ($marital_status instanceof Attributes) {
                    $list = $marital_status->getListValuesOfCompany(ModuleModel::$company->getId(), ModuleModel::$language);

                    if (count($list)) {
                        foreach ($list as $item) {
                            if ($item['code'] == $dataArray['employee']['marital_status']) {
                                $dataArray['employee']['marital_status'] = $item['value'];
                                break;
                            }
                        }
                    }
                }
            }

            $dataArray['viewers'] = [];
            foreach ($viewers as $key => $viewer) {
                $dataArray['viewers'][$key] = $viewer->getFirstname() . ' ' . $viewer->getLastname();
            }

            $params['list_object_uuid'][] = $relocationUuid;
            $dataArray['services'] = [];
            if (isset($params['service_ids']) && count($params['service_ids']) > 0) {
                $services = $relocation->getActiveRelocationServiceCompany();
                $orderSetting = [];
                $servicesUuid = [];
                foreach ($services as $serviceRelocationItem) {
                    $orderSetting[intval($serviceRelocationItem->getId())] = $serviceRelocationItem->getName();
                    $servicesUuid[intval($serviceRelocationItem->getId())] = $serviceRelocationItem->getUuid();
                }

                foreach ($params['service_ids'] as $serviceId) {
                    if (isset($orderSetting[intval($serviceId)])) {
                        $params['list_object_uuid'][] = $servicesUuid[intval($serviceId)];
                        $dataArray['services'][] = $orderSetting[intval($serviceId)];
                    }
                }
            }

            $commentsArr = [];
            if (isset($params['comment_ids']) && count($params['comment_ids']) > 0) {
                $comments = Comment::find([
                    'conditions' => 'object_uuid = :object_uuid: and id IN ({commentIds:array})',
                    'bind' => [
                        'object_uuid' => $relocationUuid,
                        'commentIds' => $params['comment_ids']
                    ]
                ]);
                if (count($comments) > 0) {
                    foreach ($comments as $key => $comment) {
                        $commentArr = $comment->toArray();
                        $userProfile = $comment->getUserProfile();
                        $commentArr['creator_comment'] = $userProfile->getFirstname() . ' ' . $userProfile->getLastname();
                        $commentArr['message'] = strip_tags($commentArr['message']);
                        $commentsArr[] = $commentArr;
                    }
                }
            }

            $tasks = [];

            $exportData = [
                'params' => [
                    'data' => $dataArray,
                    'comments' => $commentsArr,
                    'tasks' => $tasks,
                    'condition' => $params,
                    'report' => $result['data']->toArray(),
                    'company_uuid' => ModuleModel::$company->getUuid(),
                ],
                'language' => ModuleModel::$language,
                'action' => RelodayQueue::ACTION_EXPORT_REPORT_RELOCATION
            ];

            $beanQueue = RelodayQueue::__getQueueExportReport();

            $beanQueueRes = $beanQueue->addQueue($exportData);

            if (!$beanQueueRes['success']) {
                $this->db->rollback();

                $res = ['success' => false, 'message' => 'DATA_CREATE_FAIL_TEXT'];
                goto end_of_function;
            }

            $this->db->commit();
            $res = [
                'success' => true,
                'message' => 'REPORT_GENERATION_IN_PROGRESS_TEXT',
                'data' => $result['data'],
            ];
        } else {
            $res = $result;
            $this->db->rollback();
        }

        end_of_function:
        $this->response->setJsonContent($res);
        $this->response->send();
    }

    public function deleteReportAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('report');

        $res = ['success' => false, 'message' => 'REPORT_NOT_FOUND_TEXT'];

        $reportUuid = Helpers::__getRequestValue("uuid");

        if ($reportUuid) {
            $report = Report::findFirstByUuid($reportUuid);

            if ($report instanceof Report) {
                $result = $report->__quickRemove();

                if (!$result['success']) {
                    $res = ['success' => false, 'message' => 'CAN_NOT_DELETE_TEXT'];
                    goto end_of_function;
                } else {
                    $res = ['success' => true, 'message' => 'REPORT_DELETE_SUCCESS_TEXT'];
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($res);
        $this->response->send();
    }


    /**
     * @param $historyObjectData
     * @param $objectUuids
     * @return void
     */
    private function __addHistory($historyObjectData = [], $objectUuids = [], $time = 0)
    {
        sleep(1);
        if ($time == 0) {
            $time = time();
        }
        if (count($objectUuids) > 0) {
            foreach ($objectUuids as $objectUuid) {
                $historyObject = new History();
                $historyObject->setData($historyObjectData);
                $historyObject->setIp($this->request->getClientAddress());
                $historyObject->setCompanyUuid(ModuleModel::$company->getUuid());
                $historyObject->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
                $historyObject->setUuid(Helpers::__uuid());
                $historyObject->setObjectUuid($objectUuid);
                $historyObject->setUpdatedAt($time);
                $historyObject->setCreatedAt($time);

                $addHistoryObj = $historyObject->__quickCreate();

            }
        }


    }



    /**
     * Load list event by service id
     * @param int $service_id
     */
    public function getEventsOfRelocationAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $events = Event::find([
            "conditions" => "object_source = :object_source: and is_reminder_available = :yes:",
            "bind" => [
                "object_source" => "relocation",
                "yes" => Helpers::YES
            ]
        ]);
        $event_array = [];
        foreach ($events as $event) {
            $event_array[] = $event;
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $event_array
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getRecentRelocationsAction(){
        $this->view->disable();
        $this->checkAjaxPost();

        $limit = Helpers::__getRequestValue('limit');

        $return = Relocation::__getRecentRelocations([
            'limit' => $limit ?: 5,
        ]);


        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
