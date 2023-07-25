<?php

namespace SMXD\Application\Models;

use SMXD\Application\Behavior\AttributesValueCacheBehavior;
use SMXD\Application\Lib\ModelHelper;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Traits\ModelTraits;


class AttributesValueExt extends AttributesValue
{
    use ModelTraits;

    const STATUS_ARCHIVED_YES = 1;
    const STATUS_ARCHIVED_NO = 0;

    const STANDARD_YES = 1;
    const STANDARD_NO = 0;
    /**
     *
     */
	public function initialize() {

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

        $this->hasMany('id', '\SMXD\Application\Models\AttributesValueTranslationExt', 'attributes_value_id', [
            'alias' => 'Translation'
        ]);

        $this->hasMany('id', '\SMXD\Application\Models\AttributesValueTranslationExt', 'attributes_value_id', [
            'alias' => 'AttributesValueTranslationItems'
        ]);

        $this->addBehavior(new SoftDelete([
            'field' => 'archived',
            'value' => self::STATUS_ARCHIVED_YES
        ]));

        $this->addBehavior(
            new AttributesValueCacheBehavior()
        );
    }

    /**
     * @return string
     */
    public function getCode(){
	    return $this->getAttributesId()."_".$this->getId();
    }


    /**
     * @return array
     */
    public function __quickUpdate(){
        return ModelHelper::__quickUpdate( $this );
    }

    /**
     * @return array
     */
    public function __quickCreate(){
        return ModelHelper::__quickCreate( $this );
    }

    /**
     * @return array
     */
    public function __quickSave(){
        return ModelHelper::__quickSave( $this );
    }

    /**
     * @return array
     */
    public function __quickRemove(){
        return ModelHelper::__quickRemove( $this );
    }

}
