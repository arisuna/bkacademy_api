<?php

namespace Reloday\Gms\Models;

use AWS\CRT\Log;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Models\DataUserMemberExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class Comment extends \Reloday\Application\Models\CommentExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const SEEN = 1;
    const UNSEEN = 0;

    const IS_COMPLETE_MESSENGER = 1;
    const NOT_COMPLETE_MESSENGER = 0;

    public function initialize()
    {
        parent::initialize();
    }

    public function belongsToUser()
    {
        return $this->getUserProfileUuid() == ModuleModel::$user_profile->getUuid();
    }

    /**
     * find list
     * @param $options
     * @return mixed
     */
    public static function __findWithFilter($options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Comment', 'Comment');
        $queryBuilder->distinct(true);


        if (isset($options['user_profile_uuid']) && is_string($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
            $queryBuilder->andwhere('Comment.user_profile_uuid = :user_profile_uuid:', [
                'user_profile_uuid' => $options['user_profile_uuid']
            ]);
        }

        if (isset($options['object_uuid']) && is_string($options['object_uuid']) && $options['object_uuid'] != '') {
            $queryBuilder->andwhere('Comment.object_uuid = :object_uuid:', [
                'object_uuid' => $options['object_uuid']
            ]);
        }

        if (isset($options['report']) && is_numeric($options['report']) && $options['report'] >= 0) {
            $queryBuilder->andwhere('Comment.report = :report:', [
                'report' => $options['report']
            ]);
        }

        if (isset($options['sorts']) && is_object($options['sorts'])) {
            $sorts = (array)$options['sorts'];
            if (isset($sorts['field']) && isset($sorts['descending']) && strtolower($sorts['field']) == 'created_at' && $sorts['descending']) {
                $queryBuilder->orderBy(['Comment.is_complete_messenger DESC', 'Comment.created_at DESC']);
            } else {
                $queryBuilder->orderBy(['Comment.is_complete_messenger DESC', 'Comment.created_at ASC']);
            }
        } else {
            $queryBuilder->orderBy(['Comment.is_complete_messenger DESC', 'Comment.created_at DESC']);
        }

        $queryBuilder->groupBy('Comment.id');

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : (
        isset($options['length']) && is_numeric($options['length']) && $options['length'] > 0 ? $options['length'] : self::LIMIT_PER_PAGE);

        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $comment_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $data = $item->parseDataToArray();
                    $data['editable'] = $item->belongsToUser();
                    $comment_array[] = $data;
                }
            }

            return [
                'success' => true,
                'query' => $queryBuilder->getQuery()->getSql(),
                '$start' => $start,
                '$limit' => $limit,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'page' => $page,
                'data' => $comment_array,
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    public static function getCountInitiationMessages($objectUuid = '', $companyUuid = '')
    {
        if ($objectUuid == '' || $companyUuid == '') {
            return false;
        }

        $count = self::count([
            "conditions" => 'company_uuid = :company_uuid: AND object_uuid = :object_uuid: AND seen = :seen:',
            "bind" => [
                'company_uuid' => $companyUuid,
                'object_uuid' => $objectUuid,
                'seen' => self::UNSEEN,
            ]
        ]);

        return $count | 0;
    }

    public static function seenInitiationMessages($objectUuid = '', $companyUuid = ''): bool
    {
        if ($objectUuid == '' || $companyUuid == '') {
            return false;
        }

        $list = self::find([
            "conditions" => 'company_uuid = :company_uuid: AND object_uuid = :object_uuid: AND seen = :seen:',
            "bind" => [
                'company_uuid' => $companyUuid,
                'object_uuid' => $objectUuid,
                'seen' => self::UNSEEN,
            ]
        ]);

        if ($list && count($list) > 0) {
            foreach ($list as $comment) {
                $comment->setSeen(self::SEEN);
                $result = $comment->__quickUpdate();

                if (!$result['success']) {
                    return false;
                }
            }
        }

        return true;
    }
}
