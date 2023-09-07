<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Validation;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class ProductFieldExt extends ProductField
{

    use ModelTraits;

	/** status archived */
	const STATUS_ARCHIVED = -1;
	/** status active */
	const STATUS_ACTIVE = 1;
	const STATUS_INACTIVE = 0;
	/** status draft */
	const STATUS_DRAFT = 0;

    const LIMIT_PER_PAGE = 50;

    const IS_DELETE_YES = 1;
    const IS_DELETE_NO = 0;

    const TYPE_TEXT = 0;
    const TYPE_NUMERIC = 1;
    const TYPE_ATTRIBUTE = 2;
	
	/**
	 * [initialize description]
	 * @return [type] [description]
	 */
	public function initialize(){
		parent::initialize();
		$this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable(
            array(
                'beforeValidationOnCreate' => array(
                    'field' => array(
                        'created_at', 'updated_at'
                    ),
                    'format' => 'Y-m-d H:i:s'
                ),
                'beforeValidationOnUpdate' => array(
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                )
            )
        ));

       $this->addBehavior(new SoftDelete([
           'field' => 'is_deleted',
           'value' => self::IS_DELETE_YES
       ]));

       $this->belongsTo('attribute_id', '\SMXD\Application\Models\AttributesExt', 'id', [
           'alias' => 'Attribute'
       ]);
	}


    /**
     * @return bool
     */
    public function validation()
    {
        $validator = new Validation();
        $validator->add(
            'name',
            new Validation\Validator\PresenceOf([
                'model' => $this,
                'message' => 'NAME_REQUIRED_TEXT'
            ])
        );

        

        // $validator->add(
        //     ['name'],
        //     new Validation\Validator\Uniqueness([
        //         'model' => $this,
        //         'message' => 'NAME_SHOULD_BE_UNIQUE_TEXT',
        //     ])
        // );

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
