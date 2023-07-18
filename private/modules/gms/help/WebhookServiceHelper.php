<?php

namespace Reloday\Gms\Help;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\ApiCallHelpers;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Gms\Models\Comment;
use Reloday\Gms\Models\ModuleModel;

class WebhookServiceHelper
{


    /**
     * @param $object
     * @param String $typeObject
     * @param String $action
     * @param array $params
     */
    public static function __sendWebhook($object, String $controller, String $action)
    {
        $customData = [
            "object" => $object->toArray(),
            "controller" => $controller,
            "action" => $action
        ];
        $apiResults = ApiCallHelpers::__postJson("gms/webhook/sendWebhook", [
            'token' => ModuleModel::__getAccessToken(),
            'language' => ModuleModel::$language
        ], $customData);
        return $apiResults;
    }
}