<?php

namespace SMXD\app\controllers\api;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\ProductField;
use SMXD\App\Models\ProductFieldGroup;
use SMXD\App\Models\ProductFieldInGroup;
use SMXD\App\Models\ProductFieldGroupInCategory;
use SMXD\App\Models\Category;
use SMXD\App\Models\Company;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

class ProductFieldGroupController extends BaseController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $data = ProductFieldGroup::find([
            'conditions' => 'is_deleted <> 1'
        ]);
        $result = [
            'success' => true,
            'data' => $data
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

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
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');

        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $params['categories'][] = $category;
            }
        }

        $result = ProductFieldGroup::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' =>'DATA_NOT_FOUND_TEXT'
        ];

        $data = ProductFieldGroup::findFirstByUuid($uuid);
        if($data instanceof ProductFieldGroup && $data->getIsDeleted() != ProductFieldGroup::IS_DELETE_YES){
            $data_array = $data instanceof ProductFieldGroup ? $data->toArray() : [];
            $data_array['category_ids'] = [];
            $group_in_categories = ProductFieldGroupInCategory::find([
                'conditions' => 'product_field_group_id = :id:',
                'bind' => [
                    'id' => $data->getId()
                ]
            ]);
            if(count($group_in_categories) > 0){
                foreach($group_in_categories as $group_in_category){
                    $category = $group_in_category->getCategory();
                    if($category){
                        $data_array['category_ids'][] = $category->getId();
                    }
                }
            }
            $result = [
                'success' => true,
                'data' => $data_array
            ];
        }

        $this->response->setJsonContent($result);

        end:
        $this->response->send();
    }

    /**
     *
     */
    public function updateAfterImportAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();
        $groups = ProductFieldGroup::find([
            'conditions' => 'is_deleted <> 1'
        ]);
        foreach($groups as $group){
            $product_field_ids = [];
            $product_field_in_groups = ProductFieldInGroup::find([
                'conditions' => 'product_field_group_id = :product_field_group_id:',
                'bind' => [
                    'product_field_group_id' => $group->getId()
                ]
                ]);
            if(count($product_field_in_groups) > 0){
                foreach($product_field_in_groups as $product_field_in_group){
                    $product_field = $product_field_in_group->getProductField();
                    if($product_field && $product_field->getIsDeleted() != ProductField::IS_DELETE_YES){
                        $product_field_ids[] = $product_field->getId();
                    }
                }
            }
            $group->setProductFieldIds(json_encode($product_field_ids));
            $result = $group->__quickUpdate();
            if (!$result['success']) {
                goto end;
            }
        }

        end:
        $this->response->setJsonContent($result);
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
        $model = new ProductFieldGroup();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = ProductFieldGroup::findFirstByUuid($uuid);
            if (!$model instanceof ProductFieldGroup) {
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
        $model->setNameVn(Helpers::__getRequestValue('name_vn'));
        $model->setLabel(Helpers::__getRequestValue('label'));

        $this->db->begin();
        $category_ids = Helpers::__getRequestValueAsArray('category_ids');
        if(!$isNew){
            $old_group_in_categories = ProductFieldGroupInCategory::find([
                'conditions' => 'product_field_group_id = :field_group_id:',
                'bind' => [
                    'field_group_id' => $model->getId()
                ]
            ]);
            if(count($old_group_in_categories) > 0){
                foreach($old_group_in_categories as $old_group_in_category){
                    $is_removed = false;
                    $old_category = $old_group_in_category->getCategory();
                    if (count($category_ids) && is_array($category_ids)) {
                        if(!in_array($old_category->getId(), $category_ids)){
                            $is_removed = true;
                            $result = $old_group_in_category->__quickRemove();
                            if (!$result['success']) {
                                $this->db->rollback();
                                goto end;
                            }
                        }
                    } else {
                        $is_removed = true;
                        $result = $old_group_in_category->__quickRemove();
                        if (!$result['success']) {
                            $this->db->rollback();
                            goto end;
                        }
                    }
                }
            }
        }
        $model->setCategoryIds(json_encode($category_ids));
        if($isNew){
            $result = $model->__quickCreate();
        }else{
            $result = $model->__quickSave();
        }

        if (!$result['success']) {
            $this->db->rollback();
        }

        if (count($category_ids) && is_array($category_ids)) {
            foreach($category_ids as $category_id){
                $category = Category::findFirstById($category_id);
                if($category instanceof  Category){
                    $group_in_category = ProductFieldGroupInCategory::findFirst([
                        'conditions' => 'category_id = :category_id: and product_field_group_id = :group_id:',
                        'bind' => [
                            'group_id' => $model->getId(),
                            'category_id' => $category_id
                        ]
                    ]);
                    if(!$group_in_category){
                        $group_in_category =  new ProductFieldGroupInCategory();
                        $group_in_category->setCategoryId($category_id);
                        $group_in_category->setProductFieldGroupId($model->getId());
                        $create_group_in_category = $group_in_category->__quickCreate();
                        if(!$create_group_in_category['success']){
                            $result = $create_group_in_category;
                            $this->db->rollback();
                            goto end;
                        }
                    }
                }
            }
        }


        $this->db->commit();

        end:
        return $result;
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
            $model = ProductFieldGroup::findFirstByUuid($uuid);
            if ($model instanceof ProductFieldGroup) {
                $result = $model->__quickRemove();
                // if ($result['success'] == false) {
                //     $result = [
                //         'success' => false,
                //         'message' => 'DATA_DELETE_FAIL_TEXT'
                //     ];
                // }
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


}