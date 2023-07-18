<?php


namespace Reloday\Gms\Models;

use Phalcon\Mvc\Model\Relation;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Module;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class MediaAttachmentOld extends \Reloday\Application\Models\MediaAttachmentExt
{

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('media_id', 'Reloday\Gms\Models\Media', 'id', [
            'alias' => 'Media',
            'cache' => [
                'key' => 'MEDIA_',
                'lifetime' => CacheHelper::__TIME_24H,
            ]
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\MediaAttachmentSharing', 'media_attachment_id', [
            'alias' => 'MediaAttachmentShares',
            'foreignKey' => [
                'action' => Relation::ACTION_CASCADE,
            ]
        ]);

        $this->belongsTo('user_profile_uuid', 'Reloday\Gms\Models\UserProfile', 'uuid', [
            'alias' => 'UserProfile'
        ]);
    }

    /**
     * @param bool $uuid
     * @param string $object_name
     * @return array
     */
    public static function __load_attachments($uuid = [] || '', $object_name = '')
    {
        if ($object_name == '') {
            if (is_array($uuid)) {
                $attachments = self::find([
                    'object_uuid IN ("' . implode('","', $uuid) . '")'

                ]);
            } else {
                $attachments = self::findByObject_uuid($uuid);
            }
        } else {
            $attachments = self::find([
                "conditions" => "object_uuid = :object_uuid: AND object_name = :object_name:",
                "bind" => [
                    "object_uuid" => $uuid,
                    "object_name" => $object_name
                ]
            ]);
        }

        $medias = [];
        if ($attachments->count() > 0) {
            foreach ($attachments as $attachment) {
                $item = $attachment->getMedia()->toArray();
                $item['image_data']['url_thumb'] = $attachment->getMedia()->getUrlThumb();
                $item['image_data']['url_token'] = $attachment->getMedia()->getUrlToken();
                $item['image_data']['url_full'] = $attachment->getMedia()->getUrlFull();
                $item['object_uuid'] = $attachment->getObjectUuid();
                $medias[] = $item;
            }
        }
        return $medias;
    }

    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getAvatar($uuid, $type = self::MEDIA_GROUP_AVATAR, $returnType = 'array')
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
     * @return bool
     */
    public function belongsToUser()
    {
        return $this->getUserProfileUuid() == ModuleModel::$user_profile->getUuid();
    }

    /**
     * @param $options
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\MediaAttachment', 'MediaAttachment');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Media', 'Media.id = MediaAttachment.media_id', 'Media');
        $queryBuilder->where('Media.is_deleted = :media_is_deleted_no:', [
            'media_is_deleted_no' => ModelHelper::NO
        ]);

        if (isset($options['sharer_uuid']) && is_string($options['sharer_uuid']) && Helpers::__isValidUuid($options['sharer_uuid'])) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\MediaAttachmentSharing', 'MediaAttachment.id = MediaAttachmentSharing.media_attachment_id', 'MediaAttachmentSharing');
            $queryBuilder->andwhere("MediaAttachmentSharing.sharer_uuid = :sharer_uuid:", [
                'sharer_uuid' => $options['sharer_uuid'],
            ]);
        }
        if (isset($options['sharer_uuids']) && is_array($options['sharer_uuids']) && count($options['sharer_uuids'])) {
            foreach ($options['sharer_uuids'] as $key => $sharer_uuid) {
                $queryBuilder->innerJoin('\Reloday\Gms\Models\MediaAttachmentSharing', 'MediaAttachment.id = MediaAttachmentSharing_' . $key . '.media_attachment_id', 'MediaAttachmentSharing_' . $key);
                $queryBuilder->andwhere("MediaAttachmentSharing_" . $key . ".sharer_uuid = :sharer_uuid_$key:", [
                    "sharer_uuid_$key" => $sharer_uuid,
                ]);
            }
        }
        $queryBuilder->groupBy('Media.id'); //should remove ONLY_FULL_GROUP_BY in SQL_MODE

        if (isset($options['is_shared']) && is_bool($options['is_shared']) && $options['is_shared'] === true) {
            $queryBuilder->andwhere("MediaAttachment.is_shared = :is_shared_yes:", [
                'is_shared_yes' => ModelHelper::YES
            ]);
        }
        if (isset($options['is_shared']) && is_bool($options['is_shared']) && $options['is_shared'] === false) {
            $queryBuilder->andwhere("MediaAttachment.is_shared = :is_shared_no:", [
                'is_shared_no' => ModelHelper::NO
            ]);
        }

        if (isset($options['object_uuid']) && is_string($options['object_uuid']) && Helpers::__isValidUuid($options['object_uuid'])) {
            $queryBuilder->andwhere("MediaAttachment.object_uuid = :object_uuid:", [
                'object_uuid' => $options['object_uuid'],
            ]);
        }

        if (isset($options['object_name']) && is_string($options['object_name']) && !Helpers::__isNull($options['object_name'])) {
            $queryBuilder->andwhere("MediaAttachment.object_name = :object_name:", [
                'object_name' => $options['object_name'],
            ]);
        }

        if (isset($options['company_id']) && is_numeric($options['company_id']) && $options['company_id'] > 0) {
            $queryBuilder->andwhere("MediaAttachmentSharing.company_id = :company_id:", [
                'employee_id' => $options['company_id'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("MediaAttachmentSharing.employee_id = :employee_id:", [
                'employee_id' => $options['employee_id'],
            ]);
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Media.name LIKE :query: OR Media.name_static LIKE :query: OR Media.filename LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['user_profile_uuid']) && Helpers::__isValidUuid($options['user_profile_uuid'])) {
            $queryBuilder->andwhere("MediaAttachment.user_profile_uuid = :user_profile_uuid:", [
                    'user_profile_uuid' => $options['user_profile_uuid'],
                ]
            );
        }


        /*
         * if (isset($options['sharer_uuid']) && is_string($options['sharer_uuid']) && Helpers::__isValidUuid($options['sharer_uuid'])) {
            $queryBuilder->andwhere("MediaAttachmentSharing.sharer_uuid = :sharer_uuid:", [
                'sharer_uuid' => $options['sharer_uuid'],
            ]);
        }

        if (isset($options['sharer_uuids']) && is_array($options['sharer_uuids']) && count($options['sharer_uuids'])) {
            $queryBuilder->andwhere("MediaAttachmentSharing.sharer_uuid IN ({sharer_uuids:array})", [
                'sharer_uuids' => $options['sharer_uuids'],
            ]);
        }
        */


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
            $medias = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $mediaAttachmentObject) {
                    $mediaObject = $mediaAttachmentObject->getMediaCache();
                    $item = $mediaObject->toArray();
                    $item['media_attachment_uuid'] = $mediaAttachmentObject->getUuid();
                    $item['media_attachment_id'] = $mediaAttachmentObject->getId();
                    $item['name'] = $mediaObject->getNameOfficial();
                    $item['image_data']['url_thumb'] = $mediaObject->getUrlThumb();
                    $item['image_data']['url_token'] = $mediaObject->getUrlToken();
                    $item['image_data']['url_full'] = $mediaObject->getUrlFull();
                    $item['image_data']['url_download'] = $mediaObject->getUrlDownload();

                    $item['url_thumb'] = $mediaObject->getUrlThumb();
                    $item['url_token'] = $mediaObject->getUrlToken();
                    $item['url_full'] = $mediaObject->getUrlFull();
                    $item['url_download'] = $mediaObject->getUrlDownload();
                    $item['url_backend'] = $mediaObject->getBackendUrl();
                    $item['can_delete'] = $mediaAttachmentObject->canDelete() || $mediaObject->canDelete();
                    //$item['can_delete'] = ($mediaObject && $mediaObject->getCompany() ? ($mediaObject->getCompany()->getId() == ModuleModel::$company->getId()) : false);
                    $medias[] = $item;
                }
            }

            return [
                'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'orders' => $orders,
                'page' => $page,
                'data' => $medias,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'sql' => $queryBuilder->getQuery()->getSql(), 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'sql' => $queryBuilder->getQuery()->getSql(), 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'sql' => $queryBuilder->getQuery()->getSql(), 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
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
            $shareResults = [];
            foreach ($mediaList as $attachment) {
                $forceIsShared = false;
                $attachResult = MediaAttachment::__createAttachment([
                    'objectUuid' => $objectUuid,
                    'file' => $attachment,
                    'userProfile' => $userProfile,
                ]);
                if ($attachResult['success'] == true) {
                    //share to my own company
                    $mediaAttachment = $attachResult['data'];

                    if (isset(ModuleModel::$company) && ModuleModel::$company) {
                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], ModuleModel::$company);
                        if ($shareResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                            goto end_of_function;
                        }
                        $shareResults[] = $shareResult;
                    }
                    if (isset($employee) && $employee) {
                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], $employee);
                        if ($shareResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                            goto end_of_function;
                        }
                        $forceIsShared = true;
                    }

                    if (isset($counterPartyCompany) && $counterPartyCompany) {
                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], $counterPartyCompany);
                        if ($shareResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                            goto end_of_function;
                        }
                        $forceIsShared = true;
                    }

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
            $return = ['success' => true, 'data' => $items, 'shared' => $shareResults];
        }
        end_of_function:
        return $return;
    }

    /**
     * @param $objectUuid
     * @return array
     */
    public static function __getLastImage($objectUuid, $returnType = "array")
    {
        $attachments = self::query()
            ->innerJoin("Reloday\Gms\Models\Media", "Media.id = Reloday\Gms\Models\MediaAttachment.media_id", "Media")
            ->where("object_uuid = :object_uuid: AND ( object_name = :object_name_image: OR Media.file_type = :file_type_image:) AND Media.file_type = :file_type_image:", [
                "object_uuid" => $objectUuid,
                "object_name_image" => self::MEDIA_GROUP_IMAGE,
                "file_type_image" => Media::FILE_TYPE_IMAGE_NAME
            ])
            ->orderBy("Reloday\Gms\Models\MediaAttachment.created_at DESC")
            ->limit(1)
            ->execute();


        if ($returnType == "array") {
            $media = [];
            if ($attachments->count() > 0) {
                $attachment = $attachments->getFirst();
                $media = $attachment->getMedia()->toArray();
                $media['image_data']['url_thumb'] = $attachment->getMedia()->getUrlThumb();
                $media['image_data']['url_token'] = $attachment->getMedia()->getUrlToken();
                $media['image_data']['url_full'] = $attachment->getMedia()->getUrlFull();
                $media['image_data']['url_download'] = $attachment->getMedia()->getUrlDownload();
            }
            return $media;
        } else {
            if ($attachments->count() > 0) {
                $attachment = $attachments->getFirst();
                $media = $attachment->getMedia();
                return $media;
            }
        }
    }

    /**
     * @return mixed
     */
    public function getMediaCache()
    {
        return $this->getMedia([
            'cache' => [
                'key' => 'MEDIA_' . $this->getMediaId(),
                'lifetime' => CacheHelper::__TIME_24H,
            ]
        ]);
    }

    public function canDelete()
    {
        return $this->getUserProfile() && $this->getUserProfile()->getCompanyId() == ModuleModel::$company->getId();
    }
}
