<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayBatchModel;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class CommunicationTopic extends \Reloday\Application\Models\CommunicationTopicExt
{
    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 20;

    const HAVE_ATTACHMENT_YES = 1;
    const HAVE_ATTACHMENT_NO = 0;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'cache' => [
                'key' => 'COMPANY_' . $this->getCompanyId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);
        $this->belongsTo('owner_user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'OwnerUserProfile',
            'cache' => [
                'key' => 'USER_PROFILE_' . $this->getOwnerUserProfileId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
            'reusable' => true,
        ]);
        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee',
            'cache' => [
                'key' => 'EMPLOYEE_' . $this->getEmployeeId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
            'reusable' => true,
        ]);
        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', [
            'alias' => 'RelocationServiceCompany',
        ]);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation',
        ]);
        $this->belongsTo('assignment_id', 'Reloday\Gms\Models\Assignment', 'id', [
            'alias' => 'Assignment',
        ]);
        $this->belongsTo('service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', [
            'alias' => 'ServiceCompany',
            'reusable' => true,
            'cache' => [
                'key' => 'SERVICE_COMPANY_' . $this->getServiceCompanyId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
        $this->belongsTo('first_sender_user_communication_email_id', 'Reloday\Gms\Models\UserCommunicationEmail', 'id', [
            'alias' => 'FirstSenderUserCommunicationEmail',
        ]);
        $this->belongsTo('task_uuid', 'Reloday\Gms\Models\Task', 'id', [
            'alias' => 'Task',
        ]);
    }

    public function getFirstMessage()
    {

        $communicationTopicMessage = CommunicationTopicMessage::findFirst([
            "conditions" => "communication_topic_id = ".$this->getId() . " and position = 1",
        ]);
        return $communicationTopicMessage;
    }

    /**\
     * @return bool|\Phalcon\Mvc\Model\MessageInterface[]|RelodayDynamoORM[]
     */
    public function getTopicMessages()
    {
        $communicationTopicMessages = CommunicationTopicMessage::find([
            "conditions" => "communication_topic_id = ".$this->getId(),
            "order" => "position DESC"
        ]);
        return $communicationTopicMessages;
    }

    /**
     * @return array
     */
    public function getFollowerUserProfiles()
    {

        if (is_array($this->getFollowers())) {
            $followers = $this->getFollowers();
        } else {
            $followers = json_decode($this->getFollowers());
        }


        $follower_array = [];
        if (count($followers) > 0) {
            foreach ($followers as $followerUserProfileId) {
                if (is_integer($followerUserProfileId)) {
                    $item = UserProfile::findFirstByIdCache($followerUserProfileId);
                    if ($item) {
                        $follower_array[] = $item;
                    }
                }
            }
        }
        return $follower_array;
    }


    /**
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array())
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\CommunicationTopic', 'CommunicationTopic');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = CommunicationTopic.service_company_id', 'ServiceCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Employee.id = CommunicationTopic.employee_id', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = CommunicationTopic.relocation_id', 'Relocation');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany.id = CommunicationTopic.relocation_service_company_id', 'RelocationServiceCompany');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Task', 'Task.uuid = CommunicationTopic.task_uuid', 'Task');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\UserProfile', 'Owner.id = CommunicationTopic.owner_user_profile_id', 'Owner');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\CommunicationTopicMessage', 'CommunicationTopicMessage.communication_topic_id = CommunicationTopic.id', 'CommunicationTopicMessage');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\CommunicationTopicMessage', 'CommunicationTopicMessageDraft.communication_topic_id = CommunicationTopic.id AND CommunicationTopicMessageDraft.status ='.CommunicationTopicMessage::STATUS_DRAFT, 'CommunicationTopicMessageDraft');
        $queryBuilder->columns([
            'CommunicationTopic.id',
            'CommunicationTopic.uuid',
            'CommunicationTopic.company_id',
            'CommunicationTopic.owner_user_profile_id',
            'CommunicationTopic.employee_id',
            'CommunicationTopic.relocation_id',
            'CommunicationTopic.relocation_service_company_id',
            'CommunicationTopic.service_company_id',
            'CommunicationTopic.assignment_id',
            'CommunicationTopic.task_uuid',
            'CommunicationTopic.subject',
            'CommunicationTopic.created_at',
            'CommunicationTopic.updated_at',
            'CommunicationTopic.status',
            'CommunicationTopic.conversation_id',
            'CommunicationTopic.flags',
            'CommunicationTopic.followers',
            'CommunicationTopic.first_sender_user_communication_email_id',
            'CommunicationTopic.first_sender_email',
            'CommunicationTopic.first_sender_name',
            'CommunicationTopic.has_attachment',
            'CommunicationTopic.is_forward',
            'CommunicationTopic.last_sent',
            'CommunicationTopic.last_receive',
            'CommunicationTopic.has_draft',
            'CommunicationTopic.is_draft',
            'owner_id' => 'Owner.id',
            'owner_uuid' => 'Owner.uuid',
            'owner_firstname' => 'Owner.firstname',
            'owner_lastname' => 'Owner.lastname',
            'employee_id' => 'Employee.id',
            'employee_uuid' => 'Employee.uuid',
            'employee_firstname' => 'Employee.firstname',
            'employee_lastname' => 'Employee.lastname',
            'service_name' => 'ServiceCompany.name',
            'relocation_id' => 'Relocation.id',
            'relocation_employee_id' => 'Relocation.employee_id',
            'relocation_assignment_id' => 'Relocation.assignment_id',
            'relocation_uuid' => 'Relocation.uuid',
            'relocation_name' => 'Relocation.name',
            'relocation_identify' => 'Relocation.identify',
            'number_of_message' => 'Count(DISTINCT(CommunicationTopicMessage.id))',
            'number_of_draft' => 'COUNT(DISTINCT(CommunicationTopicMessageDraft.id))',
        ]);
        $queryBuilder->where('CommunicationTopic.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);
        $bindArray = [];

        $bindArray['company_id'] = ModuleModel::$company->getId();
        if (!$options["trash"]) {
            $queryBuilder->andWhere('CommunicationTopic.status = :status:', [
                'status' => self::STATUS_ACTIVE
            ]);

            $bindArray['status'] = self::STATUS_ACTIVE;
        } else {
            $queryBuilder->andWhere('CommunicationTopic.status = :status:', [
                'status' => self::STATUS_ARCHIVED
            ]);

            $bindArray['status'] = self::STATUS_ARCHIVED;
        }
        if (isset($options['relocation_uuid']) && is_string($options['relocation_uuid']) && $options['relocation_uuid'] != '') {
            $queryBuilder->andwhere('Relocation.uuid = :relocation_uuid:', [
                'relocation_uuid' => $options['relocation_uuid']
            ]);
        }
        if (isset($options['task_uuid']) && is_string($options['task_uuid']) && $options['task_uuid'] != '') {
            $queryBuilder->andwhere('Task.uuid = :task_uuid:', [
                'task_uuid' => $options['task_uuid']
            ]);
        }
        if (isset($options['relocation_service_company_uuid']) && is_string($options['relocation_service_company_uuid']) && $options['relocation_service_company_uuid'] != '') {
            $queryBuilder->andwhere('RelocationServiceCompany.uuid = :relocation_service_company_uuid:', [
                'relocation_service_company_uuid' => $options['relocation_service_company_uuid']
            ]);
        }
        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere('CommunicationTopic.subject Like :query: or CommunicationTopic.first_sender_email Like :query: or ServiceCompany.name Like :query: or concat(concat(Employee.firstname, " "), Employee.lastname) like :query:', [
                'query' => '%' . $options['query'] . '%'
            ]);
        }
        $queryBuilder->andWhere('(json_contains(CommunicationTopic.followers, :user_profile_id_like:) AND CommunicationTopic.is_draft = 0) OR CommunicationTopic.owner_user_profile_id = :user_profile_id:', [
            "user_profile_id" => ModuleModel::$user_profile->getId(),
            "user_profile_id_like" => json_encode([intval(ModuleModel::$user_profile->getId())])
        ]);
        $bindArray['user_profile_id'] = ModuleModel::$user_profile->getId();
        $bindArray['user_profile_id_like'] = '%' . ModuleModel::$user_profile->getId() . '%';

        if (isset($options['followers_ids']) && is_array($options['followers_ids']) && count($options['followers_ids']) > 0) {
            $queryString = "";
            $i = 0;
            foreach ($options['followers_ids'] as $id) {
                if ($i > 0) {
                    $queryString .= ' OR  ';
                }
                $queryString .= 'json_contains(CommunicationTopic.followers, \'' . json_encode([intval($id)]) . '\')';
                $i++;
            };
            $queryBuilder->andWhere($queryString);
        }

        if (isset($options['is_sent']) && is_bool($options['is_sent']) && $options['is_sent'] == true) {
            $queryBuilder->andwhere("CommunicationTopic.is_forward = :status_is_sent:", [
                'status_is_sent' => Helpers::NO
            ]);
            $queryBuilder->andwhere("CommunicationTopic.last_sent > 0");
        }

        if (isset($options['is_draft']) && is_bool($options['is_draft']) && $options['is_draft'] == true) {
            $queryBuilder->andwhere("CommunicationTopic.has_draft = :status_has_draft:", [
                'status_has_draft' => Helpers::YES
            ]);
//            $queryBuilder->andwhere("CommunicationTopic.is_draft = :is_draft:", [
//                'is_draft' => Helpers::YES
//            ]);
        } else {
            if (isset($options['include_draft']) && is_bool($options['include_draft']) && $options['include_draft'] == true) {
                // do nothing
            } else {
                $queryBuilder->andwhere("CommunicationTopic.is_draft = :is_draft:", [
                    'is_draft' => Helpers::NO
                ]);
            }
        }

        if (isset($options['assignment_ids']) && is_array($options['assignment_ids']) && count($options['assignment_ids']) > 0) {
            $queryBuilder->andwhere("CommunicationTopic.assignment_id IN ({assignment_ids:array})", [
                'assignment_ids' => $options['assignment_ids']
            ]);
            $bindArray['assignment_ids'] = $options['assignment_ids'];
        }
        if (isset($options['service_ids']) && is_array($options['service_ids']) && count($options['service_ids']) > 0) {
            $queryBuilder->andwhere("CommunicationTopic.service_company_id IN ({service_ids:array})", [
                'service_ids' => $options['service_ids']
            ]);
            $bindArray['service_ids'] = $options['service_ids'];
        }

        if (isset($options['employee_ids']) && is_array($options['employee_ids']) && count($options['employee_ids']) > 0) {
            $queryBuilder->andwhere("CommunicationTopic.employee_id IN ({employee_ids:array})", [
                'employee_ids' => $options['employee_ids']
            ]);
            $bindArray['employee_ids'] = $options['employee_ids'];
        }

        if (isset($options['sender_ids']) && is_array($options['sender_ids']) && count($options['sender_ids']) > 0) {
            $queryBuilder->andWhere("CommunicationTopic.owner_user_profile_id IN ({sender_ids:array})", [
                'sender_ids' => $options['sender_ids']
            ]);
            $bindArray['sender_ids'] = $options['sender_ids'];
        }
//        if (!$options["trash"]) {
//            if (isset($options['is_draft']) && is_bool($options['is_draft']) && $options['is_draft'] == true) {
//                $queryBuilder->orderBy("CommunicationTopic.updated_at DESC");
//            } else {
//                $queryBuilder->orderBy("CommunicationTopic.last_sent DESC");
//            }
//        } else {
//            $queryBuilder->orderBy("CommunicationTopic.updated_at DESC");
//        }
        $queryBuilder->orderBy("CommunicationTopic.updated_at DESC");
        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "last_sent") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['CommunicationTopic.last_sent ASC']);
                } else {
                    $queryBuilder->orderBy(['CommunicationTopic.last_sent DESC']);
                }
            }
            if ($order['field'] == "last_receive") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['CommunicationTopic.last_receive ASC']);
                } else {
                    $queryBuilder->orderBy(['CommunicationTopic.last_receive DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['CommunicationTopic.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['CommunicationTopic.created_at DESC']);
                }
            }
            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['CommunicationTopic.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['CommunicationTopic.updated_at DESC']);
                }
            }
        }
        $queryBuilder->groupBy('CommunicationTopic.id');
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
                "limit" => self::LIMIT_PER_PAGE,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $itemArray = [];
            if (count($pagination->items) > 0) {
                foreach ($pagination->items as $item) {
                    $itemElement = $item->toArray();
                    $itemElement['owner_id'] = intval($itemElement['owner_id']);
                    $itemElement['id'] = intval($itemElement['id']);
                    $itemElement['company_id'] = intval($itemElement['company_id']);
                    $itemElement['owner_user_profile_id'] = intval($itemElement['owner_user_profile_id']);
                    $itemElement['employee_id'] = intval($itemElement['employee_id']);
                    $itemElement['relocation_id'] = intval($itemElement['relocation_id']);
                    $itemElement['relocation_service_company_id'] = intval($itemElement['relocation_service_company_id']);
                    $itemElement['service_company_id'] = intval($itemElement['service_company_id']);
                    $itemElement['status'] = intval($itemElement['status']);
                    $itemElement['first_sender_user_communication_email_id'] = intval($itemElement['first_sender_user_communication_email_id']);
                    $itemElement['has_attachment'] = intval($itemElement['has_attachment']);
                    $itemElement['assignment_id'] = intval($itemElement['assignment_id']);
                    $itemElement['is_forward'] = intval($itemElement['is_forward']);
                    $itemElement['owner'] = [
                        'id' =>intval( $itemElement['owner_id']),
                        'uuid' => $itemElement['owner_uuid'],
                        'firstname' => $itemElement['owner_firstname'],
                        'lastname' => $itemElement['owner_lastname'],
                    ];
                    $itemElement['assignee'] = [
                        'id' => intval($itemElement['employee_id']),
                        'uuid' => $itemElement['employee_uuid'],
                        'firstname' => $itemElement['employee_firstname'],
                        'lastname' => $itemElement['employee_lastname'],
                    ];

                    $itemElement['from'] = [
                        'email' =>$itemElement['first_sender_email'],
                        'name' => $itemElement['first_sender_name'],
                    ];
                    $itemElement['from_email'] =  $itemElement['first_sender_email'];
                    $itemElement['deleted'] = false;
                    $itemElement['filtered'] = true;
                    $followers = json_decode($itemElement['followers']);
                    $follower_array = [];
                    if (count($followers) > 0) {
                        foreach ($followers as $followerUserProfileId) {
                            if (is_integer($followerUserProfileId)) {
                                $item = UserProfile::findFirstByIdCache($followerUserProfileId);
                                if ($item) {
                                    $follower_array[] = $item;
                                }
                            }
                        }
                    }
                    $itemElement['followers'] = $follower_array;
                    $itemElement['created_at'] = strtotime($itemElement['created_at']);
                    if($itemElement['flags'] != null && $itemElement['flags'] != "[]") {
                        $flags = json_decode($itemElement['flags'], true);
                        $itemElement['is_flagged'] = in_array(ModuleModel::$user_profile->getId(), $flags);
                    } else {
                        $itemElement['is_flagged'] = false;
                    }
                    $itemElement['relocation'] = [
                        'id' => $itemElement['relocation_id'],
                        'uuid' => $itemElement['relocation_employee_id'],
                        'employee_id' => $itemElement['relocation_assignment_id'],
                        'assignment_id' => $itemElement['relocation_uuid'],
                        'name' => $itemElement['relocation_name'],
                        'identify' => $itemElement['relocation_identify'],
                    ];
                    $itemElement['have_attachment'] = $itemElement['has_attachment'] == Helpers::YES;
                    $itemElement['number_of_message'] = intval($itemElement['number_of_message'] );
                    $itemElement['number_of_draft'] = intval($itemElement['number_of_draft'] );
                    if($itemElement['owner_user_profile_id'] != ModuleModel::$user_profile->getId()) {
                        $itemElement['number_of_message'] = $itemElement['number_of_message'] -  $itemElement['number_of_draft'];
                        $itemElement['number_of_draft'] = 0;
                    }
                    $itemElement['recipients'] = "";
                    $last_sent_message = CommunicationTopicMessage::findFirst([
                        "conditions" => "communication_topic_id = ".$itemElement['id'] ." and sender_email = :sender:",
                        "bind" => [
                            "sender" => $itemElement['first_sender_email']
                        ],
                        "order" => "position DESC"
                    ]);
                    if($last_sent_message && $last_sent_message->getRecipients() != null && $last_sent_message->getRecipients() != ""){
                        $recipients = json_decode($last_sent_message->getRecipients());
                        if(is_object($recipients) && isset( $recipients->to)) {
                            $tos = $recipients->to;
                            $i = 0;
                            if (is_array($tos) && count($tos) > 0) {
                                foreach ($tos as $to) {
                                    if ($i == 0) {
                                        $itemElement['recipients'] .= $to;
                                    } else {
                                        $itemElement['recipients'] .= ", " . $to;
                                    }
                                    $i++;
                                }
                            }
                        }
                    }
                    $itemArray[] = $itemElement;

                }
            }

            return [
                // 'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'data' => $itemArray,
                'before' => $pagination->before,
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
     * @return bool
     */
    public function belongsToGms()
    {
        return $this->getCompanyId() == ModuleModel::$company->getId();
    }

    /**
     * @return bool
     */
    public function belongsToUser()
    {
        if ($this->getSenderUserProfileId() == ModuleModel::$user_profile->getId()) return true;
        else {
            if ($this->countCommunicationTopicFollower([
                'conditions' => 'user_profile_id = :user_profile_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                ],
                'limit' => 1])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $user_profile_id
     */
    public function isUserFlag($user_profile_id = 0)
    {
        if ($user_profile_id == 0) $user_profile_id = ModuleModel::$user_profile->getId();
        $flagTopic = CommunicationTopicFlag::findTopicUser($this->getId(), $user_profile_id);
        if ($flagTopic) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * @return int
     */
    public static function countTotalFollowerThread()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\CommunicationTopic', 'CommunicationTopic');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = CommunicationTopic.uuid', 'DataUserMember');

        $queryBuilder->where('CommunicationTopic.company_id = ' . ModuleModel::$company->getId());
        $queryBuilder->andWhere('DataUserMember.user_profile_id = ' . ModuleModel::$user_profile->getId());
        $queryBuilder->orderBy("CommunicationTopic.created_at");

        $selectCount = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));
        if ($selectCount) {
            return count($selectCount);
        } else {
            return 0;
        }
    }

    /**
     * @return int
     */
    public static function countTotalThread()
    {

        $total = self::find(["conditions" => "owner_user_profile_id = :user_profile_id:", "bind" => ["user_profile_id" => ModuleModel::$user_profile->getId()]]);
        return count($total);
    }

    /**
     * @return
     */
    public static function getRelatedTopic()
    {
        $topicArray = [];
        $di = \Phalcon\DI::getDefault();

        $queryBuilder = $di->get('modelsManager')->createBuilder();//new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\CommunicationTopic', 'CommunicationTopic');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = CommunicationTopic.uuid', 'DataUserMember');

        $queryBuilder->where('CommunicationTopic.company_id = ' . ModuleModel::$company->getId());
        $queryBuilder->andWhere('DataUserMember.user_profile_id = ' . ModuleModel::$user_profile->getId());
        $queryBuilder->orderBy("CommunicationTopic.created_at");


        try {

            $followerTopics = $queryBuilder->getQuery()->execute();
        } catch (\Exception $e) {
            $followerTopics = [];
        }


        try {
            $topicArray = self::find([
                "conditions" => "owner_user_profile_id = :user_profile_id:",
                "bind" => [
                    "user_profile_id" => ModuleModel::$user_profile->getId()
                ]
            ]);
            if (count($followerTopics) > 0) {
                foreach ($followerTopics as $item) {
                    $topicArray[] = $item;
                }
            }
        } catch (\Exception $e) {
            $topicArray = [];
        }
        return $topicArray;
    }
}
