<?php

namespace SMXD\api\controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Controllers\api\BaseController;
use SMXD\Api\Models\Media;
use SMXD\Api\Models\MediaAttachment;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Models\Product;
use SMXD\Api\Models\ProductRentInfo;
use SMXD\Api\Models\ProductSaleInfo;
use SMXD\Application\Lib\Helpers;

class ProductV2Controller extends BaseController
{
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
        $this->checkAjaxPost();

        $uuid = Helpers::__uuid();
        $data = Helpers::__getRequestValuesArray();

        $result = ['success' => false, 'message' => 'DATA_CREATE_FAIL_TEXT'];

        $this->db->begin();

        $model = new Product();
        $model->setUuid($uuid);
        $model->setCreatorEndUserId(ModuleModel::$user->getId());
        $model->setCreatorCompanyId(ModuleModel::$company ? ModuleModel::$company->getId() : null);
        $model->setStatus(Product::STATUS_UNVERIFIED);
        $model->setData($data);

        $resultCreate = $model->__quickCreate();

        if(!$resultCreate['success']){
            $result = $resultCreate;
            $this->db->rollback();
            goto end;
        }

        $type = Helpers::__getRequestValue('type');
        $options = Helpers::__getRequestValue('options');

        if(!is_array($options)){
            $options = (array)$options;
        }
        switch ($type){
            case 1:
                $product_sale_info = new ProductSaleInfo();
                $product_sale_info->setUuid($model->getUuid());
                $product_sale_info->setCurrency('VND');
                $product_sale_info->setPrice(isset($options['price']) ? $options['price'] : 0);
                $product_sale_info->setQuantity(isset($options['quantity']) ? $options['quantity'] : 1);
                $resultCreateInfo = $product_sale_info->__quickCreate();

                if(!$resultCreateInfo['success']){
                    $result = $resultCreateInfo;
                    $this->db->rollback();
                    goto end;
                }
                break;
            case 2:
                $productRentInfo = new ProductRentInfo();
                $productRentInfo->setUuid($model->getUuid());
                $productRentInfo->setCurrency('VND');
                $productRentInfo->setPrice(isset($options['price']) ? $options['price'] : 0);
                $productRentInfo->setQuantity(isset($options['quantity']) ? $options['quantity'] : 1);
                $resultCreateInfo = $productRentInfo->__quickCreate();

                if(!$resultCreateInfo['success']){
                    $result = $resultCreateInfo;
                    $this->db->rollback();
                    goto end;
                }
                break;
            case 3:

                break;
        }

        //Product images
        $files = Helpers::__getRequestValue('files');
        if(is_string($files)){
            $files = json_decode($files);
        }
        if($files && is_array($files) && count($files) > 0){
            foreach ($files as $file){
                if(!is_array($file)){
                    $file = (array)$file;
                }
                $media = Media::findFirstByUuid($file['uuid']);

                if($media && $media->getUserUuid() == ModuleModel::$user->getUuid()){
                    $mediaAttachment = new MediaAttachment();
                    $mediaAttachment->setUuid(Helpers::__uuid());
                    $mediaAttachment->setObjectUuid($model->getUuid());
                    $mediaAttachment->setObjectName('product');
                    $mediaAttachment->setMediaId($media->getId());
                    $mediaAttachment->setMediaUuid($media->getUuid());
                    $mediaAttachment->setIsShared(Helpers::NO);
                    $mediaAttachment->setUserUuid(ModuleModel::$user->getUuid());
                    $mediaAttachment->setIsThumb(isset($file['is_thumb']) && $file['is_thumb'] == true ? 1 : 0);
                    //$model->setObjectId(0);
                    $mediaAttachment->setCreatedAt(date('Y-m-d H:i:s'));
                    $mediaAttachment->setUpdatedAt(date('Y-m-d H:i:s'));
                    $attachResult = $mediaAttachment->__quickSave();

                    if(!$attachResult['success']){
                        $result = $attachResult;
                        $this->db->rollback();
                        goto end;
                    }
                }
            }
        }

