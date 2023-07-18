<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security\Random;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayUriHelper;
use Reloday\Application\Models\RelocationExt;
use Reloday\Gms\Models\City;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyType;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\InvitationRequest;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;
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
class CompanyController extends BaseController
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
     * API load list all companies (hr and bookers)
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $options["query"] = Helpers::__getRequestValue("query");
        $options["hasBooker"] = Helpers::__getRequestValue("hasBooker");
        $options['limit'] = 50;
        $options['page'] = Helpers::__getRequestValue("page");
        $options['hasPagination'] = true;

        $relocationId = Helpers::__getRequestValue('relocationId');
        if (Helpers::__isValidId($relocationId)) {
            $relocation = Relocation::__findFirstByIdCache($relocationId);
            if ($relocation && $relocation->belongsToGms() == true) {
                //confirm $relocatioNId;
                $options['relocation_id'] = $relocationId;
            } else {
                unset($relocationId);
            }
        }


        $resultHr = Company::__findHrWithFilter($options);

        $this->response->setJsonContent($resultHr);
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
     * API load list company
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function getListHrAccountsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');

        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');

        /***** new filter ******/
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');
        $params['hasPagination'] = Helpers::__getRequestValue('hasPagination');

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

        $result = Company::__findHrWithFilter($params, $ordersConfig);

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
    public function simpleDataAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $id = $id ? $id : $this->request->get('id');
        $company = Company::findFirst($id ? $id : 0);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if ($company && $company->belongsToGms()) {
            $companyArray = $company->toArray();
            $companyArray['country_name'] = $company->getCountryName();
            $companyArray['company_head'] = $company->getHeadProfileName();
            $return = [
                'success' => true,
                'message' => 'LOAD_DETAIL_SUCCESS_TEXT',
                'data' => $companyArray,
                'fields' => $company->getHrFieldsDataStructure()
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * load detail of company
     * @method GET
     * @route /company/detail
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);

        $id = $id ? $id : $this->request->get('id');
        $company = Company::findFirst($id ? $id : 0);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if ($company && $company->belongsToGms()) {
            $companyArray = $company->toArray();
            $companyArray['country_name'] = $company->getCountryName();
            $companyArray['company_head'] = $company->getHeadProfileName();
            $companyArray['company_application'] = $company->getApp() ? $company->getApp() : false;
            $invitation_request = InvitationRequest::findFirst([
                "conditions" => "from_company_id = :from_company_id: and to_company_id = :to_company_id:",
                "bind" => [
                    "from_company_id" => ModuleModel::$company->getId(),
                    "to_company_id" => $company->getId()
                ]
            ]);
            $companyArray['invitation_request'] = $invitation_request instanceof InvitationRequest ? intval($invitation_request->getId()) : '';

            $financial = $company->getCompanyFinancialDetail();
            if ($financial) {
                $data_financial = $financial->toArray();

                //City info
                if ($data_financial['invoicing_city_geonameid']) {
                    $city = City::findFirstByGeonameIdCache($data_financial['invoicing_city_geonameid']);
                    $data_financial['invoicing_city_name'] = $city->getAsciiname();
                }
            } else {
                $data_financial = [];
            }
            $companyArray['financial'] = $data_financial;
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
    public function updateAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_HR_ACCOUNT);


        $id = $id ? $id : $this->request->get('id');
        $company = Company::findFirst($id ? $id : 0);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if ($company && $company->belongsToGms()) {

            $dataInput = Helpers::__getRequestValuesArray();
            $company->setData($dataInput);
            $return = $company->__quickUpdate();

            if ($return['success'] == true) {
                $return['message'] = 'UPDATE_COMPANY_SUCCESS_TEXT';
            } else {
                $return['message'] = 'UPDATE_COMPANY_FAIL_TEXT';
            }
        }

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
        $this->checkAjax('PUT');
        $this->checkAclDelete(AclHelper::CONTROLLER_HR_ACCOUNT);

        $id = $this->request->getPut('id');
        $company = Company::findFirst($id ? $id : 0);

        $return = [
            'success' => false,
            'message' => 'COMPANY_NOT_FOUND'
        ];

        if ($company instanceof Company && $company->belongsToGms()) {
            // Find current contract
            $gms_company = ModuleModel::$user_profile->getCompanyId();
            $contract = Contract::findFirst([
                'conditions' => 'from_company_id=' . $company->getId() . ' AND to_company_id=' . $gms_company
            ]);

            if ($contract instanceof Contract)
                $contract->delete();
        }

        if ($company->delete()) {
            $return = [
                'success' => false,
                'message' => 'DELETE_SUCCESS'
            ];
        } else {
            $return = [
                'success' => false,
                'message' => 'DELETE_ERROR'
            ];
        }

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
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $type = Helpers::__getRequestValue('type');
        if ($type == '') {
            $type = $this->request->getQuery('_type');
        }

        $result = Company::__loadCurrentAccountsList([
            'type' => $type
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
            $return = ['success' => true, 'message' => 'SAVE_COMPANY_SUCCESS_TEXT', 'contract' => $contract->toArray(), 'data' => $company];
            goto end_of_function;
        } else {
            $return = $resultCreate;
        }
        end_of_function:
        $return['input'] = $custom_data;
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function __OLD_createApplicationAction($id)
    {
        //2 Create (if not exist) or Update APP (if exist);
        /** create app is not exist and re affecte to company **/
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit(AclHelper::CONTROLLER_HR_ACCOUNT);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];
        if ($id && $id > 0) {
            $companyHrClient = Company::findFirstById($id);
            if ($companyHrClient && $companyHrClient->belongsToGms()) {

                if ($companyHrClient->hasApplication()) {
                    $return = ['success' => true, 'message' => 'APP_EXISTED_TEXT'];
                    goto end_of_function;
                }

                $this->db->begin();
                $appHR = new App();
                $custom_data_app = [
                    'url' => Helpers::__getRequestValue('url'),
                    'company_id' => $companyHrClient->getId(),
                    'app_name' => Helpers::__getRequestValue('name'),
                    'app_type' => App::APP_TYPE_HR
                ];

                $appHR = $appHR->__save($custom_data_app);
                if ($appHR instanceof App) {
                    /** update company app_id */
                    $companyHrClient->setAppId($appHR->getId());
                    $resultUpdate = $companyHrClient->__quickUpdate();
                    if ($resultUpdate['success'] == false) {
                        $this->db->rollback();
                        $return = ['success' => false, 'message' => 'CREATE_APP_FAIL_TEXT'];
                        goto end_of_function;
                    }
                } else {
                    $this->db->rollback();
                    $return = $appHR;
                    goto end_of_function;
                }

                $this->db->commit();
                $return = ['success' => true, 'message' => 'CREATE_APP_SUCCESS_TEXT'];
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function __OLD_getApplicationAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];
        if ($id && $id > 0) {
            $company = Company::findFirstById($id);
            if ($company && $company->belongsToGms()) {
                $return['success'] = true;
                $return['data'] = $company->getApp();
                unset($return['message']);
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function __OLD_updateApplicationAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_HR_ACCOUNT);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];
        if ($id && $id > 0) {
            $company = Company::findFirstById($id);
            if ($company && $company->belongsToGms()) {
                $application = $company->getApp();
                $application->getName(Helpers::__getRequestValue('name'));
                $return = $application->__quickUpdate();
                if ($return['success'] == true) {
                    $return['message'] = 'APPLICATION_UPDATE_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'APPLICATION_UPDATE_FAIL_TEXT';
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function __OLD_initApplicationDataAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_HR_ACCOUNT);
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];
        if ($id && $id > 0) {
            $company = Company::findFirstById($id);
            if ($company && $company->belongsToGms()) {
                $return['success'] = true;
                $return['data'] = ['url' => \Phalcon\Utils\Slug::generate($company->getName()), "name" => $company->getName()];
                unset($return['message']);
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


}
