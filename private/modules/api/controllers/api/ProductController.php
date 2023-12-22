<?php

namespace SMXD\api\Controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\AdministrativeRegion;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Models\Product;
use SMXD\Api\Models\Province;
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
//        $params['category_ids'] = Helpers::__getRequestValue('category_ids');
        $params['secondary_category_id'] = Helpers::__getRequestValue('cate');
        $params['brand_id'] = Helpers::__getRequestValue('mk');
        $params['main_category_id'] = Helpers::__getRequestValue('pCate');
        $params['location_id'] = Helpers::__getRequestValue('l');
        $params['is_region'] = Helpers::__getRequestValue('isR');
        $params['location_ids'] = Helpers::__getRequestValue('location_ids');
        $params['type'] = Helpers::__getRequestValue('type');
//        $params['price_min'] = Helpers::__getRequestValue('price_min');
//        $params['price_max'] = Helpers::__getRequestValue('price_max');
//        $params['year_min'] = Helpers::__getRequestValue('year_min');
//        $params['year_max'] = Helpers::__getRequestValue('year_max');
        $filterValues = Helpers::__getRequestValue('filterValues');

        $price = Helpers::__getRequestValue('price');

        $model_ids = Helpers::__getRequestValue('model_ids');
        if (is_array($model_ids) && count($model_ids) > 0) {
            foreach ($model_ids as $model) {
                $params['model_ids'][] = $model->id;
            }
        }

        if ($filterValues) {
            if (isset($filterValues->make_ids) && $filterValues->make_ids) {
                $make_ids = explode(',', $filterValues->make_ids);
                if (is_array($make_ids) && count($make_ids) > 0) {
                    foreach ($make_ids as $make_id) {
                        if ($make_id != null && Helpers::__isValidId($make_id)) {
                            $params['brand_ids'][] = $make_id;
                        }
                    }
                }
            }

            if (isset($filterValues->year) && $filterValues->year) {
                $yearArr = explode('-', $filterValues->year);
                if (is_array($yearArr) && count($yearArr) > 1) {
                    $params['year_min'] = $yearArr[0];
                    $params['year_max'] = $yearArr[1];
                }
            }

            if (isset($filterValues->price) && $filterValues->price) {
                $priceArr = explode('-', $filterValues->price);
                if (is_array($priceArr) && count($priceArr) > 1) {
                    $params['price_min'] = $priceArr[0];
                    $params['price_max'] = $priceArr[1];
                }
            }
        }


        if (!isset($params['type']) || !$params['type']) {
            $params['type'] = 1;
        }

        if ($params['location_id']) {
            $params['location_ids'] = [];

            if (!$params['is_region']) {
                $params['location_ids'] = [$params['location_id']];
            } else {
                $provinces = Province::findWithCache(
                    [
                        'conditions' => 'administrative_region_id = :administrative_region_id:',
                        'bind' => [
                            'administrative_region_id' => $params['location_id'],
                        ]
                    ]
                );

                if ($provinces && count($provinces) > 0) {
                    foreach ($provinces as $item) {
                        $params['location_ids'][] = $item->getId();
                    }
                }
            }
        }

        $params['status'] = Product::STATUS_PUBLISHED;

        $result = Product::__findWithFilters($params, $ordersConfig);

        $result['filterValues'] = $filterValues;

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $data = Product::findFirstByUuid($uuid);

        if ($data instanceof Product && $data->getIsDeleted() != Product::IS_DELETE_YES) {
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