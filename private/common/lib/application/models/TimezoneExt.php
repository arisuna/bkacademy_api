<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class TimezoneExt extends Timezone
{
    use ModelTraits;
    /** status archived */
    const STATUS_ARCHIVED = -1;
    /** status active */
    const STATUS_ACTIVE = 1;
    /** status draft */
    const STATUS_DRAFT = 0;

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable(
            array(
                'beforeValidationOnCreate' => array(
                    'field' => array(
                        'created_at', 'updated_at'
                    ),
                    'format' => 'Y-m-d H:i:s'
                ),
                'beforeValidationOnUpdate' => array(
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                )
            )
        ));

        $this->addBehavior(new SoftDelete([
            'field' => 'status',
            'value' => self::STATUS_ARCHIVED
        ]));
    }


    /**
     * @return array
     */
    public function __quickUpdate()
    {
        return ModelHelper::__quickUpdate($this);
    }

    /**
     * @return array
     */
    public function __quickCreate()
    {
        return ModelHelper::__quickCreate($this);
    }

    /**
     * @return array
     */
    public function __quickSave()
    {
        return ModelHelper::__quickSave($this);
    }

    /**
     * @return array
     */
    public function __quickRemove()
    {
        return ModelHelper::__quickRemove($this);
    }

    /**
     * @return mixed
     */
    public function toArray($columns = NULL): array
    {
        $array = parent::toArray($columns);
        $metadata = $this->getDI()->get('modelsMetadata');
        $types = $metadata->getDataTypes($this);
        foreach ($types as $attribute => $type) {
            $array[$attribute] = ModelHelper::__getAttributeValue($type, $array[$attribute]);
        }
        return $array;
    }

}
