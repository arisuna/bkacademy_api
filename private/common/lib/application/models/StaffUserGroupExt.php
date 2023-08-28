<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Security;
use Phalcon\Validation;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class StaffUserGroupExt extends StaffUserGroup
{
    use ModelTraits;

    const GROUP_ADMIN = 1;

    const GROUP_CRM_ADMIN = 2;
    const GROUP_CRM_SALE = 3;
    const GROUP_MEMBER = 4;

    const LEVEL_ALL = 3;
    const LEVEL_OFFICE = 2;
    const LEVEL_ASSIGNED = 1;

    const LEVEL_LABELS = [
        self::LEVEL_ASSIGNED => 'ASSIGNED_ONLY_TEXT',
        self::LEVEL_OFFICE => 'BUSINESS_ZONE_TEXT',
        self::LEVEL_ALL => 'ALL_TEXT'
    ];

    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
    }

    public function beforeValidation()
    {
        $validator = new Validation();
        $validator->add('name', new Validation\Validator\PresenceOf([
            'model' => $this,
            'message' => 'GROUP_NAME_REQUIRED_TEXT'
        ]));

        $validator->add('name', new Validation\Validator\Uniqueness([
            'model' => $this,
            'message' => 'GROUP_NAME_MUST_UNIQUE_TEXT'
        ]));

        return $this->validate($validator);
    }
    
    /**
     * @param array $custom
     */
    public function setData( $custom = []){

         ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return $this->getId() == self::GROUP_ADMIN;
    }

    /**
     * @return bool
     */
    public function isCrmAdmin()
    {
        return $this->getId() == self::GROUP_CRM_ADMIN;
    }
}