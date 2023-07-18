<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security\Random;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayUriHelper;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyType;
use Reloday\Gms\Models\Contact;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ContractPermissionItem;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\InvitationRequest;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\Office;

use Reloday\Gms\Models\App as App;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class HrAccountController extends BaseController
{

    /**
     * API load list company
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function allAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $resultHr = Company::__findHrWithFilter();
        $dataBookers = Company::__loadListBookers();
        $this->response->setJsonContent(['success' => true, 'data' => array_merge($resultHr['data'], $dataBookers)]);
        return $this->response->send();
    }

    /**
     * API load list company
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function allSimpleAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $options["query"] = Helpers::__getRequestValue("query");
        $resultHr = Company::__findHrWithFilter($options);
        $dataBookers = Company::__loadListBookers($options);
        $this->response->setJsonContent(['success' => true, 'data' => array_merge($resultHr['data'], $dataBookers)]);
        return $this->response->send();
    }


    /**
     * API load list company
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $name_search = addslashes($this->request->get('name'));

        $result = Company::__findHrWithFilter([
            'query' => $name_search,
        ]);

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [reloadAction description]
     * @return [type] [description]
     */
    public function reloadAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $resultHr = Company::__findHrWithFilter();
        $this->response->setJsonContent($resultHr);
        $this->response->send();
    }

    /**
     * Init data for company form
     * @method GET
     * @route /company/init
     * @return string
     */
    public function initAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        // -----------

        // Get init information: country, user in current company (empty for first time - create)
        $countries = Country::find(['order' => 'name']);
        $countries_arr = [];
        if (count($countries)) {
            foreach ($countries as $country) {
                $countries_arr[] = [
                    'text' => $country->getName(),
                    'value' => $country->getId()
                ];
            }
        }

        $employees = [];
        if (($id = $this->request->get('id'))) {
            $employees_list = UserProfile::find(['company_id=' . $id]);
            if (count($employees_list)) {
                foreach ($employees_list as $item) {
                    $employees[$item->getId()] = [
                        'value' => $item->getId(),
                        'text' => $item->getFirstname() . ' ' . $item->getLastname()
                    ];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'countries' => $countries_arr,
            'employees' => $employees
        ]);

    }

    /**
     * Create new company
     * @method POST
     * @route /company/save
     * @return string
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreateAndEdit(AclHelper::CONTROLLER_HR_ACCOUNT);

        /** PROCESSUS */
        // 1. Create Company
        // 2. Create APP
        // 3. Create Contract
        $this->db->begin();
        $company = new Company();
        $random = new Random();
        $custom_data = [
            /// 'app_id'            => ModuleModel::$user_login->getAppId(), // @TODO remove this configuration and create new APP for COMPANY
            'type_id' => CompanyType::TYPE_HR,
            'created_by' => ModuleModel::$user_profile->getCompanyId(),
            'uuid' => $random->uuid()
        ];

        // 1. Create company
        $result = $company->__save($custom_data);
        if ($result instanceof Company) {
            //2 Create (if not exist) or Update APP (if exist);
            /** create app is not exist and re affecte to company **/
            $appHR = $result->getApp();
            $custom_data = [];
            if (!$appHR) {
                $appHR = new App();
                $custom_data = [
                    'company_id' => $result->getId(),
                    'app_name' => $result->getName(),
                    'app_type' => App::APP_TYPE_HR
                ];
            }
            $appHR = $appHR->__save($custom_data);

            if ($appHR instanceof App) {
                /** update company app_id */
                if ($result->getAppId() !== $appHR->getId()) {
                    $result->setAppId($appHR->getId());
                    if (!$result->save()) {
                        $this->db->rollback();
                        $msg = ['success' => false, 'message' => 'CREATE_APP_FAIL_TEXT'];
                        goto end_of_function;
                    }
                }
            } else {
                $this->db->rollback();
                $msg = $appHR;
                goto end_of_function;
            }
            /** create contract if not exist **/
            //@TODO check if contract exist
            $gms_company_id = ModuleModel::$user_profile->getCompanyId();
            $gms_company = ModuleModel::$user_profile->getCompany();

            $contract = Contract::findFirst([
                'conditions' => 'from_company_id=:from_company_id: AND to_company_id=:to_company_id:',
                'bind' => [
                    'from_company_id' => $result->getId(),
                    'to_company_id' => ModuleModel::$user_profile->getCompanyId()
                ]
            ]);
            if (!$contract instanceof Contract) {
                $contract = Contract::__create([
                    'from_company' => $result->getId(),
                    'to_company' => $gms_company_id,
                    'contract_name' => $company->getName() . " - " . $gms_company->getName() . " - " . date('Ymd'),
                    'status' => Contract::STATUS_ACTIVATED
                ]);
                if (!$contract instanceof Contract) {
                    $this->db->rollback();
                    $msg = ['success' => false, 'message' => 'CREATE_CONTRACT_FAILED_TEXT'];
                    goto end_of_function;
                }
            }
            $this->db->commit();
            $msg = ['success' => true, 'message' => 'SAVE_COMPANY_SUCCESS_TEXT', 'contract' => $contract->toArray()];
            goto end_of_function;
        } else {
            $msg = $result;
        }
        end_of_function:
        $this->response->setJsonContent($msg);
        $this->response->send();
    }

    /**
     * load detail of company
     * @method GET
     * @route /company/detail
     * @param int $id
     */
    public function getAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);

        if (Helpers::__isValidUuid($uuid)) {
            $company = Company::findFirstByUuid($uuid);
        } elseif (Helpers::__isValidId($uuid)) {
            $company = Company::findFirstById($uuid);
        }

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if (isset($company) && $company && $company->belongsToGms()) {

            $companyArray = $company->toArray();
            $financial = $company->getCompanyFinancialDetail();
            $companyArray['country_name'] = $company->getCountryName();
            $companyArray['company_head'] = $company->getHeadProfileName();
            $companyArray['company_application'] = $company->getApp() ? $company->getApp() : false;
            $companyArray['is_editable'] = $company->isEditable();

            $invitation_request = InvitationRequest::findFirst([
                "conditions" => "from_company_id = :from_company_id: and to_company_id = :to_company_id:",
                "bind" => [
                    "from_company_id" => ModuleModel::$company->getId(),
                    "to_company_id" => $company->getId()
                ]
            ]);
            $companyArray['financial'] = $financial ? $financial->toArray() : [];
            $companyArray['invitation_request'] = $invitation_request instanceof InvitationRequest ? intval($invitation_request->getId()) : '';
            $return = [
                'success' => true,
                'message' => 'LOAD_DETAIL_SUCCESS_TEXT',
                'companyApp' => $company->getApp(),
                'data' => $companyArray,
                'fields' => $company->getHrFieldsDataStructure()
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Update company info
     * @method PUT
     * @route /company/update
     * @return string
     */
    public function updateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_HR_ACCOUNT);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if (Helpers::__isValidUuid($uuid)) {
            $company = Company::findFirstByUuid($uuid);
        } elseif (Helpers::__isValidId($uuid)) {
            $company = Company::findFirstById($uuid);
        }

        if (isset($company) && $company && $company->isEditable() == false) {
            $return['message'] = 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT';
            goto end;
        }

        if (isset($company) && $company && $company->belongsToGms()) {
            $dataInput = Helpers::__getRequestValuesArray();
            $company->setData($dataInput);
            $return = $company->__quickUpdate();
            $return['isEditable'] = $company->isEditable();
            if ($return['success'] == true) {
                $return['message'] = 'UPDATE_COMPANY_SUCCESS_TEXT';
            } else {
                $return['message'] = 'UPDATE_COMPANY_FAIL_TEXT';
            }
        }
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function updateFinancialDataAction(){
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_HR_ACCOUNT);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        $dataCompanyFinancial = (array)Helpers::__getRequestValuesArray();
        if (!Helpers::__isValidId($dataCompanyFinancial['company_id'])) {
            goto end;
        }

        $company = Company::findFirstByIdCache($dataCompanyFinancial['company_id']);
        if (!$company) {
            goto end;
        }

        if (isset($company) && $company && $company->isEditable() == false) {
            $return['message'] = 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT';
            goto end;
        }
        $this->db->begin();
        $reference = Helpers::__getRequestValue("reference");
        $company->setReference($reference);
        $return = $company->__quickUpdate();
        if ($return['success'] == false) {
            $this->db->rollback();
            goto end;
        }

        $financialResultInfo = $company->__saveFinancialInfo($dataCompanyFinancial);
        if ($financialResultInfo['success'] == false) {
            $return = $financialResultInfo;
            $this->db->rollback();
        } else {
            $this->db->commit();
            $return = [
                'success' => true,
                'message' => 'SAVE_COMPANY_SUCCESS_TEXT'
            ];
        }
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * Delete action
     * @route /company/update
     * @return string
     */
    public function deleteAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclDelete(AclHelper::CONTROLLER_HR_ACCOUNT);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $password = Helpers::__getRequestValue('password');
        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user_login->getEmail(), $password);
        if ($checkPassword['success'] == false) {
            $return = $checkPassword;
            $return['message'] = 'PASSWORD_INCORRECT_TEXT';
            goto end;
        }

        $uuid = Helpers::__getRequestValue('uuid');
        $company = Company::findFirstByUuid($uuid);
        if (isset($company) && $company && $company->isEditable() == false) {
            $return['message'] = 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT';
            goto end;
        }

        if ($company instanceof Company && $company->belongsToGms() && $company->isEditable() && $company->hasSubscription() == false) {
            $activeContract = ModuleModel::$company->getActiveContract($company->getId());
            $this->db->begin();
            $result = $activeContract->__quickRemove();
            if ($result['success'] == false) {
                $this->db->rollback();
                $return = [
                    'success' => false,
                    'detail' => 'Can Not Delete Contract',
                    'message' => 'DELETE_HR_ACCOUNT_FAIL_TEXT'
                ];
                goto end;
            }
            $result = $company->__quickRemove();
            if ($result['success'] == false) {
                $this->db->rollback();
                $return = [
                    'raw' => $result,
                    'success' => false,
                    'detail' => 'Can Not Delete Company',
                    'message' => 'DELETE_HR_ACCOUNT_FAIL_TEXT'
                ];
                goto end;
            }

            $this->db->commit();
            $return = [
                'success' => true,
                'message' => 'DELETE_HR_ACCOUNT_SUCCESS_TEXT'
            ];
        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * API list company
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_HR_ACCOUNT, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_TIMELOG, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $ids = Helpers::__getRequestValue('ids');
        if (!is_array($ids) && $ids) {
            $ids = explode(',', $ids);
        }


        $type = Helpers::__getRequestValue('type');
        if ($type == '') {
            $type = $this->request->getQuery('_type');
        }

        $hasBooker = Helpers::__getRequestValue('hasBooker');

        $any = Helpers::__getRequestValue('any');

        $hr_company_id = Helpers::__getRequestValue('hr_company_id');
        $limit = Helpers::__getRequestValue('pageSize');

        $page = Helpers::__getRequestValue('page');

        $result = Company::__loadCurrentAccountsList([
            'type' => $type,
            'query' => Helpers::__getRequestValue('query'),
            'ids' => $ids,
            'page' => $page,
            'hr_company_id' => $hr_company_id,
            'hasBooker' => $hasBooker,
            'any' => $any,
            'limit' => $limit
        ]);
        $result['type'] = $type;
        $this->response->setJsonContent($result);
        $this->response->send();
    }


    /**
     * @param $company_id
     */
    public function officesAction($company_id)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $company = Company::findFirstById($company_id);
        $loaded = $company instanceof Company ? true : false;

        if ($loaded) {
            $result = Office::getListByCompany($company_id);
        }

        $this->response->setJsonContent([
            'success' => $loaded ? true : false,
            'message' => $loaded ? 'LOAD_DETAIL_SUCCESS' : 'LOAD_DETAIL_FAIL',
            'data' => $loaded ? array_values($result['data']) : []
        ]);
        $this->response->send();
    }

    /**
     * @param string $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getEmployeesAction(string $uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $company = Company::findFirstByUuid($uuid);
        $loaded = $company instanceof Company && $company->belongsToGms();
        if ($loaded) {
            $params = [];
            $params['limit'] = Helpers::__getRequestValue('limit');
            $params['page'] = Helpers::__getRequestValue('page');
            $params['start'] = Helpers::__getRequestValue('start');
            $params['query'] = Helpers::__getRequestValue('search');
            $params['company_id'] = $company ? $company->getId() : null;
            $employeeRest = Employee::searchSimpleList($params);
            $this->response->setJsonContent($employeeRest);
        } else {
            $this->response->setJsonContent(['success' => false]);
        }
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getWorkersAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $company = Company::findFirstById($uuid);
        $loaded = $company instanceof Company && $company->belongsToGms() ? true : false;

        if ($loaded) {
            $result = UserProfile::__findHrContactWithFilter(['company_id' => $company_id]);
        }

        $this->response->setJsonContent([
            'success' => $loaded ? true : false,
            'message' => $loaded ? 'LOAD_DETAIL_SUCCESS' : 'LOAD_DETAIL_FAIL',
            'data' => $loaded ? array_values($result['data']) : []
        ]);
        return $this->response->send();
    }

    /**
     * Create new company
     * @method POST
     * @route /company/save
     * @return string
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclCreate(AclHelper::CONTROLLER_HR_ACCOUNT);

        /** PROCESSUS */
        // 1. Create Company
        // 3. Create Contract
        $this->db->begin();
        $company = new Company();
        $custom_data = Helpers::__getRequestValuesArray();
        $custom_data['company_type_id'] = CompanyType::TYPE_HR;
        $custom_data['created_by_company_id'] = ModuleModel::$company->getId();
        $company->setData($custom_data);
        $company->setStatus(Company::STATUS_ACTIVATED);
        $resultCreate = $company->__quickCreate();

        if ($resultCreate['success'] == true) {

            /** create contract if not exist **/
            //@TODO check if contract exist
            $gms_company_id = ModuleModel::$company->getId();
            $gms_company = ModuleModel::$company;

            $contract = Contract::__create([
                'from_company' => $company->getId(),
                'to_company' => $gms_company_id,
                'contract_name' => $company->getName() . " - " . $gms_company->getName() . " - " . date('Ymd'),
                'status' => Contract::STATUS_ACTIVATED
            ]);

            if (!$contract instanceof Contract) {
                $this->db->rollback();
                $return = ['success' => false, 'message' => 'CREATE_CONTRACT_FAILED_TEXT'];
                goto end_of_function;
            }

            $this->db->commit();
            $companyData = $company->parsedDataToArray();
            $return = [
                'success' => true,
                'message' => 'SAVE_COMPANY_SUCCESS_TEXT',
                'contract' => $contract->toArray(),
                'data' => $companyData
            ];
            goto end_of_function;
        } else {
            $return = $resultCreate;
            if (isset($resultCreate['errorMessage'])) {
                $return['message'] = $resultCreate['errorMessage'][0];
            }
        }
        end_of_function:
        $return['input'] = $custom_data;
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getOfficesAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $company = Company::findFirstByUuid($uuid);
        $return = ['success' => true, 'data' => []];
        if ($company && $company->belongsToGms()) {
            $result = Office::getListByCompany($company->getId());
            $return = [
                'success' => true,
                'data' => array_values($result['data'])
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getContactsAction(String $uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $company = Company::findFirstByUuid($uuid);
        $return = ['success' => true, 'data' => []];
        if ($company && $company->belongsToGms()) {
            $return = Contact::__findWithFilter(['uuid' => $company->getUuid()]);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $contractId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getPermissionsAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $return = [
            'success' => false,
            'message' => 'CONTRACT_NOT_FOUND_TEXT'
        ];

        $hrCompany = Company::findFirstByUuid($uuid);

        if ($hrCompany && $hrCompany->belongsToGms()) {
            $contract = ModuleModel::$company->getActiveContract($hrCompany->getId());
            if ($contract) {
                $currentPermissions = $contract->getPermissions();
                $permissionsArray = [];
                $permissions = ContractPermissionItem::__findFromCache();

                $hrCompanyEditable = $hrCompany->isEditable();
                foreach ($permissions as $permission) {
                    $item = $permission->toArray();
                    $item['is_selected'] = false;

                    if ($hrCompanyEditable == true) {
                        $item['is_selected'] = true;
                    } else {
                        foreach ($currentPermissions as $currentPermission) {
                            if ($permission->getId() == $currentPermission->getId()) {
                                $item['is_selected'] = true;
                                continue;
                            }
                        }
                    }
                    $permissionsArray[] = $item;
                }
                $return = [
                    'success' => true,
                    'data' => $permissionsArray,
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
