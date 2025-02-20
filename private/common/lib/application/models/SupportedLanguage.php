<?php

namespace SMXD\Application\Models;

class SupportedLanguage extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=12, nullable=false)
     */
    protected $id;

    /**
     *
     * @var string
     * @Column(type="string", length=2, nullable=false)
     */
    protected $name;

    /**
     *
     * @var string
     * @Column(type="string", length=45, nullable=false)
     */
    protected $description;

    /**
     *
     * @var string
     * @Column(type="string", length=45, nullable=false)
     */
    protected $iso;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    protected $data;

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
     * Method to set the value of field description
     *
     * @param string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Method to set the value of field iso
     *
     * @param string $iso
     * @return $this
     */
    public function setIso($iso)
    {
        $this->iso = $iso;

        return $this;
    }

    /**
     * Method to set the value of field data
     *
     * @param string $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

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
     * Returns the value of field description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the value of field iso
     *
     * @return string
     */
    public function getIso()
    {
        return $this->iso;
    }

    /**
     * Returns the value of field data
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return SupportedLanguage[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return SupportedLanguage
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

    public function getSource(): string
{
    return 'supported_language';
}

    protected $source = 'supported_language';

}
