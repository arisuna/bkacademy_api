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
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = ProductFieldGroup::__findWithFilters($params);
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