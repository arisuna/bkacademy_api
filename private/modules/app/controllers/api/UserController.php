<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
use SMXD\App\Models\User;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\ApplicationModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class UserController extends BaseController
{

    /**
     * Get detail of object
     * @param int $id
     */
    public function detailAction($id = 0)
    {
    	$this->view->disable();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_END_USER);
        $this->checkAjaxGet();
        $data = User::findFirst((int)$id);
        $data = $data instanceof User ? $data->toArray() : [];
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
        $this->checkAcl(AclHelper::ACTION_CREATE, AclHelper::CONTROLLER_END_USER);
        $this->checkAjaxPost();

        $email = Helpers::__getRequestValue('email');
        $checkIfExist = User::findFirst([
            'conditions' => 'status <> :deleted: and email = :email:',
            'bind' => [
                'deleted' => User::STATUS_DELETED,
                'email' => $email
            ]
            ]);
        if($checkIfExist){
            $result = [
                'success' => false,
                'message' => 'EMAIL_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }
        $phone = Helpers::__getRequestValue('phone');
        $checkIfExist = User::findFirst([
            'conditions' => 'status <> :deleted: and phone = :phone:',
            'bind' => [
                'deleted' => User::STATUS_DELETED,
                'phone' => $phone
            ]
            ]);
        if($checkIfExist){
            $result = [
                'success' => false,
                'message' => 'PHONE_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }

        if(!ModuleModel::$user->getUserGroupId() == StaffUserGroup::GROUP_CRM_ADMIN && !ModuleModel::$user->getUserGroupId() == StaffUserGroup::GROUP_ADMIN){
            $result = [
                'success' => false,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
            ];
            goto end;
        }


        $model = new User();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);
        $model->setStatus(User::STATUS_ACTIVE);
        $model->setIsActive(Helpers::YES);
        $model->setUserGroupId(null);
        $model->setLoginStatus(User::LOGIN_STATUS_HAS_ACCESS);

        $this->db->begin();
        $resultCreate = $model->__quickCreate();

        if ($resultCreate['success'] == true) {
            $password = Helpers::password(10);

            $return = ModuleModel::__adminRegisterUserCognito(['email' => $model->getEmail(), 'password' => $password, 'phone_number' => str_replace('|0', '', $phone)], $model);

            if ($return['success'] == false) {
                $this->db->rollback();
                $result = $return;
            } else {
                $this->db->commit();
                $result = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT'
                ];
            }
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
        $this->checkAcl(AclHelper::ACTION_EDIT, AclHelper::CONTROLLER_END_USER);
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'Data not found'
        ];

        if (Helpers::__isValidId($id)) {

            $model = User::findFirstById($id);
            if ($model) {

                $model->setFirstname(Helpers::__getRequestValue('firstname'));
                $model->setLastname(Helpers::__getRequestValue('lastname'));
                $phone =  Helpers::__getRequestValue('phone');
                $checkIfExist = User::findFirst([
                    'conditions' => 'status <> :deleted: and phone = :phone: and id <> :id:',
                    'bind' => [
                        'deleted' => User::STATUS_DELETED,
                        'phone' => $phone,
                        'id' => $id
                    ]
                ]);
                if($checkIfExist){
                    $result = [
                        'success' => false,
                        'message' => 'PHONE_MUST_UNIQUE_TEXT'
                    ];
                    goto end;
                }
                if($phone != $model->getPhone()){
                    $resultLoginUrl = ApplicationModel::__adminForceUpdateUserAttributes($model->getEmail(), 'phone_number', str_replace('|0', '', $phone));
                    if ($resultLoginUrl['success'] == false) {
                        $result =  $resultLoginUrl;
                        goto end;
                    }
                    $model->setPhone($phone);
                }
                $model->setUserGroupId(null);

                if(!ModuleModel::$user->getUserGroupId() == StaffUserGroup::GROUP_CRM_ADMIN && !ModuleModel::$user->getUserGroupId() == StaffUserGroup::GROUP_ADMIN){
                    $result = [
                        'success' => false,
                        'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
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
        $this->checkAcl(AclHelper::ACTION_DELETE, AclHelper::CONTROLLER_END_USER);
        $this->checkAjaxDelete();
        $user = User::findFirstById($id);



        if(!ModuleModel::$user->getUserGroupId() == StaffUserGroup::GROUP_CRM_ADMIN && !ModuleModel::$user->getUserGroupId() == StaffUserGroup::GROUP_ADMIN){
            $result = [
                'success' => false,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
            ];
            goto end;
        }

        $return = ModuleModel::__adminDeleteUser($user->getAwsCognitoUuid());

        if ($return['success'] == false) {
            $return = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
            goto end;
        }
        $this->db->begin();
        $deleteUser = $user->__quickRemove();
            if ($deleteUser['success'] == true) {
                $this->db->commit();
                $result = $deleteUser;
            } else {
                $this->db->rollback();
                $result = ([
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $deleteUser
                ]);
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
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_END_USER);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['is_end_user'] = true;
        $result = User::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
