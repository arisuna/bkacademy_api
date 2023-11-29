<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use SMXD\Application\Lib\ModelHelper;

class MediaAttachment extends \SMXD\Application\Models\MediaAttachmentExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('media_id', 'SMXD\Api\Models\Media', 'id', [
            'alias' => 'Media',
        ]);
    }

    public function belongsToUser(){
        return $this->getMedia()->getUserUuid() == ModuleModel::$user->getUuid();
    }



    public static function __findWithFilter($options = [], $orders = [])
    {

        /** object_uuid should be required */
        if (!isset($options['object_uuid'])) {
            return ['success' => false, 'items' => 0];
        }

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\Api\Models\MediaAttachment', 'MediaAttachment');
        $queryBuilder->innerjoin('\SMXD\Api\Models\Media', 'Media.id = MediaAttachment.media_id', 'Media');


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

            if ($order['field'] == "is_thumb") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['MediaAttachment.is_thumb ASC']);
                } else {
                    $queryBuilder->orderBy(['MediaAttachment.is_thumb DESC']);
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
                    $mediaObject = $mediaAttachmentObject->getMedia();

                    $item = $mediaObject->toArray();
                    $item['owner_company_id'] = $mediaAttachmentObject->getOwnerCompanyId();
                    $item['media_attachment_uuid'] = $mediaAttachmentObject->getUuid();

                    $item['created_at'] = strtotime($mediaAttachmentObject->getCreatedAt()) * 1000;
                    $item['updated_at'] = strtotime($mediaAttachmentObject->getUpdatedAt()) * 1000;
                    $item['company_uuid'] = $mediaObject->getCompany() ? $mediaObject->getCompany()->getUuid() : null;

                    $item['media_attachment_id'] = $mediaAttachmentObject->getId();
                    $item['name'] = $mediaObject->getNameOfficial();
                    $item['url_thumb'] = $mediaObject->getTemporaryThumbS3Url();
                    $item['url_full'] = $mediaObject->getTemporaryFileUrlS3();
                    $item['url_download'] = $mediaObject->getTemporaryFileUrlS3();
                    $item['can_delete'] = true;
                    $item['is_thumb'] = $mediaAttachmentObject->getIsThumb() == 1;

                    $medias[] = $item;
                }
            }

            return [
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

}
