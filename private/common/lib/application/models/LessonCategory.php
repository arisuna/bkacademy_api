<?php

namespace SMXD\Application\Models;

class LessonCategory extends \Phalcon\Mvc\Model
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
    protected $position;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $is_home_category;

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
     * Method to set the value of field position
     *
     * @param integer $position
     * @return $this
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Method to set the value of field is_home_category
     *
     * @param integer $is_home_category
     * @return $this
     */
    public function setIsHomeCategory($is_home_category)
    {
        $this->is_home_category = $is_home_category;

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
     * Returns the value of field lesson_id
     *
     * @return integer
     */
    public function getLessonId()
    {
        return $this->lesson_id;
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
     * Returns the value of field position
     *
     * @return integer
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Returns the value of field is_home_category
     *
     * @return integer
     */
    public function getIsHomeCategory()
    {
        return $this->is_home_category;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('lesson_id', 'SMXD\Application\Models\LessonExt', 'id', ['alias' => 'Lesson']);
        $this->belongsTo('category_id', 'SMXD\Application\Models\CategoryExt', 'id', ['alias' => 'Category']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentClass[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return StudentClass
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
        return 'lesson_category';
    }

}
