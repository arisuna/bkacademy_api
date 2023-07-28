<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
use SMXD\App\Models\UserLogin;
use SMXD\App\Models\User;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

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
        $this->checkAclIndex(AclHelper::CONTROLLER_USER);
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
        $this->checkAclIndex(AclHelper::CONTROLLER_USER);
        $this->checkAjaxPost();

        $model = new User();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);
        $model->setStatus(User::STATUS_ACTIVE);
        $model->setIsActive(Helpers::YES);
        $model->setLoginStatus(User::LOGIN_STATUS_HAS_ACCESS);

        $this->db->begin();
        $resultCreate = $model->__quickCreate();

        if ($resultCreate['success'] == true) {
            $password = Helpers::password(10);

            $return = ModuleModel::__adminRegisterUserCognito(['email' => $model->getEmail(), 'password' => $password]);

            if ($return['success'] == false) {
                $this->db->rollback();
                $result = $return;
            } else {
                $userLogin = $return['userLogin'];
                $model->setUserLoginId($userLogin->getId());
                $resultUpdate = $model->__quickUpdate();
                if ($resultUpdate['success'] == false) {
                    $this->db->rollback();
                    $result = $resultUpdate;
                } else {
                    $this->db->commit();
                    $result = [
                        'success' => true,
                        'message' => 'DATA_SAVE_SUCCESS_TEXT'
                    ];
                }
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
        $this->checkAclIndex(AclHelper::CONTROLLER_USER);
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'Data not found'
        ];

        if (Helpers::__isValidId($id)) {

            $model = Constant::findFirstById($id);
            if ($model) {

                $model->setName(Helpers::__getRequestValue('name'));
                $model->setValue(Helpers::__getRequestValue('value'));

                $this->db->begin();
                $resultCreate = $model->__quickUpdate();

                if ($resultCreate['success'] == true) {

                    $data_translated = Helpers::__getRequestValueAsArray('data_translated');
                    $resultAddItem = $model->createTranslatedData($data_translated);

                    if ($resultAddItem['success'] == false) {
                        $this->db->commit();
                        $result = $resultAddItem;
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
                        'message' => 'DATA_SAVE_FAIL_TEXT',
                    ]);
                }
            }
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }



    /**
     * Save data
     */
    public function saveAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_USER);

        $model = new Constant();
        if ((int)$this->request->getPost('id') > 0) {
            $model = Constant::findFirst((int)$this->request->getPost('id'));
            if (!$model instanceof Constant) {
                exit(json_encode([
                    'success' => false,
                    'msg' => 'Allowance title was not found'
                ]));
            }
        }

        $model->setName($this->request->getPost('name'));
        $model->setValue($this->request->getPost('value'));

        $this->db->begin();
        if ($model->save()) {

            // Save constant translate
            $data_translated = $this->request->getPost('data_translated');
            if (is_array($data_translated) & !empty($data_translated)) {
                // Get current data translated
                $current_data = ConstantTranslation::find('constant_id=' . $model->getId());
                if (count($current_data)) {
                    foreach ($current_data as $item) {
                        $is_break = false;
                        foreach ($data_translated as $index => $translated) {
                            if (isset($translated['id'])) {
                                if ($translated['id'] == $item->getId()) {
                                    // try update this translated
                                    if ($item->getValue() != $translated['value']) {
                                        $item->setValue($translated['value']);
                                        if (!$item->save()) {
                                            $this->db->rollback();
                                            exit(json_encode([
                                                'success' => false,
                                                'msg' => 'Try update constant translate to ' . strtoupper($item->getLanguage()) . ' was error'
                                            ]));
                                        }
                                    }
                                    unset($data_translated[$index]);
                                    $is_break = true;
                                    break;
                                }
                            }
                        }
                        if (!$is_break) {
                            // Delete current translated, because, it was not found in list posted
                            if (!$item->delete()) {
                                $this->db->rollback();
                                exit(json_encode([
                                    'success' => false,
                                    'msg' => 'Try unset constant translate was error'
                                ]));
                            }
                        }
                    }
                }

                // Try to add translate data if has new
                if (count($data_translated)) {
                    foreach ($data_translated as $item) {
                        $object = new ConstantTranslation();
                        $object->setLanguage($item['language']);
                        $object->setValue($item['value']);
                        $object->setConstantId($model->getId());

                        if (!$object->save()) {
                            $this->db->rollback();
                            exit(json_encode([
                                'success' => false,
                                'msg' => 'Try add new constant translate to ' . strtoupper($item['language']) . ' was error'
                            ]));
                        }
                    }
                }
            }

            // Update constant success
            $this->db->commit();

            $this->response->setJsonContent([
                'success' => true
            ]);
        } else {
            $this->db->rollback();
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[] = $message->getMessage();
            }
            $this->response->setJsonContent([
                'success' => false,
                'msg' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $msg
            ]);
        }

        end:
        $this->response->send();
    }

    /**
     * Delete data
     */
    public function deleteAction($id)
    {

    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_USER);
        $this->checkAjaxDelete();

        $constant = Constant::findFirstById($id);
        $translations = $constant->getConstantTranslations();
        $result = $constant->__quickRemove();
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/constant", paths={module="backend"}, methods={"GET"}, name="backend-constant-index")
     */
    public function searchAction()
    {
    	$this->view->disable();
        $this->checkAclManage(AclHelper::CONTROLLER_USER);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = User::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
