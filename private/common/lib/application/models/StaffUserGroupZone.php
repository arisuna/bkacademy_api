<?php

namespace SMXD\Application\Models;

class StaffUserGroupZone extends \Phalcon\Mvc\Model
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
    protected $user_group_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $business_zone_id;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
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
     * Method to set the value of field user_group_id
     *
     * @param integer $user_group_id
     * @return $this
     */
    public function setUserGroupId($user_group_id)
    {
        $this->user_group_id = $user_group_id;

        return $this;
    }

    /**
     * Method to set the value of field business_zone_id
     *
     * @param integer $business_zone_id
     * @return $this
     */
    public function setBusinessZoneId($business_zone_id)
    {
        $this->business_zone_id = $business_zone_id;

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
     * Returns the value of field user_group_id
     *
     * @return integer
     */
    public function getUserGroupId()
    {
        return $this->user_group_id;
    }

    /**
     * Returns the value of field business_zone_id
     *
     * @return integer
     */
    public function getBusinessZoneId()
    {
        return $this->business_zone_id;
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
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('business_zone_id', 'SMXD\Application\Models\BusinessZone', 'id', ['alias' => 'BusinessZone']);
        $this->belongsTo('user_group_id', 'SMXD\Application\Models\StaffUserGroup', 'id', ['alias' => 'UserGroup']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return StaffUserGroupZone[]
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return StaffUserGroupZone
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
        return 'staff_user_group_zone';
    }

}