        $this->db->commit();
        $result['success'] = true;
        $result['message'] = 'DATA_CREATE_SUCCESS_TEXT';
        $result['data'] = $model->parsedDataToArray();

        end:
        $this->response->setJsonContent($result);
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

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        // $this->checkAclIndex(AclHelper::CONTROLLER_PRODUCT);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $brandes = Helpers::__getRequestValue('brandes');
        if (is_array($brandes) && count($brandes) > 0) {
            foreach ($brandes as $brand) {
                $params['brand_ids'][] = $brand->id;
            }
        }
        $model_ids = Helpers::__getRequestValue('model_ids');
        if (is_array($model_ids) && count($model_ids) > 0) {
            foreach ($model_ids as $model) {
                $params['model_ids'][] = $model->id;
            }
        }
        $categories = Helpers::__getRequestValue('categories');
        if (is_array($categories) && count($categories) > 0) {
            foreach ($categories as $category) {
                $params['category_ids'][] = $category->id;
            }
        }
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $params['company_ids'][] = $company->id;
            }
        }
        $years = Helpers::__getRequestValue('years');
        if (is_array($years) && count($years) > 0) {
            foreach ($years as $year) {
                $params['years'][] = $year->id;
            }
        }
        $options = Helpers::__getRequestValue('options');
        if (is_array($options) && count($options) > 0) {
            foreach ($options as $option) {
                if($option->id == 1){
                    $params['is_rent']  = 1;
                }else if($option->id == 2){
                    $params['is_sale']  = 1;
                }else if($option->id == 3){
                    $params['is_auction']  = 1;
                }
            }
        }
        $result = Product::__findWithFiltersV2($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function updateBasicInfoAction(){
        $this->view->disable();
        $this->checkAjaxPut();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $uuid = Helpers::__getRequestValue('uuid');
        $product = Product::findFirstByUuid($uuid);
        if(!$product){
            goto end;
        }
        $product->setName(Helpers::__getRequestValue('name'));
        $product->setBrandId(Helpers::__getRequestValue('brand_id'));
        $product->setUsage(Helpers::__getRequestValue('usage'));
        $product->setYear(Helpers::__getRequestValue('year'));
        $product->setVehicleId(Helpers::__getRequestValue('vehicle_id'));

        $result = $product->__quickSave();

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function updateOptionsAction(){
        $this->view->disable();
        $this->checkAjaxPut();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $uuid = Helpers::__getRequestValue('uuid');
        $options = Helpers::__getRequestValue('options');
        $product = Product::findFirstByUuid($uuid);
        if(!$product){
            goto end;
        }

        $type = Helpers::__getRequestValue('type');
        switch ((int)$type) {
            case 1:
                $product_sale_info = ProductSaleInfo::findFirstByUuid($product->getUuid());
                if (!$product_sale_info) {
                    $product_sale_info = new ProductSaleInfo();
                    $product_sale_info->setUuid($product->getUuid());
                }

                $product_sale_info->setCurrency(isset($options['currency']) ? $options['currency'] : 'VND');
                $product_sale_info->setPrice(isset($options['price']) ? $options['price'] : 0);
                $result = $product_sale_info->__quickSave();

                if (!$result['success']) {
                    goto end;
                }
                break;
            case 2:
                $productRentInfo = ProductRentInfo::findFirstByUuid($product->getUuid());
                if (!$productRentInfo) {
                    $productRentInfo = new ProductRentInfo();
                    $productRentInfo->setUuid($product->getUuid());

                }
                $productRentInfo->setCurrency(isset($options['currency']) ? $options['currency'] : 'VND');
                $productRentInfo->setPrice(isset($options['price']) ? $options['price'] : 0);
                $resultCreateInfo = $productRentInfo->__quickSave();

                if (!$result['success']) {
                    goto end;
                }
                break;
            case 3:

                break;
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}