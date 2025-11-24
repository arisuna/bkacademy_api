<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;
use SMXD\Application\Models\Topic;
use SMXD\Application\Lib\CacheHelper;

class TopicExt extends Topic
{
    use ModelTraits;

    const STATUS_ARCHIVED = -1;
    const STATUS_ACTIVE = 1;
    const STATUS_DRAFT = 0;

    public function initialize()
    {
        parent::initialize();

        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');

        // Timestamp
        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable([
            'beforeValidationOnCreate' => [
                'field' => ['created_at', 'updated_at'],
                'format' => 'Y-m-d H:i:s'
            ],
            'beforeValidationOnUpdate' => [
                'field' => 'updated_at',
                'format' => 'Y-m-d H:i:s'
            ]
        ]));

        // Relations
        $this->belongsTo('chapter_id', ChapterExt::class, 'id', [
            'alias' => 'Chapter'
        ]);

        $this->hasMany('id', KnowledgePointExt::class, 'topic_id', [
            'alias' => 'KnowledgePoints',
            'params' => ['order' => 'code ASC']
        ]);
    }

    public function setData($custom = [])
    {
        ModelHelper::__setData($this, $custom);
        /****** YOUR CODE HERE ******/
        /****** END YOUR CODE ******/
    }
}
