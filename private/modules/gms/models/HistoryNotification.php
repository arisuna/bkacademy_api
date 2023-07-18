<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class HistoryNotification extends \Reloday\Application\Models\HistoryNotificationExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	const LIMIT_PER_PAGE = 20;

	public function initialize(){
		parent::initialize(); 
	}


    /**
     * find list
     * @param $options
     * @return mixed
     */
    public static function __findWithFilter($options = []){
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\HistoryNotification', 'HistoryNotification');
        $queryBuilder->distinct(true);

        $queryBuilder->where('HistoryNotification.company_uuid = :company_uuid:', [
            'company_uuid' => ModuleModel::$company->getUuid()
        ]);

        if(isset($options['unread']) && is_bool($options['unread']) && $options['unread'] == true){
            $queryBuilder->andWhere('HistoryNotification.is_read = :unread:', [
                'unread' => self::IS_UNREAD
            ]);
        }

        if(isset($options['isHistoryComment']) && is_bool($options['isHistoryComment']) && $options['isHistoryComment'] == true){
            $queryBuilder->andWhere('HistoryNotification.is_comment_notification = :is_comment_notification:', [
                'is_comment_notification' => ModelHelper::YES
            ]);
        }

        if (isset($options['object_uuid']) && is_string($options['object_uuid']) && $options['object_uuid'] != '') {
            $queryBuilder->andwhere('HistoryNotification.object_uuid = :object_uuid:', [
                'object_uuid' => $options['object_uuid']
            ]);
        }

        if (isset($options['user_profile_uuid']) && is_string($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
            $queryBuilder->andwhere('HistoryNotification.user_profile_uuid = :user_profile_uuid:', [
                'user_profile_uuid' => $options['user_profile_uuid']
            ]);
        }

        if(isset($options['isAdminOrManager']) && is_bool($options['isAdminOrManager']) && $options['isAdminOrManager'] == false){
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'HistoryNotification.object_uuid = DataUserMember.object_uuid AND (DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER . ' OR DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_REPORTER . ' OR DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_VIEWER . ') ', 'DataUserMember');
            $queryBuilder->andwhere('DataUserMember.user_profile_uuid = :user_profile_uuid:', [
                'user_profile_uuid' => $options['user_profile_uuid']
            ]);
        }

        if (isset($options['lastTimeRead']) && is_numeric($options['lastTimeRead']) && $options['lastTimeRead'] > 0) {
            $queryBuilder->andwhere('HistoryNotification.created_at > :last_time_read:', [
                'last_time_read' => $options['lastTimeRead']
            ]);
        }

        $queryBuilder->orderBy("HistoryNotification.created_at DESC");
        $queryBuilder->groupBy('HistoryNotification.id');

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
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
            $history_notification_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $data = $item->parseDataToArray();
                    $data['isRead'] = $item->isRead();
                    $data['isToday'] = false;
                    if(isset($options['todayStart']) && is_numeric($options['todayStart'])){
                        if($data['time'] >= $options['todayStart']){
                            $data['isToday'] = true;
                        }
                    }

                    $history_notification_array[] = $data;
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
                'data' => $history_notification_array,
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}
