<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Gms\Models\ModuleModel;

use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class MediaOld extends \Reloday\Application\Models\MediaExt
{

    /** @var [varchar] [url of token] */
    public $url_token;
    /** @var [varchar] [url of full load] */
    public $url_full;
    /** @var [varchar] [url of thumbnail] */
    public $url_thumb;
    /** @var [var char] [url of download] */
    public $url_download;

    const LIMIT_PER_PAGE = 10;

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
        $this->url_token = ApplicationModel::__getApiHostname() . '/media/file/load/' . $this->getUuid() . '/' . $this->getFileType() . "/" . base64_encode(ModuleModel::$user_login_token) . "/name/" . urlencode($this->getName() . "." . $this->getFileExtension());
        return $this->url_token;
    }

    /**
     * [getUrlFull description]
     * @return [type] [description]
     */
    public function getUrlFull($token = '')
    {
        $this->url_full = ApplicationModel::__getApiHostname() . '/media/file/full/' . $this->getUuid() . '/' . $this->getFileType() . "/" . base64_encode(ModuleModel::$user_login_token) . "/name/" . urlencode($this->getName() . "." . $this->getFileExtension());
        return $this->url_full;
    }

    /**
     * [getUrlThumb description]
     * @return [type] [description]
     */
    public function getUrlThumb($token = '')
    {
        $this->url_thumb = ApplicationModel::__getApiHostname() . '/media/file/thumbnail/' . $this->getUuid() . '/' . $this->getFileType() . "/" . base64_encode(ModuleModel::$user_login_token) . "/name/" . urlencode($this->getName() . "." . $this->getFileExtension());
        return $this->url_thumb;
    }

    /**
     * [getUrlDownload description]
     * @return [type] [description]
     */
    public function getUrlDownload($token = '')
    {
        $this->url_download = ApplicationModel::__getApiHostname() . '/media/file/download/' . $this->getUuid() . '/' . $this->getFileType() . "/" . base64_encode(ModuleModel::$user_login_token) . "/name/" . urlencode($this->getName() . "." . $this->getFileExtension());
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
        $token64 = ModuleModel::$user_login_token ? base64_encode(ModuleModel::$user_login_token) : "";
        return $token64;
    }

    /**
     * @return bool
     */
    public function belongsToCompany()
    {
        return $this->getCompanyId() == ModuleModel::$company->getId();
    }

    /**
     * @return bool
     */
    public function belongsToCurrentUserProfile()
    {
        return $this->getUserProfileUuid() == ModuleModel::$user_profile->getUuid();
    }

    /**
     * @return bool
     */
    public function canEditMedia()
    {
        if ($this->belongsToCurrentUserProfile() == true) {
            return true;
        }
        if (ModuleModel::$user_profile->isAdminOrManager() && $this->belongsToCompany()) {
            return true;
        }
        return false;
    }

    /**
     * @param array $options
     * @param array $orders
     * @return array
     */
    public static function __findWithFilters($options = [], $orders = [])
    {
        if (isset($options['mode']) && is_string($options['mode'])) {
            $mode = $options['mode'];
        } else {
            $mode = "large";
        }
        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Media', 'Media');
        $queryBuilder->distinct(true);

        $queryBuilder->orderBy(['Media.created_at DESC']);
        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Media.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Media.created_at DESC']);
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
            $queryBuilder->andwhere("Media.created_at >= :start_creation_date:", [
                'start_creation_date' => Helpers::__getDateBegin($creationDateTime),
            ]);
        }

        if (isset($options['creationDate']) && Helpers::__isDate($options['creationDate']) && $options['creationDate']) {
            $queryBuilder->andwhere("Media.created_at >= :start_creation_date:", [
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
            $queryBuilder->andwhere("Media.is_private = :is_private_yes:", [
                'is_private_yes' => self::IS_PRIVATE_YES,
            ]);
        }

        if (isset($options['companyUuid']) && Helpers::__isValidUuid($options['companyUuid']) && $options['companyUuid']) {
            $queryBuilder->andwhere("Media.company_uuid = :company_uuid:", [
                'company_uuid' => $options['company_uuid'],
            ]);
        }

        if (isset($options['userProfileUuid']) && Helpers::__isValidUuid($options['userProfileUuid']) && $options['userProfileUuid']) {
            $queryBuilder->andwhere("Media.user_profile_uuid = :user_profile_uuid:", [
                'user_profile_uuid' => $options['userProfileUuid'],
            ]);
        }

        if (isset($options['folderUuid']) && Helpers::__isValidUuid($options['folderUuid']) && $options['folderUuid']) {
            $queryBuilder->andwhere("Media.folder_uuid = :folderUuid:", [
                'folderUuid' => $options['folderUuid'],
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Media.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
            $bindArray['query'] = '%' . $options['query'] . '%';
        }


        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners'])) {
            $queryBuilder->andwhere("Media.user_profile_uuid IN ({owners:array} )", [
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
            $pagination = $paginator->getPaginate();

            $mediaArray = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $mediaItem) {

                    $mediaArray[] = $mediaItem->getParsedData();
                }
            }

            return [
                'success' => true,
                'page' => $page,
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
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @return mixed
     */
    public function getParsedData()
    {
        $item = $this->toArray();
        $item['id'] = $this->getId();
        $item['uuid'] = $this->getUuid();
        $item['name'] = $this->getName();
        $item['file_type'] = $this->getFileType();
        $item['file_extension'] = $this->getFileExtension();

        $item['company_id'] = $this->getCompanyId();
        $item['user_profile_uuid'] = $this->getUserProfileUuid();
        $item['is_owner'] = $this->belongsToCurrentUserProfile();
        $item['is_company_owner'] = $this->belongsToCompany();

        $item['file_size'] = intval($this->getSize());
        $item['file_size_human_format'] = $this->getSizeHumainFormat();
        $item['s3_full_path'] = $this->getRealFilePath();

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
        $this->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $this->setUserLoginId(ModuleModel::$user_login->getId());
        $this->setCompanyId(ModuleModel::$company->getId());
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
        try {
            return CacheHelper::__deleteModelsCache('MEDIA_' . $this->getId());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function canDelete()
    {
        if ($this && $this->getCompanyId() === ModuleModel::$company->getId()) {
            return true;
        } else {
            return false;
        }
    }
}
