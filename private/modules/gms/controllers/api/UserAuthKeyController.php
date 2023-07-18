<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Application;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\CognitoAppHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserAuthorKey;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserLoginToken;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\CompanyApiGatewayUsagePlan;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\SequenceHelper;
use Reloday\Application\Lib\RelodayApiGatewayHelper;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class UserAuthKeyController extends BaseController
{
    /**
     * @Route("/userauthkey", paths={module="gms"}, methods={"GET"}, name="gms-userauthkey-index")
     */
    public function getAddOnKeyAction()
    {
        $this->view->disable();
        $userAuthorKey = UserAuthorKey::__findAddonAuthKeyOfUser(ModuleModel::$user_profile->getId());
        if ($userAuthorKey) {
            $result = [
                'success' => true,
                'data' => $userAuthorKey
            ];
        } else {
            $result = [
                'success' => false
            ];
        }
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function generateAddonKeyAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $hashKeyUnique = Helpers::__hash();
        $model = new UserAuthorKey();
        $model->setKeyType(UserAuthorKey::TYPE_ADDON);
        $model->setKeyValue($hashKeyUnique);
        $model->setUserProfileId(ModuleModel::$user_profile->getId()); //User profile id
        $model->setIsActive(ModelHelper::YES);
        $model->setExpirationTime(time() + CacheHelper::__TIME_6_MONTHS);

        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success'] == true) {
            $result = [
                'success' => true,
                'message' => 'GENERATE_KEY_SUCCESS_TEXT',
                'data' => $model
            ];
        } else {
            $result = [
                'success' => false,
                'message' => 'GENERATE_KEY_FAIL_TEXT',
                'errorDetail' => $resultCreate
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @Route("/userauthkey", paths={module="gms"}, methods={"GET"}, name="gms-userauthkey-index")
     */
    public function getApiKeyAction()
    {
        $this->view->disable();
        $lang = ModuleModel::$language ? ModuleModel::$language : 'en';
        $userAuthorKey = UserAuthorKey::__findApiKeyOfUser(ModuleModel::$user_profile->getId());
        $userAuthorKeyV2 = UserAuthorKey::__findApiKeyV2OfUser(ModuleModel::$user_profile->getId());
        if ($userAuthorKey) {
            $userAuthorKey["age"] = $userAuthorKey["days"] . " " . ConstantHelper::__translate("DAYS_TEXT", $lang);
            if($userAuthorKey["months"] > 0){
                $userAuthorKey["age"] .= " (".($userAuthorKey["years"] > 0 ? $userAuthorKey["years"] . "y " : "").($userAuthorKey["months"] - $userAuthorKey["years"] * 12)."m)";
            }
        }
        if ($userAuthorKeyV2) {
            $userAuthorKeyV2["age"] = $userAuthorKeyV2["days"] . " " . ConstantHelper::__translate("DAYS_TEXT", $lang);
            if($userAuthorKeyV2["months"] > 0){
                $userAuthorKeyV2["age"] .= " (".($userAuthorKeyV2["years"] > 0 ? $userAuthorKeyV2["years"] . "y " : "").($userAuthorKeyV2["months"] - $userAuthorKeyV2["years"] * 12)."m)";
            }
            $userAuthorKeyV2["key_secret"] = "***";
        }
        $company_api_gateway_usage_plan = CompanyApiGatewayUsagePlan::findFirstByCompanyId(ModuleModel::$company->getId());
        if(!$company_api_gateway_usage_plan){
            $result = [
                'success' => false,
                'message' => 'API_KEY_NOT_FOUND_TEXT',
                'detail' => "USAGEPLAN_NOT_FOUND_TEXT"
            ];
            goto end_of_function;
        }
        $api_gateway_usage_plan = $company_api_gateway_usage_plan->getApiGatewayUsagePlan();
        if(!$api_gateway_usage_plan){
            $result = [
                'success' => false,
                'message' => 'API_KEY_NOT_FOUND_TEXT',
                'detail' => "APIGATEWAY_USAGEPLAN_NOT_FOUND_TEXT"
            ];
            goto end_of_function;
        }
        $result = [
            'success' => true,
            'keyv1' => $userAuthorKey,
            'keyv2' => $userAuthorKeyV2
        ];
        $result["basic_endpoint"] = getenv('BASIC_API_ENDPOINT');
        $result["jwt_authentication_endpoint"] = getenv('JWT_AUTHENTICATION_ENDPOINT');
        $result["jwt_api_endpoint"] = getenv('JWT_API_ENDPOINT');
        $result["description"] = $api_gateway_usage_plan->getDescription();
        $result["total_used"] =  SequenceHelper::getDynamoSqLastVal(UserAuthorKey::__generateSequenceNameByCompany(ModuleModel::$user_profile->getCompanyId()));
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function generateApiKeyAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $lang = ModuleModel::$language ? ModuleModel::$language : 'en';
        $hashKeyUnique = Helpers::__hash();
        $this->db->begin();
        $other_user_keys = UserAuthorKey::find([
            "conditions" => "user_profile_id = :user_id:  AND key_type = :type_api:" ,
            "bind" => 
            [
                "user_id" => ModuleModel::$user_profile->getId(),
                "type_api" => UserAuthorKey::TYPE_API
            ]
            ]);
        if(count($other_user_keys) > 0){
            foreach($other_user_keys as $other_user_key){
                $aws_uuid = $other_user_key->getAwsUuid();
                if($aws_uuid != null && $aws_uuid != ""){
                    $delete_at_api_gateway = RelodayApiGatewayHelper::__deleteApiKey($aws_uuid);
                    // if (!$delete_at_api_gateway['success']) {
                    //     $this->db->rollback();
                    //     $result = [
                    //         'success' => false,
                    //         'message' => 'DELETE_OLD_KEY_FAILED_TEXT',
                    //         'detail' => $delete_at_api_gateway
                    //     ];
                    //     goto end;
                    // }
                }
                $resultDelete = $other_user_key->__quickRemove();
                if (!$resultDelete['success']) {
                    $this->db->rollback();
                    $result = [
                        'success' => false,
                        'message' => 'DELETE_OLD_KEY_FAILED_TEXT',
                    ];
                    goto end;
                }
            }
        }
        $model = new UserAuthorKey();
        $model->setKeyType(UserAuthorKey::TYPE_API);
        $model->setKeyValue($hashKeyUnique);
        $model->setUserProfileId(ModuleModel::$user_profile->getId()); //User profile id
        $model->setIsActive(ModelHelper::YES);
        $model->setExpirationTime(time() + CacheHelper::__TIME_3_MONTHS);
        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success'] == true) {
            $company_name = ModuleModel::$company->getName();
            if(getenv("ENVIR") == 'LOCAL'){
                $create_api_key_at_api_gateway = RelodayApiGatewayHelper::__createApiKey($model->getId() . " - ". $company_name." - LOCAL", $model->getKeyValue());
            } else {
                $create_api_key_at_api_gateway = RelodayApiGatewayHelper::__createApiKey($model->getId() . " - ". $company_name, $model->getKeyValue());
            }
            if($create_api_key_at_api_gateway["success"] !=  true){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => $create_api_key_at_api_gateway
                ];
                $this->db->rollback();
                goto end;
            }
            $keyId = $create_api_key_at_api_gateway["keyId"];
            $model->setAwsUuid($keyId);
            $update_key = $model->__quickUpdate();
            if($update_key["success"] !=  true){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => $update_key
                ];
                $this->db->rollback();
                goto end;
            }
            $company_api_gateway_usage_plan = CompanyApiGatewayUsagePlan::findFirstByCompanyId(ModuleModel::$company->getId());
            if(!$company_api_gateway_usage_plan){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => "can't not find usage plan for company"
                ];
                $this->db->rollback();
                goto end;
            }
            $api_gateway_usage_plan = $company_api_gateway_usage_plan->getApiGatewayUsagePlan();
            if(!$api_gateway_usage_plan){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => "usage plan doesn't existed"
                ];
                $this->db->rollback();
                goto end;
            }
            $update_usage_plan = RelodayApiGatewayHelper::__createUsagePlanKey($model->getAwsUuid(),  $api_gateway_usage_plan->getAwsUuid());
            if($update_usage_plan["success"] !=  true){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => $update_usage_plan
                ];
                $this->db->rollback();
                goto end;
            }
            $userAuthorKey = UserAuthorKey::__findApiKeyOfUser(ModuleModel::$user_profile->getId());
            $userAuthorKey["age"] = $userAuthorKey["days"] . " " . ConstantHelper::__translate("DAYS_TEXT", $lang);
            if($userAuthorKey["months"] > 0){
                $userAuthorKey["age"] .= " (".($userAuthorKey["years"] > 0 ? $userAuthorKey["years"] . "y " : "").($userAuthorKey["months"] - $userAuthorKey["years"] * 12)."m)";
            }
            $result = [
                'success' => true,
                'message' => 'GENERATE_KEY_SUCCESS_TEXT',
                'data' => $userAuthorKey
            ];
            $this->db->commit();
        } else {
            $this->db->rollback();
            $result = [
                'success' => false,
                'message' => 'GENERATE_KEY_FAIL_TEXT',
                'errorDetail' => $resultCreate
            ];
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function generateApiKeyV2Action()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $lang = ModuleModel::$language ? ModuleModel::$language : 'en';
        $hashKeyUnique = Helpers::__hash();
        $keySecret = Helpers::__generateSecret();
        $this->db->begin();
        $other_user_keys = UserAuthorKey::find([
            "conditions" => "user_profile_id = :user_id: and key_type = ".UserAuthorKey::TYPE_API_V2,
            "bind" => 
            [
                "user_id" => ModuleModel::$user_profile->getId()
            ]
            ]);
        if(count($other_user_keys) > 0){
            foreach($other_user_keys as $other_user_key){
                $aws_uuid = $other_user_key->getAwsUuid();
                if($aws_uuid != null && $aws_uuid != ""){
                    $delete_at_api_gateway = RelodayApiGatewayHelper::__deleteApiKey($aws_uuid);
                    // if (!$resultDelete['success']) {
                    //     $this->db->rollback();
                    //     $result = [
                    //         'success' => false,
                    //         'message' => 'DELETE_OLD_KEY_FAILED_TEXT',
                    //         'detail' => $delete_at_api_gateway
                    //     ];
                    //     goto end;
                    // }
                }
                $resultDelete = $other_user_key->__quickRemove();
                if (!$resultDelete['success']) {
                    $this->db->rollback();
                    $result = [
                        'success' => false,
                        'message' => 'DELETE_OLD_KEY_FAILED_TEXT',
                    ];
                    goto end;
                }
            }
        }
        $model = new UserAuthorKey();
        $model->setKeyType(UserAuthorKey::TYPE_API_V2);
        $model->setKeyValue($hashKeyUnique);
        $model->setKeySecret($keySecret);
        $model->setUserProfileId(ModuleModel::$user_profile->getId()); //User profile id
        $model->setIsActive(ModelHelper::YES);
        $model->setExpirationTime(time() + CacheHelper::__TIME_1_YEAR);
        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success'] == true) {
            $company_name = ModuleModel::$company->getName();
            if(getenv("ENVIR") == 'LOCAL'){
                $create_api_key_at_api_gateway = RelodayApiGatewayHelper::__createApiKey($model->getId() . " - ". $company_name." - V2 - LOCAL", $model->getKeyValue());
            } else {
                $create_api_key_at_api_gateway = RelodayApiGatewayHelper::__createApiKey($model->getId() . " - ". $company_name." - V2", $model->getKeyValue());
            }
            if($create_api_key_at_api_gateway["success"] !=  true){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => $create_api_key_at_api_gateway
                ];
                $this->db->rollback();
                goto end;
            }
            $keyId = $create_api_key_at_api_gateway["keyId"];
            $model->setAwsUuid($keyId);
            $update_key = $model->__quickUpdate();
            if($update_key["success"] !=  true){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => $update_key
                ];
                $this->db->rollback();
                goto end;
            }
            $company_api_gateway_usage_plan = CompanyApiGatewayUsagePlan::findFirstByCompanyId(ModuleModel::$company->getId());
            if(!$company_api_gateway_usage_plan){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => "can't not find usage plan for company"
                ];
                $this->db->rollback();
                goto end;
            }
            $api_gateway_usage_plan = $company_api_gateway_usage_plan->getApiGatewayUsagePlan();
            if(!$api_gateway_usage_plan){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => "usage plan doesn't existed"
                ];
                $this->db->rollback();
                goto end;
            }
            $update_usage_plan = RelodayApiGatewayHelper::__createUsagePlanKey($model->getAwsUuid(), $api_gateway_usage_plan->getAwsUuid());
            if($update_usage_plan["success"] !=  true){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => $update_usage_plan
                ];
                $this->db->rollback();
                goto end;
            }
            $update_cognito_attribute = ModuleModel::__adminUpdateUserAttributes(ModuleModel::$user_login->getEmail(), UserAuthorKey::COGNITO_ATTRIBUTE, $hashKeyUnique);
            if(!$update_cognito_attribute['success']){
                $result = [
                    'success' => false,
                    'message' => 'GENERATE_KEY_FAIL_TEXT',
                    'detail' => $update_cognito_attribute
                ];
                $this->db->rollback();
                goto end;
            }
            $userAuthorKey = UserAuthorKey::__findApiKeyV2OfUser(ModuleModel::$user_profile->getId());
            $userAuthorKey["age"] = $userAuthorKey["days"] . " " . ConstantHelper::__translate("DAYS_TEXT", $lang);
            if($userAuthorKey["months"] > 0){
                $userAuthorKey["age"] .= " (".($userAuthorKey["years"] > 0 ? $userAuthorKey["years"] . "y " : "").($userAuthorKey["months"] - $userAuthorKey["years"] * 12)."m)";
            }
            $result = [
                'success' => true,
                'message' => 'GENERATE_KEY_SUCCESS_TEXT',
                'data' =>  $userAuthorKey
            ];
            $this->db->commit();
        } else {
            $this->db->rollback();
            $result = [
                'success' => false,
                'message' => 'GENERATE_KEY_FAIL_TEXT',
                'errorDetail' => $resultCreate
            ];
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
