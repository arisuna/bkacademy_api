<?php

namespace Reloday\Gms\Help;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\ApiCallHelpers;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Gms\Models\Comment;
use Reloday\Gms\Models\ModuleModel;

class NotificationServiceHelper
{
    /**
     * @param String $action
     * @param $object
     * @param String $typeObject
     * @param array $params
     */
    public static function __addNotification($object, String $typeObject, String $action, $params = [], $sendMail = true)
    {
        $customData = self::__parseCustomData($object, $typeObject, $action, $params);
        $customData['isSendMail'] = $sendMail;
        $apiResults = ApiCallHelpers::__postJson("gms/notification/addNotification", ['token' => ModuleModel::__getAccessToken(), 'refresh-token' => ModuleModel::__getRefreshToken(), 'language' => ModuleModel::$language], $customData);
        $apiResults['action'] = $action;
        $apiResults['$customData'] = $customData;
        return $apiResults;
    }

    /**
     * @param $targetCompanyId
     * @param $object
     * @param String $typeObject
     * @param String $action
     * @param array $params
     */
    public static function __addNotificationToAccount($targetCompanyId, $object, String $typeObject, String $action, $params = [])
    {
        $customData = self::__parseCustomData($object, $typeObject, $action, $params);
        $customData['target_company_id'] = $targetCompanyId;
        $apiResults = ApiCallHelpers::__postJson("gms/notification/addNotificationToAccount", [
            'token' => ModuleModel::__getAccessToken(),
            'refresh-token' => ModuleModel::__getRefreshToken(),
            'language' => ModuleModel::$language
        ], $customData);
        $apiResults['action'] = $action;
        return $apiResults;
    }

    /**
     * @param $object
     * @param String $typeObject
     * @param String $action
     * @param array $params
     * @return array
     */
    public static function __parseCustomData($object, String $typeObject, String $action, $params = []){
        if (is_object($object) && $object instanceof Comment) {
            $typeObject = HistoryModel::TYPE_COMMENT;
        }

        $customData = [
            'uuid' => is_array($object) && isset($object['uuid']) ? $object['uuid'] : (method_exists($object, 'getUuid') ? $object->getUuid() : (property_exists($object, 'uuid') ? $object->uuid : false)),
            'type' => $typeObject,
            'action' => $action,
        ];

        if (isset($params['target_user'])) {
            $customData['target_user'] = $params['target_user'];
        }

        if (isset($params['target_user_uuid'])) {
            $customData['target_user_uuid'] = $params['target_user_uuid'];
        }

        if (isset($params['svp_name'])) {
            $customData['svp_name'] = $params['svp_name'];
        }

        if (isset($params['subtask_name'])) {
            $customData['subtask_name'] = $params['subtask_name'];
        }

        if (isset($params['comment'])) {
            $customData['comment'] = $params['comment'];
        }

        if (isset($params['task'])) {
            $customData['task'] = $params['task'];
        }

        if (isset($params['property'])) {
            $customData['property'] = $params['property'];
        }

        if (isset($params['questionnaire'])) {
            $customData['questionnaire'] = $params['questionnaire'];
        }

        if (isset($params['workflow_name'])) {
            $customData['workflow_name'] = $params['workflow_name'];
        }

        if (isset($params['file_name'])) {
            $customData['file_name'] = $params['file_name'];
            $customData['filename'] = $params['file_name'];
        }

        /** exceptional case for comments */
        if ($typeObject == HistoryModel::TYPE_COMMENT) {
            $customData['uuid'] = is_array($object) && isset($object['object_uuid']) ? $object['object_uuid'] : (method_exists($object, 'getObjectUuid') ? $object->getObjectUuid() : (property_exists($object, 'object_uuid') ? $object->object_uuid : false));
            $customData['commentObject'] = $object;
            $customData['comment'] = is_array($object) && isset($object['message']) ? $object['message'] : (method_exists($object, 'getMessage') ? $object->getMessage() : (property_exists($object, 'message') ? $object->message : false));
        }

        $customData['creator_company_id'] = ModuleModel::$company->getId();

        return $customData;
    }

    /**
     * @param $object
     * @param String $typeObject
     * @param String $action
     * @param array $params
     */
    public static function __addNotificationForUser($object, String $typeObject, String $action, $params = [])
    {
        $customData = self::__parseCustomData($object, $typeObject, $action, $params);
        $apiResults = ApiCallHelpers::__postJson("gms/notification/addNotificationForUser", [
            'token' => ModuleModel::__getAccessToken(),
            'refresh-token' => ModuleModel::__getRefreshToken(),
            'language' => ModuleModel::$language
        ], $customData);
        $apiResults['action'] = $action;
        return $apiResults;
    }

    /**
     * @param $object
     * @param String $typeObject
     * @param String $action
     * @param array $params
     */
    public static function __sendPushNotificationForUser($object, String $typeObject, String $action, $params = [])
    {
        $customData = self::__parseCustomData($object, $typeObject, $action, $params);
        $apiResults = ApiCallHelpers::__postJson("gms/notification/sendPushNotificationForUser", [
            'token' => ModuleModel::__getAccessToken(),
            'refresh-token' => ModuleModel::__getRefreshToken(),
            'language' => ModuleModel::$language
        ], $customData);
        $apiResults['action'] = $action;
        return $apiResults;
    }


    /**
     * @param $object
     * @param String $typeObject
     * @param String $action
     * @param array $params
     */
    public static function __addNotificationForTagUser($object, String $typeObject, String $action, $params = [])
    {
        $customData = self::__parseCustomData($object, $typeObject, $action, $params);
        $apiResults = ApiCallHelpers::__postJson("gms/notification/addNotificationForTagUser", [
            'token' => ModuleModel::__getAccessToken(),
            'refresh-token' => ModuleModel::__getRefreshToken(),
            'language' => ModuleModel::$language
        ], $customData);
        $apiResults['action'] = $action;
        return $apiResults;
    }


    /**
     * @param $object
     * @param String $employeeUuid
     * @param String $typeObject
     * @param String $action
     * @param $params
     * @return array|false[]
     */
    public static function __assigneeAddNotification($object, String $employeeUuid, String $typeObject, String $action, $params = [])
    {
        $customData = self::__parseCustomData($object, $typeObject, $action, $params);
        $customData['employee_uuid'] = $employeeUuid;
        $apiResults = ApiCallHelpers::__postJson("gms/notification/assigneeAddNotification", [
            'token' => ModuleModel::__getAccessToken(),
            'refresh-token' => ModuleModel::__getRefreshToken(),
            'language' => ModuleModel::$language
        ], $customData);
        $apiResults['action'] = $action;
        return $apiResults;
    }
}
