<?php

namespace SMXD\app\controllers\api;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Address;
use SMXD\App\Models\District;
use SMXD\App\Models\Province;
use SMXD\App\Models\Ward;
use SMXD\Application\Lib\AclHelper;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class AddressController extends BaseController
{
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['company_id'] = Helpers::__getRequestValue('company_id');
        $params['end_user_id'] = Helpers::__getRequestValue('end_user_id');
        $params['address_type'] = Helpers::__getRequestValue('address_type');

        $result = Address::__findWithFilters($params, $ordersConfig);

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function detailAction(string $id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $data = Address::findFirstById($id);
        $data = $data instanceof Address ? $data->toArray() : [];
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

//        $name = Helpers::__getRequestValue('name');
//        $checkIfExist = Address::findFirst([
//            'conditions' => 'name = :name:',
//            'bind' => [
//                'name' => $name
//            ]
//        ]);
//
//        if ($checkIfExist) {
//            $result = [
//                'success' => false,
//                'message' => 'NAME_MUST_UNIQUE_TEXT'
//            ];
//            goto end;
//        }

        $model = new Address();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);

        $result = $model->__quickCreate();

        if (!$result['success']){
            $result = [
                'success' => false,
                'detail' => is_array($result['detail']) ? implode(". ", $result['detail']) : $result,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function updateAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'ADDRESS_NOT_FOUND_TEXT'
        ];

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }
        $model = Address::findFirstById($id);
        if (!$model instanceof Address) {
            goto end;
        }

        $model->setData($data);

        $result = $model->__quickUpdate();
        $result['message'] = 'DATA_SAVE_FAIL_TEXT';
        if ($result['success']) {
            $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Delete data
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

        $address = Address::findFirstById($id);
        if (!$address instanceof Address) {
            goto end;
        }

        $this->db->begin();

        $return = $address->__quickRemove();
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

    public function searchProvincesAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => true,
            'data' => []
        ];

        $query = Helpers::__getRequestValue('query');
        if ($query) {
            $results = Province::find([
                'conditions' => 'name LIKE :query: OR name_en LIKE :query: OR fullname LIKE :query: OR fullname_en LIKE :query:',
                'bind' => [
                    'query' => "%" . $query . "%",
                ]
            ]);
        } else {
            $results = Province::find();
        }

        if ($results && count($results) > 0) {
            $result['data'] = $results;
        }

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function searchDistrictsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => true,
            'data' => []
        ];

        $provinceId = Helpers::__getRequestValue('province_id');
        $query = Helpers::__getRequestValue('query');
        if ($provinceId && $provinceId > 0) {
            if ($query) {
                $results = District::find([
                    'conditions' => 'province_id = :province_id: AND name LIKE :query: OR name_en LIKE :query: OR fullname LIKE :query: OR fullname_en LIKE :query:',
                    'bind' => [
                        'province_id' => $provinceId,
                        'query' => "%" . $query . "%"
                    ]
                ]);
            } else {
                $results = District::find([
                    'conditions' => 'province_id = :province_id:',
                    'bind' => [
                        'province_id' => $provinceId
                    ]
                ]);
            }

            if ($results && count($results) > 0) {
                $result['data'] = $results;
            }
        }

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function searchWardsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => true,
            'data' => []
        ];

        $districtId = Helpers::__getRequestValue('district_id');
        $query = Helpers::__getRequestValue('query');
        if ($districtId && $districtId > 0) {
            if ($query) {
                $results = Ward::find([
                    'conditions' => 'district_id = :district_id: AND name LIKE :query: OR name_en LIKE :query: OR fullname LIKE :query: OR fullname_en LIKE :query:',
                    'bind' => [
                        'district_id' => $districtId,
                        'query' => "%" . $query . "%"
                    ]
                ]);
            } else {
                $results = Ward::find([
                    'conditions' => 'district_id = :district_id:',
                    'bind' => [
                        'district_id' => $districtId
                    ]
                ]);
            }

            if ($results && count($results) > 0) {
                $result['data'] = $results;
            }
        }

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

}