<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Currency;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ServicePricing;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ServicePricingController extends BaseController
{
    /**
     * Load list team
     * @Route("/team", paths={module="gms"}, methods={"GET"}, name="gms-team-index")
     */
    public function indexAction()
    {
        // Find list company they have permission manage
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = [];
        $services = ServicePricing::find([
            "conditions" => "company_id = :company_id: AND status <> :status_deleted: AND type = :type_service:",
            "bind" => [
                'company_id' => ModuleModel::$user_profile->getCompanyId(),
                'type_service' => ServicePricing::TYPE_SERVICE,
                'status_deleted' => ServicePricing::STATUS_DELETED,
            ],
            'order' => 'created_at DESC'
        ]);
        if (count($services)) {
            foreach ($services as $service) {
                $result[$service->getId()] = $service;
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $result,
            'invoiced_per' => ServicePricing::$invoiced_per_list,
        ]);
        return $this->response->send();
    }


    /**
     * Load list team
     * @Route("/team", paths={module="gms"}, methods={"GET"}, name="gms-team-index")
     */
    public function simpleListAction()
    {
        // Find list company they have permission manage
        $this->view->disable();
        $this->checkAjaxGet();

        $result = [];
        $services = ServicePricing::find([
            "conditions" => "company_id = :company_id: AND status <> :status_deleted: AND type = :type_service:",
            "bind" => [
                'company_id' => ModuleModel::$user_profile->getCompanyId(),
                'type_service' => ServicePricing::TYPE_SERVICE,
                'status_deleted' => ServicePricing::STATUS_DELETED,
            ],
            'order' => 'created_at DESC'
        ]);
        if (count($services)) {
            foreach ($services as $service) {
                $result[$service->getId()] = $service->toArray();
                $result[$service->getId()]['origin_price'] = floatval($service->getPrice());
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $result,
            'invoiced_per' => ServicePricing::$invoiced_per_list,
        ]);
        return $this->response->send();
    }

    /**
     * Get detail of object
     * @param string $id
     */
    public function detailAction($id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $id = (int)($id ? $id : $this->request->get('id'));
        $service = ServicePricing::findFirstById($id);

        if ($service instanceof ServicePricing && $service->belongsToGms() && $service->isService()) {

            $data = $service->toArray();
            if ($service->getType() == ServicePricing::TYPE_SERVICE_PACK) {
                $data['service_includes'] = json_decode($service->getServiceIncludes(), true);
            }
            $result = [
                'success' => true,
                'data' => $data
            ];
        } else {
            $result = [
                'success' => false, 'message' => 'SERVICE_PACK_PRICING_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Create or update method
     * @return string
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();


        $model = new ServicePricing();
        $data = Helpers::__getRequestValuesArray();
        $data['type'] = ServicePricing::TYPE_SERVICE;
        $data['company_id'] = ModuleModel::$user_profile->getCompanyId();
        $data['status'] = ServicePricing::STATUS_ACTIVATED;
        $model->setData($data);
        $resultSave = $model->__quickCreate();
        if ($resultSave['success'] == true) {
            $result = [
                'success' => true,
                'message' => 'SAVE_SERVICE_PRICING_SUCCESS_TEXT',
                'id' => $model->getId()
            ];
        } else {
            $result = $resultSave;
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Create or update method
     * @return string
     */
    public function editAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit();

        $return = [
            'success' => false,
            'message' => 'SERVICE_PRICING_NOT_FOUND_TEXT'
        ];
        $id = $id > 0 ? $id : Helpers::__getRequestValue('id');

        if ($id > 0) {
            $model = ServicePricing::findFirstById($id);
            if ($model instanceof ServicePricing && $model->belongsToGms() && $model->isService()) {
                $data = Helpers::__getRequestValuesArray();
                $data['type'] = ServicePricing::TYPE_SERVICE;
                $data['company_id'] = ModuleModel::$user_profile->getCompanyId();
                $data['status'] = ServicePricing::STATUS_ACTIVATED;
                $model->setData($data);
                $resultSave = $model->__quickUpdate();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_SERVICE_PRICING_SUCCESS_TEXT',
                        'id' => $model->getId()
                    ];
                } else {
                    $result = $resultSave;
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function delAction($id)
    {
        $this->view->disable();
        $this->checkAjax('DELETE');
        $this->checkAcl('delete', $this->router->getControllerName());

        $return = [
            'success' => false,
            'message' => 'SERVICE_PRICING_NOT_FOUND_TEXT'
        ];

        $id = (int)$id;
        $model = ServicePricing::findFirstById($id);
        if ($model instanceof ServicePricing && $model->belongsToGms() && $model->isService()) {

            $resultRemove = $model->__quickRemove();

            if ($resultRemove['success'] == false) {
                $return = [
                    'success' => false,
                    'detail' => $resultRemove,
                    'message' => 'DELETE_SERVICE_PRICING_FAIL_TEXT'
                ];
            } else {
                $return = [
                    'success' => true,
                    'message' => 'DELETE_SERVICE_PRICING_SUCCESS_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * all periods of invoices
     */
    public function getInvoicePeriodsAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->response->setJsonContent([
            'success' => true,
            'data' => ServicePricing::$invoiced_per_list,
        ]);
        return $this->response->send();
    }
}
