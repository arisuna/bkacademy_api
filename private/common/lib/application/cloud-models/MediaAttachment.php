<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 12/6/19
 * Time: 3:56 PM
 */

namespace Reloday\Application\CloudModels;

use Elasticsearch\Endpoints\Cat\Help;
use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\Helpers;

class MediaAttachment
{
    /**
     * Define key
     */
    protected $_key = 'uuid';

    protected $_schema = [
        'uuid' => 'S',
        'created_at' => 'N',
        'updated_at' => 'N',
        'media_uuid' => 'S',
        'object_type' => 'S',
        'object_uuid' => 'S',
        'user_profile_uuid' => 'S',
        'is_shared' => 'N',
        'file_info' => 'M',
        'folder_uuid' => 'S',
        'sharer_uuid' => 'S',
        'company_id' => 'N',
        'owner_uuid' => 'S',
        'employee_id' => 'N'
    ];

    protected $uuid;

    protected $folder_uuid;

    protected $owner_uuid;

    protected $created_at;

    protected $updated_at;

    protected $media_uuid;

    protected $file_info;

    protected $object_uuid;

    protected $user_profile_uuid;

    protected $object_type;

    /**
     * @return null
     */
    public function getDefaultTableName()
    {
        return 'media_attachment';
    }

    /**
     * @return null
     */
    public function getDefaultIndexName()
    {
        return getenv('ELASTIC_SEARCH_PREFIX') . '_' . 'attachment_index';
    }

    /**
     * @return null
     */
    public function getDefaultDynamoTableName()
    {
        return getenv('AWS_MEDIA_ATTACHMENT_TABLE');
    }

    /**
     * @return mixed
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param mixed $uuid
     */
    public function setUuid($uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * @return mixed
     */
    public function getMediaUuid()
    {
        return $this->media_uuid;
    }

    /**
     * @param mixed $media_uuid
     */
    public function setMediaUuid($media_uuid): void
    {
        $this->media_uuid = $media_uuid;
    }

    /**
     * @return mixed
     */
    public function getObjectType()
    {
        return $this->object_type;
    }

    /**
     * @param mixed $object_type
     */
    public function setObjectType($object_type): void
    {
        $this->object_type = $object_type;
    }

    /**
     * @return mixed
     */
    public function getObjectUuid()
    {
        return $this->object_uuid;
    }

    /**
     * @param mixed $object_uuid
     */
    public function setObjectUuid($object_uuid): void
    {
        $this->object_uuid = $object_uuid;
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
    public function getisShared()
    {
        return $this->is_shared;
    }

    /**
     * @param mixed $is_shared
     */
    public function setIsShared($is_shared): void
    {
        $this->is_shared = $is_shared;
    }

    /**
     * @return mixed
     */
    public function getSharerUuid()
    {
        return $this->sharer_uuid;
    }

    /**
     * @param mixed $sharer_uuid
     */
    public function setSharerUuid($sharer_uuid): void
    {
        $this->sharer_uuid = $sharer_uuid;
    }

    /**
     * @return mixed
     */
    public function getCompanyId()
    {
        return $this->company_id;
    }

    /**
     * @param mixed $company_id
     */
    public function setCompanyId($company_id): void
    {
        $this->company_id = $company_id;
    }

    /**
     * @return mixed
     */
    public function getEmployeeId()
    {
        return $this->employee_id;
    }

    /**
     * @param mixed $employee_id
     */
    public function setEmployeeId($employee_id): void
    {
        $this->employee_id = $employee_id;
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

    public function toArray()
    {

    }

    /**
     * @param $dynamoObject
     */
    public static function __convertToArray($dynamoObject)
    {
        $dynamoObjectArray = $dynamoObject->asArray();
        $dynamoObjectArray['file_info'] = Helpers::__mapArrayToArrayData($dynamoObject['file_info']);
        return $dynamoObjectArray;
    }
}
