<?php

namespace SMXD\app\controllers\api;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
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
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['is_end_user'] = true;
        $result = Company::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Get detail of object
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $data = Company::findFirst((int)$id);
        $data = $data instanceof Company ? $data->toArray() : [];
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $email = Helpers::__getRequestValue('email');
        $checkIfExist = Company::findFirst([
            'conditions' => 'status <> :deleted: and email = :email:',
            'bind' => [
                'deleted' => Company::STATUS_INACTIVATED,
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
        if ($phone){
            $checkIfExist = Company::findFirst([
                'conditions' => 'status <> :deleted: and phone = :phone:',
                'bind' => [
                    'deleted' => CompanyExt::STATUS_INACTIVATED,
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
        $model->setStatus(CompanyExt::STATUS_ACTIVATED);

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
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
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

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }
        $model = Company::findFirstById($id);
        if (!$model instanceof Company) {
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
