<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Mvc\Model;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\AclHelper;
use \SMXD\App\Controllers\ModuleApiController;
use SMXD\App\Models\Acl;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\StaffUserGroupAcl;

/**
 * Concrete implementation of Backend module controller
 *
 * @RoutePrefix("/backend/api")
 */
class AclController extends BaseController
{
    /**
     * @Route("/acl", paths={module="backend"}, methods={"GET"}, name="backend-acl-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $aclList = Acl::find();
        $this->response->setJsonContent([
            'success' => true,
            'data' => $aclList
        ]);
        return $this->response->send();
    }

    /**
     * @Route("/acl", paths={module="backend"}, methods={"GET"}, name="backend-acl-index")
     */
    public function listAction()
    {
        $this->view->disable();
        $aclLevel1List = Acl::findByLvl(1);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $aclLevel1List
        ]);
        return $this->response->send();

    }

    /**
     * @return mixed
     */
    public function getTreeAction()
    {
        $this->view->disable();
        $acl_list = Acl::find([
            'conditions' => '(acl_id IS NULL OR acl_id = 0) AND status = 1',
            'order' => 'pos, lvl ASC'
        ]);

        $aclItems = [];
        if (count($acl_list)) {

            foreach ($acl_list as $acl_item) {
                $aclItems[$acl_item->getId()] = $acl_item->getTreeChildren();
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => [[
                'id' => 0,
                'acl_id' => null,
                'name' => 'Root',
                'children' => array_values($aclItems)
            ]]
        ]);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function getAclListTreeDetailsAction($id)
    {
        $this->view->disable();

        if ($id > 0) {
            $acl = Acl::findFirstById($id);
            if ($acl) {
                $acl_list = $acl->getTreeChildren();

                $this->response->setJsonContent([
                    'success' => true,
                    'data' => [$acl_list]
                ]);

                return $this->response->send();
            }

        }


    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateAction($id)
    {
        $this->view->disable();
        $data = Helpers::__getRequestValuesArray();
        $acl_id = Helpers::__getRequestValue('acl_id');
        $resultSave = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $dependancy_acl_ids = Helpers::__getRequestValue('dependancy_acl_ids');

        if ($id != null && Helpers::__checkId($id)) {
            $acl = Acl::findFirstById($id);
            if ($acl_id > 0) {
                $parentAcl = Acl::findFirstById($acl_id);
            }
            if ($acl && $acl instanceof Acl) {

                $acl->setData($data);
                $calculatedLevel = $acl->getLevelCalculated();

                $acl->setLvl($calculatedLevel);


                $this->db->begin();
                $resultSave = $acl->__quickUpdate();
                if ($resultSave['success'] == false) {
                    $resultSave['message'] = 'DATA_SAVE_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }

                $this->db->commit();
                $resultSave['$calculatedLevel'] = $calculatedLevel;
                $resultSave['message'] = 'DATA_SAVE_SUCCESS_TEXT';
                $resultSave['data'] = $acl;
            }

        }


        end_of_function:
        $this->response->setJsonContent($resultSave);
        return $this->response->send();

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $data = Helpers::__getRequestValuesArray();

        $resultSave = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $acl = new Acl();

        if ($acl && $acl instanceof Acl) {
            $acl->setData($data);

            if ($acl->getParent()) {
                $acl->setNextPosition();
                $acl->setLvl($acl->getParent()->getLvl() + 1);
            } else {
                $acl->setNextPosition();
                $acl->setLvl(1);
            }

            if(!$acl->checkUniqueControllerAction()){
                $resultSave = [
                    'success' => false,
                    'message' => 'CONTROLLER_AND_ACTION_SHOULD_BE_UNIQUE_TEXT',
                ];
                goto end_of_function;
            }

            $acl->setStatus(Acl::STATUS_ACTIVATED);

            $resultSave = $acl->__quickCreate();
            if ($resultSave['success'] == false) {
                $resultSave['message'] = 'DATA_SAVE_FAIL_TEXT';
            } else {
                $resultSave['message'] = 'DATA_SAVE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($resultSave);
        return $this->response->send();

    }

    /**
     * @param $id
     * @return mixed
     */
    public function deleteAction($id)
    {
        $this->checkAjax('DELETE');
        $this->view->disable();
        $resultSave = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($id != null && Helpers::__checkId($id)) {
            $acl = Acl::findFirstById($id);
            if ($acl && $acl instanceof Acl) {
                $resultSave = $acl->__quickRemove();
                if ($resultSave['success'] == false) {
                    $resultSave['message'] = 'DATA_DELETE_FAIL_TEXT';
                } else {
                    $resultSave['message'] = 'DATA_DELETE_SUCCESS_TEXT';
                }
            }
        }
        $this->response->setJsonContent($resultSave);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function levelUpAction($id)
    {
        $this->checkAjax('PUT');
        $this->view->disable();
        $resultSave = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($id != null && Helpers::__checkId($id)) {
            $acl = Acl::findFirstById($id);
            if ($acl && $acl instanceof Acl) {
                $this->db->begin();
                $currentLevel = $acl->getLvl();
                $resultSave = $acl->levelUp();
                if ($resultSave['success'] == false) {
                    $resultSave['message'] = 'DATA_SAVE_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }

                $children = $acl->getChildren();
                $resultSave = ModelHelper::__quickUpdateCollection($children, ['lvl' => $currentLevel]);
                if ($resultSave['success'] == false) {
                    $resultSave['message'] = 'DATA_SAVE_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }

                $this->db->commit();
                $resultSave = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
            }
        }

        end_of_function:
        $this->response->setJsonContent($resultSave);
        return $this->response->send();
    }

    /**
     *
     */
    public function moveUpAction($id)
    {
        $this->checkAjax('PUT');
        $this->view->disable();
        $resultSave = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($id != null && Helpers::__checkId($id)) {
            $acl = Acl::findFirstById($id);

            if ($acl && $acl instanceof Acl) {
                $resultSave = $acl->moveUp();
                if ($resultSave['success'] == false) {
                    $resultSave['message'] = 'DATA_SAVE_FAIL_TEXT';
                } else {
                    $resultSave['message'] = 'DATA_SAVE_SUCCESS_TEXT';
                }
            }
        }
        $this->response->setJsonContent($resultSave);
        return $this->response->send();
    }

    /**
     *
     */
    public function moveDownAction($id)
    {
        $this->checkAjax('PUT');
        $this->view->disable();
        $resultSave = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($id != null && Helpers::__checkId($id)) {
            $acl = Acl::findFirstById($id);

            if ($acl && $acl instanceof Acl) {
                $resultSave = $acl->moveDown();
                if ($resultSave['success'] == false) {
                    $resultSave['message'] = 'DATA_SAVE_FAIL_TEXT';
                } else {
                    $resultSave['message'] = 'DATA_SAVE_SUCCESS_TEXT';
                }
            }
        }
        $this->response->setJsonContent($resultSave);
        return $this->response->send();
    }

    /**
     *
     */
    public function getControllerActionItemListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $list_controller_action = Acl::getTreeGms();
        $result = ['success' => true, 'data' => array_values($list_controller_action)];
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function removeAclItemAction()
    {

        $this->view->disable();
        $this->checkAjax('PUT');

        $user_group_id = Helpers::__getRequestValue('user_group_id');
        $acl = Helpers::__getRequestValue('acl');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        // Find group
        if ($user_group_id > 0 && $acl && isset($acl->id)) {

            $aclObject = Acl::findFirstById($acl->id);
            $userGroup = StaffUserGroup::findFirstById($user_group_id);

            if (!$userGroup || !$aclObject) {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }

            $aclGroup = StaffUserGroupAcl::getItem($user_group_id, $aclObject->getId());

            if ($aclGroup instanceof StaffUserGroupAcl) {
                $resultDelete = $aclGroup->__quickRemove();
                if ($resultDelete['success']) {
                    $return = $resultDelete;
                    $return['message'] = 'REMOVE_ROLE_SUCCESS_TEXT';
                } else {
                    $return = $resultDelete;
                    $return['message'] = 'REMOVE_ACL_ERROR_TEXT';
                }
            }
        }

        end_of_function :
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function addAclItemAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $user_group_id = Helpers::__getRequestValue('user_group_id');
        $acl = Helpers::__getRequestValue('acl');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        // Find group
        if ($user_group_id > 0 && $acl && isset($acl->id)) {
            $aclObject = Acl::findFirst($acl->id);

            $userGroup = StaffUserGroup::findFirstById($user_group_id);

            if (!$userGroup || !$aclObject) {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }

            $aclGroup = StaffUserGroupAcl::getItem($userGroup->getId(), $aclObject->getId());
            if (!$aclGroup) {
                $aclGroup = new StaffUserGroupAcl();
            }
            $aclGroup->setAclId($aclObject->getId()); //set acl id
            $aclGroup->setUserGroupId($userGroup->getId()); //set user group
            $resultSave = $aclGroup->__quickSave();
            if ($resultSave['success']) {
                $return = $resultSave;
                $return['message'] = 'SAVE_ROLE_SUCCESS_TEXT';
            } else {
                $return = $resultSave;
                $return['message'] = 'SAVE_ROLE_FAIL_TEXT';
            }
        }

        end_of_function :
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param int $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAclDetailAction($id = 0)
    {
        $this->view->disable();
        $data = Acl::findFirst((int)$id);
        $data = $data instanceof Acl ? $data->toArray() : [];
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function confirmDeleteAction()
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkConfirmActionDelete();
        $result = ['success' => true, 'message' => 'ACTION_ACCEPTED_TEXT'];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getLevel1ItemsAction()
    {
        $this->view->disable();
        $items = Acl::__getLevel1Items();
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getChildrenItemsAction($id)
    {
        $this->view->disable();
        $items = Acl::__getChildrenItems($id);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getItemAction($id)
    {
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $aclItem = Acl::findFirst($id);
        $data = $aclItem instanceof Acl ? $aclItem->toArray() : [];
        $data['parent'] = $aclItem->getParent();
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);
        return $this->response->send();

    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function applyAction()
    {
        $aclId = Helpers::__getRequestValue('aclId');
        $userGroupId = Helpers::__getRequestValue('userGroupId');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__isValidId($aclId) && Helpers::__isValidId($userGroupId)) {

            $acl = Acl::findFirstById($aclId);
            $userGroup = StaffUserGroup::findFirstById($userGroupId);

            if ($acl && $userGroup) {

                $userGroupAcl = StaffUserGroupAcl::findFirst([
                    'conditions' => 'acl_id = :acl_id: AND user_group_id = :user_group_id:',
                    'bind' => [
                        'acl_id' => $acl->getId(),
                        'user_group_id' => $userGroup->getId()
                    ]
                ]);

                if ($userGroupAcl) {
                    $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT', 'data' => $userGroupAcl];
                    goto end_of_function;
                }

                $userGroupAcl = new StaffUserGroupAcl();
                $userGroupAcl->setAclId($acl->getId());
                $userGroupAcl->setUserGroupId($userGroup->getId());
                $resultSave = $userGroupAcl->__quickCreate();

                if ($resultSave['success'] == false) {
                    $return = ['success' => false, 'message' => 'DATA_SAVE_FAIL_TEXT', 'error' => $resultSave];
                    goto end_of_function;
                }
                $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT', 'data' => $userGroupAcl];
            }
        }


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }


    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function relieveAction()
    {
        $aclId = Helpers::__getRequestValue('aclId');
        $userGroupId = Helpers::__getRequestValue('userGroupId');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__isValidId($aclId) && Helpers::__isValidId($userGroupId)) {
            $acl = Acl::findFirstById($aclId);
            $userGroup = StaffUserGroup::findFirstById($userGroupId);

            if ($acl && $userGroup) {
                $descendants = $acl->getDescendants();
                if (sizeof($descendants) == 0) {
                    $return = StaffUserGroupAcl::__deleteUserGroupAcl($acl->getId(), $userGroup->getId());
                    if ($return['success'] == true) {
                        $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT', 'data' => $return];
                        goto end_of_function;
                    }
                } else {
                    $this->db->begin();
                    $return = StaffUserGroupAcl::__deleteUserGroupAcl($acl->getId(), $userGroup->getId());
                    if ($return['success'] == false) {
                        $this->db->rollback();
                        goto end_of_function;
                    }

                    foreach ($descendants as $descendant) {
                        $return = StaffUserGroupAcl::__deleteUserGroupAcl($descendant['id'], $userGroup->getId());
                        if ($return['success'] == false) {
                            $this->db->rollback();
                            goto end_of_function;
                        }
                    }

                    $this->db->commit();
                    $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
                }
            }
        }


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDescendantsAction($id)
    {
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $resultSave = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($id != null && Helpers::__isValidId($id)) {
            $acl = Acl::findFirstById($id);
            if ($acl && $acl instanceof Acl) {
                $decendants = $acl->getDescendants();
                $return = ['success' => true, 'message' => 'Acl setting relieved', 'data' => $decendants];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Set position Action
     */
    public function setPositionAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $positions = Helpers::__getRequestValue('positions');

        $result = ['success' => false];
        if (count($positions) > 0) {
            $this->db->begin();
            foreach ($positions as $position) {
                $position = (array)$position;
                $aclItem = Acl::findFirstById($position['id']);
                if ($aclItem) {
                    $aclItem->setPosition($position['position']);
                    $resultUpdate = $aclItem->__quickUpdate();
                    if ($resultUpdate['success'] == false) {
                        $result = ['success' => false, 'error' => $resultUpdate];
                        goto end_of_function;
                    }
                }
            }
            $result = ['success' => true];
            $this->db->commit();
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $userGroupId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getItemsAppliedByUserGroupIdAction($userGroupId)
    {
        $this->view->disable();
        $items = Acl::__getAppliedItemsByUserGroup($userGroupId);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items,
            '$userGroupId' => $userGroupId,
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getItemsAction()
    {
        $this->view->disable();

        $userGroupId = Helpers::__getRequestValue('user_group_id');
        $items = [];

        $items = Acl::find();

        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getParentAclsAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $data = Helpers::__getRequestValuesArray();
        if($data['level'] >= 2){
            $data['level'] = 2;
        }
        if(isset($data['parentAclId']) && is_numeric($data['parentAclId'])){

            $conditions =  '(acl_id = :parentAclId: or acl_id is null) and lvl <= :lvl: and id != :currentId: and status != :status_archived: and is_admin = 0';

            $items = Acl::find([
                'conditions' => $conditions,
                'bind' => [
                    'lvl' => $data['level'],
                    'parentAclId' => $data['parentAclId'],
                    'currentId' => $data['currentId'],
                    'status_archived' => Acl::STATUS_INACTIVATED,
                ],
                'order' => 'pos ASC',
            ]);

        }else{
            $conditions =  'acl_id IS NULL AND is_admin <> :yes:  and lvl <= :lvl: and id != :currentId: and status != :status_archived:';
            $items = Acl::find([
                'conditions' => $conditions,
                'bind' => [
                    'yes' => ModelHelper::YES,
                    'lvl' => $data['level'],
                    'currentId' => $data['currentId'],
                    'status_archived' => Acl::STATUS_INACTIVATED
                ],
                'order' => 'pos ASC',
            ]);
        }


        end_of_function:
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }
}
