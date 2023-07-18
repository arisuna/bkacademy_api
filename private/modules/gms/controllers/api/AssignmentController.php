<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Prophecy\Exception\Call\UnexpectedCallException;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\BackgroundActionHelpers;
use Reloday\Application\Lib\EmployeeActivityHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\LanguageCode;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayDynamoORMException;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Application\Lib\TextHelper;
use Reloday\Application\Models\AssignmentCompanyDataExt;
use Reloday\Application\Models\AssignmentExt;
use Reloday\Application\Models\NationalityExt;
use Reloday\Application\Models\ReportExt;
use Reloday\Application\Models\TaskTemplateChecklist;
use Reloday\Application\Models\AssignmentGuideExt;
use Reloday\Application\Models\GuideExt;
use Reloday\Application\Models\UserProfileExt;
use Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Controllers\API\BaseController;

use Reloday\Gms\Help\HistoryHelper;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\AssignmentBasic;
use Reloday\Gms\Models\AssignmentCompanyData;
use Reloday\Gms\Models\AssignmentDestination;
use Reloday\Gms\Models\AssignmentRequest;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\Comment;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\FilterConfig;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HistoryOld;
use Reloday\Gms\Models\HistoryAction;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectFolder;
use Reloday\Gms\Models\ObjectMap;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\Event;
use Reloday\Gms\Models\EventValue;
use Phalcon\Http\Request;

