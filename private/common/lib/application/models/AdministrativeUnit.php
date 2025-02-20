<?php

namespace SMXD\Application\Models;

class AdministrativeUnit extends \Phalcon\Mvc\Model
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
     * @Column(column="code", type="string", nullable=false)
     */
    protected $code;

    /**
     *
     * @var string
     * @Column(column="label", type="string", nullable=true)
     */
    protected $label;

    /**
     *
     * @var string
     * @Column(column="label_short", type="string", nullable=true)
     */
    protected $label_short;

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
     * Method to set the value of field label
     *
     * @param string $label
     * @return $this
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Method to set the value of field label_short
     *
     * @param string $label_short
     * @return $this
     */
    public function setLabelShort($label_short)
    {
        $this->label_short = $label_short;

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
     * Returns the value of field code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Returns the value of field label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Returns the value of field label_short
     *
     * @return string
     */
    public function getLabelShort()
    {
        return $this->label_short;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("administrative_unit");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return AdministrativeUnit[]|AdministrativeUnit|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return AdministrativeUnit|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

    protected $source = 'administrative_unit';

}
