<?php

namespace Reloday\Gms\Help;

use Reloday\Gms\Models\HrCompany;
use Reloday\Gms\Models\UserProfile;

class InvitationHelper
{
    /**
     * @param $email
     */
    public static function __canInviteHrToCreateFromEmail($email)
    {
        $userProfile = UserProfile::findFirstByWorkemail($email);
        if ($userProfile) {
            return false;
        }
        return true;
    }

    /**
     * @param $email
     * @return bool
     */
    public static function __canInviteHrToConnectFromEmail($email)
    {
        $userProfile = UserProfile::findFirstByWorkemail($email);
        if (!$userProfile) {
            return false;
        }
        if ($userProfile->isHr() == false) {
            return false;
        }

        $hrCompany = HrCompany::findFirstById($userProfile->getCompanyId());
        if (!$hrCompany || $hrCompany->isHr() == false) {
            return false;
        }

        if ($hrCompany->hasSubscription() == false) {
            return false;
        }
    }

}