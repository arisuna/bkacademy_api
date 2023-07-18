<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\SequenceHelper;
use Reloday\Application\Models\SequenceExt;
use Reloday\Gms\Models\Addon;
use Reloday\Gms\Models\AddonContent;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Subscription;
use Reloday\Gms\Models\SubscriptionAddon;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\CompanyApiGatewayUsagePlan;


class AddonController extends BaseController{

    public function getAddonAction(){
        $this->view->disable();
        $this->checkAjax('POST');

        $return = [
            'success' => false,
            'messenger' => 'NO_DATA_FOUND',
        ];

        $language = Helpers::__getRequestValue('language');

        $addon = Addon::__findWithFilters(['language' => $language]);

        if($addon['success'] == true  && count($addon['data']) > 0){
            $return = [
                'success' => true,
                'messenger' => 'DATA_FOUND',
                'data' => $addon['data'],
            ];
        }

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    public function getDetailAddonContentAction(){
        $this->view->disable();
        $this->checkAjax('POST');
        $addonContentResult = [];
        $return = [
            'success' => false,
            'message' => 'NO_DATA_FOUND',
        ];

        $language = Helpers::__getRequestValue('language');
        $addon_id = Helpers::__getRequestValue('addon_id');

        $addonContent = AddonContent::findFirst([
            'conditions' => 'addon_id = :addon_id: AND language = :language: ',
            'bind' => [
                'addon_id' => $addon_id,
                'language' => $language
            ],
        ]);

        if($addonContent){
            $addonContentResult = $addonContent->toArray();
            $addonId = $addonContent->getAddonId();
            $listAddon = Addon::__findWithFilters(['language' => $language]);
            if($listAddon['success'] == true){
                foreach ($listAddon['data'] as $addon){
                    if($addon['addon_id'] == $addonId){
                        $subscriptionAddon = SubscriptionAddon::findFirst([
                            'conditions' => 'subscription_id = :subscription_id: and addon_id = :addon_id:',
                            'bind' => [
                                'subscription_id' => $addon['subscription_id'],
                                'addon_id' => $addonId,
                            ]
                        ]);

                        $addonContentResult['first_payment_date'] = $subscriptionAddon->getCreatedAt() ? $subscriptionAddon->getCreatedAt() : '';
                        $addonContentResult['is_public_api_addon'] = Addon::isPublicApiAddon($addonId) ? 1 : 0;
                        $addonContentResult['have_by'] = ModelHelper::YES;

                        if($addonContentResult['is_public_api_addon'] == ModelHelper::YES){
                            $addonContentResult['api_endpoint'] = getenv('RELO_API_ENDPOINT');
                            $company_api_gateway_usage_plan = CompanyApiGatewayUsagePlan::findFirstByCompanyId(ModuleModel::$company->getId());
                            if(!$company_api_gateway_usage_plan){
                                $return = [
                                    'success' => false,
                                    'message' => 'API_KEY_NOT_FOUND_TEXT',
                                    'detail' => "USAGEPLAN_NOT_FOUND_TEXT"
                                ];
                                goto end_of_function;
                            }
                            $api_gateway_usage_plan = $company_api_gateway_usage_plan->getApiGatewayUsagePlan();
                            if(!$api_gateway_usage_plan){
                                $return = [
                                    'success' => false,
                                    'message' => 'API_KEY_NOT_FOUND_TEXT',
                                    'detail' => "APIGATEWAY_USAGEPLAN_NOT_FOUND_TEXT"
                                ];
                                goto end_of_function;
                            }
                            $addonContentResult["frequency_description"] = $api_gateway_usage_plan->getDescription();
                        }
                        break;
                    }else{
                        $addonContentResult['have_by'] = ModelHelper::NO;
                    }
                }
            }

            $return = [
                'success' => true,
                'messenger' => 'DATA_FOUND',
                'data' => $addonContentResult
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    public function getApiCountAction(){
        $this->view->disable();
        $this->checkAjax('GET');

        $companyId = ModuleModel::$company->getId();
        $company = Company::findFirstById((int)$companyId);
        $users = [];


        if (!$company instanceof Company) {
            $this->response->setJsonContent(['success' => false, 'message' => 'Data Not Found']);
            goto end_of_function;
        }

        $companies_data = SequenceHelper::getAllDynamoSqLastVal(SequenceExt::PREFIX . "_APIKEY_COMPANY_" . $companyId);
        $uris_data = SequenceHelper::getAllDynamoSqLastVal(SequenceExt::PREFIX . "_APIKEY_COMPANY_URI_" . $companyId);

        $data = [];
        if(count($companies_data) > 0){
            foreach ($companies_data as $company_data) {
                $elements = explode('_', $company_data['name']); //
                $year = substr($elements[4], 0, 4);
                $month = substr($elements[4], 4);
                $data['total'] = isset($data['total']) && $data['total'] > 0 ? $data['total'] + $company_data['value'] : $company_data['value'];
                $data[$year]['total'] = isset($data[$year]['total']) && $data[$year]['total'] > 0 ? $data[$year]['total'] + $company_data['value'] : $company_data['value'];
                $data[$year][$month]['total'] = isset($data[$year][$month]['total']) && $data[$year][$month]['total'] > 0 ? $data[$year][$month]['total'] + $company_data['value'] : $company_data['value'];
            }
        }
        if(count($uris_data) > 0){
            foreach ($uris_data as $uri_data) {
                $elements = explode('_', $uri_data['name']);
                $year = substr($elements[7], 0, 4);
                $month = substr($elements[7], 4);
                $data[$year][$month]['uris'][$elements[5]][$elements[6]] = $uri_data['value'];
            }
        }


        $this->response->setJsonContent([
            'success' => true,
            'data' => $data,
        ]);

        end_of_function:
        return $this->response->send();
    }

    public function getAllAddonAction($language = 'en'){
        $this->view->disable();
        $this->checkAjax('GET');

        $data = [];
        $i = 0;

        $currentAddonHave = Addon::__findWithFilters(['language' => $language]);
        if($currentAddonHave['success'] && count($currentAddonHave['data']) > 0){
            $listAddonHaveBy = [];
            foreach ($currentAddonHave['data'] as $item){
                array_push($listAddonHaveBy, $item['addon_id']);
            }

            $allAddons = Addon::find([
                'conditions' => 'status = :status: AND id NOT IN ({listAddonHaveBy:array}) and is_gms = 1',
                'bind' => [
                    'status' => Addon::STATUS_ACTIVE,
                    'listAddonHaveBy' => $listAddonHaveBy,
                ],
                'order' => 'id ASC'
            ]);

        }else{ // chưa có addon nào mua thì sẽ load all
            $allAddons = Addon::find([
                'conditions' => 'status = :status: and is_gms = 1',
                'bind' => [
                    'status' => Addon::STATUS_ACTIVE
                ],
                'order' => 'id ASC'
            ]);
        }

        if($allAddons){
            foreach ($allAddons as $addon){
                $data[$i] = $addon->toArray();

                $addonContent = AddonContent::findFirst([
                    'conditions' => 'addon_id = :addon_id: AND language = :language: ',
                    'bind' => [
                        'addon_id' => $addon->getId(),
                        'language' => $language ?: 'en'
                    ],
                ]);

                $data[$i]['addon_content'] = $addonContent ? $addonContent->toArray() : [];
                $data[$i]['addon_content']['image'] = Addon::getAddonImage($addon->getUuid());
                $i++;
            }
        }

        $return = [
            'success' => true,
            'data' => $data
        ];

        $this->response->setJsonContent($return);
        $this->response->send();
    }



}
