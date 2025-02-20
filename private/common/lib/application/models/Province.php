<?php

namespace SMXD\Application\Models;

class Province extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Column(column="id", type="integer", length=11, nullable=false)
     */
    protected $id;

    /**
     *
     * @var string
     * @Column(column="name", type="string", length=255, nullable=false)
     */
    protected $name;

    /**
     *
     * @var string
     * @Column(column="name_en", type="string", length=255, nullable=false)
     */
    protected $name_en;


    /**
     *
     * @var string
     * @Column(column="fullname", type="string", length=255, nullable=false)
     */
    protected $fullname;

    /**
     *
     * @var string
     * @Column(column="fullname_en", type="string", length=255, nullable=false)
     */
    protected $fullname_en;
    /**
     *
     * @var string
     * @Column(column="code", type="string", nullable=false)
     */
    protected $code;

    /**
     *
     * @var integer
     * @Primary
     * @Column(column="administrative_unit_id", type="integer", length=11, nullable=false)
     */
    protected $administrative_unit_id;

    /**
     *
     * @var integer
     * @Primary
     * @Column(column="administrative_region_id", type="integer", length=11, nullable=false)
     */
    protected $administrative_region_id;

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
     * Method to set the value of field name_en
     *
     * @param string $name_en
     * @return $this
     */
    public function setNameEn($name_en)
    {
        $this->name_en = $name_en;

        return $this;
    }

    /**
     * Method to set the value of field fullname
     *
     * @param string $fullname
     * @return $this
     */
    public function setFullname($fullname)
    {
        $this->fullname = $fullname;

        return $this;
    }

    /**
     * Method to set the value of field fullname_en
     *
     * @param string $fullname_en
     * @return $this
     */
    public function setFullnameEn($fullname_en)
    {
        $this->fullname_en = $fullname_en;

        return $this;
    }

    /**
     * Method to set the value of field code
     *
     * @param string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Method to set the value of field administrative_unit_id
     *
     * @param integer $administrative_unit_id
     * @return $this
     */
    public function setAdministrativeUnitId($administrative_unit_id)
    {
        $this->administrative_unit_id = $administrative_unit_id;

        return $this;
    }

    /**
     * Method to set the value of field administrative_region_id
     *
     * @param integer $administrative_region_id
     * @return $this
     */
    public function setAdministrativeRegionId($administrative_region_id)
    {
        $this->administrative_region_id = $administrative_region_id;

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
     * Returns the value of field name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the value of field name_en
     *
     * @return string
     */
    public function getNameEn()
    {
        return $this->name_en;
    }

    /**
     * Returns the value of field fullname
     *
     * @return string
     */
    public function getFullname()
    {
        return $this->fullname;
    }

    /**
     * Returns the value of field fullname_en
     *
     * @return string
     */
    public function getFullnameEn()
    {
        return $this->fullname_en;
    }

    /**
     * Returns the value of field code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Returns the value of field administrative_unit_id
     *
     * @return integer
     */
    public function getAdministrativeUnitId()
    {
        return $this->administrative_unit_id;
    }

    /**
     * Returns the value of field administrative_region_id
     *
     * @return integer
     */
    public function getAdministrativeRegionId()
    {
        return $this->administrative_region_id;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("province");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Province[]|Province|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Province|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

    protected $source = 'province';

}
