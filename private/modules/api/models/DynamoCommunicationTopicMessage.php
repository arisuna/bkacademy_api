<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\SMXDDynamoORM;
use SMXD\Application\Lib\Helpers;

class DynamoCommunicationTopicMessage extends \SMXD\Application\Models\DynamoCommunicationTopicMessageExt
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
            $communicationTopicMessage = SMXDDynamoORM::factory('\SMXD\Api\Models\DynamoCommunicationTopicMessage')
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
