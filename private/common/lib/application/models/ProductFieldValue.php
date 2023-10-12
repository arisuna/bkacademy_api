<?php

namespace SMXD\Application\Models;

class ProductFieldValue extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    protected $id;

    /**
     *
     * @var integer
     */
    protected $product_id;

    /**
     *
     * @var integer
     */
    protected $product_field_id;

    /**
     *
     * @var integer
     */
    protected $product_field_group_id;

    /**
     *
     * @var string
     */
    protected $product_field_name;

    /**
     *
     * @var string
     */
    protected $value;

    /**
     *
     * @var integer
     */
    protected $is_custom;

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
     * Method to set the value of field product_field_id
     *
     * @param integer $product_field_id
     * @return $this
     */
    public function setProductFieldId($product_field_id)
    {
        $this->product_field_id = $product_field_id;

        return $this;
    }

    /**
     * Method to set the value of field product_field_group_id
     *
     * @param integer $product_field_group_id
     * @return $this
     */
    public function setProductFieldGroupId($product_field_group_id)
    {
        $this->product_field_group_id = $product_field_group_id;

        return $this;
    }

    /**
     * Method to set the value of field product_field_name
     *
     * @param string $product_field_name
     * @return $this
     */
    public function setProductFieldName($product_field_name)
    {
        $this->product_field_name = $product_field_name;

        return $this;
    }

    /**
     * Method to set the value of field value
     *
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Method to set the value of field is_custom
     *
     * @param integer $is_custom
     * @return $this
     */
    public function setIsCustom($is_custom)
    {
        $this->is_custom = $is_custom;

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
     * Returns the value of field id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
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
     * Returns the value of field product_field_id
     *
     * @return integer
     */
    public function getProductFieldId()
    {
        return $this->product_field_id;
    }

    /**
     * Returns the value of field product_field_group_id
     *
     * @return integer
     */
    public function getProductFieldGroupId()
    {
        return $this->product_field_group_id;
    }

    /**
     * Returns the value of field value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the value of field product_field_name
     *
     * @return string
     */
    public function getProductFieldName()
    {
        return $this->product_field_name;
    }

    /**
     * Returns the value of field is_custom
     *
     * @return integer
     */
    public function getIsCustom()
    {
        return $this->is_custom;
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
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("product_field_value");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductFieldInGroup[]|ProductFieldInGroup|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ProductFieldInGroup|\Phalcon\Mvc\Model\ResultInterface
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
        return 'product_field_value';
    }

}
