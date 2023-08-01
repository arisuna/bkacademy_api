<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
use SMXD\App\Models\UserLogin;
use SMXD\App\Models\User;
use SMXD\App\Models\UserGroup;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class UserGroupController extends BaseController
{

    /**
     * Get detail of object
     * @param int $id
     */
    public function detailAction($id = 0)
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        $data = UserGroup::findFirst((int)$id);
        $data = $data instanceof UserGroup ? $data->toArray() : [];
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
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $name = Helpers::__getRequestValue('name');
        $checkIfExist = UserGroup::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $name
            ]
            ]);
        if($checkIfExist){
            $result = [
                'success' => false,
                'message' => 'NAMEL_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }

        $model = new UserGroup();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);

        $this->db->begin();
        $resultCreate = $model->__quickCreate();

        if ($resultCreate['success'] == true) {
            $this->db->commit();
            $result = [
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT'
            ];
        } else {
            $this->db->rollback();
            $result = ([
                'success' => false,
                'detail' => $resultCreate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ]);
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
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (Helpers::__isValidId($id)) {

            $model = UserGroup::findFirstById($id);
            if ($model) {
                $data = Helpers::__getRequestValuesArray();

                $model->setData($data);
                $name = Helpers::__getRequestValue('name');
                $checkIfExist = UserGroup::findFirst([
                    'conditions' => 'name = :name: and id <> :id:',
                    'bind' => [
                        'name' => $name,
                        'id' => $id
                    ]
                ]);
                if($checkIfExist){
                    $result = [
                        'success' => false,
                        'message' => 'NAMEL_MUST_UNIQUE_TEXT'
                    ];
                    goto end;
                }

                $this->db->begin();
                $resultCreate = $model->__quickUpdate();

                if ($resultCreate['success'] == true) {
                    $this->db->commit();
                    $result = $resultCreate;
                } else {
                    $this->db->rollback();
                    $result = ([
                        'success' => false,
                        'message' => 'DATA_SAVE_FAIL_TEXT',
                        'detail' => $resultCreate
                    ]);
                }
            }
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
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();
        $user_group = UserGroup::findFirstById($id);
        if($id == UserGroup::GROUP_ADMIN || $id == UserGroup::GROUP_CRM_ADMIN){
            $result = [
                'success' => false,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
            ];
            goto end;
        }
        $user = User::findFirst([
            'conditions' => 'status <> :deleted: and user_group_id = :user_group_id:',
            'bind' => [
                'deleted' => User::STATUS_DELETED,
                'user_group_id' => $id
            ]
        ]);
        if($user){
            $result = [
                'success' => false,
                'message' => 'CAN_NOT_DELETE_IN_USED_ITEM_TEXT'
            ];
            goto end;
        } else {
            $result = $user_group->__quickRemove();
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/constant", paths={module="backend"}, methods={"GET"}, name="backend-constant-index")
     */
    public function searchAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $user_groups = UserGroup::find([
            'conditions' => 'id <> :admin_id:',
            'bind' => [
                'admin_id' => UserGroup::GROUP_ADMIN
            ],
            'order' => 'name'
        ]);
        $return = ['success' => true, 'data' => $user_groups];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
