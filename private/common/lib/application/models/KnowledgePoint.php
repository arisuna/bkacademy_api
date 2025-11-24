<?php

namespace SMXD\Application\Models;

class KnowledgePoint extends \Phalcon\Mvc\Model
{
    protected $id;
    protected $uuid;
    protected $code;
    protected $name;
    protected $level;
    protected $grade;
    protected $subject;
    protected $chapter_id;
    protected $topic_id;

    public function setId($id) { $this->id = $id; return $this; }
    public function setUuid($uuid) { $this->uuid = $uuid; return $this; }
    public function setCode($code) { $this->code = $code; return $this; }
    public function setName($name) { $this->name = $name; return $this; }
    public function setLevel($level) { $this->level = $level; return $this; }
    public function setGrade($grade) { $this->grade = $grade; return $this; }
    public function setSubject($subject) { $this->subject = $subject; return $this; }
    public function setChapterId($chapter_id) { $this->chapter_id = $chapter_id; return $this; }
    public function setTopicId($topic_id) { $this->topic_id = $topic_id; return $this; }

    public function getId() { return $this->id; }
    public function getUuid() { return $this->uuid; }
    public function getCode() { return $this->code; }
    public function getName() { return $this->name; }
    public function getLevel() { return $this->level; }
    public function getGrade() { return $this->grade; }
    public function getSubject() { return $this->subject; }
    public function getChapterId() { return $this->chapter_id; }
    public function getTopicId() { return $this->topic_id; }

    public function initialize()
    {
        $this->setSource("knowledge_point");

        // Relations
        $this->belongsTo('chapter_id', Chapter::class, 'id', [
            'alias' => 'Chapter'
        ]);
        $this->belongsTo('topic_id', Topic::class, 'id', [
            'alias' => 'Topic'
        ]);
    }

    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters) ?: null;
    }

    protected $source = 'knowledge_point';
}
