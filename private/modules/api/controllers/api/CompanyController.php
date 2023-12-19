<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\Api\Models\Address;
use SMXD\Api\Models\BankAccount;
use SMXD\Api\Models\Company;
use SMXD\Api\Models\MediaAttachment;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Models\User;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Models\CompanyExt;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class CompanyController extends BaseController
{

    /**
     * Get detail of object
     * @param string $uuid
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function detailAction()
    {

        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' => 'COMPANY_NOT_FOUND_TEXT',
            'data' => ''
        ];

        $user = ModuleModel::$user;
        if (!$user || !$user->getCompanyId()) {
            goto end;
        }

        $data = Company::findFirstById($user->getCompanyId());
        if (!$data instanceof Company) {
            goto end;
        }

        $result = [
            'success' => true,
            'data' => $data->parsedDataToArray()
        ];

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $data = Helpers::__getRequestValuesArray();

        $email = Helpers::__getRequestValue('email');
        $checkIfExist = Company::findFirst([
            'conditions' => 'email = :email:',
            'bind' => [
                'email' => $email
            ]
        ]);

        if ($checkIfExist) {
            $result = [
                'success' => false,
                'message' => 'EMAIL_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }

        $phone = Helpers::__getRequestValue('phone');
        if ($phone) {
            $checkIfExist = Company::findFirst([
                'conditions' => 'phone = :phone:',
                'bind' => [
                    'phone' => $phone
                ]
            ]);
            if ($checkIfExist) {
                $result = [
                    'success' => false,
                    'message' => 'PHONE_MUST_UNIQUE_TEXT'
                ];
                goto end;
            }
        }

        $creatorUser = ModuleModel::$user;
        if (!$creatorUser instanceof User || !$creatorUser->getUuid() || $creatorUser->getCompanyId()) {
            $result = [
                'success' => false,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'
            ];
            goto end;
        }

        $data['creator_uuid'] = $creatorUser->getUuid();
        $model = new Company();
        $model->setData($data);
        $model->setStatus(CompanyExt::STATUS_UNVERIFIED);

        $this->db->begin();

        $resultCreate = $model->__quickCreate();
        if (!$resultCreate['success']) {
            $this->db->rollback();
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];

            goto end;
        }

        $creatorUser->setCompanyId($model->getId());
        $resultUpdateU = $creatorUser->__quickUpdate();
        if (!$resultUpdateU['success']) {

            $this->db->rollback();
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];

            goto end;
        }

        $this->db->commit();
        $result = [
            'success' => true,
            'message' => 'DATA_SAVE_SUCCESS_TEXT',
            'data' => $model->toArray()
        ];

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function updateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'COMPANY_NOT_FOUND_TEXT'
        ];

        if ($uuid == null || !Helpers::__isValidUuid($uuid)) {
            goto end;
        }

        if (!isset($data['name']) || !$data['name']) {
            $result = [
                'success' => false,
                'message' => 'NAME_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }

        $model = Company::findFirstByUuidCache($uuid);
        if (!$model instanceof Company) {
            goto end;
        }

        if ($model->getStatus() !== Company::STATUS_VERIFIED) {
            $email = Helpers::__getRequestValue('email');
            if ($email != $model->getEmail()) {
                $checkIfExist = Company::findFirst([
                    'conditions' => 'email = :email:',
                    'bind' => [
                        'email' => $email
                    ]
                ]);

                if ($checkIfExist) {
                    $result = [
                        'success' => false,
                        'message' => 'EMAIL_MUST_UNIQUE_TEXT'
                    ];
                    goto end;
                }
            }

            $phone = Helpers::__getRequestValue('phone');
            if ($phone && $phone != $model->getPhone()) {
                $checkIfExist = Company::findFirst([
                    'conditions' => 'phone = :phone:',
                    'bind' => [
                        'phone' => $phone
                    ]
                ]);
                if ($checkIfExist) {
                    $result = [
                        'success' => false,
                        'message' => 'PHONE_MUST_UNIQUE_TEXT'
                    ];
                    goto end;
                }
            }

            $data['status'] = $model->getStatus();

            if ($model->getStatus() == Company::STATUS_UNVERIFIED && $model->getTaxNumber() && $model->getAddress() && $model->getTaxpayerName()) {
                $attachment = MediaAttachment::findFirst([
                    "conditions" => "is_shared = :is_shared: and object_uuid = :object_uuid: and object_name = :object_name:",
                    "bind" => [
                        "is_shared" => Helpers::NO,
                        "object_uuid" => $model->getUuid(),
                        "object_name" => 'company',
                    ]
                ]);
                if (!$attachment instanceof MediaAttachment) {
                    $result['message'] = 'VAT_REGISTRATION_CERTIFICATE_IS_REQUIRED_TEXT';
                    goto end;
                }

                $bankAccounts = BankAccount::findFirst([
                    'conditions' => 'is_deleted = :is_deleted: and object_uuid = :object_uuid: and object_type = :object_type:',
                    'bind' => [
                        'is_deleted' => ModelHelper::NO,
                        'object_uuid' => $model->getUuid(),
                        'object_type' => BankAccount::COMPANY_TYPE,
                    ],
                ]);

                if (!$bankAccounts instanceof BankAccount) {
                    $result['message'] = 'BANK_ACCOUNT_IS_REQUIRED_TEXT';
                    goto end;
                }

                $data['status'] = Company::STATUS_PENDING;
            }

            $model->setData($data);

            $model->setStatus($data['status']);
        } else {
            $model->setName($data['name']);
        }

        $result = $model->__quickUpdate();
        $result['message'] = 'DATA_SAVE_FAIL_TEXT';
        if ($result['success']) {
            $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
        }

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
        $params['address_type'] = Helpers::__getRequestValue('address_type');

        if (!ModuleModel::$user->getCompanyId()) {
            $result = [
                'success' => false,
                'data' => [],
                'message' => 'DATA_NOT_FOUND_TEXT',
            ];
            goto end;
        }

        $params['company_id'] = ModuleModel::$user->getCompanyId();
        $result = Address::__findWithFilters($params, $ordersConfig);

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

        if (!ModuleModel::$user->getCompanyId()) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
            goto end;
        }

        $model = new Address();
        $data = Helpers::__getRequestValuesArray();
        $data['company_id'] = ModuleModel::$user->getCompanyId();

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

        if ($id == null || !Helpers::__isValidId($id) || !ModuleModel::$user->getCompanyId()) {
            goto end;
        }
        $model = Address::findFirst([
            'conditions' => 'id = :id: and company_id = :company_id:',
            'bind' => [
                'id' => $id,
                'company_id' => ModuleModel::$user->getCompanyId(),
            ]
        ]);
        if (!$model instanceof Address) {
            goto end;
        }

        $model->setData($data);
        $data['company_id'] = ModuleModel::$user->getCompanyId();

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

        if ($id == null || !Helpers::__isValidId($id) || !ModuleModel::$user->getCompanyId()) {
            goto end;
        }

        $address = Address::findFirst([
            'conditions' => 'id = :id: and company_id = :company_id:',
            'bind' => [
                'id' => $id,
                'company_id' => ModuleModel::$user->getCompanyId(),
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

        $company = Company::findFirstByUuid($uuid);
        if (!$company instanceof Company) {
            goto end;
        }
        $data = [];

        $bankAccounts = BankAccount::find([
            'conditions' => 'is_deleted = :is_deleted: and object_uuid = :object_uuid: and object_type = :object_type:',
            'bind' => [
                'is_deleted' => ModelHelper::NO,
                'object_uuid' => $uuid,
                'object_type' => BankAccount::COMPANY_TYPE,
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
        if (!Helpers::__isValidUuid($data['company_uuid'])) {
            goto end;
        }

        $bankAccount = BankAccount::findFirst([
            'conditions' => 'is_deleted = :is_deleted: and object_uuid = :object_uuid: and object_type = :object_type: and account_number = :account_number:',
            'bind' => [
                'is_deleted' => ModelHelper::NO,
                'object_uuid' => $data['company_uuid'],
                'account_number' => $data['account_number'],
                'object_type' => BankAccount::COMPANY_TYPE,
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
        $model->setObjectUuid($data['company_uuid']);
        $model->setObjectType(BankAccount::COMPANY_TYPE);
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
                'object_type' => BankAccount::COMPANY_TYPE,
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
                'object_type' => BankAccount::COMPANY_TYPE,
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
