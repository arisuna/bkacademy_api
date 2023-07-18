<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Client\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\RelodayBatchModel;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class OldCommunicationTopic extends \Reloday\Application\Models\OldCommunicationTopicExt
{
    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 20;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company',
            'reusable' => true,
        ]);
        $this->belongsTo('sender_user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'OwnerUserProfile',
            'reusable' => true,
        ]);
        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee',
            'reusable' => true,
        ]);
        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', [
            'alias' => 'RelocationServiceCompany',
            'reusable' => true,
        ]);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation',
            'reusable' => true,
        ]);
        $this->belongsTo('service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', [
            'alias' => 'ServiceCompany',
            'reusable' => true,
        ]);
        $this->belongsTo('task_uuid', 'Reloday\Gms\Models\Task', 'id', [
            'alias' => 'Task',
            'reusable' => true,
        ]);

        /*
        $this->hasManyToMany(
            'id',
            'Reloday\Gms\Models\CommunicationTopicFollower',
            'communication_topic_id', 'user_profile_id',
            'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'FollowerUserProfile',
            'reusable' => true,
        ]);
        */

        $this->hasMany('id',
            'Reloday\Gms\Models\OldCommunicationTopic',
            'communication_topic_id', [
                'alias' => 'CommunicationTopicItem',
                'reusable' => true,
                'foreignKey' => [
                    "action" => Relation::ACTION_CASCADE,
                ]
            ]);

        $this->hasMany('id',
            'Reloday\Gms\Models\OldCommunicationTopicFollower',
            'communication_topic_id', [
                'alias' => 'CommunicationTopicFollower',
                'reusable' => false,
                'foreignKey' => [
                    "action" => Relation::ACTION_CASCADE,
                ]
            ]);

        $this->hasMany('id', 'Reloday\Gms\Models\OldCommunicationTopicRead', 'topic_id', [
            'alias' => 'CommunicationTopicRead',
            'reusable' => true,
            'foreignKey' => [
                "action" => Relation::ACTION_CASCADE,
            ]
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\OldCommunicationTopicRead', 'main_topic_id', [
            'alias' => 'MainCommunicationTopicRead',
            'reusable' => true,
            'foreignKey' => [
                "action" => Relation::ACTION_CASCADE,
            ]
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\OldCommunicationTopicContact', 'communication_topic_id', [
            'alias' => 'CommunicationTopicContact',
            'foreignKey' => [
                "action" => Relation::ACTION_CASCADE,
            ]
        ]);


    }

    /**
     * @return array
     */
    public function findByMainUserUser($user_profile_id)
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        $queryBuilder->distinct(true);

        $queryBuilder->leftjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower',
            'CommunicationTopicFollower.communication_topic_id = CommunicationTopic.id', 'CommunicationTopicFollower');
        $queryBuilder->where('CommunicationTopic.sender_user_profile_id = :user_profile_id:');
        $queryBuilder->orwhere("CommunicationTopicFollower.user_profile_id = :user_profile_id:");
        $queryBuilder->orderBy("CommunicationTopic.created_at");

        $bindArray = [];
        $bindArray['user_profile_id'] = $user_profile_id;

        $communication_array = [];
        $communications = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));

        //var_dump( $queryBuilder->getPhql() ); die();
        if ($communications->count() > 0) {
            foreach ($communications as $item) {
                $communication_array[$item->getUuid()] = $item->toArray();
            }
            return $communication_array;
        }
    }


    /**
     * @return array
     */
    public static function __findWithFilter($options = array())
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        //$queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower',
            'CommunicationTopicOwner.communication_topic_id = CommunicationTopic.id', 'CommunicationTopicOwner');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower',
            'CommunicationTopicFollower.communication_topic_id = CommunicationTopic.id', 'CommunicationTopicFollower');
        $queryBuilder->where('CommunicationTopic.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);

        $bindArray = [];
        $bindArray['company_id'] = ModuleModel::$company->getId();

        if (isset($options['sender_user_profile_id']) && $options['sender_user_profile_id'] > 0) {
            $queryBuilder->andwhere("CommunicationTopic.sender_user_profile_id = :sender_user_profile_id: OR CommunicationTopicFollower.user_profile_id = :sender_user_profile_id:", [
                'sender_user_profile_id' => $options['sender_user_profile_id']
            ]);
            $bindArray['sender_user_profile_id'] = $options['sender_user_profile_id'];
        }

        if (isset($options['communication_topic_id']) && $options['communication_topic_id'] > 0) {
            $queryBuilder->andwhere("CommunicationTopic.communication_topic_id = :communication_topic_id: OR CommunicationTopic.id = :communication_topic_id:", [
                'communication_topic_id' => $options['communication_topic_id']
            ]);
            $bindArray['communication_topic_id'] = $options['communication_topic_id'];
        } else {
            $queryBuilder->andwhere("CommunicationTopic.communication_topic_id IS NULL OR CommunicationTopic.communication_topic_id = 0");
        }


        if (isset($options['followers_ids']) && is_array($options['followers_ids']) && count($options['followers_ids']) > 0) {
            $queryBuilder->andWhere("CommunicationTopicFollower.user_profile_id IN ({followers_ids:array})", [
                'followers_ids' => $options['followers_ids']
            ]);
            $bindArray['followers_ids'] = $options['followers_ids'];
        }

        if (isset($options['user_profile_id']) && is_numeric($options['user_profile_id']) && $options['user_profile_id'] > 0) {
            $queryBuilder->andWhere("CommunicationTopicOwner.user_profile_id = :user_profile_id:", [
                'user_profile_id' => $options['user_profile_id']
            ]);
            $bindArray['user_profile_id'] = $options['user_profile_id'];
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

        $queryBuilder->orderBy("CommunicationTopic.created_at DESC");
        $queryBuilder->groupBy('CommunicationTopic.id');

        $loadContent = isset($options['load_content']) && is_bool($options['load_content']) && $options['load_content'] == true ? true : false;

        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        if ($page == 0) {
            $page = intval($start / self::LIMIT_PER_PAGE) + 1;
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
                    $owner = $item->getOwnerUserProfile();
                    $itemElement['owner'] = [
                        'id' => $owner ? $owner->getId() : '',
                        'uuid' => $owner ? $owner->getUuid() : '',
                        'firstname' => $owner ? $owner->getFirstname() : '',
                        'lastname' => $owner ? $owner->getLastname() : '',
                    ];
                    $employee = $item->getEmployee();
                    $itemElement['assignee'] = [
                        'id' => $employee ? $employee->getId() : null,
                        'uuid' => $employee ? $employee->getUuid() : null,
                        'firstname' => $employee ? $employee->getFirstname() : null,
                        'lastname' => $employee ? $employee->getLastname() : null,
                    ];


                    $itemElement['to'] = [
                        'email' => $item->parseRecipientEmail(),
                        'name' => $item->getRecipientName(),
                    ];

                    $parseEmailFrom = explode("@", $item->getFromEmail());
                    $itemElement['from'] = [
                        'email' => $item->getFromEmail(),
                        'name' => $item->getFromName() != '' ? $item->getFromName() : reset($parseEmailFrom)
                    ];

                    if (isset($options['load_content']) && $options['load_content'])

                        if ($itemElement['sender_user_profile_id'] > 0) {
                            $sender = UserProfile::findFirstByIdCache($itemElement['sender_user_profile_id']);
                            if ($sender) {
                                $itemElement['from']['uuid'] = $sender->getUuid();
                            }
                        } else {
                            $sender = UserProfile::findFirstByWorkemailCache($item->getFromEmail());
                            if ($sender) {
                                $sender = Contact::findFirstByEmailCache($item->getFromEmail());
                            }
                            if ($sender) {
                                $itemElement['from']['type'] = 'contact';
                                $itemElement['from']['uuid'] = $sender->getUuid();
                            }
                        }

                    $service = $item->getServiceCompany();
                    $itemElement['service_name'] = $service ? $service->getName() : '';
                    $itemElement['deleted'] = false;
                    $itemElement['filtered'] = true;
                    $itemElement['followers'] = $item->parseFollowerUuids();
                    $itemElement['created_at'] = strtotime($item->getCreatedAt());
                    $itemElement['is_flagged'] = $item->isUserFlag() == true ? true : false;
                    $itemElement['have_attachment'] = $item->getHaveAttachment() == true ? true : false;
                    $itemElement['content'] = $loadContent ? $item->getContentFromS3() : '';

                    if ((isset($options['communication_topic_id']) && $options['communication_topic_id'] > 0)) {
                        $itemElement['total_unread'] = 0;
                    } else {
                        $itemElement['total_unread'] = $item->getCountUnreadMessageItem(ModuleModel::$user_profile->getId());
                    }

                    $itemElement['total_messages'] = $item->getCountTotalMessageItem();
                    $itemElement['total_read'] = $item->getCountReadMessageItem(ModuleModel::$user_profile->getId());

                    if ($itemElement['total_unread'] > 0) {
                        $itemElement['read'] = false;
                    } else {
                        $itemElement['read'] = true;
                    }


                    $itemArray[] = $itemElement;

                    /*
                    $itemElement['deleted'] = false;
                    $itemElement['opened'] = false;
                    $itemElement['read'] = $itemMessage->isUserRead(ModuleModel::$user_profile->getId());
                    $messageList[] = $itemElement;
                    */

                }
            }

            return [
                'success' => true,
                'data' => $itemArray,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
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
     * @return string
     */
    public function getFollowerUuids()
    {
        return parent::getFollowerUuids(); // TODO: Change the autogenerated stub
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
     *
     */
    public function getCommunicationTopicFollowerProfiles()
    {
        $topicFollowerRelation = $this->getCommunicationTopicFollower();
        $followers = [];
        foreach ($topicFollowerRelation as $relationItem) {
            $followers[] = $relationItem->getUserProfile(['columns' => 'id, uuid, firstname, lastname, workemail']);
        }
        return $followers;
    }

    /**
     * @return array
     */
    public function setReadItem()
    {

        $readTopicManager = OldCommunicationTopicRead::findTopicUser($this->getId(), ModuleModel::$user_profile->getId());
        if (!$readTopicManager) {
            $readTopicManager = new OldCommunicationTopicRead();
            $readTopicManagerRest = $readTopicManager->__create([
                'topic_id' => $this->getId(),
                'main_topic_id' => $this->getMainTopicId(),
                'user_profile_id' => ModuleModel::$user_profile->getId(),
            ]);
            if ($readTopicManagerRest['success'] == true) {
                $return = ['success' => true];
            } else {
                $return = ['success' => false];
            }
        } else {
            $return = ['success' => true];
        }

        return $return;
    }

    /**
     * @return bool
     */
    public function setReadMain()
    {
        //@TODO maintopic and childtopic shouldbe READ

        $db = $this->getWriteConnection();
        $db->begin();
        $return = $this->setReadItem();
        if ($return['success'] == false) {
            $db->rollback();
            return $return;
        }
        $items = $this->getCommunicationTopicItem([
            'columns' => 'id'
        ]);
        if (count($items) > 0) {
            foreach ($items as $item) {
                $readTopicManager = new OldCommunicationTopicRead();
                $readTopicManagerRest = $readTopicManager->__create([
                    'topic_id' => $item['id'],
                    'main_topic_id' => $this->getMainTopicId(),
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                ]);
                if ($readTopicManagerRest['success'] == false) {
                    $db->rollback();
                }
            }
        }
        $db->commit();
        return $return;
    }

    /**
     *
     */
    public function setReadMultipleChildItem()
    {

    }

    /**
     * @return bool
     */
    public function setUnread()
    {
        //@TODO maintopic and childtopic shouldbe UNREAD


        $db = $this->getWriteConnection();
        //$db->begin();

        try {
            $readsRest = $this->getCommunicationTopicRead([
                'conditions' => 'user_profile_id = :user_profile_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId()
                ]
            ])->delete();

        } catch (\PDOException $e) {
            //$db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            //$db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }


        try {
            $readsRest = $this->getMainCommunicationTopicRead([
                'conditions' => 'user_profile_id = :user_profile_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId()
                ]
            ])->delete();

        } catch (\PDOException $e) {
            //$db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            //$db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true];

        /*
        $readTopicManager = CommunicationTopicRead::findTopicUser($this->getId(), ModuleModel::$user_profile->getId());
        if ($readTopicManager) {
            $db = $this->getWriteConnection();
            $db->begin();
            $result = $readTopicManager->remove();

            if ($result['success'] == true) {
                $res = $this->setUnreadMultipleChildItem();
                if ($res['success'] == true) {
                    $db->commit();
                    return ['success' => true];
                } else {
                    $db->rollback();
                    return ['success' => false];
                }
            } else {
                return ['success' => false];
            }
        }else{
            $res = $this->setUnreadMultipleChildItem();
            if ($res['success'] == true) {
                return ['success' => true];
            } else {
                return ['success' => false];
            }
        }
        return ['success' => true];
        */
    }

    /**
     * @return array
     */
    public function setUnreadMultipleChildItem()
    {
        if ($this->isMainTopic() == true) {
            try {
                $readsRest = $this->getMainCommunicationTopicRead([
                    'conditions' => 'user_profile_id = :user_profile_id:',
                    'bind' => [
                        'user_profile_id' => ModuleModel::$user_profile->getId()
                    ]
                ])->delete();
                return ['success' => $readsRest];
            } catch (\PDOException $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        } else {
            return ['success' => true];
        }
    }

    /**
     * @param $user_profile_id
     */
    public function isUserRead($user_profile_id = 0)
    {
        if ($user_profile_id == 0) $user_profile_id = ModuleModel::$user_profile->getId();
        $readTopicManager = OldCommunicationTopicRead::findTopicUser($this->getId(), $user_profile_id);
        if ($readTopicManager) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $user_profile_id
     */
    public function isUserFlag($user_profile_id = 0)
    {
        if ($this->getIsFlagged() == true || $this->getIsFlagged() == self::IS_FLAGGED_YES) return true;
        else {
            if ($user_profile_id == 0) $user_profile_id = ModuleModel::$user_profile->getId();
            $flagTopic = OldCommunicationTopicFlag::findTopicUser($this->getId(), $user_profile_id);
            if ($flagTopic) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @param $user_profile_id
     */
    public function setFlag($user_profile_id = 0)
    {
        $topicFlag = OldCommunicationTopicFlag::findTopicUser($this->getId(), ModuleModel::$user_profile->getId());
        if (!$topicFlag) {
            $topicFlag = new OldCommunicationTopicFlag();
            $topicFlagResult = $topicFlag->__create([
                'topic_id' => $this->getId(),
                'user_profile_id' => ModuleModel::$user_profile->getId(),
            ]);

            if ($topicFlagResult instanceof OldCommunicationTopicFlag) {
                return ['success' => true];
            } else {
                return $topicFlagResult;
            }
        } else {
            return ['success' => true];
        }
    }

    /**
     * @return bool
     */
    public function setUnflag()
    {


        if ($this->getIsFlagged() == self::IS_FLAGGED_YES) {
            $this->setIsFlagged(self::IS_FLAGGED_NO);

            $db = $this->getWriteConnection();
            $db->begin();

            $res = $this->__quickUpdate();
            if ($res['success'] == false) {
                $db->rollback();
                return ['success' => false];
            }
        }


        $readTopicManager = OldCommunicationTopicFlag::findTopicUser($this->getId(), ModuleModel::$user_profile->getId());
        if ($readTopicManager) {
            $result = $readTopicManager->remove();
            if ($result['success'] == true) {
                if (isset($db)) $db->commit();
                return ['success' => true];
            } else {
                if (isset($db)) $db->rollback();
                return ['success' => false];
            }
        }
        return ['success' => true];
    }

    /**
     *
     */
    public function setUnreadAllFollowers()
    {
        //@todo clear all read followers
    }

    /**
     * @return int
     */
    public static function countTotalMessage()
    {
        return intval(self::countTotalThread() + self::countTotalReplies());
    }

    /**
     * @return int
     */
    public static function countTotalThread()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        $queryBuilder->columns(['number' => 'COUNT(CommunicationTopic.id)']);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower', 'CommunicationTopicFollower.communication_topic_id = CommunicationTopic.id', 'CommunicationTopicFollower');
        $queryBuilder->where(' CommunicationTopicFollower.user_profile_id = :user_profile_id:', [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ]);
        $queryBuilder->andwhere("CommunicationTopic.communication_topic_id IS NULL OR CommunicationTopic.communication_topic_id  = '' OR CommunicationTopic.communication_topic_id = 0", [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ]);

        $queryBuilder->andwhere('CommunicationTopic.company_id = :company_id:', [
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ]);
        $queryBuilder->orderBy("CommunicationTopic.created_at");
        $bindArray = [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ];


        $query = $di->get('modelsManager')->createQuery($queryBuilder->getPhql());
        $query->cache([
            'key' => 'COUNT_OLD_TOPIC_' . ModuleModel::$user_profile->getId(),
            'lifetime' => CacheHelper::__TIME_6_MONTHS,
        ]);
        $selectCount = $query->execute($bindArray);
        if ($selectCount) {
            return intval($selectCount->getFirst()->toArray()['number']);
        } else {
            return 0;
        }
    }

    /**
     * @return int
     */
    public static function countTotalReplies()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        $queryBuilder->columns(['number' => 'COUNT(CommunicationTopic.id)']);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic.communication_topic_id = CommunicationMainTopic.id', 'CommunicationMainTopic');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower', 'CommunicationTopicFollower.communication_topic_id = CommunicationMainTopic.id', 'CommunicationTopicFollower');
        $queryBuilder->where(' CommunicationTopicFollower.user_profile_id = :user_profile_id:', [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ]);
        $queryBuilder->andwhere('CommunicationTopic.communication_topic_id > 0');
        $queryBuilder->andwhere('CommunicationTopic.company_id = :company_id:', [
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ]);
        $queryBuilder->orderBy("CommunicationTopic.created_at");
        $bindArray = [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ];
        $selectCount = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
        if ($selectCount) {
            return intval($selectCount->getFirst()->toArray()['number']);
        } else {
            return 0;
        }
    }

    /**
     * @return mixed
     */
    public static function countReadMessage()
    {
        return intval(self::countReadThreads() + 0);
    }

    /**
     * @return int
     */
    public static function countReadThreads()
    {
        //TODO COUNT ALL MESSAGE AND CHILD ITEM
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        $queryBuilder->columns(['number' => 'COUNT(CommunicationTopic.id)']);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower', 'CommunicationTopicFollower.communication_topic_id = CommunicationTopic.id', 'CommunicationTopicFollower');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopicRead', 'CommunicationTopicRead.main_topic_id = CommunicationTopic.id', 'CommunicationTopicRead');

        $queryBuilder->where('CommunicationTopicFollower.user_profile_id = :user_profile_id:', [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ]);
        $queryBuilder->andwhere('CommunicationTopicRead.user_profile_id = :user_profile_id:', [
            'user_profile_id' => ModuleModel::$user_profile->getId()
        ]);
        $queryBuilder->andwhere("CommunicationTopic.communication_topic_id IS NULL OR CommunicationTopic.communication_topic_id  = '' OR CommunicationTopic.communication_topic_id = 0", [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ]);
        $queryBuilder->andwhere('CommunicationTopic.company_id = :company_id:', [
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ]);
        $queryBuilder->orderBy("CommunicationTopic.created_at");
        $bindArray = [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ];
        $selectCount = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
        if ($selectCount) {
            return intval($selectCount->getFirst()->toArray()['number']);
        } else {
            return 0;
        }
    }


    /**
     * @return int
     */
    public static function countReadReplies()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        $queryBuilder->columns(['number' => 'COUNT(CommunicationTopic.id)']);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic.communication_topic_id = CommunicationMainTopic.id', 'CommunicationMainTopic');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower', 'CommunicationTopicFollower.communication_topic_id = CommunicationMainTopic.id', 'CommunicationTopicFollower');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\OldCommunicationTopicRead', 'CommunicationTopicRead.topic_id = CommunicationTopic.id', 'CommunicationTopicRead');

        $queryBuilder->where(' CommunicationTopicFollower.user_profile_id = :user_profile_id:', [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ]);
        $queryBuilder->where(' CommunicationTopicRead.user_profile_id = :user_profile_id:', [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ]);
        $queryBuilder->andwhere('CommunicationTopic.communication_topic_id > 0');
        $queryBuilder->andwhere('CommunicationTopic.company_id = :company_id:', [
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ]);
        $queryBuilder->orderBy("CommunicationTopic.created_at");
        $bindArray = [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ];
        $selectCount = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
        if ($selectCount) {
            return intval($selectCount->getFirst()->toArray()['number']);
        } else {
            return 0;
        }
    }

    /**
     * @return int
     */
    public static function countUnreadMessage()
    {
        return self::countTotalMessage() - self::countReadMessage();
    }

    /**
     *
     */
    public static function findLastUpdate()
    {
        //todo check last update of topic
        return 0;
    }


    /**
     * @return int
     */
    public function countUnreadMessageItems()
    {
        return $this->countTotalMessageItems() - $this->countReadMessageItems();
    }


    /**
     * @return int
     */
    public function countTotalMessageItems()
    {
        $diNumber = 0;
        $cacheName = CacheHelper::getCacheNameCommunicationUserTotalItems($this->getUuid());


        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        $queryBuilder->columns(['number' => 'COUNT(*)']);
        $queryBuilder->distinct(true);
        $queryBuilder->where('CommunicationTopic.communication_topic_id = :communication_topic_id:');
        $queryBuilder->orderBy("CommunicationTopic.created_at");
        $bindArray = [
            'communication_topic_id' => $this->getId(),
        ];
        $selectCount = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
        if ($selectCount) {
            return $selectCount->getFirst()->toArray()['number'];
        } else {
            return 0;
        }
    }

    /**
     * @return mixed
     */
    public function countReadMessageItems()
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopic', 'CommunicationTopic');
        $queryBuilder->columns(['number' => 'COUNT(*)']);
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\OldCommunicationTopicFollower', 'CommunicationTopicFollower.communication_topic_id = CommunicationTopic.id', 'CommunicationTopicFollower');
        $queryBuilder->where('CommunicationTopic.sender_user_profile_id = :user_profile_id: OR CommunicationTopicFollower.user_profile_id = :user_profile_id:');
        $queryBuilder->andWhere('CommunicationTopic.communication_topic_id IS NULL');
        $queryBuilder->orderBy("CommunicationTopic.created_at");
        $bindArray = [
            'user_profile_id' => ModuleModel::$user_profile->getId(),
        ];
        $selectCount = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
        if ($selectCount) {
            return $selectCount->getFirst()->toArray()['number'];
        } else return 0;
    }


}
