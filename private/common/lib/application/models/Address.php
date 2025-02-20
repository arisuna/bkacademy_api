<?php

namespace SMXD\Application\Models;

class Address extends \Phalcon\Mvc\Model
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
     * @var integer
     */
    protected $end_user_id;

    /**
     *
     * @var integer
     */
    protected $company_id;

    /**
     *
     * @var integer
     */
    protected $vn_district_id;

    /**
     *
     * @var integer
     */
    protected $vn_ward_id;

    /**
     *
     * @var integer
     */
    protected $vn_province_id;

    /**
     *
     * @var string
     */
    protected $name;

    /**
     *
     * @var string
     */
    protected $address1;

    /**
     *
     * @var string
     */
    protected $address2;

    /**
     *
     * @var string
     */
    protected $latitude;

    /**
     *
     * @var string
     */
    protected $longitude;

    /**
     *
     * @var string
     */
    protected $ward_name;

    /**
     *
     * @var string
     */
    protected $district_name;

    /**
     *
     * @var string
     */
    protected $province_name;

    /**
     *
     * @var string
     */
    protected $postal;

    /**
     *
     * @var string
     */
    protected $city;

    /**
     *
     * @var string
     */
    protected $country;

    /**
     *
     * @var string
     */
    protected $telephone;

    /**
     *
     * @var string
     */
    protected $phone;

    /**
     *
     * @var integer
     */
    protected $is_default;

    /**
     *
     * @var integer
     */
    protected $address_type;

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
     * @var integer
     */
    protected $country_id;

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
     * Method to set the value of field end_user_id
     *
     * @param integer $end_user_id
     * @return $this
     */
    public function setEndUserId($end_user_id)
    {
        $this->end_user_id = $end_user_id;

        return $this;
    }

    /**
     * Method to set the value of field company_id
     *
     * @param integer $company_id
     * @return $this
     */
    public function setCompanyId($company_id)
    {
        $this->company_id = $company_id;

        return $this;
    }

    /**
     * Method to set the value of field vn_district_id
     *
     * @param integer $vn_district_id
     * @return $this
     */
    public function setVnDistrictId($vn_district_id)
    {
        $this->vn_district_id = $vn_district_id;

        return $this;
    }

    /**
     * Method to set the value of field vn_ward_id
     *
     * @param integer $vn_ward_id
     * @return $this
     */
    public function setVnWardId($vn_ward_id)
    {
        $this->vn_ward_id = $vn_ward_id;

        return $this;
    }

    /**
     * Method to set the value of field vn_province_id
     *
     * @param integer $vn_province_id
     * @return $this
     */
    public function setVnProvinceId($vn_province_id)
    {
        $this->vn_province_id = $vn_province_id;

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
     * Method to set the value of field address1
     *
     * @param string $address1
     * @return $this
     */
    public function setAddress1($address1)
    {
        $this->address1 = $address1;

        return $this;
    }

    /**
     * Method to set the value of field address2
     *
     * @param string $address2
     * @return $this
     */
    public function setAddress2($address2)
    {
        $this->address2 = $address2;

        return $this;
    }

    /**
     * Method to set the value of field latitude
     *
     * @param string $latitude
     * @return $this
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Method to set the value of field longitude
     *
     * @param string $longitude
     * @return $this
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Method to set the value of field ward_name
     *
     * @param string $ward_name
     * @return $this
     */
    public function setWardName($ward_name)
    {
        $this->ward_name = $ward_name;

        return $this;
    }

    /**
     * Method to set the value of field district_name
     *
     * @param string $district_name
     * @return $this
     */
    public function setDistrictName($district_name)
    {
        $this->district_name = $district_name;

        return $this;
    }

    /**
     * Method to set the value of field province_name
     *
     * @param string $province_name
     * @return $this
     */
    public function setProvinceName($province_name)
    {
        $this->province_name = $province_name;

        return $this;
    }

    /**
     * Method to set the value of field postal
     *
     * @param string $postal
     * @return $this
     */
    public function setPostal($postal)
    {
        $this->postal = $postal;

        return $this;
    }

    /**
     * Method to set the value of field city
     *
     * @param string $city
     * @return $this
     */
    public function setCity($city)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Method to set the value of field country
     *
     * @param string $country
     * @return $this
     */
    public function setCountry($country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Method to set the value of field telephone
     *
     * @param string $telephone
     * @return $this
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * Method to set the value of field phone
     *
     * @param string $phone
     * @return $this
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Method to set the value of field is_default
     *
     * @param integer $is_default
     * @return $this
     */
    public function setIsDefault($is_default)
    {
        $this->is_default = $is_default;

        return $this;
    }

    /**
     * Method to set the value of field address_type
     *
     * @param integer $address_type
     * @return $this
     */
    public function setAddressType($address_type)
    {
        $this->address_type = $address_type;

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
     * Method to set the value of field country_id
     *
     * @param integer $country_id
     * @return $this
     */
    public function setCountryId($country_id)
    {
        $this->country_id = $country_id;

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
     * Returns the value of field end_user_id
     *
     * @return integer
     */
    public function getEndUserId()
    {
        return $this->end_user_id;
    }

    /**
     * Returns the value of field company_id
     *
     * @return integer
     */
    public function getCompanyId()
    {
        return $this->company_id;
    }

    /**
     * Returns the value of field vn_district_id
     *
     * @return integer
     */
    public function getVnDistrictId()
    {
        return $this->vn_district_id;
    }

    /**
     * Returns the value of field vn_ward_id
     *
     * @return integer
     */
    public function getVnWardId()
    {
        return $this->vn_ward_id;
    }

    /**
     * Returns the value of field vn_province_id
     *
     * @return integer
     */
    public function getVnProvinceId()
    {
        return $this->vn_province_id;
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
     * Returns the value of field address1
     *
     * @return string
     */
    public function getAddress1()
    {
        return $this->address1;
    }

    /**
     * Returns the value of field address2
     *
     * @return string
     */
    public function getAddress2()
    {
        return $this->address2;
    }

    /**
     * Returns the value of field latitude
     *
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Returns the value of field longitude
     *
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Returns the value of field ward_name
     *
     * @return string
     */
    public function getWardName()
    {
        return $this->ward_name;
    }

    /**
     * Returns the value of field district_name
     *
     * @return string
     */
    public function getDistrictName()
    {
        return $this->district_name;
    }

    /**
     * Returns the value of field province_name
     *
     * @return string
     */
    public function getProvinceName()
    {
        return $this->province_name;
    }

    /**
     * Returns the value of field postal
     *
     * @return string
     */
    public function getPostal()
    {
        return $this->postal;
    }

    /**
     * Returns the value of field city
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Returns the value of field country
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Returns the value of field telephone
     *
     * @return string
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * Returns the value of field phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Returns the value of field is_default
     *
     * @return integer
     */
    public function getIsDefault()
    {
        return $this->is_default;
    }

    /**
     * Returns the value of field address_type
     *
     * @return integer
     */
    public function getAddressType()
    {
        return $this->address_type;
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
     * Returns the value of field country_id
     *
     * @return integer
     */
    public function getCountryId()
    {
        return $this->country_id;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
//        $this->setSchema("sanmayxaydung");
//        $this->setSource("address");
    }
        protected $source = 'address';

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Address[]|Address|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Address|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters)?: null;
    }

}
