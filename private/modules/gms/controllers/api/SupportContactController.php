<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\EmployeeSupportContact;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\SupportedLanguage;
use Reloday\Gms\Models\Team;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\Employee;
use Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class SupportContactController extends BaseController
{

    /**
     * @param $employeeId
     */
    public function getRelocationSupportContactsAction($relocationId)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($relocationId)) {
            $relocation = Relocation::findFirstById($relocationId);
            if ($relocation instanceof Relocation && $relocation->belongsToGms()) {
                $contactSupportItem = EmployeeSupportContact::__getRelocationSupportContact($relocationId);
                $result = [
                    'success' => true,
                    'data' => $contactSupportItem
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $employeeId
     */
    public function getEmployeeBuddyContactsAction($employeeId)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($employeeId)) {
            $employee = Employee::findFirstByIdCache($employeeId);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $contacts = EmployeeSupportContact::__getBuddyContacts($employeeId);
                $result = [
                    'success' => true,
                    'data' => $contacts
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $employeeId
     */
    public function searchEmployeeSupportContactsAction($employeeId)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($employeeId)) {
            $employee = Employee::findFirstByIdCache($employeeId);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $workers = UserProfile::getGmsWorkers();
                $result = [
                    'success' => true,
                    'data' => $workers
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Team member of Employee + other Employee of Same company
     * @param $employeeId
     */
    public function searchEmployeeBuddyContactsAction($employeeId)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($employeeId)) {
            $employee = Employee::findFirstByIdCache($employeeId);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $employees = Employee::__getSimpleListByCompany($employee->getCompanyId(), $employee->getId());
                $teams = UserProfile::getHrWorkers($employee->getCompanyId());

                $contacts = [];
                if ($employees) {
                    $contacts = array_merge($contacts, $employees->toArray());
                }
                if ($teams) {
                    $contacts = array_merge($contacts, $teams->toArray());
                }
                $result = [
                    'success' => true,
                    'data' => $contacts
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $result = [
            'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        $relocationId = Helpers::__getRequestValue('relocation_id');
        $employeeId = Helpers::__getRequestValue('employee_id');
        $contactUuid = Helpers::__getRequestValue('contact_uuid');
        $isBuddy = Helpers::__getRequestValue('is_buddy');

        if (Helpers::__isValidId($employeeId)) {

            $result = [
                'success' => false, 'message' => 'ASSIGNEE_NOT_FOUND_TEXT'
            ];


            $employee = Employee::findFirstByIdCache($employeeId);
            if ($employee instanceof Employee && $employee->belongsToGms()) {




                $supportContact = new EmployeeSupportContact();
                $userProfile = UserProfile::findFirstByUuidCache($contactUuid);
                if ($userProfile && ($userProfile->belongsToGms() || $userProfile->manageByGms())) {
                    $supportContact->setContactUserProfileId($userProfile->getId());
                } else {
                    $userProfile = Employee::findFirstByUuidCache($contactUuid);
                    if ($userProfile && ($userProfile->belongsToGms() || $userProfile->manageByGms())) {
                        $supportContact->setContactEmployeeId($userProfile->getId());
                    }
                }

                if ($supportContact) {
                    if ($isBuddy == true) {
                        $supportContact->setIsBuddy(ModelHelper::YES);
                        $supportContact->setRoleName('buddy');
                    } else {
                        $supportContact->setIsBuddy(ModelHelper::NO);
                        $supportContact->setRoleName('support');
                    }
                    $supportContact->setEmployeeId($employee->getId());
                    $supportContact->setJobtitle($userProfile->getJobtitle());
                    $supportContact->setCreatorCompanyId(ModuleModel::$company->getId());
                    $supportContact->setContactProfileUuid($userProfile->getUuid());
                    $supportContact->setCompanyName($userProfile->getCompany()->getName());
                    $supportContact->setFirstname($userProfile->getFirstname());
                    $supportContact->setLastname($userProfile->getLastname());
                    $supportContact->setTelephone($userProfile->getPhonework());
                    $supportContact->setMobile($userProfile->getMobilework());
                    $supportContact->setEmail($userProfile->getWorkemail());
                    $result = $supportContact->__quickCreate();
                    $result['data'] = $supportContact;
                }
            }
        } else if (Helpers::__isValidId($relocationId)) {

            $result = [
                'success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ];

            $relocation = Relocation::findFirstById($relocationId);

            if ($relocation instanceof Relocation && $relocation->belongsToGms()) {




                $supportContact = new EmployeeSupportContact();
                $userProfile = UserProfile::findFirstByUuidCache($contactUuid);
                if ($userProfile && ($userProfile->belongsToGms() || $userProfile->manageByGms())) {
                    $supportContact->setContactUserProfileId($userProfile->getId());
                } else {
                    $userProfile = Employee::findFirstByUuidCache($contactUuid);
                    if ($userProfile && ($userProfile->belongsToGms() || $userProfile->manageByGms())) {
                        $supportContact->setContactEmployeeId($userProfile->getId());
                    }
                }

                if ($supportContact) {

                    $this->db->begin();
                    $employeeContactSupports = EmployeeSupportContact::findByRelocationId($relocationId);
                    if ($employeeContactSupports->count() > 0) {
                        $result = ModelHelper::__quickRemoveCollection($employeeContactSupports);
                        if($result['success'] == false){
                            $this->db->rollback();
                            goto end;
                        }
                    }


                    if ($isBuddy == true) {
                        $supportContact->setIsBuddy(ModelHelper::YES);
                        $supportContact->setRoleName('buddy');
                    } else {
                        $supportContact->setIsBuddy(ModelHelper::NO);
                        $supportContact->setRoleName('support');
                    }
                    $supportContact->setEmployeeId($relocation->getEmployeeId());
                    $supportContact->setRelocationId($relocation->getId());
                    $supportContact->setJobtitle($userProfile->getJobtitle());
                    $supportContact->setCreatorCompanyId(ModuleModel::$company->getId());
                    $supportContact->setContactProfileUuid($userProfile->getUuid());
                    $supportContact->setCompanyName($userProfile->getCompany()->getName());
                    $supportContact->setFirstname($userProfile->getFirstname());
                    $supportContact->setLastname($userProfile->getLastname());
                    $supportContact->setTelephone($userProfile->getPhonework());
                    $supportContact->setMobile($userProfile->getMobilework());
                    $supportContact->setEmail($userProfile->getWorkemail());
                    $result = $supportContact->__quickCreate();
                    $result['data'] = $supportContact;

                    if($result['success'] == false){
                        $this->db->rollback();
                        goto end;
                    }

                    $this->db->commit();
                }
            }
        }

        end:
        if (isset($supportContact) && $supportContact) {
            if ($supportContact->getIsBuddy() == ModelHelper::NO) {
                if ($result['success'] == true) {
                    $result['message'] = 'SET_SUPPORT_CONTACT_SUCCESS_TEXT';
                } else {
                    $result['message'] = 'SET_SUPPORT_CONTACT_FAIL_TEXT';
                }
            } else {
                if ($result['success'] == true) {
                    $result['message'] = 'SET_BUDDY_CONTACT_SUCCESS_TEXT';
                } else {
                    $result['message'] = 'SET_BUDDY_CONTACT_FAIL_TEXT';
                }
            }
        } else {
            if ($isBuddy == true) {
                $result['message'] = 'SET_BUDDY_CONTACT_FAIL_TEXT';
            } else {
                $result['message'] = 'SET_SUPPORT_CONTACT_FAIL_TEXT';
            }
        }


        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $employeeSupportContactId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function removeAction($employeeSupportContactId)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $result = [
            'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($employeeSupportContactId)) {
            $employeeContactSupport = EmployeeSupportContact::findFirstById($employeeSupportContactId);
            if ($employeeContactSupport instanceof EmployeeSupportContact && $employeeContactSupport->belongsToGms()) {
                $result = $employeeContactSupport->__quickRemove();
            }

            if ($employeeContactSupport->getIsBuddy() == ModelHelper::NO) {
                if ($result['success'] == true) {
                    $result['message'] = 'REMOVE_SUPPORT_CONTACT_SUCCESS_TEXT';
                } else {
                    $result['message'] = 'REMOVE_SUPPORT_CONTACT_FAIL_TEXT';
                }
            } else {
                if ($result['success'] == true) {
                    $result['message'] = 'REMOVE_BUDDY_CONTACT_SUCCESS_TEXT';
                } else {
                    $result['message'] = 'REMOVE_BUDDY_CONTACT_FAIL_TEXT';
                }
            }
        }


        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $relocationId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function removeRelocationSupportContactAction($relocationId)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $result = [
            'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($relocationId)) {
            $relocation = Relocation::findFirstById($relocationId);
            if ($relocation instanceof Relocation && $relocation->belongsToGms()) {
                $employeeContactSupports = EmployeeSupportContact::findByRelocationId($relocationId);
                if ($employeeContactSupports->count() > 0) {
                    $result = ModelHelper::__quickRemoveCollection($employeeContactSupports);
                }
            }
        }
        if ($result['success'] == true) {
            $result['message'] = 'REMOVE_SUPPORT_CONTACT_SUCCESS_TEXT';
        } else {
            $result['message'] = 'REMOVE_SUPPORT_CONTACT_FAIL_TEXT';
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $relocationId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getRelocationSupportContactAction($relocationId)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false, 'message' => 'RELOCATION_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($relocationId)) {
            $relocation = Relocation::findFirstById($relocationId);
            if ($relocation instanceof Relocation && $relocation->belongsToGms()) {
                $contactSupportItem = EmployeeSupportContact::__getRelocationSupportContact($relocationId);
                $result = [
                    'success' => true,
                    'data' => $contactSupportItem
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
