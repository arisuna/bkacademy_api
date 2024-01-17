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
    protected $category_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $exam_type_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $is_main_score;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $score;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $correct;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $wrong;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $not_done;

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
     * Method to set the value of field category_id
     *
     * @param integer $category_id
     * @return $this
     */
    public function setCategoryId($category_id)
    {
        $this->category_id = $category_id;

        return $this;
    }

    /**
     * Method to set the value of field is_main_score
     *
     * @param integer $is_main_score
     * @return $this
     */
    public function setIsMainScore($is_main_score)
    {
        $this->is_main_score = $is_main_score;

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
     * Method to set the value of field correct
     *
     * @param integer $correct
     * @return $this
     */
    public function setCorrect($correct)
    {
        $this->correct = $correct;

        return $this;
    }

    /**
     * Method to set the value of field wrong
     *
     * @param integer $wrong
     * @return $this
     */
    public function setWrong($wrong)
    {
        $this->wrong = $wrong;

        return $this;
    }

    /**
     * Method to set the value of field not_done
     *
     * @param integer $not_done
     * @return $this
     */
    public function setNotDone($not_done)
    {
        $this->not_done = $not_done;

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
     * Returns the value of field exam_type_id
     *
     * @return integer
     */
    public function getExamTypeId()
    {
        return $this->exam_type_id;
    }

    /**
     * Returns the value of field category_id
     *
     * @return integer
     */
    public function getCategoryId()
    {
        return $this->category_id;
    }

    /**
     * Returns the value of field is_main_score
     *
     * @return integer
     */
    public function getIsMainScore()
    {
        return $this->is_main_score;
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
     * Returns the value of field correct
     *
     * @return integer
     */
    public function getCorrect()
    {
        return $this->correct;
    }

    /**
     * Returns the value of field wrong
     *
     * @return integer
     */
    public function getWrong()
    {
        return $this->wrong;
    }

    /**
     * Returns the value of field not_done
     *
     * @return integer
     */
    public function getNotDone()
    {
        return $this->not_done;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('lesson_id', 'SMXD\Application\Models\LessonExt', 'id', ['alias' => 'Lesson']);
        $this->belongsTo('student_id', 'SMXD\Application\Models\StudentExt', 'id', ['alias' => 'Student']);
        $this->belongsTo('category_id', 'SMXD\Application\Models\CategoryExt', 'id', ['alias' => 'Category']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentClass[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentClass
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
        return 'student_score';
    }

}
