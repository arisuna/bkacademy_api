<?php

namespace SMXD\app\controllers\api;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\ProductField;
use SMXD\App\Models\ProductFieldGroup;
use SMXD\App\Models\ProductFieldInGroup;
use SMXD\App\Models\Company;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

class ProductFieldController extends BaseController
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
        $groups = Helpers::__getRequestValue('groups');
        if (is_array($groups) && count($groups) > 0) {
            foreach ($groups as $group) {
                $params['groups'][] = $group;
            }
        }
        $types = Helpers::__getRequestValue('types');
        if (is_array($types) && count($types) > 0) {
            foreach ($types as $type) {
                $params['types'][] = $type;
            }
        }
        $result = ProductField::__findWithFilters($params);
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

        $data = ProductField::findFirstByUuid($uuid);
        if($data instanceof ProductField && $data->getIsDeleted() != ProductField::IS_DELETE_YES){
            $data_array = $data instanceof ProductField ? $data->toArray() : [];
            $data_array['group_ids'] = [];
            $field_in_groups = ProductFieldInGroup::find([
                'conditions' => 'product_field_id = :id:',
                'bind' => [
                    'id' => $data->getId()
                ]
            ]);
            if(count($field_in_groups) > 0){
                foreach($field_in_groups as $field_in_group){
                    $group = $field_in_group->getProductFieldGroup();
                    if($group && $group->getIsDeleted() != ProductFieldGroup::IS_DELETE_YES){
                        $data_array['group_ids'][] = $group->getId();
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
    public function importAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();
        
        $field_group_name = Helpers::__getRequestValue('field_group');
        if($field_group_name == ''){
            $result = [
                'success' => false,
                'message' => 'FIELD_GROUP_NAME_INVALID_TEXT'
            ];
            // $this->db->rollback();
            goto end;
        }
        $field_group = ProductFieldGroup::findFirst([
            'conditions' => 'is_deleted <> 1 and name = :name:',
            'bind' => [
                'name' => $field_group_name
            ]
        ]);
        if (!$field_group instanceof ProductFieldGroup) {
            $field_group = new ProductFieldGroup();
            $field_group->setUuid(Helpers::__uuid());
            $field_group->setName($field_group_name);
            $field_group->setNameVn(Helpers::__getRequestValue('field_group_vn'));
            $result = $field_group->__quickCreate();
            if (!$result['success']) {
                // $this->db->rollback();
                goto end;
            }
        }
        $this->db->begin();
        $field_name = Helpers::__getRequestValue('field_name');
        if($field_name == ''){
            $result = [
                'success' => false,
                'message' => 'FIELD_NAME_INVALID_TEXT'
            ];
            $this->db->rollback();
            goto end;
        }
        $model = ProductField::findFirst([
            'conditions' => 'is_deleted <> 1 and name = :name:',
            'bind' => [
                'name' => $field_name
            ]
        ]);
        if (!$model instanceof ProductField) {
            $model = new ProductField();
            $model->setUuid(Helpers::__uuid());
            $model->setName($field_name);
            $isCreate = true;
        } else {
            $isCreate = false;
        }
        $model->setNameVn(Helpers::__getRequestValue('field_name_vn'));
        $model->setLabel(Helpers::__getRequestValue('label'));
        $model->setIsMandatory(Helpers::__getRequestValue('mandatory') == 0 ? 0 : 1);
        $model->setType(ProductField::TYPE_TEXT);

        
        if($isCreate){
            $result = $model->__quickCreate();
        } else {
            $result = $model->__quickSave();
        }
        if ($result['success']) {
            $product_field_in_group = ProductFieldInGroup::findFirst([
                'conditions' => 'product_field_id = :field_id: and product_field_group_id = :group_id:',
                'bind' => [
                    'field_id' => $model->getId(),
                    'group_id' => $field_group->getId()
                ]
            ]);
            if(!$product_field_in_group){
                $product_field_in_group =  new ProductFieldInGroup();
                $product_field_in_group->setProductFieldId($model->getId());
                $product_field_in_group->setProductFieldGroupId($field_group->getId());
                $create_field_in_group = $product_field_in_group->__quickCreate();
                if(!$create_field_in_group['success']){
                    $result = $create_field_in_group;
                    $this->db->rollback();
                    goto end;
                }
            }

            $this->db->commit();
        } else {
            $this->db->rollback();
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
        $model = new ProductField();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = ProductField::findFirstByUuid($uuid);
            if (!$model instanceof ProductField) {
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
        $model->setIsMandatory(Helpers::__getRequestValue('is_mandatory'));
        $model->setLabel(Helpers::__getRequestValue('label'));
        $model->setStatus(Helpers::__getRequestValue('status') == 0 ? 0 : 1);
        $model->setType(Helpers::__getRequestValue('type'));
        if($model->getType() == ProductField::TYPE_ATTRIBUTE){
            $attribute_id = Helpers::__getRequestValue('attribute_id');
            $attribute = Attributes::findFirstById($attribute_id);
            if (!$attribute instanceof Attributes) {
                $result = [
                    'success' => false,
                    'message' => 'ATTRIBUTE_NOT_FOUND_TEXT'
                ];
                goto end;
            }
            $model->setAttributeId($attribute_id);
        }
        $this->db->begin();
        $group_ids = Helpers::__getRequestValueAsArray('group_ids');
        if(!$isNew){
            $old_groups = ProductFieldInGroup::find([
                'conditions' => 'product_field_id = :field_id:',
                'bind' => [
                    'field_id' => $model->getId()
                ]
            ]);
            if(count($old_groups) > 0){
                foreach($old_groups as $old_group){
                    $is_removed = false;
                    $old_product_field_group = $old_group->getProductFieldGroup();
                    if (count($group_ids) && is_array($group_ids)) {
                        if(!in_array($old_group->getProductFieldGroupId(), $group_ids)){
                            $is_removed = true;
                            $result = $old_group->__quickRemove();
                            if (!$result['success']) {
                                $this->db->rollback();
                                goto end;
                            }
                        }
                    } else {
                        $is_removed = true;
                        $result = $old_group->__quickRemove();
                        if (!$result['success']) {
                            $this->db->rollback();
                            goto end;
                        }
                    }
                    if($is_removed){
                        $product_field_ids = json_decode($old_product_field_group->getProductFieldIds(), true);
                        $new_product_field_ids = [];
                        if(is_array($product_field_ids)){
                            foreach($product_field_ids as $product_field_id){
                                if($product_field_id !=  $model->getId()){
                                    $new_product_field_ids[] = $product_field_id;
                                }
                            }
                        }
                        $old_product_field_group->setProductFieldIds(json_encode($new_product_field_ids));
                        $result = $old_product_field_group->__quickUpdate();
                        if (!$result['success']) {
                            $this->db->rollback();
                            goto end;
                        }
                    }
                }
            }
        }
        if (count($group_ids) && is_array($group_ids)) {
            foreach($group_ids as $group_id){
                $field_group = ProductFieldGroup::findFirst([
                    'conditions' => 'is_deleted <> 1 and id = :id:',
                    'bind' => [
                        'id' => $group_id
                    ]
                ]);
                if($field_group instanceof  ProductFieldGroup){
                    $product_field_in_group = ProductFieldInGroup::findFirst([
                        'conditions' => 'product_field_id = :field_id: and product_field_group_id = :group_id:',
                        'bind' => [
                            'field_id' => $model->getId(),
                            'group_id' => $field_group->getId()
                        ]
                    ]);
                    if(!$product_field_in_group){
                        $product_field_in_group =  new ProductFieldInGroup();
                        $product_field_in_group->setProductFieldId($model->getId());
                        $product_field_in_group->setProductFieldGroupId($field_group->getId());
                        $create_field_in_group = $product_field_in_group->__quickCreate();
                        if(!$create_field_in_group['success']){
                            $result = $create_field_in_group;
                            $this->db->rollback();
                            goto end;
                        }
                        $product_field_ids = json_decode($field_group->getProductFieldIds(), true);
                        if(is_array($product_field_ids)){
                            $product_field_ids[] = $model->getId();
                        } else {
                            $product_field_ids = [];
                            $product_field_ids[] = $model->getId();
                        }
                        $field_group->setProductFieldIds(json_encode($product_field_ids));
                        $result = $field_group->__quickUpdate();
                        if (!$result['success']) {
                            $this->db->rollback();
                            goto end;
                        }
                    }
                }
            }
        }

        
        if($isNew){
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
            $model = ProductField::findFirstByUuid($uuid);
            if ($model instanceof ProductField) {
                $this->db->begin();
                $groups = ProductFieldInGroup::find([
                    'conditions' => 'product_field_id = :field_id:',
                    'bind' => [
                        'field_id' => $model->getId()
                    ]
                ]);
                if(count($groups) > 0){
                    foreach($groups as $group){
                        $product_field_group = $group->getProductFieldGroup();
                        if ($product_field_group) {
                            $result = $group->__quickRemove();
                            if (!$result['success']) {
                                $this->db->rollback();
                                goto end;
                            }
                            $product_field_ids = json_decode($product_field_group->getProductFieldIds(), true);
                            $new_product_field_ids = [];
                            if(is_array($product_field_ids)){
                                foreach($product_field_ids as $product_field_id){
                                    if($product_field_id !=  $model->getId()){
                                        $new_product_field_ids[] = $product_field_id;
                                    }
                                }
                            }
                            $product_field_group->setProductFieldIds(json_encode($new_product_field_ids));
                            $result = $product_field_group->__quickUpdate();
                            if (!$result['success']) {
                                $this->db->rollback();
                                goto end;
                            }
                        }
                            
                    }
                }
                $result = $model->__quickRemove();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result = [
                        'success' => false,
                        'message' => 'DATA_DELETE_FAIL_TEXT'
                    ];
                    goto end;
                }
                $this->db->commit();
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


}