<?php

namespace Reloday\Gms\Controllers\API;

use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\ServicePackExt;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Office;
use Reloday\Gms\Models\ServicePack;
use Reloday\Gms\Models\ServicePackHasService;
use Reloday\Gms\Models\ServiceCompany;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ServicePackController extends BaseController
{
    const CONTROLLER_NAME = 'service_pack';

    /**
     * @Route("/servicepack", paths={module="gms"}, methods={"GET"}, name="gms-servicepack-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();
        $items = ServicePack::__findWithFilter(['is_active' => true]);
        $this->response->setJsonContent(['success' => true, 'data' => $items]);
        return $this->response->send();
    }

    /**
     * @Route("/servicepack", paths={module="gms"}, methods={"GET"}, name="gms-servicepack-index")
     */
    public function simpleListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $items = ServicePack::__findWithFilter();
        $this->response->setJsonContent(['success' => true, 'data' => $items]);
        return $this->response->send();
    }

    /**
     * @Route("/servicepack", paths={module="gms"}, methods={"GET"}, name="gms-servicepack-index")
     */
    public function getFullListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        // Load list service pack
        $items = ServicePack::__findWithFilter();
        $this->response->setJsonContent(['success' => true, 'data' => $items]);
        return $this->response->send();
    }

    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();

        $params = [];
        $params['status'] = Helpers::__getRequestValue('status');
        $params['query'] = Helpers::__getRequestValue('query');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');

        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending ? 'desc' : 'asc'
                ];
            }
        }

        $servicesRes = ServicePack::__findWithFilters($params, $ordersConfig);
        $totalPages = 0;
        $data = [];
        if ($servicesRes['success']) {
            $items = $servicesRes['data'];
            if (isset($servicesRes['total_pages'])) {
                $totalPages = $servicesRes['total_pages'];
            }

            if (count($items)) {
                foreach ($items as $servicePack) {
                    $data[$servicePack->getId()] = $servicePack->toArray();
                    $data[$servicePack->getId()]['service'] = $servicePack->getServiceCompanyByActive(true, false);
                    $data[$servicePack->getId()]['count_services'] = count($data[$servicePack->getId()]['service']);
                }
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => array_values($data),
            'total_pages' => $totalPages
        ]);
        return $this->response->send();
    }


    /**
     * @Route("/servicepack", paths={module="gms"}, methods={"GET"}, name="gms-servicepack-index")
     */
    public function getActiveListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        // Load list service pack
        $items = ServicePack::__findWithFilter(['is_active' => true]);
        $this->response->setJsonContent(['success' => true, 'data' => $items]);
        return $this->response->send();
    }


    /**
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $data = [];
        // Load detail by id
        $service_pack = ServicePack::findFirstById($id);

        if ($service_pack instanceof ServicePack) {
            $data = $service_pack->toArray();

            // Find list service in this pack
            $services = ServicePackHasService::find([
                'conditions' => 'service_pack_id = ' . $id,
                'order' => 'position ASC'
            ]);
            $data['services'] = [];
            if (count($services)) {
                foreach ($services as $key => $service) {
                    $data['services'][$key] = $service->toArray();
                    $data['services'][$key]['service_name'] = $service->getServiceCompanyName();
                }
            }
            $data['is_deleted'] = $service_pack->isDeleted();
        };

        $this->response->setJsonContent([
            'success' => !empty($data),
            'message' => empty($data) ? 'SERVICE_PACK_NOT_FOUND_TEXT' : '',
            'data' => $data
        ]);
        return $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $return = ['success' => false, 'data' => '', 'message' => 'DATA_NOT_FOUND_TEXT'];
        $service_ids = Helpers::__getRequestValueAsArray('service_ids');

        $service_pack = new ServicePack();
        $this->db->begin();
        $data = Helpers::__getRequestValuesArray();
        $data['company_id'] = ModuleModel::$company->getId();
        $data['owner_company_id'] = ModuleModel::$company->getId();
        if(isset($data['description']) && $data['description'] != null && $data['description'] != ''){
            $data['description'] = rawurldecode(base64_decode($data['description']));
        }
        $service_pack->setData($data);
        $result = $service_pack->addServiceCompanies($service_ids);

        if ($result['success'] == false) {
            $this->db->rollback();
            $return = ['success' => false, 'detail' => $result, 'message' => 'SAVE_SERVICE_PACK_FAIL_TEXT'];
            if (isset($result['errorMessage']) && is_array($result['errorMessage'])) {
                $return['message'] = reset($result['errorMessage']);
            }
        } else {
            $this->db->commit();

            foreach ($service_ids as $k => $serviceCompanyId) {
                $serviceHasPack = ServicePackHasService::findFirst([
                    'conditions' => 'service_company_id = :service_company_id: AND service_pack_id = :service_pack_id:',
                    'bind' => [
                        'service_company_id' => $serviceCompanyId,
                        'service_pack_id' => $service_pack->getId()
                    ]
                ]);

                if ($serviceHasPack) {
                    $serviceHasPack->setPosition($k);

                    $serviceHasPack->__quickUpdate();
                }
            }

            $return = ['success' => true, 'data' => $service_pack, 'message' => 'SAVE_SERVICE_PACK_SUCCESS_TEXT'];
        }
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function updateAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $return = ['success' => false, 'data' => '', 'message' => 'DATA_NOT_FOUND_TEXT'];
        $service_ids = Helpers::__getRequestValueAsArray('service_ids');

        if ($id && Helpers::__checkId($id)) {
            $service_pack = ServicePack::findFirstById($id);
            if ($service_pack && $service_pack->belongsToGms()) {

                $this->db->begin();
                $custom_data = Helpers::__getRequestValuesArray();
                $custom_data['description'] = rawurldecode(base64_decode($custom_data['description']));
                $service_pack->setData($custom_data);
                $service_pack->__quickUpdate();
                $service_packResult = $service_pack->addServiceCompanies($service_ids);

                if ($service_packResult['success'] == false) {
                    $this->db->rollback();
                    $return = ['success' => false, 'detail' => $service_packResult, 'message' => 'SAVE_SERVICE_PACK_FAIL_TEXT'];
                } else {
                    $this->db->commit();

                    foreach ($service_ids as $k => $serviceCompanyId) {
                        $serviceHasPack = ServicePackHasService::findFirst([
                            'conditions' => 'service_company_id = :service_company_id: AND service_pack_id = :service_pack_id:',
                            'bind' => [
                                'service_company_id' => $serviceCompanyId,
                                'service_pack_id' => $service_pack->getId()
                            ]
                        ]);

                        if ($serviceHasPack) {
                            $serviceHasPack->setPosition($k);
                            $serviceHasPack->__quickUpdate();
                        }
                    }

                    $relatedItems = $service_pack->getServicePackHasServiceRelations();
                    $data = $service_pack->toArray();
                    if (count($relatedItems)) {
                        foreach ($relatedItems as $service) {
                            $data['services'][$service->getServiceCompanyId()] = $service->toArray();
                            $data['services'][$service->getServiceCompanyId()]['service_name'] = $service->getServiceCompanyName();
                        }
                    }
                    $return = ['success' => true, 'data' => $data, 'message' => 'SAVE_SERVICE_PACK_SUCCESS_TEXT'];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [archiveAction description]
     * @return [type] [description]
     */
    public function archiveAction($service_id = 0)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'SERVICE_PACK_NOT_FOUND_TEXT'
        ];

        if (Helpers::__checkId($service_id)) {
            $service_pack = ServicePack::findFirstById($service_id);
            if ($service_pack && $service_pack->belongsToGms()) {
                $return = $service_pack->__quickRemove();
                if ($return['success'] == true) {
                    $return = [
                        "success" => true,
                        "message" => "SERVICE_PACK_DELETE_SUCCESS_TEXT"
                    ];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param int $id
     */
    public function getServiceListAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $data = [];
        $service_pack = ServicePack::findFirstById($id);

        if ($service_pack instanceof ServicePack && $service_pack->belongsToGms() == true) {

            $data = $service_pack->toArray();
            $services = $service_pack->getServiceCompany();
            $serviceArray = [];
            if ($services->count() > 0) {
                foreach ($services as $service) {
                    $serviceArray[$service->getUuid()] = $service;
                }
            }
        };

        $this->response->setJsonContent([
            'success' => !empty($data) ? true : false,
            'message' => empty($data) ? 'SERVICE_PACK_NOT_FOUND_TEXT' : '',
            'data' => $serviceArray
        ]);

        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function cloneServicePackAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $result = ['message' => 'SAVE_SERVICE_PACK_FAIL_TEXT', 'success' => false];
        $data = Helpers::__getRequestValuesArray();
//        $old_service_pack = ServicePack::findFirst($data['id']);

        // Start transaction
        $this->db->begin();

        // Save base information (service_pack)
        $service_pack_data = $data;
        unset($service_pack_data['id']);
        unset($service_pack_data['uuid']);

        $service_pack = new ServicePack();
        $service_pack->setData($service_pack_data);
        $service_pack->setStatus(ServicePackExt::STATUS_ACTIVATED);
        $result = $service_pack->addServiceCompanies($data['service_ids']);

        if ($result['success'] == false) {
            $this->db->rollback();
            $return = ['success' => false, 'detail' => $result, 'message' => 'SAVE_SERVICE_PACK_FAIL_TEXT'];
        } else {
            $this->db->commit();

            foreach ($data['service_ids'] as $k => $serviceCompanyId) {
                $serviceHasPack = ServicePackHasService::findFirst([
                    'conditions' => 'service_company_id = :service_company_id: AND service_pack_id = :service_pack_id:',
                    'bind' => [
                        'service_company_id' => $serviceCompanyId,
                        'service_pack_id' => $service_pack->getId()
                    ]
                ]);

                if ($serviceHasPack) {
                    $serviceHasPack->setPosition($k);
                    $serviceHasPack->__quickUpdate();
                }
            }

            $return = ['success' => true, 'data' => $service_pack, 'message' => 'SAVE_SERVICE_PACK_SUCCESS_TEXT'];
        }
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
