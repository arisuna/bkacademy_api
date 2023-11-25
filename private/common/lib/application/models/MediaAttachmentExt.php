<?php


namespace SMXD\Application\Models;

use Elasticsearch\Endpoints\Cat\Help;
use Phalcon\Db;
use Phalcon\Http\Client\Provider\Exception;
use \Phalcon\Mvc\Model\Transaction\Failed as TransactionFailed;
use \Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\MediaExt as MediaExt;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;
use SMXD\Application\Traits\ModelTraits;

class MediaAttachmentExt extends MediaAttachment
{
    use ModelTraits;

    const MEDIA_GROUP_AVATAR = 'avatar';
    const MEDIA_GROUP_LOGO = 'logo';
    const MEDIA_GROUP_INVOICE_LOGO = 'invoice_logo';
    const MEDIA_GROUP_IMAGE = 'image';
    const MEDIA_GROUP_PREVIEW = 'preview';

    const MEDIA_OBJECT_DEFAULT_NAME = 'document';
    const MEDIA_OBJECT_RELOCATION_SERVICE_SPECIFIC_FIELDS = 'relocation_service_specific_fields';

    const RETURN_TYPE_OBJECT = "object";
    const RETURN_TYPE_ARRAY = "array";

    const IS_SHARED_YES = true;
    const IS_SHARED_FALSE = false;

    const IS_THUMB_YES = 1;
    const IS_THUMB_FALSE = 0;

    const LIMIT_PER_PAGE = 50;

