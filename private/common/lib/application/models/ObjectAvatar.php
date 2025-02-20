<?php

namespace SMXD\Application\Models;

class ObjectAvatar extends \Phalcon\Mvc\Model
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
    protected $name;

    /**
     *
     * @var string
     */
    protected $object_name;

    /**
     *
     * @var string
     */
    protected $object_uuid;

    /**
     *
     * @var string
     */
    protected $file_path;

    /**
     *
     * @var string
     */
    protected $file_name;

    /**
     *
     * @var string
     */
    protected $file_type;

    /**
     *
     * @var string
     */
    protected $file_extension;

    /**
     *
     * @var integer
     */
    protected $size;

    /**
     *
     * @var string
     */
    protected $mime_type;

    /**
     *
     * @var integer
     */
    protected $media_type_id;

    /**
     *
     * @var string
     */
    protected $company_uuid;

    /**
     *
     * @var string
     */
    protected $user_uuid;

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
     * Method to set the value of field file_path
     *
     * @param string $file_path
     * @return $this
     */
    public function setFilePath($file_path)
    {
        $this->file_path = $file_path;

        return $this;
    }

    /**
     * Method to set the value of field file_name
     *
     * @param string $file_name
     * @return $this
     */
    public function setFileName($file_name)
    {
        $this->file_name = $file_name;

        return $this;
    }

    /**
     * Method to set the value of field file_type
     *
     * @param string $file_type
     * @return $this
     */
    public function setFileType($file_type)
    {
        $this->file_type = $file_type;

        return $this;
    }

    /**
     * Method to set the value of field file_extension
     *
     * @param string $file_extension
     * @return $this
     */
    public function setFileExtension($file_extension)
    {
        $this->file_extension = $file_extension;

        return $this;
    }

    /**
     * Method to set the value of field size
     *
     * @param integer $size
     * @return $this
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Method to set the value of field mime_type
     *
     * @param string $mime_type
     * @return $this
     */
    public function setMimeType($mime_type)
    {
        $this->mime_type = $mime_type;

        return $this;
    }

    /**
     * Method to set the value of field media_type_id
     *
     * @param integer $media_type_id
     * @return $this
     */
    public function setMediaTypeId($media_type_id)
    {
        $this->media_type_id = $media_type_id;

        return $this;
    }

    /**
     * Method to set the value of field company_uuid
     *
     * @param string $company_uuid
     * @return $this
     */
    public function setCompanyUuid($company_uuid)
    {
        $this->company_uuid = $company_uuid;

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
     * Returns the value of field uuid
     *
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
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
     * Returns the value of field object_name
     *
     * @return string
     */
    public function getObjectName()
    {
        return $this->object_name;
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
     * Returns the value of field file_path
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->file_path;
    }

    /**
     * Returns the value of field file_name
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->file_name;
    }

    /**
     * Returns the value of field file_type
     *
     * @return string
     */
    public function getFileType()
    {
        return $this->file_type;
    }

    /**
     * Returns the value of field file_extension
     *
     * @return string
     */
    public function getFileExtension()
    {
        return $this->file_extension;
    }

    /**
     * Returns the value of field size
     *
     * @return integer
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Returns the value of field mime_type
     *
     * @return string
     */
    public function getMimeType()
    {
        return $this->mime_type;
    }

    /**
     * Returns the value of field media_type_id
     *
     * @return integer
     */
    public function getMediaTypeId()
    {
        return $this->media_type_id;
    }

    /**
     * Returns the value of field company_uuid
     *
     * @return string
     */
    public function getCompanyUuid()
    {
        return $this->company_uuid;
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
        $this->setSource("object_avatar");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return ObjectAvatar[]|ObjectAvatar|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return ObjectAvatar|\Phalcon\Mvc\Model\ResultInterface
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
        return 'object_avatar';
    }

}
