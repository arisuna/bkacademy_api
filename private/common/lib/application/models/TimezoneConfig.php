<?php

namespace SMXD\Application\Models;

class TimezoneConfig extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=10, nullable=false)
     */
    protected $zone_id;

    /**
     *
     * @var string
     * @Column(type="string", length=2, nullable=false)
     */
    protected $country_code;

    /**
     *
     * @var string
     * @Column(type="string", length=35, nullable=false)
     */
    protected $zone_name;

    /**
     *
     * @var string
     * @Column(type="string", length=20, nullable=true)
     */
    protected $utc;

    /**
     * Method to set the value of field zone_id
     *
     * @param integer $zone_id
     * @return $this
     */
    public function setZoneId($zone_id)
    {
        $this->zone_id = $zone_id;

        return $this;
    }

    /**
     * Method to set the value of field country_code
     *
     * @param string $country_code
     * @return $this
     */
    public function setCountryCode($country_code)
    {
        $this->country_code = $country_code;

        return $this;
    }

    /**
     * Method to set the value of field zone_name
     *
     * @param string $zone_name
     * @return $this
     */
    public function setZoneName($zone_name)
    {
        $this->zone_name = $zone_name;

        return $this;
    }

    /**
     * Method to set the value of field utc
     *
     * @param string $utc
     * @return $this
     */
    public function setUtc($utc)
    {
        $this->utc = $utc;

        return $this;
    }

    /**
     * Returns the value of field zone_id
     *
     * @return integer
     */
    public function getZoneId()
    {
        return $this->zone_id;
    }

    /**
     * Returns the value of field country_code
     *
     * @return string
     */
    public function getCountryCode()
    {
        return $this->country_code;
    }

    /**
     * Returns the value of field zone_name
     *
     * @return string
     */
    public function getZoneName()
    {
        return $this->zone_name;
    }

    /**
     * Returns the value of field utc
     *
     * @return string
     */
    public function getUtc()
    {
        return $this->utc;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("timezone_config");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return TimezoneConfig[]|TimezoneConfig|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return TimezoneConfig|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

    protected $source = 'timezone_config';

}
