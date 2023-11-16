<?php

namespace SMXD\Application\Models;

class BusinessOrderProduct extends \Phalcon\Mvc\Model
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
    protected $business_order_uuid;

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
    protected $quantity;

    /**
     *
     * @var integer
     */
    protected $product_auction_info_id;

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
     * Method to set the value of field business_order_uuid
     *
     * @param string $business_order_uuid
     * @return $this
     */
    public function setBusinessOrderUuid($business_order_uuid)
    {
        $this->business_order_uuid = $business_order_uuid;

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
     * Returns the value of field business_order_uuid
     *
     * @return string
     */
    public function getBusinessOrderUuid()
    {
        return $this->business_order_uuid;
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
     * Returns the value of field quantity
     *
     * @return integer
     */
    public function getQuantity()
    {
        return $this->quantity;
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
        $this->setSource("business_order_product");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return BusinessOrderProduct[]|BusinessOrderProduct|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return BusinessOrderProduct|\Phalcon\Mvc\Model\ResultInterface
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
        return 'business_order_product';
    }

}
