<?php

namespace Reloday\Application\CloudModels;

use Reloday\Application\Lib\ElasticSearchHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayDynamoORM;

class Media
{

    protected $_key = 'uuid';
    /**
     * Declare columns
     */
    protected $_schema = [
        'uuid' => 'S',
        'company_uuid' => 'S',
        'user_profile_uuid' => 'S',
        'folder_uuid' => 'S',
        'media_type_id' => 'N',
        'name' => 'S',
        'name_static' => 'S',
        'file_name' => 'S',
        'file_type' => 'S',
        'file_extension' => 'S',
        'mime_type' => 'S',
        'file_path' => 'S',
        'size' => 'N',
        'is_hosted' => 'N',
        'is_private' => 'N',
        'is_deleted' => 'N',
        'is_hidden' => 'N',
        'created_at' => 'N',
        'updated_at' => 'N',
    ];

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
    protected $name;

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
    protected $user_profile_uuid;

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
    protected $file_name;

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
     * @return null
     */
    public function getDefaultDynamoTableName()
    {
        return getenv('AWS_MEDIA_TABLE');
    }

    /**
     * @return null
     */
    public function getDefaultTableName()
    {
        return 'media';
    }

    /**
     * @return null
     */
    public function getDefaultIndexName()
    {
        return getenv('ELASTIC_SEARCH_PREFIX') . '_' . 'media_index';
    }

    /**
     * @return mixed
     */
    public function getFileName()
    {
        return $this->file_name;
    }

    /**
     * @param mixed $file_name
     */
    public function setFileName($file_name): void
    {
        $this->file_name = $file_name;
    }

    /**
     * @return mixed
     */
    public function getFileType()
    {
        return $this->file_type;
    }

    /**
     * @param mixed $file_type
     */
    public function setFileType($file_type): void
    {
        $this->file_type = $file_type;
    }

    /**
     * @return mixed
     */
    public function getFileExtension()
    {
        return $this->file_extension;
    }

    /**
     * @param mixed $file_extension
     */
    public function setFileExtension($file_extension): void
    {
        $this->file_extension = $file_extension;
    }

    /**
     * @return mixed
     */
    public function getFilePath()
    {
        return $this->file_path;
    }

    /**
     * @param mixed $file_path
     */
    public function setFilePath($file_path): void
    {
        $this->file_path = $file_path;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size): void
    {
        $this->size = $size;
    }

    /**
     * @return mixed
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param mixed $media_uuid
     */
    public function setUuid($uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * @return mixed
     */
    public function getCompanyUuid()
    {
        return $this->company_uuid;
    }

    /**
     * @param mixed $company_uuid
     */
    public function setCompanyUuid($company_uuid): void
    {
        $this->company_uuid = $company_uuid;
    }

    /**
     * @return mixed
     */
    public function getUserProfileUuid()
    {
        return $this->user_profile_uuid;
    }

    /**
     * @param mixed $user_profile_uuid
     */
    public function setUserProfileUuid($user_profile_uuid): void
    {
        $this->user_profile_uuid = $user_profile_uuid;
    }

    /**
     * @return mixed
     */
    public function getFolderUuid()
    {
        return $this->folder_uuid;
    }

    /**
     * @param mixed $folder_uuid
     */
    public function setFolderUuid($folder_uuid): void
    {
        $this->folder_uuid = $folder_uuid;
    }

    /**
     * @return mixed
     */
    public function getMediaTypeId()
    {
        return $this->media_type_id;
    }

    /**
     * @param mixed $media_type_id
     */
    public function setMediaTypeId($media_type_id): void
    {
        $this->media_type_id = $media_type_id;
    }

    /**
     * @return mixed
     */
    public function getFileInfo()
    {
        return $this->file_info;
    }

    /**
     * @param mixed $file_info
     */
    public function setFileInfo($file_info): void
    {
        $this->file_info = $file_info;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getNameStatic()
    {
        return $this->name_static;
    }

    /**
     * @param mixed $name_static
     */
    public function setNameStatic($name_static): void
    {
        $this->name_static = $name_static;
    }

    /**
     * @return mixed
     */
    public function getIsHosted()
    {
        return $this->is_hosted;
    }

    /**
     * @param mixed $is_hosted
     */
    public function setIsHosted($is_hosted): void
    {
        $this->is_hosted = $is_hosted;
    }

    /**
     * @return mixed
     */
    public function getIsDeleted()
    {
        return $this->is_deleted;
    }

    /**
     * @param mixed $is_deleted
     */
    public function setIsDeleted($is_deleted): void
    {
        $this->is_deleted = $is_deleted;
    }

    /**
     * @return mixed
     */
    public function getIsPrivate()
    {
        return $this->is_private;
    }

    /**
     * @param mixed $is_private
     */
    public function setIsPrivate($is_private): void
    {
        $this->is_private = $is_private;
    }

    /**
     * @return mixed
     */
    public function getIsHidden()
    {
        return $this->is_hidden;
    }

    /**
     * @param mixed $is_hidden
     */
    public function setIsHidden($is_hidden): void
    {
        $this->is_hidden = $is_hidden;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * @param mixed $created_at
     */
    public function setCreatedAt($created_at): void
    {
        $this->created_at = $created_at;
    }

    /**
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * @param mixed $updated_at
     */
    public function setUpdatedAt($updated_at): void
    {
        $this->updated_at = $updated_at;
    }

    /**
     * @return mixed
     */
    public function getMimeType()
    {
        return $this->mime_type;
    }

    /**
     * @param mixed $file_path
     */
    public function setMimeType($mime_type): void
    {
        $this->mime_type = $mime_type;
    }


}
