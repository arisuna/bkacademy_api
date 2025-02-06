<?php

namespace SMXD\Application\Models;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;

class ClassroomSchedule extends \Phalcon\Mvc\Model
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
    protected $classroom_id;

    /**
     *
     * @var int
     */
    protected $day_of_week;

    /**
     *
     * @var string
     */
    protected $from;

    /**
     *
     * @var string
     */
    protected $to;

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
     * Method to set the value of field classroom_id
     *
     * @param string $classroom_id
     * @return $this
     */
    public function setClassroomId($classroom_id)
    {
        $this->classroom_id = $classroom_id;

        return $this;
    }

    /**
     * Method to set the value of field day_of_week
     *
     * @param string $day_of_week
     * @return $this
     */
    public function setDayOfWeek($day_of_week)
    {
        $this->day_of_week = $day_of_week;

        return $this;
    }

    /**
     * Method to set the value of field from
     *
     * @param string $from
     * @return $this
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Method to set the value of field to
     *
     * @param integer $to
     * @return $this
     */
    public function setTo($to)
    {
        $this->to = $to;

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
     * Returns the value of field classroom_id
     *
     * @return string
     */
    public function getClassroomId()
    {
        return $this->classroom_id;
    }

    /**
     * Returns the value of field day_of_week
     *
     * @return string
     */
    public function getDayOfWeek()
    {
        return $this->day_of_week;
    }

    /**
     * Returns the value of field from
     *
     * @return string
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Returns the value of field to
     *
     * @return integer
     */
    public function getTo()
    {
        return $this->to;
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
     * Validations and business logic
     *
     * @return boolean
     */
    public function validation()
    {
        $validator = new Validation();

        return $this->validate($validator);
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'classroom_schedule';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ClassroomSchedule[]|ClassroomSchedule|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ClassroomSchedule|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
