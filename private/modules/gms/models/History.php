<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class History extends \Reloday\Application\Models\HistoryExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;
	const LIMIT_PER_PAGE = 20;

	public function initialize(){
		parent::initialize(); 
	}


    /**
     * self action
     * @var
     */
    static $historyAction;
    /**
     * @param string $lang
     * @param bool $parseDynamoDb
     * @param array $customData
     * @return array
     */
    /**
     * params template
     * @var array
     */
    static $paramsTemplate = [
        'object_number' => '',
        'object_name' => '',
        'relocation_number' => '',
        'user_name' => '',
        'username' => '',
        'fullname' => '',
        'firstname' => '',
        'name' => '',
        'lastname' => '',
        'task_number' => '',
        'task_name' => '',
        'task_description' => '',
        'assignment_number' => '',
        'url' => '',
        'assignment_url' => '',
        'relocation_url' => '',
        'task_url' => '',
        'login_url' => '',
        'date' => '',
        'subject' => '',
        'assignee_name' => '',
        'time' => '',
        'servicename' => '',
        'service_name' => '',
        'eventname' => '',
        'service_event_name' => '',
        'targetuser' => '',
        'status' => '',
        'comment' => '',
    ];

    /**
     * find list
     * @param $options
     * @return mixed
     */
    public static function __findWithFilter($options = []){
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\History', 'History');
        $queryBuilder->distinct(true);

        if(isset($options['isNotFilterCompany']) && $options['isNotFilterCompany'] == true){
            $queryBuilder->where('History.id is not null');
        }else{
            $queryBuilder->where('History.company_uuid = :company_uuid:', [
                'company_uuid' => ModuleModel::$company->getUuid()
            ]);
        }


        if (isset($options['object_uuid']) && is_string($options['object_uuid']) && $options['object_uuid'] != '') {
            $queryBuilder->andwhere('History.object_uuid = :object_uuid:', [
                'object_uuid' => $options['object_uuid']
            ]);
        }

        if (isset($options['user_profile_uuid']) && is_string($options['user_profile_uuid']) && $options['user_profile_uuid'] != '') {
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'History.object_uuid = DataUserMember.object_uuid AND (DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_OWNER . ' OR DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_REPORTER . ' OR DataUserMember.member_type_id = ' . DataUserMember::MEMBER_TYPE_VIEWER . ') ', 'DataUserMember');
            $queryBuilder->andwhere('DataUserMember.user_profile_uuid = :user_profile_uuid:', [
                'user_profile_uuid' => $options['user_profile_uuid']
            ]);
        }

        $queryBuilder->orderBy("History.created_at DESC");
        $queryBuilder->groupBy(['History.user_action', 'History.updated_at']);

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
            $history_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $data = $item->parseDataToArray();
                    if ($item->getType()) {
                        $task = Task::findFirstByUuidCache($item->getObjectUuid());

                        if ($task instanceof Task) {
                            $data['is_deleted'] = $task->getStatus() == Task::STATUS_ARCHIVED;
                        }
                    }
                    $history_array[] = $data;
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
                'data' => $history_array,
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
