<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyPricelist;
use Reloday\Gms\Models\CompanyPricelistService;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ServicePricing;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CompanyPriceListController extends BaseController
{
    /**
     * Load list company has contract & price list
     * @Route("/companypricelist", paths={module="gms"}, methods={"GET"}, name="gms-companypricelist-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $current_company_id = ModuleModel::$company->getId();
        $contracts = Contract::find([
            'from_company_id=' . (int)$current_company_id . ' OR to_company_id=' . (int)$current_company_id
        ]);
        $company_list = [];
        if (count($contracts)) {
            $company_ids = [];
            foreach ($contracts as $contract) {
                if ($current_company_id == $contract->getFromCompanyId()) {
                    $company_ids[] = $contract->getToCompanyId();
                } elseif ($current_company_id == $contract->getToCompanyId()) {
                    $company_ids[] = $contract->getFromCompanyId();
                }
            }
            // Find list company
            $companies = Company::find('id IN (' . implode(',', $company_ids) . ')');
            if (count($companies)) {
                foreach ($companies as $company) {
                    $company_list[$company->getId()] = $company->toArray();
                }
            }
        }


        // Load current company has been setup price
        $company_pricelist = CompanyPricelist::findByCompanyId($current_company_id);
        $listArray = [];
        if (count($company_pricelist)) {
            foreach ($company_pricelist as $item) {
                $listArray[$item->getId()] = $item->toArray();
                $listArray[$item->getId()]['total_service'] = $item->countServicePricing();
                $listArray[$item->getId()]['company_name'] = $item->getCompanyRelated()->getName();
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'companies' => $company_list,
            'list' => $listArray
        ]);
        return $this->response->send();
    }


    /**
     * Load list company has contract & price list
     * @Route("/companypricelist", paths={module="gms"}, methods={"GET"}, name="gms-companypricelist-index")
     */
    public function listAction()
    {
        $this->checkAjaxGet();
        $current_company_id = ModuleModel::$company->getId();
        $company_pricelist = CompanyPricelist::find('company_id=' . $current_company_id);
        $list = [];
        if (count($company_pricelist)) {
            foreach ($company_pricelist as $item) {
                $list[$item->getId()] = $item->toArray();
                $list[$item->getId()]['name'] = $item->getCompanyRelated()->getName();
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => array_values($list)
        ]);
        return $this->response->send();
    }


    /**
     * Get list service template
     */
    public function initAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $services = ServicePricing::find([
            'conditions' => 'company_id=:company_id: AND status <> :status_deleted:',
            'bind' => [
                'company_id' => ModuleModel::$user_profile->getCompanyId(),
                'status_deleted' => ServicePricing::STATUS_DELETED
            ]
        ]);
        $list = [];
        if (count($services)) {
            foreach ($services as $service) {
                $list[$service->getId()] = $service->toArray();
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => ($list)
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAvailableCompaniesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $companyIds = CompanyPricelist::find([
            'columns' => 'company_related_id',
            'distinct' => true,
            'conditions' => 'company_id=:company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
            ]
        ]);
        $list = [];
        if (count($companyIds)) {
            foreach ($companyIds as $companyId) {
                $list[] = $companyId['company_related_id'];
            }
        }
        $options['hasPagination'] = false;
        $options['except_company_ids'] = $list;
        $resultHr = Company::__findHrWithFilter($options, [], false) ;
        $bookers =  Company::find([
            'conditions' => 'company_type_id = :company_type_id: AND created_by_company_id = :created_by_company_id: AND status = :status_active:',
            'bind' => [
                'company_type_id' => Company::TYPE_BOOKER,
                'created_by_company_id' => ModuleModel::$company->getId(),
                'status_active' => Company::STATUS_ACTIVATED
            ]
        ]);
        if(count($bookers) > 0){
            foreach($bookers as $booker){
                $item["id"] = $booker->getId();
                $item["name"] = $booker->getName();
                array_push($resultHr["data"],$item);
            }

        }

        $this->response->setJsonContent($resultHr);
        return $this->response->send();
    }

    // Get current object
    public function detailAction($id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = [];
        if ($id == '') {
            $id = Helpers::__getRequestValue('id');
        }

        $company_price = CompanyPricelist::findFirst('id=' . (int)$id . " AND company_id=" . ModuleModel::$user_profile->getCompanyId());

        if ($company_price instanceof CompanyPricelist && $company_price->belongsToGms()) {
            $result = $company_price->toArray();
            $result['services'] = [];
            $result['company_name'] = $company_price->getCompanyName();
            $result['company_related_name'] = $company_price->getCompanyRelatedName();
            $result['services'] = [];
            if (count($company_price->countCompanyPriceServices())) {
                foreach ($company_price->getCompanyPriceServices() as $service) {
                    if ($service->getServicePricing()) {
                        $result['services'][$service->getServicePriceId()] = $service->toArray();
                        $result['services'][$service->getServicePriceId()]['selected'] = true;
                        $result['services'][$service->getServicePriceId()]['origin_currency_code'] = $service->getServicePricing()->getCurrencyCode();
                        $result['services'][$service->getServicePriceId()]['price'] = $service->getServicePricing()->getPrice();
                        $result['services'][$service->getServicePriceId()]['name'] = $service->getServicePricing()->getName();
                        $result['services'][$service->getServicePriceId()]['is_deleted'] = $service->getServicePricing()->isDeleted();
                    }
                }
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $result
        ]);
        return $this->response->send();
    }

    // Get current object
    public function simpleDataAction($id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $result = [];
        if ($id == '') {
            $id = Helpers::__getRequestValue('id');
        }

        $company_price = CompanyPricelist::findFirst('id=' . (int)$id . " AND company_id=" . ModuleModel::$user_profile->getCompanyId());

        if ($company_price instanceof CompanyPricelist && $company_price->belongsToGms()) {
            $result = $company_price->toArray();
            $result['services'] = [];
            $result['company_name'] = $company_price->getCompanyName();
            $result['company_related_name'] = $company_price->getCompanyRelatedName();
            if (count($company_price->countCompanyPriceServices())) {
                foreach ($company_price->getCompanyPriceServices() as $service) {
                    $result['services'][$service->getServicePriceId()] = $service->toArray();
                    $result['services'][$service->getServicePriceId()]['name'] = $service->getName();
                    $result['services'][$service->getServicePriceId()]['price'] = $service->getPrice();
                    $result['services'][$service->getServicePriceId()]['currency_code'] = $service->getCurrencyCode();
                    $result['services'][$service->getServicePriceId()]['selected'] = true;
                    $result['services'][$service->getServicePriceId()]['is_deleted'] = $service->getServicePricing() ? $service->getServicePricing()->isDeleted() : false;
                }
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $result
        ]);
        return $this->response->send();
    }

    /**
     * Delete specific object
     */
    public function deleteAction($id = '')
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'DELETE']);
        $this->checkAcl('delete', $this->router->getControllerName());
        if ($id == '') {
            $id = Helpers::__getRequestValue('id');
        }

        $object = CompanyPricelist::findFirst('id=' . (int)$id . ' AND company_id=' . ModuleModel::$user_profile->getCompanyId());
        if (!$object instanceof CompanyPricelist) {
            $return = (([
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ]));
        }

        $result = $object->__quickRemove();
        if ($result['success'] == false) {
            $return = (([
                'success' => false,
                'message' => 'DELETE_COMPANY_PRICELIST_ERROR_TEXT'
            ]));
        } else {
            $return = (([
                'success' => true,
                'message' => 'DELETE_COMPANY_PRICELIST_SUCCESS_TEXT'
            ]));
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * price item object
     * @param $id
     * @return mixe
     */
    public function itemAction($id)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        if (is_numeric($id) && $id > 0) {
            $company_price_item = CompanyPricelist::findFirst('id=' . (int)$id . " AND company_id=" . ModuleModel::$user_profile->getCompanyId());
            if ($company_price_item instanceof CompanyPricelist) {
                $result = $company_price_item->toArray();
                $result['services'] = [];
                $result['name'] = $company_price_item->getCompanyRelated()->getName();
                // Find current list service in it
                $company_price_services = $company_price_item->getCompanyPriceServices();
                if (count($company_price_services)) {
                    foreach ($company_price_services as $service) {
                        $result['services'][$service->getServicePriceId()] = $service->toArray();
                        $result['services'][$service->getServicePriceId()]['name'] = ($service->getServicePricing() ? $service->getServicePricing()->getName() : '');
                        $result['services'][$service->getServicePriceId()]['price'] = ($service->getServicePricing() ? $service->getServicePricing()->getPrice() : '');
                        $result['services'][$service->getServicePriceId()]['currency_code'] = ($service->getServicePricing() ? $service->getServicePricing()->getCurrencyCode() : '');
                    }
                }
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $result
        ]);
        return $this->response->send();
    }

    /**
     * Save company price list
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('create', $this->router->getControllerName());
        $result = [
            'success' => true,
            'message' => 'SAVE_COMPANY_PRICELIST_SUCCESS_TEXT'
        ];
        $services = Helpers::__getRequestValueAsArray('services');

        $company_related_id = Helpers::__getRequestValue('company_related_id');
        $currency_code = Helpers::__getRequestValue('currency_code');
        $description = Helpers::__getRequestValue('description');

        $obj = CompanyPricelist::findFirst([
            'conditions' => 'company_id = :company_id: AND company_related_id = :company_related_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'company_related_id' => $company_related_id
            ]
        ]);
        if ($obj instanceof CompanyPricelist) {
            exit(json_encode([
                'success' => false,
                'message' => 'THIS_COMPANY_HAS_BEEN_SETUP_TEXT'
            ]));
        } // ---------
        $company_price = new CompanyPricelist();
        $company_price->setCompanyId(ModuleModel::$user_profile->getCompanyId());
        $company_price->setCompanyRelatedId($company_related_id);
        $company_price->setCurrencyCode($currency_code);
        $company_price->setDescription($description);
        $company_price->setTotalService(is_array($services) ? count($services) : 0);
        $this->db->begin();
        $resultSave = $company_price->__quickCreate();

        if ($resultSave['success'] == true) {
            // Make service

            if (count($services) > 0) {
                foreach ($services as $service) {

                    $service = (array)$service;
                    if (!isset($service['selected']) || $service['selected'] == false) {
                        continue;
                    }

                    $_item = new CompanyPricelistService();
                    $_item->setCompanyPricelistId($company_price->getId());
                    $_item->setServicePriceId(isset($service['service_price_id']) ? (int)$service['service_price_id'] : 0);
                    $_item->setRealPrice(isset($service['real_price']) ? $service['real_price'] : 0);
                    $resultSaveItem = $_item->__quickCreate();
                    if ($resultSaveItem['success'] == false) {
                        $this->db->rollback();
                        $result['success'] = false;
                        $result['message'] = 'ERROR_WHEN_SAVE_SERVICE_PRICE_TEXT';
                        $result['detail'] = $resultSaveItem['detail'];
                        goto end_of_function;
                    }
                }
            }
        } else {
            $this->db->rollback();
            $result['success'] = false;
            $result['message'] = 'SAVE_COMPANY_PRICELIST_ERROR_TEXT';
            $result['detail'] = [];
            $result['detail'] = $resultSave['detail'];
            goto end_of_function;
        }

        $this->db->commit();
        $data = $company_price->toArray();
        $data['company_name'] = $company_price->getCompanyRelated()->getName();
        $data['services'] = $company_price->getCompanyPriceServices();
        $result['data'] = $data;

        // Print data to client
        end_of_function:
        $result['services'] = $services;
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Save company price list
     */
    public function updateAction($id)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());
        $result = [
            'success' => true,
            'message' => 'SAVE_COMPANY_PRICELIST_SUCCESS_TEXT'
        ];
        // Update or make new company price object
        if (!(Helpers::__checkId($id) && $id > 0)) {
            $id = Helpers::__getRequestValue('id');
        }
        $services = Helpers::__getRequestValueAsArray('services');
        $company_related_id = Helpers::__getRequestValue('company_related_id');
        $currency_code = Helpers::__getRequestValue('currency_code');
        $description = Helpers::__getRequestValue('description');


        $company_price = CompanyPricelist::findFirstById($id);
        if ($company_price && $company_price->belongsToGms()) {
            $company_price->setCompanyId(ModuleModel::$user_profile->getCompanyId());
            $company_price->setCompanyRelatedId($company_related_id);
            $company_price->setCurrencyCode($currency_code);
            $company_price->setDescription($description);

            $serviceSelected = array_filter($services, function ($var) {
                return isset($var['real_price']) && is_numeric($var['real_price']) && floatval($var['real_price']) > 0;
            });

            $total = is_array($serviceSelected) ? count($serviceSelected) : 0;
            $company_price->setTotalService($total);
            $this->db->begin();
            $resultSave = $company_price->__quickUpdate();
            if ($resultSave['success'] == true) {
                // Make service
                if (is_array($services)) {
                    foreach ($services as $service) {

                        $service = (array)$service;
                        $service_id = isset($service['id']) ? (int)$service['id'] : 0;
                        if ($service_id > 0) {
                            $_item = CompanyPricelistService::findFirstById($service_id);
                        } else {
                            $_item = new CompanyPricelistService();
                        }

                        /*** delete service price list **/
                        if (!isset($service['selected']) || $service['selected'] == false) {
                            if ($_item instanceof CompanyPricelistService && $_item->getId() > 0) {
                                $resultRemove = $_item->__quickRemove();
                                if ($resultRemove['success'] == false) {
                                    $this->db->rollback();
                                    $result['success'] = false;
                                    $result['message'] = 'SAVE_COMPANY_PRICELIST_ERROR_TEXT';
                                    $result['raw_message'] = 'ERROR_WHEN_REMOVE_SERVICE_PRICE_TEXT';
                                    $result['detail'] = $resultRemove['detail'];
                                    goto end_of_function;
                                }
                            }
                            continue;
                        }

                        /*** add new service - service price list **/
                        $_item->setCompanyPricelistId($company_price->getId());
                        $_item->setServicePriceId(isset($service['service_price_id']) ? (int)$service['service_price_id'] : 0);
                        $_item->setRealPrice(isset($service['real_price']) ? $service['real_price'] : 0);
                        if ($service_id > 0) {
                            $resultSaveItem = $_item->__quickUpdate();
                        } else {
                            $resultSaveItem = $_item->__quickCreate();
                        }
                        if ($resultSaveItem['success'] == false) {
                            $this->db->rollback();
                            $result['success'] = false;
                            $result['message'] = 'SAVE_COMPANY_PRICELIST_ERROR_TEXT';
                            $result['raw_message'] = 'ERROR_WHEN_SAVE_SERVICE_PRICE_TEXT';
                            $result['detail'] = $resultSaveItem['detail'];
                            goto end_of_function;
                        }
                    }
                }
            } else {
                $this->db->rollback();
                $result['success'] = false;
                $result['message'] = 'SAVE_COMPANY_PRICELIST_ERROR_TEXT';
                $result['detail'] = $resultSave['detail'];
                goto end_of_function;
            }

            $this->db->commit();
            $data = $company_price->toArray();
            $data['company_name'] = $company_price->getCompanyRelated()->getName();
            $result['data'] = $data;

        }

        // Print data to client
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
