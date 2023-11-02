<?php

namespace SMXD\Application\Models;

class BusinessOrder extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    protected $id;

    /**
     *
     * @var string
     */
    protected $uuid;

    /**
     *
     * @var string
     */
    protected $number;

    /**
     *
     * @var integer
     */
    protected $creator_end_user_id;

    /**
     *
     * @var integer
     */
    protected $target_company_id;

    /**
     *
     * @var integer
     */
    protected $product_id;

    /**
     *
     * @var integer
     */
    protected $product_sale_info_id;

    /**
     *
     * @var integer
     */
    protected $product_rent_info_id;

    /**
     *
     * @var integer
     */
    protected $product_auction_info_id;

    /**
     *
     * @var integer
     */
    protected $delivery_address_id;

    /**
     *
     * @var integer
     */
    protected $shipping_address_id;

    /**
     *
     * @var integer
     */
    protected $billing_address_id;

    /**
     *
     * @var integer
     */
    protected $owner_staff_user_id;

    /**
     *
     * @var integer
     */
    protected $type;

    /**
     *
     * @var double
     */
    protected $amount;

    /**
     *
     * @var integer
     */
    protected $quantity;

    /**
     *
     * @var string
     */
    protected $currency;

    /**
     *
     * @var integer
     */
    protected $status;

    /**
     *
     * @var integer
     */
    protected $is_deleted;

    /**
     *
     * @var string
     */
    protected $created_at;

    /**
     *
     * @var string
     */
    protected $updated_at;

    /**
     *
     * @var string
     */
    protected $deleted_at;

    /**
     * Method to set the value of field id
     *
     * @param integer $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Method to set the value of field uuid
     *
     * @param string $uuid
     * @return $this
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * Method to set the value of field number
     *
     * @param string $number
     * @return $this
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Method to set the value of field creator_end_user_id
     *
     * @param integer $creator_end_user_id
     * @return $this
     */
    public function setCreatorEndUserId($creator_end_user_id)
    {
        $this->creator_end_user_id = $creator_end_user_id;

        return $this;
    }

    /**
     * Method to set the value of field target_company_id
     *
     * @param integer $target_company_id
     * @return $this
     */
    public function setTargetCompanyId($target_company_id)
    {
        $this->target_company_id = $target_company_id;

        return $this;
    }

    /**
     * Method to set the value of field product_id
     *
     * @param integer $product_id
     * @return $this
     */
    public function setProductId($product_id)
    {
        $this->product_id = $product_id;

        return $this;
    }

    /**
     * Method to set the value of field product_sale_info_id
     *
     * @param integer $product_sale_info_id
     * @return $this
     */
    public function setProductSaleInfoId($product_sale_info_id)
    {
        $this->product_sale_info_id = $product_sale_info_id;

        return $this;
    }

    /**
     * Method to set the value of field product_rent_info_id
     *
     * @param integer $product_rent_info_id
     * @return $this
     */
    public function setProductRentInfoId($product_rent_info_id)
    {
        $this->product_rent_info_id = $product_rent_info_id;

        return $this;
    }

    /**
     * Method to set the value of field product_auction_info_id
     *
     * @param integer $product_auction_info_id
     * @return $this
     */
    public function setProductAuctionInfoId($product_auction_info_id)
    {
        $this->product_auction_info_id = $product_auction_info_id;

        return $this;
    }

    /**
     * Method to set the value of field delivery_address_id
     *
     * @param integer $delivery_address_id
     * @return $this
     */
    public function setDeliveryAddressId($delivery_address_id)
    {
        $this->delivery_address_id = $delivery_address_id;

        return $this;
    }

    /**
     * Method to set the value of field shipping_address_id
     *
     * @param integer $shipping_address_id
     * @return $this
     */
    public function setShippingAddressId($shipping_address_id)
    {
        $this->shipping_address_id = $shipping_address_id;

        return $this;
    }

    /**
     * Method to set the value of field billing_address_id
     *
     * @param integer $billing_address_id
     * @return $this
     */
    public function setBillingAddressId($billing_address_id)
    {
        $this->billing_address_id = $billing_address_id;

        return $this;
    }

    /**
     * Method to set the value of field owner_staff_user_id
     *
     * @param integer $owner_staff_user_id
     * @return $this
     */
    public function setOwnerStaffUserId($owner_staff_user_id)
    {
        $this->owner_staff_user_id = $owner_staff_user_id;

        return $this;
    }

    /**
     * Method to set the value of field type
     *
     * @param integer $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Method to set the value of field amount
     *
     * @param double $amount
     * @return $this
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Method to set the value of field quantity
     *
     * @param integer $quantity
     * @return $this
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Method to set the value of field currency
     *
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Method to set the value of field status
     *
     * @param integer $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Method to set the value of field is_deleted
     *
     * @param integer $is_deleted
     * @return $this
     */
    public function setIsDeleted($is_deleted)
    {
        $this->is_deleted = $is_deleted;

        return $this;
    }

    /**
     * Method to set the value of field created_at
     *
     * @param string $created_at
     * @return $this
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * Method to set the value of field updated_at
     *
     * @param string $updated_at
     * @return $this
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    /**
     * Method to set the value of field deleted_at
     *
     * @param string $deleted_at
     * @return $this
     */
    public function setDeletedAt($deleted_at)
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    /**
     * Returns the value of field id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the value of field uuid
     *
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Returns the value of field number
     *
     * @return string
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Returns the value of field creator_end_user_id
     *
     * @return integer
     */
    public function getCreatorEndUserId()
    {
        return $this->creator_end_user_id;
    }

    /**
     * Returns the value of field target_company_id
     *
     * @return integer
     */
    public function getTargetCompanyId()
    {
        return $this->target_company_id;
    }

    /**
     * Returns the value of field product_id
     *
     * @return integer
     */
    public function getProductId()
    {
        return $this->product_id;
    }

    /**
     * Returns the value of field product_sale_info_id
     *
     * @return integer
     */
    public function getProductSaleInfoId()
    {
        return $this->product_sale_info_id;
    }

    /**
     * Returns the value of field product_rent_info_id
     *
     * @return integer
     */
    public function getProductRentInfoId()
    {
        return $this->product_rent_info_id;
    }

    /**
     * Returns the value of field product_auction_info_id
     *
     * @return integer
     */
    public function getProductAuctionInfoId()
    {
        return $this->product_auction_info_id;
    }

    /**
     * Returns the value of field delivery_address_id
     *
     * @return integer
     */
    public function getDeliveryAddressId()
    {
        return $this->delivery_address_id;
    }

    /**
     * Returns the value of field shipping_address_id
     *
     * @return integer
     */
    public function getShippingAddressId()
    {
        return $this->shipping_address_id;
    }

    /**
     * Returns the value of field billing_address_id
     *
     * @return integer
     */
    public function getBillingAddressId()
    {
        return $this->billing_address_id;
    }

    /**
     * Returns the value of field owner_staff_user_id
     *
     * @return integer
     */
    public function getOwnerStaffUserId()
    {
        return $this->owner_staff_user_id;
    }

    /**
     * Returns the value of field type
     *
     * @return integer
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the value of field amount
     *
     * @return double
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Returns the value of field quantity
     *
     * @return integer
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Returns the value of field currency
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Returns the value of field status
     *
     * @return integer
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Returns the value of field is_deleted
     *
     * @return integer
     */
    public function getIsDeleted()
    {
        return $this->is_deleted;
    }

    /**
     * Returns the value of field created_at
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Returns the value of field updated_at
     *
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * Returns the value of field deleted_at
     *
     * @return string
     */
    public function getDeletedAt()
    {
        return $this->deleted_at;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("business_order");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return BusinessOrder[]|BusinessOrder|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return BusinessOrder|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'business_order';
    }

}
