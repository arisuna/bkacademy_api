<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class BusinessOrderExt extends BusinessOrder
{

    use ModelTraits;

	/** status archived */
	const STATUS_ARCHIVED = -1;
	/** status active */
	const STATUS_ACTIVE = 1;
	/** status draft */
	const STATUS_DRAFT = 0;


    const STATUS_ORDERED = 1;
    const STATUS_IN_PROCESSING = 2;
    const STATUS_DELIVERED = 3;
    const STATUS_COMPLETED = 4;
    const STATUS_CANCELED = -1;


    const TYPE_BUY = 1;
    const TYPE_RENT = 2;
    const TYPE_AUCTION = 3;

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
            'value' => ModelHelper::YES
        ]));


        $this->belongsTo('delivery_address_id', '\SMXD\Application\Models\AddressExt', 'id', [
            'alias' => 'DeliveryAddress'
        ]);

        $this->belongsTo('shipping_address_id', '\SMXD\Application\Models\UserExt', 'id', [
            'alias' => 'ShippingAddress'
        ]);

        $this->belongsTo('billing_address_id', '\SMXD\Application\Models\CompanyExt', 'id', [
            'alias' => 'BillingAddress'
        ]);

        $this->belongsTo('creator_end_user_id', '\SMXD\Application\Models\UserExt', 'id', [
            'alias' => 'CreatorEndUser'
        ]);

        $this->belongsTo('target_company_id', '\SMXD\Application\Models\CompanyExt', 'id', [
            'alias' => 'TargetCompany'
        ]);

        $this->belongsTo('owner_staff_user_id', '\SMXD\Application\Models\UserExt', 'id', [
            'alias' => 'OwnerStaffUser'
        ]);
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
     * @return string
     */
    public function generateOrderNumber(){
        $todayDate = date('ymd');


        $shortKeyCompany = '';
        $company = $this->getTargetCompany();
        if ($company) {
            $shortKeyCompany = Helpers::getShortkeyFromWord($company->getName());
        }

        $nicknameEndUser = Helpers::getShortkeyFromWord($this->getCreatorEndUser()->getFirstname() . " " . $this->getCreatorEndUser()->getLastname());
        $randStr = strtoupper(substr(uniqid(sha1(time())),0,4));
        $number = $todayDate . $shortKeyCompany . $nicknameEndUser . $randStr;

        return $number;
    }
}
