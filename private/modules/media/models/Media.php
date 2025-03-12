<?php

namespace SMXD\Media\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Security\Random;
use SMXD\Media\Models\ModuleModel;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\MediaExt;
use SMXD\Application\Lib\Helpers;

class Media extends \SMXD\Application\Models\MediaExt
{

    /** @var [varchar] [url of token] */
    public $url_token;
    /** @var [varchar] [url of full load] */
    public $url_full;
    /** @var [varchar] [url of thumbnail] */
    public $url_thumb;
    /** @var [var char] [url of download] */
    public $url_download;

    const LIMIT_PER_PAGE = 12;

    /**
     * [afterFetch description]
     * @return [type] [description]
     */
    public function afterFetch()
    {
        $this->url_token = $this->getUrlToken();
        $this->url_thumb = $this->getUrlThumb();
        $this->url_full = $this->getUrlFull();
        $this->url_download = $this->getUrlDownload();
    }

    /**
     * [getTUrlToken description]
     * @return [type] [description]
     */
    public function getUrlToken($token = '')
    {
        $this->url_token = ApplicationModel::__getApiHostname() . '/media/file/load?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . base64_encode(ModuleModel::$user_token) . "&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
        return $this->url_token;
    }

    /**
     * [getUrlFull description]
     * @return [type] [description]
     */
    public function getUrlFull($token = '')
    {
        $this->url_full = ApplicationModel::__getApiHostname() . '/media/file/full?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . base64_encode(ModuleModel::$user_token) . "&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
        return $this->url_full;
    }

    /**
     * [getUrlThumb description]
     * @return [type] [description]
     */
    public function getUrlThumb($token = '')
    {
        $this->url_thumb = ApplicationModel::__getApiHostname() . '/media/file/thumbnail?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . base64_encode(ModuleModel::$user_token) . "&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
        return $this->url_thumb;
    }

    /**
     * [getUrlDownload description]
     * @return [type] [description]
     */
    public function getUrlDownload($token = '')
    {
        $this->url_download = ApplicationModel::__getApiHostname() . '/media/file/download?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . base64_encode(ModuleModel::$user_token) . "&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
        return $this->url_download;
    }

    /**
     *
     */
    public function setAllUrl()
    {
        $this->getUrlToken();
        $this->getUrlThumb();
        $this->getUrlFull();
        $this->getUrlDownload();
    }

    /**
     * @return string
     */
    public function getTokenKey64()
    {
        $token64 = ModuleModel::$user_token ? base64_encode(ModuleModel::$user_token) : "";
        return $token64;
    }

    /**
     * @return bool
     */
    public function belongsToCompany()
    {
//        return $this->getCompanyId() == ModuleModel::$company->getId();
    }

    /**
     * @return bool
     */
    public function belongsToCurrentUserProfile()
    {
        return $this->getUserUuid() == ModuleModel::$user->getUuid();
    }

    /**
     * @return bool
     */
    public function canEditMedia()
    {
        if ($this->belongsToCurrentUserProfile() == true) {
            return true;
        }
        if (ModuleModel::$user->isAdminOrManager()) {
            return true;
        }
        return false;
    }

