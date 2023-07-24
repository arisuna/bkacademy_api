<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 12/6/19
 * Time: 3:56 PM
 */

namespace SMXD\Application\CloudModels;

use Aws\DynamoDb\Exception\DynamoDbException;
use Phalcon\Http\Client\Provider\Exception;
use SMXD\Application\Lib\DynamoHelper;
use SMXD\Application\Lib\ElasticSearchHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\RelodayDynamoORM;
use SMXD\Application\Lib\RelodayQueue;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\EmployeeExt;
use SMXD\Application\Models\UserProfileExt;
use SMXD\Gms\Models\ModuleModel;

class MediaAttachmentExt extends MediaAttachment
{
    const MEDIA_GROUP_IMAGE = 'image';
    const MEDIA_GROUP_PREVIEW = 'preview';

    const MEDIA_OBJECT_DEFAULT_NAME = 'document';

    const RETURN_TYPE_OBJECT = "object";
    const RETURN_TYPE_ARRAY = "array";

    const IS_SHARED_YES = true;
    const IS_SHARED_FALSE = false;

    const LIMIT_PER_PAGE = 50;


    /**
     * @return mixed
     * @throws \Exception
     */
    public function getMedia()
    {
        return MediaExt::findFirstByUuid($this->getMediaUuid());
    }

    /**
     * @return mixed
     */
    public function getEmployee()
    {
        return EmployeeExt::findFirstByUuid($this->getUserProfileUuid());
    }

    /**
     * @return mixed
     */
    public function getUserProfile()
    {
        return UserProfileExt::findFirstByUuid($this->getUserProfileUuid());
    }

    /**
     * @param $array
     * @return array
     */
    public function convertFileInfoArray($array = [])
    {
        $item = [];
        foreach ($array as $key => $value) {
            if ($key == 'size' || $key == 'is_deleted') {
                $item[$key] = ['N' => $value];
            } else {
                $item[$key] = ['S' => $value];
            }
        }
        return $item;
    }

    /**
     * Quick create DynamoDB and Elastic
     * @return \Aws\Result
     * @throws \Exception
     */
    public function __quickCreate()
    {
        /** DYNAMO DB CREATE*/
        $dynamoMediaAttachment = RelodayDynamoORM::factory('\SMXD\Application\DynamoDb\ORM\DynamoMediaAttachment')->create();
        $dynamoMediaAttachment->setUuid($this->getUuid());
        $dynamoMediaAttachment->setObjectUuid($this->getObjectUuid());
        $dynamoMediaAttachment->setObjectType($this->getObjectType());
        $dynamoMediaAttachment->setMediaUuid($this->getMediaUuid());
        $dynamoMediaAttachment->setIsShared($this->getisShared());
        $dynamoMediaAttachment->setSharerUuid($this->getSharerUuid());
        $dynamoMediaAttachment->setUserProfileUuid($this->getUserProfileUuid());
        $dynamoMediaAttachment->setFileInfo($this->convertFileInfoArray($this->getFileInfo()));
        if ($this->getEmployeeId()) {
            $dynamoMediaAttachment->setEmployeeId(intval($this->getEmployeeId()));
            $dynamoMediaAttachment->setCompanyId(null);
        }

        if ($this->getCompanyId()) {
            $dynamoMediaAttachment->setCompanyId(intval($this->getCompanyId()));
            $dynamoMediaAttachment->setEmployeeId(null);
        }

        $dynamoMediaAttachment->setCreatedAt(time());
        $dynamoMediaAttachment->setUpdatedAt(time());


        try {
            $resultMediaAttachment = $dynamoMediaAttachment->save();
        } catch (\Exception $e) {
            $return['detail'] = $e->getMessage();
            $return['success'] = false;
            $return['message'] = "DATA_SAVE_FAIL_TEXT";
            goto end_of_function;
        }


//        $this->addToQueueElastic();

        $return['success'] = true;
        $return['message'] = 'DATA_SAVE_SUCCESS_TEXT';

        end_of_function:
        return $return;
    }


