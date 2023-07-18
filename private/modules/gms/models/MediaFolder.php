<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class MediaFolder extends \Reloday\Application\Models\MediaFolderExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const LIMIT_PER_PAGE = 10;
    const MAX_LIMIT_PER_PAGE = 1000;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @return bool
     */
    public function canEditFolder()
    {
        if ($this->belongsToCreatorUserProfile() == true) {
            return true;
        }
        if (ModuleModel::$user_profile->isAdminOrManager() && $this->belongsToCompany()) {
            return true;
        }
        return false;
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
    public function belongsToCreatorUserProfile()
    {
        return $this->getCreatorUserProfileUuid() == ModuleModel::$user_profile->getUuid();
    }

    /**
     * @return bool
     */
    public function isAssigneeFolder()
    {
        $employee = Employee::findFirstByUuid($this->getUserProfileUuid());
        if ($employee){
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
        $queryBuilder->addFrom('\Reloday\Gms\Models\MediaFolder', 'MediaFolder');
        $queryBuilder->distinct(true);


        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['MediaFolder.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['MediaFolder.created_at DESC']);
                }
            }

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['MediaFolder.name ASC']);
                } else {
                    $queryBuilder->orderBy(['MediaFolder.name DESC']);
                }
            }
        }else{
            $queryBuilder->orderBy(['MediaFolder.created_at DESC']);
        }


        if (isset($options['creationDateTime']) && is_numeric($options['creationDateTime']) && $options['creationDateTime'] > 0) {
            $creationDateTime = Helpers::__convertTimeToUTC($options['creationDateTime']);
            $queryBuilder->andwhere("MediaFolder.created_at = :creation_date:", [
                'creation_date_time' => $creationDateTime,
            ]);
        }

        if (isset($options['creationDate']) && Helpers::__isDate($options['creationDate']) && $options['creationDate']) {
            $queryBuilder->andwhere("MediaFolder.created_at = :creation_date:", [
                'creation_date' => $options['creationDate'],
            ]);
        }

        if (isset($options['isCompany']) && is_bool($options['isCompany']) && $options['isCompany'] == true) {
            $queryBuilder->andwhere("MediaFolder.company_id = :company_uuid: AND MediaFolder.is_company = 1", [
                'company_uuid' => $options['company_uuid'],
            ]);
        }

        if (isset($options['isPrivate']) && is_bool($options['isPrivate']) && $options['isPrivate'] == true) {
            $queryBuilder->andwhere("MediaFolder.is_private = :is_private_yes:", [
                'is_private_yes' => Helpers::YES,
            ]);
        }

        if (isset($options['companyId']) && Helpers::__isValidId($options['companyId']) && $options['companyId'] > 0) {
            $queryBuilder->andwhere("MediaFolder.company_id = :companyId:", [
                'companyId' => $options['companyId'],
            ]);
        }

        if (isset($options['userProfileUuid']) && Helpers::__isValidUuid($options['userProfileUuid']) && $options['userProfileUuid']) {
            $queryBuilder->andwhere("MediaFolder.user_profile_uuid = :user_profile_uuid:", [
                'user_profile_uuid' => $options['userProfileUuid'],
            ]);
        }


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("MediaFolder.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
            $bindArray['query'] = '%' . $options['query'] . '%';
        }


        if (isset($options['owners']) && is_array($options['owners']) && count($options['owners'])) {
            $queryBuilder->andwhere("MediaFolder.user_profile_uuid IN ({owners:array} )", [
                'owners' => $options['owners']
            ]);
        }


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::MAX_LIMIT_PER_PAGE;
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


            $mediaFolderArray = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $mediaFolderItem) {
                    $countMedias = $mediaFolderItem->countMedias([
                        'conditions' => 'is_deleted != 1',
                    ]);
                    $item = $mediaFolderItem->toArray();
                    $item['creator_user_name'] = $mediaFolderItem->getCreatorUserProfile() ? $mediaFolderItem->getCreatorUserProfile()->getFullname() : '';
                    $item['total_files'] = $countMedias;
                    $mediaFolderArray[] = $item;
                }
            }


            return [
                //'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'page' => $page,
                'data' => $mediaFolderArray,
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
}
