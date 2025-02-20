<?php

namespace SMXD\Application\Models;

class StudentScore extends \Phalcon\Mvc\Model
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
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $student_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $lesson_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $home_score;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $score;

    /**
     *
     * @var integer
     * @Column(type="string", length=11, nullable=false)
     */
    protected $note;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $date;

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
     * Method to set the value of field student_id
     *
     * @param integer $student_id
     * @return $this
     */
    public function setStudentId($student_id)
    {
        $this->student_id = $student_id;

        return $this;
    }

    /**
     * Method to set the value of field lesson_id
     *
     * @param integer $lesson_id
     * @return $this
     */
    public function setLessonId($lesson_id)
    {
        $this->lesson_id = $lesson_id;

        return $this;
    }

    /**
     * Method to set the value of field home_score
     *
     * @param integer $home_score
     * @return $this
     */
    public function setHomeScore($home_score)
    {
        $this->home_score = $home_score;

        return $this;
    }

    /**
     * Method to set the value of field score
     *
     * @param integer $score
     * @return $this
     */
    public function setScore($score)
    {
        $this->score = $score;

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
     * Method to set the value of field note
     *
     * @param integer $note
     * @return $this
     */
    public function setNote($note)
    {
        $this->note = $note;

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
     * Returns the value of field student_id
     *
     * @return integer
     */
    public function getStudentId()
    {
        return $this->student_id;
    }

    /**
     * Returns the value of field lesson_id
     *
     * @return integer
     */
    public function getLessonId()
    {
        return $this->lesson_id;
    }

    /**
     * Returns the value of field home_score
     *
     * @return integer
     */
    public function getHomeScore()
    {
        return $this->home_score;
    }

    /**
     * Returns the value of field score
     *
     * @return integer
     */
    public function getScore()
    {
        return $this->score;
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
     * Returns the value of field note
     *
     * @return integer
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('lesson_id', 'SMXD\Application\Models\LessonExt', 'id', ['alias' => 'Lesson']);
        $this->belongsTo('student_id', 'SMXD\Application\Models\StudentExt', 'id', ['alias' => 'Student']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentScore[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentScore
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters)?: null;
    }

    protected $source = 'student_score';

}
