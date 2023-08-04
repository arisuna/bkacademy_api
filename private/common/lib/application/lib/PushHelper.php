<?php

namespace SMXD\Application\Lib;

use Phalcon\Http\Client\Provider\Exception;
use Pusher\Pusher;
use Pusher\PusherException;

class PushHelper
{
    const EVENT_ADD_NEW_HOUSING_PROPOSITION = 'add_new_housing_proposition';
    const EVENT_RELOAD_HOUSING_PROPOSITION = 'reload_housing_proposition';
    const EVENT_GET_NEW_NOTIFICATION = 'get_new_notification';
    const EVENT_GET_NEW_REMINDER = 'get_new_reminders';
    const EVENT_GET_NEW_MESSAGES = 'get_new_messages';
    const EVENT_REFRESH_CACHE_ACL = 'refresh_acl';
    const EVENT_GENERATE_PDF = 'generate_pdf';
    const EVENT_RELOAD = 'reload';
    const EVENT_RELOAD_TASK_SERVICE = 'reload_task_service';
    const EVENT_SUCCESS_MESSAGE = 'send_success_message';

    protected $pushEngine;

    /**
     * PushHelper constructor
     */
    public function __construct()
    {
        $this->pushEngine = new Pusher(getenv('PUSHER_APP_KEY'), getenv('PUSHER_APP_SECRET'), getenv('PUSHER_APP_ID'), [
            'cluster' => getenv('PUSHER_APP_CLUSTER'),
            'encrypted' => true]);
    }

    /**
     * send message to SOCKET CLOUD
     * @param $channel
     * @param $event
     * @param array $data
     * @param bool $encrypt
     * @return array
     */
    public function sendMessage($channel, $event, $data = [])
    {
        if ($this->pushEngine) {
            try {
                $result = $this->pushEngine->trigger($channel, $event, $data);
                if (is_bool($result) && $result == true) {
                    return ['success' => true];
                } else {
                    return ['success' => false, 'detail' => $result];
                }
            } catch (PusherException $e) {
                return ['success' => false, 'detail' => $e->getMessage()];
            } catch (Exception $e) {
                return ['success' => false, 'detail' => $e->getMessage()];
            }
        }
        return ['success' => false, 'msg' => 'ENGINE_NOT_FOUND_TEXT'];
    }

    /**
     * send message to SOCKET CLOUD
     * @param $channel
     * @param $event
     * @param array $data
     * @param bool $encrypt
     * @return array
     */
    public function sendMultipleChannels($channels = [], $event, $data = [])
    {
        if ($this->pushEngine) {
            try {
                $result = $this->pushEngine->trigger($channels, $event, $data);
                if (is_bool($result) && $result == true) {
                    return ['success' => true];
                } else {
                    return ['success' => false, 'detail' => $result];
                }
            } catch (PusherException $e) {
                return ['success' => false, 'detail' => $e->getMessage()];
            } catch (Exception $e) {
                return ['success' => false, 'detail' => $e->getMessage()];
            }
        }
        return ['success' => false, 'msg' => 'ENGINE_NOT_FOUND_TEXT'];
    }

    /**
     * @param $objectUuid
     */
    public static function __sendPushReload($objectUuid){
        if( $objectUuid != ''){
            $pushHelpers = new self();
            return $pushHelpers->sendMessage( $objectUuid, self::EVENT_RELOAD);
        }
    }

    /**
     * @param $objectUuid
     */
    public static function __sendPushReloadTaskService($objectUuid){
        if( $objectUuid != ''){
            $pushHelpers = new self();
            return $pushHelpers->sendMessage( $objectUuid, self::EVENT_RELOAD_TASK_SERVICE);
        }
    }

    /**
     * @param $objectUuid
     */
    public static function __sendPushNewReminders($objectUuid){
        if( $objectUuid != ''){
            $pushHelpers = new self();
            return $pushHelpers->sendMessage( $objectUuid, self::EVENT_GET_NEW_REMINDER);
        }
    }

    /**
     * @param $objectUuid
     * @param $commentObject
     * @return array
     */
    public static function __sendNewChatMessage( $objectUuid, $commentObject ){
        if( $objectUuid != ''){
            $pushHelpers = new self();
            return $pushHelpers->sendMessage( $objectUuid, self::EVENT_GET_NEW_MESSAGES , $commentObject);
        }
    }

    /**
     * @param $channel
     * @param $data
     * @return array
     */
    public static function __sendReloadEvent($channel, $data)
    {
        $pushHelpers = new self();
        return $pushHelpers->sendMessage($channel, self::EVENT_RELOAD, $data);
    }


    /**
     * @param $channel
     * @param $data
     * @return array
     */
    public static function __sendEvent($channel, $eventName, $data)
    {
        $pushHelpers = new self();
        return $pushHelpers->sendMessage($channel, $eventName, $data);
    }
}
