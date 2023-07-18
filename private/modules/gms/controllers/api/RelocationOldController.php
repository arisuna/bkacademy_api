<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\NeedAssessmentItems;
use Reloday\Gms\Models\NeedAssessments;
use Reloday\Gms\Models\NeedAssessmentsRelocation;
use Reloday\Gms\Models\Property;
use \Reloday\Gms\Models\Relocation;
use \Reloday\Gms\Models\RelocationServiceCompany;
use \Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ReminderConfig;
use Reloday\Gms\Models\Service;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\ServiceField;
use Reloday\Gms\Models\ServiceFieldValue;
use Reloday\Gms\Models\SupportedLanguage;
use \Reloday\Gms\Models\Task as Task;
use \Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\TaskWorkflow;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Gms\Models\Workflow;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class RelocationOldController extends BaseController
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

        $data = $this->request->getPost();
        if (count($data) == 1 || count($data) == 0) {
            $data = $this->request->getJsonRawBody(true);
        }

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

        if (!($assignment_id > 0)) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $data];
            $basic = isset($data['assignment']['basic']) ? $data['assignment']['basic'] : false;
            $destination = isset($data['assignment']['destination']) ? $data['assignment']['destination'] : false;
            $employee = isset($data['employee']) ? $data['employee'] : false;

            if ($basic && $destination && $employee) {
                //
                $dataAssignment = [
                    'basic' => (array)$basic,
                    'destination' => (array)$destination,
                    'employee' => (array)$employee,
                ];

                $resultCreate = $this->createAssignment($dataAssignment);
                if ($resultCreate['success'] == true) {
                    $assignment = $resultCreate['data'];
                } else {
                    $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $data];
                }
            } else {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $data];
                goto end_of_function;
            }
        } else {
            $assignment = Assignment::findFirstById($assignment_id);
        }

        if (!$assignment) {
            $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'raw' => $data];
            goto end_of_function;
        }

        if ($assignment &&
            $assignment->belongsToGMS() &&
            $assignment->canCreateRelocation() == true) {

            $dataRelocation = [
                'hr_company_id' => $assignment->getCompanyId(),
                'assignment_id' => $assignment->getId()
            ];

            $this->db->begin();

            $relocation = new Relocation();
            $resultModel = $relocation->__save($dataRelocation);

            if ($resultModel['success'] == true) {

                $service_pack_id = Helpers::__getRequestValue("service_pack_id");

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

                /*
                if ($service_pack_id > 0) {
                    $resultAddServices = $relocation->addServicesFromServicePack($service_pack_id);
                    if ($resultAddServices['success'] == false) {
                        $return['detail'] = "Can not save service from service pack";
                        $this->db->rollback();
                        goto end_of_function;
                    }
                }
                */
                $services = Helpers::__getRequestValueAsArray("services");
                $servicesArray = [];
                foreach ($services as $service) {
                    if (isset($service['selected']) && $service['selected'] == true) {
                        $servicesArray[] = $service;
                    }
                }

                $resultAddServices = $relocation->addServices($servicesArray);
                if ($resultAddServices['success'] == false) {
                    $return = $resultAddServices;
                    $return['detail'] = "Can not save services";
                    $this->db->rollback();
                    goto end_of_function;
                }


                /** update assignment */
                $assignment->setCreateRelocationStatus(Assignment::CREATE_RELOCATION_DONE);
                $resultUpdateAssignment = $assignment->__quickUpdate();
                if ($resultUpdateAssignment['success'] == false) {
                    $return = $resultUpdateAssignment;
                    $return['detail'] = "Can not update assignment";
                    $this->db->rollback();
                    goto end_of_function;
                }

                $this->db->commit();

                $return = [
                    'success' => true,
                    'message' => 'RELOCATION_CREATE_SUCCESS_TEXT',
                    'data' => $relocation,
                    'services' => $services,
                ];

            } else {
                $return = $resultModel;
            }
        } else {
            if ($assignment->canHaveRelocation()) {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_APPROVED_TEXT', 'data' => $data];
            }
            if ($assignment->hasRelocation()) {
                $return = ['success' => false, 'message' => 'ASSIGNMENT_HAVE_ACTIVE_RELOCATION_TEXT', 'data' => $data];
            }
        }


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @return [type] [description]
     */
    public function detailAction($relocation_id = '')
    {

        $this->view->disable();
        $this->checkAjaxGet();

        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if (is_numeric($relocation_id) && $relocation_id > 0) {
            $relocation = Relocation::findFirstById($relocation_id);
            if ($relocation && $relocation->belongsToGms() && $relocation->checkMyViewPermission()) {
                $data = [];
                $data = $relocation->toArray();
                $company = $relocation->getCompany();
                $data['company'] = $company;
                $data['company_id'] = $company->getId();
                $data['services'] = $relocation->getServiceCompany();
                $data['employee'] = $relocation->getEmployee();
                $data['gmsFolderUuid'] = $relocation->getGmsFolderUuid();
                $data['employeeFolderUuid'] = $relocation->getEmployeeFolderUuid();
                //$data['start_date']   = Helpers::formatdateDMY( $relocation->getStartDate() );
                //$data['end_date']     = Helpers::formatdateDMY( $relocation->getEndDate() );
                $return = ['success' => true, 'data' => $data];
            }
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

            if ($relocation && $relocation->belongsToGms()) {
                $relocationData = $relocation->toArray();
                $company = $relocation->getCompany();

                $relocationData['company'] = $company;
                $relocationData['company_id'] = $company->getId();
                $relocationData['service_pack'] = $relocation->getServicePack();
                $relocationData['service_pack_name'] = $relocation->getServicePack() ? $relocation->getServicePack()->getName() : '';
                $relocationData['service_count'] = $relocation->countRelocationServiceCompanies();
                $relocationData['gmsFolderUuid'] = $relocation->getGmsFolderUuid();
                $relocationData['employeeFolderUuid'] = $relocation->getEmployeeFolderUuid();
                /* relocation_service_company */
                //$relocation_service_companies = $relocation->getActiveRelocationServiceCompany();
                //$services_data = [];
                //$assignment = $relocation->getAssignment();
                // $employee = $relocation->getEmployee();

                $return = ['success' => true, 'data' => $relocationData];
            } else {
                $return['detail'] = 'CONTRACT_DESACTIVATED_TEXT';
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function get_assignmentAction($relocation_uuid = null)
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

                    $return = [
                        'success' => true,
                        'data' => $assignmentArray,
                        'basic' => $basicArray,
                        'destination' => $destinationArray,
                    ];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function get_employeeAction($relocation_uuid = null)
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
                    $employeeArray = $employee->toArray();
                    $employeeArray['company_name'] = ($employee->getCompany()) ? $employee->getCompany()->getName() : "";
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

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $relocation = Relocation::findFirstByUuid($uuid);

            if ($relocation && $relocation->belongsToGms()) {

                $this->db->begin();
                $result = $relocation->__save();
                if ($result['success'] == true) {
                    $services = Helpers::__getRequestValueAsArray("services");
                    $servicesArray = [];
                    if (is_array($services) && count($services) > 0) {
                        foreach ($services as $service) {
                            if (isset($service['selected']) && $service['selected'] == true) {
                                $servicesArray[] = $service;
                            }
                        }
                        $resultAddServices = $relocation->addServices($servicesArray);
                        if ($resultAddServices['success'] == false) {
                            $return['detail'] = "Can not save services";
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }
                    $this->db->commit();
                    $return = [
                        'success' => true,
                        'message' => 'RELOCATION_EDIT_SUCCESS_TEXT',
                        'data' => $result,
                    ];
                } else {
                    $return = $result;
                }
            }
        }

        end_of_function:
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
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * generate an relocation from assigment
     * @return [type] [description]
     */
    public function generateAction($assignment_id = '')
    {

    }

    /** list of tasks of relocation_uuid */
    public function get_tasksAction($relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($relocation_uuid != '') {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->belongsToGms()) {
                $tasks = Task::__loadList([
                    "object_uuid" => $relocation->getUuid(),
                ]);

                $return = [
                    'success' => true,
                    'data' => $tasks
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
    public function get_all_membersAction($relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($relocation_uuid != '') {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->belongsToGms()) {

                $members = UserProfile::getWorkers($relocation->getHrCompanyId());
                //all active HR MEMBERS of HR
                //all active GMS_MEMBERS

                $users_array = [];
                foreach ($members as $user) {
                    /* check selected */
                    $users_array[$user->getId()] = $user->toArray();
                    $users_array[$user->getId()]['avatar'] = $user->getAvatar();
                    $users_array[$user->getId()]['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
                    $users_array[$user->getId()]['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;
                    /** check selected  */
                    $viewer_uuids = DataUserMember::getViewersUuids($relocation->getUuid());
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
     * [set_viewerAction description]
     * @return [type] [description]
     */
    public function set_viewerAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $action = "edit";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $data = $this->request->getJsonRawBody();

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if (isset($data->relocation_uuid) && $data->relocation_uuid != '' &&
            isset($data->member_uuid) && $data->member_uuid != ''
        ) {

            $relocation = Relocation::findFirstByUuid($data->relocation_uuid);
            if ($relocation && $relocation->belongsToGms()) {

                $user = UserProfile::findFirstByUuid($data->member_uuid);

                //User of GMS or USER of HR
                if ($user->getCompanyId() == ModuleModel::$company->getId() ||
                    $user->getCompanyId() == $relocation->getHrCompanyId()
                ) {

                    $selected = isset($data->selected) ? $data->selected : null;


                    if ($selected === true) {
                        $data_user_member = DataUserMember::findFirst([
                            "conditions" => "object_uuid = :object_uuid: AND user_profile_id = :user_profile_id: AND member_type_id = :member_type_id:",
                            'bind' => [
                                'object_uuid' => $relocation->getUuid(),
                                'user_profile_id' => $user->getId(),
                                'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                            ]
                        ]);

                        if (!$data_user_member) {
                            $data_user_member_manager = new DataUserMember();
                            $model = $data_user_member_manager->__save([
                                'object_uuid' => $relocation->getUuid(),
                                'user_profile_id' => $user->getId(),
                                'user_login_id' => $user->getUserLoginId(),
                                'object_name' => $relocation->getSource(),
                                'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                            ]);
                            if ($model instanceof DataUserMember) {
                                $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_ADDED_SUCCESS_TEXT'];
                            } else {
                                $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_ADDED_FAIL_TEXT'];
                            }
                        }
                    } elseif ($selected === false) {
                        $data_user_member = DataUserMember::findFirst([
                            "conditions" => "object_uuid = :object_uuid: AND user_profile_id = :user_profile_id: AND member_type_id = :member_type_id:",
                            'bind' => [
                                'object_uuid' => $relocation->getUuid(),
                                'user_profile_id' => $user->getId(),
                                'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                            ]
                        ]);
                        $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_REMOVE_SUCCESS_TEXT'];
                        if ($data_user_member) {
                            if (!$data_user_member->delete()) {
                                $return = ['success' => false, 'data' => [], 'message' => 'VIEWER_REMOVE_FAIL_TEXT'];
                            }
                        }
                    } else {
                        die('NOT OK');
                    }
                }
            }
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
                $return = $relocation->__quickRemove();
                if ($return['success'] == true) $return['message'] = "RELOCATION_DELETE_SUCCESS_TEXT";
                else $return['message'] = "RELOCATION_DELETE_FAIL_TEXT";
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function get_simple_activeAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $search = Relocation::loadList();
        //load assignment list
        $results = [];


        if ($search['success'] == true) {
            $relocations = $search['data'];

            if (count($relocations)) {
                foreach ($relocations as $relocation) {

                    $results[] = [
                        'id' => $relocation->getId(),
                        'uuid' => $relocation->getUuid(),
                        'identify' => $relocation->getIdentify(),
                        'employee_name' => $relocation->getEmployee()->getFirstname() . " " . $relocation->getEmployee()->getLastname(),
                        'company_name' => $relocation->getEmployee()->getCompany()->getName(),
                        'host_country' => '',
                        'start_date' => $relocation->getStartDate(),
                        'end_date' => $relocation->getEndDate(),
                        'status' => $relocation->getStatus()
                    ];
                }
            }
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        return $this->response->send();
    }

    /**
     * @param string $relocation_uuid
     * @return mixed
     */
    public function quickviewAction($relocation_uuid = '')
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
     * @return mixed
     */
    public function simplelistAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $results = Relocation::simpleList();
        //load assignment list

        $this->response->setJsonContent($results);
        return $this->response->send();
    }

    /**
     * @param $relocation_uuid
     * @return mixed
     */
    public function get_servicesAction($relocation_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());


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
                'id' => $service->getId(),
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
     * @param $user_profile_uuid
     */
    public function workerAction($user_profile_uuid)
    {
        $this->view->disable();

        $this->checkAjaxPutGet();
        $this->checkAcl('index');

        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = $user_profile_uuid != '' ? $user_profile_uuid : (isset($data->user_profile_uuid) && $data->user_profile_uuid != '' ? $data->user_profile_uuid : null);
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }

        $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND', 'count' => 0];
        $di = $this->di;
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
            $queryBuilder->distinct(true);

            $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Relocation.uuid', 'DataUserMember');
            $queryBuilder->where("DataUserMember.user_profile_uuid = :user_profile_uuid:", [
                'user_profile_uuid' => $user_profile_uuid
            ]);

            /*
            $queryBuilder->andWhere("Relocation.status = :status:", [
                'status' => Relocation::STATUS_ONGOING
            ]);
            */

            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 100,
                'page' => 1
            ]);

            $paginate = $paginator->getPaginate();
            $relocationsArray = [];
            foreach ($paginate->items as $item) {
                $ass_destination = $item->getAssignment()->getAssignmentDestination();
                $relocationsArray[] = [
                    'identify' => $item->getIdentify(),
                    'id' => $item->getId(),
                    'uuid' => $item->getUuid(),
                    'status' => $item->getStatus(),
                    'state' => $item->getFrontendState(),
                    'employee_uuid' => $item->getEmployee()->getUuid(),
                    'employee_name' => $item->getEmployee()->getFirstname() . " " . $item->getEmployee()->getLastname(),
                    'company_name' => $item->getEmployee()->getCompany()->getName(),
                    'host_country' => ($ass_destination && $ass_destination->getDestinationCountry() ? $ass_destination->getDestinationCountry()->getName() : ""),
                    'worker_status_label' => $item->getWorkerStatus($user_profile_uuid)
                ];
            }
            $return = ['success' => true, 'data' => $relocationsArray, 'count' => $paginate->total_items];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
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
    public function getAllServicesAction($relocation_uuid = null)
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAcl('index', $this->router->getControllerName());

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        if ($relocation_uuid != '' && Helpers::__isValidUuid($relocation_uuid)) {
            $relocation = Relocation::findFirstByUuid($relocation_uuid);
            if ($relocation && $relocation->checkCompany()) {

                $servicesRelocation = $relocation->getActiveRelocationServiceCompany();
                $servicesCompany = ServiceCompany::getFullListOfMyCompany();
                $servicesArray = [];
                foreach ($servicesRelocation as $serviceRelocationItem) {
                    $serviceCompany = ServiceCompany::findFirstById($serviceRelocationItem->getServiceCompanyId());
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
                        if ($serviceRelocationItem->getServiceProviderCompany()) {
                            $item['svp_company_id'] = $serviceRelocationItem->getServiceProviderCompany()->getId();
                            $item['svp_company_uuid'] = $serviceRelocationItem->getServiceProviderCompany()->getUuid();
                            $item['svp_name'] = $serviceRelocationItem->getServiceProviderCompany()->getName();
                        }
                        $item['progress'] = $serviceRelocationItem->getEntityProgressValue();
                    } else {
                        $item['selected'] = false;
                        $item['progress'] = 0;
                    }
                    $item['frontend_state'] = $serviceRelocationItem->getFrontendState();
                    $item['relocation_uuid'] = $serviceRelocationItem->getRelocationId();
                    $item['relocation_service_company_uuid'] = $serviceRelocationItem->getUuid();
                    $item['description'] = $serviceRelocationItem->getName();
                    if ($serviceCompany->isArchived()) {
                        if (isset($item['relocation_service_company_uuid']) &&
                            $item['relocation_service_company_uuid'] != "" &&
                            Helpers::__isValidUuid($item['relocation_service_company_uuid']) &&
                            isset($item['selected']) && $item['selected'] == true
                        ) {
                            $servicesArray[$serviceRelocationItem->getUuid()] = $item;
                        }
                    } else {
                        $servicesArray[$serviceRelocationItem->getUuid()] = $item;
                    }
                }

                $this->response->setJsonContent([
                    'success' => true,
                    'data' => ($servicesArray)
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
                            $need_assessment_array[$item->getId()]['relocation_service_company_id'] = $request->getRelocationServiceCompanyId();
                            $need_assessment_array[$item->getId()]['relocation_service_company_uuid'] = $request->getRelocationServiceCompany()->getUuid();
                        } else {
                            $need_assessment_array[$item->getId()]['request_uuid'] = '';
                            $need_assessment_array[$item->getId()]['request_status'] = 0;
                            $need_assessment_array[$item->getId()]['sent_on'] = '';
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
        $this->checkAcl('index');

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['company_id'] = Helpers::__getRequestValue('company_id');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['length'] = Helpers::__getRequestValue('length');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['simple'] = Helpers::__getRequestValue('simple');
        $params['active'] = Helpers::__getRequestValue('active');
        $params['page'] = Helpers::__getRequestValue('page');
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
        $ordersConfig = Helpers::__getDataTableOrderConfig();

        $return = Relocation::__findWithFilter($params, $ordersConfig);

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
     *
     */
    public function createAssignment($data = [])
    {
        $assignmentManager = new Assignment();
        $data['app_id'] = ModuleModel::$user_login->getAppId();
        $assignmentModelResult = $assignmentManager->__save($data);

        if ($assignmentModelResult instanceof Assignment) {
            $assignmentModelResult->addReporter(ModuleModel::$user_profile);
            if ($assignmentModelResult->getAssignmentDestination() && $assignmentModelResult->getAssignmentDestination()->getHrOwnerProfile()) {
                $assignmentModelResult->addOwner($assignmentModelResult->getAssignmentDestination()->getHrOwnerProfile());
            }

            if (isset($data['upload_document']['attachments']) && count($data['upload_document']['attachments']) > 0) {
                $attachments = $data['upload_basic']['attachments'];
                $resultAttachment = MediaAttachment::__create_attachments($assignmentModelResult, $attachments, "documents");
            }

            if (isset($data['attachments']) && count($data['attachments']) > 0) {
                $attachments = $data['attachments'];
                $resultAttachment = MediaAttachment::__create_attachments($assignmentModelResult, $attachments, "documents");
            }

            $return = [
                'success' => true,
                'message' => 'ASSIGNMENT_CREATE_SUCCESS_TEXT',
                'data' => $assignmentModelResult
            ];
        } else {
            $return = $assignmentModelResult;
        }

        return $return;
    }


    /**
     *
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

                $this->db->begin();
                $employee = $relocation->getEmployee();
                $user_login_password = '';

                if ($employee) {

                    $userLogin = $employee->getUserLogin();
                    if (!$userLogin) {
                        $user_login_password = Helpers::password(16);
                        $userLogin = new UserLogin();
                        $userLogin->setData([
                            'email' => $employee->getWorkemail(),
                            'password' => $user_login_password,
                            'app_id' => $employee->getCompany()->getAppId(),
                            'status' => UserLogin::STATUS_ACTIVATED,
                            'user_group_id' => UserLogin::USER_GROUP_EE,
                        ]);
                        $resultSaveUserLogin = $userLogin->__quickCreate();
                        if ($resultSaveUserLogin['success'] == false) {
                            $this->db->rollback();
                            $return = $resultSaveUserLogin;
                            goto end_of_function;
                        }
                    }

                    if ($userLogin) {
                        if ($user_login_password != '') {
                            $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                            $url_login = $userLogin->getApp()->getEmployeeUrl();
                            $dataArray = [
                                'action' => "sendMail",
                                'to' => $userLogin->getEmail(),
                                'email' => $userLogin->getEmail(),
                                'user_login' => $userLogin->getEmail(),
                                'url_login' => $url_login,
                                'user_password' => $user_login_password,
                                'user_name' => $employee->getFullname(),
                                'templateName' => EmailTemplateDefault::SEND_NEW_PASSWORD,
                                'language' => ModuleModel::$system_language
                            ];
                            $resultCheck = $beanQueue->addQueue($dataArray);
                            $return['resultCheck'] = $resultCheck;
                        }
                    }

                    $relocation->setActive(Employee::ACTIVE_YES);
                    $relocation->setAssigneeIsInvited(Employee::ACTIVE_YES);
                    $resultSaveRelocation = $relocation->__quickUpdate();
                    if ($resultSaveRelocation['success'] == false) {
                        $this->db->rollback();
                        $return = $resultSaveUserLogin;
                        goto end_of_function;
                    }

                    $this->db->commit();
                    $return['success'] = true;
                    $return['message'] = 'INVITE_ASSIGNEE_SUCCESS_TEXT';
                    $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                    $url_login = $userLogin->getApp()->getEmployeeUrl();
                    $dataArray = [
                        'action' => "sendMail",
                        'to' => $userLogin->getEmail(),
                        'email' => $userLogin->getEmail(),
                        'url_login' => $url_login,
                        'user_password' => $user_login_password,
                        'user_name' => $employee->getFullname(),
                        'templateName' => EmailTemplateDefault::INVITE_ASSIGNEE,
                        'language' => ModuleModel::$system_language
                    ];
                    $resultCheck = $beanQueue->addQueue($dataArray);
                    $return['resultCheck'] = $resultCheck;
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
    public function desactivateServiceAction($relocation_service_company_uuid = '')
    {

        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclEdit();

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($relocation_service_company_uuid == '') {
            $relocation_service_company_uuid = $this->request->get('uuid');
        }

        if ($relocation_service_company_uuid != '' && Helpers::__isValidUuid($relocation_service_company_uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() == true
            ) {
                $this->db->begin();
                $return = $relocation_service_company->archive();
                if ($return['success'] == true) {
                    $resultDelete = DataUserMember::__removeMember($relocation_service_company->getUuid());
                    if ($resultDelete['success'] == false) {
                        $this->db->rollback();
                        $return['success'] = false;
                        $return['message'] = 'REMOVE_SERVICE_FAIL_TEXT';
                        $return['detail'] = $resultDelete;
                        goto end_of_function;
                    }
                    $this->db->commit();
                    $return['success'] = true;
                    $return['message'] = 'REMOVE_SERVICE_SUCCESS_TEXT';
                } else {
                    $this->db->rollback();
                    $return['success'] = false;
                    $return['message'] = 'REMOVE_SERVICE_FAIL_TEXT';
                }
            }
        }
        end_of_function:
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

        $relocation_service_company_uuid = isset($data->relocation_service_company_uuid) && $data->relocation_service_company_uuid != '' ?
            $data->relocation_service_company_uuid : '';

        $relocation_uuid = isset($data->relocation_uuid) && $data->relocation_uuid != '' ?
            $data->relocation_uuid : '';

        $service_company_uuid = isset($data->uuid) && $data->uuid != '' ?
            $data->uuid : '';

        $relocationServiceCompany = false;
        if ($relocation_service_company_uuid != '') {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);
        }


        if ($relocationServiceCompany && $relocationServiceCompany->belongsToGms() == true) {

            if ($relocationServiceCompany->getStatus() == RelocationServiceCompany::STATUS_ARCHIVED) {

                $this->db->begin();

                $return = $relocationServiceCompany->reactivate();

                if ($return['success'] == true) {
                    $return['message'] = 'ADD_SERVICE_SUCCESS_TEXT';

                    $relocation = $relocationServiceCompany->getRelocation();
                    $ownerProfileDefault = $relocation->getDataOwner();
                    //add OWNER
                    if (!$relocationServiceCompany->getDataOwner() && $ownerProfileDefault) {
                        $res = $relocationServiceCompany->addOwner($ownerProfileDefault);
                        if ($res['success'] = false) {
                            $this->db->rollback();
                            $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                            goto end_of_function;
                        }
                    }

                    $reporterProfileDefault = $relocation->getDataReporter();
                    //add REPORTER
                    if (!$relocationServiceCompany->getDataReporter() && $reporterProfileDefault) {
                        $res = $relocationServiceCompany->addReporter($reporterProfileDefault);
                        if ($res['success'] = false) {
                            $this->db->rollback();
                            $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                            goto end_of_function;
                        }
                    }

                    //add OWNER
                    $serviceCompany = $relocationServiceCompany->getServiceCompany();
                    $attachments = MediaAttachment::__load_attachments($serviceCompany->getUuid());
                    if (count($attachments) > 0) {
                        foreach ($attachments as $attachment) {
                            $res = MediaAttachment::__create_attachment_from_uuid($relocationServiceCompany->getUuid(), $attachment);
                            if ($res['success'] = false) {
                                $this->db->rollback();
                                $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                                goto end_of_function;
                            }
                        }
                    };
                    //add OWNER
                    $this->db->commit();
                    $return['owner'] = $ownerProfileDefault;
                    $return['reporter'] = $reporterProfileDefault;
                    $return['message'] = 'ADD_SERVICE_SUCCESS_TEXT';

                } else {
                    $this->db->rollback();
                    $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                }
            } else {
                $return = ['success' => true, 'message' => 'ADD_SERVICE_SUCCESS_TEXT'];
            }
        } else {
            // create new relocation
            if ($relocation_uuid != '' && $service_company_uuid != '') {
                $relocation = Relocation::findFirstByUuid($relocation_uuid);
                if ($relocation && $relocation->checkCompany()) {
                    $serviceCompany = ServiceCompany::findFirstByUuid($service_company_uuid);
                    if ($serviceCompany && $serviceCompany->belongsToGms()) {
                        $this->db->begin();
                        if (isset($data->dependant_id)) {
                            $return = RelocationServiceCompany::__createNew($relocation, $serviceCompany, $data->description, $data->dependant_id);
                        } else {
                            $return = RelocationServiceCompany::__createNew($relocation, $serviceCompany, $data->description, null);
                        }


                        if ($return['success'] == true) {
                            $relocationServiceCompany = $return['data'];
                            $ownerProfileDefault = $relocation->getDataOwner();
                            //addOwner
                            if ($ownerProfileDefault) {
                                $res = $relocationServiceCompany->addOwner($ownerProfileDefault);
                                if ($res['success'] = false) {
                                    $this->db->rollback();
                                    $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }

                            $reporterProfileDefault = $relocation->getDataReporter();
                            //addReporter
                            if ($reporterProfileDefault) {
                                $res = $relocationServiceCompany->addReporter($reporterProfileDefault);
                                if ($res['success'] = false) {
                                    $this->db->rollback();
                                    $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                                    goto end_of_function;
                                }
                            }

                            //add attachment from service
                            $attachments = MediaAttachment::__load_attachments($serviceCompany->getUuid());
                            if (count($attachments) > 0) {
                                foreach ($attachments as $attachment) {
                                    $res = MediaAttachment::__create_attachment_from_uuid($relocationServiceCompany->getUuid(), $attachment);
                                    if ($res['success'] = false) {
                                        $this->db->rollback();
                                        $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                                        goto end_of_function;
                                    }
                                }
                            };

                            // prefill fields if needed
                            if ($serviceCompany->getServiceId() == Service::FUNITURE_RENTAL
                                || $serviceCompany->getServiceId() == Service::CHECKIN_SERVICE
                                || $serviceCompany->getServiceId() == Service::LEASE_CLOSEDOWN_SERVICE
                                || $serviceCompany->getServiceId() == Service::LEASE_RENEWAL_SERVICE
                                || $serviceCompany->getServiceId() == Service::SETTLING_IN_SERVICE
                                || $serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE
                                || $serviceCompany->getServiceId() == Service::TENANCY_MANAGEMENT_SERVICE
                                || $serviceCompany->getServiceId() == Service::DRIVING_LICENSE_SERVICE
                                || $serviceCompany->getServiceId() == Service::PARTNER_SUPPORT_SERVICE
                                || $serviceCompany->getServiceId() == Service::SCHOOL_SEARCH_SERVICE
                                || $serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE
                                || $serviceCompany->getServiceId() == Service::TAX_SERVICE
                                || $serviceCompany->getServiceId() == Service::CHECKOUT_SERVICE) {
                                $fields = $serviceCompany->getServiceFields([
                                    'order' => 'position ASC',
                                ]);

                                //get selected property if needed
                                if ($serviceCompany->getServiceId() == Service::FUNITURE_RENTAL
                                    || $serviceCompany->getServiceId() == Service::CHECKIN_SERVICE
                                    || $serviceCompany->getServiceId() == Service::LEASE_CLOSEDOWN_SERVICE
                                    || $serviceCompany->getServiceId() == Service::LEASE_RENEWAL_SERVICE
                                    || $serviceCompany->getServiceId() == Service::SETTLING_IN_SERVICE
                                    || $serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE
                                    || $serviceCompany->getServiceId() == Service::TENANCY_MANAGEMENT_SERVICE
                                    || $serviceCompany->getServiceId() == Service::CHECKOUT_SERVICE) {
                                    $servicesRelocation = $relocation->getRelocationServiceCompanies();
                                    foreach ($servicesRelocation as $serviceRelocationItem) {
                                        $serviceCompanyItem = $serviceRelocationItem->getServiceCompany();
                                        if ($serviceCompanyItem->getServiceId() == Service::HOME_SEARCH_SERVICE) {
                                            $homeSearchServiceOfRelocation = $serviceRelocationItem;
                                            break;
                                        }
                                    }

                                    if ($homeSearchServiceOfRelocation instanceof RelocationServiceCompany) {
                                        $property = $homeSearchServiceOfRelocation->getSelectedProperty();
                                    }
                                }
                                // get assignment
                                if ($serviceCompany->getServiceId() == Service::TAX_SERVICE
                                    || $serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                                    $assignment = $relocation->getAssignment();
                                }
                                $assignee = $relocation->getEmployee();
                                foreach ($fields as $field) {
                                    $isAutoFill = false;

                                    switch ($field->getCode()) {
                                        case ServiceField::DRIVING_LICENCE_NUMBER:
                                            if ($serviceCompany->getServiceId() == Service::DRIVING_LICENSE_SERVICE) {
                                                $isAutoFill = true;
                                                $value = $assignee->getDrivingLicenceNumber();
                                            }
                                            break;
                                        case ServiceField::EXPIRY_DATE:
                                            if ($serviceCompany->getServiceId() == Service::DRIVING_LICENSE_SERVICE) {
                                                $isAutoFill = true;
                                                $value = strtotime($assignee->getDrivingLicenceExpiryDate());
                                            }
                                            break;
                                        case ServiceField::LANDLORD:
                                            if ($serviceCompany->getServiceId() == Service::FUNITURE_RENTAL
                                                || $serviceCompany->getServiceId() == Service::CHECKIN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_CLOSEDOWN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_RENEWAL_SERVICE
                                                || $serviceCompany->getServiceId() == Service::SETTLING_IN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE
                                                || $serviceCompany->getServiceId() == Service::TENANCY_MANAGEMENT_SERVICE
                                                || $serviceCompany->getServiceId() == Service::CHECKOUT_SERVICE) {

                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getLandlordSvpId();
                                                }
                                            }
                                            break;
                                        case ServiceField::PROPERTY_NAME:
                                            if ($serviceCompany->getServiceId() == Service::FUNITURE_RENTAL
                                                || $serviceCompany->getServiceId() == Service::CHECKIN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_CLOSEDOWN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_RENEWAL_SERVICE
                                                || $serviceCompany->getServiceId() == Service::SETTLING_IN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE
                                                || $serviceCompany->getServiceId() == Service::TENANCY_MANAGEMENT_SERVICE
                                                || $serviceCompany->getServiceId() == Service::CHECKOUT_SERVICE) {

                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getUuid();
                                                }
                                            }
                                            break;
                                        case ServiceField::PROPERTY_ADDRESS:
                                            if ($serviceCompany->getServiceId() == Service::FUNITURE_RENTAL
                                                || $serviceCompany->getServiceId() == Service::CHECKIN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_CLOSEDOWN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::SETTLING_IN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE
                                                || $serviceCompany->getServiceId() == Service::TENANCY_MANAGEMENT_SERVICE) {

                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getAddress1();
                                                }
                                            }
                                            break;
                                        case ServiceField::PARTNER_NAME:
                                            if ($serviceCompany->getServiceId() == Service::PARTNER_SUPPORT_SERVICE) {
                                                $dependant = $relocationServiceCompany->getDependant();
                                                $isAutoFill = true;
                                                $value = $dependant->getFirstname() . " " . $dependant->getLastname();

                                            }
                                            break;
                                        case ServiceField::PARTNER_PHONE:
                                            if ($serviceCompany->getServiceId() == Service::PARTNER_SUPPORT_SERVICE) {
                                                $dependant = $relocationServiceCompany->getDependant();
                                                $isAutoFill = true;
                                                $value = $dependant->getHomePhone();
                                                if ($value == null) $value = $dependant->getWorkPhone();
                                                if ($value == null) $value = $dependant->getMobilePhone();

                                            }
                                            break;
                                        case ServiceField::CHILD_NAME:
                                            if ($serviceCompany->getServiceId() == Service::SCHOOL_SEARCH_SERVICE) {
                                                $dependant = $relocationServiceCompany->getDependant();
                                                $isAutoFill = true;
                                                $value = $dependant->getFirstname() . " " . $dependant->getLastname();
                                            }
                                            break;
                                        case ServiceField::CHILD_BIRTHDATE:
                                            if ($serviceCompany->getServiceId() == Service::SCHOOL_SEARCH_SERVICE) {
                                                $dependant = $relocationServiceCompany->getDependant();
                                                $isAutoFill = true;
                                                $value = strtotime($dependant->getBirthDate());
                                            }
                                            break;
                                        case ServiceField::DEPOSIT_AMOUNT:
                                            if ($serviceCompany->getServiceId() == Service::LEASE_CLOSEDOWN_SERVICE) {
                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getDepositAmount() . '#' . $property->getDepositCurrency();
                                                }
                                            }
                                            break;
                                        case ServiceField::RENT_AMOUNT_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE) {
                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getRentAmount() . '#' . $property->getRentCurrency();
                                                }
                                            }
                                            break;
                                        case ServiceField::RENT_AMOUNT_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE) {
                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getRentAmount();
                                                }
                                            }
                                            break;
                                        case ServiceField::PERIOD:
                                            if ($serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE) {
                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getRentPeriod();
                                                }
                                            }
                                            break;
                                        case ServiceField::AGENT_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::TENANCY_MANAGEMENT_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_CLOSEDOWN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_NEGOTIATION_SERVICE
                                                || $serviceCompany->getServiceId() == Service::SETTLING_IN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::CHECKOUT_SERVICE
                                                || $serviceCompany->getServiceId() == Service::FUNITURE_RENTAL
                                                || $serviceCompany->getServiceId() == Service::CHECKIN_SERVICE
                                                || $serviceCompany->getServiceId() == Service::LEASE_RENEWAL_SERVICE) {
                                                if ($property instanceof Property) {
                                                    $isAutoFill = true;
                                                    $value = $property->getAgent() ? $property->getAgent()->getId() : '';
                                                }
                                            }
                                            break;
                                        case ServiceField::HOST_TOWN_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::TAX_SERVICE) {
                                                $isAutoFill = true;
                                                $value = $assignment->getAssignmentDestination()->getDestinationCity();
                                            }
                                            break;
                                        case ServiceField::HOST_COUNTRY_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::TAX_SERVICE) {
                                                $isAutoFill = true;
                                                $value = $assignment->getAssignmentDestination()->getDestinationCountryId();
                                            }
                                            break;
                                        case ServiceField::HOSTING_MANAGER_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::TAX_SERVICE) {
                                                $isAutoFill = true;
                                                $value = $assignment->getAssignmentBasic()->getHomeHrName();
                                            }
                                            break;
                                        case ServiceField::HOSTING_HR_MANAGER_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::TAX_SERVICE) {
                                                $isAutoFill = true;
                                                if ($assignment->getAssignmentDestination()->getHrOwnerProfile() instanceof UserProfile) {
                                                    $value = $assignment->getAssignmentDestination()->getHrOwnerProfile()->getName();
                                                }
                                            }
                                            break;
                                        case ServiceField::PROPOSED_JOB_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::TAX_SERVICE
                                                || ($serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE &&
                                                    $relocationServiceCompany->getDependantId() == 0)) {
                                                $isAutoFill = true;
                                                $value = $assignment->getAssignmentDestination()->getDestinationJobTitle();
                                            }
                                            break;
                                        case ServiceField::PASSPORT_N_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                                                if ($relocationServiceCompany->getDependantId() > 0) {
                                                    $dependant = $relocationServiceCompany->getDependant();
                                                    $isAutoFill = true;
                                                    $value = $dependant->getPassportNumber();
                                                } else {
                                                    $value = $assignee->getPassportNumber();
                                                    $isAutoFill = true;
                                                }
                                            }
                                            break;
                                        case ServiceField::PASSPORT_EXPIRY_DATE_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                                                if ($relocationServiceCompany->getDependantId() > 0) {
                                                    $dependant = $relocationServiceCompany->getDependant();
                                                    $isAutoFill = true;
                                                    $value = strtotime($dependant->getPassportExpiryDate());
                                                } else {
                                                    $value = strtotime($assignee->getPassportExpiryDate());
                                                    $isAutoFill = true;
                                                }
                                            }
                                            break;
                                        case ServiceField::APPLICANT_NAME_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                                                if ($relocationServiceCompany->getDependantId() > 0) {
                                                    $dependant = $relocationServiceCompany->getDependant();
                                                    $isAutoFill = true;
                                                    $value = $dependant->getFirstname() . " " . $dependant->getLastname();
                                                } else {
                                                    $value = $assignee->getFirstname() . " " . $assignee->getLastname();
                                                    $isAutoFill = true;
                                                }
                                            }
                                            break;
                                        case ServiceField::N_FAMILY_ACC_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                                                $dependants = $assignment->getDependants();
                                                $i = 1;
                                                if (count($dependants) > 0) {
                                                    foreach ($dependants as $dependant) {
                                                        if ($dependant->getStatus() == Dependant::STATUS_ACTIVE) {
                                                            $i++;
                                                        }
                                                    }
                                                }
                                                $isAutoFill = true;
                                                $value = $i;
                                            }
                                            break;
                                        case ServiceField::HOST_ENTITY_TEXT:
                                            if ($serviceCompany->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                                                $isAutoFill = true;
                                                $value = $assignment->getAssignmentDestination()->getDestinationBusinessUnit();
                                            }
                                            break;
                                    }

                                    if ($isAutoFill) {
                                        $newServiceFieldValue = ServiceFieldValue::__getByServiceAndFieldWithCheck($relocationServiceCompany->getId(), $field->getId());
                                        $newServiceFieldValue->setData([
                                            'relocation_service_company_id' => $relocationServiceCompany->getId(),
                                            'service_field_id' => $field->getId(),
                                            'service_event_id' => $field->getServiceEventId(),
                                            'value' => $value,
                                        ]);

                                        $resultSaveServiceFieldValue = $newServiceFieldValue->__quickCreate();

                                        //TODO = commit in EVENt WORK but NOT IN SERVICE FIELD EVENt
                                        if ($resultSaveServiceFieldValue['success'] == false) {
                                            $this->db->rollback();
                                            $return = [
                                                'input' => $value,
                                                'field' => $field,
                                                'success' => false,
                                                'message' => 'SAVE_INFOS_FAIL_TEXT',
                                                'detail' => $resultSaveServiceFieldValue['detail']
                                            ];
                                            goto end_of_function;
                                        }
                                    }
                                }
                            }
                            // end prefill
                            $this->db->commit();
                            $return['owner'] = $ownerProfileDefault;
                            $return['reporter'] = $reporterProfileDefault;
                            $return['message'] = 'ADD_SERVICE_SUCCESS_TEXT';
                            $return['data'] = $relocationServiceCompany;
                            $return['property'] = $property;
                            $return['servicesRelocation'] = $servicesRelocation;
                            $return['homeSearchServiceOfRelocation'] = $homeSearchServiceOfRelocation;
                        } else {
                            $this->db->rollback();
                            if ($return['detail'] == 'NAME_MUST_BE_UNIQUE_TEXT') {
                                $return['message'] = 'NAME_MUST_BE_UNIQUE_TEXT';
                            } else {
                                $return['message'] = 'ADD_SERVICE_FAIL_TEXT';
                            }
                            goto end_of_function;
                        }
                    }
                }
            }
        }


        end_of_function:
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
        $this->checkAcl('edit', $this->router->getControllerName());

        $uuid = Helpers::__getRequestValue("uuid");
        $workflow_id = Helpers::__getRequestValue("workflow");
        $tasks = Helpers::__getRequestValue("tasks");
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation = Relocation::findFirstByUuid($uuid);
            if ($relocation && $relocation->belongsToGms() && $relocation->checkMyEditPermission()) {
                if ($workflow_id != '') {
                    $workflow = Workflow::findFirstById($workflow_id);
                    $workflow_uuid = $workflow->getUuid();
                    if ($workflow && $workflow->belongsToGms()) {
                        $task_workflows = TaskWorkflow::getByWorkflowUuid($workflow_uuid);
                        $tasks_list = [];
                        $this->db->begin();
                        if ($task_workflows->count() > 0) {

                            foreach ($task_workflows as $task_workflow) {
                                $custom = $task_workflow->toArray();
                                $custom['assignment_id'] = $relocation->getAssignmentId();
                                $custom['relocation_id'] = $relocation->getId();
                                $custom['link_type'] = Task::LINK_TYPE_RELOCATION;
                                $custom['object_uuid'] = $uuid;
                                $custom['company_id'] = ModuleModel::$company->getId();
                                $custom['owner_id'] = ModuleModel::$user_profile->getId(); //get owner of task

                                $taskObject = new Task();
                                $taskObject->setData($custom);
                                $taskResult = $taskObject->__quickCreate();

                                if ($taskResult['success'] == true) {
                                    /// add reporter of RELOCATION
                                    $reporter = $relocation->getDataReporter();
                                    if ($reporter) {
                                        $taskObject->addReporter($reporter);
                                    }
                                    /// add owner of RELOCATION
                                    $owner = $relocation->getDataOwner();
                                    if ($owner instanceof UserProfile) {

                                        $resultAddOwner = $taskObject->addOwner($owner);
                                        if (!$resultAddOwner["success"]) {
                                            $this->db->rollback();
                                            $return = ['success' => false, 'detailAddOwner' => $resultAddOwner, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                                            $this->response->setJsonContent($return);
                                            return $this->response->send();
                                        }
                                    }

                                    $checklists = TaskWorkflow::find(["conditions" => "object_uuid = :object_uuid: AND link_type = :link_type:",
                                        "bind" => ["object_uuid" => $task_workflow->getUuid(), "link_type" => TaskWorkflow::TASK_WORKFLOW]]);
                                    $tasks_list[$taskObject->getUuid()] = $taskObject->toArray();
                                    $tasks_list[$taskObject->getUuid()]['checklist'][] = $checklists;
                                    if (count($checklists) > 0) {
                                        foreach ($checklists as $checklist) {
                                            $taskItemObject = new Task();
                                            $taskItemObject->setAssignmentId($relocation->getId());
                                            $taskItemObject->setLinkType(Task::LINK_TYPE_TASK);
                                            $taskItemObject->setObjectUuid($taskObject->getUuid());
                                            $taskItemObject->setCompanyId(ModuleModel::$company->getId());
                                            $taskItemObject->setOwnerId(ModuleModel::$user_profile->getId());
                                            $taskItemObject->setName($checklist->getName());

                                            $taskItemObject->setData();
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
                                } else {
                                    $this->db->rollback();
                                    $return = ['success' => false, 'data' => $tasks_list, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT', 'raw' => $taskObject];
                                    $this->response->setJsonContent($return);
                                    return $this->response->send();
                                }
                            }
                        }
                        if (count($tasks) > 0) {
                            foreach ($tasks as $task) {
                                if ($task->keep == 0) {
                                    $taskToRemove = Task::findFirstByUuid($task->uuid);
                                    $resultRemove = $taskToRemove->__quickRemove();
                                    if ($resultRemove['success'] == false) {
                                        $return = ['success' => false, 'data' => $tasks_list, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                                        $this->db->rollback();
                                        $this->response->setJsonContent($return);
                                        return $this->response->send();
                                    }
                                }
                            }
                        }

                        if (count($tasks) > 0) {
                            foreach ($tasks as $task) {
                                if ($task->keep == 0) {
                                    $taskToRemove = Task::findFirstByUuid($task->uuid);
                                    $resultRemove = $taskToRemove->__quickRemove();
                                    if ($resultRemove['success'] == false) {
                                        $return = ['success' => false, 'detail' => $resultRemove, 'message' => 'TASK_LIST_CREATE_FAILED_TEXT'];
                                        $this->db->rollback();
                                        $this->response->setJsonContent($return);
                                        return $this->response->send();
                                    }
                                }
                            }
                        }

                        $attachments = MediaAttachment::__get_attachments_from_uuid($workflow_uuid);
                        if (count($attachments) > 0) {
                            $resultAttach = MediaAttachment::__create_attachments_from_uuid($uuid . "_" . ModuleModel::$company->getUuid(), $attachments);
                            if (!$resultAttach["success"]) {
                                $this->db->rollback();
                                $return = ['success' => false, 'detail' => $resultAttach, 'message' => 'ATTACHMENT_SAVE_FAILED_TEXT'];
                                goto end_of_function;
                            }
                        }

                        /** set workflow applied = true */
                        try {
                            $relocation->setWorkflowId($workflow_id);
                            $resultSaveRelocation = $relocation->__save();
                            if ($resultSaveRelocation["success"]) {
                                $this->db->commit();
                            } else {
                                $return = ['success' => false, 'relocation' => $resultSaveRelocation, 'message' => 'DATA_SAVE_FAILED_TEXT'];
                                $this->db->rollback();
                                $this->response->setJsonContent($return);
                                return $this->response->send();
                            }
                        } catch (\PDOException $e) {
                            $return = ['success' => false, 'data' => $tasks_list, 'message' => 'DATA_SAVE_FAILED_TEXT'];
                        } catch (Exception $e) {
                            $return = ['success' => false, 'data' => $tasks_list, 'message' => 'DATA_SAVE_FAILED_TEXT'];
                        }
                        $return = ['success' => true,
                            'data' => $tasks_list,
                            'message' => 'TASK_LIST_CREATE_SUCCESS_TEXT',
                            'template' => $task_workflows,
                            'attachment' => $attachments];

                    } else {
                        $return = [
                            'success' => false, 'message' => 'WORKFLOW_NOT_FOUND_TEXT', 'raw' => $_POST, 'data' => $workflow
                        ];
                    }
                } else {
                    $return = ['success' => false, 'message' => 'WORKFLOW_NOT_FOUND_TEXT', 'raw' => $_POST];
                }
            } else {
                $return = [
                    'success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT', 'raw' => $_POST, 'data' => $relocation
                ];
            }
        } else {
            $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT', 'raw' => $_POST];
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @param  string $id [description]ee
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
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getSimpleListActiveAction($uuid)
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
                    $serviceArray[$service->getId()]['number_providers'] = $service->countActiveServiceProviderCompany();
                    $serviceArray[$service->getId()]['need_spouse'] = false;
                    $serviceArray[$service->getId()]['need_child'] = false;
                    $serviceArray[$service->getId()]['need_dependant'] = false;
                    $relocationServices = RelocationServiceCompany::find([
                        "conditions" => "status = :status: and relocation_id = :id:  and service_company_id = :service_company_id:",
                        "bind" => [
                            "status" => RelocationServiceCompany::STATUS_ACTIVE,
                            "id" => $relocation->getId(),
                            "service_company_id" => $service->getId()
                        ]
                    ]);
                    $serviceArray[$service->getId()]['count'] = count($relocationServices);
                    if ($service->getServiceId() == Service::PARTNER_SUPPORT_SERVICE) {
                        $serviceArray[$service->getId()]['need_spouse'] = true;
                    }
                    if ($service->getServiceId() == Service::SCHOOL_SEARCH_SERVICE) {
                        $serviceArray[$service->getId()]['need_child'] = true;
                    }
                    if ($service->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                        $serviceArray[$service->getId()]['need_dependant'] = true;
                    }

                }
                $result = [
                    'success' => true,
                    'data' => array_values($serviceArray),
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
     * @param $uuid
     */
    public function removeAllServicesAction($uuid)
    {

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
        $this->checkAcl('index', $this->router->getControllerName());

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
                foreach ($attachments as $attachment) {
                    $res = MediaAttachment::__create_attachment_from_uuid($service->getUuid(), $attachment);
                    if ($res['success'] = false) {
                        $this->db->rollback();
                        goto end_of_function;
                    } else {
                        $hasTransaction = true;
                    }
                }
            };
        }
        if ($hasTransaction == true) {
            $this->db->commit();
        }

        $return = ['success' => true];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();


    }

}
