<?php

namespace SMXD\api\Controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Product;
use SMXD\Application\Lib\Helpers;

class ProductController extends ModuleApiController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['brand_ids'] = Helpers::__getRequestValue('make_ids');
        $params['category_ids'] = Helpers::__getRequestValue('category_ids');
        $params['secondary_category_id'] = Helpers::__getRequestValue('category_id');
        $params['brand_id'] = Helpers::__getRequestValue('make_id');
        $params['main_category_id'] = Helpers::__getRequestValue('parent_category_id');
        $params['location_ids'] = Helpers::__getRequestValue('location_ids');
        $params['type'] = Helpers::__getRequestValue('type');
        $params['price_min'] = Helpers::__getRequestValue('price_min');
        $params['price_max'] = Helpers::__getRequestValue('price_max');

        $model_ids = Helpers::__getRequestValue('model_ids');
        if (is_array($model_ids) && count($model_ids) > 0) {
            foreach ($model_ids as $model) {
                $params['model_ids'][] = $model->id;
            }
        }

        $result = Product::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $data = Product::findFirstByUuid($uuid);

        if ($data instanceof Product && $data->getIsDeleted() != Product::IS_DELETE_YES) {
            $data_array = $data->parsedDataToArray();
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
    public function createAction()
    {
        $this->view->disable();
        $this->checkAclCreate(AclHelper::CONTROLLER_PRODUCT);
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
        $this->checkAclEdit(AclHelper::CONTROLLER_PRODUCT);
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
        $model = new Product();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = Product::findFirstByUuid($uuid);
            if (!$model instanceof Product) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }else{
            $isNew = true;
            if(!ModuleModel::$user->isAdmin()){
                $model->setCreatorEndUserId(ModuleModel::$user->getId());
                $model->setCreatorCompanyId(ModuleModel::$company->getId());
            }
            $model->setUuid(Helpers::__uuid());
        }
        $model->setStatus(Product::STATUS_UNVERIFIED);
        $model->setData(Helpers::__getRequestValuesArray());
        if(!$model->getBrand() instanceof Brand){
            $result = [
                'success' => false,
                'message' => 'BRAND_NOT_FOUND_TEXT'
            ];
        }
        if(!$model->getModel() instanceof Model){
            $result = [
                'success' => false,
                'message' => 'MODEL_NOT_FOUND_TEXT'
            ];
        }
        $this->db->begin();
        if($isNew){
            $result = $model->__quickCreate();
        }else{
            $result = $model->__quickSave();
        }
        if (!$result['success']) {
            $this->db->rollback();
            goto end;
        }
        //sale info
        $product_sale_info = ProductSaleInfo::findFirstByUuid($model->getUuid());
        if(!$product_sale_info){
            $product_sale_info = new ProductSaleInfo();
            $product_sale_info->setUuid($model->getUuid());
            $product_sale_info->setCurrency('VND');
        }
        $product_sale_info->setData(Helpers::__getRequestValueAsArray('product_sale_info'));
        $result = $product_sale_info->__quickSave();
        if (!$result['success']) {
            $this->db->rollback();
            goto end;
        }
        //rent info
        $product_rent_info = ProductRentInfo::findFirstByUuid($model->getUuid());
        if(!$product_rent_info){
            $product_rent_info = new ProductRentInfo();
            $product_rent_info->setUuid($model->getUuid());
            $product_rent_info->setCurrency('VND');
        }
        $product_rent_info->setData(Helpers::__getRequestValueAsArray('product_rent_info'));
        $result = $product_rent_info->__quickSave();
        if (!$result['success']) {
            $this->db->rollback();
            goto end;
        }
        $groups = Helpers::__getRequestValueAsArray('product_field_groups');
        if (count($groups) && is_array($groups)) {
            foreach($groups as $group){
                $product_field_group = ProductFieldGroup::findFirstById($group['id']);
                if($product_field_group){
                    $fields = $group['fields'];

                    if (count($fields) && is_array($fields)) {
                        foreach($fields as $field){
                            $product_field = ProductField::findFirst([
                                'conditions' => 'is_deleted <> 1 and id = :id:',
                                'bind' => [
                                    'id' => $field['id']
                                ]
                            ]);
                            if($product_field instanceof  ProductField){
                                $product_field_value = ProductFieldValue::findFirst([
                                    'conditions' => 'product_field_id = :field_id: and product_id = :product_id: and product_field_group_id = :product_field_group_id:',
                                    'bind' => [
                                        'product_id' => $model->getId(),
                                        'field_id' => $product_field->getId(),
                                        'product_field_group_id' => $group['id']
                                    ]
                                ]);
                                if(!$product_field_value){
                                    $product_field_value =  new ProductFieldValue();
                                    $product_field_value->setProductId($model->getId());
                                    $product_field_value->setProductFieldId($product_field->getId());
                                    $product_field_value->setProductFieldGroupId($group['id']);
                                }
                                $product_field_value->setValue($field['value'] ? $field['value'] : null);
                                $product_field_value->setIsCustom($field['is_custom']);
                                $product_field_value->setProductFieldName($product_field->getName());
                                $save_product_field_value = $product_field_value->__quickSave();
                                if(!$save_product_field_value['success']){
                                    $result = $save_product_field_value;
                                    $this->db->rollback();
                                    goto end;
                                }
                            }
                        }
                    }

                }
            }
        }

        
        
        if ($result['success']) {
            $this->db->commit();
            $data_array = $model->parsedDataToArray();
            $result['data'] = $data_array;
        } else {
            $this->db->rollback();
        }

        end:
        return $result;
    }
}