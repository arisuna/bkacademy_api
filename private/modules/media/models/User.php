<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Media\Models;

class User extends \SMXD\Application\Models\UserExt
{


    /** get avatar */
    public function getAvatar()
    {
        $avatar = MediaAttachment::__getLastAttachment($this->getUuid(), "avatar");
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }
}