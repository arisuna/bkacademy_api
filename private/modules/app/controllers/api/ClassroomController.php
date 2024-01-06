<?php

namespace SMXD\app\controllers\api;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\App\Models\Acl;
use SMXD\App\Models\BankAccount;
use SMXD\App\Models\Classroom;
use SMXD\App\Models\MediaAttachment;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\User;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Models\ClassroomExt;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class ClassroomController extends BaseController
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
        $params['statuses'] = $statuses;
//        if ($statuses && count($statuses) > 0) {
//            if (in_array(Classroom::STATUS_ARCHIVED, $statuses)) {
//                $params['is_deleted'] = Helpers::YES;
//            } else {
//                $params['is_deleted'] = Helpers::NO;
//            }
//
//            foreach ($statuses as $item) {
//                if ($item != -1) {
//                    $params['statuses'][] = $item;
//                }
//            }
//        }

        $result = Classroom::__findWithFilters($params, $ordersConfig);
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
            $data = Classroom::findFirstByUuid($uuid);
        } else {
            $data = Classroom::findFirstById($uuid);
        }
        $data = $data instanceof Classroom ? $data->toArray() : [];

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

        $name = Helpers::__getRequestValue('name');
        $checkIfExist = Classroom::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $name
            ]
        ]);

        if ($checkIfExist) {
            $result = [
                'success' => false,
                'message' => 'NAMEL_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }

        $model = new Classroom();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);
        $model->setStatus(Classroom::STATUS_ACTIVATED);

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
            'message' => 'CLASSROOM_NOT_FOUND_TEXT'
        ];

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }

        $model = Classroom::findFirstById($id);
        if (!$model instanceof Classroom) {
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
        $this->checkAjaxPut();

        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'CLASSROOM_NOT_FOUND_TEXT'
        ];

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }

        $classroom = Classroom::findFirstById($id);
        if (!$classroom instanceof Classroom) {
            goto end;
        }

        $this->db->begin();

        $classroom->setStatus(Classroom::STATUS_ARCHIVED);
        $result = $classroom->__quickUpdate();
        if (!$result['success']) {
            $result['message'] = 'DATA_DELETE_FAIL_TEXT';
        }

        $return = $classroom->__quickRemove();
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
