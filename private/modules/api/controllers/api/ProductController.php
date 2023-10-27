<?php

namespace SMXD\api\controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Product;
use SMXD\Application\Lib\Helpers;

class ProductController extends ModuleApiController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $brand_ids = Helpers::__getRequestValue('brand_ids');
        $params['secondary_category_id'] = Helpers::__getRequestValue('category_id');
        $params['main_category_id'] = Helpers::__getRequestValue('parent_category_id');
        $params['location_id'] = Helpers::__getRequestValue('location_id');
        $params['is_region']=  Helpers::__getRequestValue('is_region');
        $params['type']=  Helpers::__getRequestValue('type');

        if (is_array($brand_ids) && count($brand_ids) > 0) {
            foreach ($brand_ids as $brand) {
                $params['brand_ids'][] = $brand->id;
            }
        }
        $model_ids = Helpers::__getRequestValue('model_ids');
        if (is_array($model_ids) && count($model_ids) > 0) {
            foreach ($model_ids as $model) {
                $params['model_ids'][] = $model->id;
            }
        }

        $result = Product::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' =>'DATA_NOT_FOUND_TEXT'
        ];

        $data = Product::findFirstByUuid($uuid);

        if($data instanceof Product && $data->getIsDeleted() != Product::IS_DELETE_YES){
            $data_array = $data->parsedDataToArray();
            $result = [
                'success' => true,
                'data' => $data_array
            ];
        }

        $this->response->setJsonContent($result);

        end:
        $this->response->send();
    }
}