use Phalcon\Security\Random;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Report;
use Reloday\Gms\Models\TaskChecklist;
use Reloday\Gms\Models\TaskFile;
use Reloday\Gms\Models\TaskTemplate;
use Reloday\Gms\Models\TaskWorkflow;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ReminderConfig;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\Workflow;
use Reloday\Gms\Module;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AssignmentController extends BaseController
{
    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index');

        $result = Assignment::loadList();
        if ($result['success'] == true) {
            $this->response->setJsonContent([
                'success' => true,
                'message' => '',
                'data' => $result['data'],
                'raw' => 12,
                'recordsFiltered' => 100,
                'recordsTotal' => 100,
            ]);
            $this->response->send();
        } else {
            $this->response->setJsonContent($result);
            $this->response->send();
        }

    }

    /**
     * [companyAction description]
     * @param string $company_id [description]
     * @return [type]             [description]
     */
    public function companyAction($company_id = '')
    {

        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $result = Assignment::loadList(null, " company_id = " . $company_id);
        if ($result['success'] == true) {
            $this->response->setJsonContent([
                'success' => true,
                'message' => '',
                'data' => $result['data'],
            ]);
            $this->response->send();
        } else {
            $this->response->setJsonContent($result);
            $this->response->send();
        }
    }


    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];


        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $assignmentManager = Assignment::findFirstByUuid($uuid);
            ModuleModel::$oldAssignmentEndDate = $assignmentManager->getEndDate();
            ModuleModel::$oldAssignmentStartDate = $assignmentManager->getEffectiveStartDate();
            if ($assignmentManager && $assignmentManager->belongsToGms()) {

                $this->checkPermissionEditAssignment($assignmentManager);

                $assignmentData = Helpers::__getRequestValuesArray();
                $customDataBasic = Helpers::__getRequestValueAsArray('basic');
                $customDataDestination = Helpers::__getRequestValueAsArray('destination');

                unset($assignmentData['basic']);
                unset($assignmentData['destination']);
                unset($assignmentData['home_country']);
                unset($assignmentData['destination_country']);
                unset($assignmentData['employee']);
                unset($assignmentData['company']);
                unset($assignmentData['dependants']);
                unset($assignmentData['ownerDspProfile']);


                if (isset($customDataBasic['home_city'])) {
                    $assignmentData['home_city'] = $customDataBasic['home_city'];
                }
                if (isset($customDataBasic['home_country_id'])) {
                    $assignmentData['home_country_id'] = $customDataBasic['home_country_id'];
                }
                if (isset($customDataBasic['home_city_geonameid'])) {
                    $assignmentData['home_city_geonameid'] = $customDataBasic['home_city_geonameid'];
                }


                if (isset($customDataDestination['destination_city'])) {
                    $assignmentData['destination_city'] = $customDataDestination['destination_city'];
                }
                if (isset($customDataDestination['destination_country_id'])) {
                    $assignmentData['destination_country_id'] = $customDataDestination['destination_country_id'];
                }
                if (isset($customDataDestination['destination_city_geonameid'])) {
                    $assignmentData['destination_city_geonameid'] = $customDataDestination['destination_city_geonameid'];
                }


                $this->db->begin();
                $companyData = Helpers::__getRequestValueAsArray('data');
                $customFields = AssignmentCompanyData::$fields;
                foreach ($customFields as $customField) {
                    if (Helpers::__existCustomValue($customField, $companyData)) {

                        $resultUpdate = AssignmentCompanyData::__addNew($assignmentManager, $customField, Helpers::__getCustomValue($customField, $companyData));
                        if ($resultUpdate['success'] == false) {
                            $return = [
                                'success' => false,
                                '$customField' => $customField,
                                'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT',
                                'type' => 'assignmentCompanyData',
                                'errorMessage' => $resultUpdate['errorMessage']
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }
                }


                $resultUpdate = $assignmentManager->updateSingleOne($assignmentData);
                if ($resultUpdate['success'] == false) {
                    $return = [
                        'success' => false,
                        'errorType' => 'assignmentError',
                        'errorDetail' => $resultUpdate,
                        'errorMethod' => 'updateSingleOne',
                        'message' => $resultUpdate['message'],
                        'data' => $assignmentData
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                $assignmentDestination = $assignmentManager->getAssignmentDestination();
                if (!$assignmentDestination) {
                    $assignmentDestination = new AssignmentDestination();
                    $resultUpdate = $assignmentDestination->createSingleOne($customDataDestination, $assignmentManager);
                } else {
                    $resultUpdate = $assignmentDestination->updateSingleOne($customDataDestination, $assignmentManager);
                }

                if ($resultUpdate['success'] == false) {
                    $return = [
                        'success' => true,
                        'errorType' => 'assignmentDestinationError',
                        'message' => 'ASSIGNMENT_SAVE_FAIL_TEXT',
                        'data' => $assignmentDestination
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                $assignmentBasic = $assignmentManager->getAssignmentBasic();
                if (!$assignmentBasic) {
                    $assignmentBasic = new AssignmentBasic();
                    $resultUpdate = $assignmentBasic->createSingleOne($customDataBasic, $assignmentManager);
                } else {
                    $resultUpdate = $assignmentBasic->updateSingleOne($customDataBasic, $assignmentManager);
                }

                if ($resultUpdate['success'] == false) {
                    $return = [
                        'success' => true,
                        'errorType' => 'assignmentBasicError',
                        'message' => 'ASSIGNMENT_SAVE_FAIL_TEXT',
                        'data' => $assignmentBasic
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                if ($assignmentDestination->getDestinationCountryId() != $assignmentManager->getDestinationCountryId()) {
                    $assignmentManager->setDestinationCountryId($assignmentDestination->getDestinationCountryId());
                }
                if ($assignmentDestination->getDestinationCity() != $assignmentManager->getDestinationCity()) {
                    $assignmentManager->setDestinationCity($assignmentDestination->getDestinationCity());
                }
                if ($assignmentDestination->getDestinationCityGeonameid() != $assignmentManager->getDestinationCityGeonameid()) {
                    $assignmentManager->setDestinationCityGeonameid($assignmentDestination->getDestinationCityGeonameid());
                }

                if ($assignmentBasic->getHomeCountryId() != $assignmentManager->getHomeCountryId()) {
                    $assignmentManager->setHomeCountryId($assignmentBasic->getHomeCountryId());
                }
                if ($assignmentBasic->getHomeCity() != $assignmentManager->getHomeCity()) {
                    $assignmentManager->setHomeCity($assignmentBasic->getHomeCity());
                }
                if ($assignmentBasic->getHomeCityGeonameid() != $assignmentManager->getHomeCityGeonameid()) {
                    $assignmentManager->setHomeCityGeonameid($assignmentBasic->getHomeCityGeonameid());
                }

                $resultUpdate = $assignmentManager->__quickUpdate();
                if ($resultUpdate['success'] == false) {
                    $return = [
                        'success' => true,
                        'errorType' => 'assignmentUpdateError',
                        'message' => 'ASSIGNMENT_SAVE_FAIL_TEXT',
                        'data' => $assignmentManager
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }


//                if ($assignmentDestination && $assignmentDestination->getHrOwnerProfile()) {
//                    $assignmentManager->addOwner($assignmentManager->getAssignmentDestination()->getHrOwnerProfile());
//                }


                $return = [
                    'success' => true,
                    '$companyData' => $companyData,
                    'message' => 'ASSIGNMENT_SAVE_SUCCESS_TEXT',
                    'data' => $assignmentManager->getInfoDetailInArray()
                ];

                $this->db->commit();
            }
        }


        end_of_function:

        if ($return['success'] == true && isset($assignmentManager)) {
            ModuleModel::$assignment = $assignmentManager;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignmentManager, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_UPDATE);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Phalcon\Security\Exception
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $companyId = Helpers::__getRequestValue('company_id');
        $hrCompany = Company::findFirstById($companyId);
        if (!$hrCompany || !$hrCompany->belongsToGms()) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        $this->checkPermissionCreateAssignment($hrCompany);

        $assignment = new Assignment();
        $data = Helpers::__getRequestValuesArray();
        $uuid = Helpers::__getRequestValue('uuid');
        $assignmentObject = RelodayObjectMapHelper::__getObject($uuid);
        if ($assignmentObject && $assignmentObject->isAssignment()) {
            $assignment->setUuid($uuid);
        } else {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $attachments = Helpers::__getRequestValueAsArray('attachments');
        $employeeId = Helpers::__getRequestValueAsArray('employee_id');
        $destinationData = Helpers::__getRequestValueAsArray('destination');
        $basicData = Helpers::__getRequestValueAsArray('basic');
        $companyData = Helpers::__getRequestValueAsArray('data');

        $uuid = Helpers::__getRequestValue('uuid');
        unset($data['destination']);
        unset($data['basic']);

        $employee = Employee::findFirstById($employeeId);
        if (!$employee || $employee->belongsToGms() == false) {
            $return = ['success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $currentActiveContract = ModuleModel::$company->getActiveContract($employee->getCompanyId());
        if (!$currentActiveContract) {
            $return = ['success' => false, 'message' => 'CONTRACT_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $data['home_city'] = Helpers::__getRequestValue('home_city');
        $data['home_country_id'] = Helpers::__getRequestValue('home_country_id');
        $data['home_city_geonameid'] = Helpers::__getRequestValue('home_city_geonameid');

        $data['destination_city'] = Helpers::__getRequestValue('destination_city');
        $data['destination_country_id'] = Helpers::__getRequestValue('destination_country_id');
        $data['destination_city_geonameid'] = Helpers::__getRequestValue('destination_city_geonameid');

        $resultCheck = $assignment->setData($data);

        if ($resultCheck['success'] == false) {
            $return = ['success' => false, 'message' => $resultCheck['message'], 'type' => 'checkData'];
            goto end_of_function;
        }
        /** set assignment company id */
        $assignment->setCompanyId($employee->getCompanyId());

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $objectData = RelodayObjectMapHelper::__getAssignment($uuid);
            if ($objectData && $objectData->isCreated() == false) {
                $assignment->setUuid($objectData->getUuid());
            }
        }
        $reference = $assignment->generateNumber();
        $assignment->setReference($reference);
        $assignment->setName($reference);
        $assignment->setApprovalStatus(AssignmentExt::APPROVAL_STATUS_APPROVED); //auto approved assignment create from dsp

        $this->db->begin();
        $assignmentModelResult = $assignment->__quickCreate();
        if ($assignmentModelResult['success'] == false) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'detail' => isset($assignmentModelResult['detail']) ? $assignmentModelResult['detail'] : null];
            $this->db->rollback();
            goto end_of_function;
        }

        $customFields = AssignmentCompanyDataExt::$fields;
        foreach ($customFields as $customField) {
            if (Helpers::__existCustomValue($customField, $companyData)) {
                $resultUpdate = AssignmentCompanyData::__addNew($assignment, $customField, Helpers::__getCustomValue($customField, $companyData));
                if ($resultUpdate['success'] == false) {
                    $return = ['success' => false, '$customField' => $customField, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'type' => 'assignmentCompanyData', 'errorMessage' => $resultUpdate['errorMessage']];
                    $this->db->rollback();
                    goto end_of_function;
                }
            }
        }

        $assignmentBasic = new AssignmentBasic();
        $assignmentBasic->setId($assignment->getId());
        $assignmentBasic->setData($basicData);

        $assignmentBasic->setHomeCountryId($assignment->getHomeCountryId());
        $assignmentBasic->setHomeCity($assignment->getHomeCity());
        $assignmentBasic->setHomeCityGeonameid($assignment->getHomeCityGeonameid());

        $assignmentModelResult = $assignmentBasic->__quickCreate();
        if ($assignmentModelResult['success'] == false) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'errorMessage' => $assignmentModelResult['errorMessage'], 'type' => 'assignmentBasic', 'data' => $assignment];
            $this->db->rollback();
            goto end_of_function;
        }


        $assignmentDestination = new AssignmentDestination();
        $assignmentDestination->setId($assignment->getId());
        $assignmentDestination->setData($destinationData);

        $assignmentDestination->setDestinationCountryId($assignment->getDestinationCountryId());
        $assignmentDestination->setDestinationCity($assignment->getDestinationCity());
        $assignmentDestination->setDestinationCityGeonameid($assignment->getDestinationCityGeonameid());


        $assignmentModelResult = $assignmentDestination->__quickCreate();
        if ($assignmentModelResult['success'] == false) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'errorMessage' => $assignmentModelResult['errorMessage'], 'type' => 'assignmentDestination'];
            $this->db->rollback();
            goto end_of_function;
        }

        $resultAddCreator = $assignment->addCreator(ModuleModel::$user_profile);
        if ($resultAddCreator['success'] == false) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'detail' => $resultAddCreator['detail'], 'type' => 'addCreator'];
            $this->db->rollback();
            goto end_of_function;
        }
        $resultAddReporter = $assignment->addReporter(ModuleModel::$user_profile);
        if ($resultAddReporter['success'] == false) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'detail' => $resultAddReporter['detail'], 'type' => 'addReporter'];
            $this->db->rollback();
            goto end_of_function;
        }

        $ownerProfile = $assignment->getGmsOwner();
        if (!$ownerProfile) {
            $gms_assignment_owner_id = Helpers::__getRequestValue('gms_assignment_owner_id');
            $gmsOwnerProfile = ModuleModel::$user_profile;
            if ($gms_assignment_owner_id && $gms_assignment_owner_id > 0) {
                $gmsOwnerProfile = UserProfile::findFirstByIdCache($gms_assignment_owner_id);
                if ($gmsOwnerProfile && $gmsOwnerProfile->isGms() && $gmsOwnerProfile->belongsToGms()) {
                    // nothing
                } else {
                    $gmsOwnerProfile = ModuleModel::$user_profile;
                }
            }
            $resultAddDspOwner = $assignment->addOwner($gmsOwnerProfile);
            if ($resultAddDspOwner['success'] == false) {
                $return = [
                    'success' => false,
                    'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT',
                    'detail' => $resultAddDspOwner['detail'],
                    'type' => 'addOwner',
                    'ownerProfile' => $gmsOwnerProfile
                ];
                $this->db->rollback();
                goto end_of_function;
            }
            $return['$resultAddDspOwner'] = isset($resultAddDspOwner) ? $resultAddDspOwner : null;
        }


        /// add HR Owner
        $hr_assignment_owner_id = Helpers::__getRequestValue('hr_assignment_owner_id');
        if ($hr_assignment_owner_id && $hr_assignment_owner_id > 0) {
            $hrOwnerProfile = UserProfile::findFirstByIdCache($hr_assignment_owner_id);
            if ($hrOwnerProfile && $hrOwnerProfile->isHr() && $hrOwnerProfile->controlByGms() && $hrOwnerProfile->getCompanyId() == $assignment->getCompanyId()) {
                $resultAddHrOwner = $assignment->addHrOwner($hrOwnerProfile);
                if ($resultAddHrOwner['success'] == false) {
                    $return = ['success' => false, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'detail' => $resultAddHrOwner['detail'], 'type' => 'addHrOwner'];
                    $this->db->rollback();
                    goto end_of_function;
                }
            }
        }

        $return['$resultAddHrOwner'] = isset($resultAddHrOwner) ? $resultAddHrOwner : null;

        $resultAddDependant = $assignment->confirmAddDependants();

        if ($resultAddDependant['success'] == false) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT', 'detail' => $resultAddDependant['detail'], 'type' => 'addDependant'];
            $this->db->rollback();
            goto end_of_function;
        }

        $resultAddContract = $assignment->addToContract($currentActiveContract);
        if ($resultAddContract['success'] == false) {
            $return = [
                'success' => false,
                'message' => 'REQUEST_SENT_FAIL_TEXT',
            ];
            $this->db->rollback();
            goto end_of_function;
        }

        $autoInitiate = $assignment->createAutoInitiationRequest();
        if ($autoInitiate['success'] == false) {
            $return = [
                'success' => false,
                'message' => 'REQUEST_SENT_FAIL_TEXT',
            ];
            $this->db->rollback();
            goto end_of_function;
        }
        $random = new Random();

        // create object folder for hr
        $object_folder_hr = new ObjectFolder();
        $object_folder_hr->setUuid($random->uuid());
        $object_folder_hr->setObjectUuid($assignment->getUuid());
        $object_folder_hr->setObjectName($assignment->getSource());
        $object_folder_hr->setAssignmentId($assignment->getId());
        $object_folder_hr->setHrCompanyId($employee->getCompanyId());
        $result = $object_folder_hr->__quickCreate();
        if (!$result['success']) {
            $return = [
                'success' => false,
                'message' => 'REQUEST_SENT_FAIL_TEXT',
                'detail' => $result
            ];
            $this->db->rollback();
            goto end_of_function;
        }

        // create object folder for dsp
        $object_folder_dsp = $assignment->getMyDspFolder();
        if (!$object_folder_dsp) {
            $object_folder_dsp = new ObjectFolder();
            $object_folder_dsp->setUuid($random->uuid());
            $object_folder_dsp->setObjectUuid($assignment->getUuid());
            $object_folder_dsp->setObjectName($assignment->getSource());
            $object_folder_dsp->setAssignmentId($assignment->getId());
            $object_folder_dsp->setDspCompanyId(ModuleModel::$company->getId());
            $result = $object_folder_dsp->__quickCreate();
            if (!$result['success']) {
                $return = [
                    'success' => false,
                    'message' => 'REQUEST_SENT_FAIL_TEXT',
                    'detail' => $object_folder_dsp
                ];
                $this->db->rollback();
                goto end_of_function;
            }
        }

        // create object folder for dsp and hr
        $object_folder_dsp_hr = new ObjectFolder();
        $object_folder_dsp_hr->setUuid($random->uuid());
        $object_folder_dsp_hr->setObjectUuid($assignment->getUuid());
        $object_folder_dsp_hr->setObjectName($assignment->getSource());
        $object_folder_dsp_hr->setAssignmentId($assignment->getId());
        $object_folder_dsp_hr->setDspCompanyId(ModuleModel::$company->getId());
        $object_folder_dsp_hr->setHrCompanyId($employee->getCompanyId());
        $result = $object_folder_dsp_hr->__quickCreate();
        if (!$result['success']) {
            $return = [
                'success' => false,
                'message' => 'REQUEST_SENT_FAIL_TEXT',
                'detail' => $object_folder_dsp_hr
            ];
            $this->db->rollback();
            goto end_of_function;
        }

        // create object folder for employee and dsp
        $object_folder_dsp_ee = new ObjectFolder();
        $object_folder_dsp_ee->setUuid($random->uuid());
        $object_folder_dsp_ee->setObjectUuid($assignment->getUuid());
        $object_folder_dsp_ee->setObjectName($assignment->getSource());
        $object_folder_dsp_ee->setAssignmentId($assignment->getId());
        $object_folder_dsp_ee->setDspCompanyId(ModuleModel::$company->getId());
        $object_folder_dsp_ee->setEmployeeId($employee->getId());
        $result = $object_folder_dsp_ee->__quickCreate();
        if (!$result['success']) {
            $return = [
                'success' => false,
                'message' => 'REQUEST_SENT_FAIL_TEXT',
                'detail' => $object_folder_dsp_ee
            ];
            $this->db->rollback();
            goto end_of_function;
        }

        // create object folder for employee and hr
        $object_folder_hr_ee = new ObjectFolder();
        $object_folder_hr_ee->setUuid($random->uuid());
        $object_folder_hr_ee->setObjectUuid($assignment->getUuid());
        $object_folder_hr_ee->setObjectName($assignment->getSource());
        $object_folder_hr_ee->setAssignmentId($assignment->getId());
        $object_folder_hr_ee->setHrCompanyId($employee->getCompanyId());
        $object_folder_hr_ee->setEmployeeId($employee->getId());
        $result = $object_folder_hr_ee->__quickCreate();
        if (!$result['success']) {
            $return = [
                'success' => false,
                'message' => 'REQUEST_SENT_FAIL_TEXT',
                'detail' => $object_folder_hr_ee
            ];
            $this->db->rollback();
            goto end_of_function;
        }

        $resultAttachment = MediaAttachment::__createAttachments([
            'objectUuid' => $object_folder_dsp->getUuid(),
            'objectName' => $object_folder_dsp->getSource(),
            'isShared' => false,
            'fileList' => $attachments,
            'userProfile' => ModuleModel::$user_profile,
            'object_folder_hr' => $object_folder_hr,
            'object_folder_hr_ee' => $object_folder_hr_ee,
            'object_folder_dsp' => $object_folder_dsp,
            'object_folder_dsp_ee' => $object_folder_dsp_ee,
            'object_folder_dsp_hr' => $object_folder_dsp_hr
        ]);


        //Set active guide for HR

        $resultSuggestGuides = GuideExt::__suggestionHrGuidesFilters([
            'destination_country_id' => $assignmentDestination->getDestinationCountryId(),
            'destination_city' => $assignmentDestination->getDestinationCity(),
            'hr_company_id' => $assignment->getCompanyId()
        ]);

        $guides = [];
        if ($resultSuggestGuides['success']) {
            $guides = $resultSuggestGuides['data'];
        }

        if (count($guides) > 0) {
            foreach ($guides as $guide) {
                $resultAddGuide = AssignmentGuideExt::__addAssignmentGuide($assignment->getId(), $guide['id'], $assignment->getEmployeeId());
                if (!$resultAddGuide['success']) {
                    $return = [
                        'success' => false,
                        'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT',
                        'detail' => $resultAddGuide
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }
            }

            if ($assignment->countRelocations() == 0 && $assignment->countAssignmentRequests() == 0) {
                $assignment->setIsInitiated(\Reloday\Application\Lib\ModelHelper::NO);
            } else {
                $assignment->setIsInitiated(\Reloday\Application\Lib\ModelHelper::YES);
            }

            $assignment->setIsActivateGuide(ModelHelper::YES);
            $resultRelocation = $assignment->__quickSave();

            if (!$resultRelocation['success']) {
                $this->db->rollback();

                $return = [
                    'success' => false,
                    'message' => 'ASSIGNMENT_CREATE_FAIL_TEXT',
                    'detail' => $resultRelocation
                ];
                $this->db->rollback();
                goto end_of_function;
            }
        }

        $this->db->commit();
        $dataArray = $assignment->toArray();
        $dataArray['company_folder_uuid'] = $assignment->getCompanyFolderUuid();
        $dataArray['destination_country'] = $assignment->getDestinationCountry();
        $dataArray['home_country'] = $assignment->getHomeCountry();

        $return = [
            'success' => true,
            'message' => 'ASSIGNMENT_CREATE_SUCCESS_TEXT',
            'data' => $dataArray
        ];

        end_of_function:

        if ($return['success'] == true && isset($assignment)) {
            ModuleModel::$assignment = $assignment;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_CREATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('view');

        $return = [
            'success' => false,
            'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $assignment = Assignment::findFirstByUuid($uuid);

            if (!DataUserMember::checkViewPermissionOfUserByProfile($uuid, ModuleModel::$user_profile)) {
                $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'permissionNotFound' => true];
                goto end_of_function;
            }

            // get assignment detail to create request do not need to check belongtogms
            if ($assignment && $assignment->checkMyViewPermission()) {
                $dataArray = $assignment->getInfoDetailInArray();
                $dataArray['company_folder_uuid'] = $assignment->getCompanyFolderUuid();
                $dependants = $assignment->getDependants()->toArray();
                if (count($dependants)) {
                    foreach ($dependants as $key => $dependant) {
                        if ($assignment->checkIfDependantExist($dependant['id'])) {
                            $dependants[$key]['selected'] = true;
                        } else {
                            $dependants[$key]['selected'] = false;
                        }
                        $dependants[$key]['birth_country'] = '';
                        if ($dependant['birth_country_id'] > 0) {
                            $birth_country = Country::findFirstByIdCache($dependant['birth_country_id']);
                            if ($birth_country) {
                                $dependants[$key]['birth_country'] = $birth_country->getName();
                            }
                        }
                        if ($dependant['citizenships'] != '' && Helpers::__isJsonValid($dependant['citizenships'])) {
                            $dependants[$key]['citizenships'] = json_decode($dependant['citizenships'], true);
                        }
                        if ($dependant['spoken_languages'] != '' && Helpers::__isJsonValid($dependant['spoken_languages'])) {
                            $dependants[$key]['spoken_languages'] = json_decode($dependant['spoken_languages'], true);
                        }
                        $dependants[$key]['relation_label'] = Dependant::$relation_to_label[$dependant["relation"]]["label"];
                    }
                }

                $dataArray['dependants'] = $dependants;
                $dataArray['gmsFolderUuid'] = $assignment->getGmsFolderUuid();
                $dataArray['hrFolderUuid'] = $assignment->getHrFolderUuid();
                $dataArray['sharedHrGmsFolderUuid'] = $assignment->getSharedHrGmsFolderUuid(ModuleModel::$company->getUuid());
                $dataArray['employeeFolderUuid'] = $assignment->getEmployeeFolderUuid();
                $dataArray['is_editable'] = $assignment->isEditable();
                $dataArray['folders'] = $assignment->getFolders();
                $dataArray['owner_name'] = $assignment->getSimpleProfileOwner() ? $assignment->getSimpleProfileOwner()->getFullname() : '';
                $dataArray['owner_uuid'] = $assignment->getSimpleProfileOwner() ? $assignment->getSimpleProfileOwner()->getUuid() : '';
                $assignment_request = $assignment->getAssignmentRequest();

                if ($assignment_request && $assignment_request->getMessage() != null && $assignment_request->getMessage() != '') {
                    $dataArray['request'] = $assignment->getAssignmentRequest();
                }

                $dataArray['data'] = $assignment->getCompanyCustomData();

                $company = $assignment->getCompany();
                $countMessages = 0;
                if (isset($dataArray['request']) && $dataArray['request']->getUuid() && $company && $company->getUuid()) {
                    $countMessages = Comment::getCountInitiationMessages($dataArray['request']->getUuid(), $company->getUuid());
                }

                $dataArray['count_initiation_message'] = $countMessages;

                $return = [
                    'success' => true,
                    'message' => '',
                    'company' => $company,
                    'data' => $dataArray
                ];
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param int $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAction(int $id = 0)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclView();

        if (!Helpers::__isValidId($id)) {
            $id = Helpers::__getRequestValue('id');
        }

        if (Helpers::__isValidId($id)) {
            $assignment = Assignment::findFirstById($id);
            // get assignment detail to create request do not need to check belongtogms
            if ($assignment) {
                $dataArray = $assignment->getInfoDetailInArray();
                $dataArray['company_folder_uuid'] = $assignment->getCompanyFolderUuid();
                $dataArray['gmsFolderUuid'] = $assignment->getGmsFolderUuid();
                $dataArray['hrFolderUuid'] = $assignment->getHrFolderUuid();
                $dataArray['sharedHrGmsFolderUuid'] = $assignment->getSharedHrGmsFolderUuid(ModuleModel::$company->getUuid());
                $dataArray['employeeFolderUuid'] = $assignment->getEmployeeFolderUuid();
                $dataArray['dependants'] = $assignment->getDependants();
                $dataArray['data'] = $assignment->getDataItems();
                $ownerProfile = $assignment->getGmsOwner();
                if ($ownerProfile)
                    $dataArray['owner_id'] = $ownerProfile->getId();
                else
                    $dataArray['owner_id'] = null;

                $return = [
                    'success' => true,
                    'message' => '',
                    'data' => $dataArray,
                    'basic' => $assignment->getAssignmentBasic(),
                    'destination' => $assignment->getAssignmentDestination(),
                ];

            } else {
                $return = [
                    'success' => false,
                    'message' => 'ASSIGNMENT_NOT_FOUND_TEXT',
                    'data' => $assignment
                ];
            }
        } else {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
        }
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function getRelocationAction(string $assignment_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('view');

        $return = [
            'success' => false,
            'message' => 'RELOCATION_NOT_FOUND_TEXT',
        ];

        if ($assignment_uuid != '') {
            $assignment = Assignment::findFirstByUuid($assignment_uuid);
            // get assignment detail to create request do not need to check belongtogms
            if (!$assignment) {
                $return = [
                    'success' => false,
                    'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }

            if ($assignment) {
                $relocation = $assignment->getRelocation();

                if ($relocation && ($relocation->getActive() == Relocation::STATUS_ACTIVATED || $relocation->getActive() == Relocation::STATUS_ARCHIVED)) {
                    $relocationData = $relocation->toArray();
                    $relocationData['service_pack_name'] = $relocation->getServicePack() ? $relocation->getServicePack()->getName() : '';
                    $relocationData['service_count'] = $relocation->countRelocationServiceCompanies();

                    $return = [
                        'success' => true,
                        'message' => '',
                        'data' => $relocationData
                    ];
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function approveAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('change_status', $this->router->getControllerName());


        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $assignment = Assignment::findFirstByUuid($uuid);
            if ($assignment && $assignment->belongsToGms() && $assignment->checkMyViewPermission()) {
                $assignment->setApprovalStatus(Assignment::STATUS_APPROVED);
                $assignmentModelResult = $assignment->__quickUpdate();
                if ($assignmentModelResult instanceof Assignment) {
                    $return = [
                        'success' => true,
                        'message' => 'ASSIGNMENT_UPDATED_SUCCESS_TEXT',
                        'data' => [
                            'id' => $assignmentModelResult->getId(),
                            'reference' => $assignmentModelResult->getReference(),
                            'type_name' => ($assignmentModelResult->getAssignmentType()) ? $assignmentModelResult->getAssignmentType()->getName() : null,
                        ]
                    ];
                } else {
                    $return = $assignmentModelResult;
                }
            } else {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'data' => $assignment];
            }
        } else {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
        }

        end:

        if ($return['success'] == true && isset($assignment)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_CHANGE_STATUS);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function rejectAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('change_status', AclHelper::CONTROLLER_ASSIGNMENT);
        $this->checkPasswordBeforeExecute();

        $uuid = Helpers::__getRequestValue('uuid');
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $assignment = Assignment::findFirstByUuid($uuid);
            if ($assignment && $assignment->belongsToGms() && $assignment->checkMyViewPermission()) {
                $assignment->setApprovalStatus(Assignment::STATUS_REJECTED);
                $assignmentModelResult = $assignment->__quickUpdate();
                if ($assignmentModelResult instanceof Assignment) {
                    $return = [
                        'success' => true,
                        'message' => 'ASSIGNMENT_UPDATED_SUCCESS_TEXT',
                        'data' => [
                            'id' => $assignmentModelResult->getId(),
                            'reference' => $assignmentModelResult->getReference(),
                            'type_name' => ($assignmentModelResult->getAssignmentType()) ? $assignmentModelResult->getAssignmentType()->getName() : null,
                        ]
                    ];
                } else {
                    $return = $assignmentModelResult;
                }
            } else {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $_POST, 'data' => $assignment];
            }
        } else {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $_POST];
        }

        end:
        if ($return['success'] == true && isset($assignment)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_CHANGE_STATUS);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function terminateAction($uuid = "")
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('change_status', AclHelper::CONTROLLER_ASSIGNMENT);
        $this->checkPasswordBeforeExecute();
        if ($uuid == '') $uuid = Helpers::__getRequestValue('uuid');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $assignment = Assignment::findFirstByUuid($uuid);
            if ($assignment && $assignment->belongsToGms() && $assignment->checkMyViewPermission()) {
                $this->checkPermissionEditAssignment($assignment);
                $assignment->setIsTerminated(ModelHelper::YES);
                $assignmentModelResult = $assignment->__quickUpdate();
                if ($assignmentModelResult instanceof Assignment) {
                    $return = [
                        'success' => true,
                        'message' => 'ASSIGNMENT_UPDATED_SUCCESS_TEXT',
                        'data' => [
                            'id' => $assignmentModelResult->getId(),
                            'reference' => $assignmentModelResult->getReference(),
                            'type_name' => ($assignmentModelResult->getAssignmentType()) ? $assignmentModelResult->getAssignmentType()->getName() : null,
                        ]
                    ];
                } else {
                    $return = $assignmentModelResult;
                }
            } else {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $_POST, 'data' => $assignment];
            }
        } else {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $_POST];
        }
        if ($return['success'] == true && isset($assignment)) {
            ModuleModel::$assignment = $assignment;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_CHANGE_STATUS);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function initiate_relocationAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit();

        $assignment_uuid = Helpers::__getRequestValue('uuid');

        if ($assignment_uuid != '' && Helpers::__isValidUuid($assignment_uuid)) {
            $assignment = Assignment::findFirstByUuid($assignment_uuid);

            if ($assignment instanceof Assignment &&
                $assignment->belongsToGms() &&
                $assignment->canCreateRelocation() == true
            ) {
                $relocation = Relocation::createFromAssignment($assignment);
                if ($relocation && $relocation instanceof Relocation) {
                    $return = [
                        'success' => true,
                        'message' => 'RELOCATION_CREATE_SUCCESS_TEXT',
                        'data' => $assignment,
                        'relocation' => $relocation
                    ];
                } else {
                    $return = ['success' => false, 'message' => 'RELOCATION_CREATE_FAIL_TEXT'];
                }
            } else {
                $return = [
                    'success' => false,
                    'message' => 'ASSIGNMENT_NOT_FOUND_TEXT',
                ];
            }
        } else {
            $return = [
                'success' => false,
                'message' => 'ASSIGNMENT_NOT_FOUND_TEXT',
            ];
        }
        end:
        if ($return['success'] == true && isset($relocation)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_CREATE);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function itemAction($assignment_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('view', $this->router->getControllerName());
        $msg = [];

        if ($assignment_uuid != '') {
            $assignment = Assignment::getDetailByUuid($assignment_uuid);


            if ($assignment && $assignment->belongsToGms()) {
                $dataArray = $assignment_uuid->toArray();
                $dataArray['gmsFolderUuid'] = $assignment->getGmsFolderUuid();
                $dataArray['hrFolderUuid'] = $assignment->getHrFolderUuid();
                $dataArray['sharedHrGmsFolderUuid'] = $assignment->getSharedHrGmsFolderUuid();
                $dataArray['employeeFolderUuid'] = $assignment->getEmployeeFolderUuid();
                $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $dataArray]);
                return $this->response->send();
            } else {
                $this->response->setJsonContent(['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'data' => null]);
                return $this->response->send();
            }
        } else {
            $this->response->setJsonContent(['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT']);
            return $this->response->send();
        }
    }


    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function quickviewAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('view');

        if ($uuid != '') {
            $assignment = Assignment::findFirstByUuid($uuid);
            if ($assignment) {
                if ($assignment->checkCompany()) {
                    $this->response->setJsonContent(['success' => true, 'message' => 'ASSIGNMENT_FOUND_TEXT', 'data' => $assignment]);
                    return $this->response->send();
                } else {
                    $this->response->setJsonContent(['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT']);
                    return $this->response->send();
                }
            } else {
                $this->response->setJsonContent(['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT']);
                return $this->response->send();
            }
        } else {
            $this->response->setJsonContent(['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT']);
            return $this->response->send();
        }
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function deleteAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAcl('delete');
        $this->checkPasswordBeforeExecute();

        $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];

        if ($uuid != '') {
            $assignment = Assignment::findFirstByUuid($uuid);
            if ($assignment && $assignment->belongsToGms()) {
                $this->checkPermissionEditAssignment($assignment);
                $result = $assignment->archive();
                if ($result !== true) {
                    $return = $result;
                } else {
                    $return = ['success' => true, 'message' => 'ASSIGNMENT_DELETE_SUCCESS_TEXT'];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * count ending soon
     * @return mixed
     */
    public function count_approvalAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);


        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = isset($data->user_profile_uuid) && $data->user_profile_uuid != '' ? $data->user_profile_uuid : null;
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }
        $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND', 'count' => 0];
        $di = $this->di;
        try {

            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
            $queryBuilder->distinct(true);

            if (ModuleModel::$user_profile->isAdminOrManager() == false) {
                $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Task.uuid', 'DataUserMember');
                $queryBuilder->where("DataUserMember.user_profile_uuid = '" . $user_profile_uuid . "'");
            } else {
                $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
                $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
                $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
                $queryBuilder->where('Contract.to_company_id = ' . ModuleModel::$company->getId());
                $queryBuilder->andwhere('Contract.status = ' . Contract::STATUS_ACTIVATED);
            }

            $queryBuilder->andWhere("Assignment.approval_status = " . Assignment::APPROVAL_STATUS_DEFAULT);
            $queryBuilder->andwhere("Assignment.archived = :archived:", [
                'archived' => Assignment::ARCHIVED_NO
            ]);
            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 10,
                'page' => 1
            ]);

            $paginate = $paginator->getPaginate();
            $return = ['success' => true, 'count' => $paginate->total_items];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Get All Assignment of Employee Active in Contract
     * @param string $employee_id
     */
    public function employee_activeAction($employee_id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = Assignment::__findWithFilter(['employee_id' => $employee_id, 'limit' => 100]);
        if ($result['success'] == true) {
            $this->response->setJsonContent([
                'success' => true,
                'message' => '',
                'data' => $result['data'],
            ]);
            $this->response->send();
        } else {
            $this->response->setJsonContent($result);
            $this->response->send();
        }
    }


    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function searchAssignmentAction()
    {

        $this->view->disable();
        $this->checkAjax('PUT');

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['has_relocation'] = Helpers::__getRequestValue('has_relocation') === ModelHelper::YES || Helpers::__getRequestValue('has_relocation') === true ? true : (Helpers::__getRequestValue('has_relocation') === ModelHelper::NO || Helpers::__getRequestValue('has_relocation') === false ? false : null);
        $params['has_request'] = Helpers::__getRequestValue('has_request') === ModelHelper::YES || Helpers::__getRequestValue('has_request') === true ? true : (Helpers::__getRequestValue('has_request') === ModelHelper::NO || Helpers::__getRequestValue('has_request') === false ? false : null);
        $params['company_id'] = Helpers::__getRequestValue('company_id');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['simple'] = Helpers::__getRequestValue('simple');
        $params['active'] = Helpers::__getRequestValue('active');
        $params['employee_id'] = Helpers::__getRequestValue('employee_id');
        $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');
        $params['owner_uuid'] = Helpers::__getRequestValue('owner_uuid');

        $columns = Helpers::__getRequestValue('columns');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        /****** orders ****/
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


        /****** companies ****/
        $companies = Helpers::__getRequestValue('companies');
        $companiesIds = [];
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $item) {
                $item = (array)$item;
                if (isset($item['id'])) {
                    $companiesIds[] = $item['id'];
                }
            }
        }
        $params['companies'] = $companiesIds;
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
        $params['bookers'] = $bookersIds;
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

        /** @var run $return */

        $return = Assignment::__findWithFilter($params, $ordersConfig);

        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['ordersConfig'] = $ordersConfig;
            $return['draw'] = Helpers::__getRequestValue('draw');
        }

        $return['params'] = $params;

        if ($return['success'] == true) {
            $this->response->setJsonContent($return);
            $this->response->send();
        } else {
            $this->response->setJsonContent($return);
            $this->response->send();
        }

    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function searchAssignmentRequestAction()
    {

        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('index');

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['start'] = Helpers::__getRequestValue('start');

        $params['employee_id'] = Helpers::__getRequestValue('employee_id');
        $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');
        $params['owner_uuid'] = Helpers::__getRequestValue('owner_uuid');

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

        $return = AssignmentRequest::__findWithFilter($params, $ordersConfig);

        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }

        $return['params'] = $params;

        if ($return['success'] == true) {
            $this->response->setJsonContent($return);
            $this->response->send();
        } else {
            $this->response->setJsonContent($return);
            $this->response->send();
        }

    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function changeStatusAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl('change_status', $this->router->getControllerName());
        $status = Helpers::__getRequestValue('status');

        $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];

        $password = Helpers::__getRequestValue('password');
        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user_login->getEmail(), $password);
        if ($checkPassword['success'] == false) {
            $return = $checkPassword;
            $return['message'] = 'PASSWORD_INCORRECT_TEXT';
            goto end_of_function;
        }

        $uuid = Helpers::__getRequestValue('uuid');
        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }

        $assignment = Assignment::findFirstByUuid($uuid);
        if (!($assignment && $assignment->belongsToGms() && $assignment->checkMyViewPermission())) {
            goto end_of_function;
        }

        $this->checkPermissionEditAssignment($assignment);

        if ($status != 0 && $status != 1) {
            $return['message'] = 'DATA_NOT_FOUND_TEXT';
            goto end_of_function;
        }
        $assignment->setStatus((int)$status);
        $assignmentModelResult = $assignment->__quickUpdate();
        if ($assignmentModelResult['success'] == true) {
            $return = [
                'success' => true,
                'message' => 'ASSIGNMENT_UPDATED_SUCCESS_TEXT',
                'data' => [
                    'id' => $assignment->getId(),
                    'status' => $assignment->getStatus(),
                    'reference' => $assignment->getReference()
                ]
            ];
        } else {
            $return = $assignmentModelResult;
            $return['message'] = 'ASSIGNMENT_CAN_NOT_CHANGE_APPROVAL_STATUS_TEXT';
        }

        end_of_function:
        if ($return['success'] == true && isset($assignment)) {

            ModuleModel::$assignment = $assignment;

            if ($assignment->isActive()) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_ACTIVE);
            }

            if (!$assignment->isActive()) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_DESACTIVE);
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function changeApprovalStatusAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('change_status', AclHelper::CONTROLLER_ASSIGNMENT);
        $approval_status = Helpers::__getRequestValue('approval_status');
        $this->checkPasswordBeforeExecute();

        $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }

        $assignment = Assignment::findFirstByUuid($uuid);
        if (!($assignment && $assignment->belongsToGms() && $assignment->checkMyViewPermission())) {
            goto end_of_function;
        }

        $this->checkPermissionEditAssignment($assignment);
        if (!array_key_exists($approval_status, Assignment::$status_text)) {
            $return['message'] = 'DATA_NOT_FOUND_TEXT';
            goto end_of_function;
        }

        $assignment->setApprovalStatus($approval_status);
        $result = $assignment->__quickUpdate();

        if ($result['success'] == true) {
            $return = [
                'success' => true,
                'message' => 'ASSIGNMENT_UPDATED_SUCCESS_TEXT',
                'data' => [
                    'id' => $assignment->getId(),
                    'approval_status' => $assignment->getApprovalStatus(),
                    'reference' => $assignment->getReference(),
                    'type_name' => ($assignment->getAssignmentType()) ? $assignment->getAssignmentType()->getName() : null,
                ]
            ];
        } else {
            $return = $result;
            $return['message'] = 'ASSIGNMENT_CAN_NOT_CHANGE_APPROVAL_STATUS_TEXT';
        }

        end_of_function:
        if ($return['success'] == true && isset($assignment)) {
            ModuleModel::$assignment = $assignment;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_CHANGE_STATUS);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function saveEmployeeAssignmentDependantAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $this->checkAclMultiple([
            ['action' => 'edit', 'controller' => $this->router->getControllerName()],
            ['action' => 'create', 'controller' => $this->router->getControllerName()],
        ]);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $assignment = Helpers::__getRequestValueAsArray('assignment');
        $employee = Helpers::__getRequestValueAsArray('employee');
        $dependants = Helpers::__getRequestValueAsArray('dependants');

        if ($employee && isset($employee['uuid']) && Helpers::__isValidUuid($employee['uuid'])) {
            $employeeModel = Employee::findFirstByUuid($employee['uuid']);
            if (!$employeeModel || !$employeeModel->belongsToGms()) {
                $return = ['success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];
                goto end_of_function;
            }
        } else {
            goto end_of_function;
        }

        if ($assignment && isset($assignment['uuid']) && Helpers::__isValidUuid($assignment['uuid'])) {

            $assignmentTemporaryModel = RelodayObjectMapHelper::__getObjectWithCache($assignment['uuid']);


            if ($assignmentTemporaryModel &&
                $assignmentTemporaryModel->isAssignment() &&
                $assignmentTemporaryModel->isCreated() == false) {
                if (isset($assignment['id']) && Helpers::__isValidId($assignment['id'])) {
                    $assignmentModel = Assignment::findFirstByUuid($assignment['uuid']);

                    $this->checkPermissionEditAssignment($assignmentModel);

                    if (!$assignmentModel || !$assignmentModel->belongsToGms()) {
                        $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'data' => $assignmentModel];
                        goto end_of_function;
                    }
                }
                //Nothing
            } else {
                $assignmentModel = Assignment::findFirstByUuid($assignment['uuid']);

                $this->checkPermissionEditAssignment($assignmentModel);

                if (!$assignmentModel || !$assignmentModel->belongsToGms()) {
                    $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'data' => $assignmentModel];
                    goto end_of_function;
                }
            }
        }

        if (!is_array($dependants) || count($dependants) == 0) {
            $return = ['success' => true, 'message' => 'DATE_SAVE_SUCCESS_TEXT'];
            goto end_of_function;
        }

        $this->db->begin();

        $dependantModelList = [];

        foreach ($dependants as $dependant) {
            if (isset($dependant['id']) && intval($dependant['id']) > 0) {
                $dependantModel = Dependant::findFirstById($dependant['id']);

                if ($dependantModel &&
                    $dependantModel->belongsToGms() &&
                    $dependantModel->getEmployeeId() == $employeeModel->getId()) {

                    if (isset($dependant['selected']) && $dependant['selected'] == true) {
                        if (isset($assignmentModel) && $assignmentModel) {
                            $resultAssigment = $assignmentModel->addDependant($dependantModel);

                            if ($resultAssigment['success'] == false) {
                                $return = ['success' => false, 'message' => 'DEPENDANT_SAVE_FAIL_TEXT'];
                                $this->db->rollback();
                                goto end_of_function;
                            }
                        } else {
                            $dependantModelList[] = $dependant;
                        }
                    } else {
                        //remove
                        if (isset($assignmentModel) && $assignmentModel) {
                            $resultRemove = $assignmentModel->removeDependant($dependantModel);
                            if ($resultRemove['success'] == false) {
                                $return = ['success' => false, 'message' => 'DEPENDANT_SAVE_FAIL_TEXT'];
                                $this->db->rollback();
                                goto end_of_function;
                            }
                        } else {
                            $dependantModelList[] = $dependant;
                        }
                    }
                }
            } else {
                $resultDependant = $employeeModel->addDependant($dependant);

                if ($resultDependant['success'] == false) {
                    $return = ['success' => false, 'message' => 'DEPENDANT_SAVE_FAIL_TEXT', 'detail' => $resultDependant['detail']];
                    $this->db->rollback();
                    goto end_of_function;
                }

                if (isset($assignmentModel) &&
                    $assignmentModel &&
                    isset($dependant['selected']) &&
                    $dependant['selected'] == true) {


                    $resultAssigment = $assignmentModel->addDependant($resultDependant['data']);

                    if ($resultAssigment['success'] == false) {
                        $return = ['success' => false, 'message' => 'DEPENDANT_SAVE_FAIL_TEXT', 'detail' => $resultAssigment['detail']];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                }

                if (!isset($assignmentModel)) {
                    $dependantModelList[$resultDependant['data']->getId()] = $resultDependant['data']->toArray();
                    $dependantModelList[$resultDependant['data']->getId()]['selected'] = isset($dependant['selected']) ? $dependant['selected'] : false;
                }
            }
        }

        $this->db->commit();
        $return = ['success' => true, 'message' => 'DEPENDANT_SAVE_SUCCESS_TEXT'];

        $return['data'] = isset($assignmentModel) ? $assignmentModel->getAllDependants() : array_values($dependantModelList);
        end_of_function:

        if ($return['success'] == true && isset($assignmentModel)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignmentModel, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_UPDATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeEmployeeAssignmentDependantAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $return = ['success' => false, 'message' => 'CAN_NOT_UPDATE_ASSIGNMENT_TEXT'];
        $this->checkAclMultiple([
            ['action' => 'edit', 'controller' => $this->router->getControllerName()],
            ['action' => 'create', 'controller' => $this->router->getControllerName()],
        ]);

        $uuid = Helpers::__getRequestValue('uuid');

        if ($uuid && Helpers::__isValidUuid($uuid)) {

            $assignmentTemporaryModel = RelodayObjectMapHelper::__getObjectWithCache($uuid);

            if ($assignmentTemporaryModel &&
                $assignmentTemporaryModel->isAssignment() &&
                $assignmentTemporaryModel->isCreated() == false) {
                //Nothing
            } else {
                $assignmentModel = Assignment::findFirstByUuid($uuid);
                $this->checkPermissionEditAssignment($assignmentModel);
                if (!$assignmentModel || !$assignmentModel->belongsToGms()) {
                    $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'data' => $assignmentModel];
                    goto end_of_function;
                }
                $return = $assignmentModel->removeAllDependants();
            }
        }


        end_of_function:

        if ($return['success'] == true && isset($assignmentModel)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignmentModel, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_UPDATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function getAllDependants($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('view', $this->router->getControllerName());
        $msg = [];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $assignment = Assignment::findFirstByUuid($uuid);
            if ($assignment && $assignment->belongsToGms() && $assignment->checkMyViewPermission()) {

                $employee = $assignment->getEmployee();
                $dependants = [];
                if ($employee) {
                    $dependants = $employee->getDependants()->toArray();
                    if (count($dependants)) {
                        foreach ($dependants as $key => $dependant) {
                            if ($assignment->checkIfDependantExist($dependant['id'])) {
                                $dependants[$key]['selected'] = true;
                            }
                        }
                    }
                }

                $return = [
                    'success' => true, 'data' => $dependants
                ];

            } else {
                $return = [
                    'success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $_POST, 'data' => $assignment
                ];
            }
        } else {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $_POST];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"POST"}, name="apply-workflow")
     */
    public function applyWorkflowAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclEdit();

        $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue("uuid");
        $workflow_id = Helpers::__getRequestValue("workflow");
        $tasks = Helpers::__getRequestValue("tasks");
        $listTaskUuids = [];
        $count = 0;
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $assignment = Assignment::findFirstByUuid($uuid);

            if ($assignment && $assignment->belongsToGms() && $assignment->checkMyEditPermission()) {

                if ($workflow_id != '') {
                    $workflow = Workflow::findFirstById($workflow_id);
                    if ($workflow && $workflow->belongsToGms()) {
                        $workflow_uuid = $workflow->getUuid();
                        // update: task_template
                        $taskTemplates = TaskTemplate::getByWorkflowUuid($workflow_uuid, Task::TASK_TYPE_INTERNAL_TASK);
                        $tasks_list = [];
                        $task_created_list = [];
                        $task_deleted_list = [];
                        $this->db->begin();
                        if ($taskTemplates->count() > 0) {

                            foreach ($taskTemplates as $taskTemplate) {
                                $count++;
                                $custom = $taskTemplate->toArray();
                                $custom['assignment_id'] = $assignment->getId();
                                $custom['link_type'] = Task::LINK_TYPE_ASSIGNMENT;
                                $custom['object_uuid'] = $uuid;
                                $custom['company_id'] = ModuleModel::$company->getId();
                                $custom['owner_id'] = ModuleModel::$user_profile->getId(); //get owner of task
                                $custom['creator_id'] = ModuleModel::$user_profile->getId(); //get owner of task
                                $numberExistedTasks = $assignment->countTasks();
                                $taskTemplateSequence = $taskTemplate->getSequence() ? $taskTemplate->getSequence() : 0;

                                $taskObject = new Task();
                                $taskObject->setData($custom);
                                $taskObject->setEmployeeId($assignment->getEmployeeId());
                                $taskObject->setSequence($numberExistedTasks + $taskTemplateSequence);
                                $taskObject->setCreatorId(ModuleModel::$user_profile->getId());
                                $taskObject->setTaskType((int)$taskTemplate->getTaskType());
                                $taskObject->setIsFinalReview((int)$taskTemplate->getIsFinalReview());
                                $taskObject->setHasFile((int)$taskTemplate->getHasFile());

                                $taskResult = $taskObject->__quickCreate();
                                $listTaskUuids[] = $taskObject->getUuid();
                                $task_created_list[] = $taskObject;

                                if ($taskResult['success'] == true) {
                                    /// add reporter of RELOCATION
                                    $taskObject->addReporter(ModuleModel::$user_profile);

                                    /// add owner of RELOCATION
                                    $owner = $assignment->getSimpleProfileOwner();
                                    if ($owner instanceof UserProfile) {

                                        $resultAddOwner = $taskObject->addOwner($owner);
                                        if (!$resultAddOwner["success"]) {
                                            $this->db->rollback();
                                            $return = [
                                                'success' => false,
                                                'detailAddOwner' => $resultAddOwner,
                                                'message' => 'TASK_LIST_CREATE_FAILED_TEXT'
                                            ];
                                            goto end_of_function;
                                        }
                                        $resultAddCreator = $taskObject->addCreator(ModuleModel::$user_profile);
                                        if (!$resultAddCreator["success"]) {
                                            $this->db->rollback();
                                            $return = [
                                                'success' => false,
                                                'detailAddCreator' => $resultAddCreator,
                                                'message' => 'TASK_LIST_CREATE_FAILED_TEXT'
                                            ];
                                            goto end_of_function;
                                        }
                                    }

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
                                            if ($checklist->getName() != '') {
                                                $taskItemObject = new TaskChecklist();
                                                $taskItemObject->setData();
                                                $taskItemObject->setObjectUuid($taskObject->getUuid());
                                                $taskItemObject->setName($checklist->getName());
                                                $taskItemObject->setTaskId($taskObject->getId());
                                                $taskItemObject->setSequence($checklist->getSequence());
                                                $taskItemResult = $taskItemObject->__quickCreate();
                                                if ($taskItemResult['success'] == false) {
                                                    $this->db->rollback();
                                                    $return = [
                                                        'success' => false,
                                                        'detail' => $taskItemResult,
                                                        'message' => 'TASK_LIST_CREATE_FAILED_TEXT',
                                                        'raw' => $taskObject
                                                    ];
                                                    goto end_of_function;
                                                }
                                                $tasks_list[$taskObject->getUuid()]['checklist_create'][] = $taskItemObject;
                                            }
                                        }
                                    }

                                    $reminders = $taskTemplate->getTaskTemplateReminders();

                                    if (count($reminders) > 0) {
                                        foreach ($reminders as $reminder) {
                                            $reminderConfig = new ReminderConfig();

                                            $reminderConfig->setUuid(Helpers::__uuid());
                                            $reminderConfig->setAssignmentId($assignment->getId());
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
                                                $eventValue = EventValue::__findByOjbectAndEvent($assignment->getId(), $event->getId());
                                                if (!$eventValue) {
                                                    $eventValue = new EventValue();
                                                    if ($event->getObjectSource() == Event::SOURCE_ASSIGNMENT) {
                                                        $object = $assignment;
                                                    } else if ($event->getObjectSource() == Event::SOURCE_EMPLOYEE && $taskObject->getEmployee()) {
                                                        $object = $taskObject->getEmployee();
                                                    } else {
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
                                    $return = ['success' => false, 'detail' => $taskResult, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT', 'raw' => $taskObject];
                                    goto end_of_function;
                                }
                            }
                        }
                        $isRemove = false;

                        if (count($tasks) > 0) {
                            $customHistoryParams = [
                                'companyUuid' => ModuleModel::$company->getUuid(),
                                'currentUserUuid' =>  ModuleModel::$user_profile->getUuid(),
                                'language' => ModuleModel::$system_language,
                                'ip' => $this->request->getClientAddress(),
                                'appUrl' => ModuleModel::$app->getFrontendUrl(),
                                'historyObjectUuids' => [$assignment->getUuid()]
                            ];

                            foreach ($tasks as $task) {
                                if ($task->keep == 0) {
                                    $isRemove = true;
                                    $taskToRemove = Task::findFirstByUuid($task->uuid);
                                    $resultRemove = $taskToRemove->__quickRemove();
                                    if ($resultRemove['success'] == false) {
                                        $return = ['success' => false, 'detail' => $resultRemove, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                                        $this->db->rollback();
                                        goto end_of_function;
                                    }
                                    $task_deleted_list[] = $taskToRemove;
                                    $taskToRemove->sendHistoryToQueue(History::HISTORY_REMOVE, $customHistoryParams);
                                }
                            }
                        }

                        $attachments = MediaAttachment::__get_attachments_from_uuid($workflow_uuid);
                        $folders = $assignment->getFolders();
                        if (!$folders['success']) {
                            $this->db->rollback();
                            $return = ['success' => false, 'folders' => $folders, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                            goto end_of_function;
                        }
                        if (count($attachments) > 0) {

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

                        /** set workflow applied = true */

                        $assignment->setWorkflowId($workflow_id);
                        $resultSaveAssignment = $assignment->__quickUpdate();
                        if ($resultSaveAssignment['success'] == true) {
                            $this->db->commit();

                            if ($isRemove) {
                                //SET SEQUENCE AGAIN
                                $assignment->setSequenceOfTasks();
                            }

                            $return = [
                                'success' => true,
                                'data' => $tasks_list,
                                'message' => 'TASK_LIST_CREATE_SUCCESS_TEXT',
                                'template' => $taskTemplates,
                                'attachment' => $attachments,
                            ];
                            goto end_of_function;
                        } else {
                            $return = [
                                'success' => false,
                                'assignment' => $assignment,
                                'message' => 'DATA_SAVE_FAILED_TEXT'
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    } else {
                        $return = [
                            'success' => false,
                            'message' => 'WORKFLOW_NOT_FOUND_TEXT',
                            'data' => $workflow
                        ];
                    }
                } else {
                    $return = ['success' => false, 'message' => 'WORKFLOW_NOT_FOUND_TEXT'];
                }
            } else {
                $return = [
                    'success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'data' => $assignment
                ];
            }
        }

        end_of_function:

        if ($return['success'] == true && isset($assignment)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_APPLY_WORKFLOW);

            ModuleModel::$assignment = $assignment;
            ModuleModel::$task_deleted_list = $task_deleted_list;
            ModuleModel::$task_created_list = $task_created_list;
            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('workflow_name', $workflow ? $workflow->getName() : '');
            $this->dispatcher->setParam('listTaskUuids', $listTaskUuids);
            $this->dispatcher->setParam('task_count', $count);

        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * count active items
     */
    public function countTotalAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = [
            'success' => true,
            'data' => [
                'count_requests' => Assignment::__countActiveRequests(),
                'count_pending_requests' => Assignment::__countPendingRequests(),
                'count_assignments' => Assignment::__countActiveAssignments()
            ]
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param String $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getRequestAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = [
            'success' => false, 'message' => 'REQUEST_NOT_FOUND_TEXT',
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $request = AssignmentRequest::findFirstByUuid($uuid);
            if ($request) {
                $dataArray = $request->toArray();
                $assignment = $request->getAssignment();
                $dataArray["assignment"] = $assignment->getInfoDetailInArray();
                $dataArray["assignment"]["owner"] = $assignment->getHrOwner();
                $dependants = $assignment->getEmployee()->getDependants()->toArray();
                $count = 0;
                if (count($dependants)) {
                    foreach ($dependants as $key => $dependant) {
                        if ($assignment->checkIfDependantExist($dependant['id'])) {
                            $dependants[$key]['selected'] = true;
                            $count += 1;
                        } else {
                            $dependants[$key]['selected'] = false;
                        }
                    }
                }

                $dataArray['dependants'] = $dependants;
                $dataArray['num_dependants_follow'] = $count;
                $employee = $assignment->getEmployee();
                $dataArray["employee"] = $assignment->getEmployee()->parseArrayData();
                $dataArray["employee"]['citizenships'] = $employee->parseCitizenships();
                $dataArray['hr_company'] = $request->getOwnerCompany()->getName();
                $dataArray['hr_company_uuid'] = $request->getOwnerCompany()->getUuid();
                $dataArray['hr_company_phone'] = $request->getOwnerCompany()->getPhone();
                $return = [
                    'success' => true,
                    'data' => $dataArray,
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function rejectRequestAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();


        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];
        $requestUuid = Helpers::__getRequestValue('uuid');
        if ($requestUuid != '' && Helpers::__isValidUuid($requestUuid)) {
            $assignmentRequest = AssignmentRequest::findFirstByUuid($requestUuid);

            if ($assignmentRequest) {

                if ($assignmentRequest->getRelocation() || $assignmentRequest->getStatus() == AssignmentRequest::STATUS_ACCEPTED) {
                    $return = [
                        'success' => false,
                        'message' => 'RELOCATION_EXISTED_TEXT',
                    ];
                    goto end_of_function;
                }

                $hrCompany = $assignmentRequest->getOwnerCompany();
                $employee = $assignmentRequest->getAssignment()->getEmployee();
                $assignment = $assignmentRequest->getAssignment();

                $this->db->begin();
                $assignmentRequest->setStatus(AssignmentRequest::STATUS_REJECTED);
                $resultDelete = $assignmentRequest->__quickUpdate();
                if ($resultDelete['success'] == false) {
                    $this->db->rollback();
                    $return = [
                        'success' => false,
                        'message' => 'REQUEST_REJECT_FAIL_TEXT',
                    ];
                    goto end_of_function;
                }

                /**
                 * create comment for initiation
                 */
                $commentUuid = Helpers::__uuid();

                $commentObject = new Comment();
                $commentObject->setUuid($commentUuid);
                $commentObject->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
                $commentObject->setCompanyUuid(ModuleModel::$company->getUuid());
                $commentObject->setObjectUuid($assignmentRequest->getUuid());
                $commentObject->setMessage(TextHelper::convert2markdown(Helpers::__getRequestValue('message')));
                $commentObject->setReport(Comment::REPORT_NO);

                $result = $commentObject->__quickCreate();
                if ($result['success'] == false) {
                    $return = $result;
                    $return['message'] = 'COMMENT_ADDED_FAILED_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }


                $comment = $commentObject->parseDataToArray();
                $comment['message'] = Helpers::__getRequestValue('message');
                $comment['editable'] = true;
                $comment['user_uuid'] = $commentObject->getUserProfileUuid();
                $returnPusher = PushHelper::__sendNewChatMessage($commentObject->getObjectUuid(), $comment);

                $this->db->commit();
                $resultCheck = [];
                /*** sent to DSP ADMIN **/
                $adminProfile = $assignment->getHrOwner();
                if ($adminProfile) {
                    $beanQueue = RelodayQueue::__getQueueSendMail();
                    $resultCheck = [];
                    $dataArray = [
                        'action' => "sendMail",
                        'to' => $adminProfile->getPrincipalEmail(),
                        'params' => [
                            'company_name' => $hrCompany->getName(),
                            'provider' => ModuleModel::$company->getName(),
                            'assignee_name' => $employee->getFullName(),
                            'assignment_number' => $assignment->getNumber(),
                            'assignment_url' => $assignment->getHrFrontendUrl($hrCompany),
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'date' => date('d M Y - H:i:s'),
                            'message' => Helpers::__getRequestValue('message'),
                            'user_name' => $adminProfile->getFullname(),
                            'recipient_name' => $adminProfile->getFullname(),
                        ],
                        'templateName' => EmailTemplateDefault::INITIATION_REQUEST_REJECTED,
                        'language' => ModuleModel::$system_language
                    ];
                    $resultCheck[] = $beanQueue->addQueue($dataArray);
                }
                $reporterProfile = $assignment->getHrReporter();
                if ($reporterProfile && $adminProfile && $reporterProfile->getId() != $adminProfile->getId()) {
                    $beanQueue = RelodayQueue::__getQueueSendMail();
                    $resultCheck = [];

                    $dataArray = [
                        'action' => "sendMail",
                        'to' => $reporterProfile->getPrincipalEmail(),
                        'params' => [
                            'company_name' => $hrCompany->getName(),
                            'provider' => ModuleModel::$company->getName(),
                            'assignee_name' => $employee->getFullName(),
                            'assignment_number' => $assignment->getNumber(),
                            'assignment_url' => $assignment->getHrFrontendUrl($hrCompany),
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'date' => date('d M Y - H:i:s'),
                            'message' => Helpers::__getRequestValue('message'),
                            'user_name' => $reporterProfile->getFullname(),
                            'recipient_name' => $reporterProfile->getFullname(),
                        ],
                        'templateName' => EmailTemplateDefault::INITIATION_REQUEST_REJECTED,
                        'language' => ModuleModel::$system_language
                    ];
                    $resultCheck[] = $beanQueue->addQueue($dataArray);
                }

                $return = [
                    'success' => true,
                    'resultCheckSendToAdmin' => $resultCheck,
                    'message' => 'REQUEST_REJECT_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->dispatcher->setParam('return', $return);

        if ($return['success'] == true && isset($assignment) && $assignment && isset($gmsApplyCompany)) {
            if ($assignment) {
                ModuleModel::$assignment = $assignment;
                $return['$apiResults'] = NotificationServiceHelper::__addNotification(
                    $assignment,
                    HistoryModel::TYPE_ASSIGNMENT,
                    HistoryModel::INITIATION_REQUEST_REJECTED,
                    [
                        'company_name' => $gmsApplyCompany->getName()
                    ]);
            }
        }

        if ($return['success'] && isset($assignment) && $assignment) {
            $beanQueue = new RelodayQueue(getenv('QUEUE_WEBHOOK_WORKER'));
    
            $resultQueue = $beanQueue->addQueue([
                'action' => "create",
                'params' => [
                    'uuid' => $assignmentRequest->getUuid(),
                    'object_type' => $assignmentRequest->getSource(),
                    'action' => 'edit',
                    'action_display' => 'rejectInitiationRequest'
                ]
            ]);
            $resultHistory = $this->sendHistoryToHrOfRequestAction($assignment, 'HISTORY_REJECT_RELOCATION_REQUEST');
            $return['resultHistory'] = $resultHistory;
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function acceptRequestAction(string $requestUuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $ownerUserProfileUuid = Helpers::__getRequestValue('owner_user_profile_uuid');

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if (!Helpers::__isValidUuid($requestUuid)) {
            $return = [
                'success' => false,
                'message' => 'OWNER_NOT_FOUND_TEXT',
            ];
        }

        $ownerProfile = UserProfile::findFirstByUuidCache($ownerUserProfileUuid);

        if (!$ownerProfile || $ownerProfile->isGms() == false || $ownerProfile->belongsToGms() == false) {
            $return = [
                'success' => false,
                'message' => 'OWNER_NOT_FOUND_TEXT',
            ];
        }


        if ($requestUuid != '' && Helpers::__isValidUuid($requestUuid)) {
            $assignmentRequest = AssignmentRequest::findFirstByUuid($requestUuid);

            if ($assignmentRequest) {

                $hrCompany = $assignmentRequest->getOwnerCompany();
                $employee = $assignmentRequest->getAssignment()->getEmployee();
                $assignment = $assignmentRequest->getAssignment();

                $currentActiveContract = ModuleModel::$company->getActiveContract($hrCompany->getId());
                if (!$currentActiveContract) {
                    $return = [
                        'success' => false,
                        'data' => $currentActiveContract,
                        'message' => 'CONTRACT_NOT_FOUND_TEXT',
                    ];
                    goto end_of_function;
                }

                $this->db->begin();
                $assignmentRequest->setStatus(AssignmentRequest::STATUS_ACCEPTED);
                $resultDelete = $assignmentRequest->__quickUpdate();
                if ($resultDelete['success'] == false) {
                    $this->db->rollback();
                    $return = [
                        'success' => false,
                        'errorType' => 'canNotChangeStatusOfAssignmentRequest',
                        'message' => 'REQUEST_ACCEPT_FAIL_TEXT',
                    ];
                    goto end_of_function;
                }
                $resultAddContract = $assignment->addToContract($currentActiveContract);
                if ($resultAddContract['success'] == false) {
                    $return = [
                        'success' => false,
                        'errorType' => 'cannotAddAssignmentToContract',
                        'message' => 'REQUEST_ACCEPT_FAIL_TEXT',
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                //attachments
                $sharer_uuids = [];
                $sharer_uuids[] = ModuleModel::$company->getUuid();
                $sharer_uuids[] = $hrCompany->getUuid();
                $attachments = MediaAttachment::__findWithFilter([
                    'limit' => 1000,
//                    'is_shared' => true,
                    'object_name' => false,
                    'object_uuid' => $assignmentRequest->getUuid(),
//                    'sharer_uuids' => $sharer_uuids
                ]);
                if ($attachments['success'] == false) {
                    $return = [
                        'success' => false,
                        'errorType' => 'cannotFindAttachments',
                        'message' => 'REQUEST_ACCEPT_FAIL_TEXT',
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }
                $random = new Random();

                // create object folder for dsp
                $object_folder_dsp = new ObjectFolder();
                $object_folder_dsp->setUuid($random->uuid());
                $object_folder_dsp->setObjectUuid($assignment->getUuid());
                $object_folder_dsp->setObjectName($assignment->getSource());
                $object_folder_dsp->setAssignmentId($assignment->getId());
                $object_folder_dsp->setDspCompanyId(ModuleModel::$company->getId());
                $result = $object_folder_dsp->__quickCreate();
                if (!$result['success']) {
                    $return = [
                        'success' => false,
                        'errorDetail' => $result,
                        'errorType' => 'canNotCreateObjectFolderDsp',
                        'message' => 'REQUEST_ACCEPT_FAIL_TEXT',
                        'detail' => $object_folder_dsp
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                // create object folder for dsp and hr
                $object_folder_dsp_hr = new ObjectFolder();
                $object_folder_dsp_hr->setUuid($random->uuid());
                $object_folder_dsp_hr->setObjectUuid($assignment->getUuid());
                $object_folder_dsp_hr->setObjectName($assignment->getSource());
                $object_folder_dsp_hr->setAssignmentId($assignment->getId());
                $object_folder_dsp_hr->setDspCompanyId(ModuleModel::$company->getId());
                $object_folder_dsp_hr->setHrCompanyId($employee->getCompanyId());
                $result = $object_folder_dsp_hr->__quickCreate();
                if (!$result['success']) {
                    $return = [
                        'success' => false,
                        'errorType' => 'canNotCreateObjectFolderDsp',
                        'message' => 'REQUEST_ACCEPT_FAIL_TEXT',
                        'detail' => $object_folder_dsp_hr
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                // create object folder for employee and dsp
                $object_folder_dsp_ee = new ObjectFolder();
                $object_folder_dsp_ee->setUuid($random->uuid());
                $object_folder_dsp_ee->setObjectUuid($assignment->getUuid());
                $object_folder_dsp_ee->setObjectName($assignment->getSource());
                $object_folder_dsp_ee->setAssignmentId($assignment->getId());
                $object_folder_dsp_ee->setDspCompanyId(ModuleModel::$company->getId());
                $object_folder_dsp_ee->setEmployeeId($employee->getId());
                $result = $object_folder_dsp_ee->__quickCreate();
                if (!$result['success']) {
                    $return = [
                        'success' => false,
                        'errorType' => 'canNotCreateObjectFolderDsp',
                        'message' => 'REQUEST_ACCEPT_FAIL_TEXT',
                        'detail' => $object_folder_dsp_ee
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                if (count($attachments['data']) > 0) {
                    foreach ($attachments['data'] as $attachment) {
                        if (Helpers::__isValidUuid($attachment['user_profile_uuid'])) {
                            $userProfile = UserProfile::findFirstByUuidCache($attachment['user_profile_uuid']);
                        } else {
                            $userProfile = ModuleModel::$user_profile;
                        }

                        $attachResult = MediaAttachment::__createAttachment([
                            'objectUuid' => $object_folder_dsp_hr->getUuid(),
                            'objectName' => $object_folder_dsp_hr->getSource(),
                            'file' => $attachment,
                            'userProfile' => $userProfile,
                        ]);

                        if (!$attachResult['success']) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $attachResult];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }
                }

                $resultAddOwner = $assignment->addOwner($ownerProfile);
                if (!$resultAddOwner['success']) {
                    $return = ['success' => false, 'errorType' => 'assignmentAddOwner', 'detail' => $resultAddOwner];
                    $this->db->rollback();
                    goto end_of_function;
                }

                //Assignment copy order number
                $assignment->setOrderNumber($assignmentRequest->getPoCode());
                $resultUpdateAssignment = $assignment->__quickUpdate();
                if (!$resultUpdateAssignment['success']) {
                    $return = ['success' => false, 'errorType' => 'assignmentUpdatePoNumber', 'detail' => $resultUpdateAssignment];
                    $this->db->rollback();
                    goto end_of_function;
                }

                $this->db->commit();

                $resultCheck = [];
                /*** sent to HR ADMIN **/
                $adminProfile = $assignment->getHrOwner();
                if ($adminProfile) {
                    $beanQueue = RelodayQueue::__getQueueSendMail();
                    $resultCheck = [];

                    $dataArray = [
                        'action' => "sendMail",
                        'to' => $adminProfile->getPrincipalEmail(),
                        'params' => [
                            'company_name' => $hrCompany->getName(),
                            'provider' => ModuleModel::$company->getName(),
                            'assignee_name' => $employee->getFullName(),
                            'assignment_number' => $assignment->getNumber(),
                            'assignment_url' => $assignment->getHrFrontendUrl($hrCompany),
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'date' => date('d M Y - H:i:s'),
                            'message' => Helpers::__getRequestValue('message'),
                            'user_name' => $adminProfile->getFullname(),
                            'recipient_name' => $adminProfile->getFullname(),
                        ],
                        'templateName' => EmailTemplateDefault::INITIATION_REQUEST_ACCEPTED,
                        'language' => ModuleModel::$system_language
                    ];
                    $resultCheck[] = $beanQueue->addQueue($dataArray);
                }
                $reporterProfile = $assignment->getHrReporter();
                if ($reporterProfile && $adminProfile && $reporterProfile->getId() != $adminProfile->getId()) {
                    $beanQueue = RelodayQueue::__getQueueSendMail();
                    $resultCheck = [];

                    $dataArray = [
                        'action' => "sendMail",
                        'to' => $reporterProfile->getPrincipalEmail(),
                        'params' => [
                            'company_name' => $hrCompany->getName(),
                            'provider' => ModuleModel::$company->getName(),
                            'assignee_name' => $employee->getFullName(),
                            'assignment_number' => $assignment->getNumber(),
                            'assignment_url' => $assignment->getHrFrontendUrl($hrCompany),
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'date' => date('d M Y - H:i:s'),
                            'message' => Helpers::__getRequestValue('message'),
                            'user_name' => $reporterProfile->getFullname(),
                            'recipient_name' => $reporterProfile->getFullname(),
                        ],
                        'templateName' => EmailTemplateDefault::INITIATION_REQUEST_ACCEPTED,
                        'language' => ModuleModel::$system_language
                    ];
                    $resultCheck[] = $beanQueue->addQueue($dataArray);
                }

                $return = [
                    'success' => true,
                    'resultCheckSendToAdmin' => $resultCheck,
                    'message' => 'REQUEST_ACCEPT_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->dispatcher->setParam('return', $return);

        if ($return['success'] == true && isset($assignment) && $assignment && isset($hrCompany)) {
            if ($assignment) {
                ModuleModel::$assignment = $assignment;
                $return['$apiResults'] = NotificationServiceHelper::__addNotification(
                    $assignment,
                    HistoryModel::TYPE_ASSIGNMENT,
                    HistoryModel::INITIATION_REQUEST_ACCEPTED,
                    [
                        'company_name' => $hrCompany->getName()
                    ]);

                $resultHistory = $this->sendHistoryToHrOfRequestAction($assignment, 'HISTORY_ACCEPT_RELOCATION_REQUEST');
                $return['resultHistory'] = $resultHistory;
            }

        }
        if ($return['success'] == true){
            $beanQueue = new RelodayQueue(getenv('QUEUE_WEBHOOK_WORKER'));
    
            $resultQueue = $beanQueue->addQueue([
                'action' => "create",
                'params' => [
                    'uuid' => $assignmentRequest->getUuid(),
                    'object_type' => $assignmentRequest->getSource(),
                    'action' => 'edit',
                    'action_display' => 'acceptInitiationRequest'
                ]
            ]);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Check if Object Folder DSP is created or Not, if not, create object dsp folder
     * @param String $uuid
     */
    public function checkMyDocumentFolderAction(string $uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__isValidUuid($uuid)) {
            $assignment = Assignment::findFirstByUuid($uuid);
            if (!$assignment) {
                $checkObjectMap = RelodayObjectMapHelper::__getAssignment($uuid);
                if (!$checkObjectMap) {
                    $return = [
                        'success' => false,
                        'message' => 'OBJECT_ASSIGNMENT_NOT_FOUND_TEXT'
                    ];
                    goto end;
                }
            }
            if ($assignment && $assignment->belongsToGms() == false) {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
                goto end;
            }

            if ($assignment) {
                $myDspFolder = $assignment->getMyDspFolder();
                if (!$myDspFolder) {
                    //create one
                    $return = ObjectFolder::__createMyDspFolder($assignment->getUuid(), 'assignment');
                    goto end;
                } else {
                    $return = ['success' => true, 'data' => $myDspFolder];
                    goto end;
                }
            } else if (isset($checkObjectMap) && $checkObjectMap) {
                $myDspFolder = $checkObjectMap->getMyDspFolder(ModuleModel::$company->getId());
                if (!$myDspFolder) {
                    //create one
                    $return = ObjectFolder::__createMyDspFolder($checkObjectMap->getUuid(), 'assignment');
                    goto end;
                } else {
                    $return = ['success' => true, 'data' => $myDspFolder];
                    goto end;
                }
            }
        }
        end:
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

        $assignment_uuid = Helpers::__getRequestValue("uuid");

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_ASSIGNMENT_TEXT', 'detail' => $assignment_uuid];

        if ($assignment_uuid != '' && Helpers::__isValidUuid($assignment_uuid)) {

            $assignment = Assignment::findFirstByUuid($assignment_uuid);

            if (!($assignment && $assignment->belongsToGms() && $assignment->checkMyEditPermission())) {
                $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_ASSIGNMENT_TEXT'];
                goto end_of_function;
            }

            $this->db->begin();
            $update = false;

            $estimated_start_date = Helpers::__getRequestValue("estimated_start_date");
            if ($estimated_start_date != '' && Helpers::__isDate($estimated_start_date, "Y-m-d")) {
                $assignment->setEstimatedStartDate($estimated_start_date);
                $update = true;
                $this->dispatcher->setParam('isHistoryUpdate', true);
            }

            $estimated_end_date = Helpers::__getRequestValue("estimated_end_date");
            if ($estimated_end_date != '' && Helpers::__isDate($estimated_end_date, "Y-m-d")) {
                $assignment->setEstimatedEndDate($estimated_end_date);
                $update = true;
                $this->dispatcher->setParam('isHistoryUpdate', true);
            }

            $effective_start_date = Helpers::__getRequestValue("effective_start_date");
            if ($effective_start_date != '' && Helpers::__isDate($effective_start_date, "Y-m-d")) {
                $assignment->setEffectiveStartDate($effective_start_date);
                $update = true;
                $this->dispatcher->setParam('isHistoryUpdate', true);
            }

            $end_date = Helpers::__getRequestValue("end_date");
            if ($end_date != '' && Helpers::__isDate($end_date, "Y-m-d")) {
                $assignment->setEndDate($end_date);
                $update = true;
                $this->dispatcher->setParam('isHistoryUpdate', true);
            }

            $booker_company_id = Helpers::__getRequestValue("booker_company_id");
            if ($booker_company_id) {
                $assignment->setBookerCompanyId($booker_company_id);
                $update = true;
                $this->dispatcher->setParam('isHistoryUpdate', true);
            }

            /*** add reporter profile **/
            $report_user_profile_uuid = Helpers::__getRequestValue("report_user_profile_uuid");
            if ($report_user_profile_uuid != '' && Helpers::__isValidUuid($report_user_profile_uuid)) {
                $update = true;
                $profile = UserProfile::findFirstByUuid($report_user_profile_uuid);
                $reporter = DataUserMember::getDataReporter($assignment_uuid);
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
                    $return = [
                        "success" => true,
                        "message" => "SET_REPORTER_SUCCESS_TEXT"
                    ];
                    // If same reporter do nothing
                } else {
                    //delete current reporter if exist
                    if ($reporter) {
                        $returnDeleteReporter = DataUserMember::deleteReporters($assignment_uuid);
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
                        $assignment_uuid,
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
                $owner = DataUserMember::getDataOwner($assignment_uuid, ModuleModel::$company->getId());
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
                    $return = [
                        "success" => true,
                        "message" => "SET_OWNER_SUCCESS_TEXT"
                    ];
                    // If same owner do nothing
                } else {
                    //delete current owner
                    if ($owner) {
                        $returnDeleteOwner = DataUserMember::deleteOwners($assignment_uuid, ModuleModel::$company->getId());
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
                    $returnAddOwner = DataUserMember::addOwner(
                        $assignment_uuid,
                        $profile,
                        DataUserMember::MEMBER_TYPE_OBJECT_TEXT
                    );
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
                                    'object_uuid' => $assignment_uuid,
                                    'user_profile_uuid' => $user->getUuid(),
                                    'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                                ]
                            ]);

                            if (!$data_user_member) {
                                $returnCreate = DataUserMember::addViewer($assignment_uuid, $user);

                                if ($returnCreate['success'] == false) {
                                    $return = [
                                        'success' => false,
                                        'data' => $user,
                                        'message' => 'VIEWER_ADDED_FAIL_TEXT',
                                        'detail' => $returnCreate
                                    ];
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
                $return = $assignment->__quickUpdate();
                if ($return['success'] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }

                $refreshAssignment = Assignment::findFirstByUuid($assignment_uuid);
                $pollingCount = 1;
                while ($refreshAssignment->getUpdatedAt() != $refreshAssignment->getUpdatedAt() && $pollingCount < 10) {
                    $pollingCount++;
                    $refreshAssignment = Assignment::findFirstByUuid($assignment_uuid);
                }

                $return = [
                    'success' => true,
                    'message' => 'ASSIGNMENT_EDIT_SUCCESS_TEXT',
                    'data' => $assignment,
                ];
            }
            $this->db->commit();
        }
        end_of_function:

        if ($return['success'] == true && isset($assignment)) {
            ModuleModel::$assignment = $assignment;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification(
                $assignment,
                HistoryModel::TYPE_ASSIGNMENT,
                HistoryModel::HISTORY_UPDATE
            );
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Load list assignment by employee id with pagination
     * @param string $employee_id
     */
    public function loadByEmployeeAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $params = [
            'employee_id' => Helpers::__getRequestValue('employee_id'),
            'limit' => Helpers::__getRequestValue('limit'),
            'active' => true,
            'approval_statuses' => [Assignment::APPROVAL_STATUS_APPROVED, Assignment::APPROVAL_STATUS_IN_APPROVAL],
            'page' => Helpers::__getRequestValue('page'),
            'query' => Helpers::__getRequestValue('query'),
        ];

        $result = Assignment::__findWithFilter($params);
        if ($result['success'] == true) {
            $this->response->setJsonContent([
                'success' => true,
                'message' => '',
                'data' => $result['data'],
                'page' => $result['page'],
                'before' => $result['before'],
                'next' => $result['next'],
                'last' => $result['last'],
                'current' => $result['current'],
                'total_items' => $result['total_items'],
                'total_pages' => $result['total_pages']
            ]);
            $this->response->send();
        } else {
            $this->response->setJsonContent($result);
            $this->response->send();
        }
    }

    /**
     * Load list assignment by employee id with pagination
     * @param string $employee_id
     */
    public function searchActiveAssignmentAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $params = [
            'employee_id' => Helpers::__getRequestValue('employee_id'),
            'limit' => Helpers::__getRequestValue('limit'),
            'has_relocation' => false,
            'active' => true,
            'approval_statuses' => [Assignment::APPROVAL_STATUS_APPROVED, Assignment::APPROVAL_STATUS_IN_APPROVAL],
            'page' => Helpers::__getRequestValue('page'),
            'query' => Helpers::__getRequestValue('query'),
        ];

        $result = Assignment::__findWithFilter($params);
        if ($result['success'] == true) {
            $this->response->setJsonContent([
                'success' => true,
                'message' => '',
                'data' => $result['data'],
                'page' => $result['page'],
                'before' => $result['before'],
                'next' => $result['next'],
                'last' => $result['last'],
                'current' => $result['current'],
                'total_items' => $result['total_items'],
                'total_pages' => $result['total_pages']
            ]);
            $this->response->send();
        } else {
            $this->response->setJsonContent($result);
            $this->response->send();
        }
    }

    /**
     *
     */
    public function addDependentToAssignmentAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $assignment_uuid = Helpers::__getRequestValue('assignment_uuid');
        $dependent_uuid = Helpers::__getRequestValue('dependent_uuid');

        if (Helpers::__isValidUuid($dependent_uuid) == false) {
            $result = ['success' => true, 'message' => 'DEPENDENT_NOT_FOUND_TEXT'];
            goto end;
        }

        if (Helpers::__isValidUuid($assignment_uuid) == false) {
            $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
            goto end;
        }

        $dependent = Dependant::findFirstByUuidCache($dependent_uuid);
        if (!$dependent || $dependent->isDeleted() || $dependent->belongsToGms() == false) {
            $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
            goto end;
        }

        $assignment = Assignment::findFirstByUuid($assignment_uuid);

        if ($assignment) {
            if ($assignment->belongsToGms()) {
                $result = $assignment->addDependant($dependent);
            } else {
                $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
                goto end;
            }
        } else {

            $assignmentObject = RelodayObjectMapHelper::__getObject($assignment_uuid);
            if ($assignmentObject && $assignmentObject->isAssignment()) {
                $result = $assignmentObject->addDependant($dependent);
                $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
            } else {
                $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
                goto end;
            }
        }

        end:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @TODO when create Assignment >>> transform all anssignment_dependant with UUID to ID
     */
    public function removeDependentFromAssignmentAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();


        $this->view->disable();
        $this->checkAjaxPutPost();

        $assignment_uuid = Helpers::__getRequestValue('assignment_uuid');
        $dependent_uuid = Helpers::__getRequestValue('dependent_uuid');

        if (Helpers::__isValidUuid($dependent_uuid) == false) {
            $result = ['success' => true, 'message' => 'DEPENDENT_NOT_FOUND_TEXT'];
            goto end;
        }

        if (Helpers::__isValidUuid($assignment_uuid) == false) {
            $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
            goto end;
        }

        $dependent = Dependant::findFirstByUuidCache($dependent_uuid);
        if (!$dependent || $dependent->isDeleted() || $dependent->belongsToGms() == false) {
            $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
            goto end;
        }

        $assignment = Assignment::findFirstByUuid($assignment_uuid);
        if ($assignment) {
            if ($assignment->belongsToGms()) {
                $result = $assignment->removeDependant($dependent);
            } else {
                $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
                goto end;
            }
        } else {
            $assignmentObject = RelodayObjectMapHelper::__getObject($assignment_uuid);
            if ($assignmentObject && $assignmentObject->isAssignment()) {
                $result = $assignmentObject->removeDependant($dependent);
            } else {
                $result = ['success' => true, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
                goto end;
            }
        }


        end:
        $this->response->setJsonContent($result);
        $this->response->send();

    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function getDependentsAction($assignment_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('view', $this->router->getControllerName());

        $result = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];

        if ($assignment_uuid != '') {
            $assignment = Assignment::findFirstByUuid($assignment_uuid);
            if ($assignment && $assignment->belongsToGms()) {
                $result = [
                    'success' => true,
                    'data' => $assignment->getDependants()
                ];
            }
        }


        $this->response->setJsonContent($result);
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
            $assignment = Assignment::findFirstByUuid($uuid);
            if ($assignment && $assignment->belongsToGms()) {
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

    public function getReportsAction($assignment_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('report');

        $res = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];

        if (!$assignment_uuid || !Helpers::__isValidUuid($assignment_uuid)) {
            goto end_of_function;
        }

        $assignment = Assignment::findFirstByUuid($assignment_uuid);
        if (!$assignment || !$assignment->belongsToGms()) {
            goto end_of_function;
        }

        $listEx = Report::listNeedRemove($assignment_uuid, ReportExt::TYPE_DSP_EXPORT_ASSIGNMENT);

        foreach ($listEx as $reportEx) {
            if ($reportEx instanceof Report) {
                $res = $reportEx->__quickRemove();
            }
        }

        $result = Report::loadList([
            'object_uuid' => $assignment_uuid,
            'type' => ReportExt::TYPE_DSP_EXPORT_ASSIGNMENT
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

    public function exportReportAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('report');

        $res = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'];
        $this->db->begin();

        $assignment_uuid = Helpers::__getRequestValue("object_uuid");
        $params = Helpers::__getRequestValueAsArray("params");

        if (!$assignment_uuid || !Helpers::__isValidUuid($assignment_uuid)) {
            $this->db->rollback();
            goto end_of_function;
        }

        $assignment = Assignment::findFirstByUuid($assignment_uuid);
        if (!$assignment || !$assignment->belongsToGms()) {
            $this->db->rollback();
            goto end_of_function;
        }

        $canExportReport = Report::canExportReport($assignment_uuid, ReportExt::TYPE_DSP_EXPORT_ASSIGNMENT);
        if (!$canExportReport) {
            $res = ['success' => false, 'message' => 'REPORT_QUEUE_LIMIT_EXCEED_PLEASE_WAIT_TEXT'];
            $this->db->rollback();
            goto end_of_function;
        }

        $now = time();
        $uuid = ModuleModel::$company->getUuid();
        $random = new Random();
        $report = new Report();
        $report->setData();
        $report->setCompanyUuid($uuid);
        $report->setName($assignment->getName() . $now . '.xlsx');
        $report->setObjectUuid($assignment_uuid);
        $report->setCreatorUuid(ModuleModel::$user_profile->getUuid());
        $report->setStatus(ReportExt::STATUS_IN_PROCESS);
        $report->setParams(json_encode($params));
        $report->setExpiredAt(date('Y-m-d H:i:s', $now + ReportExt::EXPIRED_TIME));
        $report->setType(ReportExt::TYPE_DSP_EXPORT_ASSIGNMENT);
        $result = $report->__quickCreate();

        if ($result['success']) {
            $dataArray = $assignment->getInfoDetailInArray();
            $dependants = $assignment->getDependants()->toArray();
            $dataArray['dependants'] = $dependants;
            $dataArray['owner_name'] = $assignment->getSimpleProfileOwner() ? $assignment->getSimpleProfileOwner()->getFullname() : '';
            $dataArray['owner_uuid'] = $assignment->getSimpleProfileOwner() ? $assignment->getSimpleProfileOwner()->getUuid() : '';
            $reporter = DataUserMember::__getDataReporter($assignment_uuid, ModuleModel::$company->getId());
            $dataArray['reporter_name'] = $reporter ? $reporter->getFullname() : '';

            $assignment_request = $assignment->getAssignmentRequest();
            if ($assignment_request && $assignment_request->getMessage() != null && $assignment_request->getMessage() != '') {
                $dataArray['request'] = $assignment->getAssignmentRequest();
            }

            if (isset($dataArray['data']['home_hr_phone']) && $dataArray['data']['home_hr_phone']) {
                $dataArray['data']['home_hr_phone'] = '0' . $dataArray['data']['home_hr_phone'];
            }

            if (isset($dataArray['data']['home_hr_phone']) && $dataArray['data']['destination_hr_phone']) {
                $dataArray['data']['destination_hr_phone'] = '0' . $dataArray['data']['destination_hr_phone'];
            }

            $viewers = DataUserMember::__getDataViewers($assignment_uuid, ModuleModel::$company->getId());
            $dataArray['company'] = $dataArray['company']->toArray();

            if (isset($dataArray['employee']['marital_status']) && $dataArray['employee']['marital_status']) {
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

            $dataArray['viewers'] = [];
            foreach ($viewers as $viewer) {
                $dataArray['viewers'][] = $viewer->getFirstname() . ' ' . $viewer->getLastname();
            }

            $commentsArr = [];

            if (isset($params['comment_ids']) && count($params['comment_ids']) > 0) {
                $comments = Comment::find([
                    'conditions' => 'object_uuid = :object_uuid: and id IN ({commentIds:array})',
                    'bind' => [
                        'object_uuid' => $assignment_uuid,
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

            foreach ($dependants as $dependant) {
                $dataArray['employee']['dependants'][] = $dependant['firstname'] . ' ' . $dependant['lastname'];
            }

            $tasks = [];

            if (isset($params['task_ids']) && count($params['task_ids']) > 0) {
                $taskIds = [];

                foreach ($params['task_ids'] as $id => $item) {
                    $taskIds[] = $id;
                }

                $tasks = Task::__loadList([
                    "isArray" => true,
                    "company_id" => ModuleModel::$company->getId(),
                    "object_uuid" => $assignment_uuid,
                    'ids' => $taskIds
                ], true);
            }

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
                'action' => RelodayQueue::ACTION_EXPORT_REPORT_ASSIGNMENT
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

    /**
     * @param $housing
     * @return array|bool
     */
    private function sendHistoryToHrOfRequestAction($assignment, $action = "")
    {
        $hrCompany = $assignment->getCompany();
        $gmsCompany = ModuleModel::$company;
        $currentUser = ModuleModel::$user_profile;

        if ($assignment) {
            $params = [];
            $params['number'] = $assignment ? $assignment->getReference() : '';
            $params['provider_company'] = $gmsCompany ? $gmsCompany->getName() : '';

            $objectArray = [];
            $objectArray['uuid'] = $assignment->getUuid();
            $objectArray['frontend_url'] = $hrCompany->getFrontendUrl() . RelodayUrlHelper::__getAssignmentSuffix($assignment->getUuid());
            $objectArray['number'] = $assignment->getReference();
            $objectArray['frontend_state'] = $assignment->getFrontendState();
            $objectArray['object_label'] = "ASSIGNMENT_TEXT";

            $userArray = $currentUser->toArray();
            $userArray['fullname'] = $currentUser->getFullname();
            $userArray['avatarUrl'] = $currentUser->getAvatarUrl();

            $actionObject = HistoryAction::__getActionObject($action, History::TYPE_ASSIGNMENT);
            $historyObject = new History();
            $historyObject->setUuid(Helpers::__uuid());
            $historyObject->setType(History::TYPE_ASSIGNMENT);
            $historyObject->setUserAction($actionObject->getMessage());
            $historyObject->setUserProfileUuid($currentUser->getUuid());
            $historyObject->setMessage($actionObject->getMessage());
            $historyObject->setObjectUuid($assignment->getUuid());
            $historyObject->setCompanyUuid($hrCompany ? $hrCompany->getUuid() : null);
            $historyObject->setIp($this->request->getClientAddress());
            $historyObject->setObject(json_encode($objectArray));
            $historyObject->setParams(json_encode($params));

            $return = $historyObject->__quickCreate();

            return $return;
        }


        return false;
    }

    /**
     * Load list event by service id
     * @param int $service_id
     */
    public function getEventsOfAssignmentAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $events = Event::find([
            "conditions" => "object_source = :object_source: and is_reminder_available = :yes:",
            "bind" => [
                "object_source" => "assignment",
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
}
