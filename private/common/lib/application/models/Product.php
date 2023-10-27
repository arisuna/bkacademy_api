<?php

namespace SMXD\Application\Models;

class Product extends \Phalcon\Mvc\Model
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
    protected $name;

    /**
     *
     * @var string
     */
    protected $product_fields;

    /**
     *
     * @var string
     */
    protected $vehicle_id;

    /**
     *
     * @var integer
     */
    protected $usage;

    /**
     *
     * @var integer
     */
    protected $year;

    /**
     *
     * @var integer
     */
    protected $is_deleted;

    /**
     *
     * @var integer
     */
    protected $status;

    /**
     *
     * @var integer
     */
    protected $main_category_id;

    /**
     *
     * @var integer
     */
    protected $secondary_category_id;

    /**
     *
     * @var integer
     */
    protected $current_address_id;

    /**
     *
     * @var integer
     */
    protected $brand_id;

    /**
     *
     * @var integer
     */
    protected $model_id;

    /**
     *
     * @var integer
     */
    protected $creator_end_user_id;

    /**
     *
     * @var integer
     */
    protected $creator_company_id;

    /**
     *
     * @var integer
     */
    protected $product_type_id;

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
    protected $description_id;


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
     * Method to set the value of field name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Method to set the value of field product_fields
     *
     * @param string $product_fields
     * @return $this
     */
    public function setProductFields($product_fields)
    {
        $this->product_fields = $product_fields;

        return $this;
    }

    /**
     * Method to set the value of field vehicle_id
     *
     * @param string $vehicle_id
     * @return $this
     */
    public function setVehicleId($vehicle_id)
    {
        $this->vehicle_id = $vehicle_id;

        return $this;
    }

    /**
     * Method to set the value of field usage
     *
     * @param integer $usage
     * @return $this
     */
    public function setUsage($usage)
    {
        $this->usage = $usage;

        return $this;
    }

    /**
     * Method to set the value of field year
     *
     * @param integer $year
     * @return $this
     */
    public function setYear($year)
    {
        $this->year = $year;

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
     * Method to set the value of field main_category_id
     *
     * @param integer $main_category_id
     * @return $this
     */
    public function setMainCategoryId($main_category_id)
    {
        $this->main_category_id = $main_category_id;

        return $this;
    }

    /**
     * Method to set the value of field secondary_category_id
     *
     * @param integer $secondary_category_id
     * @return $this
     */
    public function setSecondaryCategoryId($secondary_category_id)
    {
        $this->secondary_category_id = $secondary_category_id;

        return $this;
    }

    /**
     * Method to set the value of field current_address_id
     *
     * @param integer $current_address_id
     * @return $this
     */
    public function setCurrentAddressId($current_address_id)
    {
        $this->current_address_id = $current_address_id;

        return $this;
    }

    /**
     * Method to set the value of field brand_id
     *
     * @param integer $brand_id
     * @return $this
     */
    public function setBrandId($brand_id)
    {
        $this->brand_id = $brand_id;

        return $this;
    }

    /**
     * Method to set the value of field model_id
     *
     * @param integer $model_id
     * @return $this
     */
    public function setModelId($model_id)
    {
        $this->model_id = $model_id;

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
     * Method to set the value of field creator_company_id
     *
     * @param integer $creator_company_id
     * @return $this
     */
    public function setCreatorCompanyId($creator_company_id)
    {
        $this->creator_company_id = $creator_company_id;

        return $this;
    }

    /**
     * Method to set the value of field product_type_id
     *
     * @param integer $product_type_id
     * @return $this
     */
    public function setProductTypeId($product_type_id)
    {
        $this->product_type_id = $product_type_id;

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
     * Method to set the value of field description_id
     *
     * @param integer $description_id
     * @return $this
     */
    public function setDescriptionId($description_id)
    {
        $this->description_id = $description_id;

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
     * Returns the value of field name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the value of field product_fields
     *
     * @return string
     */
    public function getProductFields()
    {
        return $this->product_fields;
    }

    /**
     * Returns the value of field vehicle_id
     *
     * @return string
     */
    public function getVehicleId()
    {
        return $this->vehicle_id;
    }

    /**
     * Returns the value of field usage
     *
     * @return integer
     */
    public function getUsage()
    {
        return $this->usage;
    }

    /**
     * Returns the value of field year
     *
     * @return integer
     */
    public function getYear()
    {
        return $this->year;
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
     * Returns the value of field main_category_id
     *
     * @return integer
     */
    public function getMainCategoryId()
    {
        return $this->main_category_id;
    }

    /**
     * Returns the value of field secondary_category_id
     *
     * @return integer
     */
    public function getSecondaryCategoryId()
    {
        return $this->secondary_category_id;
    }

    /**
     * Returns the value of field current_address_id
     *
     * @return integer
     */
    public function getCurrentAddressId()
    {
        return $this->current_address_id;
    }

    /**
     * Returns the value of field brand_id
     *
     * @return integer
     */
    public function getBrandId()
    {
        return $this->brand_id;
    }

    /**
     * Returns the value of field model_id
     *
     * @return integer
     */
    public function getModelId()
    {
        return $this->model_id;
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
     * Returns the value of field creator_company_id
     *
     * @return integer
     */
    public function getCreatorCompanyId()
    {
        return $this->creator_company_id;
    }

    /**
     * Returns the value of field product_type_id
     *
     * @return integer
     */
    public function getProductTypeId()
    {
        return $this->product_type_id;
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
     * Returns the value of field description_id
     *
     * @return integer
     */
    public function getDescriptionId()
    {
        return $this->description_id;
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
        $this->setSource("product");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductField[]|ProductField|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductField|\Phalcon\Mvc\Model\ResultInterface
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
        return 'product';
    }

}
