<?php

namespace SMXD\App\Controllers\API;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\Category;
use SMXD\App\Models\Company;
use SMXD\App\Models\Classroom;
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
        // $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['level'] = Helpers::__getRequestValue('level');
        $params['ids'] = Helpers::__getRequestValue('ids');
        $params['sub_category_only'] = Helpers::__getRequestValue('sub_category_only');
        $params['not_root_category'] = Helpers::__getRequestValue('not_root_category');
        
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $class_id = Helpers::__getRequestValue('class_id');
        if($class_id > 0){
            $class = Classroom::findFirstById($class_id);
            if (!$class || $class->getIsDeleted() == Helpers::YES) {
                $result = [
                    'success' => false,
                    'detail' => Helpers::__getRequestValue('class_id'),
                    'message' => 'CLASSROOM_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }
            $params['grade'] =  $class->getGrade();
        }
        $result = Category::__findWithFilters($params, $ordersConfig);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        // $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        if(Helpers::__isValidUuid($uuid)){
            $category = Category::findFirstByUuid($uuid);
        } else {
            $category = Category::findFirstById($uuid);
        }
        $data = $category instanceof Category ? $category->toArray() : [];

        $data['parent'] = $category->getParent();

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
        $model->setReference(Helpers::__getRequestValue('reference'));
        $model->setGrade(Helpers::__getRequestValue('grade'));
        $model->setParentCategoryId(Helpers::__getRequestValue('parent_category_id'));

        $this->db->begin();
        if($isNew){
            if ($model->getParent()) {
                $model->setNextPosition();
                $model->setLvl($model->getParent()->getLvl() + 1);
                $model->setGrade($model->getParent()->getGrade());
            } else {
                $model->setNextPosition();
                $model->setLvl(1);
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
        $model->setReference(Helpers::__getRequestValue('reference'));

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
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $class_id = Helpers::__getRequestValue('class_id');
        if($class_id > 0){
            $class = Classroom::findFirstById($class_id);
            if (!$class || $class->getIsDeleted() == Helpers::YES) {
                $result = [
                    'success' => false,
                    'detail' => Helpers::__getRequestValue('class_id'),
                    'message' => 'CLASSROOM_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }
            $data = Category::find([
                "conditions" => "grade = :grade:",
                "bind" => [
                    "grade" => $class->getGrade()
                ]
            ]);
            goto end;
        }
        $data = Category::find();
        end:
        $result = [
            'success' => true,
            'data' => $data
        ];
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAllLevel2ItemsAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $class_id = Helpers::__getRequestValue('class_id');
        if($class_id > 0){
            $class = Classroom::findFirstById($class_id);
            if (!$class || $class->getIsDeleted() == Helpers::YES) {
                $result = [
                    'success' => false,
                    'detail' => Helpers::__getRequestValue('class_id'),
                    'message' => 'CLASSROOM_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }
            $items = Category::find([
                "conditions" => "grade = :grade: and parent_category_id is not null and lvl = 2",
                'order' => 'parent_category_id ASC',
                "bind" => [
                    "grade" => $class->getGrade()
                ]
            ]);
            goto end;
        }

        $items = Category::find([
            'conditions' => 'parent_category_id is not null and lvl = 2',
            'order' => 'parent_category_id ASC'
        ]);
        end:
        $data_array = [];
        foreach($items as $item){
            $item_array = $item->toArray();
            if($item->getParent()){
                $item_array['parent_name'] = $item->getParent()->getName();
                $data_array[] = $item_array;
            }
        }
        $result = [
            'success' => true,
            'data' => $data_array
        ];
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAllLevel3ItemsAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $class_id = Helpers::__getRequestValue('class_id');
        if($class_id > 0){
            $class = Classroom::findFirstById($class_id);
            if (!$class || $class->getIsDeleted() == Helpers::YES) {
                $result = [
                    'success' => false,
                    'detail' => Helpers::__getRequestValue('class_id'),
                    'message' => 'CLASSROOM_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }
            $items = Category::find([
                "conditions" => "grade = :grade: and parent_category_id is not null and lvl = 2",
                'order' => 'parent_category_id ASC',
                "bind" => [
                    "grade" => $class->getGrade()
                ]
            ]);
            goto end;
        }

        $items = Category::find([
            'conditions' => 'parent_category_id is not null and lvl = 3',
            'order' => 'parent_category_id ASC'
        ]); 
        end:
        $data_array = [];
        foreach($items as $item){
            $item_array = $item->toArray();
            if($item->getParent()){
                $item_array['parent_name'] = $item->getParent()->getName();
                $data_array[] = $item_array;
            }
        }
        $result = [
            'success' => true,
            'data' => $data_array
        ];
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getLevel1ItemsAction()
    {
        $this->view->disable();
        $params = [];
        $grades = Helpers::__getRequestValue('grades');
        if (is_array($grades) && count($grades) > 0) {
            foreach ($grades as $grade) {
                $params['grades'][] = $grade->id;
            }
        }
        $params['lvl'] = 1;
        $data = Category::__findAllWithFilter($params);
        $result = [
            'success' => true,
            'data'=> $data
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getChildrenItemsAction($id)
    {
        $this->view->disable();
        // $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $items = Category::find([
            'conditions' => 'parent_category_id = :id:',
            'bind' => [
                'id' => $id
            ],
            'order' => 'position ASC'
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
                'order' => 'position ASC',
            ]);

        }else{
            $conditions =  'parent_category_id IS NULL AND level <= :level: and id != :currentId:';

            $items = Category::find([
                'conditions' => $conditions,
                'bind' => [
                    'level' => $data['level'],
                    'currentId' => $data['currentId'],
                ],
                'order' => 'position ASC',
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