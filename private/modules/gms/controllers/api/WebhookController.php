<?php
/**
 * Created by PhpStorm.
 * User: nguyenthuy
 * Date: 7/11/18
 * Time: 5:41 PM
 */

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\WebhookHeader;
use Reloday\Gms\Models\WebhookConfiguration;
use Reloday\Gms\Models\Webhook;
use Reloday\Application\Lib\RelodayQueue;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/webhook")
 */
class WebhookController extends BaseController
{

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('index', $this->router->getControllerName());
        $result = Webhook::getListOfMyCompany();

        $this->response->setJsonContent($result);
        $this->response->send();

    }

    /**
     * get webhook configurations
     * @method GET
     * @route /webhook/getConfigurations
     */
    public function getConfigurationsAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET']);
        $this->checkAclIndex();

        $webhook_configurations = WebhookConfiguration::find([
            "conditions" => "is_gms = 1"
        ]);

        $actions = [];
        $object_types = [];

        foreach($webhook_configurations as $webhook_configuration){
            $actions[$webhook_configuration->getObjectTypeLabel()][] = $webhook_configuration->getActionLabel();
            if(!in_array($webhook_configuration->getObjectTypeLabel(),$object_types ))
                $object_types[] = $webhook_configuration->getObjectTypeLabel();
        }

        $return = [
            "success" => true,
            "actions" => $actions,
            "object_types" => $object_types
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * verify url
     * @method GET
     * @route /webhook/verifyURL
     */
    public function verifyURLAction()
    {
        $this->view->disable();
        $this->checkAjax(['POST']);
        $this->checkAclIndex();

        $url = Helpers::__getRequestValue('url');
        $headers = Helpers::__getRequestValuesArray()['headers'];
        $action = Helpers::__getRequestValue('action');
        $object_type = Helpers::__getRequestValue('object_type');
        $verifyCode = mt_rand(100000,999999);
        $uuid = Helpers::__uuid();

        $webhook_configuration = WebhookConfiguration::findFirst([
            "conditions" => "object_type_label = :object_type: and action_label = :action: and is_gms = 1",
            "bind" => [
                "object_type" => $object_type,
                "action" => $action
            ]
            ]);
        if(!$webhook_configuration){
            $return = [
                "success" => false,
                "message" => "NO_WEBHOOK_FOR_THIS_SETUP_TEXT"
            ];
            goto end_of_function;
        }
        
        $check_existed_webhook = Webhook::findFirst([
            "conditions" => "webhook_configuration_id = :webhook_configuration_id: and status != -1 and is_deleted = 0 and company_id = :company_id:",
            "bind" => [
                "webhook_configuration_id" => $webhook_configuration->getId(),
                "company_id" => ModuleModel::$company->getId()
            ]
            ]);
        if($check_existed_webhook){
            $return = [
                "success" => false,
                "message" => "WEBHOOK_EXISTED_TEXT",
                "data" => $check_existed_webhook
            ];
            goto end_of_function;
        }

        $this->db->begin();

        $webhook = new Webhook();
        $webhook->setUuid($uuid);
        $webhook->setCompanyId(ModuleModel::$company->getId());
        $webhook->setUrl($url);
        $webhook->setStatus(Webhook::INACTIVE);
        $webhook->setVerificationCode($verifyCode);
        $webhook->setWebhookConfigurationId($webhook_configuration->getId());
        $webhook->setFirstActive(time());
        $webhook->setIsVerified(Helpers::NO);
        $create_webhook = $webhook->__quickCreate();

        if(!$create_webhook["success"]){
            $return = $create_webhook;
            $this->db->rollback();
            goto end_of_function;
        }
        $header_array = [];
        $header_array["User-Agent"] ="Relotalent";
        if(count($headers) > 0){
            foreach($headers as $header){
                if(isset($header['name']) && isset($header['value'])){
                    $webhook_header = new WebhookHeader();
                    $webhook_header->setName($header['name']);
                    $webhook_header->setValue($header['value']);
                    $webhook_header->setUuid(Helpers::__uuid());
                    $webhook_header->setWebhookId($webhook->getId());
                    $create_webhook_header = $webhook_header->__quickCreate();

                    if(!$create_webhook_header["success"]){
                        $return = $create_webhook_header;
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $header_array[$header['name']] = $header['value'];
                }
            }
        }


        $queue = new RelodayQueue(getenv('QUEUE_WEBHOOK'));
                        
        $return = $queue->addQueue([
            'action' => 'sendWebhook',
            'params' => [
                'log_uuid' => Helpers::__uuid(),
                'data' => [
                    "verifyCode" => $verifyCode
                ],
                'webhook_uuid' => $uuid,
                'url' => $url,
                'company_uuid' => ModuleModel::$company->getUuid(),
                'headers' => $header_array
            ],
        ], ModuleModel::$company->getId());
        if(!$return["success"]){
            $this->db->rollback();
            goto end_of_function;
        }
        $this->db->commit();
        $return['data'] = $webhook->toArray();
        unset($return['data']['verification_code']);
        // unset($return['params']);
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $uuid = Helpers::__getRequestValue('uuid');
        $verification_code = Helpers::__getRequestValue('verification_code');
        $webhook = Webhook::findFirstByUuid($uuid);
        if(!$webhook || !$webhook->belongsToGms()){
            $return = [
                "success" => false,
                "message" => "CREATE_WEBHOOK_FAILED_TEXT"
            ];
            goto end_of_function;
        }
        if($verification_code != $webhook->getVerificationCode()){
            $return = [
                "success" => false,
                "message" => "WEBHOOK_VERIFICATION_CODE_NOT_MATCH_TEXT"
            ];
            goto end_of_function;
        }
        $webhook->setStatus(Webhook::ACTIVE);
        $webhook->setIsVerified(Helpers::YES);
        $webhook->setLastActive(null);
        $webhook->setFirstActive(time());
        $return = $webhook->__quickUpdate();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * load detail of webhook
     * @method GET
     * @route /webhook/detail
     * @param int $id
     */
    public function detailAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax(['GET']);
        $this->checkAclIndex();

        $uuid = $uuid ? $uuid : $this->request->get('uuid');
        $webhook = Webhook::findFirstByUuid($uuid);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if ($webhook && $webhook->belongsToGms()) {
            $webhookArray = $webhook->toArray();
            $webhook_configuration = $webhook->getWebhookConfiguration();
            $webhookArray['object_type'] = $webhook_configuration->getObjectTypeLabel();
            unset($webhookArray['verification_code']);
            $webhookArray['action'] = $webhook_configuration->getActionLabel();
            $webhookArray['headers'] = [];
            $headers = $webhook->getWebhookHeaders();
            if(count($headers) > 0){
                foreach($headers as $header){
                    $webhookArray['headers'][] = [
                        "name" => $header->getName(),
                        "value" => $header->getValue()
                    ];
                }
            }

            $return = [
                'success' => true,
                'message' => 'LOAD_DETAIL_SUCCESS_TEXT',
                'data' => $webhookArray
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function deleteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'WEBHOOK_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $webhook = Webhook::findFirstByUuid($uuid);
            if ($webhook instanceof Webhook && $webhook->belongsToGms()) {
                $return = $webhook->__quickRemove();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Save service action
     */
    public function updateAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $webhook = Webhook::findFirstByUuid($uuid);
            if ($webhook instanceof Webhook && $webhook->belongsToGms()) {
                $this->db->begin();
                $headers = Helpers::__getRequestValuesArray()['headers'];
                $header_array = [];
                $header_array["User-Agent"] ="Relotalent";
                $old_headers = $webhook->getWebhookHeaders();
                
                if(count($headers) > 0){
                    foreach($headers as $header){
                        if(isset($header['name']) && isset($header['value'])){
                            $webhook_header = WebhookHeader::findFirst([
                                "conditions" => "webhook_id = :webhook_id: and name = :name:",
                                "bind" => [
                                    "webhook_id" => $webhook->getId(),
                                    "name" => $header['name']
                                ]
                                ]);
                            if(!$webhook_header){
                                $create_header = true;
                                $webhook_header = new WebhookHeader();
                                $webhook_header->setUuid(Helpers::__uuid());
                                $webhook_header->setWebhookId($webhook->getId());
                                $webhook_header->setName($header['name']);
                            }
                            $webhook_header->setValue($header['value']);
                            
                            if($create_header){
                                $create_webhook_header = $webhook_header->__quickCreate();
                            } else {
                                $create_webhook_header = $webhook_header->__quickUpdate();
                            }
                            if(!$create_webhook_header["success"]){
                                $return = $create_webhook_header;
                                $this->db->rollback();
                                goto end_of_function;
                            }
                            $header_array[$header['name']] = $header['value'];
                        }
                    }
                }
                if(count($old_headers) > 0){
                    foreach($old_headers as $old_header){
                        if(!in_array($old_header->getName(), $header_array)){
                            $remove_webhook_header = $old_header->__quickRemove();
                            if(!$remove_webhook_header["success"]){
                                $return = $remove_webhook_header;
                                $this->db->rollback();
                                goto end_of_function;
                            }
                        }
                    }
                }
                $url = Helpers::__getRequestValue('url');
                if($url != $webhook->getUrl() || $webhook->getIsVerified() == Helpers::NO){
                    $verifyCode = mt_rand(100000,999999);
                    $webhook->setUrl($url);
                    $webhook->setStatus(Webhook::INACTIVE);
                    $webhook->setIsVerified(Helpers::NO);
                    $webhook->setLastActive(time());
                    $webhook->setVerificationCode($verifyCode);
                    $update = $webhook->__quickUpdate();
                    if(!$update["success"]){
                        $return = $update;
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $queue = new RelodayQueue(getenv('QUEUE_WEBHOOK'));
                        
                    $return = $queue->addQueue([
                        'action' => 'sendWebhook',
                        'params' => [
                            'log_uuid' => Helpers::__uuid(),
                            'data' => [
                                "verifyCode" => $verifyCode
                            ],
                            'webhook_uuid' => $uuid,
                            'url' => $url,
                            'company_uuid' => ModuleModel::$company->getUuid(),
                            'headers' => $header_array
                        ],
                    ], ModuleModel::$company->getId());
                    if(!$return["success"]){
                        $this->db->rollback();
                        goto end_of_function;
                    }
                    $return["needUpdateConfirmationCode"] = true;
                    // unset($return['params']);
                } else {
                    $return["needUpdateConfirmationCode"] = false;
                }
                $this->db->commit();
                $return["success"] = true;
                $return["message"] = "DATA_SAVE_SUCCESS_TEXT";
                $return['data'] = $webhook->toArray();
                unset($return['data']['verification_code']);
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

     /**
     * @return mixed
     */
    public function verifyCodeAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit();

        $uuid = Helpers::__getRequestValue('uuid');
        $verification_code = Helpers::__getRequestValue('verification_code');
        $next_status = Helpers::__getRequestValue('next_status');
        $webhook = Webhook::findFirstByUuid($uuid);
        if(!$webhook || !$webhook->belongsToGms()){
            $return = [
                "success" => false,
                "message" => "DATA_NOT_FOUND_TEXT"
            ];
            goto end_of_function;
        }
        if($verification_code != $webhook->getVerificationCode()){
            $return = [
                "success" => false,
                "message" => "WEBHOOK_VERIFICATION_CODE_NOT_MATCH_TEXT"
            ];
            goto end_of_function;
        }
        $webhook->setIsVerified(Helpers::YES);
        $webhook->setStatus(Webhook::ACTIVE);
        if($next_status === Webhook::INACTIVE){
            $webhook->setStatus(Webhook::INACTIVE);
        }
        $webhook->setLastActive(null);
        $return = $webhook->__quickUpdate();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function activeAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclEdit();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $webhook = Webhook::findFirstByUuid($uuid);
        if ($webhook && $webhook->belongsToGms() == true && $webhook->getIsVerified(Helpers::YES)) {

            $webhook->setStatus(Webhook::ACTIVE);
            $result = $webhook->__quickUpdate();
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function deactiveAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclEdit();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $webhook = Webhook::findFirstByUuid($uuid);
        if ($webhook && $webhook->belongsToGms() == true) {

            $webhook->setStatus(Webhook::INACTIVE);
            $result = $webhook->__quickUpdate();
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
