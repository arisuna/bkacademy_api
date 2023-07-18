<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\AccountProductPricing;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\InvoiceQuoteItem;
use Reloday\Gms\Models\ModuleModel;
use \Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ProductPricing;
use Reloday\Gms\Models\ServiceCompany;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ProductController extends BaseController
{
    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function getListProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX]
        ]);

        $data = [];
        $data["query"] = Helpers::__getRequestValue("query");
        $data["service_ids"] = [];
        $data["account_ids"] = [];

        $services = Helpers::__getRequestValue("services");
        if (is_array($services) && count($services) > 0) {
            foreach ($services as $service) {
                $data["service_ids"][] = $service->id;
            }
        }

        $companies = Helpers::__getRequestValue("companies");
        if (is_array($companies) && count($companies) > 0) {
            foreach ($companies as $company) {
                $data["account_ids"][] = $company->id;
            }
        }

        $data["account_id"] = Helpers::__getRequestValue("account_id");
        $data["page"] = Helpers::__getRequestValue("page");
        $data["limit"] = 1000;

        $result = ProductPricing::getListOfMyCompany($data);

        if ($result['success'] == true) {
            $this->response->setJsonContent([
                'success' => true,
                'data' => $result['data'],
                'params' => $data,
            ]);
        } else {
            $this->response->setJsonContent($result);
        }
        $this->response->send();

    }

    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function getAllProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX]
        ]);

        $is_active = Helpers::__getRequestValue('is_active');
        $query = Helpers::__getRequestValue('query');
        $conditionFilterName = "";
        if(isset($query) && $query != "" && strlen($query) > 0 ){
            $query = str_replace("'", "\\'", $query);
            $conditionFilterName = " AND name LIKE '%".htmlspecialchars($query)."%' ";
        }

        if ($is_active == null && !is_numeric($is_active)) {
            $products = ProductPricing::find([
                "conditions" => "is_deleted = 0 and company_id = :company_id:" . $conditionFilterName,
                "bind" => [
                    "company_id" => ModuleModel::$company->getId()
                ],
                "order" => "created_at DESC",
            ]);
        } else if ($is_active == 0 && is_numeric($is_active)) {
            $products = ProductPricing::find([
                "conditions" => "is_deleted = 0 and company_id = :company_id: and is_active = 0" . $conditionFilterName,
                "bind" => [
                    "company_id" => ModuleModel::$company->getId()
                ],
                "order" => "created_at DESC",
            ]);
        } else {
            $products = ProductPricing::find([
                "conditions" => "is_deleted = 0 and company_id = :company_id: and is_active = 1" . $conditionFilterName,
                "bind" => [
                    "company_id" => ModuleModel::$company->getId()
                ],
                "order" => "created_at DESC",
            ]);
        }

        $items = [];
        if (count($products) > 0) {
            foreach ($products as $product) {
                $item = $product->toArray();
                $item['id'] = intval($product->getId());
                if ($product->getServiceLinked() == ProductPricing::SERVICE_LINKED && $product->getServiceCompany() instanceof ServiceCompany) {
                    $item["service_name"] = $product->getServiceCompany()->getName();
                }
                $item["tax_rate"] = $product->getTaxRule() ? round($product->getTaxRule()->getRate(), 2) : 0;
                $items[] = $item;
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        $this->response->send();

    }

    /**
     * @return mixed
     */
    public function createProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $data = Helpers::__getRequestValuesArray();
        $product = new ProductPricing();
        $data['company_id'] = ModuleModel::$company->getId();
        $productIfExist = ProductPricing::findFirst([
            "conditions" => "name = :name: and company_id = :company_id: and is_deleted = 0",
            "bind" => [
                "name" => $data["name"],
                "company_id" => $data["company_id"]
            ]
        ]);
        if ($productIfExist instanceof ProductPricing) {
            $result['message'] = 'PRODUCT_NAME_MUST_BE_UNIQUE_TEXT';
            $result['success'] = false;
            goto end_of_function;
        }

        $service_linked = Helpers::__getRequestValue("service_linked");
        $product->setData($data);
        if ($service_linked) $product->setServiceLinked(1);
        $result = $product->__quickCreate();
        if ($result['success'] == false) {
            $result['message'] = 'SAVE_PRODUCT_FAIL_TEXT';
        } else {
            $result['message'] = 'SAVE_PRODUCT_SUCCESS_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function detailProductAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX]
        ]);
        $result = [
            'success' => false,
            'message' => 'PRODUCT_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $product = ProductPricing::findFirstByUuid($uuid);
            if ($product instanceof ProductPricing && $product->belongsToGms()) {
                $account_prices = $product->getAllAccountProducts();
                $account_prices_array = [];
                if (count($account_prices) > 0) {
                    foreach ($account_prices as $account_price) {
                        $account_price_array = $account_price->toArray();
                        $account_price_array["account_name"] = $account_price->getAccount()->getName();
                        $account_price_array["tax_rate"] = $account_price->getTaxRule() ? $account_price->getTaxRule()->getRate() : 0;
                        $account_prices_array[] = $account_price_array;
                    }
                }
                $item = $product->toArray();
                $item["tax_rate"] = $product->getTaxRule() ? $product->getTaxRule()->getRate() : 0;
//                if ($product->getServiceLinked() == ProductPricing::SERVICE_LINKED) {
////                    $item["service_linked"] = true;
////                }
                $item["account_prices"] = $account_prices_array;
                $result = [
                    'success' => true,
                    'data' => $item
                ];
            }
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Save service action
     */
    public function editProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'PRODUCT_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $product = ProductPricing::findFirstByUuid($uuid);

            if ($product instanceof ProductPricing && $product->belongsToGms()) {
                $this->db->begin();
                $old_currency = $product->getCurrency();
                $new_currency = Helpers::__getRequestValue("currency");
                $old_cost = $product->getCost();
                $new_cost = floatval(Helpers::__getRequestValue("cost"));
                $old_price = $product->getPrice();
                $new_price = Helpers::__getRequestValue("price");
                if (($old_currency != $new_currency || $old_cost != $new_cost) && !$product->isCurrencyEditable()) {
                    $this->db->rollback();
                    $result['message'] = 'PRODUCT_CAN_NOT_EDIT_TEXT';
                    goto end_of_function;
                }
                if ($old_price != $new_price && !$product->isPriceEditable()) {
                    $this->db->rollback();
                    $result['message'] = 'PRODUCT_CAN_NOT_EDIT_TEXT';
                    goto end_of_function;
                }
                if ($old_currency != $new_currency || $old_cost != $new_cost || $old_price != $new_price) {
                    $account_product_pricings = $product->getAccountProducts();
                    if (count($account_product_pricings) > 0) {
                        foreach ($account_product_pricings as $account_product_pricing) {
                            $account_product_pricing->setCost($new_cost);
                            $account_product_pricing->setCurrency($new_currency);
                            $result = $account_product_pricing->__quickUpdate();

                            if ($result['success'] == false) {
                                $this->db->rollback();
                                $result['message'] = 'SAVE_PRODUCT_FAIL_TEXT';
                                $result['detail'] = $account_product_pricing;
                                goto end_of_function;
                            }
                        }
                    }
                }
                $product->setData(Helpers::__getRequestValuesArray());


                $service_linked = Helpers::__getRequestValue("service_linked");
                if ($service_linked == true || $service_linked == 1) {
                    $product->setServiceLinked(ModelHelper::YES);
                } else {
                    $product->setServiceLinked(ModelHelper::NO);
                }

                $productIfExist = ProductPricing::findFirst([
                    "conditions" => "name = :name: and company_id = :company_id: and id != :id: and is_deleted = 0",
                    "bind" => [
                        "name" => Helpers::__getRequestValue("name"),
                        "company_id" => ModuleModel::$company->getId(),
                        "id" => $product->getId()
                    ]
                ]);
                if ($productIfExist instanceof ProductPricing) {
                    $result['message'] = 'PRODUCT_NAME_MUST_BE_UNIQUE_TEXT';
                    $result['success'] = false;
                    $this->db->rollback();
                    goto end_of_function;
                }


                $result = $product->__quickUpdate();

//                var_dump(get_class($result));
//                die();
//                die(__METHOD__);
                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_PRODUCT_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }
                $result['message'] = 'SAVE_PRODUCT_SUCCESS_TEXT';
                $this->db->commit();

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function cloneProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'PRODUCT_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $product = ProductPricing::findFirstByUuid($uuid);

            if ($product instanceof ProductPricing && $product->belongsToGms()) {
                $this->db->begin();
                $product_clone = new ProductPricing();
                $name = Helpers::__getRequestValue("name");
                $products = ProductPricing::find([
                    "conditions" => "name = :name: and company_id = :company_id: and is_deleted = 0",
                    "bind" => [
                        "name" => $name,
                        "company_id" => ModuleModel::$company->getId()
                    ]
                ]);
                $data = $product->toArray();
                unset($data['id']);
                unset($data['uuid']);
                $product_clone->setData($data);
                $product_clone->setName($name . (count($products) > 0 ? ' ' . count($products) : ''));
                $result = $product_clone->__quickCreate();
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $result['message'] = 'SAVE_PRODUCT_FAIL_TEXT';
                    goto end_of_function;
                } else {
                    $result['message'] = 'SAVE_PRODUCT_SUCCESS_TEXT';
                    $account_prices = $product->getActiveAccountProducts();
                    if (count($account_prices) > 0) {
                        foreach ($account_prices as $account_price) {
                            $account_product = new AccountProductPricing();
                            $data_account_product = $account_price->toArray();
                            $data_account_product["uuid"] = "";
                            $account_product->setData($data_account_product);
                            $account_product->setProductPricingId($product_clone->getId());
                            $result = $account_product->__quickCreate();
                            if ($result['success'] == false) {
                                $this->db->rollback();
                                $result['account_product_pricing'] = $account_product;
                                $result['product'] = $product_clone;
                                $result['message'] = 'SAVE_ACCOUNT_PRODUCT_FAIL_TEXT';
                                goto end_of_function;
                            }
                        }
                    }
                }
                $this->db->commit();
                $result['message'] = 'SAVE_ACCOUNT_PRODUCT_SUCCESS_TEXT';
                $result["data"] = $product_clone;
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeProductAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'PRODUCT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $product = ProductPricing::findFirstByUuid($uuid);

            $account_products = AccountProductPricing::findByProductPricingId($product->getId());

            if (!$product->isRemovable()) {
                $return = [
                    "success" => false,
                    "message" => "PRODUCT_CAN_NOT_REMOVE_TEXT"
                ];
                goto end_of_function;
            }
            if (count($account_products) > 0) {
                foreach ($account_products as $account_product) {
                    $account_product->__quickRemove();
                }
            }
            if ($product instanceof ProductPricing && $product->belongsToGms()) {

                $return = $product->__quickRemove();
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function archiveProductAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'PRODUCT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $product = ProductPricing::findFirstByUuid($uuid);
            if ($product instanceof ProductPricing && $product->belongsToGms()) {
                $this->db->begin();

                $product->setIsActive(Helpers::NO);
                $return = $product->__quickUpdate();
                if ($return['success'] == false) {
                    $this->db->rollback();
                    $return['message'] = 'DATA_SAVE_FAILED_TEXT';
                    goto end_of_function;
                }
                $account_products = AccountProductPricing::findByProductPricingId($product->getId());

                if (count($account_products) > 0) {
                    foreach ($account_products as $account_product) {
                        $account_product->setIsActive(Helpers::NO);
                        $return = $account_product->__quickUpdate();
                        if ($return['success'] == false) {
                            $this->db->rollback();
                            $return['message'] = 'DATA_SAVE_FAILED_TEXT';
                            goto end_of_function;
                        }
                    }
                }
                $return['message'] = 'DATA_SAVE_SUCCESS_TEXT';
                $this->db->commit();
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function activeProductAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'PRODUCT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $product = ProductPricing::findFirstByUuid($uuid);
            if ($product instanceof ProductPricing && $product->belongsToGms()) {
                $this->db->begin();

                $product->setIsActive(Helpers::YES);
                $return = $product->__quickUpdate();
                if ($return['success'] == false) {
                    $this->db->rollback();
                    $return['message'] = 'DATA_SAVE_FAILED_TEXT';
                    goto end_of_function;
                }
                $return['message'] = 'DATA_SAVE_SUCCESS_TEXT';
                $this->db->commit();
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function saveAccountProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'ACCOUNT_PRODUCT_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $account_product = AccountProductPricing::findFirstByUuid($uuid);

            if ($account_product instanceof AccountProductPricing && $account_product->belongsToGms()) {
                $old_currency = $account_product->getCurrency();
                $new_currency = Helpers::__getRequestValue("currency");
                $old_price = $account_product->getPrice();
                $new_price = Helpers::__getRequestValue("price");
                $old_product_pricing_id = $account_product->getProductPricingId();
                $new_product_pricing_id = Helpers::__getRequestValue("product_pricing_id");
                if (!$account_product->isChangeable() && ($old_currency != $new_currency || $old_price != $new_price || $old_product_pricing_id != $new_product_pricing_id)) {
                    $result['message'] = 'ACCOUNT_PRODUCT_CAN_NOT_EDIT_TEXT';
                    $result['success'] = false;
                    goto end_of_function;
                }
                $account_product->setData(Helpers::__getRequestValuesArray());
                $account_product->setCost((int)Helpers::__getRequestValue('cost'));
                $account_product->setPrice((int)Helpers::__getRequestValue('price'));
                $account_product->setTaxRuleId(Helpers::__getRequestValue('tax_rule_id'));
                $account_product->setExternalHrisId(Helpers::__getRequestValue('external_hris_id'));

                $accountProductIfExist = AccountProductPricing::findFirst([
                    "conditions" => "name = :name: AND account_id = :account_id: AND product_pricing_id = :product_pricing_id: AND company_id = :company_id: and id != :id: AND is_deleted = 0",
                    "bind" => [
                        "name" => Helpers::__getRequestValue("name"),
                        "account_id" => Helpers::__getRequestValue("account_id"),
                        "product_pricing_id" => Helpers::__getRequestValue("product_pricing_id"),
                        "company_id" => ModuleModel::$company->getId(),
                        "id" => $account_product->getId()
                    ]
                ]);
                if ($accountProductIfExist instanceof AccountProductPricing) {
                    $result['message'] = 'PRODUCT_NAME_EXISTED_TEXT';
                    $result['success'] = false;
                    goto end_of_function;
                }

                $result = $account_product->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'PRODUCT_NAME_EXISTED_TEXT';
                } else {
                    $itemToArray = $account_product->toArray();
                    $itemToArray['product_name'] = $account_product->getProductPricing()->getName();
                    $result['message'] = 'SAVE_ACCOUNT_PRODUCT_SUCCESS_TEXT';
                    $result['data'] = $itemToArray;
                }

            }
        } else {
            $account_product = new AccountProductPricing();
            $data = Helpers::__getRequestValuesArray();
            $data['company_id'] = ModuleModel::$company->getId();
//            $accountProductIfExist = AccountProductPricing::findFirst([
//                "conditions" => "account_id = :account_id: and product_pricing_id = :product_pricing_id: and company_id = :company_id: and is_deleted = 0",
//                "bind" => [
//                    "account_id" => Helpers::__getRequestValue("account_id"),
//                    "product_pricing_id" => Helpers::__getRequestValue("product_pricing_id"),
//                    "company_id" => ModuleModel::$company->getId()
//                ]
//            ]);
//            if ($accountProductIfExist instanceof AccountProductPricing) {
//                $result['message'] = 'ACCOUNT_PRODUCT_MUST_BE_UNIQUE_TEXT';
//                $result['detail'] = $accountProductIfExist;
//                $result['success'] = false;
//                goto end_of_function;
//            }

            $account_product->setIsDeleted(Helpers::NO);
            $account_product->setData($data);
            $account_product->setCost((int)Helpers::__getRequestValue('cost'));
            $account_product->setPrice((int)Helpers::__getRequestValue('price'));
            $result = $account_product->__quickCreate();
            if ($result['success'] == false) {
                if (isset($result['errorMessage']) && is_array($result['errorMessage']) && is_string(end($result['errorMessage']))) {
                    $return['message'] = end($result['errorMessage']);
                } else {
                    $return['message'] = "SAVE_ACCOUNT_PRODUCT_FAIL_TEXT";
                }
            } else {
                $itemToArray = $account_product->toArray();
                $itemToArray['product_name'] = $account_product->getProductPricing()->getName();
                $result['message'] = 'SAVE_ACCOUNT_PRODUCT_SUCCESS_TEXT';
                $result['data'] = $itemToArray;
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function archiveAccountProductAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPUT();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'ACCOUNT_PRODUCT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $account_product = AccountProductPricing::findFirstByUuid($uuid);

            if ($account_product instanceof AccountProductPricing && $account_product->belongsToGms()) {

                $account_product->setIsActive(Helpers::NO);
                $return = $account_product->__quickUpdate();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function activeAccountProductAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPUT();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'ACCOUNT_PRODUCT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $account_product = AccountProductPricing::findFirstByUuid($uuid);

            if ($account_product instanceof AccountProductPricing && $account_product->belongsToGms()) {
                $product = $account_product->getProductPricing();
                if (!$product) {
                    $return = [
                        'success' => false,
                        'message' => 'PRODUCT_NOT_FOUND_TEXT'
                    ];
                    goto end_of_function;
                }
                if ($product->getIsActive() != Helpers::YES) {
                    $return = [
                        'success' => false,
                        'message' => 'PRODUCT_MUST_ACTIVE_TEXT'
                    ];
                    goto end_of_function;
                }
                $account_product->setIsActive(Helpers::YES);
                $return = $account_product->__quickUpdate();
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeAccountProductAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'ACCOUNT_PRODUCT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $account_product = AccountProductPricing::findFirstByUuid($uuid);

            if ($account_product instanceof AccountProductPricing && $account_product->belongsToGms()) {

                if (!$account_product->isChangeable()) {
                    $return['message'] = 'ACCOUNT_PRODUCT_CAN_NOTE_REMOVE_TEXT';
                    $return['success'] = false;
                } else {
                    $return = $account_product->__quickRemove();
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function getListAccountProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX]
        ]);

        $data = [];
        $data["query"] = Helpers::__getRequestValue("query");
        $account_products_results = AccountProductPricing::getList($data);
        $this->response->setJsonContent($account_products_results);
        $this->response->send();

    }

    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function getAccountPriceListDetailAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $data = [];
        $query = Helpers::__getRequestValue("query");
        $uuid = Helpers::__getRequestValue("uuid");
        $result = [
            "success" => false,
            "message" => "COMPANY_NOT_FOUND_TEXT"
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $account = Company::findFirstByUuid($uuid);
            if ($account instanceof Company) {
                $account_products = AccountProductPricing::find([
                    "conditions" => "is_deleted = 0 and name LIKE :name: and account_id = :account_id: and company_id = :company_id:",
                    "bind" => [
                        "name" => "%" . $query . "%",
                        "account_id" => $account->getId(),
                        "company_id" => ModuleModel::$company->getId()
                    ]
                ]);
                $account_array = $account->toArray();
                $account_array['number_of_active_account_prices'] = AccountProductPricing::count([
                    "conditions" => "is_deleted = 0 and account_id = :account_id: and company_id = :company_id: and is_active = 1",
                    "bind" => [
                        "account_id" => $account->getId(),
                        "company_id" => ModuleModel::$company->getId()
                    ]
                ]);
                $account_array['number_of_archive_account_prices'] = AccountProductPricing::count([
                    "conditions" => "is_deleted = 0 and account_id = :account_id: and company_id = :company_id: and is_active = 0",
                    "bind" => [
                        "account_id" => $account->getId(),
                        "company_id" => ModuleModel::$company->getId()
                    ]
                ]);

                $account_products = AccountProductPricing::__findWithFilter([
                    'query' => $query,
                    'account_id' => $account->getId(),
                ]);
                $result = [
                    "success" => true,
                    "account_prices" => $account_products,
                    "account" => $account_array
                ];
            }
        }
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function getListProductByAccountForInvoiceAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_CREDIT_NOTE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_QUOTE, 'action' => AclHelper::ACTION_INDEX]
        ]);

        $data = [];
        $account_id = Helpers::__getRequestValue("account_id");
        $account_price_only = Helpers::__getRequestValue("account_price_only");
        $currency = Helpers::__getRequestValue("currency");
        $products = ProductPricing::find([
            "conditions" => "is_deleted = 0 and company_id = :company_id:",
            "bind" => [
                "company_id" => ModuleModel::$company->getId()
            ]
        ]);

        if (count($products) > 0) {
            foreach ($products as $product) {
                $account_product_pricing = AccountProductPricing::findFirst([
                    "conditions" => "product_pricing_id = :product_pricing_id: and is_deleted = 0 and account_id = :account_id: and company_id = :company_id: and currency = :currency:",
                    "bind" => [
                        "product_pricing_id" => $product->getId(),
                        "account_id" => $account_id,
                        "company_id" => ModuleModel::$company->getId(),
                        "currency" => $currency
                    ]
                ]);
                if ($account_product_pricing instanceof AccountProductPricing) {
                    $item = $account_product_pricing->toArray();
                    $item["type"] = InvoiceQuoteItem::TYPE_ACCOUNT_PRODUCT;
                    $item["tax_rule"] = $product->getTaxRule();
                    $item["tax_rule_id"] = intval($product->getTaxRuleId());
                    $item["description"] = $account_product_pricing->getProductPricing()->getDescription();
                    $data[] = $item;
                } else if (!$account_price_only && $product->getCurrency() == $currency) {
                    $item = $product->toArray();
                    $item["type"] = InvoiceQuoteItem::TYPE_PRODUCT;
                    $item["tax_rule"] = $product->getTaxRule();
                    $item["tax_rule_id"] = intval($product->getTaxRuleId());
                    $data[] = $item;
                }
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);
        $this->response->send();

    }


    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function getListProductForAccountProductAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX]
        ]);

        $data = [];

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ProductPricing', 'ProductPricing');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'ProductPricing.id',
            'ProductPricing.uuid',
            'ProductPricing.name',
            'ProductPricing.currency',
            'ProductPricing.cost',
            "service_name" => 'ServiceCompany.name'
        ]);
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AccountProductPricing', 'ProductPricing.id = AccountProductPricing.product_pricing_id and AccountProductPricing.is_deleted != 1 and AccountProductPricing.account_id = ' . Helpers::__getRequestValue("account_id"), 'AccountProductPricing');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = ProductPricing.service_company_id', 'ServiceCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\TaxRule', 'TaxRule.id = ProductPricing.tax_rule_id', 'TaxRule');
        $queryBuilder->where('ProductPricing.company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $queryBuilder->andWhere('ProductPricing.is_deleted = 0');
        $queryBuilder->andWhere('ProductPricing.is_active = 1');

        $queryBuilder->andWhere('AccountProductPricing.id is null');

        $queryBuilder->groupBy('ProductPricing.id');
        $data = $queryBuilder->getQuery()->execute();
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);
        $this->response->send();

    }
}