    /**
     * @var array
     */
    static $media_groups = [
        self::MEDIA_GROUP_AVATAR, self::MEDIA_GROUP_LOGO, self::MEDIA_GROUP_INVOICE_LOGO
    ];

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
        $this->belongsTo('media_id', 'SMXD\Application\Models\MediaExt', 'id', [
            'alias' => 'Media',
            'cache' => [
                'key' => 'MEDIA_OBJECT_' . $this->getMediaId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);

        $this->belongsTo('user_uuid', 'SMXD\Application\Models\UserExt', 'uuid', [
            'alias' => 'User',
        ]);

        $this->belongsTo('media_id', 'SMXD\Application\Models\MediaExt', 'id', [
            'alias' => 'Media',
        ]);

        $this->belongsTo('owner_company_id', 'SMXD\Application\Models\CompanyExt', 'id', [
            'alias' => 'Company'
        ]);

    }

    /**
     * [getTable description]
     * @return [type] [description]
     */
    static function getTable()
    {
        $instance = new MediaAttachment();
        return $instance->getSource();
    }

    /**
     * [before validate model]
     * @return [type] [description]
     */
    public function beforeValidation()
    {
        $validator = new Validation();

        $validator->add([
            'uuid',
        ], new UniquenessValidator([
                'model' => $this,
                'message' => 'UUID_REQUIRED_TEXT'
            ])
        );

        $validator->add([
            'object_uuid',
        ], new PresenceOfValidator([
                'model' => $this,
                'message' => 'OBJECT_UUID_REQUIRED_TEXT'
            ])
        );

        $validator->add([
            'media_uuid',
        ], new PresenceOfValidator([
                'model' => $this,
                'message' => 'MEDIA_UUID_REQUIRE_TEXT'
            ])
        );

        $validator->add([
            'object_uuid',
            'media_uuid',
        ], new UniquenessValidator([
                'model' => $this,
                'message' => 'MEDIA_ALREADY_ATTACHED_TEXT'
            ])
        );
        return $this->validate($validator);
    }


    /**
     * @return array
     */
    public function __quickRemove()
    {
        return ModelHelper::__quickRemove($this);
    }

    /**
     * @return array
     */
    public function __quickCreate()
    {
        return ModelHelper::__quickCreate($this);
    }

    /**
     * @return array
     */
    public function __quickUpdate()
    {
        return ModelHelper::__quickUpdate($this);
    }

    /**
     * @return array
     */
    public function __quickSave()
    {
        return ModelHelper::__quickSave($this);
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {
        ModelHelper::__setData($this, $custom);
    }

    /**
     * @param $options
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Application\Models\MediaExt', 'Media');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\SMXD\Application\Models\MediaAttachmentExt', 'Media.id = MediaAttachment.media_id', 'MediaAttachment');

        $queryBuilder->where('Media.is_deleted = :media_is_deleted_no:', [
            'media_is_deleted_no' => ModelHelper::NO
        ]);
        $queryBuilder->groupBy('Media.uuid');

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Media.name LIKE :query: OR Media.name_static LIKE :query: OR Media.filename LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }
//
//        if (isset($options['user_uuid']) && Helpers::__isValidUuid($options['user_uuid'])) {
//            $queryBuilder->andwhere("MediaAttachment.user_uuid = :user_uuid:", [
//                    'user_uuid' => $options['user_uuid'],
//                ]
//            );
//        }

        if (isset($options['object_name']) && is_string($options['object_name']) && $options['object_name'] != '') {
            $queryBuilder->andwhere("MediaAttachment.object_name = :object_name:", [
                    'object_name' => $options['object_name'],
                ]
            );
        }

        if (isset($options['object_uuid']) && Helpers::__isValidUuid($options['object_uuid'])) {
            $queryBuilder->andwhere("MediaAttachment.object_uuid = :object_uuid:", [
                    'object_uuid' => $options['object_uuid'],
                ]
            );
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['MediaAttachment.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['MediaAttachment.created_at DESC']);
                }
            }
        } else {
            $queryBuilder->orderBy(['MediaAttachment.created_at DESC']);
        }


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $items = [];

            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $mediaObject) {
                    $item = $mediaObject->toArray();
                    $item['name'] = $mediaObject->getNameOfficial();

                    $token = base64_encode(ModuleModel::$user_token);

                    $item['media_attachment_uuid'] = $mediaObject->getUuid();
                    $item['media_uuid'] = $mediaObject->getMediaUuid();

                    $item['image_data']['url_thumb'] = $mediaObject->getUrlThumb($token);
                    $item['image_data']['url_token'] = $mediaObject->getUrlToken($token);
                    $item['image_data']['url_full'] = $mediaObject->getUrlFull($token);
                    $item['image_data']['url_download'] = $mediaObject->getUrlDownload($token);

                    $item['url_thumb'] = $mediaObject->getUrlThumb($token);
//                    $item['is_thumb'] = $mediaObject->getIsThumb();
                    $item['url_token'] = $mediaObject->getUrlToken($token);
                    $item['url_full'] = $mediaObject->getUrlFull($token);
                    $item['url_download'] = $mediaObject->getUrlDownload($token);
                    $item['url_backend'] = $mediaObject->getBackendUrl($token);

                    if (isset($options['requestEntityUuid']) && is_string($options['requestEntityUuid']) && $options['requestEntityUuid'] != '') {
                        $item['can_delete'] = ($mediaObject && $mediaObject->getCompany() ? ($mediaObject->getCompany()->getUuid() == $options['requestEntityUuid']) : false);
                    } else {
                        $item['can_delete'] = true;
                    }

                    $items[] = $item;
                }
            }

            return [
                'success' => true,
                'orders' => $orders,
                'page' => $page,
                'data' => $items,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * [get media list attached to a data with uuid]
     * @param string $uuid [description]
     * @return [type]       [description]
     */
    public static function __get_attachments_from_uuid($objectUuid = '',
                                                       $objectName = '',
                                                       $objectShared = null,
                                                       $userUuid = '',
                                                       $excludeUserUuid = '',
                                                       $requestEntityUuid = '')
    {
        $bind = [];

        $conditions = "object_uuid = :object_uuid:";
        $bind['object_uuid'] = $objectUuid;


        if ($objectName != '') {
            $bind['object_name'] = $objectName;
            $conditions .= " AND object_name = :object_name:";
        }

        if ($objectShared === self::IS_SHARED_FALSE || $objectShared === self::IS_SHARED_YES) {
            $bind['is_shared'] = $objectShared;
            $conditions .= " AND is_shared = :is_shared:";
        }

        if ($userUuid != '' && Helpers::__isValidUuid($userUuid)) {
            $bind['user_uuid'] = $userUuid;
            $conditions .= " AND user_uuid = :user_uuid:";
        }

        if ($excludeUserUuid != '' && Helpers::__isValidUuid($excludeUserUuid)) {
            $bind['exclude_user_uuid'] = $excludeUserUuid;
            $conditions .= " AND user_uuid <> :exclude_user_uuid:";
        }


        $params = [
            'conditions' => $conditions,
            'distinct' => true,
            'bind' => $bind,
            'order' => 'created_at desc'
        ];


        $attachments = self::find($params);

        $medias = [];
        if ($attachments->count() > 0) {
            foreach ($attachments as $attachment) {
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
                    $item['is_thumb'] = $attachment->getIsThumb() == 1;
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
    public static function __getObjectMediaListFromUuid($objectUuid = '', $objectName = '', $objectShared = null)
    {
        $bind = [];
        $conditions = "object_uuid = :object_uuid:";
        $bind['object_uuid'] = $objectUuid;
        if ($objectName != '') {
            $bind['object_name'] = $objectName;
            $conditions .= " AND object_name = :object_name:";
        }
        if ($objectShared === self::IS_SHARED_FALSE || $objectShared === self::IS_SHARED_YES) {
            $bind['is_shared'] = $objectShared;
            $conditions .= " AND is_shared = :is_shared:";
        }
        $params = [
            'conditions' => $conditions,
            'distinct' => true,
            'bind' => $bind
        ];
        $attachments = self::find($params);
        $medias = [];
        if ($attachments->count() > 0) {
            foreach ($attachments as $attachment) {
                $medias[] = $attachment->getMedia();
            }
        }
        return $medias;
    }

    /**
     * @param $objectUuid
     * @return array
     */
    public static function __getLastImage($objectUuid, $returnType = "array", $token = '')
    {
        try {

            $di = \Phalcon\DI::getDefault();
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\SMXD\Application\Models\MediaExt', 'Media');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\SMXD\Application\Models\MediaAttachmentExt', 'Media.id = MediaAttachment.media_id', 'MediaAttachment');
            $queryBuilder->where("MediaAttachment.object_uuid = :object_uuid:");
            $queryBuilder->andWhere("Media.file_type = :file_type_image:");
//            $queryBuilder->andWhere("Media.is_deleted = :is_deleted:");
            $queryBuilder->andWhere("MediaAttachment.object_name = :object_name_image: OR Media.file_type = :file_type_image:");
            $queryBuilder->limit(1);
            $queryBuilder->orderBy('MediaAttachment.created_at desc');

            $bindArray = [
                "object_uuid" => $objectUuid,
                "object_name_image" => self::MEDIA_GROUP_IMAGE,
                "file_type_image" => MediaExt::FILE_TYPE_IMAGE_NAME,
//                "is_deleted" => ModelHelper::NO
            ];
            $attachments = $queryBuilder->getQuery()->execute($bindArray);
        } catch (\Exception $e) {
            return [];
        }


        if ($returnType == "array") {
            $media = [];
            if ($attachments) {
                $attachment = $attachments->getFirst();
                if ($attachment) {
                    $media = $attachment->toArray();
                    $media['image_data']['url_thumb'] = $attachment->getUrlThumb($token);
                    $media['image_data']['url_token'] = $attachment->getUrlToken($token);
                    $media['image_data']['url_full'] = $attachment->getUrlFull($token);
                    $media['image_data']['url_download'] = $attachment->getUrlDownload($token);
                }
            }
            return $media;
        } else {
            if ($attachments) {
                $attachment = $attachments->getFirst();
                return $attachment;
            }
        }

    }

    /**
     * @param $objectUuid
     * @return array
     */
    public static function __getFirstImage($objectUuid, $returnType = "array", $token = '')
    {
        try {

            $di = \Phalcon\DI::getDefault();
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\SMXD\Application\Models\MediaExt', 'Media');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\SMXD\Application\Models\MediaAttachmentExt', 'Media.id = MediaAttachment.media_id', 'MediaAttachment');
            $queryBuilder->where("MediaAttachment.object_uuid = :object_uuid:");
            $queryBuilder->andWhere("Media.file_type = :file_type_image:");
//            $queryBuilder->andWhere("Media.is_deleted = :is_deleted:");
            $queryBuilder->andWhere("MediaAttachment.object_name = :object_name_image: OR Media.file_type = :file_type_image:");
            $queryBuilder->limit(1);
            $queryBuilder->orderBy('MediaAttachment.created_at ASC');

            $bindArray = [
                "object_uuid" => $objectUuid,
                "object_name_image" => self::MEDIA_GROUP_IMAGE,
                "file_type_image" => MediaExt::FILE_TYPE_IMAGE_NAME,
//                "is_deleted" => ModelHelper::NO
            ];
            $attachments = $queryBuilder->getQuery()->execute($bindArray);
        } catch (\Exception $e) {
            return [];
        }


        if ($returnType == "array") {
            $media = [];
            if ($attachments) {
                $attachment = $attachments->getFirst();
                if ($attachment) {
                    $media = $attachment->toArray();
                    $media['image_data']['url_thumb'] = $attachment->getUrlThumb($token);
                    $media['image_data']['url_token'] = $attachment->getUrlToken($token);
                    $media['image_data']['url_full'] = $attachment->getUrlFull($token);
                    $media['image_data']['url_download'] = $attachment->getUrlDownload($token);
                }
            }
            return $media;
        } else {
            if ($attachments) {
                $attachment = $attachments->getFirst();
                return $attachment;
            }
        }

    }

    public function getMedia()
    {
        return MediaExt::findFirstByUuid($this->getMediaUuid());
    }


    /**
     * @param $objectUuid
     * @return array
     */
    public static function __getImages($objectUuid, $returnType = "array", $token = "")
    {
        $attachments = [];
        try {

            $di = \Phalcon\DI::getDefault();
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\SMXD\Application\Models\MediaExt', 'Media');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\SMXD\Application\Models\MediaAttachmentExt', 'Media.id = MediaAttachment.media_id', 'MediaAttachment');
            $queryBuilder->where("MediaAttachment.object_uuid = :object_uuid:");
            $queryBuilder->andWhere("Media.file_type = :file_type_image:");
            $queryBuilder->andWhere("MediaAttachment.object_name = :object_name_image: OR Media.file_type = :file_type_image:");

            $bindArray = [
                "object_uuid" => $objectUuid,
                "object_name_image" => self::MEDIA_GROUP_IMAGE,
                "file_type_image" => MediaExt::FILE_TYPE_IMAGE_NAME
            ];
            $attachments = $queryBuilder->getQuery()->execute($bindArray);
        } catch (\Exception $e) {
            return [];
        }


        if ($returnType == "array") {
            $medias = [];
            if ($attachments) {
                foreach ($attachments as $attachment) {
                    $media = $attachment->toArray();
                    $media['image_data']['url_thumb'] = $attachment->getUrlThumb($token);
                    $media['image_data']['url_token'] = $attachment->getUrlToken($token);
                    $media['image_data']['url_full'] = $attachment->getUrlFull($token);
                    $media['image_data']['url_download'] = $attachment->getUrlDownload($token);
                    $medias[] = $media;
                }
            }
            return $medias;
        } else {
            return $attachments;
        }
        return [];
    }

    /**
     * [get media list attached to a data with uuid]
     * @param string $uuid [description]
     * @return [type]       [description]
     */
    public static function __getLastAttachment($objectUuid = '', $objectName = '', $returnType = "array")
    {
        if ($objectName == '') {
            $attachment = self::findFirstByObject_uuid($objectUuid);
        } else {
            $attachment = self::findFirst([
                "conditions" => "object_uuid = :object_uuid: AND object_name = :object_name:",
                "bind" => [
                    "object_uuid" => $objectUuid,
                    "object_name" => $objectName
                ],
                "order" => "created_at DESC",
                "limit" => 1
            ]);
        }

        if ($returnType == "array") {
            $media = [];
            if ($attachment instanceof MediaAttachmentExt) {
                $media = $attachment->getMedia()->toArray();
                $media['image_data']['url_thumb'] = $attachment->getMedia()->getUrlThumb();
                $media['image_data']['url_token'] = $attachment->getMedia()->getUrlToken();
                $media['image_data']['url_full'] = $attachment->getMedia()->getUrlFull();
                $media['image_data']['url_download'] = $attachment->getMedia()->getUrlDownload();
            }
            return $media;
        } else {
            if ($attachment) {
                $media = $attachment->getMedia();
                return $media;
            }
        }
    }

    public static function __getLastThumbAttachment($objectUuid = '', $isThumb = self::IS_THUMB_FALSE, $returnType = "array")
    {
        $attachment = self::findFirst([
            "conditions" => "object_uuid = :object_uuid: AND is_thumb = :is_thumb:",
            "bind" => [
                "object_uuid" => $objectUuid,
                "is_thumb" => $isThumb
            ],
            "limit" => 1
        ]);


        if ($returnType == "array") {
            $media = [];
            if ($attachment instanceof MediaAttachmentExt) {
                $media = $attachment->getMedia()->toArray();
                $media['image_data']['url_thumb'] = $attachment->getMedia()->getUrlThumb();
                $media['image_data']['url_token'] = $attachment->getMedia()->getUrlToken();
                $media['image_data']['url_full'] = $attachment->getMedia()->getUrlFull();
                $media['image_data']['url_download'] = $attachment->getMedia()->getUrlDownload();
            }
            return $media;
        } else {
            if ($attachment) {
                $media = $attachment->getMedia();
                return $media;
            }
        }
    }

    public static function __getFirstThumbAttachment($objectUuid = '',  $returnType = "array")
    {
        $attachment = self::findFirst([
            "conditions" => "object_uuid = :object_uuid:",
            "bind" => [
                "object_uuid" => $objectUuid,
            ],
            "limit" => 1
        ]);


        if ($returnType == "array") {
            $media = [];
            if ($attachment instanceof MediaAttachmentExt) {
                $media = $attachment->getMedia()->toArray();
                $media['image_data']['url_thumb'] = $attachment->getMedia()->getUrlThumb();
                $media['image_data']['url_token'] = $attachment->getMedia()->getUrlToken();
                $media['image_data']['url_full'] = $attachment->getMedia()->getUrlFull();
                $media['image_data']['url_download'] = $attachment->getMedia()->getUrlDownload();
            }
            return $media;
        } else {
            if ($attachment) {
                $media = $attachment->getMedia();
                return $media;
            }
        }
    }


    /**
     * create Attachments without
     * @param $params
     */
    public static function __createAttachmentsWithCompany($params)
    {
        $objectUuid = isset($params['objectUuid']) && $params['objectUuid'] != '' ? $params['objectUuid'] : '';
        $object = isset($params['object']) && $params['object'] != null ? $params['object'] : null;
        $mediaList = isset($params['fileList']) && is_array($params['fileList']) ? $params['fileList'] : [];
        if (count($mediaList) == 0) {
            $mediaList = isset($params['files']) && is_array($params['files']) ? $params['files'] : [];
        }
        //$objectName = isset($params['objectName']) && $params['objectName'] != '' ? $params['objectName'] : self::MEDIA_OBJECT_DEFAULT_NAME;
        //$isShared = isset($params['isShared']) && is_bool($params['isShared']) ? $params['isShared'] : self::IS_SHARED_FALSE;

        $user = isset($params['user']) ? $params['user'] : null;
        $company = isset($params['company']) ? $params['company'] : null;
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
                $forceIsShared = false;
                $attachResult = MediaAttachmentExt::__createAttachment([
                    'objectUuid' => $objectUuid,
                    'file' => $attachment,
                    'user' => $user,
                ]);
                if ($attachResult['success'] == true) {
                    $mediaAttachment = $attachResult['data'];

                    if ($forceIsShared == true) {
                        /** @var set force Shared $updateResult */
                        $updateResult = $mediaAttachment->setForceShared();
                        if ($updateResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $updateResult];
                            goto end_of_function;
                        }
                    }

                    $items[] = $mediaAttachment;

                } else {
                    $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $attachResult];
                    goto end_of_function;
                }
            }
            $return = ['success' => true, 'data' => $items];
        }
        end_of_function:
        return $return;
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

        $user = isset($params['user']) ? $params['user'] : null;

