<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Http\ResponseInterface;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Address;
use SMXD\Api\Models\BusinessOrder;
use SMXD\Api\Models\User;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\ApplicationModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/api")
 */
class ProfileController extends BaseController
{
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $profile_array = ModuleModel::$user ? ModuleModel::$user->getParsedArray() : null;

        unset($profile_array['id']);
        unset($profile_array['aws_cognito_uuid']);

        $result = [
            'success' => true,
            'profile' => $profile_array
        ];

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    public function getListOrdersAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $profile = ModuleModel::$user;

        $businessOrders = BusinessOrder::find([
            'conditions' => 'creator_end_user_id = :creator_end_user_id:',
            'bind' => [
                'creator_end_user_id' => $profile->getId()
            ],
            'order' => 'created_at desc'
        ]);

        $data = [];
        foreach ($businessOrders as $businessOrder) {
            $data[] = $businessOrder->parsedDataToArray();
        }

        $result = [
            'success' => true,
            'data' => $data
        ];

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'PROFILE_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user_uuid = ModuleModel::$user ? ModuleModel::$user->getUuid() : '';
        $firstname = isset($dataInput['firstname']) && $dataInput['firstname'] != '' ? $dataInput['firstname'] : "";
        $lastname = isset($dataInput['lastname']) && $dataInput['lastname'] != '' ? $dataInput['lastname'] : "";
        $email = isset($dataInput['email']) && $dataInput['email'] != '' ? $dataInput['email'] : "";
        $birthdate = isset($dataInput['birthdate']) && $dataInput['birthdate'] != '' ? $dataInput['birthdate'] : "";

        if ($user_uuid != '' && $firstname && $lastname && $email) {
            $user = ModuleModel::$user;
            if ($user->getUuid() == $user_uuid) {

                if($user->getEmail() != $email){

                    $resultLoginUrl = ApplicationModel::__adminForceUpdateUserAttributes($user->getAwsCognitoUuid(), 'email', $email);
                    if ($resultLoginUrl['success'] == false) {
                        $result =  $resultLoginUrl;
                        goto end;
                    }
                }
                $user->setBirthdate($birthdate);
                $user->setEmail($email);
                $user->setFirstname($firstname);
                $user->setLastname($lastname);

                $modelResult = $user->__quickUpdate();

                if ($modelResult['success']) {
                    $result = [
                        'success' => true,
                        'message' => 'USER_PROFILE_SAVE_SUCCESS_TEXT',
                        'data' => $modelResult,
                    ];
                } else {
                    $result = $modelResult;
                }
            }
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function createAddressAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $model = new Address();
        $data = Helpers::__getRequestValuesArray();
        $data['end_user_id'] = ModuleModel::$user->getId();

        $model->setData($data);

        $result = $model->__quickCreate();

        if (!$result['success']) {
            $result = [
                'success' => false,
                'detail' => isset($result['detail']) && is_array($result['detail']) ? implode(". ", $result['detail']) : $result,
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
    public function updateAddressAction($id)
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
        $model = Address::findFirst([
            'conditions' => 'id = :id: and end_user_id = :end_user_id:',
            'bind' => [
                'id' => $id,
                'end_user_id' => ModuleModel::$user->getId(),
            ]
        ]);
        if (!$model instanceof Address) {
            goto end;
        }

        $model->setData($data);
        $data['end_user_id'] = ModuleModel::$user->getId();

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
    public function deleteAddressAction($id)
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

        $address = Address::findFirst([
            'conditions' => 'id = :id: and end_user_id = :end_user_id:',
            'bind' => [
                'id' => $id,
                'end_user_id' => ModuleModel::$user->getId(),
            ]
        ]);
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


    public function searchAddressAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['end_user_id'] = ModuleModel::$user->getId();

        $result = Address::__findWithFilters($params, $ordersConfig);

        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
