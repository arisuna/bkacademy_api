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

class ExamTypeExt extends ExamType
{
    use ModelTraits;

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
            'message' => '_NAME_REQUIRED_TEXT'
        ]));

        $validator->add('name', new Validation\Validator\Uniqueness([
            'model' => $this,
            'message' => 'NAME_MUST_UNIQUE_TEXT'
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
}