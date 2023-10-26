<?php

namespace SMXD\app\controllers\api;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
use SMXD\App\Models\MediaAttachment;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\User;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\CompanyExt;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class CompanyController extends BaseController
{

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
        $params['is_end_user'] = true;
        $statuses = Helpers::__getRequestValue('statuses');
        $params['statuses'] = [];
        if ($statuses && count($statuses) > 0) {
            if (in_array(Company::STATUS_ARCHIVED, $statuses)) {
                $params['is_deleted'] = Helpers::YES;
            } else {
                $params['is_deleted'] = Helpers::NO;
            }

            foreach ($statuses as $item) {
                if ($item != -1) {
                    $params['statuses'][] = $item;
                }
            }
        }

        $result = Company::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Get detail of object
     * @param string $uuid
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function detailAction(string $uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        if (Helpers::__isValidUuid($uuid)) {
            $data = Company::findFirstByUuid($uuid);
        } else {
            $data = Company::findFirstById($uuid);
        }
        $data = $data instanceof Company ? $data->toArray() : [];

        if ($data && $data['user_verified_uuid']) {
            $verifyUser = User::findFirstByUuidCache($data['user_verified_uuid']);
            if ($verifyUser) {
                $data['user_verified_name'] = $verifyUser->getFirstname() . " " . $verifyUser->getLastname();
            }
        }

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
        if ($creatorUser instanceof User) {
            $data['creator_uuid'] = $creatorUser->getUuid();
        }

        $model = new Company();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);
        $model->setStatus(CompanyExt::STATUS_UNVERIFIED);

        $this->db->begin();

        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success']) {
            $this->db->commit();
            $result = [
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT'
            ];
        } else {
            $this->db->rollback();
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
            'message' => 'COMPANY_NOT_FOUND_TEXT'
        ];

        if ($data && isset($data['confirmText']) && $data['confirmText'] != 'unverified') {
            $result['message'] = 'CONFIRM_TEXT_INCORRECT_TEXT';
            goto end;
        }

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }

        $model = Company::findFirstById($id);
        if (!$model instanceof Company) {
            goto end;
        }

        if ($data['status'] == 1) {
            $attachment = MediaAttachment::findFirst([
                "conditions" => "is_shared = :is_shared: and object_uuid = :object_uuid: and object_name = :object_name:",
                "bind" => [
                    "is_shared" => Helpers::NO,
                    "object_uuid" => $model->getUuid(),
                    "object_name" => 'company',
                ]
            ]);

            if (!$attachment) {
                $result['message'] = 'VAT_REGISTRATION_CERTIFICATE_MISSING_TEXT';
                goto end;
            }
        }

        // can't change status
        if ($model->getStatus() != $data['status'] && $data['status'] == 1) {
            $data['verified_at'] = date('Y-m-d H:i:s');
            $data['user_verified_uuid'] = ModuleModel::$user->getUuid();
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
        $this->checkAjaxPut();

        $data = Helpers::__getRequestValuesArray();

        if ($data && isset($data['confirmText']) && $data['confirmText'] != 'delete') {
            $result['message'] = 'CONFIRM_TEXT_INCORRECT_TEXT';
            goto end;
        }

        $result = [
            'success' => false,
            'message' => 'COMPANY_NOT_FOUND_TEXT'
        ];

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }

        $company = Company::findFirstById($id);
        if (!$company instanceof Company) {
            goto end;
        }

        $this->db->begin();

        $company->setStatus(Company::STATUS_UNVERIFIED);
        $result = $company->__quickUpdate();
        if (!$result['success']) {
            $result['message'] = 'DATA_DELETE_FAIL_TEXT';
        }

        $return = $company->__quickRemove();
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
