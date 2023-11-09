<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Http\ResponseInterface;
use SMXD\Api\Models\FavouriteProduct;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Models\Product;
use SMXD\Application\Lib\Helpers;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/api")
 */
class FavouriteProductController extends BaseController
{
    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $model = new FavouriteProduct();
        $result = [
            'success' => false,
            'message' => 'DATA_SAVE_FAIL_TEXT',
        ];

        $data = [];
        $data['end_user_id'] = ModuleModel::$user->getId();
        $data['product_id'] = Helpers::__getRequestValue('product_id');
        if (!isset($data['product_id']) || !$data['product_id']) {
            goto  end;
        }

        $favouriteProduct = FavouriteProduct::findFirst([
            'conditions' => 'product_id = :product_id: and end_user_id = :end_user_id:',
            'bind' => [
                'product_id' => $data['product_id'],
                'end_user_id' => $data['end_user_id'],
            ]
        ]);
        if ($favouriteProduct instanceof FavouriteProduct) {
            goto end;
        }

        $model->setData($data);

        $result = $model->__quickCreate();

        if (!$result['success']) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }

        $model = FavouriteProduct::findFirst([
            'conditions' => 'id = :id: and end_user_id = :end_user_id:',
            'bind' => [
                'id' => $id,
                'end_user_id' => ModuleModel::$user->getId(),
            ]
        ]);
        if (!$model instanceof FavouriteProduct) {
            goto end;
        }

        $this->db->begin();

        $return = $model->__quickRemove();
        if ($return['success']) {
            $return['message'] = "DATA_DELETE_SUCCESS_TEXT";
            $this->db->commit();
        } else {
            $return['message'] = "DATA_DELETE_FAIL_TEXT";
            $this->db->rollback();
        }

        $result = $return;

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


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
        $params['brand_ids'] = Helpers::__getRequestValue('make_ids');
        $params['category_ids'] = Helpers::__getRequestValue('category_ids');
        $params['secondary_category_id'] = Helpers::__getRequestValue('category_id');
        $params['brand_id'] = Helpers::__getRequestValue('make_id');
        $params['main_category_id'] = Helpers::__getRequestValue('parent_category_id');
        $params['location_ids'] = Helpers::__getRequestValue('location_ids');
        $params['type'] = Helpers::__getRequestValue('type');
        $params['price_min'] = Helpers::__getRequestValue('price_min');
        $params['price_max'] = Helpers::__getRequestValue('price_max');
        $params['year_min'] = Helpers::__getRequestValue('year_min');
        $params['year_max'] = Helpers::__getRequestValue('year_max');
        $params['is_favourite'] = Helpers::__getRequestValue('is_favourite');

        if (isset($params['is_favourite']) &&  $params['is_favourite'] == 1){
            $params['end_user_id'] = ModuleModel::$user->getId();
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
}