//        $ownerCompany = isset($params['ownerCompany']) && is_object($params['ownerCompany']) && $params['ownerCompany'] != null ? $params['ownerCompany'] : null;
//        $employee = isset($params['employee']) && is_object($params['employee']) && $params['employee'] != null ? $params['employee'] : null;
//        $counterPartyCompany = isset($params['counterPartyCompany']) && is_object($params['counterPartyCompany']) && $params['counterPartyCompany'] != null ? $params['counterPartyCompany'] : null;


        if ($objectUuid == '' && !is_null($object) && is_object($object) && method_exists($object, 'getUuid')) {
            $objectUuid = $object->getUuid();
        }
        if ($objectUuid == '' && !is_null($object) && is_array($object) && isset($object['uuid'])) {
            $objectUuid = $object['uuid'];
        }

        if ($objectUuid != '') {
            $items = [];
            foreach ($mediaList as $attachment) {
                $forceIsShared = false;

                $attachResult = self::__createAttachment([
                    'objectUuid' => $objectUuid,
                    'file' => $attachment,
                    'user' => $user,
                ]);


                if ($attachResult['success'] == true) {
                    $mediaAttachment = $attachResult['data'];

                    if ($forceIsShared == true) {
                        /** @var set force Shared $updateResult */
                        $updateResult = $mediaAttachment->setForceShared();
                        if ($updateResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $updateResult];
                            goto end_of_function;
                        }
                    }

                    $items[] = $mediaAttachment;

                } else {
                    $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $attachResult];
                    goto end_of_function;
                }
            }
            $return = ['success' => true, 'data' => $items];
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
        $user = isset($params['user']) ? $params['user'] : null;
        $userUuid = isset($params['userUuid']) ? $params['userUuid'] : null;

        if ($objectUuid == '' && !is_null($object) && is_object($object) && method_exists($object, 'getUuid')) {
            $objectUuid = $object->getUuid();
        }

        if ($objectUuid == '' && !is_null($object) && is_array($object) && isset($object['uuid'])) {
            $objectUuid = $object['uuid'];
        }

        if (!is_null($object) && is_object($object) && method_exists($object, 'getUuid')) {
            $result = self::__createAttachmentToObject($object, $mediaFile, $objectName, $user);
            if ($result['success'] == true) {
                return $result;
            } else {
                return $result;
            }
        } else {
            $result = self::__createAttachmentToObjectUuid($objectUuid, $mediaFile, $objectName, $user);
            if ($result['success'] == true) {
                return $result;
            } else {
                return $result;
            }
        }

        return [
            'success' => false,
            'method' => __METHOD__,
            'objectUuid' => $objectUuid
        ];
    }


    /**
     * @param $uuid
     * @return array
     */
    public static function __getAvatarAttachment($uuid)
    {
        $cacheName = CacheHelper::__getCacheNameAvatar($uuid);
        $media = CacheHelper::__getCacheValue($cacheName);

        if (!$media) {
            $attachment = self::findFirst([
                "conditions" => "object_uuid = :object_uuid: AND ( object_name = :object_name_invoice_logo:  OR object_name = :object_name_avatar: OR object_name = :object_name_logo: OR object_name = :object_name_preview:)",
                "bind" => [
                    "object_uuid" => $uuid,
                    "object_name_invoice_logo" => self::MEDIA_GROUP_INVOICE_LOGO,
                    "object_name_avatar" => self::MEDIA_GROUP_AVATAR,
                    "object_name_logo" => self::MEDIA_GROUP_LOGO,
                    "object_name_preview" => self::MEDIA_GROUP_PREVIEW,
                ],
                "order" => "created_at DESC, updated_at DESC",
            ]);
            if ($attachment) {
                $media = $attachment->getMedia();
                if ($media->getFileType() == MediaExt::FILE_TYPE_IMAGE_NAME) {
                    CacheHelper::__updateCacheValue($cacheName, $media);
                    return $media;
                }
            }
            return null;
        }
        return $media;
    }


    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getAvatar($uuid, $type = self::MEDIA_GROUP_AVATAR, $returnType = "array")
    {
        if (!in_array($type, self::$media_groups)) {
            $type = self::MEDIA_GROUP_AVATAR;
        }
        $avatar = self::__getLastAttachment($uuid, $type, $returnType);
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }


    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getLogo($uuid, $type = self::MEDIA_GROUP_LOGO, $returnType = "object")
    {
        if (!in_array($type, self::$media_groups)) {
            $type = self::MEDIA_GROUP_LOGO;
        }
        $avatar = self::__getLastAttachment($uuid, $type, $returnType);
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }


    /**
     * @param $objectUuid
     * @param $mediaId
     * @param $objectName
     * @return MediaAttachment
     */
    public static function __findByObjectAndMediaId($objectUuid, $mediaId, $objectName = '')
    {
        if ($objectName != '') {
            $params = [
                "conditions" => "media_id = :media_id: AND object_uuid = :object_uuid: AND object_name = :object_name:",
                "bind" => [
                    "media_id" => $mediaId,
                    "object_uuid" => $objectUuid,
                    "object_name" => $objectName
                ]
            ];
        } else {
            $params = [
                "conditions" => "media_id = :media_id: AND object_uuid = :object_uuid:",
                "bind" => [
                    "media_id" => $mediaId,
                    "object_uuid" => $objectUuid,
                ]
            ];
        }

        return self::findFirst($params);
    }

    /**
     * @param $mediaId
     * @param $objectUuid
     * @param string $objectName
     * @return bool
     */
    public static function __attachmentExist($mediaId, $objectUuid, $objectName = '')
    {
        if ($objectName != '') {
            $params = [
                "conditions" => "media_id = :media_id: AND object_uuid = :object_uuid: AND object_name = :object_name:",
                "bind" => [
                    "media_id" => $mediaId,
                    "object_uuid" => $objectUuid,
                    "object_name" => $objectName
                ]
            ];
        } else {
            $params = [
                "conditions" => "media_id = :media_id: AND object_uuid = :object_uuid:",
                "bind" => [
                    "media_id" => $mediaId,
                    "object_uuid" => $objectUuid,
                ]
            ];
        }

        $findFirst = self::findFirst($params);

        if ($findFirst) return true;
        else return false;
    }

    /**
     * @param $mediaUuid
     * @param $objectUuid
     * @param string $objectName
     * @return bool
     */
    public static function __attachmentExistByMediaUuid($mediaUuid, $objectUuid)
    {
        $params = [
            "conditions" => "media_uuid = :media_uuid: AND object_uuid = :object_uuid:",
            "bind" => [
                "media_uuid" => $mediaUuid,
                "object_uuid" => $objectUuid,
            ]
        ];
        $findFirst = self::findFirst($params);
        if ($findFirst) return true;
        else return false;
    }


    /**
     * @param $mainObject
     * @param $media
     * @param null $user
     * @return array
     */
    public static function __createAttachmentToObject($mainObject, $mediaObject, $objectName = null, $user = null)
    {

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

        $mediaId = false;
        if (is_object($mediaObject) && method_exists($mediaObject, 'getId') && $mediaObject->getId() > 0) {
            $mediaId = ($mediaObject->getId());
        } elseif (is_object($mediaObject) && property_exists($mediaObject, 'id') && $mediaObject->id > 0) {
            $mediaId = $mediaObject->id;
        } elseif (is_array($mediaObject) && isset($mediaObject['id']) && $mediaObject['id'] > 0) {
            $mediaId = ($mediaObject['id']);
        }
        if (!$mediaId > 0) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => "MEDIA_ID_NOT_FOUND_TEXT",
                'type' => 'FAIL'
            ];
        }

        $mediaAttachment = self::__findByObjectAndMediaId($mediaId, $objectUuid);

        if ($mediaAttachment) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => 'MEDIA_ALREADY_ATTACHED_TEXT',
                'type' => 'FAIL'
            ];
        }

        if (is_array($mediaObject)) {
            $mediaObject = MediaExt::findFirstById($mediaId);
        } elseif (is_object($mediaObject) && method_exists($mediaObject, 'getUuid')) {
            $mediaObject = $mediaObject;
        } elseif (is_object($mediaObject) && property_exists($mediaObject, 'id')) {
            $mediaObject = MediaExt::findFirstById($mediaId);
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
        $mediaAttachment->setObjectName($objectName);
        $mediaAttachment->setMediaId($mediaId);
        $mediaAttachment->setMediaUuid($mediaObject->getUuid());
        $mediaAttachment->setCreatedAt(date('Y-m-d H:i:s'));
        $mediaAttachment->setUpdatedAt(date('Y-m-d H:i:s'));


        if (is_array($user) &&
            isset($user['uuid']) &&
            $user['uuid'] != '') {

            $mediaAttachment->setUserUuid($user['uuid']);
            $mediaAttachment->setOwnerCompanyId($user['company_id']);

        } else if (is_object($user) && method_exists($user, 'getUuid')) {
            $mediaAttachment->setUserUuid($user->getUuid());

            if ($user) {
                $mediaAttachment->setOwnerCompanyId($user->getCompanyId());
            }
        }

        $resultCreate = $mediaAttachment->__quickCreate();

        if ($resultCreate['success'] == true) {
            return [
                'success' => true,
                'action' => __METHOD__,
                'message' => "ATTACH_SUCCESS_TEXT",
                'type' => 'SUCCESS',
                'data' => $mediaAttachment->parsedDataToArray(),
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
     */
    public static function __createAttachmentToObjectUuid($objectUuid, $media, $objectName = null, $user = null)
    {

        if ($objectUuid == '') {
            return [
                'success' => false,
                'objectUuid' => $objectUuid,
                'action' => __METHOD__,
                'message' => "OBJECT_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }

        if (is_null($media)) {
            return [
                'success' => false,
                'objectUuid' => $objectUuid,
                'action' => __METHOD__,
                'message' => "MEDIA_FILE_EMPTY_TEXT",
                'type' => 'FAIL'
            ];
        }

        if (is_object($media) && method_exists($media, 'getId') && $media->getId() > 0) {
            $mediaId = ($media->getId());
        } elseif (is_object($media) && property_exists($media, 'id') && $media->id > 0) {
            $mediaId = $media->id;
        } elseif (is_array($media) && isset($media['id']) && $media['id'] > 0) {
            $mediaId = ($media['id']);
        }
        if (!$mediaId > 0) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => "MEDIA_ID_NOT_FOUND_TEXT",
                'type' => 'FAIL'
            ];
        }

        $objectName = $objectName ?? self::MEDIA_OBJECT_DEFAULT_NAME;

        $mediaAttachment = self::__findByObjectAndMediaId($mediaId, $objectUuid, $objectName);

        if ($mediaAttachment) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => 'MEDIA_ALREADY_ATTACHED_TEXT',
                'type' => 'FAIL'
            ];
        }

        if (is_array($media)) {
            $mediaObject = MediaExt::findFirstById($mediaId);
        } elseif (is_object($media) && method_exists($media, 'getUuid')) {
            $mediaObject = $media;
        } elseif (is_object($media) && property_exists($media, 'id')) {
            $mediaObject = MediaExt::findFirstById($mediaId);
        }


        if (!$mediaObject) {
            return [
                'success' => false,
                'action' => __METHOD__,
                'message' => "MEDIA_NOT_FOUND_TEXT",
                'type' => 'FAIL'
            ];
        }

        $mediaAttachment = new self();
        $mediaAttachment->setUuid(ApplicationModel::uuid());
        $mediaAttachment->setObjectUuid($objectUuid);
        $mediaAttachment->setObjectName($objectName);
        $mediaAttachment->setMediaId($mediaId);
        $mediaAttachment->setMediaUuid($mediaObject->getUuid());
        $mediaAttachment->setIsShared(Helpers::NO);
        //$model->setObjectId(0);
        $mediaAttachment->setCreatedAt(date('Y-m-d H:i:s'));
        $mediaAttachment->setUpdatedAt(date('Y-m-d H:i:s'));
        //$mediaAttachment->setIsShared();

        if (is_array($user) &&
            isset($user['uuid']) &&
            $user['uuid'] != '') {

            $mediaAttachment->setUserUuid($user['uuid']);
            $mediaAttachment->setOwnerCompanyId($user['company_id']);

        } else if (is_object($user) && method_exists($user, 'getUuid')) {
            $mediaAttachment->setUserUuid($user->getUuid());

            if ($user) {
                $mediaAttachment->setOwnerCompanyId($user->getCompanyId());
            }
        }

        $resultCreate = $mediaAttachment->__quickCreate();

        if ($resultCreate['success'] == true) {
            return [
                'success' => true,
                'action' => __METHOD__,
                'message' => "ATTACH_SUCCESS_TEXT",
                'type' => 'SUCCESS',
                'data' => $mediaAttachment->parsedDataToArray(),
            ];
        } else {
            return $resultCreate;
        }
    }

    /**
     * @return array
     */
    public function setForceShared(): array
    {
        $this->setIsShared(ModelHelper::YES);
        return $this->__quickUpdate();
    }

    /**
     * @param $uuid
     * @return MediaAttachment
     */
    public static function findFirstByUuidCache($uuid, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        return self::__findFirstByUuidCache($uuid, $lifetime);
    }


    /**
     * [get media list attached to a data with uuid]
     * @param string $uuid [description]
     * @return [type]       [description]
     */
    public static function __countPhotos($objectUuid)
    {
        $attachments = [];
        try {
            $di = \Phalcon\DI::getDefault();
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\SMXD\Application\Models\MediaExt', 'Media');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\SMXD\Application\Models\MediaAttachmentExt', 'Media.id = MediaAttachment.media_id', 'MediaAttachment');
            $queryBuilder->where("MediaAttachment.object_uuid = :object_uuid:");
            $queryBuilder->andWhere("Media.file_type = :file_type_image:");
            $queryBuilder->andWhere("MediaAttachment.object_name = :object_name_image: OR Media.file_type = :file_type_image:");

            $bindArray = [
                "object_uuid" => $objectUuid,
                "object_name_image" => self::MEDIA_GROUP_IMAGE,
                "file_type_image" => MediaExt::FILE_TYPE_IMAGE_NAME
            ];
            $count = $queryBuilder->getQuery()->execute($bindArray)->count();
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * [get media list attached to a data with uuid]
     * @param string $uuid [description]
     * @return [type]       [description]
     */
    public static function __countAttachments($objectUuid)
    {
        $attachments = [];
        try {
            $di = \Phalcon\DI::getDefault();
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\SMXD\Application\Models\MediaExt', 'Media');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\SMXD\Application\Models\MediaAttachmentExt', 'Media.id = MediaAttachment.media_id', 'MediaAttachment');
            $queryBuilder->where("MediaAttachment.object_uuid = :object_uuid:");
//            $queryBuilder->andWhere("Media.is_deleted = :is_deleted:");


            $bindArray = [
                "object_uuid" => $objectUuid,
//                "is_deleted" => ModelHelper::NO,
            ];
            $count = $queryBuilder->getQuery()->execute($bindArray)->count();
            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     *
     */
    public function getSharersUuid()
    {
        $shares = $this->getMediaAttachmentShares();
        $res = [];
        if ($shares) {
            foreach ($shares as $share) {
                if ($share->getSharerUuid() != '') $res[] = $share->getSharerUuid();
            }
        }
        return $res;
    }

    /**
     * @return object
     */
    public function getEmployeeOrUser()
    {
        if ($this->getEmployee()) return $this->getEmployee();
        if ($this->getUser()) return $this->getUser();
    }

    public static function __getImageByObjUuidAndType($objUuid, $type, $returnType = 'object')
    {
        $image = self::__getLastAttachment($objUuid, $type, $returnType);
        if ($image) {
            return $image;
        } else {
            return null;
        }
    }

    public static function __getImageByObjUuidAndIsThumb($objUuid, $isThumb, $returnType = 'object')
    {
        $image = self::__getLastThumbAttachment($objUuid, $isThumb, $returnType);
        if ($image) {
            return $image;
        } else {
            return   self::__getFirstThumbAttachment($objUuid,  $returnType);
        }
    }

    public function parsedDataToArray(){
        $mediaObject = $this->getMedia();

        $item = $mediaObject->toArray();
        $item['owner_company_id'] = $this->getOwnerCompanyId();
        $item['media_attachment_uuid'] = $this->getUuid();

        $item['created_at'] = strtotime($this->getCreatedAt()) * 1000;
        $item['updated_at'] = strtotime($this->getUpdatedAt()) * 1000;
        $item['company_uuid'] = $mediaObject->getCompany() ? $mediaObject->getCompany()->getUuid() : null;

        $item['media_attachment_id'] = $this->getId();
        $item['name'] = $mediaObject->getNameOfficial();


        $item['url_thumb'] = $mediaObject->getTemporaryThumbS3Url();
        $item['url_full'] = $mediaObject->getTemporaryFileUrlS3();
        $item['url_download'] = $mediaObject->getTemporaryFileUrlS3();
        $item['can_delete'] = true;
        $item['is_thumb'] = $this->getIsThumb() == 1;

        return $item;
    }
}
