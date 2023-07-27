<?php

namespace SMXD\Media\Models;

class Employee extends \SMXD\Application\Models\EmployeeExt {

    /** get avatar */
    public function getAvatar(){
        $avatar = MediaAttachment::__getLastAttachment( $this->getUuid() , "avatar");
        if( $avatar ){
            return $avatar;
        }else{
            return null;
        }
    }
}