<?php

namespace SMXD\api\controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\BusinessOrder;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Models\Product;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;

class BusinessOrderController extends ModuleApiController
{
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $data = BusinessOrder::findFirstByUuid($uuid);

        if ($data instanceof BusinessOrder && $data->getIsDeleted() != ModelHelper::YES) {
            $data_array = $data->toArray();
            $result = [
                'success' => true,
                'data' => $data_array
            ];
        }

        $this->response->setJsonContent($result);

        end:
        $this->response->send();
    }

    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $productUuid = Helpers::__getRequestValue('productUuid');
        $type = Helpers::__getRequestValue('type');
        $product = Product::findFirstByUuid($productUuid);
        if(!$product){
            $result = ['success' => false, 'message' => 'PRODUCT_NOT_FOUND_TEXT'];
            goto end;
        }

        $model = new BusinessOrder();
        $model->setUuid(Helpers::__uuid());
        $model->setProductId($product->getId());

        if($type == BusinessOrder::TYPE_BUY){
            $productSaleInfo = $product->getProductSaleInfo();
            $model->setProductSaleInfoId($productSaleInfo->getId());
            $model->setAmount($productSaleInfo->getPrice());
            $model->setCurrency($productSaleInfo->getCurrency());
            $model->setQuantity(Helpers::__getRequestValue('quantity'));


        }
        if($type == BusinessOrder::TYPE_RENT){
            $productRentInfo = $product->getProductRentInfo();
            $model->setProductRentInfoId($productRentInfo->getId());
            $model->setAmount($productRentInfo->getPrice());
            $model->setCurrency($productRentInfo->getCurrency());
            $model->setQuantity(Helpers::__getRequestValue('quantity'));

        }

        if($type == BusinessOrder::TYPE_AUCTION){
            $model->setProductAuctionInfoId(Helpers::__getRequestValue('product_auction_info_id'));
        }

        $model->setBillingAddressId(Helpers::__getRequestValue('billing_address_id'));
        $model->setShippingAddressId(Helpers::__getRequestValue('shipping_address_id'));
        $model->setDeliveryAddressId(Helpers::__getRequestValue('deliver_address_id'));

        $model->setOwnerStaffUserId(Helpers::__getRequestValue('order_staff_user_id'));
        $model->setCreatorEndUserId(ModuleModel::$user->getId());
        $model->setTargetCompanyId(ModuleModel::$company->getId());

        $model->setStatus(BusinessOrder::STATUS_IN_PROCESSING);
        if(!$model->getNumber()){
            $model->setNumber($model->generateOrderNumber());
        }


        $result = $model->__quickCreate();


        end:
        $this->response->setJsonContent($result);
        $this->response->send();
    }
}