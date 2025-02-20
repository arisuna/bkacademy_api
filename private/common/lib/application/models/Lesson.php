<?php

namespace SMXD\Application\Models;

class Lesson extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $id;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=false)
     */
    protected $name;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=false)
     */
    protected $code;

    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $date;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $week;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $week_report;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $month_report;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $had_homework;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $lesson_type_id;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $exam_type_id;
    
    /**
     *
     * @var integer
     * @Primary
     * @Identity
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $class_id;

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
     * Method to set the value of field date
     *
     * @param integer $date
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Method to set the value of field week
     *
     * @param integer $week
     * @return $this
     */
    public function setWeek($week)
    {
        $this->week = $week;

        return $this;
    }

    /**
     * Method to set the value of field week_report
     *
     * @param integer $week_report
     * @return $this
     */
    public function setWeekReport($week_report)
    {
        $this->week_report = $week_report;

        return $this;
    }

    /**
     * Method to set the value of field month_report
     *
     * @param integer $month_report
     * @return $this
     */
    public function setMonthReport($month_report)
    {
        $this->month_report = $month_report;

        return $this;
    }

    /**
     * Method to set the value of field had_homework
     *
     * @param integer $had_homework
     * @return $this
     */
    public function setHadHomework($had_homework)
    {
        $this->had_homework = $had_homework;

        return $this;
    }

    /**
     * Method to set the value of field lesson_type_id
     *
     * @param integer $lesson_type_id
     * @return $this
     */
    public function setLessonTypeId($lesson_type_id)
    {
        $this->lesson_type_id = $lesson_type_id;

        return $this;
    }


    /**
     * Method to set the value of field exam_type_id
     *
     * @param integer $exam_type_id
     * @return $this
     */
    public function setExamTypeId($exam_type_id)
    {
        $this->exam_type_id = $exam_type_id;

        return $this;
    }
    /**
     * Method to set the value of field class_id
     *
     * @param integer $class_id
     * @return $this
     */
    public function setClassId($class_id)
    {
        $this->class_id = $class_id;

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
     * Returns the value of field date
     *
     * @return integer
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Returns the value of field week
     *
     * @return integer
     */
    public function getWeek()
    {
        return $this->week;
    }

    /**
     * Returns the value of field week_report
     *
     * @return integer
     */
    public function getWeekReport()
    {
        return $this->week_report;
    }

    /**
     * Returns the value of field month_report
     *
     * @return integer
     */
    public function getMonthReport()
    {
        return $this->month_report;
    }

    /**
     * Returns the value of field had_homework
     *
     * @return integer
     */
    public function getHadHomework()
    {
        return $this->had_homework;
    }

    /**
     * Returns the value of field lesson_type_id
     *
     * @return integer
     */
    public function getLessonTypeId()
    {
        return $this->lesson_type_id;
    }

    /**
     * Returns the value of field exam_type_id
     *
     * @return integer
     */
    public function getExamTypeId()
    {
        return $this->exam_type_id;
    }

    /**
     * Returns the value of field class_id
     *
     * @return integer
     */
    public function getClassId()
    {
        return $this->class_id;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Lesson[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Lesson
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
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
        return 'lesson';
    }

}
