<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Http\ResponseInterface;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Address;
use SMXD\Api\Models\BusinessOrder;
use SMXD\Api\Models\BankAccount;
use SMXD\Api\Models\User;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Lib\ModelHelper;

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
        $data['address_type'] = isset($data['address_type']) && $data['address_type'] == Address::ADDRESS_TYPE_COMPANY ? Address::ADDRESS_TYPE_COMPANY : Address::ADDRESS_TYPE_END_USER;
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
        $data['address_type'] = isset($data['address_type']) && $data['address_type'] == Address::ADDRESS_TYPE_COMPANY ? Address::ADDRESS_TYPE_COMPANY : Address::ADDRESS_TYPE_END_USER;
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
        $params['company_id'] = Helpers::__getRequestValue('company_id');
        $params['address_type'] = Helpers::__getRequestValue('address_type');
        $params['end_user_id'] = ModuleModel::$user->getId();

        $result = Address::__findWithFilters($params, $ordersConfig);

        $this->response->setJsonContent($result);
        return $this->response->send();
    }



    public function getBankAccountsAction(string $uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $result = [
            'success' => false,
            'message' => 'COMPANY_NOT_FOUND_TEXT'
        ];

        if (!Helpers::__isValidUuid($uuid)) {
            goto end;
        }

        $user = User::findFirstByUuid($uuid);
        if (!$user instanceof User) {
            goto end;
        }
        $data = [];

        $bankAccounts = BankAccount::find([
            'conditions' => 'is_deleted = :is_deleted: and object_uuid = :object_uuid: and object_type = :object_type:',
            'bind' => [
                'is_deleted' => ModelHelper::NO,
                'object_uuid' => $uuid,
                'object_type' => BankAccount::END_USER_TYPE,
            ],
        ]);

        foreach ($bankAccounts as $bank) {
            $bankArr = $bank->toArray();
            $bankArr['account_number'] = substr($bankArr['account_number'], 0, 2) . '***' . substr($bankArr['account_number'], -4);
            $data[] = $bankArr;
        }

        $result = [
            'success' => true,
            'data' => $data
        ];

        end:

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function createBankAccountAction(): ResponseInterface
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $result = [
            'success' => false,
            'message' => 'DATA_INVALID_TEXT'
        ];

        $data = Helpers::__getRequestValuesArray();
        if (!Helpers::__isValidUuid($data['end_user_uuid'])) {
            goto end;
        }

        $bankAccount = BankAccount::findFirst([
            'conditions' => 'is_deleted = :is_deleted: and object_uuid = :object_uuid: and object_type = :object_type: and account_number = :account_number:',
            'bind' => [
                'is_deleted' => ModelHelper::NO,
                'object_uuid' => $data['end_user_uuid'],
                'account_number' => $data['account_number'],
                'object_type' => BankAccount::END_USER_TYPE,
            ],
        ]);

        if ($bankAccount instanceof BankAccount) {
            $result = [
                'success' => false,
                'message' => 'BANK_ACCOUNT_EXISTED_TEXT'
            ];

            goto end;
        }

        $model = new BankAccount();
        $model->setData($data);
        $model->setObjectUuid($data['end_user_uuid']);
        $model->setObjectType(BankAccount::END_USER_TYPE);
        $model->setIsVerified(Helpers::NO);

        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success']) {
            $result = [
                'success' => true,
                'data' => $model->toArray(),
                'message' => 'DATA_SAVE_SUCCESS_TEXT',
            ];
        } else {
            $result = [
                'success' => false,
                'detail' => is_array($resultCreate['detail']) ? implode(". ", $resultCreate['detail']) : $resultCreate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function updateBankAccountAction(): ResponseInterface
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $result = [
            'success' => false,
            'message' => 'DATA_INVALID_TEXT'
        ];

        $data = Helpers::__getRequestValuesArray();
        if (!Helpers::__isValidUuid($data['uuid'])) {
            goto end;
        }

        $model = BankAccount::findFirst([
            'conditions' => 'is_deleted = :is_deleted: and object_type = :object_type: and uuid = :uuid:',
            'bind' => [
                'is_deleted' => ModelHelper::NO,
                'uuid' => $data['uuid'],
                'object_type' => BankAccount::END_USER_TYPE,
            ],
        ]);

        if (!$model instanceof BankAccount) {
            $result = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];

            goto end;
        }

        $model->setIban($data['iban']);
        $model->setBranch($data['branch']);
        $model->setCurrency($data['currency']);

        $resultUpdate = $model->__quickUpdate();
        if ($resultUpdate['success']) {
            $result = [
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT',
                'data' => $model->toArray(),
            ];
        } else {
            $result = [
                'success' => false,
                'data' => $data,
                'detail' => is_array($resultUpdate['detail']) ? implode(". ", $resultUpdate['detail']) : $resultUpdate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function removeBankAccountAction(string $uuid = ''): ResponseInterface
    {
        $this->view->disable();

        $this->checkAjaxDelete();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (!Helpers::__isValidUuid($uuid)) {
            goto end;
        }

        $bankAccount = BankAccount::findFirst([
            'conditions' => 'is_deleted = :is_deleted: and uuid = :uuid: and object_type = :object_type:',
            'bind' => [
                'is_deleted' => ModelHelper::NO,
                'uuid' => $uuid,
                'object_type' => BankAccount::END_USER_TYPE,
            ],
        ]);

        if (!$bankAccount instanceof BankAccount) {
            goto end;
        }

        $resultCreate = $bankAccount->__quickRemove();
        if ($resultCreate['success']) {
            $result = [
                'success' => true,
                'message' => 'DATA_REMOVE_SUCCESS_TEXT',
            ];
        } else {
            $result = [
                'success' => false,
                'detail' => is_array($resultCreate['detail']) ? implode(". ", $resultCreate['detail']) : $resultCreate,
                'message' => 'DATA_REMOVE_FAILED_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
