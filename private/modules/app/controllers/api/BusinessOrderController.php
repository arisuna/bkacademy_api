<?php

namespace SMXD\app\controllers\api;

use Reloday\Gms\Models\Task as Task;
use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\BusinessOrder;
use SMXD\App\Models\BusinessOrderProduct;
use SMXD\App\Models\Company;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\Product;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

class BusinessOrderController extends BaseController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $data = BusinessOrder::find([
            'conditions' => 'status = 1'
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
        $params['statuses'] = Helpers::__getRequestValue('statuses');
        $result = BusinessOrder::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
//        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $data = BusinessOrder::findFirstByUuid($uuid);
        $data = $data instanceof BusinessOrder ? $data->parsedDataToArray() : [];

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
//        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
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
//        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
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
        $model = new BusinessOrder();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = BusinessOrder::findFirstByUuid($uuid);
            if (!$model instanceof BusinessOrder) {
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

        $products = Helpers::__getRequestValue('products');
        $type = Helpers::__getRequestValue('type');


        $totalAmount = 0;

        foreach ($products as $product){
            if(!is_array($product)){
                $product = (array)$product;
            }
            $productModel = Product::findFirstByUuid($product['uuid']);


            $businessOrderProduct = new BusinessOrderProduct();
            $businessOrderProduct->setUuid(Helpers::__uuid());
            $businessOrderProduct->setBusinessOrderUuid($model->getUuid());
            $businessOrderProduct->setQuantity(isset($product['quantity']) ?? 1);
            $businessOrderProduct->setProductId($productModel->getId());
            $businessOrderProduct->setProductAuctionInfoId($productModel->getProductAuctionInfo() ? $productModel->getProductAuction()->getId() : null);
            $businessOrderProduct->setProductSaleInfoId($productModel->getProductSaleInfo() ? $productModel->getProductSaleInfo()->getId() : null);
            $businessOrderProduct->setProductRentInfoId($productModel->getProductRentInfo() ? $productModel->getProductRentInfo()->getId() : null);

            $resultBusinessOrderProduct = $model->__quickCreate();

            if(!$resultBusinessOrderProduct['success']){
                $result = $resultBusinessOrderProduct;
                goto end;
            }

            switch ($type){
                case BusinessOrder::TYPE_BUY:
                    $productSaleInfo = $productModel->getProductSaleInfo();
                    $totalAmount = $totalAmount + ((double)$productSaleInfo->getPrice() * (int)$businessOrderProduct->getQuantity());
                    break;
                case BusinessOrder::TYPE_RENT:
                    $productRentInfo = $productModel->getProductRentInfo();
                    $totalAmount = $totalAmount + ((double)$productRentInfo->getPrice() * (int)$businessOrderProduct->getQuantity());
                    break;
                case BusinessOrder::TYPE_AUCTION:
                    $totalAmount = $totalAmount + isset($product['price']) ?? 0;
                    break;
                default:
                    break;
            }

        }


//        $model->setProductId(Helpers::__getRequestValue('product_id'));
//        $model->setProductAuctionInfoId(Helpers::__getRequestValue('product_auction_info_id'));
//        $model->setProductSaleInfoId(Helpers::__getRequestValue('product_sale_info_id'));
//        $model->setProductRentInfoId(Helpers::__getRequestValue('product_rent_info_id'));


        $model->setBillingAddressId(Helpers::__getRequestValue('billing_address_id'));
        $model->setShippingAddressId(Helpers::__getRequestValue('shipping_address_id'));
        $model->setDeliveryAddressId(Helpers::__getRequestValue('deliver_address_id'));
        $model->setCurrency(Helpers::__getRequestValue('currency'));
        $model->setAmount($totalAmount);
        $model->setType($type);


        $model->setOwnerStaffUserId(Helpers::__getRequestValue('order_staff_user_id'));
        $model->setCreatorEndUserId(ModuleModel::$user->getId());
        $model->setTargetCompanyId(ModuleModel::$company->getId());

        $model->setStatus(BusinessOrder::ORDER_STATUS_PENDING);
        if(!$model->getNumber()){
            $model->setNumber($model->generateOrderNumber());
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
//        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();

        $result = [
            'success' => false,
            'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
        ];


        if (Helpers::__isValidUuid($uuid)) {
            $model = BusinessOrder::findFirstByUuid($uuid);
            if ($model instanceof BusinessOrder) {
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

    public function setCompletedAction(string $order_uuid)
    {
        return $this->changeStatus($order_uuid, BusinessOrder::ORDER_STATUS_COMPLETED);
    }

    /**
     * @return mixed
     */
    public function setConfirmedAction(string $order_uuid)
    {
        return $this->changeStatus($order_uuid, BusinessOrder::ORDER_STATUS_CONFIRMED);
    }

    /**
     * @return mixed
     */
    public function setCancelledAction(string $order_uuid)
    {
        return $this->changeStatus($order_uuid, BusinessOrder::ORDER_STATUS_CANCELED);
    }

    /**
     * @param string $order_uuid
     * @param int $status
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function changeStatus(string $order_uuid, int $status){
        $this->view->disable();
//        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();
        $result = ['success'=> false, 'message' => 'ORDER_NOT_FOUND_TEXT'];

        $order = BusinessOrder::findFirstByUuid($order_uuid);

        if(!$order){
            goto end;
        }

        $order->setStatus($status);
        $result = $order->__quickUpdate();

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

}