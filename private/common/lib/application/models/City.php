<?php

namespace SMXD\Application\Models;

class City extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Column(column="geonameid", type="integer", length=11, nullable=false)
     */
    protected $geonameid;

    /**
     *
     * @var string
     * @Column(column="name", type="string", length=200, nullable=false)
     */
    protected $name;

    /**
     *
     * @var string
     * @Column(column="asciiname", type="string", nullable=false)
     */
    protected $asciiname;

    /**
     *
     * @var string
     * @Column(column="alternate_names", type="string", nullable=true)
     */
    protected $alternate_names;

    /**
     *
     * @var double
     * @Column(column="latitude", type="double", length=10, nullable=true)
     */
    protected $latitude;

    /**
     *
     * @var double
     * @Column(column="longitude", type="double", length=10, nullable=true)
     */
    protected $longitude;

    /**
     *
     * @var string
     * @Column(column="fclass", type="string", length=1, nullable=true)
     */
    protected $fclass;

    /**
     *
     * @var string
     * @Column(column="fcode", type="string", length=10, nullable=true)
     */
    protected $fcode;

    /**
     *
     * @var string
     * @Column(column="country_iso_code", type="string", length=2, nullable=true)
     */
    protected $country_iso_code;

    /**
     *
     * @var string
     * @Column(column="cc2", type="string", length=60, nullable=true)
     */
    protected $cc2;

    /**
     *
     * @var string
     * @Column(column="admin1", type="string", length=20, nullable=true)
     */
    protected $admin1;

    /**
     *
     * @var string
     * @Column(column="admin2", type="string", length=80, nullable=true)
     */
    protected $admin2;

    /**
     *
     * @var string
     * @Column(column="admin3", type="string", length=20, nullable=true)
     */
    protected $admin3;

    /**
     *
     * @var string
     * @Column(column="admin4", type="string", length=20, nullable=true)
     */
    protected $admin4;

    /**
     *
     * @var integer
     * @Column(column="population", type="integer", length=11, nullable=true)
     */
    protected $population;

    /**
     *
     * @var string
     * @Column(column="elevation", type="string", length=255, nullable=true)
     */
    protected $elevation;

    /**
     *
     * @var integer
     * @Column(column="gtopo30", type="integer", length=11, nullable=true)
     */
    protected $gtopo30;

    /**
     *
     * @var string
     * @Column(column="timezone", type="string", length=40, nullable=true)
     */
    protected $timezone;

    /**
     *
     * @var string
     * @Column(column="moddate", type="string", nullable=true)
     */
    protected $moddate;

    /**
     *
     * @var integer
     * @Column(column="country_id", type="integer", length=11, nullable=true)
     */
    protected $country_id;

    /**
     *
     * @var integer
     * @Column(column="state_county_geonameid", type="integer", length=11, nullable=true)
     */
    protected $state_county_geonameid;

    /**
     *
     * @var string
     * @Column(column="state_county_name", type="string", length=255, nullable=true)
     */
    protected $state_county_name;

    /**
     * Method to set the value of field geonameid
     *
     * @param integer $geonameid
     * @return $this
     */
    public function setGeonameid($geonameid)
    {
        $this->geonameid = $geonameid;

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
     * Method to set the value of field asciiname
     *
     * @param string $asciiname
     * @return $this
     */
    public function setAsciiname($asciiname)
    {
        $this->asciiname = $asciiname;

        return $this;
    }

    /**
     * Method to set the value of field alternate_names
     *
     * @param string $alternate_names
     * @return $this
     */
    public function setAlternateNames($alternate_names)
    {
        $this->alternate_names = $alternate_names;

        return $this;
    }

    /**
     * Method to set the value of field latitude
     *
     * @param double $latitude
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
     * @param double $longitude
     * @return $this
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Method to set the value of field fclass
     *
     * @param string $fclass
     * @return $this
     */
    public function setFclass($fclass)
    {
        $this->fclass = $fclass;

        return $this;
    }

    /**
     * Method to set the value of field fcode
     *
     * @param string $fcode
     * @return $this
     */
    public function setFcode($fcode)
    {
        $this->fcode = $fcode;

        return $this;
    }

    /**
     * Method to set the value of field country_iso_code
     *
     * @param string $country_iso_code
     * @return $this
     */
    public function setCountryIsoCode($country_iso_code)
    {
        $this->country_iso_code = $country_iso_code;

        return $this;
    }

    /**
     * Method to set the value of field cc2
     *
     * @param string $cc2
     * @return $this
     */
    public function setCc2($cc2)
    {
        $this->cc2 = $cc2;

        return $this;
    }

    /**
     * Method to set the value of field admin1
     *
     * @param string $admin1
     * @return $this
     */
    public function setAdmin1($admin1)
    {
        $this->admin1 = $admin1;

        return $this;
    }

    /**
     * Method to set the value of field admin2
     *
     * @param string $admin2
     * @return $this
     */
    public function setAdmin2($admin2)
    {
        $this->admin2 = $admin2;

        return $this;
    }

    /**
     * Method to set the value of field admin3
     *
     * @param string $admin3
     * @return $this
     */
    public function setAdmin3($admin3)
    {
        $this->admin3 = $admin3;

        return $this;
    }

    /**
     * Method to set the value of field admin4
     *
     * @param string $admin4
     * @return $this
     */
    public function setAdmin4($admin4)
    {
        $this->admin4 = $admin4;

        return $this;
    }

    /**
     * Method to set the value of field population
     *
     * @param integer $population
     * @return $this
     */
    public function setPopulation($population)
    {
        $this->population = $population;

        return $this;
    }

    /**
     * Method to set the value of field elevation
     *
     * @param string $elevation
     * @return $this
     */
    public function setElevation($elevation)
    {
        $this->elevation = $elevation;

        return $this;
    }

    /**
     * Method to set the value of field gtopo30
     *
     * @param integer $gtopo30
     * @return $this
     */
    public function setGtopo30($gtopo30)
    {
        $this->gtopo30 = $gtopo30;

        return $this;
    }

    /**
     * Method to set the value of field timezone
     *
     * @param string $timezone
     * @return $this
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Method to set the value of field moddate
     *
     * @param string $moddate
     * @return $this
     */
    public function setModdate($moddate)
    {
        $this->moddate = $moddate;

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
     * Method to set the value of field state_county_geonameid
     *
     * @param integer $state_county_geonameid
     * @return $this
     */
    public function setStateCountyGeonameid($state_county_geonameid)
    {
        $this->state_county_geonameid = $state_county_geonameid;

        return $this;
    }

    /**
     * Method to set the value of field state_county_name
     *
     * @param string $state_county_name
     * @return $this
     */
    public function setStateCountyName($state_county_name)
    {
        $this->state_county_name = $state_county_name;

        return $this;
    }

    /**
     * Returns the value of field geonameid
     *
     * @return integer
     */
    public function getGeonameid()
    {
        return $this->geonameid;
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
     * Returns the value of field asciiname
     *
     * @return string
     */
    public function getAsciiname()
    {
        return $this->asciiname;
    }

    /**
     * Returns the value of field alternate_names
     *
     * @return string
     */
    public function getAlternateNames()
    {
        return $this->alternate_names;
    }

    /**
     * Returns the value of field latitude
     *
     * @return double
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Returns the value of field longitude
     *
     * @return double
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Returns the value of field fclass
     *
     * @return string
     */
    public function getFclass()
    {
        return $this->fclass;
    }

    /**
     * Returns the value of field fcode
     *
     * @return string
     */
    public function getFcode()
    {
        return $this->fcode;
    }

    /**
     * Returns the value of field country_iso_code
     *
     * @return string
     */
    public function getCountryIsoCode()
    {
        return $this->country_iso_code;
    }

    /**
     * Returns the value of field cc2
     *
     * @return string
     */
    public function getCc2()
    {
        return $this->cc2;
    }

    /**
     * Returns the value of field admin1
     *
     * @return string
     */
    public function getAdmin1()
    {
        return $this->admin1;
    }

    /**
     * Returns the value of field admin2
     *
     * @return string
     */
    public function getAdmin2()
    {
        return $this->admin2;
    }

    /**
     * Returns the value of field admin3
     *
     * @return string
     */
    public function getAdmin3()
    {
        return $this->admin3;
    }

    /**
     * Returns the value of field admin4
     *
     * @return string
     */
    public function getAdmin4()
    {
        return $this->admin4;
    }

    /**
     * Returns the value of field population
     *
     * @return integer
     */
    public function getPopulation()
    {
        return $this->population;
    }

    /**
     * Returns the value of field elevation
     *
     * @return string
     */
    public function getElevation()
    {
        return $this->elevation;
    }

    /**
     * Returns the value of field gtopo30
     *
     * @return integer
     */
    public function getGtopo30()
    {
        return $this->gtopo30;
    }

    /**
     * Returns the value of field timezone
     *
     * @return string
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Returns the value of field moddate
     *
     * @return string
     */
    public function getModdate()
    {
        return $this->moddate;
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
     * Returns the value of field state_county_geonameid
     *
     * @return integer
     */
    public function getStateCountyGeonameid()
    {
        return $this->state_county_geonameid;
    }

    /**
     * Returns the value of field state_county_name
     *
     * @return string
     */
    public function getStateCountyName()
    {
        return $this->state_county_name;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("city");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return City[]|City|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return City|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

    protected $source = 'city';

}
