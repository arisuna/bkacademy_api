<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;


class OldCommunicationTopicItem extends \Reloday\Application\Models\OldCommunicationTopicItemExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 200;
    const PREFIX_FOLDER = "communications";

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('communication_topic_id', 'Reloday\Gms\Models\OldCommunicationTopic', 'id', [
            'alias' => 'CommunicationTopic'
        ]);
        $this->belongsTo('sender_user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'SenderUserProfile'
        ]);
        $this->hasMany('uuid', 'Reloday\Gms\Models\OldCommunicationTopicRead', 'topic_uuid', [
            'alias' => 'CommunicationTopicRead'
        ]);
    }


    /**
     * @param $options
     * @return array
     */
    public function findWithFilter($options)
    {

        if (!is_array($options) && count($options) > 0) return ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\OldCommunicationTopicItem', 'CommunicationTopicItem');
        $queryBuilder->distinct(true);

        if (isset($options['communication_topic_id']) && is_numeric($options['communication_topic_id']) && $options['communication_topic_id'] > 0)
            $queryBuilder->where('CommunicationTopicItem.communication_topic_id = :communication_topic_id:', [
                'communication_topic_id' => $options['communication_topic_id']
            ]);

        if (isset($options['last_topic_item_id']) && is_numeric($options['last_topic_item_id']) && $options['last_topic_item_id'] > 0)
            $queryBuilder->where('CommunicationTopicItem.id > :last_topic_item_id:', [
                'last_topic_item_id' => $options['last_topic_item_id']
            ]);

        $queryBuilder->orderBy("CommunicationTopicItem.created_at DESC");

        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => self::LIMIT_PER_PAGE,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            return [
                'success' => true,
                'data' => $pagination->items,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
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
     * @return mixed
     */
    public function parseCc()
    {
        if ($this->getCc() != '' && Helpers::__isJsonValid($this->getCc())) {
            return json_decode($this->getCc(), true);
        }
    }

    /**
     *
     */
    public function belongsToGms()
    {
        if ($this->getCommunicationTopic() && $this->getCommunicationTopic()->belongsToGms()) {
            return true;
        }
        return false;
    }

    /**
     * @return array|mixed
     */
    public function getContentFromS3()
    {
        $fileName = self::PREFIX_FOLDER . "/" . $this->getUuid() . ".json";
        $bodyResult = RelodayS3Helper::__getBodyObject($fileName);

        if ($bodyResult['success'] == true) {
            if (Helpers::__isJsonValid($bodyResult['data'])) {
                $bodyArray = json_decode($bodyResult['data'], true);
                if ($bodyArray['content']) return $bodyArray['content'];
            }
        }
        return null;

    }

    /**
     * @return string
     */
    public function getFilenameInS3()
    {
        $fileName = self::PREFIX_FOLDER . "/" . $this->getUuid() . ".json";
        return $fileName;
    }

    /**
     * @return array|mixed
     */
    public function uploadContentToS3()
    {
        $fileName = $this->getFilenameInS3();
        $resultUpload = RelodayS3Helper::__uploadSingleFile($fileName, json_encode([
            'subject' => $this->getSubject(),
            'content' => $this->getAddedContent()
        ]));
        return $resultUpload;
    }


    /**
     *
     */
    public function convertToArray()
    {
        $itemElement = array();

        $itemElement['to'] = [
            'email' => $this->getRecipientEmail(),
            'name' => $this->getRecipientName(),
        ];
        $itemElement['from'] = [
            'email' => $this->getFromEmail(),
            'name' => $this->getFromName()
        ];
        $itemElement['cc'] = $this->parseCc();
        $itemElement['deleted'] = false;
        $itemElement['opened'] = false;
        $itemElement['read'] = false;

        return $itemElement;
    }

    /**
     *
     */
    public function getSenderEmail()
    {
        return $this->getCommunicationTopic()->getSenderEmail();
    }

    /**
     *
     */
    public function getSenderName()
    {
        return $this->getSenderUserProfile()->getFullName();
    }

    /**
     * @return mixed
     */
    public function getReplyToEmail()
    {
        return $this->getCommunicationTopic()->getReplyToEmail();
    }

    /**
     * getListRead
     */
    public function getUserProfileIdsReadList()
    {
        $return = [];
        $readList = $this->getCommunicationTopicRead();
        foreach ($readList as $readItem) {
            $return[] = $readItem->getUserProfileId();
        }
    }

    /**
     * @param $user_profile_id
     */
    public function isUserRead($user_profile_id = 0)
    {
        if ($user_profile_id = 0) $user_profile_id = ModuleModel::$user_profile->getId();
        $readTopicManager = OldCommunicationTopicRead::findTopicUser($this->getUuid(), $user_profile_id);
        if ($readTopicManager) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function setRead()
    {
        $readTopicManager = OldCommunicationTopicRead::findTopicUser($this->getUuid(), ModuleModel::$user_profile->getId());
        if (!$readTopicManager) {
            $readTopicManager = new OldCommunicationTopicRead();
            $readTopicManager = $readTopicManager->__create([
                'topic_uuid' => $this->getUuid(),
                'user_profile_id' => ModuleModel::$user_profile->getId(),
            ]);
            if ($readTopicManager instanceof OldCommunicationTopicRead) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function setUnread()
    {
        $readTopicManager = OldCommunicationTopicRead::findTopicUser($this->getUuid(), ModuleModel::$user_profile->getId());
        if ($readTopicManager) {
            $result = $readTopicManager->remove();
            if ($result['success'] == true) return true;
            else return false;
        }
        return true;
    }

    /**
     *
     */
    public function afterCreate()
    {
        return $this->getCommunicationTopic()->setUnreadAllFollowers();
    }
}
