<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class BusinessZoneExt extends BusinessZone
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

//        $this->addBehavior(new SoftDelete([
//            'field' => 'status',
//            'value' => self::STATUS_ARCHIVED
//        ]));
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
