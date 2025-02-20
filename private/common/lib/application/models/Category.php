<?php

namespace SMXD\Application\Models;

class Category extends \Phalcon\Mvc\Model
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
    protected $uuid;

    /**
     *
     * @var string
     */
    protected $reference;

    /**
     *
     * @var string
     */
    protected $name;

    /**
     *
     * @var integer
     */
    protected $lvl;

    /**
     *
     * @var integer
     */
    protected $is_leaf;

    /**
     *
     * @var integer
     */
    protected $parent_category_id;

    /**
     *
     * @var integer
     */
    protected $difficult_level;

    /**
     *
     * @var string
     */
    protected $general_solution;

    /**
     *
     * @var string
     */
    protected $detail_solution;

    /**
     *
     * @var integer
     */
    protected $grade;

    /**
     *
     * @var varchar
     */
    protected $subject;

    /**
     *
     * @var integer
     */
    protected $position;

    /**
     *
     * @var integer
     */
    protected $direct_question_number;

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
     * Method to set the value of field uuid
     *
     * @param string $uuid
     * @return $this
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * Method to set the value of field reference
     *
     * @param string $reference
     * @return $this
     */
    public function setReference($reference)
    {
        $this->reference = $reference;

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
     * Method to set the value of field lvl
     *
     * @param integer $lvl
     * @return $this
     */
    public function setLvl($lvl)
    {
        $this->lvl = $lvl;

        return $this;
    }

    /**
     * Method to set the value of field is_leaf
     *
     * @param integer $is_leaf
     * @return $this
     */
    public function setIsLeaf($is_leaf)
    {
        $this->is_leaf = $is_leaf;

        return $this;
    }

    /**
     * Method to set the value of field parent_category_id
     *
     * @param integer $parent_category_id
     * @return $this
     */
    public function setParentCategoryId($parent_category_id)
    {
        $this->parent_category_id = $parent_category_id;

        return $this;
    }

    /**
     * Method to set the value of field difficult_level
     *
     * @param integer $difficult_level
     * @return $this
     */
    public function setDifficultLevel($difficult_level)
    {
        $this->difficult_level = $difficult_level;

        return $this;
    }

    /**
     * Method to set the value of field general_solution
     *
     * @param string $general_solution
     * @return $this
     */
    public function setGeneralSolution($general_solution)
    {
        $this->general_solution = $general_solution;

        return $this;
    }

    /**
     * Method to set the value of field detail_solution
     *
     * @param string $detail_solution
     * @return $this
     */
    public function setDetailSolution($detail_solution)
    {
        $this->detail_solution = $detail_solution;

        return $this;
    }

    /**
     * Method to set the value of field grade
     *
     * @param integer $grade
     * @return $this
     */
    public function setGrade($grade)
    {
        $this->grade = $grade;

        return $this;
    }

    /**
     * Method to set the value of field subject
     *
     * @param string $subject
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

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
     * Method to set the value of field direct_question_number
     *
     * @param integer $direct_question_number
     * @return $this
     */
    public function setDirectQuestionNumber($direct_question_number)
    {
        $this->direct_question_number = $direct_question_number;

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
     * Returns the value of field uuid
     *
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Returns the value of field reference
     *
     * @return string
     */
    public function getReference()
    {
        return $this->reference;
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
     * Returns the value of field lvl
     *
     * @return integer
     */
    public function getLvl()
    {
        return $this->lvl;
    }

    /**
     * Returns the value of field is_leaf
     *
     * @return integer
     */
    public function getIsLeaf()
    {
        return $this->is_leaf;
    }

    /**
     * Returns the value of field parent_category_id
     *
     * @return integer
     */
    public function getParentCategoryId()
    {
        return $this->parent_category_id;
    }

    /**
     * Returns the value of field difficult_level
     *
     * @return integer
     */
    public function getDifficultLevel()
    {
        return $this->difficult_level;
    }

    /**
     * Returns the value of field general_solution
     *
     * @return string
     */
    public function getGeneralSolution()
    {
        return $this->general_solution;
    }

    /**
     * Returns the value of field detail_solution
     *
     * @return string
     */
    public function getDetailSolution()
    {
        return $this->detail_solution;
    }

    /**
     * Returns the value of field grade
     *
     * @return integer
     */
    public function getGrade()
    {
        return $this->grade;
    }

    /**
     * Returns the value of field subject
     *
     * @return integer
     */
    public function getSubject()
    {
        return $this->subject;
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
     * Returns the value of field direct_question_number
     *
     * @return integer
     */
    public function getDirectQuestionNumber()
    {
        return $this->direct_question_number;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("category");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Category[]|Category|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Category|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters);
    }

    protected $source = 'category';

}
