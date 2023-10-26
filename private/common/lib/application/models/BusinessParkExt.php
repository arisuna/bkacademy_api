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
class BusinessParkExt extends BusinessPark
{

    use ModelTraits;

	/** status archived */
	const STATUS_ARCHIVED = -1;
	/** status active */
	const STATUS_ACTIVE = 1;
	/** status draft */
	const STATUS_DRAFT = 0;
	
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

        $this->belongsTo('province_id', 'SMXD\Application\Models\ProvinceExt', 'id', [
            'alias' => 'Province',
        ]);

        $this->belongsTo('district_id', 'SMXD\Application\Models\DistrictExt', 'id', [
            'alias' => 'District',
        ]);

        $this->belongsTo('ward_id', 'SMXD\Application\Models\WardExt', 'id', [
            'alias' => 'Ward',
        ]);

        $this->belongsTo('business_zone_uuid', 'SMXD\Application\Models\BusinessZoneExt', 'id', [
            'alias' => 'BusinessZone',
        ]);

//        $this->addBehavior(new SoftDelete([
//            'field' => 'status',
//            'value' => self::STATUS_ARCHIVED
//        ]));
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


        $validator->add(
            ['name'],
            new Validation\Validator\Uniqueness([
                'model' => $this,
                'message' => 'NAME_SHOULD_BE_UNIQUE_TEXT',
            ])
        );

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