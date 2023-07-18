<?php


namespace Reloday\Gms\Models;

use Aws\Exception\AwsException;
use Phalcon\Mvc\Model\Relation;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ElasticSearchHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Models\MediaAttachmentExt;
use Reloday\Gms\Models\MediaFolder;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Gms\Models\ModuleModel;

class MediaAttachment extends MediaAttachmentExt
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
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\MediaAttachmentSharing', 'media_attachment_id', [
            'alias' => 'MediaAttachmentShares',
            'foreignKey' => [
                'action' => Relation::ACTION_CASCADE,
            ]
        ]);

        $this->belongsTo('owner_company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'OwnerCompany'
        ]);

        $this->belongsTo('user_profile_uuid', 'Reloday\Gms\Models\UserProfile', 'uuid', [
            'alias' => 'UserProfile'
        ]);

        $this->belongsTo('user_profile_uuid', 'Reloday\Gms\Models\Employee', 'uuid', [
            'alias' => 'Employee'
        ]);
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        return $this->getOwnerCompanyId() == ModuleModel::$company->getId() || $this->getUserProfile() && $this->getUserProfile()->belongsToGms();
    }

    /**
     * @return bool
     */
    public function belongsToUser()
    {
        return $this->getUserProfileUuid() == ModuleModel::$user_profile->getUuid();
    }

    /**
     * @return bool
     */
    public function getEmployeeOrUserProfile()
    {
        if ($this->getEmployee()) return $this->getEmployee();
        if ($this->getUserProfile()) return $this->getUserProfile();
    }

    /**
     * @return bool
     */
    public function belongsToCompany()
    {
        return $this->getMedia()->getCompanyId() == ModuleModel::$company->getId();
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

    private static function getUserLoginToken()
    {
        return base64_encode(ModuleModel::$user_login_token);
    }

    /**
     * @return mixed
     */
    public function getMediaData()
    {
        return $this->getMedia();
    }

    /**
     * @param $options
     */
    public static function __findWithFilter($options = [], $orders = [])
    {

        /** object_uuid should be required */
        if (!isset($options['object_uuid'])) {
            return ['success' => false, 'items' => 0];
        }


        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\MediaAttachment', 'MediaAttachment');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Media', 'Media.id = MediaAttachment.media_id', 'Media');

        $queryBuilder->where("MediaAttachment.object_uuid = :object_uuid:", [
            'object_uuid' => $options['object_uuid'],
        ]);
//        $queryBuilder->groupBy('Media.id'); //should remove ONLY_FULL_GROUP_BY in SQL_MODE


        if (isset($options['media_is_deleted']) && is_bool($options['media_is_deleted']) && $options['media_is_deleted'] === false) {
            $queryBuilder->andwhere("Media.is_deleted = :media_is_deleted_no:", [
                'media_is_deleted_no' => ModelHelper::NO
            ]);
        }

        if (isset($options['media_is_deleted']) && is_bool($options['media_is_deleted']) && $options['media_is_deleted'] === true) {
            $queryBuilder->andwhere("Media.is_deleted = :media_is_deleted_yes:", [
                'media_is_deleted_yes' => ModelHelper::YES
            ]);
        }

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

        if (isset($options['media_uuid']) && is_string($options['media_uuid']) && Helpers::__isValidUuid($options['media_uuid'])) {
            $queryBuilder->andwhere("MediaAttachment.media_uuid = :media_uuid:", [
                'media_uuid' => $options['media_uuid'],
            ]);
        }

        if (isset($options['object_name']) && is_string($options['object_name']) && !Helpers::__isNull($options['object_name'])) {
            $queryBuilder->andwhere("MediaAttachment.object_name = :object_name:", [
                'object_name' => $options['object_name'],
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

        if (isset($options['folder_uuid']) && Helpers::__isValidUuid($options['folder_uuid'])) {
            $queryBuilder->andwhere("MediaAttachment.folder_uuid = :folder_uuid:", [
                    'folder_uuid' => $options['folder_uuid'],
                ]
            );
        }

        if (isset($options['nullFolder']) && is_numeric($options['nullFolder']) && $options['nullFolder'] == 1) {
            $queryBuilder->andwhere("MediaAttachment.folder_uuid is null");
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

        $permissionDeleteResult = AclHelper::__canAccessResource(AclHelper::CONTROLLER_ATTACHMENTS, AclHelper::ACTION_DELETE);

        $canDeleteAttachment = ModuleModel::$user_profile->isAdmin() || $permissionDeleteResult['success'];

//        var_dump($canDeleteAttachment);
//        die();

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
                    $mediaObject = $mediaAttachmentObject->getMedia();

                    $item = $mediaObject->toArray();
                    $item['owner_company_id'] = $mediaAttachmentObject->getOwnerCompanyId();
                    $item['media_attachment_uuid'] = $mediaAttachmentObject->getUuid();
                    $item['assignee_folder_uuid'] = $mediaAttachmentObject->getFolderUuid();
                    if ($mediaAttachmentObject->getFolderUuid()) {
                        $mediaFolder = MediaFolder::findFirstByUuid($mediaAttachmentObject->getFolderUuid());
                        $item['assignee_folder_name'] = $mediaFolder ? $mediaFolder->getName() : null;
                    } else {
                        $item['assignee_folder_name'] = null;
                    }

                    $item['created_at'] = strtotime($mediaAttachmentObject->getCreatedAt()) * 1000;
                    $item['updated_at'] = strtotime($mediaAttachmentObject->getUpdatedAt()) * 1000;
                    $item['company_uuid'] = $mediaObject->getCompany() ? $mediaObject->getCompany()->getUuid() : null;

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
                    $item['can_delete'] = (($mediaAttachmentObject->canDelete() || $mediaObject->canDelete()) && $canDeleteAttachment);

                    if (!$item['can_delete'] && $mediaAttachmentObject->belongsToUser()) {
                        $item['can_delete'] = true;
                    }
                    //can delete where 1 file = attachement deletable OR media deletable AND user has permission to delete;

                    //$item['can_delete'] = ($mediaObject && $mediaObject->getCompany() ? ($mediaObject->getCompany()->getId() == ModuleModel::$company->getId()) : false);

                    $item['media_attachments'] = $mediaAttachmentObject->toArray();
                    $item['is_employee'] = $mediaObject->isEmployee();
                    $item['attached_by_company'] = $mediaObject->getCompany() ? $mediaObject->getCompany()->getName() : null;
                    $item['is_thumb'] = $mediaAttachmentObject->getIsThumb() == 1;

                    if ($mediaAttachmentObject->getUserProfile()) {
                        $item['attached_by'] = $mediaAttachmentObject->getUserProfile()->getFullname();
                    } else if ($mediaObject->isEmployee() && $mediaAttachmentObject->getEmployee()) {
                        $item['attached_by'] = $mediaAttachmentObject->getEmployee()->getFullname();
                    } else {
                        $item['attached_by'] = null;
                    }

                    if ($mediaAttachmentObject->getOwnerCompanyId() > 0) {
                        $item['attached_by_company'] = $mediaAttachmentObject->getOwnerCompany()->getName();
                    }


                    $can_attach_to_my_library = true;
                    if ($mediaObject) {
                        $newMedia = new Media();
                        $newMedia->setName($mediaObject->getName());
                        $newMedia->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
                        $newMedia->setFileExtension($mediaObject->getFileExtension());
                        $newMedia->setIsDeleted(ModelHelper::NO);
                        $existed = $newMedia->checkFileNameExisted();
                        if ($existed) {
                            $can_attach_to_my_library = false;
                        }
                    }
                    $item['can_attach_to_my_library'] = $can_attach_to_my_library;

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
        } catch (\Exception $e) {
            return ['success' => false, 'sql' => $queryBuilder->getQuery()->getSql(), 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param $options
     */
    public static function __findSharedWithMe($options = [], $orders = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\MediaAttachment', 'MediaAttachment');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Media', 'Media.id = MediaAttachment.media_id', 'Media');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ObjectFolder', 'ObjectFolder.uuid = MediaAttachment.object_uuid', 'ObjectFolder');
        $queryBuilder->leftjoin('Reloday\Gms\Models\DataUserMember', "DataUserMember.object_uuid = ObjectFolder.object_uuid  AND  (DataUserMember.member_type_id IN (" . \Reloday\Application\Models\DataUserMemberExt::MEMBER_TYPE_VIEWER . ',' . \Reloday\Application\Models\DataUserMemberExt::MEMBER_TYPE_OWNER . ',' . \Reloday\Application\Models\DataUserMemberExt::MEMBER_TYPE_REPORTER . ")) AND DataUserMember.company_id = " . ModuleModel::$company->getId(), "DataUserMember");
        $queryBuilder->where('DataUserMember.user_profile_uuid = :user_profile_uuid:', [
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid()
        ]);
        $queryBuilder->andWhere('Media.user_profile_uuid != :user_profile_uuid:', [
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid()
        ]);
        $queryBuilder->andWhere('ObjectFolder.dsp_company_id = :dsp_company_id:', [
            'dsp_company_id' => ModuleModel::$company->getId()
        ]);

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Media.name LIKE :query: OR Media.name_static LIKE :query: OR Media.filename LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
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

        $queryBuilder->groupBy('MediaAttachment.id');


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
                    $mediaObject = $mediaAttachmentObject->getMedia();
                    $item = $mediaObject->toArray();
                    $item['media_attachment_uuid'] = $mediaAttachmentObject->getUuid();
                    $item['assignee_folder_uuid'] = $mediaAttachmentObject->getFolderUuid();
                    if ($mediaAttachmentObject->getFolderUuid()) {
                        $mediaFolder = MediaFolder::findFirstByUuid($mediaAttachmentObject->getFolderUuid());
                        $item['assignee_folder_name'] = $mediaFolder ? $mediaFolder->getName() : null;
                    } else {
                        $item['assignee_folder_name'] = null;
                    }

                    $item['created_at'] = strtotime($mediaAttachmentObject->getCreatedAt()) * 1000;
                    $item['updated_at'] = strtotime($mediaAttachmentObject->getUpdatedAt()) * 1000;
                    $item['company_uuid'] = $mediaObject->getCompany()->getUuid();

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
                    $item['media_attachments'] = $mediaAttachmentObject->toArray();
                    $item['is_employee'] = $mediaObject->isEmployee();

                    $can_attach_to_my_library = true;
                    if ($mediaObject) {
                        $newMedia = new Media();
                        $newMedia->setName($mediaObject->getName());
                        $newMedia->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
                        $newMedia->setFileExtension($mediaObject->getFileExtension());
                        $newMedia->setIsDeleted(ModelHelper::NO);
                        $existed = $newMedia->checkFileNameExisted();
                        if ($existed) {
                            $can_attach_to_my_library = false;
                        }
                    }
                    $item['can_attach_to_my_library'] = $can_attach_to_my_library;

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
        } catch (\Exception $e) {
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

//                    if (isset(ModuleModel::$company) && ModuleModel::$company) {
//                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], ModuleModel::$company);
//                        if ($shareResult['success'] == false) {
//                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
//                            goto end_of_function;
//                        }
//                        $shareResults[] = $shareResult;
//                    }
//                    if (isset($employee) && $employee) {
//                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], $employee);
//                        if ($shareResult['success'] == false) {
//                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
//                            goto end_of_function;
//                        }
//                        $forceIsShared = true;
//                    }
//
//                    if (isset($counterPartyCompany) && $counterPartyCompany) {
//                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], $counterPartyCompany);
//                        if ($shareResult['success'] == false) {
//                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
//                            goto end_of_function;
//                        }
//                        $forceIsShared = true;
//                    }
//
//                    if ($forceIsShared == true) {
//                        /** @var set force Shared $updateResult */
//                        $updateResult = $mediaAttachment->setForceShared();
//                        if ($updateResult['success'] == false) {
//                            $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $updateResult];
//                            goto end_of_function;
//                        }
//                    }


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

    public function getMediaParsedData()
    {
        $item = $this->getMedia()->getParsedData();
        $item['assignment_folder_uuid'] = $this->getUuid();
        $item['assignee_folder_uuid'] = $this->getFolderUuid();
        if ($this->getFolderUuid()) {
            $mediaFolder = MediaFolder::findFirstByUuid($this->getFolderUuid());
            $item['assignee_folder_name'] = $mediaFolder ? $mediaFolder->getName() : '';
        }
        return $item;
    }

    /**
     * @return bool
     */
    public function canDelete()
    {
        if ($this->belongsToUser()) {
            return true;
        }

        if (ModuleModel::$user_profile->isAdmin() && $this->belongsToGms()) {
            return true;
        }


        if ($this->getUserProfileUuid() === null && $this->getOwnerCompanyId() === null) {
            return true;
        }

        if ($this->getMedia()->getCompany() && ModuleModel::$user_profile->getCompany()->getUuid() == $this->getMedia()->getCompany()->getUuid()) {
            return true;
        }

        return false;
    }

    /**
     * @param $objectUuid
     * @return array
     */
    public static function __getLastImage($objectUuid, $returnType = "array", $token = "")
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
     * @param $objectUuid
     * @return array
     */
    public static function __getFirstImage($objectUuid, $returnType = "array", $token = "")
    {
        $attachments = self::query()
            ->innerJoin("Reloday\Gms\Models\Media", "Media.id = Reloday\Gms\Models\MediaAttachment.media_id", "Media")
            ->where("object_uuid = :object_uuid: AND ( object_name = :object_name_image: OR Media.file_type = :file_type_image:) AND Media.file_type = :file_type_image:", [
                "object_uuid" => $objectUuid,
                "object_name_image" => self::MEDIA_GROUP_IMAGE,
                "file_type_image" => Media::FILE_TYPE_IMAGE_NAME
            ])
            ->orderBy("Reloday\Gms\Models\MediaAttachment.created_at ASC")
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
     * @param $objectUuid
     * @return array
     */
    public static function __getMainThumb($objectUuid, $returnType = "array", $token = "")
    {
        $attachments = self::query()
            ->innerJoin("Reloday\Gms\Models\Media", "Media.id = Reloday\Gms\Models\MediaAttachment.media_id", "Media")
            ->where("object_uuid = :object_uuid: AND ( object_name = :object_name_image: OR Media.file_type = :file_type_image:) AND Media.file_type = :file_type_image: and is_thumb = :is_thumb:", [
                "object_uuid" => $objectUuid,
                "object_name_image" => self::MEDIA_GROUP_IMAGE,
                "file_type_image" => Media::FILE_TYPE_IMAGE_NAME,
                "is_thumb" => ModelHelper::YES,
            ])
            ->orderBy("Reloday\Gms\Models\MediaAttachment.created_at DESC")
            ->limit(1)
            ->execute();

        if(count($attachments) == 0){
            return [];
        }

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
     * Parse Data to Array
     */
    public function parsedDataToArray(){
        $mediaObject = $this->getMedia();

        $item = $mediaObject->toArray();
        $item['owner_company_id'] = $this->getOwnerCompanyId();
        $item['media_attachment_uuid'] = $this->getUuid();
        $item['assignee_folder_uuid'] = $this->getFolderUuid();
        if ($this->getFolderUuid()) {
            $mediaFolder = MediaFolder::findFirstByUuid($this->getFolderUuid());
            $item['assignee_folder_name'] = $mediaFolder ? $mediaFolder->getName() : null;
        } else {
            $item['assignee_folder_name'] = null;
        }

        $item['created_at'] = strtotime($this->getCreatedAt()) * 1000;
        $item['updated_at'] = strtotime($this->getUpdatedAt()) * 1000;
        $item['company_uuid'] = $mediaObject->getCompany() ? $mediaObject->getCompany()->getUuid() : null;

        $item['media_attachment_id'] = $this->getId();
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

        $item['media_attachments'] = $this->toArray();
        $item['is_employee'] = $mediaObject->isEmployee();
        $item['attached_by_company'] = $mediaObject->getCompany() ? $mediaObject->getCompany()->getName() : null;
        $item['is_thumb'] = $this->getIsThumb() == 1;

        if ($this->getUserProfile()) {
            $item['attached_by'] = $this->getUserProfile()->getFullname();
        } else if ($mediaObject->isEmployee() && $this->getEmployee()) {
            $item['attached_by'] = $this->getEmployee()->getFullname();
        } else {
            $item['attached_by'] = null;
        }

        if ($this->getOwnerCompanyId() > 0) {
            $item['attached_by_company'] = $this->getOwnerCompany()->getName();
        }

        return $item;
    }
}
