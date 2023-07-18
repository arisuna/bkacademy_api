<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 2/12/20
 * Time: 2:32 PM
 */

namespace Reloday\Gms\Models;


use Reloday\Application\Models\DynamoReminderUserDeclinedExt;
use Phalcon\Di;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Gms\Models\UserProfile;

class DynamoReminderUserDeclined extends DynamoReminderUserDeclinedExt
{
    public function getUserProfile()
    {
        return UserProfile::findFirstByUuid($this->getUud());
    }

    public function getReminderConfig()
    {
        $reminderConfig = RelodayDynamoORM::factory('\Reloday\Gms\Models\DynamoReminderConfig')
            ->findOne($this->getReminderConfigUuid());
        if($reminderConfig instanceof DynamoReminderConfig){
            return $reminderConfig;
        }
        return false;
    }
}
