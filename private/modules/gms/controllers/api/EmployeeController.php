<?php

namespace Reloday\Gms\Controllers\API;

use Elasticsearch\Endpoints\Cat\Help;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\DateFormatHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\EntityDocument;
use Reloday\Gms\Models\HrCompany;
use Reloday\Gms\Models\InvitationRequest;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\SupportedLanguage;
use Reloday\Gms\Models\Team;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\Employee;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Module;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class EmployeeController extends BaseController
{
    /**
     * @Route("/employee", paths={module="gms"}, methods={"GET"}, name="gms-employee-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $data = $this->request->get('data');
        $conditions = ($data ? "  status <> " . Employee::STATUS_ARCHIVED . " AND (firstname LIKE '%$data%' OR lastname LIKE '%$data%')" : " status <> " . Employee::STATUS_ARCHIVED);
        $result = EmployeeInContract::loadList($conditions);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/employee", paths={module="gms"}, methods={"GET"}, name="gms-employee-index")
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $data = $this->request->get('data');
        $data = $this->request->get('data');
        $type = $this->request->get('type');
        $result = Employee::searchSimpleList([
            'keyword' => $data,
            'type' => $type
        ]);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/employee", paths={module="gms"}, methods={"GET"}, name="gms-employee-index")
     */
    public function getListTestAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $this->response->setJsonContent(['success' => true, 'data' => []]);
        return $this->response->send();
    }

    /**
     * [contactsAction description]
     * @return [type] [description]
     */
    public function contactsAction()
    {
        $this->view->disable();
        $access = $this->canAccessResource($this->router->getControllerName(), 'index');
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $company_id = $this->request->getPost('company_id');

        if ($company_id && $company_id > 0) {

            $result = [
                'success' => true,
                'data' => [
                    'supports' => Employee::getSupportContactList($company_id),
                    'buddies' => Employee::getBuddyContactList($company_id),
                ]
            ];
        } else {
            $result = ['success' => false];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [initAction description]
     * @return [type] [description]
     */
    public function initAction()
    {
        $this->view->disable();

        $access = $this->canAccessResource($this->router->getControllerName(), 'index');
        if (!$access['success']) {
            exit(json_encode($access));
        }

        // 1. Load list
        $list = Team::loadList();

        $companies = [];
        $offices = [];
        $departments = [];
        $teams = [];
        $supports = [];

        // Find companies, offices and departments
        if ($list['success']) {
            foreach ($list['data'] as $team) {
                $teams[] = [
                    'text' => $team['name'],
                    'value' => $team['id'],
                    'department_id' => $team['department_id']
                ];
            } // End filter teams

            foreach ($list['departments'] as $_department) {
                $departments[] = [
                    'text' => $_department['name'],
                    'value' => $_department['id'],
                    'office_id' => $_department['office_id']
                ];
            } // End filter departments

            foreach ($list['offices'] as $_office) {
                $offices[] = [
                    'text' => $_office['name'],
                    'value' => $_office['id'],
                    'company_id' => $_office['company_id']
                ];
            } // End filter offices

            foreach ($list['companies'] as $company) {
                $companies[] = [
                    'text' => $company['name'],
                    'value' => $company['id'],
                ];
            } // End filter companies
        }

        // Load list roles
        $roles = UserGroup::find([
            'conditions' => 'status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);
        $roles_arr = [];
        if (count($roles))
            foreach ($roles as $role) {
                $roles_arr[] = [
                    'text' => $role->getName(),
                    'value' => $role->getId()
                ];
            }

        echo json_encode([
            'success' => true,
            'data' => [
                'companies' => $companies,
                'offices' => $offices,
                'departments' => $departments,
                'teams' => $teams,
                'roles' => $roles_arr,
                'supports' => [],
            ]
        ]);
    }


    /**
     * [detailAction description]
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function detailAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidUuid($uuid)) {
            $employee = Employee::findFirstByUuid($uuid);
        } else if ($uuid > 0) {
            $employee = Employee::findFirstById($uuid);
        }
        if ($employee instanceof Employee && $employee->belongsToGms()) {
            $login = $employee->getUserLogin();
            $loginStatus = $employee->updateLoginStatus();
            $profileInfo = $employee->toArray();
            $profileInfo['login_status'] = $loginStatus;
            $profileInfo['spoken_languages'] = $employee->parseSpokenLanguages();
            $profileInfo['office_name'] = $employee->getOfficeName();
            $profileInfo['company_name'] = $employee->getCompanyName();
            $profileInfo['department_name'] = $employee->getDepartmentName();
            $profileInfo['team_name'] = $employee->getTeamName();
            $profileInfo['company_uuid'] = $employee->getCompany()->getUuid();
            $profileInfo['citizenships'] = $employee->parseCitizenships();
            if ($login instanceof UserLogin) {
                $profileInfo['has_login'] = true;
            } else {
                $profileInfo['has_login'] = false;
            }

            $profileInfo['passport_expiry_date'] = DateFormatHelper::__formatdateYmdHis($employee->getPassportExpiryDate());
            $profileInfo['visa_expiry_date'] = DateFormatHelper::__formatdateYmdHis($employee->getVisaExpiryDate());
            $profileInfo['driving_licence_expiry_date'] = DateFormatHelper::__formatdateYmdHis($employee->getDrivingLicenceExpiryDate());
            $profileInfo['visa_approval_date'] = DateFormatHelper::__formatdateYmdHis($employee->getVisaApprovalDate());
            $profileInfo['visa_estimated_date'] = DateFormatHelper::__formatdateYmdHis($employee->getVisaEstimatedDate());
            $profileInfo['hasUserLogin'] = $employee->getUserLogin() ? true : false;
            $profileInfo['login_email'] = $employee->getUserLogin() ? $employee->getUserLogin()->getEmail() : null;

            if ($employee->isEditable() == false) {
                $profileInfo['is_editable'] = false;
            } else {
                $profileInfo['is_editable'] = true;
            }
            $result = [
                'success' => true,
                'profile' => $profileInfo,
                'login' => $login instanceof UserLogin ? $login->toArray() : [],
                ///'avatar'    => $employee->getAvatar()
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * return permissions List of Index
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getPermissionsAction(String $uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false, 'message' => 'DATA_NOT_FOUND',
        ];

        if (Helpers::__isValidUuid($uuid)) {
            $employee = Employee::findFirstByUuidCache($uuid);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $permissions = [];
                $permissions['is_viewable'] = true;
                if ($employee->isEditable() == false) {
                    $permissions['is_editable'] = false;
                } else {
                    $permissions['is_editable'] = true;
                }
                $result = [
                    'success' => true,
                    'data' => $permissions,
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function getFieldsAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $result = [
            'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidUuid($uuid)) {
            $employee = Employee::findFirstByUuid($uuid);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $result = [
                    'success' => true,
                    'data' => $employee->getFieldsDataStructure(),
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function getLoginAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $result = [
            'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidUuid($uuid)) {
            $employee = Employee::findFirstByUuidCache($uuid);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $login = $employee->getUserLogin();
                if ($login) {
                    $login = $login->toArray();
                } else {
                    $login = ['email' => $employee->getWorkemail()];
                }
                $result = [
                    'success' => true,
                    'data' => $login
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function itemAction($id = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $id = $id ? $id : $this->request->get('id');
        if ($id > 0) {
            $employee = Employee::findFirstById($id);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $employeeData = $employee->toArray();
                $employeeData['spoken_languages'] = $employee->parseSpokenLanguages();
                $employeeData['citizenships'] = $employee->parseCitizenships();
                $employeeData['dependants_number'] = $employee->countActiveDependants();
                $result = [
                    'success' => true,
                    'data' => $employeeData,
                ];
            } else {
                $result = [
                    'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
                ];
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
     * Create or update method
     * @return string
     */
    public function saveAction()
    {
        $this->view->disable();

        if (!in_array($this->request->getMethod(), ['POST', 'PUT'])) {
            exit(json_encode([
                'success' => false,
                'message' => 'ACCESS_DENIED_TEXT'
            ]));
        }

        $action = $this->request->isPost() ? 'create' : 'edit';
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $dataInput = $this->request->getJsonRawBody(true);
        if (!isset($dataInput['company_id'])) {
            if ($this->request->isPost()) $dataInput = $this->request->getPost();
            if ($this->request->isPut()) $dataInput = $this->request->getPut();
        }
        $custom = [
            'employee_id' => isset($dataInput['id']) && is_numeric($dataInput['id']) && intval($dataInput['id']) > 0 ? intval($dataInput['id']) : null,
        ];

        $this->db->begin();
        $model = new Employee();
        $model = $model->__save($custom);


        if ($model instanceof Employee || (is_object($model) && $model->getId() > 0)) {

            //Create or Edit Login when Active User = YES ONLY ON CREATE MODE
            if ($action == 'create' && $model->getActive() == Employee::STATUS_ACTIVATED) {

                $user_login = new UserLogin();

                $user_login_email = '';
                $user_login_password = '';
                if (isset($dataInput->user_login)) {
                    $user_login_email = $dataInput->user_login->email;
                    $user_login_password = $dataInput->user_login->password;
                }

                if (isset($dataInput['user_login'])) {
                    $user_login_email = $dataInput['user_login']['email'];
                    $user_login_password = $dataInput['user_login']['password'];
                }

                $checkUserModel = $user_login->__save_infos([
                    'user_login_email' => $user_login_email,
                    'user_login_password' => $user_login_password,
                    'app_id' => $model->getCompany()->getAppId(),
                    'status' => UserLogin::STATUS_ACTIVATED,
                    'group_id' => UserLogin::USER_GROUP_EE,
                ]);

                if ($checkUserModel instanceof UserLogin) {
                    //Login OK
                    $model->setUserLoginId($checkUserModel->getId());
                    try {
                        if (!$model->save()) {
                            $msg = [];
                            foreach ($model->getMessages() as $message) {
                                $msg[$message->getField()] = $message->getMessage();
                            }
                            $result = [
                                'success' => false,
                                'message' => 'SAVE_EMPLOYEE_FAILED_TEXT',
                                'raw' => $msg,
                            ];
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    } catch (\PDOException $e) {
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_EMPLOYEE_FAILED_TEXT',
                            'raw' => $e->getMessage(),
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    } catch (Exception $e) {
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_EMPLOYEE_FAILED_TEXT',
                            'raw' => $e->getMessage(),
                        ];
                        $this->db->rollback();
                        goto end_of_function;
                    }
                } else {
                    $this->db->rollback();
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_USER_LOGIN_FAILED_TEXT',
                        'detail' => $checkUserModel
                    ];
                    goto end_of_function;
                }
            }

            // Find current contract and if it's new employee
            if ($this->request->isPost()) {

                $contract = Contract::findFirst([
                    'conditions' => 'from_company_id=:from_company_id: AND to_company_id=:to_company_id:',
                    'bind' => [
                        'from_company_id' => $model->getCompanyId(),
                        'to_company_id' => ModuleModel::$user_profile->getCompanyId()
                    ]
                ]);


                if (!$contract instanceof Contract) {
                    $contract = new Contract();
                    $contract->setFromCompanyId($model->getCompanyId());
                    $contract->setToCompanyId(ModuleModel::$user_profile->getCompanyId());
                    $contract->setStartDate(time());
                    $contract->save();
                }

                // Save current user to user_in_contract
                $employee_in_contract = new EmployeeInContract();
                $employee_in_contract->setContractId($contract->getId());
                $employee_in_contract->setEmployeeId($model->getId());

                try {
                    if ($employee_in_contract->save()) {
                        $this->db->commit();
                        $result = [
                            'success' => true,
                            'data' => $model,
                            'message' => 'SAVE_USER_SUCCESS_TEXT'
                        ];
                    } else {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_USER_FAILED_TEXT'
                        ];
                        goto end_of_function;
                    }
                } catch (\PDOException $e) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EMPLOYEE_FAILED_TEXT',
                        'raw' => $e->getMessage(),
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                } catch (Exception $e) {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_EMPLOYEE_FAILED_TEXT',
                        'raw' => $e->getMessage(),
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }
            }


            if ($this->request->isPut()) {
                $this->db->commit();
                $result = [
                    'success' => true,
                    'data' => $model,
                    'message' => 'SAVE_USER_SUCCESS_TEXT'
                ];
                goto end_of_function;
            }
        } else {
            $this->db->rollback();
            $result = $model;
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [companyAction description]
     * @param string $company_id [description]
     * @return [type]             [description]
     */
    public function companyAction($company_id = '')
    {
        $this->checkAjaxGet();

        $access = $this->canAccessResource();
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($company_id > 0) {
            $conditions = "(company_id = " . $company_id . ")";
            $result = EmployeeInContract::loadList($conditions);
        } else {
            $result = [
                'success' => false,
                'data' => []
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param String $employee_uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteAction(String $employee_uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($employee_uuid != '') {
            $employee = Employee::findFirstByUuid($employee_uuid);


            if ($employee && $employee instanceof Employee && $employee->belongsToGms() == true) {
                //remove LOGIN
                $hrCompany = $employee->getCompany();
                if ($hrCompany->isEditable() == false) {
                    $activeContract = ModuleModel::$company->getActiveContract($hrCompany->getId());
                    if (!$activeContract || $activeContract->hasPermission(AclHelper::CONTROLLER_EMPLOYEE, AclHelper::ACTION_CREATE) == false) {
                        $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
                        goto end_of_function;
                    }
                }
                $this->db->begin();
                $user_login = $employee->getUserLogin();
                if ($user_login && $user_login instanceof UserLogin) {
                    $return = $user_login->__quickRemove();
                    if ($return['success'] == false) {
                        $this->db->rollback();
                        goto end_of_function;
                    }
                }
                //remove EMPLOYEE
                $return = $employee->__quickRemove();
                if ($return['success'] == false) {
                    $this->db->rollback();
                    goto end_of_function;
                }
                $this->db->commit();
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('create', $this->router->getControllerName());

        $dataInput = $this->request->getJsonRawBody(true);
        if (!isset($dataInput['company_id'])) {
            if ($this->request->isPost()) $dataInput = $this->request->getPost();
            if ($this->request->isPut()) $dataInput = $this->request->getPut();
        }

        $companyId = Helpers::__getRequestValue('company_id');
        $hrCompany = HrCompany::findFirstByIdCache(intval($companyId));

        if (!$hrCompany || !$hrCompany->isHr() || !$hrCompany->belongsToGms()) {
            $result = ['success' => false, 'message' => 'ACCOUNT_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        $activeContract = ModuleModel::$company->getActiveContract($hrCompany->getId());

        if ($hrCompany->isEditable() == false) {
            if (!$activeContract || $activeContract->hasPermission(AclHelper::CONTROLLER_EMPLOYEE, AclHelper::ACTION_CREATE) == false) {
                $result = ['success' => false, 'message' => 'HR_DID_NOT_GIVE_PERMISSION_TEXT'];
                goto end_of_function;
            }
        }

        $employeeModel = new Employee();
        $employeeModel->setData($dataInput);
        /*** get number ***/
        $number = $employeeModel->generateNumber();
        $nickname = $employeeModel->generateNickName();
        unset($employeeModel);

        $this->db->begin();
        $employeeModel = new Employee();
        $dataInput['number'] = $number;
        $dataInput['nickname'] = $nickname;
        $employeeModel->setData($dataInput);
        $employeeModel->setCompanyId($hrCompany->getId());
        $resultSaveEmployee = $employeeModel->__quickCreate();

        if ($resultSaveEmployee['success'] == false) {
            $this->db->rollback();
            $result = $resultSaveEmployee;
            goto end_of_function;
        }

        //Create or Edit Login when Active User = YES ONLY ON CREATE MOD

        if (!isset($activeContract) || !$activeContract) {
            $result = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            $this->db->rollback();
            goto end_of_function;
        }

        // Save current user to user_in_contract
        $employee_in_contract = new EmployeeInContract();
        $employee_in_contract->setContractId($activeContract->getId());
        $employee_in_contract->setEmployeeId($employeeModel->getId());
        $resultSaveContract = $employee_in_contract->__quickCreate();

        if ($resultSaveContract['success'] == false) {
            $result = [
                'success' => false,
                'message' => 'SAVE_EMPLOYEE_FAILED_TEXT',
                'raw' => $resultSaveContract['detail']
            ];
            $this->db->rollback();
            goto end_of_function;
        }
        $this->db->commit();
        $result = [
            'success' => true,
            'message' => 'CREATE_EMPLOYEE_SUCCESS_TEXT',
            'data' => $employeeModel
        ];
        ModuleModel::$employee = $employeeModel;
        $this->dispatcher->setParam('return', $result);


        if ($employeeModel) {
            $userLogin = $employeeModel->getUserLogin();
            if ($userLogin && isset($user_login_password) && $user_login_password != '') {
                $beanQueue = RelodayQueue::__getQueueSendMail();
                $url_login = $userLogin->getApp()->getEmployeeUrl();
                $dataArray = [
                    'action' => "sendMail",
                    'to' => $userLogin->getEmail(),
                    'email' => $userLogin->getEmail(),
                    'language' => ModuleModel::$system_language,
                    'templateName' => EmailTemplateDefault::SEND_NEW_PASSWORD,
                    'params' => [
                        'user_login' => $userLogin->getEmail(),
                        'url_login' => $url_login,
                        'user_password' => $user_login_password,
                        'user_name' => $employeeModel->getFullname(),
                    ]
                ];
                $resultCheck = $beanQueue->addQueue($dataArray);
                $result['resultCheck'] = $resultCheck;
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();

    }

    /**
     * @param $company_id
     * @return mixed
     */
    public function simpleAction($company_id = null)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        if ($company_id && is_numeric($company_id) && $company_id > 0) {
            $employees = Employee::__getSimpleListByCompany($company_id);
        } else {
            $employees = [];
        }
        $this->response->setJsonContent(['success' => true, 'data' => $employees]);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function editAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());

        //get data and sanitize data
        $dataInput = $this->request->getJsonRawBody(true);
        if (!isset($dataInput['company_id'])) {
            if ($this->request->isPost()) $dataInput = $this->request->getPost();
            if ($this->request->isPut()) $dataInput = $this->request->getPut();
        }
        unset($dataInput['company_id']);

        $employee_id = isset($dataInput['id']) && $dataInput['id'] > 0 ? $dataInput['id'] : false;
        $result = ['success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];
        if ($employee_id > 0) {
            $employee = Employee::findFirstById($employee_id);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $this->db->begin();
                if ($employee->isEditable() == false) {
                    $result = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
                    goto end_of_function;
                }

                /*** generate number ***/
                if ($employee->getNumber() == '') {
                    $number = $employee->generateNumber();
                    $dataInput['number'] = $number;
                }
                /*** generate nickname ***/
                if ($employee->getNickname() == '') {
                    $nickname = $employee->generateNickName();
                    $dataInput['nickname'] = $nickname;
                }

                $employee->setData($dataInput);

                $resultSave = $employee->__quickUpdate();
                if ($resultSave['success'] == true) {
                    $changeLoginStatus = false;
                    $login = $employee->getUserLogin();
                    if (!$login) {
                        $changeLoginStatus = true;
                    }

                    $result = [
                        'success' => true,
                        'message' => 'SAVE_EMPLOYEE_SUCCESS_TEXT',
                        'data' => $employee,
                        'changeLoginStatus' => $changeLoginStatus
                    ];
                    $this->db->commit();
                } else {
                    $this->db->rollback();
                    $result = $resultSave;
                    if (is_array($result['detail']) && count($result)) {
                        $result['message'] = reset($result['detail']);
                    }
                    goto end_of_function;
                }
            }
        }

        end_of_function:
        if ($result['success'] == true) {
            ModuleModel::$employee = $employee;
            $this->dispatcher->setParam('return', $result);
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function  searchAssigneeAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $search = Helpers::__getRequestValue('search');
        if ($search == "") {
            $search = Helpers::__getRequestValue('query');
        }
        $company_id = Helpers::__getRequestValue('company_id');
        $employee_id = Helpers::__getRequestValue('employee_id');
        $limit = Helpers::__getRequestValue('limit');
        $page = Helpers::__getRequestValue('page');
        $type = Helpers::__getRequestValue('type');
        $companies = Helpers::__getRequestValue('company_ids');
        $company_ids = [];
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $company = (array)$company;
                if (isset($company['id'])) {
                    $company_ids[] = $company['id'];
                }
            }
        }
        $params['company_ids'] = $company_ids;

        $ids = Helpers::__getRequestValue('ids');
        if (!is_array($ids) && $ids) {
            $ids = explode(',', $ids);
        }

        if(Helpers::__isValidId($company_id)) {
            $hrCompany = Company::findFirstByIdCache(intval($company_id));
            if ($hrCompany && $hrCompany->isBooker()) {
                $bookerCompanyId = $hrCompany->getId();
            } else if ($hrCompany && $hrCompany->isHr()) {
                $hrCompanyId = $hrCompany->getId();
            }
        }
        $is_filter_admin = Helpers::__getRequestValue('is_filter_admin');


        $employeesResult = Employee::__findWithFilter([
            'keyword' => $search,
            'company_id' => isset($hrCompanyId) ? $hrCompanyId : null,
            'booker_company_id' => isset($bookerCompanyId) ? $bookerCompanyId : null,
            'employee_id' => $employee_id,
            'limit' => $limit,
            'type' => $type,
            'page' => $page,
            'user_profile_uuid' => ModuleModel::$user_profile->isAdminOrManager() == false ? ModuleModel::$user_profile->getUuid() : null,
            'company_ids' => $company_ids,
            'ids' => $ids,
        ]);

        $create_assignment_right = true;
        $create_assignee_right = true;
        if ($employeesResult['success']) {
            $hrCompany = Company::findFirstById($company_id);

            if ($hrCompany) {
                if (intval($hrCompany->getAppId()) > 0) {
                    $activeContract = ModuleModel::$company->getActiveContract($hrCompany->getId());
                    if ($activeContract) {
                        if ($activeContract->hasPermission('assignment', 'create') == false) {
                            $create_assignment_right = false;
                        }
                        if ($activeContract->hasPermission('employee', 'create') == false) {
                            $create_assignee_right = false;
                        }
                    } else {
                        $create_assignment_right = false;
                        $create_assignee_right = false;
                    }
                }
            }
        }

        $employeesResult['create_assignment_right'] = $create_assignment_right;
        $employeesResult['create_assignee_right'] = $create_assignee_right;

        $this->response->setJsonContent($employeesResult);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function searchAssigneeFullAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);

        $search = Helpers::__getRequestValue('search');
        $companies = Helpers::__getRequestValue('company_ids');
        $company_ids = [];
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $company = (array)$company;
                if (isset($company['id'])) {
                    $company_ids[] = $company['id'];
                }
            }
        }
        $params['search'] = $search;
        $params['company_ids'] = $company_ids;
        /****** query ****/
        $params['query'] = Helpers::__getRequestValue('query');
        /****** start ****/
        $params['start'] = Helpers::__getRequestValue('start');
        /****** start ****/
        $params['page'] = Helpers::__getRequestValue('page');
        /****** limit ****/
        $params['limit'] = Helpers::__getRequestValue('limit');
        /**** admin or not admin ***/
        if (ModuleModel::$user_profile->isGmsAdminOrManager() == false) {
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


        $employeesResult = Employee::__findWithFilterInList($params, $ordersConfig);

        if ($employeesResult['success'] == true) {
            $employeesResult['recordsFiltered'] = ($employeesResult['total_items']);
            $employeesResult['recordsTotal'] = ($employeesResult['total_items']);
            $employeesResult['length'] = ($employeesResult['total_items']);
            $employeesResult['draw'] = Helpers::__getRequestValue('draw');
        }

        $this->response->setJsonContent($employeesResult);
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
        $this->checkAcl('index', $this->router->getControllerName());

        $result = [
            'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($uuid)) {
            $employee = Employee::findFirstById($uuid);
        }
        if (Helpers::__isValidUuid($uuid)) {
            $employee = Employee::findFirstByUuid($uuid);
        }
        if (isset($employee)) {
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $result = [
                    'success' => true,
                    'data' => $employee->getDependants(),
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [addDependantAction description]
     * @param string $id [description]ee
     * @return [type]     [description]ee
     */
    public function addDependantAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl("manage_dependant");

        $employee_uuid = Helpers::__getRequestValue('employee_uuid');
        $dependantInput = Helpers::__getRequestValueAsArray('dependant');
        if ($employee_uuid && Helpers::__isValidUuid($employee_uuid)) {
            $employee = Employee::findFirstByUuidCache($employee_uuid);
            if ($employee instanceof Employee && $employee->belongsToGms()) {

                $dependant = new Dependant();
                $dependantInput['employee_id'] = $employee->getId();
                $dependant->setData($dependantInput);
                $resultSave = $dependant->__quickCreate();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_DEPENDANT_SUCCESS_TEXT',
                        'data' => $dependant,
                    ];
                } else {
                    $result = $resultSave;
                }
            } else {
                $result = [
                    'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
        }
        if($result["success"]){
            ModuleModel::$dependant = $dependant;
            $this->dispatcher->setParam('return', $result);
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * [editDependantAction description]
     * @param string $id [description]ee
     * @return [type]     [description]ee
     */
    public function editDependantAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl("manage_dependant");

        $dependantInput = Helpers::__getRequestValuesArray();

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $dependant = Dependant::findFirstByUuid($uuid);
            if ($dependant instanceof Dependant && $dependant->belongsToGms()) {

                $dependantInput['employee_id'] = $dependant->getEmployeeId();
                $dependant->setData($dependantInput);

                $resultSave = $dependant->__quickUpdate();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_DEPENDANT_SUCCESS_TEXT',
                        'data' => $dependant,
                    ];
                } else {
                    $result = $resultSave;
                }
            } else {
                $result = [
                    'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
        }
        if($result["success"]){
            ModuleModel::$dependant = $dependant;
            $this->dispatcher->setParam('return', $result);
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [deleteDependantAction description]
     * @param string $id [description]ee
     * @return [type]     [description]ee
     */
    public function deleteDependantAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'DELETE']);
        $this->checkPermissionCreateEdit();
        $dependantInput = Helpers::__getRequestValuesArray();
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $dependant = Dependant::findFirstByUuid($uuid);
            if ($dependant instanceof Dependant && $dependant->belongsToGms()) {

                $dependantInput['employee_id'] = $dependant->getEmployeeId();
                $dependant->setData($dependantInput);
                ModuleModel::$dependant = $dependant;
                $resultSave = $dependant->__quickRemove();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'DELETE_DEPENDANT_SUCCESS_TEXT',
                        'data' => $dependant,
                    ];
                } else {
                    $result = $resultSave;
                }
            } else {
                $result = [
                    'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
        }
        if($result["success"]){
            $this->dispatcher->setParam('return', $result);
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [getDependantDetailAction description]
     * @param string $id [description]ee
     * @return [type]     [description]ee
     */
    public function getDependantDetailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $dependant = Dependant::findFirstByUuid($uuid);
            if ($dependant instanceof Dependant && $dependant->belongsToGms()) {
                $dependantArray = $dependant->toArray();
                $dependantArray['spoken_languages'] = $dependant->parseSpokenLanguages();
                $dependantArray['citizenships'] = $dependant->parseCitizenships();
                $result = [
                    'success' => true,
                    'data' => $dependantArray,
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getExpirePassportListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => false, 'data' => [], 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];
        /****** query ****/
        $params = [];
        $params['limit'] = 100;
        /****** start ****/
        $params['page'] = 0;
        $params['passport_is_unusable'] = true;
        /*** search **/
        $return = EntityDocument::__getExpiredPassportOfEmployee($params);
        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getExpireVisaListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => false, 'data' => [], 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];

        /****** query ****/
        $params = [];
        $params['limit'] = 1000;
        /****** start ****/
        $params['page'] = 0;
        $params['visa_is_unusable'] = true;
        /*** search **/
        $return = EntityDocument::__getExpiredVisaOfEmployee($params);
        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function desactivateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $uuid = Helpers::__getRequestValue('uuid');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '') {
            $employee = Employee::findFirstByUuid($uuid);
            if ($employee && $employee instanceof Employee && $employee->belongsToGms() == true) {

                $userLogin = $employee->getUserLogin();
                if ($userLogin && $userLogin instanceof $userLogin) {
                    $resultUserLogin = $userLogin->clearUserLoginWhenUserDeactivated();
                    if ($resultUserLogin['success'] == false) {
                        $return = $resultUserLogin;
                        goto end_of_function;
                    }
                }

                $employee->setActive(ModelHelper::NO);
                $employee->setLoginStatus(UserProfile::LOGIN_STATUS_INACTIVE);
                $return = $employee->__quickUpdate();
                if ($return['success'] == false) {
                    $return['message'] = 'EMPLOYEE_DESACTIVATE_FAIL_TEXT';
                } else {
                    $return['message'] = 'EMPLOYEE_DESACTIVATE_SUCCESS_TEXT';
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function activateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
        $uuid = Helpers::__getRequestValue('uuid');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '') {
            $employee = Employee::findFirstByUuid($uuid);
            if ($employee && $employee instanceof Employee && $employee->belongsToGms() == true) {
                $employee->setActive(ModelHelper::YES);
                $employee->setLoginStatus(Employee::LOGIN_STATUS_LOGIN_MISSING);
                $return = $employee->__quickUpdate();
                if ($return['success'] == false) {
                    $return['message'] = 'EMPLOYEE_ACTIVATE_FAIL_TEXT';
                } else {
                    $return['employee'] = $employee->parseArrayData();
                    $return['message'] = 'EMPLOYEE_ACTIVATE_SUCCESS_TEXT';
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
