<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Http\ResponseInterface;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Address;
use SMXD\Api\Models\BusinessOrder;
use SMXD\Api\Models\BankAccount;
use SMXD\Api\Models\User;
use SMXD\Api\Models\Company;
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
        $id_number = isset($dataInput['id_number']) && $dataInput['id_number'] != '' ? $dataInput['id_number'] : "";
        $verification_status = isset($dataInput['verification_status']) && $dataInput['verification_status'] != '' ? $dataInput['verification_status'] : "";

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
                $user->setIdNumber($id_number);
                $user->setVerificationStatus($verification_status);

                $modelResult = $user->__quickUpdate();

                if ($modelResult['success']) {
                    $dataOutput = $user->getParsedArray();
                    $result = [
                        'success' => true,
                        'message' => $dataOutput['company_status'] == Company::STATUS_VERIFIED ? 'USER_PROFILE_SAVE_SUCCESS_TEXT' : 'ACCOUNT_UNDER_VERIFICATION_TEXT',
                        'data' => $dataOutput,
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
     * @return mixed
     */
    public function updateVerificationAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'PROFILE_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user = ModuleModel::$user;
        if (!$user){
            goto end;
        }

        if (!$user->canSendVerification()){
            $result = [
                'success' => false,
                'message' => 'CANNOT_VERIFY_TEXT',
            ];

            goto end;
        }

        $user->setVerificationStatus(User::PENDING_VERIFIED_STATUS);
        $modelResult = $user->__quickUpdate();

        if ($modelResult['success']) {
            $dataOutput = $user->getParsedArray();
            $result = [
                'success' => true,
                'message' => isset($dataOutput['company_status']) && $dataOutput['company_status'] == Company::STATUS_VERIFIED ? 'USER_PROFILE_SAVE_SUCCESS_TEXT' : 'ACCOUNT_UNDER_VERIFICATION_TEXT',
                'data' => $dataOutput,
            ];
        } else {
            $result = $modelResult;
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function updateIdNumberAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'PROFILE_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user = ModuleModel::$user;
        $id_number = isset($dataInput['id_number']) && $dataInput['id_number'] != '' ? $dataInput['id_number'] : "";

        if ($user != '') {

            $user->setIdNumber($id_number);

            $modelResult = $user->__quickUpdate();

            if ($modelResult['success']) {
                $dataOutput = $user->getParsedArray();
                $result = [
                    'success' => true,
                    'message' => isset($dataOutput['company_status']) && $dataOutput['company_status'] == Company::STATUS_VERIFIED ? 'USER_PROFILE_SAVE_SUCCESS_TEXT' : 'ACCOUNT_UNDER_VERIFICATION_TEXT',
                    'data' => $dataOutput,
                ];
            } else {
                $result = $modelResult;
            }
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function canVerifyAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $result = [
            'success' => false,
            'message' => 'PROFILE_SAVE_FAIL_TEXT',
        ];

        $user = ModuleModel::$user;
        if(!$user){
            goto end;
        }

        $result['success'] = true;
        $result['data'] = $user->canSendVerification();

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
    public function updateAddressAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'ADDRESS_NOT_FOUND_TEXT'
        ];

        if ($uuid == null || !Helpers::__isValidUuid($uuid)) {
            goto end;
        }
        $model = Address::findFirst([
            'conditions' => 'uuid = :uuid: and end_user_id = :end_user_id:',
            'bind' => [
                'uuid' => $uuid,
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
    public function deleteAddressAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $result = [
            'success' => false,
            'message' => 'ADDRESS_NOT_FOUND_TEXT'
        ];

        if ($uuid == null || !Helpers::__isValidUuid($uuid)) {
            goto end;
        }

        $address = Address::findFirst([
            'conditions' => 'uuid = :uuid: and end_user_id = :end_user_id:',
            'bind' => [
                'uuid' => $uuid,
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
