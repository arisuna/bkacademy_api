<?php

namespace SMXD\Application\Models;

class Nationality extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var string
     * @Primary
     * @Column(type="string", length=3, nullable=false)
     */
    protected $code;

    /**
     *
     * @var string
     * @Column(type="string", length=128, nullable=false)
     */
    protected $name;

    /**
     *
     * @var string
     * @Column(type="string", length=128, nullable=false)
     */
    protected $value;

    /**
     *
     * @var string
     * @Column(type="string", length=128, nullable=true)
     */
    protected $ext1;

    /**
     *
     * @var string
     * @Column(type="string", length=128, nullable=true)
     */
    protected $ext2;

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
     * Method to set the value of field ext1
     *
     * @param string $ext1
     * @return $this
     */
    public function setExt1($ext1)
    {
        $this->ext1 = $ext1;

        return $this;
    }

    /**
     * Method to set the value of field ext2
     *
     * @param string $ext2
     * @return $this
     */
    public function setExt2($ext2)
    {
        $this->ext2 = $ext2;

        return $this;
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
     * Returns the value of field name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * Returns the value of field ext1
     *
     * @return string
     */
    public function getExt1()
    {
        return $this->ext1;
    }

    /**
     * Returns the value of field ext2
     *
     * @return string
     */
    public function getExt2()
    {
        return $this->ext2;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
        $this->hasMany('code', 'SMXD\Application\Models\AssignmentBasic', 'nationality_code', ['alias' => 'AssignmentBasic']);
        $this->hasMany('code', 'SMXD\Application\Models\Dependant', 'nationality_code', ['alias' => 'Dependant']);
        $this->hasMany('code', 'SMXD\Application\Models\NationalityTranslation', 'code', ['alias' => 'NationalityTranslation']);
    }

    protected $source = 'nationality';

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Nationality[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Nationality
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters)?: null;
    }

}