    /**
     * Quick update DynamoDB and Elastic
     * @return \Aws\Result
     * @throws \Exception
     */
    public function __quickUpdate()
    {
        /** DYNAMO DB CREATE*/
        $mediaAttachment = RelodayDynamoORM::factory('\SMXD\Application\DynamoDb\ORM\DynamoMediaAttachment')
            ->findOne($this->getUuid());
        $data = $this->__toArray();
        foreach ($data as $key => $value) {
            if ($key != $this->_key && array_key_exists($key, $this->_schema)) {
                $mediaAttachment->$key = $value;
            }
        }

        try {
            $resultMediaAttachment = $mediaAttachment->save();
        } catch (\Exception $e) {
            $return['detail'] = $e->getMessage();
            $return['success'] = false;
            $return['message'] = "DATA_SAVE_FAIL_TEXT";
            goto end_of_function;
        }


        unset($data['uuid']);
        $data['file_info'] = DynamoHelper::__mapArrayToArrayData($data['file_info']);

//        $this->addToQueueElastic();

        $return['success'] = true;
        $return['message'] = 'DATA_SAVE_SUCCESS_TEXT';

        end_of_function:
        return $return;
    }

    /**
     * Quick remove DynamoDB and Elastic
     * Safe deleted
     * @return \Aws\Result
     * @throws \Exception
     */
    public function __quickRemove()
    {
        $dynamoMediaAttachment = RelodayDynamoORM::factory('\SMXD\Application\DynamoDb\ORM\DynamoMediaAttachment')
            ->findOne($this->getUuid());

        try {
            $resultMediaAttachmentDynamoDb = $dynamoMediaAttachment->delete();
        } catch (\Exception $e) {
            $return['success'] = false;
            $return['detail'] = $e->getMessage();
            $return['message'] = "DATA_DELETE_FAIL_TEXT";
            goto end_of_function;
        }


        $return['success'] = true;
        $return['message'] = 'DATA_DELETE_SUCCESS_TEXT';

        end_of_function:
        return $return;
    }

    /**
     * Parsed object to Array
     * @return array
     */
    public function __toArray()
    {
        $items = [];
        foreach (array_keys($this->_schema) as $val) {
            if ($val) {
                $items[$val] = $this->$val;
            }
        }
        return $items;
    }

    /**
     * Set data to object
     * @param array $array
     */
    public static function __setData($array = [])
    {
        $_this = new static();
        foreach ($array as $key => $value) {
            if (array_key_exists($key, $_this->_schema)) {
                $_this->$key = $value;
            }
        }
        return $_this;
    }


    /**
     * [get media list attached to a data with uuid]
     * @param string $uuid [description]
     * @return [type]       [description]
     */
    public static function __get_attachments_from_uuid($objectUuid = '',
                                                       $objectName = '',
                                                       $objectShared = null,
                                                       $userProfileUuid = '',
                                                       $excludeUserProfileUuid = '',
                                                       $requestEntityUuid = '')
    {

        (new static)::__init();
        $className = get_called_class();
        $attachments = (new static)::factory($className);

        if (isset($objectUuid) && is_string($objectUuid) && Helpers::__isValidUuid($objectUuid)) {
            $attachments->index('ObjectUuidTypeIndex')
                ->where('object_uuid', $objectUuid);
        }

        if (isset($objectName) && is_string($objectName) && $objectName != '') {
            $attachments->where('object_type', $objectName);
        }

        if ($objectShared === self::IS_SHARED_FALSE || $objectShared === self::IS_SHARED_YES) {
            $attachments->filter('is_shared', $objectShared ? ModelHelper::YES : ModelHelper::NO);
        }

        if ($userProfileUuid != '' && Helpers::__isValidUuid($userProfileUuid)) {
            $attachments->filter('user_profile_uuid', $userProfileUuid);
        }

        if ($excludeUserProfileUuid != '' && Helpers::__isValidUuid($excludeUserProfileUuid)) {
            $attachments->filter('user_profile_uuid', '!=', $excludeUserProfileUuid);
        }


        $result = $attachments->findMany();

        $medias = [];
        if (count($result) > 0) {
            foreach ($result as $attachment) {
                if ($attachment->getMedia()) {
                    $item = $attachment->getMedia()->toArray();
                    $item['name'] = $attachment->getMedia()->getNameOfficial();
                    $item['image_data']['url_thumb'] = $attachment->getMedia()->getUrlThumb();
                    $item['image_data']['url_token'] = $attachment->getMedia()->getUrlToken();
                    $item['image_data']['url_full'] = $attachment->getMedia()->getUrlFull();
                    $item['image_data']['url_download'] = $attachment->getMedia()->getUrlDownload();

                    $item['url_thumb'] = $attachment->getMedia()->getUrlThumb();
                    $item['url_token'] = $attachment->getMedia()->getUrlToken();
                    $item['url_full'] = $attachment->getMedia()->getUrlFull();
                    $item['url_download'] = $attachment->getMedia()->getUrlDownload();
                    $item['url_backend'] = $attachment->getMedia()->getBackendUrl();

                    if ($requestEntityUuid != '' && Helpers::__isValidUuid($requestEntityUuid)) {
                        $item['can_delete'] = ($attachment->getMedia() && $attachment->getMedia()->getCompany() ? ($attachment->getMedia()->getCompany()->getUuid() == $requestEntityUuid) : false);
                    } else {
                        $item['can_delete'] = true;
                    }
                    $medias[] = $item;
                }
            }
        }
        return $medias;
    }


