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
        $result = ProductField::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $data = ProductField::findFirstByUuid($uuid);
        $data = $data instanceof ProductField ? $data->toArray() : [];

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
    public function importAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();
        $this->db->begin();
        $field_group_name = Helpers::__getRequestValue('field_group');
        if($field_group_name == ''){
            $result = [
                'success' => false,
                'message' => 'FIELD_GROUP_NAME_INVALID_TEXT'
            ];
            $this->db->rollback();
            goto end;
        }
        $field_group = ProductFieldGroup::findFirstByName($field_group_name);
        if (!$field_group instanceof ProductFieldGroup) {
            $field_group = new ProductFieldGroup();
            $field_group->setUuid(Helpers::__uuid());
            $field_group->setName($field_group_name);
            $field_group->setNameVn(Helpers::__getRequestValue('field_group_vn'));
            $result = $field_group->__quickCreate();
            if (!$result['success']) {
                $this->db->rollback();
                goto end;
            }
        }
        $field_name = Helpers::__getRequestValue('field_name');
        if($field_name == ''){
            $result = [
                'success' => false,
                'message' => 'FIELD_NAME_INVALID_TEXT'
            ];
            $this->db->rollback();
            goto end;
        }
        $model = ProductField::findFirstByName($field_name);
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
                $product_field_in_group->getProductFieldId($model->getId());
                $product_field_in_group->getProductFieldGroupId($field_group->getId());
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


}