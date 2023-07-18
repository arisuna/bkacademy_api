<?php

namespace Reloday\Gms\Controllers;

use Reloday\Application\Controllers\ApplicationApiController;
use Phalcon\Config;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\EmployeeActivityHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\HttpStatusCode;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RequestHeaderHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\EmployeeActivityExt;
use Reloday\Application\Models\UserLoginExt;
use Reloday\Gms\Help\HistoryHelper;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Help\SendHistoryHelper;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\EntityProgress;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HousingProposition;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\UserLoginToken;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\WebhookConfigurationMap;
use Reloday\Gms\Models\WebhookConfiguration;
use Reloday\Gms\Models\Webhook;
use Reloday\Gms\Models\SubscriptionAddon;
use Reloday\Gms\Models\Subscription;
use Reloday\Gms\Models\Addon;
use Reloday\Gms\Module;

/**
 * Base class of Gms module API controller
 */
class ModuleApiController extends ApplicationApiController
{
    /**
     *  set cors config
     */
    public function afterExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkPrelightRequest();
        $this->sendEmployeeActivityFeed($dispatcher);
        SendHistoryHelper::__sendHistory($dispatcher);
        $this->sendWebhook($dispatcher);
        $this->sendWebhookToOtherCompany($dispatcher);
    }

    /**
     * @return bool
     */
    public function checkLogin()
    {

        if ($this->request->isAjax()) {
            $headers = array_change_key_case($this->request->getHeaders());
            if (array_key_exists('token-key', $headers)) {
                $token_key = $headers['token-key'];
            }
            $login = UserLoginToken::findFirst("token='" . addslashes($token_key) . "'");
            if (!$login instanceof UserLoginToken) {
                return false;
            } else {
                if (strtotime($login->getExpiredAt()) < time()) {
                    return false;
                } else {
                    // Update expired time
                    $login->setExpiredAt(date('Y-m-d H:i:s', time() + $this->config->application->session_timeout * 3600));
                    $login->save();
                    $this->login = $login->login;
                    return true;
                }
            }
        } // Validate login
    }

    /**
     * check authu of user
     * @param string $msg
     * @return bool
     * //TODO : use JWT in the future
     */
    public function checkAuthMessage($auth = [])
    {
        $controller = $this->router->getControllerName();
        $action = $this->router->getActionName();
        //not apply for loginAction
        if (in_array($controller, ['index', 'auth']) && $action == 'login') {
            // Not redirect form
        } else {

            $this->blockActionCompanyDesactived();

            if ($auth['success'] == false) {
                if ($this->request->isAjax()) {
                    $this->view->disable();
                    $this->response->setStatusCode(HttpStatusCode::HTTP_UNAUTHORIZED, HttpStatusCode::getMessageForCode(HttpStatusCode::HTTP_UNAUTHORIZED));
                    $this->response->setJsonContent([
                        'success' => false,
                        'auth' => $auth,
                        'method' => __METHOD__,
                        'cache' => ApplicationModel::__getJwtKeyCache(),
                        'message' => 'ERROR_SESSION_EXPIRED_TEXT',
                        'required' => 'login', // If has parameter, page need reload or redirect to login page,
                        'url_redirect' => '/gms/#/login',
                    ]);
                    $this->response->send();
                    exit();
                } else {
                    $url = $this->router->getRewriteUri();
                    if (substr($url, 0, 1) == '/') {
                        $url = substr($url, 1);
                    }
                    $this->response->redirect('/gms/#/login?returnUrl=' . base64_encode($url));
                    return false;
                }
            }
        }
    }

    /**
     * check block compapny desactivated
     */
    public function blockActionCompanyDesactived()
    {
        if (ModuleModel::$company && !is_null(ModuleModel::$company) && ModuleModel::$company->isActive() == false) {
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'COMPANY_DESACTIVATED_TEXT',
                'required' => 'login', // If has parameter, page need reload or redirect to login page,
                'url_redirect' => '/gms/#/login',
            ]);
            $this->response->send();
            exit();
        }
    }

    /**
     * send Employee Activity Feed
     */
    public function sendEmployeeActivityFeed(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();
        //assignment
        if ($dispatcher->getControllerName() == 'assignment') {

            //create assignment
            if ($dispatcher->getActionName() == 'create') {

                if (ModuleModel::$assignment && ModuleModel::$assignment->getEffectiveStartDate() != '') {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$assignment->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment->getUuid(),
                            'object_uuid' => ModuleModel::$assignment->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNMENT_START_DATE,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                            'creator_company_id' => ModuleModel::$company->getId(),
                        ],
                        [
                            'date' => Helpers::__convertDateToSecond(ModuleModel::$assignment->getEffectiveStartDate()),
                            'date_time' => date('d-m-Y', strtotime(ModuleModel::$assignment->getEffectiveStartDate())),
                            'updated_date_time' => date('d-m-Y H:i:s', strtotime(ModuleModel::$assignment->getUpdatedAt())) . '(GMT)',
                        ]
                    );
                }

                if (ModuleModel::$assignment && ModuleModel::$assignment->getEndDate() != '') {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$assignment->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment->getUuid(),
                            'object_uuid' => ModuleModel::$assignment->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNMENT_END_DATE,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'date' => Helpers::__convertDateToSecond(ModuleModel::$assignment->getEndDate()),
                            'date_time' => date('d-m-Y', strtotime(ModuleModel::$assignment->getEffectiveStartDate())),
                            'updated_date_time' => date('d-m-Y H:i:s', strtotime(ModuleModel::$assignment->getUpdatedAt())) . '(GMT)',
                        ]
                    );
                }
            }


            if ($dispatcher->getActionName() == 'update') {

                if (ModuleModel::$assignment && ModuleModel::$oldAssignmentStartDate != null &&
                    ModuleModel::$assignment->getEffectiveStartDate() != date('Y-m-d', strtotime(ModuleModel::$oldAssignmentStartDate))) {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$assignment->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment->getUuid(),
                            'object_uuid' => ModuleModel::$assignment->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNMENT_START_DATE,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'date' => Helpers::__convertDateToSecond(ModuleModel::$assignment->getEffectiveStartDate()),
                            'date_time' => date('d-m-Y', strtotime(ModuleModel::$assignment->getEffectiveStartDate())),
                            'updated_date_time' => date('d-m-Y H:i:s', strtotime(ModuleModel::$assignment->getUpdatedAt())) . '(GMT)',
                        ]
                    );

                    ModuleModel::$oldAssignmentStartDate = null;
                }


                if (ModuleModel::$assignment && ModuleModel::$oldAssignmentEndDate != null &&
                    ModuleModel::$assignment->getEndDate() != date('Y-m-d', strtotime(ModuleModel::$oldAssignmentEndDate))) {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$assignment->getEmployee()->getUuid(),
                            'object_uuid' => ModuleModel::$assignment->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNMENT_END_DATE,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'date' => Helpers::__convertDateToSecond(ModuleModel::$assignment->getEndDate()),
                            'date_time' => date('d-m-Y', strtotime(ModuleModel::$assignment->getEndDate())),
                            'updated_date_time' => date('d-m-Y H:i:s', strtotime(ModuleModel::$assignment->getUpdatedAt())) . '(GMT)',
                        ]
                    );

                    ModuleModel::$oldAssignmentEndDate = null;
                }

            }

            if ($dispatcher->getActionName() == 'changeStatus') {
                EmployeeActivityHelper::__addEmployeeActivity(
                    [
                        'employee_uuid' => ModuleModel::$assignment->getEmployee()->getUuid(),
                        'object_uuid' => ModuleModel::$assignment->getUuid(),
                        'assignment_uuid' => ModuleModel::$assignment->getUuid(),
                        'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNMENT_STATUS_CHANGE,
                        'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_ASSIGNMENT,
                        'creator_company_id' => ModuleModel::$company->getId()
                    ],
                    [
                        'status' => Assignment::__getStatusLabel(ModuleModel::$assignment->getApprovalStatus())
                    ]
                );
            }

            if ($dispatcher->getActionName() == 'changeApprovalStatus') {
                if (!is_null(ModuleModel::$assignment)) {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$assignment->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment->getUuid(),
                            'object_uuid' => ModuleModel::$assignment->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNMENT_STATUS_CHANGE,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_ASSIGNMENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'status' => Assignment::__getStatusLabel(ModuleModel::$assignment->getApprovalStatus())
                        ]
                    );
                }
            }

            if ($dispatcher->getActionName() == 'terminate') {
                if (!is_null(ModuleModel::$assignment)) {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$assignment->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment->getUuid(),
                            'object_uuid' => ModuleModel::$assignment->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNMENT_STATUS_CHANGE,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_ASSIGNMENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'status' => ModuleModel::$assignment->getCompletedLabelTranslate()
                        ]
                    );
                }
            }

        }
        //relocation
        if ($dispatcher->getControllerName() == 'relocation') {
            if ($dispatcher->getActionName() == 'changeStatus' && ModuleModel::$relocation) {
                EmployeeActivityHelper::__addEmployeeActivity(
                    [
                        'employee_uuid' => ModuleModel::$relocation->getEmployee()->getUuid(),
                        'assignment_uuid' => ModuleModel::$relocation->getAssignment()->getUuid(),
                        'object_uuid' => ModuleModel::$relocation->getUuid(),
                        'activity_name' => EmployeeActivityHelper::EE_HISTORY_RELOCATION_STATUS_CHANGE,
                        'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_RELOCATION,
                        'creator_company_id' => ModuleModel::$company->getId()
                    ],
                    [
                        'company_name' => ModuleModel::$company->getName(),
                        'status' => Relocation::__getStatusLabel(ModuleModel::$relocation->getStatus())
                    ]
                );
            }
            if ($dispatcher->getActionName() == 'desactivateService') {
//                if (ModuleModel::$relocationServiceCompany) {
//                    EmployeeActivityHelper::__addEmployeeActivity(
//                        [
//                            'employee_uuid' => ModuleModel::$relocationServiceCompany->getRelocation()->getEmployee()->getUuid(),
//                            'assignment_uuid' => ModuleModel::$relocationServiceCompany->getAssignment()->getUuid(),
//                            'object_uuid' => ModuleModel::$relocationServiceCompany->getUuid(),
//                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_SERVICE_STATUS_CHANGE,
//                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_SERVICE,
//                            'creator_company_id' => ModuleModel::$company->getId()
//                        ],
//                        [
//                            'company_name' => ModuleModel::$company->getName(),
//                            'service_name' => ModuleModel::$relocationServiceCompany->getName(),
//                            'status' => RelocationServiceCompany::__getStatusLabel(ModuleModel::$relocationServiceCompany->getEntityProgressStatus())
//                        ]
//                    );
//                }
            }
            if ($dispatcher->getActionName() == 'activateService') {

//                if (ModuleModel::$relocationServiceCompany) {
//                    EmployeeActivityHelper::__addEmployeeActivity(
//                        [
//                            'employee_uuid' => ModuleModel::$relocationServiceCompany->getRelocation()->getEmployee()->getUuid(),
//                            'assignment_uuid' => ModuleModel::$relocationServiceCompany->getAssignment()->getUuid(),
//                            'object_uuid' => ModuleModel::$relocationServiceCompany->getUuid(),
//                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_SERVICE_STATUS_CHANGE,
//                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_SERVICE,
//                            'creator_company_id' => ModuleModel::$company->getId()
//                        ],
//                        [
//                            'company_name' => ModuleModel::$company->getName(),
//                            'service_name' => ModuleModel::$relocationServiceCompany->getName(),
//                            'status' => RelocationServiceCompany::__getStatusLabel(ModuleModel::$relocationServiceCompany->getEntityProgressStatus())
//                        ]
//                    );
//                }
            }

            if($dispatcher->getActionName() == 'applyWorkflow'){
                $isAssigneeTask = $dispatcher->getParam('isAssigneeTask');
                $taskCount = $dispatcher->getParam('task_count');
                if($isAssigneeTask == true && ModuleModel::$relocation){
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$relocation->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$relocation->getAssignment()->getUuid(),
                            'object_uuid' => ModuleModel::$relocation->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_WORKFLOW_LAUNCHED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'company_name' => ModuleModel::$company->getName(),
                            'task_count' => isset($taskCount) && $taskCount ? $taskCount : 0,
                            'file_count' => isset($taskCount) && $taskCount ? $taskCount : 0,
                        ]
                    );
                }
            }
        }

        if ($dispatcher->getControllerName() == 'task') {
            if (ModuleModel::$task) {
                if ($dispatcher->getActionName() == 'create') {
                    if (ModuleModel::$task->getTaskType() == Task::TASK_TYPE_EE_TASK) {
                        if (ModuleModel::$task->getMainAssignment()) {
                            $object = RelocationServiceCompany::__findFirstByUuidCache(ModuleModel::$task->getObjectUuid());
                            if (!$object) {
                                $object = Relocation::__findFirstByUuidCache(ModuleModel::$task->getObjectUuid());
                            }

                            $employee = ModuleModel::$task->getEmployee();
                            if ($employee && $object) {
                                $return = EmployeeActivityHelper::__addEmployeeActivity(
                                    [
                                        'employee_uuid' => $employee->getUuid(),
                                        'assignment_uuid' => ModuleModel::$task->getMainAssignment() ? ModuleModel::$task->getMainAssignment()->getUuid() : '',
                                        'object_uuid' => ModuleModel::$task->getUuid(),
                                        'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_TASK_CREATED,
                                        'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                                        'creator_company_id' => ModuleModel::$company->getId()
                                    ],
                                    [
                                        'company_name' => ModuleModel::$company->getName(),
                                        'task_name' => ModuleModel::$task->getName(),
                                        'task_uuid' => ModuleModel::$task->getUuid(),
                                        'progress' => ModuleModel::$task->getProgress(),
                                        'task_number' => ModuleModel::$task->getNumber(),
                                    ]
                                );

                            }
                        }

                    }
                }

                if ($dispatcher->getActionName() == 'delete') {
                    if (ModuleModel::$task->getTaskType() == Task::TASK_TYPE_EE_TASK) {
                        if (ModuleModel::$task->getMainAssignment()) {
                            $object = RelocationServiceCompany::__findFirstByUuidCache(ModuleModel::$task->getObjectUuid());
                            if (!$object) {
                                $object = Relocation::__findFirstByUuidCache(ModuleModel::$task->getObjectUuid());
                            }

                            $employee = ModuleModel::$task->getEmployee();
                            if ($employee && $object) {

                                $return = EmployeeActivityHelper::__addEmployeeActivity(
                                    [
                                        'employee_uuid' => $employee->getUuid(),
                                        'assignment_uuid' => ModuleModel::$task->getMainAssignment() ? ModuleModel::$task->getMainAssignment()->getUuid() : '',
                                        'object_uuid' => ModuleModel::$task->getUuid(),
                                        'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_TASK_DELETE,
                                        'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                                        'creator_company_id' => ModuleModel::$company->getId()
                                    ],
                                    [
                                        'company_name' => ModuleModel::$company->getName(),
                                        'task_name' => ModuleModel::$task->getName(),
                                        'task_number' => ModuleModel::$task->getNumber(),
                                    ]
                                );
                            }
                        }

                    }
                }

                if ($dispatcher->getActionName() == 'setDone' || $dispatcher->getActionName() == 'setTodo' || $dispatcher->getActionName() == 'setInProgress' || $dispatcher->getActionName() == 'setAwaitingFinalReview') {
                    if (ModuleModel::$task->getTaskType()) {
                        $relocationServiceCompany = RelocationServiceCompany::__findFirstByUuidCache(ModuleModel::$task->getObjectUuid());
                        if ($relocationServiceCompany instanceof RelocationServiceCompany && EntityProgress::__checkStatusChanged($relocationServiceCompany->getUuid())) {
                            $employee = ModuleModel::$task->getEmployee();
                            if ($employee) {
                                $return = EmployeeActivityHelper::__addEmployeeActivity(
                                    [
                                        'employee_uuid' => $employee->getUuid(),
                                        'assignment_uuid' => ModuleModel::$task->getMainAssignment() ? ModuleModel::$task->getMainAssignment()->getUuid() : '',
                                        'object_uuid' => $relocationServiceCompany->getUuid(),
                                        'activity_name' => EmployeeActivityHelper::EE_HISTORY_SERVICE_STATUS_CHANGE,
                                        'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_SERVICE,
                                        'creator_company_id' => ModuleModel::$company->getId(),
                                    ],
                                    [
                                        'company_name' => ModuleModel::$company->getName(),
                                        'service_name' => $relocationServiceCompany->getName(),
                                        'status' => RelocationServiceCompany::__getStatusLabel($relocationServiceCompany->getEntityProgressStatus()),
                                        'task_uuid' => ModuleModel::$task->getUuid(),
                                        'task_name' => ModuleModel::$task->getName(),
                                        'task_number' => ModuleModel::$task->getNumber(),
                                    ]
                                );
                            }
                        }
                    }

                    if (ModuleModel::$task->getTaskType() == Task::TASK_TYPE_EE_TASK) {
                        EmployeeActivityHelper::__addEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$task->getEmployee()->getUuid(),
                                'assignment_uuid' => ModuleModel::$task->getMainAssignment() ? ModuleModel::$task->getMainAssignment()->getUuid() : '',
                                'object_uuid' => ModuleModel::$task->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_TASK_STATUS_CHANGE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                                'creator_company_id' => ModuleModel::$company->getId()
                            ], [
                                'company_name' => ModuleModel::$company->getName(),
                                'task_name' => ModuleModel::$task->getName(),
                                'task_number' => ModuleModel::$task->getNumber(),
                                'task_uuid' => ModuleModel::$task->getUuid(),
                                'progress' => ModuleModel::$task->getProgress(),
                                'status' => Task::__getStatusLabel(ModuleModel::$task->getProgress())
                            ]
                        );
                    }
                }

                if($dispatcher->getActionName() == 'bulk' && ModuleModel::$task->getTaskType() == Task::TASK_TYPE_EE_TASK){
                    $addActivity = EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$task->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$task->getMainAssignment() ? ModuleModel::$task->getMainAssignment()->getUuid() : '',
                            'object_uuid' => ModuleModel::$task->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_TASK_UPDATED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'company_name' => ModuleModel::$company->getName(),
                            'task_name' => ModuleModel::$task->getName(),
                            'task_number' => ModuleModel::$task->getNumber(),
                            'task_uuid' => ModuleModel::$task->getUuid(),
                            'progress' => ModuleModel::$task->getProgress(),
                        ]
                    );

                }
            }

            if($dispatcher->getActionName() == 'generateAssigneeTasks' && ModuleModel::$relocationServiceCompany){
                $taskCount = $dispatcher->getParam('task_count');
                EmployeeActivityHelper::__addEmployeeActivity(
                    [
                        'employee_uuid' => ModuleModel::$relocationServiceCompany->getRelocation()->getEmployee()->getUuid(),
                        'assignment_uuid' => ModuleModel::$relocationServiceCompany->getAssignment()->getUuid(),
                        'object_uuid' => ModuleModel::$relocationServiceCompany->getUuid(),
                        'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_WORKFLOW_LAUNCHED,
                        'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                        'creator_company_id' => ModuleModel::$company->getId()
                    ],
                    [
                        'company_name' => ModuleModel::$company->getName(),
                        'task_count' => isset($taskCount) && $taskCount ? $taskCount : 0,
                        'file_count' => isset($taskCount) && $taskCount ? $taskCount : 0,
                    ]
                );
            }
        }

        if ($dispatcher->getControllerName() == 'comments') {
            if ($dispatcher->getActionName() == 'createComment') {

                $housingProposition = HousingProposition::findFirstByUuid(ModuleModel::$comment->object_uuid);

                if ($housingProposition && !$housingProposition->isToSuggest()) {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => $housingProposition->getEmployee()->getUuid(),
                            'assignment_uuid' => $housingProposition->getRelocation() && $housingProposition->getRelocation()->getAssignment() ? $housingProposition->getRelocation()->getAssignment()->getUuid() : '',
                            'object_uuid' => $housingProposition->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PROPERTY_COMMENTED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_SERVICE,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'company_name' => ModuleModel::$company->getName(),
                            'property_name' => $housingProposition->getProperty()->getName(),
                            'housing_proposition_uuid' => $housingProposition->getUuid(),
                            //'property_name' => '<a href="' . $housingProposition->getEmployeeDetailUrl() . '">' . $housingProposition->getProperty()->getName() . '</a>'
                        ]
                    );

                }
            }
        }

        if ($dispatcher->getControllerName() == AclHelper::CONTROLLER_ASSIGNEE_DOCUMENT) {
            if ($dispatcher->getActionName() == 'createDocument') {
                if (ModuleModel::$assigneeDocument && ModuleModel::$assigneeDocument->isPassport()) {
                    if (ModuleModel::$assigneeDocument->hasUpdated('expiry_date') && ModuleModel::$assigneeDocument->getExpiryDate() > 0) {
                        $return = EmployeeActivityHelper::__addEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PASSPORT_EXPIRY_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                                'creator_company_id' => ModuleModel::$company->getId()
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getExpiryDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ]
                        );
                    }
                }
                if (ModuleModel::$assigneeDocument && ModuleModel::$assigneeDocument->isVisa()) {

                    if (ModuleModel::$assigneeDocument->hasUpdated('expiry_date') && ModuleModel::$assigneeDocument->getExpiryDate() > 0) {
                        $return['employeeActivityVisaExpiryResult'] = EmployeeActivityHelper::__addEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_VISA_EXPIRY_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                                'creator_company_id' => ModuleModel::$company->getId()
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getExpiryDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ]
                        );
                    }


                    if (ModuleModel::$assigneeDocument->hasUpdated('estimated_approval_date') && ModuleModel::$assigneeDocument->getEstimatedApprovalDate() > 0) {
                        $return = EmployeeActivityHelper::__addEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_VISA_ESTIMATED_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                                'creator_company_id' => ModuleModel::$company->getId()
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getEstimatedApprovalDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ]
                        );
                    }

                    if (ModuleModel::$assigneeDocument->hasUpdated('approval_date') && ModuleModel::$assigneeDocument->getApprovalDate() > 0) {
                        $return['employeeActivityVisaApprovalResult'] = EmployeeActivityHelper::__addEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_VISA_APPROVAL_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                                'creator_company_id' => ModuleModel::$company->getId()
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getApprovalDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ]
                        );
                    }
                }


                if (ModuleModel::$assigneeDocument && ModuleModel::$assigneeDocument->isDrivingLicence()) {
                    if (ModuleModel::$assigneeDocument->hasUpdated('expiry_date') && ModuleModel::$assigneeDocument->getExpiryDate() > 0) {
                        $return['employeeActivityDrivingExpiryResult'] = EmployeeActivityHelper::__addEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_DRIVING_EXPIRY_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                                'creator_company_id' => ModuleModel::$company->getId()
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getExpiryDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ]
                        );
                    }
                }
            }
            if ($dispatcher->getActionName() == 'updateDocument') {
                if (ModuleModel::$assigneeDocument && ModuleModel::$assigneeDocument->isPassport()) {
                    if (ModuleModel::$assigneeDocument->hasUpdated('expiry_date') && ModuleModel::$assigneeDocument->getExpiryDate() > 0) {
                        $return = EmployeeActivityHelper::__updateEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PASSPORT_EXPIRY_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getExpiryDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ],
                            ModuleModel::$language
                        );
                    }
                }
                if (ModuleModel::$assigneeDocument && ModuleModel::$assigneeDocument->isVisa()) {

                    if (ModuleModel::$assigneeDocument->hasUpdated('expiry_date') && ModuleModel::$assigneeDocument->getExpiryDate() > 0) {
                        $return['employeeActivityVisaExpiryResult'] = EmployeeActivityHelper::__updateEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_VISA_EXPIRY_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getExpiryDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ],
                            ModuleModel::$language
                        );
                    }


                    if (ModuleModel::$assigneeDocument->hasUpdated('estimated_approval_date') && ModuleModel::$assigneeDocument->getEstimatedApprovalDate() > 0) {
                        $return = EmployeeActivityHelper::__updateEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_VISA_ESTIMATED_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getEstimatedApprovalDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ],
                            ModuleModel::$language
                        );
                    }

                    if (ModuleModel::$assigneeDocument->hasUpdated('approval_date') && ModuleModel::$assigneeDocument->getApprovalDate() > 0) {
                        $return['employeeActivityVisaApprovalResult'] = EmployeeActivityHelper::__updateEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_VISA_APPROVAL_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getApprovalDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ],
                            ModuleModel::$language
                        );
                    }
                }


                if (ModuleModel::$assigneeDocument && ModuleModel::$assigneeDocument->isDrivingLicence()) {
                    if (ModuleModel::$assigneeDocument->hasUpdated('expiry_date') && ModuleModel::$assigneeDocument->getExpiryDate() > 0) {
                        $return['employeeActivityDrivingExpiryResult'] = EmployeeActivityHelper::__updateEmployeeActivity(
                            [
                                'employee_uuid' => ModuleModel::$assigneeDocument->getEntityUuid(),
                                'object_uuid' => ModuleModel::$assigneeDocument->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_DRIVING_EXPIRY_DATE,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT
                            ],
                            [
                                'date' => intval(ModuleModel::$assigneeDocument->getExpiryDate()),
                                'document_name' => ModuleModel::$assigneeDocument->getName()
                            ],
                            ModuleModel::$language
                        );
                    }
                }
            }
        }

        if ($dispatcher->getControllerName() == 'home-search') {
            if ($dispatcher->getActionName() == 'changeSelectedProposition') {
                if (ModuleModel::$housingProposition->isSelected() == true) {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$housingProposition->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$housingProposition->getRelocation() && ModuleModel::$housingProposition->getRelocation()->getAssignment() ? ModuleModel::$housingProposition->getRelocation()->getAssignment()->getUuid() : '',
                            'object_uuid' => ModuleModel::$housingProposition->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PROPERTY_FINAL,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_SERVICE,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'company_name' => ModuleModel::$company->getName(),
                            'property_name' => ModuleModel::$housingProposition->getProperty()->getName(), //property_name should be a Text and Not an Linktag
                            'housing_proposition_uuid' => ModuleModel::$housingProposition->getUuid(),
                            // 'property_name' => '<a href="' . ModuleModel::$housingProposition->getEmployeeDetailUrl() . '">' . ModuleModel::$housingProposition->getProperty()->getName() . '</a>'
                        ]
                    );
                }
            }

            if ($dispatcher->getActionName() == 'changeStatusHousingProposition') {
                if (ModuleModel::$housingProposition->isSuggested() == true) {
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$housingProposition->getEmployee()->getUuid(),
                            'assignment_uuid' => ModuleModel::$housingProposition->getRelocation() && ModuleModel::$housingProposition->getRelocation()->getAssignment() ? ModuleModel::$housingProposition->getRelocation()->getAssignment()->getUuid() : '',
                            'object_uuid' => ModuleModel::$housingProposition->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PROPERTY_SUGGESTED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_SERVICE,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'company_name' => ModuleModel::$company->getName(),
                            'property_name' => ModuleModel::$housingProposition->getProperty()->getName(),  //property_name should be a Text and Not an Linktag
                            'housing_proposition_uuid' => ModuleModel::$housingProposition->getUuid(),
                            //'property_name' => '<a href="' . ModuleModel::$housingProposition->getEmployeeDetailUrl() . '">' . ModuleModel::$housingProposition->getProperty()->getName() . '</a>'
                        ]
                    );
                }
            }
        }

        if ($dispatcher->getControllerName() == 'relocation-service') {
            if ($dispatcher->getActionName() == 'sendNeedAssessmentFormOfRelocation') {
                EmployeeActivityHelper::__addEmployeeActivity(
                    [
                        'employee_uuid' => ModuleModel::$needFormRequest->getEmployee()->getUuid(),
                        'assignment_uuid' => ModuleModel::$needFormRequest->getRelocation() && ModuleModel::$needFormRequest->getRelocation()->getAssignment() ? ModuleModel::$needFormRequest->getRelocation()->getAssignment()->getUuid() : '',
                        'object_uuid' => ModuleModel::$needFormRequest->getUuid(),
                        'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_QUESTIONNAIRE_RECEIVED,
                        'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_QUESTIONNAIRE,
                        'creator_company_id' => ModuleModel::$company->getId()
                    ],
                    [
                        'company_name' => ModuleModel::$company->getName(),
                        'questionnaire_name' => ModuleModel::$needFormRequest->getNeedFormGabarit()->getName(),
                        'need_form_request_uuid' => ModuleModel::$needFormRequest->getUuid(),
                    ]
                );
            }
        }

        if ($dispatcher->getControllerName() == 'attachments') {
            if ($dispatcher->getActionName() == 'attachMultipleFiles') {
                if (ModuleModel::$employee) {
                    $objectFolder = ModuleModel::$objectFolder;
                    $fileCount = $dispatcher->getParam('file_count');

                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$employee->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '',
                            'object_uuid' => ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '',
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_DOCUMENT_UPLOADED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_DOCUMENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'user_name' => ModuleModel::$user_profile->getFullname(),
                            'company_name' => ModuleModel::$company->getName(),
                            'company_uuid' => ModuleModel::$company->getUuid(),
                            'object_folder_uuid' => isset($objectFolder) && $objectFolder ? $objectFolder->getUuid() : null,
                            'file_count' => isset($fileCount) && $fileCount ? $fileCount : 0
                        ]
                    );
                }
            }
            if ($dispatcher->getActionName() == 'copyMultipleFiles') {
                if (ModuleModel::$employee) {
                    $objectFolder = ModuleModel::$objectFolder;
                    $fileCount = $dispatcher->getParam('file_count');
                    EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$employee->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '',
                            'object_uuid' => ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '',
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_DOCUMENT_UPLOADED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_DOCUMENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'user_name' => ModuleModel::$user_profile->getFullname(),
                            'company_name' => ModuleModel::$company->getName(),
                            'company_uuid' => ModuleModel::$company->getUuid(),
                            'object_folder_uuid' => isset($objectFolder) && $objectFolder ? $objectFolder->getUuid() : null,
                            'file_count' => isset($fileCount) && $fileCount ? $fileCount : 0,
                        ]
                    );
                }
            }
        }

        if ($dispatcher->getControllerName() == AclHelper::CONTROLLER_HOME_SEARCH) {


            if ($dispatcher->getActionName() == 'addVisiteEvent') {
                if (ModuleModel::$employee && ModuleModel::$housingProposition) {
                    $offset = ModuleModel::$housingVisiteEvent->getTimezoneOffset() ? ModuleModel::$housingVisiteEvent->getTimezoneOffset() : 0;
                    $offsetUtc = 'UTC ' . ($offset > 0 ? '+' : '') . ($offset / 60);

                    $result = EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$employee->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '',
                            'object_uuid' => ModuleModel::$housingProposition->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PROPERTY_VISIT_SCHEDULED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'user_name' => ModuleModel::$user_profile->getFullname(),
                            'company_name' => ModuleModel::$company->getName(),
                            'property_name' => ModuleModel::$property->getName(),
                            'property_uuid' => ModuleModel::$property->getUuid(),
                            'date' => ModuleModel::$housingVisiteEvent->getStart(),
                            'timezone_offset' => ModuleModel::$housingVisiteEvent->getTimezoneOffset(),
                            'date_time' => date('d/m/Y H:i:s', ModuleModel::$housingVisiteEvent->getStart() + ($offset * 60)) . ' ' .$offsetUtc,
                            'housing_proposition_uuid' => ModuleModel::$housingProposition->getUuid(),
                        ]
                    );
                }
            }
            if ($dispatcher->getActionName() == 'cancelVisiteEvent') {
                if (ModuleModel::$employee) {
                    $result = EmployeeActivityHelper::__removeEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$employee->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '',
                            'object_uuid' => ModuleModel::$housingProposition->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PROPERTY_VISIT_SCHEDULED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT
                        ],
                        [
                            'user_name' => ModuleModel::$user_profile->getFullname(),
                            'company_name' => ModuleModel::$company->getName(),
                            'property_name' => ModuleModel::$property->getName(),
                            'property_uuid' => ModuleModel::$property->getUuid(),
                        ]
                    );


                    $result2 = EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => ModuleModel::$employee->getUuid(),
                            'assignment_uuid' => ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '',
                            'object_uuid' => ModuleModel::$housingProposition->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_PROPERTY_VISIT_DELETED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_EVENT,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'user_name' => ModuleModel::$user_profile->getFullname(),
                            'company_name' => ModuleModel::$company->getName(),
                            'property_name' => ModuleModel::$property->getName(),
                            'property_uuid' => ModuleModel::$property->getUuid(),
                            'date' => ModuleModel::$housingVisiteEvent->getStart(),
                            'date_time' => date('d/m/Y', ModuleModel::$housingVisiteEvent->getStart()),
                            'housing_proposition_uuid' => ModuleModel::$housingProposition->getUuid(),
                        ]
                    );

                }
            }
        }

        if ($controller == AclHelper::CONTROLLER_TASK_FILE) {
            $task = ModuleModel::$task;
            $taskFile = ModuleModel::$taskFile;
            if ($task && $taskFile && $task->getTaskType() == Task::TASK_TYPE_EE_TASK) {
                if ($action == 'save') {
                    $mainAssignment = $task->getMainAssignment();
                    $employee = $task->getEmployee();
                    $result = EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => $employee->getUuid(),
                            'assignment_uuid' => $mainAssignment ? $mainAssignment->getUuid() : '',
                            'object_uuid' => $task->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_TASK_ATTACHMENT_ADDED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'user_name' => ModuleModel::$user_profile->getFullname(),
                            'company_name' => ModuleModel::$company->getName(),
                            'task_number' => $task->getNumber(),
                            'task_name' => $task->getName(),
                            'task_uuid' => $task->getUuid(),
                            'progress' => ModuleModel::$task->getProgress(),
                            'filename' => $taskFile->getName(),
                        ]
                    );
                }

                if ($action == 'delete') {
                    $mainAssignment = $task->getMainAssignment();
                    $employee = $task->getEmployee();
                    $result = EmployeeActivityHelper::__addEmployeeActivity(
                        [
                            'employee_uuid' => $employee->getUuid(),
                            'assignment_uuid' => $mainAssignment ? $mainAssignment->getUuid() : '',
                            'object_uuid' => $task->getUuid(),
                            'activity_name' => EmployeeActivityHelper::EE_HISTORY_ASSIGNEE_TASK_ATTACHMENT_DELETED,
                            'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                            'creator_company_id' => ModuleModel::$company->getId()
                        ],
                        [
                            'user_name' => ModuleModel::$user_profile->getFullname(),
                            'company_name' => ModuleModel::$company->getName(),
                            'task_number' => $task->getNumber(),
                            'task_name' => $task->getName(),
                            'task_uuid' => $task->getUuid(),
                            'progress' => ModuleModel::$task->getProgress(),
                            'filename' => $taskFile->getName()
                        ]
                    );
                }
            }
        }
        if ($controller == AclHelper::CONTROLLER_COMMENTS) {

            $comment = ModuleModel::$comment;
            if ($comment) {
                if ($action == 'createComment') {
                    $task = ModuleModel::$task;
                    if($task && $task->getTaskType() == Task::TASK_TYPE_EE_TASK){
                        $mainAssignment = $task->getMainAssignment();
                        $employee = $task->getEmployee();
                        $result = EmployeeActivityHelper::__addEmployeeActivity(
                            [
                                'employee_uuid' => $employee->getUuid(),
                                'assignment_uuid' => $mainAssignment ? $mainAssignment->getUuid() : '',
                                'object_uuid' => $task->getUuid(),
                                'activity_name' => EmployeeActivityHelper::EE_HISTORY_EE_TASK_COMMENTED,
                                'activity_type' => EmployeeActivityHelper::ACTIVITY_TYPE_TASK,
                                'creator_company_id' => ModuleModel::$company->getId()
                            ],
                            [
                                'user_name' => ModuleModel::$user_profile->getFullname(),
                                'company_name' => ModuleModel::$company->getName(),
                                'task_number' => $task->getNumber(),
                                'task_name' => $task->getName(),
                                'task_uuid' => $task->getUuid(),
                                'comment' => $comment ? $comment->getMessage() : '',
                                'progress' => ModuleModel::$task->getProgress(),
                            ]
                        );
                    }
                }

            }
        }
    }


    /**
     * @param \Phalcon\Mvc\Dispatcher $dispatcher
     * @return void
     */
    public function sendWebhook(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        if (ModuleModel::$subscription == null || !ModuleModel::$subscription instanceof Subscription) {
            return;
        }
        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();
        $return = $dispatcher->getParam('return');
        //check addon
        $addon_webhook = Addon::findFirstByCode(Addon::CODE_WEBHOOK);
        if (!$addon_webhook) {
            return;
        }

        $subscription_addon = SubscriptionAddon::findFirst([
            "conditions" => " addon_id = :addon_id: and subscription_id= :subscription_id:",
            "bind" => [
                "addon_id" => $addon_webhook->getId(),
                "subscription_id" => ModuleModel::$subscription->getId()
            ]
        ]);
        if (!$subscription_addon) {
            return;
        }
        if ($return['success']) {
            $time = time();
            $webhook_configuration_maps = WebhookConfigurationMap::find([
                "conditions" => "controller = :controller: and action = :action: and is_gms = 1",
                "bind" => [
                    "controller" => $controller,
                    "action" => $action
                ]
            ]);
            if(count($webhook_configuration_maps) > 0){
                foreach($webhook_configuration_maps as $webhook_configuration_map){
                    $webhook_configuration = $webhook_configuration_map->getWebhookConfiguration();

                    $webhook = Webhook::findFirst([
                        "conditions" => "webhook_configuration_id = :webhook_configuration_id: and company_id = :company_id: and is_deleted = 0 and status = :active: and is_verified = 1",
                        "bind" => [
                            "webhook_configuration_id" => $webhook_configuration_map->getWebhookConfigurationId(),
                            "company_id" => ModuleModel::$company->getId(),
                            "active" => Webhook::ACTIVE
                        ]
                    ]);
                    if($webhook instanceof Webhook){
                        if ($webhook_configuration) {
                            if ($webhook_configuration->getObjectType() == "employee") {
                                $object = ModuleModel::$employee;
                            } else if ($webhook_configuration->getObjectType() == "assignment") {
                                $object = ModuleModel::$assignment;
                            } else if ($webhook_configuration->getObjectType() == "relocation") {
                                $object = ModuleModel::$relocation;
                            } else if ($webhook_configuration->getObjectType() == "relocation_service_company") {
                                $objectes = ModuleModel::$relocationServiceCompanies;
                                $object = ModuleModel::$relocationServiceCompany;
                            } else if ($webhook_configuration->getObjectType() == "task") {
                                $object = ModuleModel::$task;
                                if($webhook_configuration->getAction() == 'create'){
                                    $objectes = ModuleModel::$task_created_list;
                                } else if($webhook_configuration->getAction() == 'delete'){
                                    $objectes = ModuleModel::$task_deleted_list;
                                }  
                            } else if ($webhook_configuration->getObjectType() == "invoice") {
                                $object = ModuleModel::$invoice;
                            } else if ($webhook_configuration->getObjectType() == "expense") {
                                $object = ModuleModel::$expense;
                            } else if ($webhook_configuration->getObjectType() == "timelog") {
                                $object = ModuleModel::$timelog;
                            } else if ($webhook_configuration->getObjectType() == "transaction") {
                                $object = ModuleModel::$transaction;
                            } else if ($webhook_configuration->getObjectType() == "bill") {
                                $object = ModuleModel::$bill;
                            } else if ($webhook_configuration->getObjectType() == "credit_note") {
                                $object = ModuleModel::$credit_note;
                            } else if ($webhook_configuration->getObjectType() == "dependant") {
                                $object = ModuleModel::$dependant;
                            }
                            if (isset($objectes)) {
                                foreach ($objectes as $object) {
                                    $queue = new RelodayQueue(getenv('QUEUE_WEBHOOK'));
        
                                    $addQueue = $queue->addQueue([
                                        'action' => 'sendWebhook',
                                        'params' => [
                                            'log_uuid' => Helpers::__uuid(),
                                            'data' => [
                                                "object" => $object->toArrayInItem(ModuleModel::$language),
                                                "action" => $webhook_configuration_map->getActionDisplay(),
                                                // "controller" => $controller
                                            ],
                                            'webhook_uuid' => $webhook->getUuid(),
                                            'url' => $webhook->getUrl(),
                                            'company_uuid' => ModuleModel::$company->getUuid(),
                                            'headers' => $webhook->getHeaders()
                                        ],
                                    ], ModuleModel::$company->getId());
                                    if (!$addQueue["success"]) {
                                        // var_dump("send queue webhook error \n");
                                        // var_dump($addQueue);
                                        // die();
                                    }
                                }
                            } else if (isset($object)) {
                                $queue = new RelodayQueue(getenv('QUEUE_WEBHOOK'));
        
                                $addQueue = $queue->addQueue([
                                    'action' => 'sendWebhook',
                                    'params' => [
                                        'log_uuid' => Helpers::__uuid(),
                                        'data' => [
                                            "object" => $object->toArrayInItem(ModuleModel::$language),
                                            "action" => $webhook_configuration_map->getActionDisplay(),
                                            // "controller" => $controller
                                        ],
                                        'webhook_uuid' => $webhook->getUuid(),
                                        'url' => $webhook->getUrl(),
                                        'company_uuid' => ModuleModel::$company->getUuid(),
                                        'headers' => $webhook->getHeaders()
                                    ],
                                ], ModuleModel::$company->getId());
                                if (!$addQueue["success"]) {
                                    // var_dump("send queue webhook error \n");
                                    // var_dump($addQueue);
                                    // die();
                                }
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * @param \Phalcon\Mvc\Dispatcher $dispatcher
     * @return void
     */
    public function sendWebhookToOtherCompany(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        if(ModuleModel::$hrCompany instanceof Company){
            $subscription = ModuleModel::$hrCompany->getSubscription();
            if (!$subscription instanceof Subscription) {
                return;
            }
            $controller = $dispatcher->getControllerName();
            $action = $dispatcher->getActionName();
            $return = $dispatcher->getParam('return');
            //check addon
            $addon_webhook = Addon::findFirstByCode(Addon::CODE_WEBHOOK);
            if (!$addon_webhook) {
                return;
            }

            $subscription_addon = SubscriptionAddon::findFirst([
                "conditions" => " addon_id = :addon_id: and subscription_id= :subscription_id:",
                "bind" => [
                    "addon_id" => $addon_webhook->getId(),
                    "subscription_id" => $subscription->getId()
                ]
            ]);
            if (!$subscription_addon) {
                return;
            }
            if ($return['success']) {
                $time = time();
                $webhook_configuration_maps = WebhookConfigurationMap::find([
                    "conditions" => "controller = :controller: and action = :action: and from_gms = 1",
                    "bind" => [
                        "controller" => $controller,
                        "action" => $action
                    ]
                ]);
                if(count($webhook_configuration_maps) > 0){
                    foreach($webhook_configuration_maps as $webhook_configuration_map){
                        $webhook_configuration = $webhook_configuration_map->getWebhookConfiguration();

                        $webhook = Webhook::findFirst([
                            "conditions" => "webhook_configuration_id = :webhook_configuration_id: and company_id = :company_id: and is_deleted = 0 and status = :active: and is_verified = 1",
                            "bind" => [
                                "webhook_configuration_id" => $webhook_configuration_map->getWebhookConfigurationId(),
                                "company_id" => ModuleModel::$hrCompany->getId(),
                                "active" => Webhook::ACTIVE
                            ]
                        ]);
                        if($webhook instanceof Webhook){
                            if ($webhook_configuration) {
                                if ($webhook_configuration->getObjectType() == "initation_request") {
                                    $object = ModuleModel::$assignmentRequest;
                                } else if ($webhook_configuration->getObjectType() == "relocation") {
                                    $object = ModuleModel::$relocation;
                                } else if ($webhook_configuration->getObjectType() == "contract") {
                                    $object = ModuleModel::$contract;
                                }
                                if (isset($objectes)) {
                                    foreach ($objectes as $object) {
                                        $queue = new RelodayQueue(getenv('QUEUE_WEBHOOK'));
            
                                        $addQueue = $queue->addQueue([
                                            'action' => 'sendWebhook',
                                            'params' => [
                                                'log_uuid' => Helpers::__uuid(),
                                                'data' => [
                                                    "object" => $object->toArrayInItem(ModuleModel::$hrCompany->getLanguage()),
                                                    "action" => $webhook_configuration_map->getActionDisplay(),
                                                    // "controller" => $controller
                                                ],
                                                'webhook_uuid' => $webhook->getUuid(),
                                                'url' => $webhook->getUrl(),
                                                'company_uuid' => ModuleModel::$hrCompany->getUuid(),
                                                'headers' => $webhook->getHeaders()
                                            ],
                                        ], ModuleModel::$hrCompany->getId());
                                        if (!$addQueue["success"]) {
                                            // var_dump("send queue webhook error \n");
                                            // var_dump($addQueue);
                                            // die();
                                        }
                                    }
                                } else if (isset($object)) {
                                    $queue = new RelodayQueue(getenv('QUEUE_WEBHOOK'));
            
                                    $addQueue = $queue->addQueue([
                                        'action' => 'sendWebhook',
                                        'params' => [
                                            'log_uuid' => Helpers::__uuid(),
                                            'data' => [
                                                "object" => $object->toArrayInItem(ModuleModel::$hrCompany->getLanguage()),
                                                "action" => $webhook_configuration_map->getActionDisplay(),
                                                // "controller" => $controller
                                            ],
                                            'webhook_uuid' => $webhook->getUuid(),
                                            'url' => $webhook->getUrl(),
                                            'company_uuid' => ModuleModel::$hrCompany->getUuid(),
                                            'headers' => $webhook->getHeaders()
                                        ],
                                    ], ModuleModel::$hrCompany->getId());
                                    if (!$addQueue["success"]) {
                                        // var_dump("send queue webhook error \n");
                                        // var_dump($addQueue);
                                        // die();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}