    /**
     * [get media list attached to a data with uuid]
     * @param string $uuid [description]
     * @return [type]       [description]
     */
    public static function __getObjectMediaListFromUuid($objectUuid = '', $objectType = '', $objectShared = null)
    {

        $queryMust = [];

        $queryMust[] = ['term' => ['object_uuid' => $objectUuid]];
        if ($objectType != '') {
            $queryMust[] = ['term' => ['object_type' => $objectType]];
        }
        if ($objectShared === self::IS_SHARED_FALSE || $objectShared === self::IS_SHARED_YES) {
            $queryMust[] = ['term' => ['is_shared' => $objectShared ? ModelHelper::YES : ModelHelper::NO]];
        }

        try {
            $params = [
                'index' => (new static)->getDefaultIndexName(),
                'type' => (new static)->getDefaultTableName(),
                'body' => [
                    'query' => [
                        "bool" => [
                            "must" => $queryMust,
                        ]
                    ],
                    'sort' => ['created_at' => 'desc']
                ],
            ];

            $results = ElasticSearchHelper::query($params);
            if ($results['success'] == false) {
                return $results;
            }

            $medias = [];
            if (count($results['data']) > 0) {
                foreach ($results as $attachment) {
                    $object = self::__setData($attachment);
                    if ($object->getMedia()) {
                        $item = $object->getMedia();
                        $medias[] = $item;
                    }
                }
            }
            return $medias;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * create Attachments without
     * @param $params
     */
    public static function __createAttachments($params)
    {
        $objectUuid = isset($params['objectUuid']) && $params['objectUuid'] != '' ? $params['objectUuid'] : '';
        $object = isset($params['object']) && $params['object'] != null ? $params['object'] : null;
        $mediaList = isset($params['fileList']) && is_array($params['fileList']) ? $params['fileList'] : [];
        if (count($mediaList) == 0) {
            $mediaList = isset($params['files']) && is_array($params['files']) ? $params['files'] : [];
        }
        $objectName = isset($params['objectName']) && $params['objectName'] != '' ? $params['objectName'] : self::MEDIA_OBJECT_DEFAULT_NAME;
        $isShared = isset($params['isShared']) && is_bool($params['isShared']) ? $params['isShared'] : self::IS_SHARED_FALSE;

        $userProfile = isset($params['userProfile']) ? $params['userProfile'] : null;
        $ownerCompany = isset($params['ownerCompany']) && is_object($params['ownerCompany']) && $params['ownerCompany'] != null ? $params['ownerCompany'] : null;
        $employee = isset($params['employee']) && is_object($params['employee']) && $params['employee'] != null ? $params['employee'] : null;
        $counterPartyCompany = isset($params['counterPartyCompany']) && is_object($params['counterPartyCompany']) && $params['counterPartyCompany'] != null ? $params['counterPartyCompany'] : null;


        if ($objectUuid == '' && !is_null($object) && is_object($object) && method_exists($object, 'getUuid')) {
            $objectUuid = $object->getUuid();
        }
        if ($objectUuid == '' && !is_null($object) && is_array($object) && isset($object['uuid'])) {
            $objectUuid = $object['uuid'];
        }

        if ($objectUuid != '') {
            $items = [];
            foreach ($mediaList as $attachment) {
                if (isset(ModuleModel::$company) && ModuleModel::$company && is_null($employee) && is_null($counterPartyCompany)) {
                    $attachResult = MediaAttachmentExt::__createAttachment([
                        'objectUuid' => $objectUuid,
                        'file' => $attachment,
                        'userProfile' => $userProfile,
                        'sharerActor' => ModuleModel::$company,
                        'is_shared' => false
                    ]);
                }

                if (isset($employee) && $employee) {
                    $attachResult = MediaAttachmentExt::__createAttachment([
                        'objectUuid' => $objectUuid,
                        'file' => $attachment,
                        'userProfile' => $userProfile,
                        'sharerActor' => $employee,
                        'is_shared' => true
                    ]);
                }

                if (isset($counterPartyCompany) && $counterPartyCompany) {
                    $attachResult = MediaAttachmentExt::__createAttachment([
                        'objectUuid' => $objectUuid,
                        'file' => $attachment,
                        'userProfile' => $userProfile,
                        'sharerActor' => $counterPartyCompany,
                        'is_shared' => true
                    ]);
                }
                if ($attachResult['success'] == true) {
                    //share to my own company
                    $mediaAttachment = $attachResult['data'];
                    $items[] = $mediaAttachment;

                } else {
                    $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $attachResult];
                    goto end_of_function;
                }
            }
            $return = ['success' => true, 'data' => $items, '$ownerCompany' => isset($ownerCompany) ? $ownerCompany : null];
        }
        end_of_function:
        return $return;
    }

    /**
     * @param $params
     * @return array
     */
    public static function __createAttachment($params)
    {
        $objectUuid = isset($params['objectUuid']) && $params['objectUuid'] != '' ? $params['objectUuid'] : '';
        $object = isset($params['object']) && $params['object'] != null ? $params['object'] : null;
        $mediaFile = isset($params['file']) && (is_array($params['file']) || is_object($params['file'])) ? $params['file'] : null;
        $isShared = isset($params['isShared']) && is_bool($params['isShared']) ? $params['isShared'] : self::IS_SHARED_FALSE;
        $objectName = isset($params['objectName']) && $params['objectName'] != '' ? $params['objectName'] : null;
        $companyUuid = isset($params['companyUuid']) && $params['companyUuid'] != '' ? $params['companyUuid'] : null;
        $userProfileUuid = isset($params['userProfileUuid']) && $params['userProfileUuid'] != '' ? $params['userProfileUuid'] : null;

        if ($objectUuid == '' && !is_null($object) && is_object($object) && method_exists($object, 'getUuid')) {
            $objectUuid = $object->getUuid();
        }

        if ($objectUuid == '' && !is_null($object) && is_array($object) && isset($object['uuid'])) {
            $objectUuid = $object['uuid'];
        }

        $params = [
            'objectUuid' => $objectUuid,
            'media' => $mediaFile,
            'objectName' => $objectName,
            'isShared' => $isShared,
            'userProfileUuid' => $userProfileUuid,
            'companyUuid' => $companyUuid
        ];

        $result = self::__createAttachmentToObjectUuid($params);
        if ($result['success'] == true) {
            return $result;
        } else {
            return $result;
        }
    }


    /**
     * @param $objectUuid
     * @param $mediaUuid
     * @param $objectName
     * @return mixed
     */
    public static function __findByObjectAndMediaId(String $objectUuid, String $mediaUuid)
    {
        $attachment = RelodayDynamoORM::factory('\SMXD\Application\DynamoDb\ORM\DynamoMediaAttachment')
            ->index('ObjectUuidMediaUuidIndex')
            ->where('object_uuid', $objectUuid)
            ->where('media_uuid', $mediaUuid);
        return $attachment->findFirst();
    }


    /**
     * @param $mainObject
     * @param $media
     * @param null $userProfile
     * @return array
     * @throws \Exception
     */
    public static function __createAttachmentToObject($params = [])
    {
        $mainObject = $params['object'];
        $mediaObject = $params['media'];
        $objectName = $params['objectName'];
        $userProfile = $params['userProfile'];
        $sharerActor = $params['sharerActor'];
        $is_shared = $params['is_shared'];
        $employeeId = $params['employeeId'];
        $companyId = $params['companyId'];

        if (!$mainObject && $mainObject->getUuid()) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => "OBJECT_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }

        if (is_null($mediaObject)) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'mediaObject' => $mediaObject,
                'message' => "MEDIA_FILE_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }


        $objectUuid = $mainObject->getUuid();

        $mediaUuid = '';
        if (is_object($mediaObject) && method_exists($mediaObject, 'getUuid') && Helpers::__isValidUuid($mediaObject->getUuid())) {
            $mediaUuid = ($mediaObject->getUuid());
            $mediaFullName = $mediaObject->getName() . '.' . $mediaObject->getFileExtension();
        } elseif (is_object($mediaObject) && property_exists($mediaObject, 'uuid') && $mediaObject->uuid != null) {
            $mediaUuid = $mediaObject->uuid;
            $mediaFullName = $mediaObject->name . '.' . $mediaObject->file_extension;
        } elseif (is_array($mediaObject) && isset($mediaObject['id']) && $mediaObject['id'] > 0) {
            $mediaUuid = ($mediaObject['uuid']);
            $mediaFullName = $mediaObject['name'] . '.' . $mediaObject['file_extension'];
        }

        if (is_null($mediaUuid) || $mediaUuid == '') {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => "MEDIA_ID_NOT_FOUND_TEXT",
                'type' => 'FAIL'
            ];
        }

        if (!$employeeId && !$companyId) {
            return [
                'success' => false,
                'objectUuid' => $objectUuid,
                'action' => __METHOD__,
                'message' => "SHARER_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }

        $mediaAttachment = self::__findByObjectAndMediaId($objectUuid, $mediaUuid);

        if ($mediaAttachment) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => 'MEDIA_ALREADY_ATTACHED_TEXT',
                'type' => 'FAIL',
                'file_name' => $mediaFullName
            ];
        }

        if (is_array($mediaObject)) {
            $mediaObject = MediaExt::findFirstByUuid($mediaUuid);
        } elseif (is_object($mediaObject) && property_exists($mediaObject, 'uuid')) {
            $mediaObject = MediaExt::findFirstByUuid($mediaUuid);
        }

        if (!$mediaObject) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => "MEDIA_NOT_FOUND_TEXT",
                'type' => 'FAIL'
            ];
        }

        $objectName = $objectName ?? (method_exists($mainObject, 'getSource') ? $mainObject->getSource() : self::MEDIA_OBJECT_DEFAULT_NAME);

        $mediaAttachment = new self();

        $mediaAttachment->setUuid(ApplicationModel::uuid());
        $mediaAttachment->setObjectUuid($objectUuid);
        $mediaAttachment->setObjectType($objectName);
        $mediaAttachment->setMediaUuid($mediaObject->getUuid());
        $mediaAttachment->setIsShared($is_shared == true ? intval(ModelHelper::YES) : intval(ModelHelper::NO));
        $mediaAttachment->setSharerUuid($sharerActor->getUuid());
        $mediaAttachment->setFileInfo($mediaObject->getFileInfoArrayToElasticSearch());
        if (method_exists($sharerActor, 'isEmployee') && $sharerActor->isEmployee() == true) {
            $mediaAttachment->setEmployeeId(intval($sharerActor->getId()));
            $mediaAttachment->setCompanyId(null);
        }

        if (method_exists($sharerActor, 'isGms') && $sharerActor->isGms() == true) {
            $mediaAttachment->setCompanyId(intval($sharerActor->getId()));
            $mediaAttachment->setEmployeeId(null);
        }

        if (method_exists($sharerActor, 'isHr') && $sharerActor->isHr() == true) {
            $mediaAttachment->setCompanyId(intval($sharerActor->getId()));
            $mediaAttachment->setEmployeeId(null);
        }
        $mediaAttachment->setCreatedAt(time());
        $mediaAttachment->setUpdatedAt(time());

        if (is_array($userProfile) &&
            isset($userProfile['uuid']) &&
            $userProfile['uuid'] != '') {
            $mediaAttachment->setUserProfileUuid($userProfile['uuid']);
        } else if (is_object($userProfile) && method_exists($userProfile, 'getUuid')) {
            $mediaAttachment->setUserProfileUuid($userProfile->getUuid());
        }

        $resultCreate = $mediaAttachment->__quickCreate();

        if ($resultCreate['success'] == true) {
            return [
                'success' => true,
                'action' => __METHOD__,
                'message' => "ATTACH_SUCCESS_TEXT",
                'type' => 'SUCCESS',
                'data' => $mediaAttachment
            ];
        } else {
            return $resultCreate;
        }
    }

    /**
     * [save all attachment with uuid]
     * @param  [type] $object      [description]
     * @param array $media_list [description]
     * @param string $objectName [description]
     * @return [type]              [description]
     * @throws \Exception
     */
    public static function __createAttachmentToObjectUuid($params = [])
    {
        $objectUuid = $params['objectUuid'];
        $media = $params['media'];
        $objectName = $params['objectName'];
        $isShared = $params['isShared'];
        $companyUuid = $params['companyUuid'];
        $userProfileUuid = $params['userProfileUuid'];

        if ($objectUuid == '') {
            return [
                'success' => false,
                'errorType' => 'objectUuidNotFound',
                'objectUuid' => $objectUuid,
                'action' => __METHOD__,
                'message' => "OBJECT_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }

        if (is_null($media)) {
            return [
                'success' => false,
                'errorType' => 'mediaNotFound',
                'objectUuid' => $objectUuid,
                'action' => __METHOD__,
                'message' => "MEDIA_FILE_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }


        if (is_object($media) && method_exists($media, 'getUuid') && Helpers::__isValidUuid($media->getUuid())) {
            $mediaUuid = ($media->getUuid());
            $mediaFullName = $media->getName() . '.' . $media->getFileExtension();
        } elseif (is_object($media) && property_exists($media, 'uuid') && $media->uuid != null) {
            $mediaUuid = $media->uuid;
            $mediaFullName = $media->name . '.' . $media->file_extension;
        } elseif (is_array($media) && isset($media['uuid']) && $media['uuid'] != '') {
            $mediaUuid = ($media['uuid']);
            $mediaFullName = $media['name'] . '.' . $media['file_extension'];
        }


        if (is_null($mediaUuid) || $mediaUuid == '') {
            return [
                'errorType' => 'mediaUuidNotFound',
                'success' => false,
                'action' => __METHOD__,
                'message' => "MEDIA_ID_NOT_FOUND_TEXT",
                'type' => 'FAIL'
            ];
        }

        if (!$companyUuid) {
            return [
                'success' => false,
                'errorType' => 'creatorCompanyUuidNotFound',
                'objectUuid' => $objectUuid,
                'action' => __METHOD__,
                'message' => "SHARER_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }

        $mediaAttachment = self::__findByObjectAndMediaId($objectUuid, $mediaUuid);

        if ($mediaAttachment) {
            return [
                'success' => false,
                'errorType' => 'mediaAlreadyAttached',
                'action' => __METHOD__,
                'message' => 'MEDIA_ALREADY_ATTACHED_TEXT',
                'type' => 'FAIL',
                'file_name' => $mediaFullName
            ];
        }

        if (is_array($media)) {
            $mediaObject = MediaExt::findFirstByUuid($mediaUuid);
        } elseif (is_object($media) && method_exists($media, 'getUuid')) {
            $mediaObject = $media;
        } elseif (is_object($media) && property_exists($media, 'uuid')) {
            $mediaObject = MediaExt::findFirstByUuid($mediaUuid);
        }

        if (!$mediaObject) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'errorType' => 'mediaObjectNotExist',
                'message' => "MEDIA_NOT_FOUND_TEXT",
                'type' => 'FAIL'
            ];
        }

        $objectName = $objectName ?? self::MEDIA_OBJECT_DEFAULT_NAME;

        $mediaAttachment = new self();

        $mediaAttachment->setUuid(ApplicationModel::uuid());
        $mediaAttachment->setObjectUuid($objectUuid);
        $mediaAttachment->setObjectType($objectName);
        $mediaAttachment->setMediaUuid($mediaObject->getUuid());
        $mediaAttachment->setIsShared($isShared == true ? intval(ModelHelper::YES) : intval(ModelHelper::NO));
        $mediaAttachment->setFileInfo($mediaObject->getFileInfoArrayToElasticSearch());
        $mediaAttachment->setUserProfileUuid($userProfileUuid);
        $mediaAttachment->setCreatedAt(time());
        $mediaAttachment->setUpdatedAt(time());


        $resultCreate = $mediaAttachment->__quickCreate();

        if ($resultCreate['success'] == true) {
            return [
                'success' => true,
                'action' => __METHOD__,
                'message' => "ATTACH_SUCCESS_TEXT",
                'type' => 'SUCCESS',
                'data' => $mediaAttachment
            ];
        } else {
            $resultCreate['errorType'] = 'canNotCreateAttachment';
            return $resultCreate;
        }
    }

    /**
     * @return array
     */
    public function setForceShared()
    {
        $this->setIsShared(intval(ModelHelper::YES));
        return $this->__quickUpdate();
    }

    /**
     * Find first by uuid (Using DynamoDb to find first)
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public static function findFirstByUuid($uuid)
    {
        $mediaAttachment = RelodayDynamoORM::factory('\SMXD\Application\DynamoDb\ORM\DynamoMediaAttachment')->findOne($uuid);
        if ($mediaAttachment != null) {
            $arr = $mediaAttachment->asArray();
            return self::__setData($arr);
        } else {
            return null;
        }
    }

    /**
     * Find first by uuid (Using DynamoDb to find first)
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public static function __findAllByObjectUuid($uuid)
    {
        $items = [];
        try {
            RelodayDynamoORM::__init();
            $ormContainer = RelodayDynamoORM::factory('\SMXD\Application\DynamoDb\ORM\DynamoMediaAttachment');
            $mediaAttachments = $ormContainer->index('ObjectUuidCreatedAtIndex')->where('object_uuid', $uuid)->findMany();
        } catch (DynamoDbException $e) {
            $return = ['success' => false, 'detail' => $e->getMessage(), 'message' => 'CLEAN_REMINDER_FAIL_TEXT',];
            return $return;
        } catch (AwsException $e) {
            $return = ['success' => false, 'detail' => $e->getMessage(), 'message' => 'CLEAN_REMINDER_FAIL_TEXT',];
            return $return;
        } catch (Exception $e) {
            $return = ['success' => false, 'detail' => $e->getMessage(), 'message' => 'CLEAN_REMINDER_FAIL_TEXT',];
            return $return;
        }
        if ($mediaAttachments != null) {
            foreach ($mediaAttachments as $mediaAttachment) {
                $items[] = self::__setData($mediaAttachment->asArray());
            }
            return $items;
        } else {
            return [];
        }
    }

    /**
     * @return mixed
     */
    public function updateToElastic()
    {
        $data = $this->__toArray();
        unset($data['uuid']);
        $data['file_info'] = DynamoHelper::__mapArrayToArrayData($data['file_info']);
        $params = [
            'index' => $this->getDefaultIndexName(),
            'type' => $this->getDefaultTableName(),
            'id' => $this->getUuid(),
            'body' => [
                'doc' => $data
            ]
        ];
        return ElasticSearchHelper::__update($params);
    }

    /**
     * @return mixed
     */
    public function getFromElastic()
    {
        $params = [
            'index' => $this->getDefaultIndexName(),
            'type' => $this->getDefaultTableName(),
            'id' => $this->getUuid(),
        ];
        return ElasticSearchHelper::__getData($params);
    }

    /**
     * @return array
     */
    public function addToQueueElastic()
    {
        $beanQueue = new RelodayQueue(getenv('QUEUE_MEDIA_ATTACHMENTS'));
        return $beanQueue->addQueue([
            'action' => 'sync',
            'uuid' => $this->getUuid()
        ]);
    }
}
