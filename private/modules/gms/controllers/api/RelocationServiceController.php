<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Behavior\DataUserMemberCacheBehevior;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\LanguageCode;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\UserActionHelpers;
use Reloday\Application\Models\Company;
use Reloday\Application\Models\CompanyExt;
use Reloday\Application\Models\ConstantExt;
use Reloday\Application\Models\NationalityExt;
use Reloday\Application\Models\RelocationServiceCompanyExt;
use Reloday\Application\Models\ReportExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\AttributesValue;
use Reloday\Gms\Models\Comment;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\ListOrderSetting;
use Reloday\Gms\Models\EmployeeSupportContact;
use Reloday\Gms\Models\EntityDocument;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ModuleContent;
use Reloday\Gms\Models\NeedFormGabarit;
use Reloday\Gms\Models\NeedFormGabaritItem;
use Reloday\Gms\Models\NeedFormGabaritSection;
use Reloday\Gms\Models\NeedFormRequest;
use Reloday\Gms\Models\NeedAssessmentItemsRelocation;
use Reloday\Gms\Models\NeedAssessments;
use Reloday\Gms\Models\NeedAssessmentsRelocation;
use Reloday\Gms\Models\Policy;
use Reloday\Gms\Models\Property;
use \Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ReminderConfig;
use Reloday\Gms\Models\Report;
use Reloday\Gms\Models\Service;
use \Reloday\Gms\Models\ServiceCompany;
use \Reloday\Gms\Models\ServiceEvent;
use Reloday\Gms\Models\ServiceEventValue;
use \Reloday\Gms\Models\ServiceField;
use \Reloday\Gms\Models\ServiceFieldType;
use Reloday\Gms\Models\ServiceFieldValue;
use Reloday\Gms\Models\ServicePack;
use Reloday\Gms\Models\ServiceProviderCompany;
use \Reloday\Gms\Models\Task;
use \Reloday\Gms\Models\UserProfile;
use \Reloday\Gms\Models\DataUserMember;
use \Reloday\Gms\Models\ModuleModel;
use \Reloday\Gms\Models\ServiceFieldGroup;
use \Reloday\Gms\Models\TaskTemplateCompany;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Module;
use Reloday\Gms\Models\NeedFormGabaritServiceCompany;
use Phalcon\Security\Random;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class RelocationServiceController extends BaseController
{

    /** detail  */
    public function itemAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                $dataArray = $relocation_service_company->toArray();
                $dataArray['progress'] = $relocation_service_company->getEntityProgressValue();
                $dataArray['progress_status'] = $relocation_service_company->getEntityProgressStatus();
                $dataArray['service_id'] = $relocation_service_company->getServiceCompany() ? (int)$relocation_service_company->getServiceCompany()->getServiceId() : null;
                $dataArray['is_home_search'] = $relocation_service_company->isHomeSearch();
                $dataArray['relocation_uuid'] = $relocation_service_company->getRelocation() ? $relocation_service_company->getRelocation()->getUuid() : null;
                $dataArray['relocation_cancelled'] = $relocation_service_company->getRelocation() ? $relocation_service_company->getRelocation()->isCancelled() : false;

                $relocation = $relocation_service_company->getRelocation();
                $employee = $relocation->getEmployee();
                $dataArray['employee_name'] = $employee->getFullname();
                $dataArray['employee_uuid'] = $employee->getUuid();
                $dataArray['employee_id'] = $employee->getId();
                $dataArray['employee_email'] = $employee->getWorkemail();
                $dataArray['company_name'] = $relocation->getHrCompany()->getName();
                $data_owner = $relocation_service_company->getDataOwner();
                $dataArray['owner_name'] = $data_owner ? $data_owner->getFullname() : '';
                $dataArray['owner_uuid'] = $data_owner ? $data_owner->getUuid() : '';

                $assignment = $relocation->getAssignment();
                $dataArray['booker_company_name'] = $assignment->getBookerCompany() ? $assignment->getBookerCompany()->getName() : '';
                $return = [
                    'success' => true,
                    'data' => $dataArray,
                ];
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'SERVICE_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function isExistAndActiveAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                if (ModuleModel::$user_profile->isAdminOrManager()) {
                    $return = [
                        'success' => true,
                    ];
                    goto end;
                } else if ($relocation_service_company->checkMyViewPermission() == true) {
                    $return = [
                        'success' => true,
                    ];
                    goto end;
                } else {
                    $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'permissionNotFound' => true];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'SERVICE_NOT_FOUND_TEXT'];
            }
        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getAssigneeAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                if ($relocation_service_company->getRelocation() && $relocation_service_company->getRelocation()->getEmployee()) {
                    $employee = $relocation_service_company->getRelocation()->getEmployee();
                    $employeeArray = $employee->toArray();
                    $employeeArray['is_editable'] = $employee->isEditable();
                    $employeeArray['company_name'] = $employee->getCompany()->getName();
                    $employeeArray['company_uuid'] = $employee->getCompany()->getUuid();
                    $employeeArray['office_name'] = $employee->getOffice() ? $employee->getOffice()->getName() : "";
                    $employeeArray['team_name'] = $employee->getTeam() ? $employee->getTeam()->getName() : "";
                    $employeeArray['department_name'] = $employee->getDepartment() ? $employee->getDepartment()->getName() : "";
                    $employeeArray['citizenships'] = $employee->parseCitizenships();
                    $employeeArray['documents'] = EntityDocument::__getDocumentsByEntityUuid($employee->getUuid());
                    $employeeArray['support_contacts'] = EmployeeSupportContact::__getSupportContacts($employee->getId(), $relocation_service_company->getRelocation()->getId());
                    $employeeArray['buddy_contacts'] = EmployeeSupportContact::__getBuddyContacts($employee->getId());
                    $employeeArray['spoken_languages'] = $employee->parseSpokenLanguages();
                    $employeeArray['birth_country_name'] = $employee->getBirthCountry() ? $employee->getBirthCountry()->getName() : "";
                    $employeeArray['first_login'] = $employee->getUserLogin() ? $employee->getUserLogin()->getFirstconnectAt() : "";
                    $employeeArray['last_login'] = $employee->getUserLogin() ? $employee->getUserLogin()->getLastconnectAt() : "";
                    $employeeArray['hasLogin'] = $employee->hasLogin();
                    $employeeArray['hasUserLogin'] = $employee->getUserLogin() ? true : false;
                    $employeeArray['login_email'] = $employee->getUserLogin() ? $employee->getUserLogin()->getEmail() : null;
                    $employeeArray['dependants'] = $employee->getDependants() ? $employee->getDependants()->toArray() : [];
                    $return = [
                        'success' => true,
                        'data' => $employeeArray
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getRelocationAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                if ($relocation_service_company->getRelocation()) {
                    $return = [
                        'success' => true,
                        'data' => $relocation_service_company->getRelocation(),
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getAssignmentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {

                if ($relocation_service_company->getRelocation()) {

                    $assignment = $relocation_service_company->getRelocation()->getAssignment();
                    if ($assignment) {

                        $basic = $assignment->getAssignmentBasic();
                        $destination = $assignment->getAssignmentDestination();

                        $basicArray = [];
                        $destinationArray = [];

                        if ($destination) {
                            $destinationArray = $destination->toArray();
                            $destinationArray['country_name'] = $destination->getDestinationCountry() ? $destination->getDestinationCountry()->getName() : "";
                            if ($destination->getDestinationCity() != '' && $assignment->getDestinationCity() == '') {
                                $assignment->setDestinationCity($destination->getDestinationCity());
                            }
                        }

                        if ($basic) {
                            $basicArray = $basic->toArray();
                            $basicArray['country_name'] = $basic->getHomeCountry() ? $basic->getHomeCountry()->getName() : "";
                            if ($basic->getHomeCity() != '' && $assignment->getHomeCity() == '') {
                                $assignment->setHomeCity($basic->getHomeCity());
                            }
                        }

                        $assignmentArray = $assignment->getInfoDetailInArray();

                        $return = [
                            'success' => true,
                            'data' => $assignmentArray,
                            'basic' => $basicArray,
                            'destination' => $destinationArray
                        ];
                    }
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $uuid
     * @return mixed
     */
    public function getServiceCompanyAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                if ($relocation_service_company->getServiceCompany()) {
                    $return = [
                        'success' => true,
                        'data' => $relocation_service_company->getServiceCompany(),
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getSvpCompanyAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                if ($relocation_service_company->getServiceProviderCompany()) {
                    $return = [
                        'success' => true,
                        'data' => $relocation_service_company->getServiceProviderCompany(),
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $uuid
     * @return mixed
     */
    public function getServiceTabsAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();


        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                $service_company = $relocation_service_company->getServiceCompany();
                if ($service_company) {
                    $tabs = $service_company->getService()->getTabs()->toArray();

                    foreach ($tabs as $key => $tab) {
                        if ($tabs[$key]['template'] == 'relocation/service-detail-tasks') {
                            $tabs[$key]['acl'] = $this->router->getControllerName() . "/" . AclHelper::ACTION_MANAGE_TASK;
                        } elseif ($tabs[$key]['template'] == 'relocation/service-detail-documents') {
                            $tabs[$key]['acl'] = $this->router->getControllerName() . "/" . AclHelper::ACTION_MANAGE_DOCUMENTS;
                        } else {
                            $tabs[$key]['acl'] = $this->router->getControllerName() . "/index";
                        }
                    }


                    $return = [
                        'success' => true,
                        'data' => $tabs
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getEventsAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                $service_company = $relocation_service_company->getServiceCompany();
                if ($service_company) {
                    $events = $service_company->getServiceEvents(['base = ' . ServiceEvent::BASE_EVENT_ACTIVE]);
                    $events_array = [];
                    $events_data = $relocation_service_company->getEventsData();
                    foreach ($events as $event) {
                        $item = $event->toArray();
                        $item['value'] = $event->getEventValue($relocation_service_company->getId());
                        /*isset($events_data[$event->getCode()]) &&
                        is_numeric($events_data[$event->getCode()]) &&
                         $events_data[$event->getCode()] > 0 ? $events_data[$event->getCode()] : null;*/
                        $events_array[] = $item;
                    }
                    $return = [
                        'success' => true,
                        'data' => $events_array
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function saveEventsAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $eventsDataList = Helpers::__getRequestValue('events');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                $this->db->begin();
                $events_data = [];
                $serviceCompany = $relocation_service_company->getServiceCompany();
                if ($serviceCompany) {
                    $eventList = $serviceCompany->getServiceEvents();
                    $resultQueueEvents = [];
                    if ($eventList->count() > 0) {
                        $resultSaveEventTimes = [];
                        foreach ($eventList as $eventItem) {
                            foreach ($eventsDataList as $eventValue) {
                                $eventValue = (array)$eventValue;

                                if (intval($eventValue['id']) == $eventItem->getId()) {

                                    $eventObject = ServiceEventValue::__findByServiceAndEventWithCheck($relocation_service_company->getId(), $eventItem->getId());
                                    $eventObject->setData([
                                        'relocation_service_company_id' => $relocation_service_company->getId(),
                                        'service_event_id' => $eventItem->getId(),
                                        'value' => is_numeric($eventValue['value']) && $eventValue['value'] > 0 ? $eventValue['value'] : ($eventValue['value'] == null || $eventValue['value'] == '' ? null : strtotime($eventValue['value']))
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
                                    $resultSaveEventTimes[] = $resultSaveEventTime;
                                    if ($checkReminder == true) {
                                        $resultSaveReminderConfig = $eventObject->updateReminderConfigData();
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
                        }

                        $this->db->commit();

                        $return = [
                            'success' => true,
                            'message' => 'SAVE_EVENTS_SUCCESS_TEXT',
                            '$resultReminderConfig' => isset($resultReminderConfig) ? $resultReminderConfig : null,
                            'resultQueueEvents' => $resultQueueEvents,
                            'resultSaveEventTimes' => $resultSaveEventTimes
                        ];
                    }
                }
            }

            end_of_function:
            if ($return['success'] == true && isset($relocation_service_company)) {
                ModuleModel::$relocationServiceCompany = $relocation_service_company;
                $this->dispatcher->setParam('return', $return);
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation_service_company, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_SAVE_SERVICE_EVENTS);
            }
            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getFieldsAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                $service_company = $relocation_service_company->getServiceCompany();
                if ($service_company) {
                    $fields_data = json_decode($relocation_service_company->getData(), true);
                    $fields = $service_company->getServiceFields([
                        'order' => 'position ASC',
                    ]);

                    $fields_array = [];
                    $fields_groups = [];
                    $fields_groups_cpt = 0;

                    foreach ($fields as $key => $field) {

                        $item = $field->toArray();
                        $item['value'] = $field->getFieldValue($relocation_service_company->getId());

                        $item['attribute_name'] = '';
                        $item['type'] = $field->getDataTypeName();

                        if ($field->getServiceFieldTypeId() == ServiceFieldType::TYPE_ATTRIBUTES) {
                            if ($field->getAttribute()) {
                                $item['attribute_name'] = $field->getAttribute()->getCode();
                            }
                        }

                        if ($field->getServiceFieldGroup()) {
                            $fields_groups[$field->getServiceFieldGroup()->getPosition()]['name'] = $field->getServiceFieldGroup()->getName();
                            $fields_groups[$field->getServiceFieldGroup()->getPosition()]['repeat'] = $field->getServiceFieldGroup()->getRepeat();
                            $fields_groups[$field->getServiceFieldGroup()->getPosition()]['label'] = $field->getServiceFieldGroup()->getLabel();
                            $fields_groups[$field->getServiceFieldGroup()->getPosition()]['add_button_label'] = $field->getServiceFieldGroup()->getAddButtonLabel();
                            $fields_groups[$field->getServiceFieldGroup()->getPosition()]['items'][$field->getIdentify()] = $item;
                        } else {
                            $fields_groups['0']['name'] = 'Basic';
                            $fields_groups['0']['repeat'] = 0;
                            $fields_groups['0']['label'] = 'BASIC_INFORMATION_TEXT';
                            $fields_groups['0']['items'][$field->getCode()] = $item;
                        }
                        //@TODO field group dependant
                        if ($field->getServiceFieldGroup() && $field->getServiceFieldGroup()->getRepeat() == ServiceFieldGroup::REPEAT_YES) {

                        } else {
                            $fields_array[$field->getIdentify()] = $item;
                        }
                    }
                    $return = [
                        'success' => true,
                        'fields' => $fields_array,
                        'groups' => $fields_groups,
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function saveInfosAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $fields_value = Helpers::__getRequestValue('fields');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }

        $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);

        if (!($relocation_service_company &&
            $relocation_service_company->belongsToGms() &&
            $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE)
        ) {
            goto end_of_function;
        }

        $service_company = $relocation_service_company->getServiceCompany();
        if ($service_company) {
            $fields = $service_company->getServiceFields([
                'order' => 'position ASC',
            ]);


            $fields_data = [];
            $fields_value = (array)$fields_value;
            foreach ($fields as $field) {
                if (!isset($fields_data[$field->getIdentify()])) {
                    $fields_data[$field->getIdentify()] = '';
                }
                foreach ($fields_value as $key => $field_value) {
                    $field_value = (array)$field_value;
                    if ($field_value['id'] == $field->getId()) {
                        $fields_data[$field->getIdentify()] = ($field_value['value']);
                        unset($fields_value[$key]);
                    } else {
                        continue;
                    }
                }
                // if date / time and date time
                if ($field->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATE ||
                    $field->getServiceFieldTypeId() == ServiceFieldType::TYPE_TIME ||
                    $field->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATETIME) {

                    if (isset($fields_data[$field->getCode()]) &&
                        $fields_data[$field->getCode()] != '' &&
                        is_numeric($fields_data[$field->getCode()])) {
                        $fields_data[$field->getCode()] = Helpers::__convertToSecond($fields_data[$field->getIdentify()]);
                    } elseif (Helpers::__isDate($fields_data[$field->getIdentify()])) {
                        $fields_data[$field->getIdentify()] = strtotime($fields_data[$field->getIdentify()]);
                    } else {
                        $fields_data[$field->getCode()] = intval($fields_data[$field->getIdentify()]);
                    }
                }
                if ($field->getServiceFieldTypeId() == ServiceFieldType::TYPE_PASSIVE_DATE) {
//                    if (Helpers::__isDate($fields_data[$field->getCode()])) {
                    $fields_data[$field->getIdentify()] = ($fields_data[$field->getIdentify()]);
//                    }
                }

                if ($field->getServiceFieldTypeId() == ServiceFieldType::TYPE_NATIONALITY) {
                    
                    if ($fields_data[$field->getIdentify()] != '' && is_array($fields_data[$field->getIdentify()])) {
                        $fields_data[$field->getIdentify()] = json_encode($fields_data[$field->getIdentify()]);
                    } else {
                        $fields_data[$field->getIdentify()] = '';
                    }
                }
               
            }

            $this->db->begin();

            foreach ($fields as $field) {
                $newServiceFieldValue = ServiceFieldValue::__getByServiceAndFieldWithCheck($relocation_service_company->getId(), $field->getId());
                $newServiceFieldValue->setData([
                    'relocation_service_company_id' => $relocation_service_company->getId(),
                    'service_field_id' => $field->getId(),
                    'service_event_id' => $field->getServiceEventId(),
                    'value' => $fields_data[$field->getIdentify()],
                ]);

                if ($newServiceFieldValue->getId() > 0) {
                    $resultSaveServiceFieldValue = $newServiceFieldValue->__quickUpdate();
                } else {
                    $resultSaveServiceFieldValue = $newServiceFieldValue->__quickCreate();
                }

                //TODO = commit in EVENt WORK but NOT IN SERVICE FIELD EVENt
                if ($resultSaveServiceFieldValue['success'] == false) {
                    $this->db->rollback();
                    $return = [
                        'input' => ($fields_data),
                        'success' => false,
                        'message' => 'SAVE_INFOS_FAIL_TEXT',
                        'detail' => $resultSaveServiceFieldValue['detail'],
                    ];
                    goto end_of_function;
                }

                if ($field->getServiceEventId() > 0) {

                    $eventObject = ServiceEventValue::__findByServiceAndEventWithCheck($relocation_service_company->getId(), $field->getServiceEventId());
                    $eventObject->setData([
                        'relocation_service_company_id' => $relocation_service_company->getId(),
                        'service_event_id' => $field->getServiceEventId(),
                        'value' => is_numeric($fields_data[$field->getIdentify()]) && $fields_data[$field->getIdentify()] > 0 ? $fields_data[$field->getIdentify()] : strtotime($fields_data[$field->getIdentify()])
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
                        $resultSaveReminderConfig = $eventObject->updateReminderConfigData();
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
            try {
                $this->db->commit();
            } catch (\PDOException $e) {
                die('commit failed');
            } catch (Exception $e) {
                die('commit failed');
            }

            $return = [
                'input' => ($fields_data),
                'success' => true,
                'message' => 'SAVE_INFOS_SUCCESS_TEXT',
            ];

            foreach ($fields as $field) {

            }

            $test = ServiceFieldValue::__getByServiceAndFieldWithCheck($relocation_service_company->getId(), 1);
            $return['test'] = $test;
        }

        end_of_function:

        if ($return['success'] == true && isset($relocation_service_company)) {
            ModuleModel::$relocationServiceCompany = $relocation_service_company;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation_service_company, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_UPDATE);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_service_company_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function saveServiceProviderAction($relocation_service_company_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'data' => [], 'message' => 'SERVICE_NOT_FOUND_TEXT'];
        $relocation_uuid = Helpers::__getRequestValue('relocation_uuid', $this->request);
        $service_uuid = Helpers::__getRequestValue('service_uuid', $this->request);
        $relocation_service_company_uuid = $relocation_service_company_uuid != '' ? $relocation_service_company_uuid : Helpers::__getRequestValue('relocation_service_company_uuid', $this->request);
        $service_provider_company_id = Helpers::__getRequestValue('service_provider_company_id', $this->request);
        $return = ['success' => false, 'data' => [], 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($relocation_service_company_uuid != '' && Helpers::__isValidUuid($relocation_service_company_uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {

                $relocation_service_company->setServiceProviderCompanyId($service_provider_company_id);

                if (!$service_provider_company_id) {
                    $relocation_service_company->setSvpMemberId(null);
                } else {
                    $svpMember = $relocation_service_company->getSvpMember();
                    if ($svpMember && $svpMember->getServiceProviderCompanyId() != $service_provider_company_id) {
                        $relocation_service_company->setSvpMemberId(null);
                    }
                }

                $resultSave = $relocation_service_company->__quickUpdate();

                if ($resultSave['success'] == false) {
                    $return = [
                        'success' => false,
                        'message' => 'SAVE_INFOS_FAIL_TEXT',
                        'detail' => $resultSave['detail'],
                    ];
                } else {
                    $return = [
                        'success' => true,
                        'message' => 'SAVE_INFOS_SUCCESS_TEXT',
                    ];
                }
            }
        }

        end:
        if ($return['success'] == true && isset($relocation_service_company)) {
            ModuleModel::$relocationServiceCompany = $relocation_service_company;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation_service_company, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_SET_SVP);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_service_company_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function saveSvpMemberAction($relocation_service_company_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'data' => [], 'message' => 'SERVICE_NOT_FOUND_TEXT'];
        $relocation_service_company_uuid = $relocation_service_company_uuid != '' ? $relocation_service_company_uuid : Helpers::__getRequestValue('relocation_service_company_uuid', $this->request);
        $svp_member_id = Helpers::__getRequestValue('svp_member_id', $this->request);
        $return = ['success' => false, 'data' => [], 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($relocation_service_company_uuid != '' && Helpers::__isValidUuid($relocation_service_company_uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {

                $relocation_service_company->setSvpMemberId($svp_member_id);

                $resultSave = $relocation_service_company->__quickUpdate();

                if ($resultSave['success'] == false) {
                    $return = [
                        'success' => false,
                        'message' => 'SAVE_INFOS_FAIL_TEXT',
                        'detail' => $resultSave['detail'],
                    ];
                } else {
                    $return = [
                        'success' => true,
                        'message' => 'SAVE_INFOS_SUCCESS_TEXT',
                    ];
                }
            }
        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_service_company_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function relocation_itemAction($relocation_service_company_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();


        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($relocation_service_company_uuid != '') {

            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);

            if ($relocation_service_company) {

                $svp_company = $relocation_service_company->getServiceProviderCompany();
                if ($svp_company) {
                    $return = [
                        'success' => true,
                        'svp_company' => $svp_company,
                    ];
                } else {
                    $return = [
                        'success' => true,
                        'svp_company' => null,
                    ];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $service_uuid
     * @return mixed
     */
    public function quickviewAction($relocation_service_company_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if ($relocation_service_company_uuid != '') {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {

                $data = $relocation_service_company->toArray();
                $data['relocation_uuid'] = $relocation_service_company->getRelocation()->getUuid();
                $data['service_company_uuid'] = $relocation_service_company->getServiceCompany()->getUuid();
                $data['service_name'] = $relocation_service_company->getServiceCompany()->getName();
                $return = [
                    'success' => true,
                    'data' => $data
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $relocation_service_company_uuid
     * @return mixed
     * @deprecated
     * Deprecated method >>> deleted and move to Background Service Engine
     * Setup WORKFLOW TASK
     */
    public function generate_workflowAction(string $relocation_service_company_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $return = ['success' => true, 'data' => [], 'message' => 'RELOCATION_SERVICE_NOT_FOUND_TEXT'];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $objectUuid
     * @return mixed
     */
    public function getTaskTemplateAction($objectUuid)
    {
        return $this->getTaskTemplatesAction($objectUuid);
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getTaskTemplatesAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocation_service_company &&
                $relocation_service_company->belongsToGms() &&
                $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
            ) {
                $service_company = $relocation_service_company->getServiceCompany();
                if ($service_company) {
                    $return = [
                        'success' => true,
                        'data' => $service_company->getTasksTemplate(),
                    ];
                }
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function generateTasksAction(string $relocationServiceCompanyUuid)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();
        $return = ['success' => true, 'data' => false];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $relocationServiceCompanyUuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function generateNewTask(string $relocationServiceCompanyUuid)
    {
        $return = ['success' => false, 'data' => [], 'message' => 'CREATE_TASK_FAIL_TEXT'];
        $task_template_id = Helpers::__getRequestValue('task_template_id');
        $countSequence = Helpers::__getRequestValue('count');

        if (!($relocationServiceCompanyUuid != '' && Helpers::__isValidUuid($relocationServiceCompanyUuid))) {
            goto end_of_function;
        }

        $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($relocationServiceCompanyUuid);

        if (!($relocationServiceCompany && $relocationServiceCompany->getWorkflowApplied() == RelocationServiceCompany::WORKFLOW_APPLIED_NO)) {
            $return = ['success' => false, 'data' => [], 'message' => 'WORKFLOW_GENERATED_TEXT'];
            goto end_of_function;
        }
        //setup workfflow
        $serviceCompany = $relocationServiceCompany->getServiceCompany();
        if (!($serviceCompany && $task_template_id > 0)) {
            goto end_of_function;
        }
        $task_template = TaskTemplateCompany::findFirstById($task_template_id);

        if (!($task_template && $task_template->getServiceCompanyId() == $serviceCompany->getId())) {
            goto end_of_function;
        }

        $task = Task::findFirst([
            'conditions' => 'task_template_company_id = :task_template_company_id: AND object_uuid = :relocation_service_uuid: AND status = :status_active:',
            'bind' => [
                'status_active' => Task::STATUS_ACTIVE,
                'task_template_company_id' => $task_template_id,
                'relocation_service_uuid' => $relocationServiceCompany->getUuid(),
            ]
        ]);

        /** check if task exist and active */
        if (!$task) {

            $custom = $task_template->toArray();
            $custom['relocation_service_company_id'] = $relocationServiceCompany->getId();
            $custom['task_template_company_id'] = $task_template->getId();
            $custom['link_type'] = Task::LINK_TYPE_SERVICE;
            $custom['object_uuid'] = $relocationServiceCompany->getUuid();
            $custom['company_id'] = (int)ModuleModel::$company->getId();
            $custom['creator_id'] = ModuleModel::$user_profile->getId(); //get owner of task
            $custom['owner_id'] = null;
            $custom['relocation_id'] = $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getId() : null;
            $custom['employee_id'] = $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getEmployeeId() : null;
            $custom['assignment_id'] = $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getAssignmentId() : null;
            $custom['count_sequence'] = $countSequence;

            $taskObject = new Task();
            $taskObject->setData($custom);
            $this->db->begin();
            $taskResult = $taskObject->__quickCreate();
            if ($taskResult['success'] == true) {
                /** create first reminder if existed */
                if ($task_template->getReminderTime() > 0 &&
                    $task_template->getReminderTimeUnit() != '' &&
                    $task_template->getBeforeAfter() != '' &&
                    $task_template->getReminderServiceEventId() > 0
                ) {

                    $reminderConfig = new ReminderConfig();
                    $paramReminder = [
                        'object_uuid' => $taskObject->getUuid(),
                        'reminder_time' => $task_template->getReminderTime(),
                        'reminder_time_unit' => $task_template->getReminderTimeUnit(),
                        'before_after' => $task_template->getBeforeAfter(),
                        'service_event_id' => $task_template->getReminderServiceEventId(),
                        'company_id' => ModuleModel::$company->getId(),
                        'type' => ReminderConfig::TYPE_BASE_ON_EVENT,
                        'number' => $taskObject->getNumber(),
                        'relocation_id' => $relocationServiceCompany->getRelocation() ? $relocationServiceCompany->getRelocation()->getId() : null,
                        'relocation_service_company_id' => $relocationServiceCompany->getId(),
                    ];

                    $reminderConfig->setData($paramReminder);
                    $resultCreateReminder = $reminderConfig->__quickSave();
                    if ($resultCreateReminder['success'] == false) {
                        $this->db->rollback();
                        $return = $resultCreateReminder;
                        goto end_of_function;
                    }
                }
                /*** creator ***/
                $resultAddCreator = $taskObject->addCreator(ModuleModel::$user_profile);
                if ($resultAddCreator['success'] == false) {
                    $this->db->rollback();
                    $return = $resultAddCreator;
                    goto end_of_function;
                }
                unset($resultAddCreator);

                /** @var add $reporter */
                $reporter = $relocationServiceCompany->getDataReporter();
                if (!$reporter) {
                    $reporter = $relocationServiceCompany->getRelocation()->getDataReporter();
                }
                if ($reporter) {
                    $resultAddReporter = $taskObject->addReporter($reporter);
                    if ($resultAddReporter['success'] == false) {
                        $this->db->rollback();
                        $return = $resultAddReporter;
                        goto end_of_function;
                    }
                    unset($resultAddReporter);
                }
                /** @var add $owner */
                $owner = $relocationServiceCompany->getDataOwner();
                if (!$owner) {
                    $owner = $relocationServiceCompany->getRelocation()->getDataOwner();
                }

                if ($owner) {
                    $resultAddOwner = $taskObject->addOwner($owner);
                    if ($resultAddOwner['success'] == false) {
                        $this->db->rollback();
                        $return = $resultAddOwner;
                        goto end_of_function;
                    }
                    unset($resultAddOwner);
                }

                $this->db->commit();
                $return = ['success' => true, 'data' => $taskObject, 'message' => 'TASK_GENERATED_TEXT'];
            } else {
                $this->db->rollback();
                $return = $taskResult;
                goto end_of_function;
            }
        }

        end_of_function:
        if ($return['success'] == true && isset($relocationServiceCompany)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocationServiceCompany, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_UPDATE);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function workflow_applyAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'CREATE_TASK_FAIL_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocationServiceCompany && $relocationServiceCompany->getWorkflowApplied() == RelocationServiceCompany::WORKFLOW_APPLIED_NO) {
                $relocationServiceCompany->setWorkflowApplied(RelocationServiceCompany::WORKFLOW_APPLIED_YES);
                $resultSave = $relocationServiceCompany->__quickUpdate();
                if ($resultSave['success'] == true) {
                    $return = ['success' => true, 'message' => 'TASK_LIST_CREATE_SUCCESS_TEXT'];
                } else {
                    $return = [
                        'success' => false,
                        'message' => 'TASK_LIST_CREATE_FAILED_TEXT',
                        'detail' => $resultSave,
                        'object' => $relocationServiceCompany,
                        'number' => $relocationServiceCompany->generateNumber()
                    ];
                }
            } else {
                $return = ['success' => true, 'data' => [], 'message' => 'WORKFLOW_GENERATED_TEXT'];
            }

            end:
            if ($return['success'] == true && isset($relocationServiceCompany)) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocationServiceCompany, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_UPDATE);
            }
            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getWorkflowApplyStatusAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();
        $return = ['success' => true];
        $uuid = Helpers::__getRequestValue('uuid');
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($uuid);
            //@TODO countServiceTaskTemplate <= countServiceTask => changeStatus;
            if ($relocationServiceCompany && $relocationServiceCompany->getWorkflowApplied() == RelocationServiceCompany::WORKFLOW_APPLIED_YES) {
                $return = ['success' => true, 'message' => 'WORKFLOW_GENERATED_TEXT'];
            } else {
                $return = ['success' => false, 'message' => 'WORKFLOW_GENERATED_IN_PROGRESS_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function needAssessmentListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'SERVICE_NOT_FOUND_TEXT'];


        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($uuid);

            if ($relocationServiceCompany && $relocationServiceCompany->belongsToGms()) {

                $result = $relocationServiceCompany->getAllNeedFormGabarit();

                if ($result['success'] == true) {
                    $need_assessment_array = [];
                    foreach ($result['data'] as $item) {
                        $need_assessment_array[$item->getId()] = $item->toArray();
                        $need_assessment_array[$item->getId()]['service_name'] = $relocationServiceCompany->getName();
                        $need_assessment_array[$item->getId()]['category_name'] = "";

                        $request = $item->getNeedFormRequestOfRelocationServiceCompany($relocationServiceCompany->getId());


                        // Load need assessment sections
                        $sections = NeedFormGabaritSection::find([
                            'conditions' => 'need_form_gabarit_id=:id:',
                            'bind' => [
                                'id' => $item->getId()
                            ],
                            'order' => 'position ASC']);
                        foreach ($sections as $section) {
                            $need_assessment_array[$item->getId()]['formBuilder'][$section->getPosition()] = $section->toArray();
                            $need_assessment_array[$item->getId()]['formBuilder'][$section->getPosition()]['index'] = (int)$section->getPosition();
                            $need_assessment_array[$item->getId()]['formBuilder'][$section->getPosition()]['goToSection'] = (int)$section->getNextSectionId();
                            $need_assessment_array[$item->getId()]['formBuilder'][$section->getPosition()]['items'] = [];

                            $items = $section->getDetailSectionContent();
                            $need_assessment_array[$item->getId()]['formBuilder'][$section->getPosition()]['items'] = $items;

                            // $items = NeedFormGabaritItem::find(['conditions' => 'need_form_gabarit_section_id=:id:',
                            //     'bind' => [
                            //         'id' => $section->getId()
                            //     ],
                            //     'order' => 'position ASC'
                            // ]);
                            // $i = 0;
                            // foreach ($items as $keyPosition => $question) {
                            //     $need_assessment_array[$item->getId()]['formBuilder'][$section->getPosition()]['items'][$keyPosition] = $question->getContent();
                            //     $i++;
                            // }
                        }
                        if ($request) {
                            $need_assessment_array[$item->getId()]['request_uuid'] = $request->getUuid();
                            $need_assessment_array[$item->getId()]['request_status'] = $request->getStatus();
                            $need_assessment_array[$item->getId()]['sent_on'] = $request->getSentOn();
                            $need_assessment_array[$item->getId()]['sent_on_time'] = is_string($request->getSentOn()) ? strtotime($request->getSentOn()) : $request->getSentOn();
                            $need_assessment_array[$item->getId()]['relocation_service_company_id'] = $request->getRelocationServiceCompanyId();
                            $need_assessment_array[$item->getId()]['relocation_service_company_uuid'] = $request->getRelocationServiceCompany()->getUuid();
                            if ($request->getStatus() == NeedFormRequest::STATUS_ANSWERED) {
                                $need_assessment_array[$item->getId()]['formBuilder'] = $request->getFormBuilderStructureAnswered($request);
                            } else {
                                $need_assessment_array[$item->getId()]['formBuilder'] = $request->getFormBuilderStructure();
                            }

                        } else {
                            $need_assessment_array[$item->getId()]['request_uuid'] = '';
                            $need_assessment_array[$item->getId()]['request_status'] = NeedFormRequest::STATUS_NOT_SENT;
                            $need_assessment_array[$item->getId()]['sent_on'] = '';
                            $need_assessment_array[$item->getId()]['sent_on_time'] = null;
                            $need_assessment_array[$item->getId()]['relocation_service_company_id'] = 0;
                            $need_assessment_array[$item->getId()]['relocation_service_company_uuid'] = '';
                        }

                        $need_assessment_array[$item->getId()]['relocation_service_company_id'] = $relocationServiceCompany->getId();
                        $need_assessment_array[$item->getId()]['relocation_service_company_uuid'] = $relocationServiceCompany->getUuid();
                        $need_assessment_array[$item->getId()]['relocation_uuid'] = $relocationServiceCompany->getRelocation()->getUuid();
                    }
                    $return = ['success' => true, 'data' => array_values($need_assessment_array)];
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
     * send need assessment form action
     */
    public function sendNeedAssessmentFormOfRelocationAction($uuid = '')
    {

        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);


        $id = Helpers::__getRequestValue('id');
        $relocation_id = Helpers::__getRequestValue('relocation_id');
        $service_company_id = Helpers::__getRequestValue('service_company_id');

        $relocation_service_company_uuid = Helpers::__getRequestValue('relocation_service_company_uuid');

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];


        $this->db->begin();
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $needFormRequest1 = NeedFormRequest::findFirstByUuid($uuid);
            $resultDelete = $needFormRequest1->__quickRemove();
            if (!$resultDelete['success'] == true) {
                $this->db->rollback();
                $return = $resultDelete;
                $return['message'] = 'SEND_NEED_ASSESSMENT_ERROR_TEXT';
                goto end_of_function;
            }
            //SEND
        }

        if (is_numeric($id) && $id > 0) {
            //should create request
            $needFormGabarit = NeedFormGabarit::findFirstById($id);

            if ($needFormGabarit && $needFormGabarit->belongsToGms()) {

                if ($relocation_service_company_uuid != '') {

                    $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($relocation_service_company_uuid);

                    $need_form_gabarit_service_company = NeedFormGabaritServiceCompany::findFirst([
                        "conditions" => "need_form_gabarit_id = :need_form_gabarit_id: and service_company_id = :service_company_id:",
                        "bind" => [
                            "service_company_id" => $relocationServiceCompany->getServiceCompanyId(),
                            "need_form_gabarit_id" => $needFormGabarit->getId()
                        ]
                    ]);
                    if (!$need_form_gabarit_service_company instanceof NeedFormGabaritServiceCompany || $need_form_gabarit_service_company->getIsDeleted() == Helpers::YES) {
                        $this->db->rollback();
                        $return['success'] = false;
                        $return['message'] = 'NEED_ASSESSMENT_NOT_BELONG_TO_SERVICE_TEXT';
                        goto end_of_function;
                    }
                    // $needFormRequest = $needFormGabarit->getNeedFormRequestOfRelocationServiceCompany($relocationServiceCompany->getId());

                    $needFormRequest = new NeedFormRequest();
                    $employeeProfile = $relocationServiceCompany->getRelocation()->getEmployee();
                    $res = $needFormRequest->__save([
                        'owner_company_id' => ModuleModel::$company->getId(),
                        'object_uuid' => $relocationServiceCompany->getUuid(),
                        'relocation_id' => $relocationServiceCompany->getRelocationId(),
                        'service_company_id' => $relocationServiceCompany->getServiceCompanyId(),
                        'relocation_service_company_id' => $relocationServiceCompany->getId(),
                        'user_profile_uuid' => $employeeProfile->getUuid(),
                        'need_form_gabarit_id' => $needFormGabarit->getId(),
                        'assignment_id' => $relocationServiceCompany->getRelocation()->getAssignmentId(),
                        'company_id' => $relocationServiceCompany->getRelocation()->getHrCompanyId(),
                        'need_form_category' => $needFormGabarit->getNeedFormCategory(),
                        'form_name' => $needFormGabarit->getName(),
                        'status' => NeedFormRequest::STATUS_NOT_SENT,
                        'viewed' => NeedFormRequest::VIEWED_YES,
                    ]);

                    if ($res instanceof NeedFormRequest) {
                        $needFormRequest = $res;
                    } else {
                        $needFormRequest = false;
                        $this->db->rollback();
                        $return = $res;
                    }

                }
            }
        }
        /*
         * @todo active user with permission to send
        if( isset($needFormRequest) &&
            $needFormRequest && $needFormRequest->getEmployee() &&
            $needFormRequest->getEmployee()->getActive() == Employee::ACTIVE_NO ){

            $return = [
                'success' => false,
                'message' => 'YOU_SHOULD_ACTIVE_EMPLOYEE_TO_SEND_NEED_ASSESMENT_FORM_TEXT',
                'data' => $needFormRequest,
            ];
            goto end_of_function;
        }
        */

        if (isset($needFormRequest) &&
            $needFormRequest &&
            $needFormRequest->getEmployee()) {
            //send and create new SQS or BeanStald
            $employeeProfile = $needFormRequest->getEmployee();
            $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
            $resultQueue = $beanQueue->addQueue([
                'action' => "sendMail",
                'to' => $employeeProfile->getWorkemail(),
                'params' => [
                    'fullname' => $employeeProfile->getFirstname() . " " . $employeeProfile->getLastname(),
                    'username' => ModuleModel::$user_profile->getFirstname() . " " . ModuleModel::$user_profile->getLastname(),
                    'fullname' => $employeeProfile->getFullname(),
                    'email' => $employeeProfile->getWorkemail(),
                    'request_uuid' => $needFormRequest->getUuid(),
                    'url' => $needFormRequest->getLandingUrl(),
                    'url_login' => $needFormRequest->getEmployeeLoginUrl(),
                    'relocation_uuid' => $needFormRequest->getRelocation()->getUuid(),
                    'relocation_url' => $needFormRequest->getRelocation()->getFrontendUrl(),
                    'relocation_number' => $needFormRequest->getRelocation() ? $needFormRequest->getRelocation()->getIdentify() : "",
                    'relocation_name' => $needFormRequest->getRelocation() ? $needFormRequest->getRelocation()->getIdentify() : "",
                    'service_name' => $needFormRequest->getServiceCompanyId() > 0 && $needFormRequest->getServiceCompany() ? $needFormRequest->getServiceCompany()->getName() : '',
                    'service_number' => $needFormRequest->getRelocationServiceCompany()->getNumber(),
                    'dsp_company_name' => ModuleModel::$company->getName()
                ],
                'templateName' => EmailTemplateDefault::SEND_NEEDS_ASSESSMENT,
                'language' => ModuleModel::$system_language
            ]);


            //
            $needFormRequest->setSentOn(date('Y-m-d H:i:s'));
            $needFormRequest->setStatus(NeedFormRequest::STATUS_SENT);

            $resultSaveNeedForm = $needFormRequest->__quickUpdate();
            if ($resultSaveNeedForm['success'] == true) {

                $resultPusher = PushHelper::__sendEvent($employeeProfile->getUuid(), 'reload_questionnaire', []);
                $return = [
                    'success' => true,
                    'urlLanding' => $needFormRequest->getLandingUrl(),
                    '$resultPusher' => $resultPusher,
                    'message' => 'SEND_NEED_ASSESSMENT_SUCCESS_TEXT',
                    'data' => $needFormRequest,
                    'sendMail' => $resultQueue,
                ];
            } else {
                $this->db->rollback();
                $return = [
                    'success' => false,
                    'message' => 'SEND_NEED_ASSESSMENT_ERROR_TEXT',
                    'detail' => $resultSaveNeedForm,
                    'sendMail' => $resultQueue,
                ];
            }
        }

        end_of_function:

        if ($return['success'] == true) {
            ModuleModel::$needFormRequest = $needFormRequest;
            $beanQueue = new RelodayQueue(getenv('QUEUE_WEBHOOK_WORKER'));

            $resultQueue = $beanQueue->addQueue([
                'action' => "create",
                'params' => [
                    'uuid' => $needFormRequest->getUuid(),
                    'object_type' => $needFormRequest->getSource(),
                    'action' => 'create',
                    'action_display' => 'send'
                ]
            ]);
            $this->dispatcher->setParam('return', $return);
            $this->db->commit();
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @return [type] [description]
     */
    public function getRemindersAction($relocationServiceUuid = '')
    {

        $this->view->disable();
        $this->checkAjaxGet();

        $this->checkAclIndex(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_SERVICE_NOT_FOUND_TEXT'];
        if (!Helpers::__isValidUuid($relocationServiceUuid)) {
            $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_SERVICE_NOT_FOUND_TEXT'];
            goto end;
        }

        $relocation_service_company = RelocationServiceCompany::findFirstByUuid($relocationServiceUuid);
        if ($relocation_service_company &&
            $relocation_service_company->belongsToGms() &&
            $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE
        ) {

            //@TODO reminder DynamoReminderConfig >> ReminderConfig

            $return = ReminderConfig::__findWithFilters([
                'assignment_id' => $relocation_service_company->getAssignment()->getId(),
                'relocation_id' => $relocation_service_company->getRelocationId(),
                'relocation_service_company_id' => $relocation_service_company->getId(),
                'limit' => 1000
            ]);

        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Allow switching is_visit_allowed
     */
    public function changeVisitAllowedAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);
        $return = ['success' => false, 'message' => 'CHANGE_VISIT_ALLOWED_FAILED_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid ');
        $is_visit_allowed = Helpers::__getRequestValue('is_visit_allowed');
        if ($uuid != '' && Helpers::__isValidUuid($uuid) && isset($is_visit_allowed) && is_bool($is_visit_allowed)) {
            $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($uuid);
            if ($relocationServiceCompany &&
                $relocationServiceCompany->belongsToGms() &&
                $relocationServiceCompany->isActive()) {
                $relocationServiceCompany->setIsVisitAllowed(intval($is_visit_allowed));
                $return = $relocationServiceCompany->__quickUpdate();
                if ($return['success'] == false) {
                    goto end_of_function;
                }
                $return = ['success' => true, 'message' => 'CHANGE_VISIT_ALLOWED_SUCCESS_TEXT'];
            }
        }
        end_of_function:
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

        $service_uuid = Helpers::__getRequestValue("uuid");

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_SERVICE_TEXT', 'detail' => $service_uuid];

        if ($service_uuid != '' && Helpers::__isValidUuid($service_uuid)) {

            $service = RelocationServiceCompany::findFirstByUuid($service_uuid);

            $this->db->begin();

            /*** add reporter profile **/
            $report_user_profile_uuid = Helpers::__getRequestValue("report_user_profile_uuid");
            if ($report_user_profile_uuid != '' && Helpers::__isValidUuid($report_user_profile_uuid)) {
                $profile = UserProfile::findFirstByUuid($report_user_profile_uuid);
                $reporter = DataUserMember::getDataReporter($service_uuid);
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
                    // I
                } else {

                    if ($reporter) {
                        //delete current reporter if existed
                        $returnDeleteReporter = DataUserMember::deleteReporters($service_uuid);

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
                        $service_uuid,
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
                $profile = UserProfile::findFirstByUuid($owner_user_profile_uuid);
                $owner = DataUserMember::getDataOwner($service_uuid);
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
                } else {
                    if ($owner) {
                        //delete current owner
                        $returnDeleteOwner = DataUserMember::deleteOwners($service_uuid);

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
                    $returnAddOwner = DataUserMember::addOwner($service_uuid, $profile, DataUserMember::MEMBER_TYPE_OBJECT_TEXT);
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
                                    'object_uuid' => $service_uuid,
                                    'user_profile_uuid' => $user->getUuid(),
                                    'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                                ]
                            ]);

                            if (!$data_user_member) {
                                $returnCreate = DataUserMember::addViewer($service_uuid, $user);

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
            }

            // SERVICE EVENTS
            $serviceCompany = $service->getServiceCompany();

            $events = $serviceCompany->getServiceEvents(['base = ' . ServiceEvent::BASE_EVENT_ACTIVE]);

            foreach ($events as $event) {

                if ($event->getCode() == 'SERVICE_START' && Helpers::__getRequestValue("start_date") != '') {
                    $resultSaveEventStart = $this->_saveEvent($service->getId(), $event->getId(), Helpers::__getRequestValue("start_date"));
                    if (!$resultSaveEventStart['success']) {
                        $return = $resultSaveEventStart;
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $this->dispatcher->setParam('isHistoryUpdate', true);
                }

                if ($event->getCode() == 'SERVICE_END' && Helpers::__getRequestValue("end_date") != '') {
                    $resultSaveEventEnd = $this->_saveEvent($service->getId(), $event->getId(), Helpers::__getRequestValue("end_date"));
                    if (!$resultSaveEventEnd['success']) {
                        $return = $resultSaveEventEnd;
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $this->dispatcher->setParam('isHistoryUpdate', true);
                }

                if ($event->getCode() == 'AUTHORISED' && Helpers::__getRequestValue("authorised_date") != '') {
                    $resultSaveEventAuthorised = $this->_saveEvent($service->getId(), $event->getId(), Helpers::__getRequestValue("authorised_date"));
                    if (!$resultSaveEventAuthorised['success']) {
                        $return = $resultSaveEventAuthorised;
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $this->dispatcher->setParam('isHistoryUpdate', true);
                }

                if ($event->getCode() == 'COMPLETION' && Helpers::__getRequestValue("completion_date") != '') {
                    $resultSaveEventCompletion = $this->_saveEvent($service->getId(), $event->getId(), Helpers::__getRequestValue("completion_date"));
                    if (!$resultSaveEventCompletion['success']) {
                        $return = $resultSaveEventCompletion;
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $this->dispatcher->setParam('isHistoryUpdate', true);
                }

                if ($event->getCode() == 'EXPIRY' && Helpers::__getRequestValue("expiry_date") != '') {
                    $resultSaveEventExpiry = $this->_saveEvent($service->getId(), $event->getId(), Helpers::__getRequestValue("expiry_date"));
                    if (!$resultSaveEventExpiry['success']) {
                        $return = $resultSaveEventExpiry;
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $this->dispatcher->setParam('isHistoryUpdate', true);
                }
            }

            $this->db->commit();

            $return = [
                'success' => true,
                'message' => 'SERVICE_EDIT_SUCCESS_TEXT',
                'data' => $service,
            ];

            //Sync owner if preference ticked (is_sync_owner)
            if (isset($profile) && isset($owner) && $profile && $owner && $profile->getUuid() != $owner->getUuid()) {
                if (ModuleModel::$user_profile->getIsSyncOwner() == ModelHelper::YES) {
                    $return['$apiQueueResults'] = $service->startSyncTaskOwner($profile);
                }
            }


        }
        end_of_function:

        if ($return['success'] == true && isset($service)) {
            ModuleModel::$relocationServiceCompany = $service;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($service, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_UPDATE);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Allows save a service event
     *
     * @param $serviceId
     * @param $eventId
     * @param $eventValue
     * @return array
     */
    private function _saveEvent($serviceId, $eventId, $eventValue)
    {
        $result = [
            'success' => true,
            'message' => 'EVENT_SAVE_SUCCESS_TEXT'
        ];

        $eventObject = ServiceEventValue::__findByServiceAndEventWithCheck($serviceId, $eventId);

        $eventObject->setData([
            'relocation_service_company_id' => $serviceId,
            'service_event_id' => $eventId,
            'value' => is_numeric($eventValue) && $eventValue > 0 ? $eventValue : ($eventValue == null || $eventValue == '' ? null : strtotime($eventValue))
        ]);

        $checkReminder = false;
        if (($eventObject->hasSnapshotData() == true && $eventObject->hasChanged()) || $eventObject->hasSnapshotData() == false) {
            $checkReminder = true;
        }

        $resultSaveEvent = $eventObject->__quickSave();

        if ($resultSaveEvent['success'] == false) {
            $result = $resultSaveEvent;
            goto end_of_function;
        }

        if ($checkReminder == true) {
            $resultSaveReminderConfig = $eventObject->updateReminderConfigData();
            if ($resultSaveReminderConfig['success'] == false) {
                $result = $resultSaveReminderConfig;
                goto end_of_function;
            }
        }

        end_of_function:
        return $result;

    }

    /**
     * Action save personal list order setting
     * @return array
     */
    public function saveListOrderSettingAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $result = [
            'success' => false,
            'message' => 'SAVE_LIST_ORDER_SETTING_FAIL_TEXT'
        ];

        $objectType = Helpers::__getRequestValue("object_type");
        $objectUuid = Helpers::__getRequestValue("object_uuid");
        $listType = Helpers::__getRequestValue("list_type");
        $orderSetting = Helpers::__getRequestValue("order_setting");

        if (!$objectType || !$objectUuid || !$orderSetting || !$listType) {
            goto end_of_function;
        }

        $listOrderSetting = ListOrderSetting::findFirst(
            [
                'conditions' => 'object_type = :object_type: AND object_uuid = :object_uuid: AND list_type = :list_type:',
                'bind' => [
                    'object_type' => $objectType,
                    'object_uuid' => $objectUuid,
                    'list_type' => $listType
                ]
            ]
        );

        if (!$listOrderSetting) {
            $listOrderSetting = new ListOrderSetting();
            $listOrderSetting->setCompanyId(ModuleModel::$company->getId());
            $listOrderSetting->setObjectType($objectType);
            $listOrderSetting->setObjectUuid($objectUuid);
            $listOrderSetting->setListType($listType);
            $listOrderSetting->setOrderSetting($orderSetting);
            $listOrderSetting->setCreatedAt(date('Y-m-d H:i:s'));
        } else {
            $listOrderSetting->setOrderSetting($orderSetting);
        }

        if (!$listOrderSetting->save()) {
            foreach ($listOrderSetting->getMessages() as $message) {
                $result['detail'][] = $message->getMessage();
            }
            goto end_of_function;
        }

        $result = [
            'success' => true,
            'message' => 'SAVE_LIST_ORDER_SETTING_SUCCESS_TEXT'
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Action retrieve personal list order setting
     * @return array
     */
    public function getListOrderSettingAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $result = [
            'success' => false,
            'message' => 'GET_LIST_ORDER_SETTING_FAIL_TEXT'
        ];

        $objectType = Helpers::__getRequestValue("object_type");
        $objectUuid = Helpers::__getRequestValue("object_uuid");
        $listType = Helpers::__getRequestValue("list_type");

        if (!$objectType || !$objectUuid || !$listType) {
            goto end_of_function;
        }

        $listOrderSetting = ListOrderSetting::findFirst(
            [
                'conditions' => 'object_type = :object_type: AND object_uuid = :object_uuid: AND list_type = :list_type:',
                'bind' => [
                    'object_type' => $objectType,
                    'object_uuid' => $objectUuid,
                    'list_type' => $listType
                ]
            ]
        );

        $data = '';

        if ($listOrderSetting) {
            $data = $listOrderSetting->getOrderSetting();
        }

        $result = [
            'success' => true,
            'message' => 'GET_LIST_ORDER_SETTING_SUCCESS_TEXT',
            'data' => $data
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function preCreateServicesAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $services = Helpers::__getRequestValueAsArray("services");
        $serviceGeneratedList = [];
        if (is_array($services) && count($services) > 0) {
            foreach ($services as $service) {
                if (isset($service['id']) && Helpers::__isValidId($service['id'])) {
                    $serviceObject = ServiceCompany::findFirstById($service['id']);
                    if ($serviceObject && $serviceObject->belongsToGms() && $serviceObject->isArchived() == false) {
                        $temporaryObjectUuid = Helpers::__uuid();
                        $resultTemporaryObject = RelodayObjectMapHelper::__createObject($temporaryObjectUuid, RelodayObjectMapHelper::TABLE_RELOCATION_SERVICE, false);
                        if ($resultTemporaryObject['success'] == true) {

                            $serviceItem = [
                                'uuid' => $temporaryObjectUuid,
                                'table_name' => RelodayObjectMapHelper::TABLE_RELOCATION_SERVICE,
                                'service_company_id' => $serviceObject->getId(),
                                'service_name' => $serviceObject->getName(),
                            ];

                            $serviceItem['need_spouse'] = false;
                            $serviceItem['need_child'] = false;
                            $serviceItem['need_dependant'] = false;
                            $serviceItem['need_participant'] = false;
                            if ($serviceObject->getTargetRelation() == Service::TARGET_RELATION_CHILD) {
                                $serviceItem['need_child'] = true;
                                $serviceItem['need_participant'] = true;
                            }
                            if ($serviceObject->getTargetRelation() == Service::TARGET_RELATION_SPOUSE) {
                                $serviceItem['need_spouse'] = true;
                                $serviceItem['need_participant'] = true;
                            }
                            if ($serviceObject->getTargetRelation() == Service::TARGET_RELATION_ALL) {
                                $serviceItem['need_dependant'] = true;
                                $serviceItem['need_participant'] = true;
                            }

                            $serviceItem['is_selected'] = true;

                            $serviceGeneratedList[] = $serviceItem;
                        }
                    }
                }
            }
        }

        $result = [
            'success' => true,
            'data' => $serviceGeneratedList
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function preCreateServicesFromServicePackAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $servicePacks = Helpers::__getRequestValueAsArray("servicePacks");
        $serviceGeneratedList = [];
        if (is_array($servicePacks) && count($servicePacks) > 0) {
            foreach ($servicePacks as $servicePack) {
                if (isset($servicePack['id']) && Helpers::__isValidId($servicePack['id'])) {
                    $servicePackObject = ServicePack::findFirstById($servicePack['id']);
                    if ($servicePackObject && $servicePackObject->belongsToGms() && $servicePackObject->isDeleted() == false) {

                        $services = $servicePackObject->getServiceCompanies();
                        foreach ($services as $serviceObject) {
                            if ($serviceObject && $serviceObject->belongsToGms() && $serviceObject->isArchived() == false) {
                                $temporaryObjectUuid = Helpers::__uuid();
                                $resultTemporaryObject = RelodayObjectMapHelper::__createObject($temporaryObjectUuid, RelodayObjectMapHelper::TABLE_RELOCATION_SERVICE, false);
                                if ($resultTemporaryObject['success'] == true) {
                                    $serviceItem = [
                                        'uuid' => $temporaryObjectUuid,
                                        'table_name' => RelodayObjectMapHelper::TABLE_RELOCATION_SERVICE,
                                        'service_company_id' => $serviceObject->getId(),
                                        'service_name' => $serviceObject->getName(),
                                    ];

                                    $serviceItem['need_spouse'] = false;
                                    $serviceItem['need_child'] = false;
                                    $serviceItem['need_dependant'] = false;
                                    $serviceItem['need_participant'] = false;

                                    if ($serviceObject->getServiceId() == Service::PARTNER_SUPPORT_SERVICE) {
                                        $serviceItem['need_spouse'] = true;
                                        $serviceItem['need_participant'] = true;
                                    }
                                    if ($serviceObject->getServiceId() == Service::SCHOOL_SEARCH_SERVICE) {
                                        $serviceItem['need_child'] = true;
                                        $serviceItem['need_participant'] = true;
                                    }
                                    if ($serviceObject->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                                        $serviceItem['need_dependant'] = true;
                                        $serviceItem['need_participant'] = true;
                                    }

                                    $serviceItem['is_selected'] = true;

                                    $serviceGeneratedList[] = $serviceItem;
                                }
                            }
                        }
                    }
                }
            }
        }

        $result = [
            'success' => true,
            'data' => $serviceGeneratedList,
            'servicePack' => isset($servicePackObject) ? $servicePackObject : null,
            'servicePacks' => $servicePacks
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function searchServicesAction()
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
        $params['relocation_id'] = Helpers::__getRequestValue('relocation_id');
        $params['relocation_uuid'] = Helpers::__getRequestValue('relocation_uuid');

        if ($params['active'] === null || $params['active'] === '') {
            $params['active'] = RelocationServiceCompanyExt::STATUS_ACTIVE;
        }

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
        $hrCompany = CompanyExt::__findFirstByIdWithCache($hrCompanyId);
        if ($hrCompany) {
            if ($hrCompany->getCompanyTypeId() == CompanyExt::TYPE_BOOKER) {
                $bookersIds[] = $hrCompanyId;
                unset($params['hr_company_id']);
            } else if ($hrCompany->getCompanyTypeId() == CompanyExt::TYPE_HR) {
                $params['company_id'] = $hrCompanyId;
            }
        }

        $company_id = Helpers::__getRequestValue('company_id');
        $company = Company::findFirstById($company_id);
        if ($company instanceof Company && $company->getCompanyTypeId() == CompanyExt::TYPE_BOOKER) {
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
                    if ($company instanceof Company && $company->getCompanyTypeId() == CompanyExt::TYPE_HR) {
                        $companiesIds[] = $item['id'];
                    } else if ($company instanceof Company && $company->getCompanyTypeId() == CompanyExt::TYPE_BOOKER) {
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

        $return = RelocationServiceCompany::__findWithFilter($params, $ordersConfig);

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


    public function getReportsAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('report');

        $res = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!$uuid) {
            goto end_of_function;
        }

        $relocationService = RelocationServiceCompany::findFirstByUuid($uuid);

        if (!$relocationService ||
            !$relocationService->belongsToGms() ||
            $relocationService->getStatus() != RelocationServiceCompany::STATUS_ACTIVE) {
            goto end_of_function;
        }

        $listEx = Report::listNeedRemove($uuid, ReportExt::TYPE_DSP_EXPORT_RELOCATION_SERVICE);

        foreach ($listEx as $reportEx) {
            if ($reportEx instanceof Report) {
                $res = $reportEx->__quickRemove();
            }
        }

        $result = Report::loadList([
            'object_uuid' => $uuid,
            'type' => ReportExt::TYPE_DSP_EXPORT_RELOCATION_SERVICE
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

        $res = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $this->db->begin();

        $phpDateTimeFormat = ModuleModel::$company->getPhpDateFormat(true, 'H:i:s', '/');
        $phpDateFormat = ModuleModel::$company->getPhpDateFormat();

        $objectUuid = Helpers::__getRequestValue("object_uuid");
        $params = Helpers::__getRequestValueAsArray("params");

        if (!$objectUuid || !Helpers::__isValidUuid($objectUuid)) {
            $this->db->rollback();
            goto end_of_function;
        }

        $relocationService = RelocationServiceCompany::findFirstByUuid($objectUuid);
        if (!$relocationService || !$relocationService->belongsToGms()) {
            $this->db->rollback();
            goto end_of_function;
        }

        $canExportReport = Report::canExportReport($objectUuid, ReportExt::TYPE_DSP_EXPORT_RELOCATION_SERVICE);
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
        $report->setName($relocationService->getName() . $now . '.xlsx');
        $report->setObjectUuid($objectUuid);
        $report->setCreatorUuid(ModuleModel::$user_profile->getUuid());
        $report->setStatus(ReportExt::STATUS_IN_PROCESS);
        $report->setExpiredAt(date('Y-m-d H:i:s', $now + ReportExt::EXPIRED_TIME));
        $report->setParams(json_encode($params));
        $report->setType(ReportExt::TYPE_DSP_EXPORT_RELOCATION_SERVICE);

        $result = $report->__quickCreate();

        if ($result['success']) {
            $dataArray = $relocationService->toArray();
            $dataArray['progress'] = $relocationService->getEntityProgressValue();
            $dataArray['progress_status'] = $relocationService->getEntityProgressStatus();
            $dataArray['service_id'] = $relocationService->getServiceCompany() ? (int)$relocationService->getServiceCompany()->getServiceId() : null;
            $dataArray['is_home_search'] = $relocationService->isHomeSearch();
            $dataArray['relocation_uuid'] = $relocationService->getRelocation() ? $relocationService->getRelocation()->getUuid() : null;
            $dataArray['relocation_cancelled'] = $relocationService->getRelocation() ? $relocationService->getRelocation()->isCancelled() : false;
            $viewers = DataUserMember::__getDataViewers($objectUuid);
            $company = $relocationService->getCompany();
            $dataArray['number'] = $relocationService->getNumber();
            $svp = $relocationService->getServiceProviderCompany();
            $dataArray['svp_name'] = $svp ? $svp->getName() : '';
            $svpMember = $relocationService->getSvpMember();

            if ($svpMember) {
                $dataArray['svp_member_name'] = $svpMember->getFirstName() . ' ' . $svpMember->getLastName();

            }
            $service_company = $relocationService->getServiceCompany();
            $detailArray = [];
            $specificDetailArray = [];
            if ($service_company) {
                $events = $service_company->getServiceEvents(['base = ' . ServiceEvent::BASE_EVENT_ACTIVE]);
                foreach ($events as $event) {
                    $item = $event->toArray();
                    $item['value'] = $event->getEventValue($relocationService->getId());

                    $detailArray[] = [
                        "label" => $item['label'],
                        "value" => $item['value'] ? date($phpDateFormat, $item['value']) : '',
                    ];
                }


                $fields = $service_company->getServiceFields([
                    'order' => 'position ASC',
                ]);

                foreach ($fields as $field) {
                    $specificDetailArray[] = $this->getServiceFieldData($field, $relocationService->getId(), $phpDateFormat);
                }
            }

            $dataArray['details'] = $detailArray;
            $dataArray['company'] = $company->toArray();
            $dataArray['company_id'] = $company->getId();
            $dataArray['owner_name'] = $relocationService->getOwner() ? $relocationService->getOwner()->getFullname() : '';
            $dataArray['owner_uuid'] = $relocationService->getOwner() ? $relocationService->getOwner()->getUuid() : '';
            $reporter = DataUserMember::__getDataReporter($objectUuid, ModuleModel::$company->getId());
            $dataArray['reporter_name'] = $reporter ? $reporter->getFullname() : '';

            if ($relocationService->getRelocation()) {
                $assignment = $relocationService->getRelocation()->getAssignment();
                if ($assignment) {
                    $assignmentArr = $assignment->getInfoDetailInArray();
                    $dataArray['assignment_uuid'] = $relocationService->getAssignment() ? $relocationService->getAssignment()->getUuid() : '';
                    $dataArray['assignment_name'] = $relocationService->getAssignment() ? $relocationService->getAssignment()->getName() : '';
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

                    foreach ($dependants as $dependant) {
                        $dataArray['employee']['dependants'][] = $dependant['firstname'] . ' ' . $dependant['lastname'];
                    }
                }
            }

            $dataArray['viewers'] = [];
            foreach ($viewers as $key => $viewer) {
                $dataArray['viewers'][$key] = $viewer->getFirstname() . ' ' . $viewer->getLastname();
            }

            $tasks = [];
            if (isset($params['task_ids']) && count($params['task_ids']) > 0) {
                $taskIds = [];

                foreach ($params['task_ids'] as $id => $item) {
                    $taskIds[] = (int)$id;
                }

                $tasks = Task::__loadList([
                    "isArray" => true,
                    "company_id" => ModuleModel::$company->getId(),
                    'ids' => $taskIds,
                    'task_type' => 'all'
                ], true);
            }

            $exportData = [
                'params' => [
                    'data' => $dataArray,
                    'specificInfo' => $specificDetailArray,
                    'tasks' => $tasks,
                    'condition' => $params,
                    'report' => $result['data']->toArray(),
                    'company_uuid' => ModuleModel::$company->getUuid(),
                ],
                'language' => ModuleModel::$language,
                'action' => RelodayQueue::ACTION_EXPORT_REPORT_RELOCATION_SERVICE
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

    private function getServiceFieldData(ServiceField $field, $relocationServiceId, $dateFormat = 'd/m/Y')
    {
        $item = $field->toArray();
        $item['value'] = $field->getFieldValue($relocationServiceId);
        $item['type'] = $field->getDataTypeName();

        if ($field->getServiceFieldType() && $field->getServiceFieldType()->getName()) {
            $item['type'] = $field->getServiceFieldType()->getName();
        }
        $value = $item['value'];

        switch (strtoupper($item['type'])) {
            case 'YESNO':
                if ($item['value'] == Helpers::YES) {
                    $value = ConstantExt::__translateConstant('YES_TEXT', ModuleModel::$language) ?: 'Yes';
                } else {
                    $value = ConstantExt::__translateConstant('NO_TEXT', ModuleModel::$language) ?: 'No';
                }
                break;
            case 'ATTRIBUTES':
                if ($item['value']) {
                    $valuesSplit = preg_split("/_/", '31_151', -1, 0);
                    if ($valuesSplit && count($valuesSplit) > 1) {
                        $attribute = AttributesValue::findFirstById((int)$valuesSplit[1]);
                        if ($attribute instanceof AttributesValue) {
                            $value = $attribute->getValue() ?: '';
                        }
                    }
                }

                break;
            case 'DATE':
                $value = $item['value'] ? date($dateFormat, $item['value']) : '';
                break;
            case 'PROPERTY_SELECT':
                if ($item['value']) {
                    $property = Property::findFirstByUuid($item['value']);
                    if ($property instanceof Property) {
                        $value = $property->getNumber() . ' - ' . $property->getName() . ' - ' . $property->getRentCurrency();
                    }
                }
                break;
            case 'PRICE':
                if ($item['value']) {
                    $value = str_replace('#', ' ', $item['value']);
                }
                break;
            case 'DATETIME':
                $value = $item['value'] ? date($dateFormat . '-H:ieP', $item['value']) : '';
                break;
            case 'TIME':
                $value = $item['value'] ? date('H:i', strtotime($item['value'])) : '';
                break;
            case 'POLICIES SELECTOR':
                if ($value) {
                    $policy = Policy::findFirstById($value);
                    if ($policy instanceof Policy) {
                        $value = $policy->getName();
                    }
                }
                break;
            case 'SVP_PROVIDER':
            case 'PROPERTY_LANDLORD_AGENTS':
                if ($value) {
                    $svpCompany = ServiceProviderCompany::findFirstById($value);
                    if ($svpCompany instanceof ServiceProviderCompany) {
                        $value = $svpCompany->getName();
                    }
                }
                break;
            case 'COUNTRY':
                if ($value) {
                    $country = Country::findFirstById($value);
                    if ($country instanceof Country) {
                        $value = $country->getName();
                    }
                }
                break;
            case 'NUMBER':
            case 'CURRENCY':
            case 'TEXTAREA':
            case 'NATIONALITY':
            case 'ATTACHMENTS':
            case 'EMAIL':
            case 'EMPLOYEE_BIRTHDATE_PREFILLED':
            case 'EMPLOYEE_CITIZENSHIP_PREFILLED':
            case 'EMPLOYEE_JOBTILE_PREFILLED':
            case 'EMPLOYEE_DEPENDANT_NAME_PREFILLED':
            case 'EMPLOYEE_DEPENDANT_BIRHTDATE_PREFILLED':
            case 'EMPLOYEE_DEPENDANT_CITIZENSHIP_PREFILLED':
            case 'COMMENTS':
            case 'TEXT':
            default:
                break;
        }

        return [
            "label" => $item['label_constant'],
            "value" => $value ? (is_numeric($value) ? "'" . $value : $value) : ''
        ];
    }

    public function saveInfoAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit(AclHelper::CONTROLLER_RELOCATION_SERVICE);

        $field_value = Helpers::__getRequestValue('field');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        if (!($uuid != '' && Helpers::__isValidUuid($uuid)) && $field_value->id) {
            goto end_of_function;
        }

        $relocation_service_company = RelocationServiceCompany::findFirstByUuid($uuid);

        if (!($relocation_service_company &&
            $relocation_service_company->belongsToGms() &&
            $relocation_service_company->getStatus() == RelocationServiceCompany::STATUS_ACTIVE)
        ) {
            goto end_of_function;
        }

        $field = ServiceField::findFirstById($field_value->id);
        if (!$field) {
            goto  end_of_function;
        }

        $service_company = $relocation_service_company->getServiceCompany();
        if ($service_company) {
            if ($field->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATE ||
                $field->getServiceFieldTypeId() == ServiceFieldType::TYPE_DATETIME) {

                if (Helpers::__isDate($field_value->value)) {
                    $field_value->value = strtotime($field_value->value);
                }
            }


            $this->db->begin();

            $newServiceFieldValue = ServiceFieldValue::__getByServiceAndFieldWithCheck($relocation_service_company->getId(), $field->getId());
            $newServiceFieldValue->setData([
                'relocation_service_company_id' => $relocation_service_company->getId(),
                'service_field_id' => $field->getId(),
                'service_event_id' => $field->getServiceEventId(),
                'value' => $field_value->value,
            ]);

            if ($newServiceFieldValue->getId() > 0) {
                $resultSaveServiceFieldValue = $newServiceFieldValue->__quickUpdate();
            } else {
                $resultSaveServiceFieldValue = $newServiceFieldValue->__quickCreate();
            }

            if (!$resultSaveServiceFieldValue['success']) {
                $this->db->rollback();
                $return = [
                    'input' => $field_value->value,
                    'success' => false,
                    'message' => 'SAVE_INFOS_FAIL_TEXT',
                    'detail' => $resultSaveServiceFieldValue['detail'],
                ];
                goto end_of_function;
            }

            if ($field->getServiceEventId() > 0) {

                $eventObject = ServiceEventValue::__findByServiceAndEventWithCheck($relocation_service_company->getId(), $field->getServiceEventId());
                $eventObject->setData([
                    'relocation_service_company_id' => $relocation_service_company->getId(),
                    'service_event_id' => $field->getServiceEventId(),
                    'value' => is_numeric($field_value->value) && $field_value->value > 0 ? $field_value->value : strtotime($field_value->value)
                ]);

                $checkReminder = false;
                if (($eventObject->hasSnapshotData() && $eventObject->hasChanged()) || !$eventObject->hasSnapshotData()) {
                    $checkReminder = true;
                }

                $resultSaveEventTime = $eventObject->__quickSave();
                if (!$resultSaveEventTime['success']) {

                    $this->db->rollback();
                    $return = [
                        'success' => false,
                        'message' => 'SAVE_EVENTS_FAIL_TEXT',
                        'detail' => $resultSaveEventTime['detail']
                    ];
                    goto end_of_function;
                }
                if ($checkReminder) {
                    $resultSaveReminderConfig = $eventObject->updateReminderConfigData();
                    if (!$resultSaveReminderConfig['success']) {
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

            try {
                $this->db->commit();
            } catch (\PDOException $e) {
                die('commit failed');
            } catch (Exception $e) {
                die('commit failed');
            }

            $return = [
                'input' => $field_value->value,
                'success' => true,
                'message' => 'SAVE_INFOS_SUCCESS_TEXT',
            ];

            $test = ServiceFieldValue::__getByServiceAndFieldWithCheck($relocation_service_company->getId(), 1);
            $return['test'] = $test;
        }

        end_of_function:

        if ($return['success'] == true && isset($relocation_service_company)) {
            ModuleModel::$relocationServiceCompany = $relocation_service_company;
            $this->dispatcher->setParam('return', $return);
            $return['$apiResults'] = NotificationServiceHelper::__addNotification($relocation_service_company, HistoryModel::TYPE_SERVICE, HistoryModel::HISTORY_UPDATE);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getRecentRelocationServicesAction(){
        $this->view->disable();
        $this->checkAjaxPost();

        $limit = Helpers::__getRequestValue('limit');

        $return = RelocationServiceCompany::__getRecentRelocationServices([
            'limit' => $limit ?: 10,
        ]);


        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
