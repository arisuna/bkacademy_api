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

class UserGroupExt extends UserGroup
{
    use ModelTraits;

    const GROUP_ADMIN = 1;

    const GROUP_CRM_ADMIN = 2;

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
}