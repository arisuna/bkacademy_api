<?php

namespace SMXD\Application\Models;

class Media extends \Phalcon\Mvc\Model
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
     * @Column(type="string", length=64, nullable=false)
     */
    protected $uuid;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=false)
     */
    public $name;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=true)
     */
    protected $name_static;

    /**
     *
     * @var string
     * @Column(type="string", length=64, nullable=true)
     */
    protected $user_uuid;

    /**
     *
     * @var string
     * @Column(type="string", length=64, nullable=true)
     */
    protected $folder_uuid;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=false)
     */
    protected $filename;

    /**
     *
     * @var string
     * @Column(type="string", length=45, nullable=false)
     */
    protected $file_type;

    /**
     *
     * @var string
     * @Column(type="string", length=45, nullable=true)
     */
    protected $file_extension;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    protected $size;

    /**
     *
     * @var string
     * @Column(type="string", length=128, nullable=false)
     */
    protected $mime_type;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=false)
     */
    protected $media_type_id;

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
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    protected $user_login_id;

    /**
     *
     * @var integer
     * @Column(type="integer", length=11, nullable=true)
     */
    protected $company_id;

    /**
     *
     * @var string
     * @Column(type="string", length=255, nullable=true)
     */
    protected $file_path;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=false)
     */
    protected $is_hosted;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=false)
     */
    protected $is_private;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=false)
     */
    protected $is_deleted;

    /**
     *
     * @var integer
     * @Column(type="integer", length=1, nullable=true)
     */
    protected $is_hidden;

    /**
     *
     * @var string
     * @Column(type="string", nullable=true)
     */
    protected $origin_url;

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
     * Method to set the value of field name_static
     *
     * @param string $name_static
     * @return $this
     */
    public function setNameStatic($name_static)
    {
        $this->name_static = $name_static;

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
     * Method to set the value of field filename
     *
     * @param string $filename
     * @return $this
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

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
     * Method to set the value of field user_login_id
     *
     * @param integer $user_login_id
     * @return $this
     */
    public function setUserLoginId($user_login_id)
    {
        $this->user_login_id = $user_login_id;

        return $this;
    }

    /**
     * Method to set the value of field company_id
     *
     * @param integer $company_id
     * @return $this
     */
    public function setCompanyId($company_id)
    {
        $this->company_id = $company_id;

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
     * Method to set the value of field is_hosted
     *
     * @param integer $is_hosted
     * @return $this
     */
    public function setIsHosted($is_hosted)
    {
        $this->is_hosted = $is_hosted;

        return $this;
    }

    /**
     * Method to set the value of field is_private
     *
     * @param integer $is_private
     * @return $this
     */
    public function setIsPrivate($is_private)
    {
        $this->is_private = $is_private;

        return $this;
    }

    /**
     * Method to set the value of field is_deleted
     *
     * @param integer $is_deleted
     * @return $this
     */
    public function setIsDeleted($is_deleted)
    {
        $this->is_deleted = $is_deleted;

        return $this;
    }

    /**
     * Method to set the value of field is_hidden
     *
     * @param integer $is_hidden
     * @return $this
     */
    public function setIsHidden($is_hidden)
    {
        $this->is_hidden = $is_hidden;

        return $this;
    }

    /**
     * Method to set the value of field origin_url
     *
     * @param string $origin_url
     * @return $this
     */
    public function setOriginUrl($origin_url)
    {
        $this->origin_url = $origin_url;

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
     * Returns the value of field name_static
     *
     * @return string
     */
    public function getNameStatic()
    {
        return $this->name_static;
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
     * Returns the value of field folder_uuid
     *
     * @return string
     */
    public function getFolderUuid()
    {
        return $this->folder_uuid;
    }

    /**
     * Returns the value of field filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
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
     * Returns the value of field user_login_id
     *
     * @return integer
     */
    public function getUserLoginId()
    {
        return $this->user_login_id;
    }

    /**
     * Returns the value of field company_id
     *
     * @return integer
     */
    public function getCompanyId()
    {
        return $this->company_id;
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
     * Returns the value of field is_hosted
     *
     * @return integer
     */
    public function getIsHosted()
    {
        return $this->is_hosted;
    }

    /**
     * Returns the value of field is_private
     *
     * @return integer
     */
    public function getIsPrivate()
    {
        return $this->is_private;
    }

    /**
     * Returns the value of field is_deleted
     *
     * @return integer
     */
    public function getIsDeleted()
    {
        return $this->is_deleted;
    }

    /**
     * Returns the value of field is_hidden
     *
     * @return integer
     */
    public function getIsHidden()
    {
        return $this->is_hidden;
    }

    /**
     * Returns the value of field origin_url
     *
     * @return string
     */
    public function getOriginUrl()
    {
        return $this->origin_url;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("media");
        $this->hasMany('id', 'SMXD\Application\Models\MediaAttachment', 'media_id', ['alias' => 'MediaAttachment']);
        $this->belongsTo('media_type_id', 'SMXD\Application\Models\MediaType', 'id', ['alias' => 'MediaType']);
        $this->belongsTo('user_login_id', 'SMXD\Application\Models\UserLogin', 'id', ['alias' => 'UserLogin']);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Media[]|Media|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Media|\Phalcon\Mvc\Model\ResultInterface
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
        return 'media';
    }

}
