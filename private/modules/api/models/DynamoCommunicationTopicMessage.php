<?php

namespace Reloday\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\Helpers;

class DynamoCommunicationTopicMessage extends \Reloday\Application\Models\DynamoCommunicationTopicMessageExt
{

    public function getCommunicationTopic()
    {
        return CommunicationTopic::findFirstByUuid($this->getCommunicationTopicUuid());
    }

    public function getEmail(){
        $userCommunicationEmail = UserCommunicationEmail::findFirstByImapEmail($this->getSenderEmail());
        return $userCommunicationEmail;
    }

    public static function findFirstByUuid($uuid){
        try{
            $communicationTopicMessage = RelodayDynamoORM::factory('\Reloday\Api\Models\DynamoCommunicationTopicMessage')
                ->findOne($uuid);
            return $communicationTopicMessage;
        } catch(\Exception $e){
            var_dump($e->getMessage());
            return $return = [
                'success' => false,
                'message' => 'DATA_REMOVE_FAILED_TEXT',
                'detail' => $e->getMessage()
            ];
        }
    }
}
