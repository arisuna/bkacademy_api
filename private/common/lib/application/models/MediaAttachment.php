<?php

namespace SMXD\Application\Models;

class MediaAttachment extends \Phalcon\Mvc\Model
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
    protected $object_uuid;

    /**
     *
     * @var string
     */
    protected $object_name;

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
     *
     * @var integer
     */
    protected $media_id;

    /**
     *
     * @var string
     */
    protected $media_uuid;

    /**
     *
     * @var integer
     */
    protected $media_folder_id;

    /**
     *
     * @var integer
     */
    protected $is_shared;

    /**
     *
     * @var string
     */
    protected $user_uuid;

    /**
     *
     * @var string
     */
    protected $shared_employee_uuid;

    /**
     *
     * @var integer
     */
    protected $object_id;

    /**
     *
     * @var integer
     */
    protected $owner_company_id;

    /**
     *
     * @var string
     */
    protected $folder_uuid;

    /**
     *
     * @var string
     */
    protected $task_file_uuid;

    /**
     *
     * @var integer
     */
    protected $is_thumb;

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
     * Method to set the value of field object_uuid
     *
     * @param string $object_uuid
     * @return $this
     */
    public function setObjectUuid($object_uuid)
    {
        $this->object_uuid = $object_uuid;

        return $this;
    }

    /**
     * Method to set the value of field object_name
     *
     * @param string $object_name
     * @return $this
     */
    public function setObjectName($object_name)
    {
        $this->object_name = $object_name;

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
     * Method to set the value of field media_id
     *
     * @param integer $media_id
     * @return $this
     */
    public function setMediaId($media_id)
    {
        $this->media_id = $media_id;

        return $this;
    }

    /**
     * Method to set the value of field media_uuid
     *
     * @param string $media_uuid
     * @return $this
     */
    public function setMediaUuid($media_uuid)
    {
        $this->media_uuid = $media_uuid;

        return $this;
    }

    /**
     * Method to set the value of field media_folder_id
     *
     * @param integer $media_folder_id
     * @return $this
     */
    public function setMediaFolderId($media_folder_id)
    {
        $this->media_folder_id = $media_folder_id;

        return $this;
    }

    /**
     * Method to set the value of field is_shared
     *
     * @param integer $is_shared
     * @return $this
     */
    public function setIsShared($is_shared)
    {
        $this->is_shared = $is_shared;

        return $this;
    }

    /**
     * Method to set the value of field user_uuid
     *
     * @param string $user_uuid
     * @return $this
     */
    public function setUserUuid($user_uuid)
    {
        $this->user_uuid = $user_uuid;

        return $this;
    }

    /**
     * Method to set the value of field shared_employee_uuid
     *
     * @param string $shared_employee_uuid
     * @return $this
     */
    public function setSharedEmployeeUuid($shared_employee_uuid)
    {
        $this->shared_employee_uuid = $shared_employee_uuid;

        return $this;
    }

    /**
     * Method to set the value of field object_id
     *
     * @param integer $object_id
     * @return $this
     */
    public function setObjectId($object_id)
    {
        $this->object_id = $object_id;

        return $this;
    }

    /**
     * Method to set the value of field owner_company_id
     *
     * @param integer $owner_company_id
     * @return $this
     */
    public function setOwnerCompanyId($owner_company_id)
    {
        $this->owner_company_id = $owner_company_id;

        return $this;
    }

    /**
     * Method to set the value of field folder_uuid
     *
     * @param string $folder_uuid
     * @return $this
     */
    public function setFolderUuid($folder_uuid)
    {
        $this->folder_uuid = $folder_uuid;

        return $this;
    }

    /**
     * Method to set the value of field task_file_uuid
     *
     * @param string $task_file_uuid
     * @return $this
     */
    public function setTaskFileUuid($task_file_uuid)
    {
        $this->task_file_uuid = $task_file_uuid;

        return $this;
    }

    /**
     * Method to set the value of field is_thumb
     *
     * @param integer $is_thumb
     * @return $this
     */
    public function setIsThumb($is_thumb)
    {
        $this->is_thumb = $is_thumb;

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
     * Returns the value of field object_uuid
     *
     * @return string
     */
    public function getObjectUuid()
    {
        return $this->object_uuid;
    }

    /**
     * Returns the value of field object_name
     *
     * @return string
     */
    public function getObjectName()
    {
        return $this->object_name;
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
     * Returns the value of field media_id
     *
     * @return integer
     */
    public function getMediaId()
    {
        return $this->media_id;
    }

    /**
     * Returns the value of field media_uuid
     *
     * @return string
     */
    public function getMediaUuid()
    {
        return $this->media_uuid;
    }

    /**
     * Returns the value of field media_folder_id
     *
     * @return integer
     */
    public function getMediaFolderId()
    {
        return $this->media_folder_id;
    }

    /**
     * Returns the value of field is_shared
     *
     * @return integer
     */
    public function getIsShared()
    {
        return $this->is_shared;
    }

    /**
     * Returns the value of field user_uuid
     *
     * @return string
     */
    public function getUserUuid()
    {
        return $this->user_uuid;
    }

    /**
     * Returns the value of field shared_employee_uuid
     *
     * @return string
     */
    public function getSharedEmployeeUuid()
    {
        return $this->shared_employee_uuid;
    }

    /**
     * Returns the value of field object_id
     *
     * @return integer
     */
    public function getObjectId()
    {
        return $this->object_id;
    }

    /**
     * Returns the value of field owner_company_id
     *
     * @return integer
     */
    public function getOwnerCompanyId()
    {
        return $this->owner_company_id;
    }

    /**
     * Returns the value of field folder_uuid
     *
     * @return string
     */
    public function getFolderUuid()
    {
        return $this->folder_uuid;
    }

    /**
     * Returns the value of field task_file_uuid
     *
     * @return string
     */
    public function getTaskFileUuid()
    {
        return $this->task_file_uuid;
    }

    /**
     * Returns the value of field is_thumb
     *
     * @return integer
     */
    public function getIsThumb()
    {
        return $this->is_thumb;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('media_id', 'SMXD\Application\Models\Media', 'id', ['alias' => 'Media']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return MediaAttachment[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return MediaAttachment
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
        return 'media_attachment';
    }

}
