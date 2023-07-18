<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\OutlookHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Mpdf\Cache;
use Reloday\Application\Lib\CacheHelper;

class UserCommunicationEmail extends \Reloday\Application\Models\UserCommunicationEmailExt
{

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	const TLS = 2;
	const STARTTLS = 3;
	const SSL = 1;
	const NONE = 0;

	const GOOGLE = 1;
	const OUTLOOK = 2;
	const OTHER = 3;

	public function initialize(){
		parent::initialize();
	}

    /**
     * load all allowance type associated with an GMS with contract
     * @return [type] [description]
     */
    public static function loadByUserProfile()
    {
        $emails = self::find([
            "conditions" => "user_profile_id = :user_profile_id:",
            "bind" => [
                'user_profile_id' => ModuleModel::$user_profile->getId()
            ]
        ]);
        $returnArray = [];
        if (count($emails)) {
            foreach ($emails as $email) {
                $returnArray[$email->getId()] = $email->toArray();
                $returnArray[$email->getId()]["provider"] = $email->getType() == 1 ? "google" : ( $email->getType() == 2 ? "office365" : "another");
                $returnArray[$email->getId()]["name"] = $email->getType() == 1 ? "GOOGLE_TEXT" : ( $email->getType() == 2 ? "OFFICE_365_TEXT" : "OTHER_EMAIL_PROVIDER_TEXT");
            }
        }
        return array_values($returnArray);
    }

    /**
     * load all allowance type associated with an GMS with contract
     * @return [type] [description]
     */
    public static function getMailsByUserProfile()
    {
        $emails = self::find([
            "conditions" => "user_profile_id = :user_profile_id: AND status <> :status_archived:",
            "bind" => [
                'user_profile_id' => ModuleModel::$user_profile->getId(),
                'status_archived' => self::STATUS_ARCHIVED,
            ]
        ]);
        $returnArray = [];
        if (count($emails)) {
            foreach ($emails as $email) {
                $returnArray[$email->getId()] = $email->toArray();
            }
        }
        return array_values($returnArray);
    }

    /**
     * load all allowance type associated with an GMS with contract
     * @return [type] [description]
     */
    public static function checkIfTokenExpired()
    {
        $emails = self::find([
            "conditions" => "user_profile_id = :user_profile_id: AND status <> :status_archived: and type = :outlook: and is_expired = 1",
            "bind" => [
                'user_profile_id' => ModuleModel::$user_profile->getId(),
                'status_archived' => self::STATUS_ARCHIVED,
                "outlook" => self::OUTLOOK
            ]
        ]);
        $returnArray = [];
        if (count($emails)) {
            foreach ($emails as $email) {
                $returnArray[$email->getId()] = $email->toArray();
            }
        }
        return array_values($returnArray);
    }



    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if ($this->getUserProfileId() == ModuleModel::$user_profile->getId()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function belongsToMe(){
        return $this->getUserProfileId() == ModuleModel::$user_profile->getId();
    }
}
