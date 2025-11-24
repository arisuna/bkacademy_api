<?php

namespace SMXD\Application\Models;

class Chapter extends \Phalcon\Mvc\Model
{
    protected $id;
    protected $uuid;
    protected $code;
    protected $name;
    protected $type;
    protected $grade;
    protected $subject;

    public function setId($id) { $this->id = $id; return $this; }
    public function setUuid($uuid) { $this->uuid = $uuid; return $this; }
    public function setCode($code) { $this->code = $code; return $this; }
    public function setName($name) { $this->name = $name; return $this; }
    public function setType($type) { $this->type = $type; return $this; }
    public function setGrade($grade) { $this->grade = $grade; return $this; }
    public function setSubject($subject) { $this->subject = $subject; return $this; }

    public function getId() { return $this->id; }
    public function getUuid() { return $this->uuid; }
    public function getCode() { return $this->code; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getGrade() { return $this->grade; }
    public function getSubject() { return $this->subject; }

    public function initialize()
    {
        $this->setSource("chapter");
    }

    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    public static function findFirst($parameters = null): ?\Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters) ?: null;
    }

    protected $source = 'chapter';
}
