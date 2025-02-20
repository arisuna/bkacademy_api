<?php

namespace SMXD\Application\Models;

class Acl extends \Phalcon\Mvc\Model
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
     * @Column(type="integer", length=11, nullable=true)
     */
    protected $acl_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=true)
     */
    protected $is_visible;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=false)
     */
    protected $label;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=true)
     */
    protected $summary_label;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    protected $name;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    protected $controller;

    /**
     *
     * @var string
     * @Column(type="string", length=45, nullable=true)
     */
    protected $action;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=true)
     */
    protected $group;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=true)
     */
    protected $lvl;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    protected $pos;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    protected $main_class;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    protected $info_class;

    /**
     *
     * @var string
     * @Column(type="string", length=50, nullable=true)
     */
    protected $angular_state;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    protected $description;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=true)
     */
    protected $is_admin;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=true)
     */
    protected $status;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
     */
    protected $created_at;

    /**
     *
     * @var string
     * @Column(type="string", nullable=false)
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
     * Method to set the value of field acl_id
     *
     * @param integer $acl_id
     * @return $this
     */
    public function setAclId($acl_id)
    {
        $this->acl_id = $acl_id;

        return $this;
    }

    /**
     * Method to set the value of field is_visible
     *
     * @param integer $is_visible
     * @return $this
     */
    public function setIsVisible($is_visible)
    {
        $this->is_visible = $is_visible;

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
     * Method to set the value of field summary_label
     *
     * @param string $summary_label
     * @return $this
     */
    public function setSummaryLabel($summary_label)
    {
        $this->summary_label = $summary_label;

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
     * Method to set the value of field controller
     *
     * @param string $controller
     * @return $this
     */
    public function setController($controller)
    {
        $this->controller = $controller;

        return $this;
    }

    /**
     * Method to set the value of field action
     *
     * @param string $action
     * @return $this
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Method to set the value of field group
     *
     * @param integer $group
     * @return $this
     */
    public function setGroup($group)
    {
        $this->group = $group;

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
     * Method to set the value of field pos
     *
     * @param integer $pos
     * @return $this
     */
    public function setPos($pos)
    {
        $this->pos = $pos;

        return $this;
    }

    /**
     * Method to set the value of field main_class
     *
     * @param string $main_class
     * @return $this
     */
    public function setMainClass($main_class)
    {
        $this->main_class = $main_class;

        return $this;
    }

    /**
     * Method to set the value of field info_class
     *
     * @param string $info_class
     * @return $this
     */
    public function setInfoClass($info_class)
    {
        $this->info_class = $info_class;

        return $this;
    }

    /**
     * Method to set the value of field angular_state
     *
     * @param string $angular_state
     * @return $this
     */
    public function setAngularState($angular_state)
    {
        $this->angular_state = $angular_state;

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
     * Method to set the value of field is_admin
     *
     * @param integer $is_admin
     * @return $this
     */
    public function setIsAdmin($is_admin)
    {
        $this->is_admin = $is_admin;

        return $this;
    }

    /**
     * Method to set the value of field status
     *
     * @param integer $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

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
     * Returns the value of field acl_id
     *
     * @return integer
     */
    public function getAclId()
    {
        return $this->acl_id;
    }

    /**
     * Returns the value of field is_visible
     *
     * @return integer
     */
    public function getIsVisible()
    {
        return $this->is_visible;
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
     * Returns the value of field summary_label
     *
     * @return string
     */
    public function getSummaryLabel()
    {
        return $this->summary_label;
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
     * Returns the value of field controller
     *
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Returns the value of field action
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Returns the value of field group
     *
     * @return integer
     */
    public function getGroup()
    {
        return $this->group;
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
     * Returns the value of field pos
     *
     * @return integer
     */
    public function getPos()
    {
        return $this->pos;
    }

    /**
     * Returns the value of field main_class
     *
     * @return string
     */
    public function getMainClass()
    {
        return $this->main_class;
    }

    /**
     * Returns the value of field info_class
     *
     * @return string
     */
    public function getInfoClass()
    {
        return $this->info_class;
    }

    /**
     * Returns the value of field angular_state
     *
     * @return string
     */
    public function getAngularState()
    {
        return $this->angular_state;
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
     * Returns the value of field is_admin
     *
     * @return integer
     */
    public function getIsAdmin()
    {
        return $this->is_admin;
    }

    /**
     * Returns the value of field status
     *
     * @return integer
     */
    public function getStatus()
    {
        return $this->status;
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
     * Method to set the value of field parent_id
     *
     * @param integer $parent_id
     * @return $this
     */
    public function setParentId($parent_id)
    {
        $this->parent_id = $parent_id;

        return $this;
    }

    /**
     * Returns the value of field parent_id
     *
     * @return integer
     */
    public function getParentId()
    {
        return $this->parent_id;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("acl");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Acl[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Acl
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters)?: null;
    }

    protected $source = "acl";

}
