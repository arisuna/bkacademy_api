<?php

namespace SMXD\Application\Models;

class StudentEvaluation extends \Phalcon\Mvc\Model
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
    protected $evaluation_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $is_home_evaluation;

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
     * Method to set the value of field evaluation_id
     *
     * @param integer $evaluation_id
     * @return $this
     */
    public function setEvaluationId($evaluation_id)
    {
        $this->evaluation_id = $evaluation_id;

        return $this;
    }

    /**
     * Method to set the value of field is_home_evaluation
     *
     * @param integer $is_home_evaluation
     * @return $this
     */
    public function setIsHomeEvaluation($is_home_evaluation)
    {
        $this->is_home_evaluation = $is_home_evaluation;

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
     * Returns the value of field evaluation_id
     *
     * @return integer
     */
    public function getEvaluationId()
    {
        return $this->evaluation_id;
    }

    /**
     * Returns the value of field is_home_evaluation
     *
     * @return integer
     */
    public function getIsHomeEvaluation()
    {
        return $this->is_home_evaluation;
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
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('lesson_id', 'SMXD\Application\Models\LessonExt', 'id', ['alias' => 'Lesson']);
        $this->belongsTo('student_id', 'SMXD\Application\Models\StudentExt', 'id', ['alias' => 'Student']);
        $this->belongsTo('evaluation_id', 'SMXD\Application\Models\EvaluationExt', 'id', ['alias' => 'Evaluation']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentEvaluation[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentEvaluation
     */
    public static function findFirst($parameters = null)
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
        return 'student_evaluation';
    }

}
