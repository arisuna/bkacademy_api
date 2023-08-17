<?php

namespace SMXD\app\controllers\api;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\Category;
use SMXD\App\Models\Company;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;

class CategoryController extends BaseController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = Category::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $data = Category::findFirstByUuid($uuid);
        $data = $data instanceof Category ? $data->toArray() : [];

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        $this->response->send();
    }

    /**
     *
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();
        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    /**
     *
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        //Change Archived status of attribute value
        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    /**
     * @return array
     */
    private function __save()
    {
        $model = new Category();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = Category::findFirstByUuid($uuid);
            if (!$model instanceof Category) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }else{
            $isNew = true;
            $model->setUuid(Helpers::__uuid());
        }
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setLabel(Helpers::__getRequestValue('label'));
        $model->setParentCategoryId(Helpers::__getRequestValue('parent_category_id'));
        $model->setDescription(Helpers::__getRequestValue('description'));

        $this->db->begin();
        if($isNew){
            if ($model->getParent()) {
                $model->setNextPosition();
                $model->setLevel($model->getParent()->getLevel() + 1);
            } else {
                $model->setNextPosition();
                $model->setLevel(1);
            }

            $result = $model->__quickCreate();
        }else{
            $result = $model->__quickSave();
        }
        if ($result['success']) {
            $this->db->commit();
        } else {
            $this->db->rollback();
        }

        end:
        return $result;
    }

    /**
     * Old function
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $uuid = Helpers::__getRequestValue('uuid');
        $model = new Category();
        if (Helpers::__isValidUuid($uuid)) {
            $model = Category::findFirstByUuid($uuid);
            if (!$model instanceof Category) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }else{
            $model->setUuid(Helpers::__uuid());
        }


        $model->setName(Helpers::__getRequestValue('name'));
        $model->setStatus(Helpers::__getRequestValue('status') == 0 ? 0 : 1);
        $model->setDescription(Helpers::__getRequestValue('description'));

        $this->db->begin();
        if ($model->save()) {

            $this->db->commit();
            $this->response->setJsonContent([
                'success' => true,
                'data' => $model,
                'message' => 'DATA_SAVE_SUCCESS_TEXT',
            ]);
        } else {
            $this->db->rollback();
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[] = $message->getMessage();
            }
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $msg
            ]);
        }

        end:
        $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteAction($uuid)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();

        $result = [
            'success' => false,
            'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
        ];


        if (Helpers::__isValidUuid($uuid)) {
            $model = Category::findFirstByUuid($uuid);
            if ($model instanceof Category) {
                $result = $model->__quickRemove();
                if ($result['success'] == false) {
                    $result = [
                        'success' => false,
                        'message' => 'DATA_DELETE_FAIL_TEXT'
                    ];
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($result);
        end:
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getLevel1ItemsAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $items = Category::find([
            'conditions' => 'parent_category_id is null',
            'order' => 'pos ASC'
        ]);
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
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $items = Category::find([
            'conditions' => 'parent_category_id = :id:',
            'bind' => [
                'id' => $id
            ],
            'order' => 'pos ASC'
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getParentCategoriesAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $data = Helpers::__getRequestValuesArray();
        if($data['level'] >= 1){
            $data['level'] = 1;
        }
        if(isset($data['parentCategoryId']) && is_numeric($data['parentCategoryId'])){

            $conditions =  '(parent_category_id = :parentCategoryId: or parent_category_id is null) and level <= :level: and id != :currentId:';

            $items = Category::find([
                'conditions' => $conditions,
                'bind' => [
                    'level' => $data['level'],
                    'parentCategoryId' => $data['parentCategoryId'],
                    'currentId' => $data['currentId'],
                ],
                'order' => 'pos ASC',
            ]);

        }else{
            $conditions =  'parent_category_id IS NULL AND level <= :level: and id != :currentId:';

            $items = Category::find([
                'conditions' => $conditions,
                'bind' => [
                    'level' => $data['level'],
                    'currentId' => $data['currentId'],
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

    /**
     * Set position Action
     */
    public function setPositionAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        $positions = Helpers::__getRequestValue('positions');

        $result = ['success' => false];
        if (count($positions) > 0) {
            $this->db->begin();
            foreach ($positions as $position) {
                $position = (array)$position;
                $categoryItem = Category::findFirstById($position['id']);
                if ($categoryItem) {
                    $categoryItem->setPosition($position['position']);
                    $resultUpdate = $categoryItem->__quickUpdate();
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
}