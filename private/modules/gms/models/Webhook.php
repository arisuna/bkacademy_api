<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use phpDocumentor\Reflection\Types\This;
use Reloday\Application\Models\MediaAttachmentExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\WebhookHeader;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Webhook extends \Reloday\Application\Models\WebhookExt
{

	public function initialize(){
		parent::initialize();

        $this->belongsTo('webhook_configuration_id', 'Reloday\Gms\Models\WebhookConfiguration', 'id', [
            'alias' => 'WebhookConfiguration',
            'reusable' => false
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\WebhookHeader', 'webhook_id', ['alias' => 'WebhookHeaders']);
	}

    public function getCompany(){
        $company = Company::findFirstById($this->getId());
        return $company;
    }

    public function getHeaders(){
        $data = [];
        $data["User-Agent"] ="Relotalent";
        $headers = $this->getWebhookHeaders();
        if(count($headers) > 0){
            foreach($headers as $header){
                $data[$header->getName()] = $header->getValue();
            }
        }
        return $data;
    }

    /**
     * @return \Reloday\Gms\Models\Workflow[]
     */
    public static function getListOfMyCompany()
    {
        $webhooks = self::find([
            'conditions' => 'company_id = :company_id: AND is_deleted = 0 AND status != :unverified:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'unverified' => self::UNVERIFIED
            ]
        ]);
        $webhook_array = [];
        if ($webhooks->count() > 0) {
            foreach ($webhooks as $item) {
                $item_array = $item->toArray();
                $item_array['status_label'] = $item->getStatus() == self::ACTIVE ? "ACTIVE_TEXT" : (($item->getStatus() == self::INACTIVE && $item->getIsVerified() ==  Helpers::YES) ? "INACTIVE_TEXT" : "NOT_VERIFIED_TEXT");
                $webhook_configuration = $item->getWebhookConfiguration();
                if($webhook_configuration){
                    $object_type = $webhook_configuration->getObjectTypeLabel();
                    $action = $webhook_configuration->getActionLabel();
                    $item_array['object_type'] = $object_type;
                    $item_array['action'] = $action;
                    $webhook_array[] = $item_array;
                }
            }
        }
        return ["success" => true, "data" => $webhook_array, "count" => count($webhook_array)];
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        return $this->getCompanyId() == ModuleModel::$company->getId() && $this->getIsDeleted() == 0;
    }

}
