<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Service;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\ServiceProviderBusinessDetail;
use Reloday\Gms\Models\ServiceProviderCompany;
use Reloday\Gms\Models\ServiceProviderCompanyHasServiceProviderType;
use Reloday\Gms\Models\ServiceProviderFinancialDetail;
use Reloday\Gms\Models\ServiceProviderType;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\Media;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class SvpCompanyController extends BaseController
{
    /**
     * @Route("/svpcompany", paths={module="gms"}, methods={"GET"}, name="gms-svpcompany-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        // Load active owner company

        $country_ids = Helpers::__getRequestValue('countries');

        $providers = ServiceProviderCompany::__getSimpleServiceProvider([
            'active' => true,
            'country_ids' => $country_ids
        ]);

        $this->response->setJsonContent(['success' => true, 'data' => $providers]);
        return $this->response->send();
    }

    /**
     * @Route("/svpcompany", paths={module="gms"}, methods={"GET"}, name="gms-svpcompany-index")
     */
    public function listArchivedAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $country_ids = Helpers::__getRequestValue('countries');

        $providers = ServiceProviderCompany::__getSimpleServiceProvider([
            'active' => false,
            'country_ids' => $country_ids
        ]);

        $this->response->setJsonContent(['success' => true, 'data' => $providers]);
        return $this->response->send();
    }


    /**
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION_SERVICE, 'action' => AclHelper::ACTION_INDEX],
        ]);

        // 1. Load company details
        $service_provider = ServiceProviderCompany::findFirst($id ? $id : 0);
        if (!$service_provider instanceof ServiceProviderCompany || !$service_provider->belongsToGms()) {
            $return = [
                'success' => false,
                'message' => 'SERVICE_PROVIDER_NOT_FOUND_TEXT'
            ];
        } else {
            // Load list provider type in service
            $provider_type_ids = [];
            $provider_types = $service_provider->getServiceProviderType();
            if ($provider_types->count() > 0) {
                foreach ($provider_types as $type) {
                    $provider_type_ids[] = intval($type->getId());
                }
            }
            // 2. Load business information
            $business = $service_provider->getServiceProviderBusinessDetail();
            // 3. Load financial details
            $financial = $service_provider->getServiceProviderFinancialDetail();
            $info = $service_provider->toArray();
            $info['country_code'] = $service_provider->getCountryCode();
            $info['tagsList'] = $service_provider->getTagsObjectList();
            $info['country_name'] = $service_provider->getCountryName();
            $info['types'] = $service_provider->getTypeIdList();
            $businessInfo = $business ? $business->toArray() : [];
            $businessInfo['head_office_name'] = $business ? $business->getHeadOfficeName() : null;
            $businessInfo['skills'] = $business ? $business->getSkillsText() : "";
            $members = $service_provider->getSvpMembers();
            // Found owner information
            $return = ([
                'success' => true,
                'data' => $info,
                'info' => $info,
                'business' => $businessInfo,
                'members' => $members,
                'financial' => $financial ? $financial->toArray() : [],
                'type_in_svp' => count($provider_types) ? $provider_types->toArray() : [],
                'provider_type_ids' => $provider_type_ids
            ]);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param int $id
     */
    public function getAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => false,
            'data' => null,
            'message' => 'SERVICE_PROVIDER_NOT_FOUND_TEXT'
        ];

        if (Helpers::__isValidId($id)) {
            $service_provider = ServiceProviderCompany::__findFirstByIdWithCache($id, CacheHelper::__TIME_24H);
            if ($service_provider && $service_provider->belongsToGms()) {
                $return = ([
                    'success' => true,
                    'data' => $service_provider->toArray(),
                ]);
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param int $id
     */
    public function memberAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        // 1. Load company details
        $service_provider = ServiceProviderCompany::findFirst($id ? $id : 0);
        if (!$service_provider instanceof ServiceProviderCompany || !$service_provider->belongsToGms()) {
            $return = [
                'success' => false,
                'message' => 'SERVICE_PROVIDER_NOT_FOUND_TEXT'
            ];
        } else {
            $return = ([
                'success' => true,
                'members' => $service_provider->getSvpMembers(),
            ]);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $action = $this->request->isPost() ? 'create' : 'edit';
        $this->checkAcl($action);

        $this->db->begin();
        // 1. save general details
        $service_provider = new ServiceProviderCompany();
        $service_provider = $service_provider->__save([
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ]);
        if ($service_provider instanceof ServiceProviderCompany) {
            // 2. save business details
            $business = new ServiceProviderBusinessDetail();
            $business = $business->__save([
                'id' => $service_provider->getId(),
                'comments' => $this->request->getPut('b_comments'),
            ]);
            if ($business instanceof ServiceProviderBusinessDetail) {
                // 3. save financial details
                $financial = new ServiceProviderFinancialDetail();
                $financial = $financial->__save([
                    'id' => $service_provider->getId()
                ]);

                if ($financial instanceof ServiceProviderFinancialDetail) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_SERVICE_PROVIDER_SUCCESS_TEXT'
                    ];
                } else {
                    $this->db->rollback();
                    $result = $financial;
                }
            } else {
                $this->db->rollback();
                $result = $business;
            }

        } else {
            $this->db->rollback();
            $result = $service_provider;
        }

        // Save type
        $types = explode(',', $this->request->isPost() ? $this->request->getPost('types') : $this->request->getPut('types'));
        $current_types = ServiceProviderCompanyHasServiceProviderType::find('service_provider_company_id=' . $service_provider->getId());
        if (count($current_types)) {
            foreach ($current_types as $k => $item) {
                if ($index = array_search($item->getServiceProviderTypeId(), $types) > 0) {
                    unset($types[$index]);
                } else {
                    if (!$item->delete()) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'DELETE_TYPE_IN_SERVICE_PROVIDER_FAIL_TEXT'
                        ];
                        break;
                    }
                }
            }
        }

        if ($result['success'] & count($types) > 0) {
            foreach ($types as $item) {

                if ($item != '') {
                    $type = new ServiceProviderCompanyHasServiceProviderType();
                    $type->setServiceProviderTypeId($item);
                    $type->setServiceProviderCompanyId($service_provider->getId());

                    if (!$type->save()) {
                        $this->db->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_TYPE_TO_SERVICE_PROVIDER_FAIL_TEXT'
                        ];
                        break;
                    }
                }
            }
        }

        if ($result['success']) {
            $this->db->commit();
        }

        echo json_encode($result);
    }

    /**
     * [load_simpleAction description]
     * @return [type] [description]
     */
    public function getSimpleListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        $svp_provider_types = Helpers::__getRequestValue('provider_type_ids');
        $ids = Helpers::__getRequestValue('ids');
        if ($ids && !is_array($ids)){
            $ids = explode(',', $ids);
        }
        $taxNumberList = [];
        if ($svp_provider_types == false) {
            $bindArray = [];
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany');
            $queryBuilder->columns(['ServiceProviderCompany.id',
                'ServiceProviderCompany.uuid',
                'ServiceProviderCompany.name',
                'ServiceProviderCompany.reference',
                'ServiceProviderCompany.address',
                'ServiceProviderCompany.town',
                'ServiceProviderCompany.country_id',
                'ServiceProviderCompany.email',
                'ServiceProviderCompany.phone',
                'tax_number' => 'ServiceProviderFinancialDetail.vat_number']);
            $queryBuilder->distinct(true);
            $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceProviderFinancialDetail', 'ServiceProviderFinancialDetail.id = ServiceProviderCompany.id', 'ServiceProviderFinancialDetail');
            $queryBuilder->andWhere("ServiceProviderCompany.company_id = :company_id:",[
                'company_id' => (ModuleModel::$user_profile->getCompanyId() ? ModuleModel::$user_profile->getCompanyId() : 0),
            ]);
            $queryBuilder->andWhere("ServiceProviderCompany.status = :status_activated:", [
                'status_activated' => ServiceProviderCompany::STATUS_ACTIVATED,
            ]);
            if (is_array($ids) && $ids){
                $queryBuilder->andWhere("ServiceProviderCompany.id IN ({ids:array})", [
                    'ids' => $ids
                ]);
            }
            $companies = ($queryBuilder->getQuery()->execute());
            if (count($companies) > 0) {
                foreach ($companies as $company) {
                    $taxNumberList[$company['id']] = $company['tax_number'];
                }
            }
        } else {
            $svp_provider_type_ids = is_string($svp_provider_types) ? explode(',', $svp_provider_types) : (is_array($svp_provider_types) ? $svp_provider_types : []);
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany');
            $queryBuilder->columns(['ServiceProviderCompany.id',
                'ServiceProviderCompany.uuid',
                'ServiceProviderCompany.name',
                'ServiceProviderCompany.reference',
                'ServiceProviderCompany.address',
                'ServiceProviderCompany.town',
                'ServiceProviderCompany.country_id',
                'ServiceProviderCompany.email',
                'ServiceProviderCompany.phone']);
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\ServiceProviderCompanyHasServiceProviderType', 'ServiceProviderCompanyHasServiceProviderType.service_provider_company_id = ServiceProviderCompany.id', 'ServiceProviderCompanyHasServiceProviderType');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\ServiceProviderType', 'ServiceProviderCompanyHasServiceProviderType.service_provider_type_id = ServiceProviderType.id', 'ServiceProviderType');
            $queryBuilder->andWhere("ServiceProviderCompany.company_id = :company_id:");
            $queryBuilder->andWhere("ServiceProviderCompany.status = :status_activated:");
            $queryBuilder->andWhere("ServiceProviderType.id IN ({svp_provider_type_ids:array})");
            if (is_array($ids) && $ids){
                $queryBuilder->andWhere("ServiceProviderCompany.id IN ({ids:array})", [
                    'ids' => $ids
                ]);
            }
            $modelManager = $this->di->get('modelsManager');
            $companies = $modelManager->executeQuery($queryBuilder->getPhql(), [
                'company_id' => (ModuleModel::$user_profile->getCompanyId() ? ModuleModel::$user_profile->getCompanyId() : 0),
                'status_activated' => ServiceProviderCompany::STATUS_ACTIVATED,
                'svp_provider_type_ids' => $svp_provider_type_ids,
            ]);
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $companies,
            'taxNumberList' => $taxNumberList
        ]);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (Helpers::__isValidId($id) && $id > 0) {
            $svp_company = ServiceProviderCompany::findFirstById($id);
            if ($svp_company && $svp_company instanceof ServiceProviderCompany && $svp_company->belongsToGms() == true) {
                $return = $svp_company->__quickRemove();
                if ($return['success'] == true) {
                    $return['message'] = 'SERVICE_PROVIDER_DELETE_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'SERVICE_PROVIDER_DELETE_SUCCESS_TEXT';
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getLandlordsListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $providerType = ServiceProviderType::findFirstById(ServiceProviderCompany::TYPE_LANDLORD);
        $bindArray = [];
        $bindArray['company_id'] = (ModuleModel::$user_profile->getCompanyId() ? ModuleModel::$user_profile->getCompanyId() : 0);
        $bindArray['status'] = ServiceProviderCompany::STATUS_ACTIVATED;
        $companies = $providerType->getServiceProviderCompany([
            'conditions' => '[Reloday\Gms\Models\ServiceProviderCompany].company_id = :company_id: AND status = :status:',
            'bind' => $bindArray
        ]);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $companies,
        ]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function getAgentsListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        $providerType = ServiceProviderType::findFirstById(ServiceProviderCompany::TYPE_REAL_ESTATE_AGENT);
        $bindArray = [];
        $bindArray['company_id'] = (ModuleModel::$user_profile->getCompanyId() ? ModuleModel::$user_profile->getCompanyId() : 0);
        $bindArray['status'] = ServiceProviderCompany::STATUS_ACTIVATED;
        $companies = $providerType->getServiceProviderCompany([
            'conditions' => '[Reloday\Gms\Models\ServiceProviderCompany].company_id = :company_id: AND status = :status:',
            'bind' => $bindArray
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $companies,
        ]);
        return $this->response->send();
    }

    /**
     * @param int $id
     */
    public function getDetailsSimpleAction($id = 0)
    {
        $this->view->disable();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $service_provider = ServiceProviderCompany::findFirstById($id);
        if (!$service_provider instanceof ServiceProviderCompany || $service_provider->belongsToGms() == false) {
            exit(json_encode([
                'success' => false,
                'message' => 'SERVICE_PROVIDER_NOT_FOUND_TEXT'
            ]));
        }
        // Load list provider type in service
        $types = ServiceProviderCompanyHasServiceProviderType::find([
            'service_provider_company_id=' . $id
        ]);
        // Found owner information
        echo json_encode([
            'success' => true,
            'data' => $service_provider,
        ]);
    }


    /**
     * @return mixed
     */
    public function saveBusinessInfoAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $id = $id && Helpers::__isValidId($id) ? $id : Helpers::__getRequestValue('id');
        $return = ['success' => false, 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__isValidId($id)) {
            // 1. save general details
            $service_provider = ServiceProviderCompany::findFirstById($id);
            if ($service_provider && $service_provider->belongsToGms()) {
                if ($service_provider instanceof ServiceProviderCompany) {
                    $business = $service_provider->getServiceProviderBusinessDetail();
                    $dataBusiness = [
                        'view_extra' => Helpers::__getRequestValue('view_extra'),
                        'account_manager_name' => Helpers::__getRequestValue('account_manager_name'),
                        'account_manager_email' => Helpers::__getRequestValue('account_manager_email'),
                        'account_manager_telephone' => Helpers::__getRequestValue('account_manager_telephone'),
                        'account_manager_mobile' => Helpers::__getRequestValue('account_manager_mobile'),
                        'account_manager_user_profile_id' => Helpers::__getRequestValue('account_manager_user_profile_id'),
                        'head_office_user_profile_id' => Helpers::__getRequestValue('head_office_user_profile_id'),
                        'group_reference' => Helpers::__getRequestValue('group_reference'),
                        'areas_covered' => Helpers::__getRequestValue('areas_covered'),
                        'consultants' => Helpers::__getRequestValue('consultants'),
                        'skills' => Helpers::__getRequestValue('skills'),
                        'quality' => Helpers::__getRequestValue('quality'),
                        'comments' => Helpers::__getRequestValue('b_comments'),
                    ];
                    if ($business) {
                        $business->setData($dataBusiness);
                        $businessResult = $business->__quickUpdate($dataBusiness);
                    } else {
                        $business = new ServiceProviderBusinessDetail();
                        $business->setData($dataBusiness);
                        $business->setId($service_provider->getId());
                        $businessResult = $business->__quickCreate();
                    }
                    if (!($businessResult['success'] == true)) {
                        $return = $businessResult;
                    } else {
                        $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
                    }
                }
            }
        }

        $return['data'] = isset($business) ? $business : [];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function saveFinancialInfoAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $id = $id && Helpers::__isValidId($id) ? $id : Helpers::__getRequestValue('id');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__isValidId($id)) {

            // 1. save general details
            $service_provider = ServiceProviderCompany::findFirstById($id);
            if ($service_provider && $service_provider->belongsToGms()) {

                $financial = $service_provider->getServiceProviderFinancialDetail();
                $dataFinancial = [
                    'creditor' => Helpers::__getRequestValue('creditor'),
                    'creditor_town' => Helpers::__getRequestValue('creditor_town'),
                    'creditor_country_id' => Helpers::__getRequestValue('creditor_country_id'),
                    'creditor_reference' => Helpers::__getRequestValue('creditor_reference'),
                    'bank' => Helpers::__getRequestValue('bank'),
                    'bank_branch' => Helpers::__getRequestValue('bank_branch'),
                    'swift_code' => Helpers::__getRequestValue('swift_code'),
                    'account_number' => Helpers::__getRequestValue('account_number'),
                    'account_name' => Helpers::__getRequestValue('account_name'),
                    'commission' => Helpers::__getRequestValue('commission'),
                ];
                if ($financial) {
                    $financialResult = $financial->__update($dataFinancial);
                } else {
                    $financial = new ServiceProviderFinancialDetail();
                    $financial->setId($service_provider->getId());
                    $dataFinancial['id'] = $service_provider->getId();
                    $financialResult = $financial->__create($dataFinancial);
                }

                if ($financialResult['success'] == false) {
                    $return = $financialResult;
                } else {
                    $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
                }
            }
        }

        $return['data'] = isset($financial) ? $financial : [];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function saveBasicInfoAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $id = $id && Helpers::__isValidId($id) ? $id : Helpers::__getRequestValue('id');
        $return = ['success' => false, 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__isValidId($id)) {
            $serviceProvider = ServiceProviderCompany::findFirstById($id);
            if ($serviceProvider && $serviceProvider->belongsToGms()) {
                $dataCompany = (array)Helpers::__getRequestValues();

                $this->db->begin();

                $resultServiceProvider = $serviceProvider->__update($dataCompany);
                if ($resultServiceProvider['success'] == false) {
                    $this->db->rollback();
                    $return = $resultServiceProvider;
                    goto end_of_function;
                }

                $provider_type_ids = Helpers::__getRequestValue('provider_type_ids');
                $resultProviderTypes = $serviceProvider->saveProviderTypes($provider_type_ids);

                if ($resultProviderTypes['success'] == false) {
                    $this->db->rollback();
                    $return = $resultProviderTypes;
                    goto end_of_function;
                }

                $this->db->commit();
                $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
            }
        }

        end_of_function:
        $return['data'] = isset($serviceProvider) ? $serviceProvider : [];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function updateServiceCompanyProviderAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $id = $id && Helpers::__isValidId($id) ? $id : Helpers::__getRequestValue('id');
        $return = ['success' => false, 'DATA_NOT_FOUND_TEXT'];


        if (Helpers::__isValidId($id)) {
            $service_provider = ServiceProviderCompany::findFirstById($id);
            if ($service_provider && $service_provider->belongsToGms()) {
                $dataCompany = (array)Helpers::__getRequestValues();
                $service_provider->setData($dataCompany);
                $result = $service_provider->__quickUpdate();
                if ($result['success']) {
                    $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createProviderAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);
        $this->checkAcl('create', 'svp_company');
        $return = ['success' => false, 'SAVE_SERVICE_PROVIDER_SUCCESS_TEXT'];

        $this->db->begin();
        // 1. save general details
        $serviceProvider = new ServiceProviderCompany();

        $dataCompany = (array)Helpers::__getRequestValues();
        $dataCompany['company_id'] = ModuleModel::$company->getId();
        $resultSave = $serviceProvider->__create($dataCompany);

        if ($resultSave['success'] == true) {
            // Save type
            $provider_type_ids = Helpers::__getRequestValue('provider_type_ids');
            $resultProviderTypes = $serviceProvider->saveProviderTypes($provider_type_ids);

            if ($resultProviderTypes['success'] == false) {
                $this->db->rollback();
                $return = $resultProviderTypes;
                goto end_of_function;
            }

            $this->db->commit();
            $return = [
                'success' => true,
                'message' => 'SAVE_SERVICE_PROVIDER_SUCCESS_TEXT',
                'data' => $serviceProvider
            ];
        } else {
            $this->db->rollback();
            $return = $resultSave;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getTypesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $types = ServiceProviderType::find([
            'order' => 'name', 'cache' => [
                'key' => '__SVP_TYPE_LIST__',
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $types
        ]);
        end:
        return $this->response->send();
    }

    /**
     * Search Company Service
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $options = [];
        $options['query'] = Helpers::__getRequestValue('query');
        $options['page'] = Helpers::__getRequestValue('page');
        $options['limit'] = Helpers::__getRequestValue('limit');
        $options['company_id'] = Helpers::__getRequestValue('company_id');
        $options['country_id'] = Helpers::__getRequestValue('country_id');
        $options['service_company_id'] = Helpers::__getRequestValue('service_company_id');
        $options['service_provider_type_id'] = Helpers::__getRequestValue('service_provider_type_id');
        $options['provider_type_name'] = Helpers::__getRequestValue('provider_type_name');
        $options['is_deleted'] = Helpers::__getRequestValue('is_deleted');
        $options['service_provider_type_ids'] = Helpers::__getRequestValue('service_provider_type_ids');
        $options['country_ids'] = Helpers::__getRequestValue('country_ids');
        $options['isFullOption'] = Helpers::__getRequestValue('isFullOption');
        $options['self_service'] = Helpers::__getRequestValue('self_service');

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


        $return = ServiceProviderCompany::__findWithFilter($options, $ordersConfig);
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getTypeAction($id)
    {
        $this->checkAjaxGet();
        $return = ['success' => false, 'data' => null];
        if (Helpers::__isValidId($id)) {
            $serviceProviderType = ServiceProviderType::__findFirstByIdWithCache($id);
            if ($serviceProviderType) {
                $return = ['success' => true, 'data' => $serviceProviderType];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}

