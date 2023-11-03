<?php

namespace SMXD\app\controllers\api;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\BusinessOrder;
use SMXD\App\Models\Company;
use SMXD\App\Models\ModuleModel;
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
        $data = $data instanceof BusinessOrder ? $data->toArray() : [];

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
        $model->setProductId(Helpers::__getRequestValue('product_id'));
        $model->setAmount(Helpers::__getRequestValue('amount'));
        $model->setQuantity(Helpers::__getRequestValue('quantity'));
        $model->setBillingAddressId(Helpers::__getRequestValue('billing_address_id'));
        $model->setShippingAddressId(Helpers::__getRequestValue('shipping_address_id'));
        $model->setDeliveryAddressId(Helpers::__getRequestValue('deliver_address_id'));
        $model->setCurrency(Helpers::__getRequestValue('currency'));


        $model->setOwnerStaffUserId(Helpers::__getRequestValue('order_staff_user_id'));
        $model->setProductAuctionInfoId(Helpers::__getRequestValue('product_auction_info_id'));
        $model->setProductSaleInfoId(Helpers::__getRequestValue('product_sale_info_id'));
        $model->setProductRentInfoId(Helpers::__getRequestValue('product_rent_info_id'));
        $model->setCreatorEndUserId(ModuleModel::$user->getId());
        $model->setTargetCompanyId(ModuleModel::$company->getId());

        $model->setStatus(BusinessOrder::STATUS_PENDING);
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


}