    /**
     * Using Elastic search
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function __findWithFilters($options = [], $orders = [])
    {
        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Media\Models\Media', 'Media');
        $queryBuilder->distinct(true);

        $queryBuilder->orderBy(['Media.updated_at DESC']);
        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Media.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Media.updated_at DESC']);
                }
            }

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Media.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Media.name DESC']);
                }
            }
        }


        if (isset($options['creationDateTime']) && is_numeric($options['creationDateTime']) && $options['creationDateTime'] > 0) {
            $creationDateTime = Helpers::__convertTimeToUTC($options['creationDateTime']);
            $queryBuilder->andwhere("Media.updated_at >= :start_creation_date:", [
                'start_creation_date' => Helpers::__getDateBegin($creationDateTime),
            ]);
        }

        if (isset($options['creationDate']) && Helpers::__isDate($options['creationDate'], 'Y-m-d') && $options['creationDate']) {
            $queryBuilder->andwhere("Media.updated_at >= :start_creation_date:", [
                'start_creation_date' => Helpers::__getDateBegin($options['creationDate']),
            ]);
        }

        if (isset($options['isCompany']) && is_bool($options['isCompany']) && $options['isCompany'] == true) {
            $queryBuilder->andwhere("Media.company_uuid = :company_uuid: AND Media.is_company = 1", [
                'company_uuid' => $options['company_uuid'],
            ]);
        }

        if (isset($options['isHidden']) && is_bool($options['isHidden']) && $options['isHidden'] == false) {
            $queryBuilder->andwhere("Media.is_hidden != :is_hidden_yes: OR Media.is_hidden IS NULL", [
                'is_hidden_yes' => ModelHelper::YES,
            ]);
        }

        if (isset($options['isDeleted']) && is_bool($options['isDeleted']) && $options['isDeleted'] == true) {
            $queryBuilder->andwhere("Media.is_deleted = :is_deleted_yes:", [
                'is_deleted_yes' => self::IS_DELETE_YES,
            ]);
        } else {
            $queryBuilder->andwhere("Media.is_deleted = :is_deleted_no:", [
                'is_deleted_no' => self::IS_DELETE_NO,
            ]);
        }

        if (isset($options['isPrivate']) && is_bool($options['isPrivate']) && $options['isPrivate'] == true) {
            if (isset($options['folderUuid']) && Helpers::__isValidUuid($options['folderUuid']) && $options['folderUuid']) {
                $queryBuilder->andwhere("Media.folder_uuid = :folderUuid:", [
                    'folderUuid' => $options['folderUuid'],
                ]);
            }else{
                $queryBuilder->andwhere("Media.is_private = :is_private_yes:", [
                    'is_private_yes' => self::IS_PRIVATE_YES,
                ]);
            }
        }

        if (isset($options['isPrivate']) && is_bool($options['isPrivate']) && $options['isPrivate'] == false) {
            $queryBuilder->andwhere("Media.is_private = :is_private_no:", [
                'is_private_no' => self::IS_PRIVATE_NO,
            ]);
        }

        if (isset($options['company_id']) && Helpers::__isValidId($options['company_id']) && $options['company_id']) {
            $queryBuilder->andwhere("Media.company_id = :company_id:", [
                'company_id' => $options['company_id'],
            ]);
        }

        if (isset($options['userProfileUuid']) && Helpers::__isValidUuid($options['userProfileUuid']) && $options['userProfileUuid']) {
            $queryBuilder->andwhere("Media.user_uuid = :user_uuid:", [
                'user_uuid' => $options['userProfileUuid'],
            ]);
        }

        if (isset($options['user_uuid']) && Helpers::__isValidUuid($options['user_uuid']) && $options['user_uuid']) {
            $queryBuilder->andwhere("Media.user_uuid = :user_uuid:", [
                'user_uuid' => $options['user_uuid'],
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Media.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
            $bindArray['query'] = '%' . $options['query'] . '%';
        }


        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners'])) {
            $queryBuilder->andwhere("Media.user_uuid IN ({owners:array} )", [
                'owners' => $options['owners']
            ]);
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
            $pagination = $paginator->paginate();

            $mediaArray = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $mediaItem) {

                    $item = $mediaItem->getParsedData();

                    $can_attach_to_my_library = true;
                    if($mediaItem){
                        $newMedia = new Media();
                        $newMedia->setName($mediaItem->getName());
                        $newMedia->setUserUuid(ModuleModel::$user->getUuid());
                        $newMedia->setFileExtension($mediaItem->getFileExtension());
                        $newMedia->setIsDeleted(ModelHelper::NO);
                        $existed = $newMedia->checkFileNameExisted();
                        if($existed){
                            $can_attach_to_my_library = false;
                        }
                    }
                    $item['can_attach_to_my_library'] = $can_attach_to_my_library;

                    $mediaArray[] = $item;
                }
            }

            return [
                'success' => true,
                'query' => $queryBuilder->getQuery()->getSql(),
                'data' => $mediaArray,
                'before' => $pagination->before,
                'page' => $pagination->current,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param $items
     * @return mixed
     */
    public function getParsedData($items = [])
    {
        $item = $this->toArray();
        $item['uuid'] = $this->getUuid();
        $item['name'] = $this->getName();
        $item['basename'] = $this->getName();
        $item['extension'] = $this->getFileExtension();
        $item['file_type'] = $this->getFileType();
        $item['type'] = $this->getMimeType();
        $item['real_type'] = $this->getMimeType();
        $item['file_extension'] = $this->getFileExtension();


        $item['company_id'] = $this->getCompanyId();
//        $item['company_uuid'] = $this->getCompany()->getUuid();
        $item['user_uuid'] = $this->getUserUuid();
        $item['is_owner'] = $this->belongsToCurrentUserProfile();
        $item['is_company_owner'] = $this->belongsToCompany();
        $item['is_private'] = $this->getIsPrivate();

        $item['file_size'] = intval($this->getSize());
        $item['file_size_human_format'] = $this->getSizeHumainFormat();
        $item['s3_full_path'] = $this->getRealFilePath();
        // Convert second to millisecond | created_at and updated_at
        $item['created_at'] = strtotime($this->getCreatedAt()) * 1000;
        $item['updated_at'] = strtotime($this->getUpdatedAt()) * 1000;
        $item['image_data'] = [
            "url_public" => $this->getPublicUrl(),
            "url_token" => $this->getUrlToken(),
            "url_full" => $this->getUrlFull(),
            "url_thumb" => $this->getUrlThumb(),
            "url_download" => $this->getUrlDownload(),
            "name" => $this->getFilename()
        ];
        return $item;
    }

    /**
     * @return bool|void
     */
    public function beforeValidationOnCreate()
    {
        $this->setUserLoginId(ModuleModel::$user->getId());
        $this->setUserUuid(ModuleModel::$user->getUuid());
//        $this->setCompanyId(ModuleModel::$company->getId());
        parent::beforeValidationOnCreate();
    }

    /**
     *
     */
    public function beforeUpdate()
    {
        $this->clearCache();
    }

    /**
     * @return bool
     */
    public function clearCache()
    {
//        try {
//           return CacheHelper::__deleteModelsCache('MEDIA_' . $this->getId());
//        } catch (\Exception $e) {
//            return false;
//        }
    }

    /**
     * @return bool
     */
    public function canDelete()
    {
        if ($this && $this->getUserUuid() === ModuleModel::$user->getUuid()) {
            return true;
        } else {
            return false;
        }
    }

    public function canAttachMediaToLibrary(){
        $can_attach_to_my_library = true;
        $newMedia = new Media();
        $newMedia->setName($this->getName());
        $newMedia->setUserUuid(ModuleModel::$user->getUuid());
        $newMedia->setFileExtension($this->getFileExtension());
        $newMedia->setIsDeleted(ModelHelper::NO);
        $existed = $newMedia->checkFileNameExisted();
        if($existed){
            $can_attach_to_my_library = false;
        }
        return  $can_attach_to_my_library;
    }
}
