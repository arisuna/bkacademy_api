<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Http\ResponseInterface;
use SMXD\Api\Models\FavouriteProduct;
use SMXD\Api\Models\ModuleModel;
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

        $data = [];
        $data['end_user_id'] = ModuleModel::$user->getId();
        $data['product_id'] = Helpers::__getRequestValue('product_id');
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
            'message' => 'ADDRESS_NOT_FOUND_TEXT'
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
        if (!$return['success']) {
            $return['message'] = "DATA_DELETE_FAIL_TEXT";
            $this->db->rollback();
        } else {
            $return['message'] = "DATA_DELETE_SUCCESS_TEXT";
            $this->db->commit();
        }
        $result = $return;

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
