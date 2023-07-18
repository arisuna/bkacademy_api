<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class CommunicationTopicRecipient extends \Reloday\Application\Models\CommunicationTopicRecipientExt
{
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('contact_id', 'Reloday\Gms\Models\Contact', 'id', [
            'alias' => 'Contact',
            'reusable' => true,
            'cache' => [
                'key' => 'CONTACT_' . $this->getContactId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
    }

    public static function createRecipient($message_id, $email, $type, $name = "")
    {
        $recipient = new CommunicationTopicRecipient();
        $recipient->setCommunicationTopicMessageId($message_id);
        $contact = Contact::findFirst(["conditions" => "company_id = :company_id: AND email = :email:",
            "bind" => [
                'company_id' => ModuleModel::$company->getId(),
                'email' => $email
            ]]);
        if (!$contact instanceof Contact) {
            $contact = new Contact();
            $contact->setEmail($email);
            $contact->setCreatorUserProfileId(ModuleModel::$user_profile->getId());
            $contact->setCompanyId(ModuleModel::$company->getId());
            $contact->setFirstname($name);
            $contact->setData();
            $resultContact = $contact->__quickCreate();
            if (!$resultContact["success"]) {
                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "contact" => $resultContact];
                goto end_of_function;
            }
            $contact = $resultContact["data"];
        }
        $recipient->setContactId($contact->getId());
        $recipient->setType($type);
        $createRecipient = $recipient->__quickCreate();
        if (!$createRecipient["success"]) {
            $return = ["success" => false,
                "message" => "DATA_CREATE_FAIL_TEXT",
                "contact" => $contact,
                "recipient" => $createRecipient];
            goto end_of_function;
        }
        $return = ["success" => true, "message" => "DATA_CREATE_SUCCESS_TEXT",
            "contact" => $contact,
            "recipient" => $recipient];
        end_of_function:
        return $return;
    }
}
