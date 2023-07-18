<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\DynamoDb\ORM\DynamoObjectMapExt;
use Reloday\Application\Lib\FCMHelper;
use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\UserProfileDeviceToken;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class NotificationFcmController extends ModuleApiController
{
    public function setTokenAction($userProfileUuid = '')
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $token = Helpers::__getRequestValue('token');
        $platform = Helpers::__getRequestValue('platform');
        $deviceId = Helpers::__getRequestValue('deviceId');

        if (!$token || !$platform) {
            goto end_of_function;
        }
        
        $userProfile = UserProfile::findFirstByUuid($userProfileUuid);
        if (!$userProfile){
            goto end_of_function;
        }
        $userProfileUuid = $userProfile->getUuid();

        $this->db->begin();

        $userDeviceToken = UserProfileDeviceToken::findFirst([
            'conditions' => 'platform = :platform: and device_id = :device_id: and user_profile_uuid = :user_profile_uuid:',
            'bind' => [
                'platform' => $platform,
                'device_id' => $deviceId,
                'user_profile_uuid' => $userProfileUuid,
            ]
        ]);

        if ($userDeviceToken) {
            $userDeviceToken->setToken($token);
        } else {
            $userDeviceToken = new UserProfileDeviceToken();
            $userDeviceToken->setUuid(Helpers::__uuid());
            $userDeviceToken->setUserProfileUuid($userProfileUuid);
            $userDeviceToken->setPlatform($platform);
            $userDeviceToken->setToken($token);
            $userDeviceToken->setDeviceId($deviceId);
        }

        $res = $userDeviceToken->__quickSave();

        if (!$res['success']) {
            $return = $res;
            $this->db->rollback();
            $return['message'] = 'TOKEN_SAVE_FAILED_TEXT';
            goto end_of_function;
        }

        $return = $res;
        $this->db->commit();

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $userProfileUuid
     * @return void
     */
    public function removeDeviceTokenAction($userProfileUuid = ''){
        $this->view->disable();
        $this->checkAjax('PUT');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $platform = Helpers::__getRequestValue('platform');
        $deviceId = Helpers::__getRequestValue('deviceId');

        $userProfile = UserProfile::findFirstByUuid($userProfileUuid);
        if (!$userProfile){
            goto end_of_function;
        }
        $userProfileUuid = $userProfile->getUuid();
        $userDeviceToken = UserProfileDeviceToken::findFirst([
            'conditions' => 'device_id = :device_id: and user_profile_uuid = :user_profile_uuid:',
            'bind' => [
                'device_id' => $deviceId,
                'user_profile_uuid' => $userProfileUuid,
            ]
        ]);

        if (!$userDeviceToken) {
            $return = ['success' => true, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $return = $userDeviceToken->__quickRemove();


        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    public function clearBadgeIosAction($userProfileUuid = ''){
        $this->view->disable();
        $this->checkAjax('GET');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $userProfile = UserProfile::findFirstByUuid($userProfileUuid);
        if (!$userProfile){
            goto end_of_function;
        }
        $userProfileUuid = $userProfile->getUuid();
        $relodayObjectMap = DynamoObjectMapExt::findFirstByUuid($userProfileUuid);
        if($relodayObjectMap){
            $relodayObjectMap->setBadgeCount(0);;
            $result =$relodayObjectMap->__quickUpdate();

            if($result['success']){
                FCMHelper::__clearBadgeForUserGms($userProfileUuid);
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }
}
