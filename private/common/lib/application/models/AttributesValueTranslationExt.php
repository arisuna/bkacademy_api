<?php

namespace SMXD\Application\Models;

use SMXD\Application\Behavior\AttributesValueTranslationCacheBehavior;

class AttributesValueTranslationExt extends AttributesValueTranslation
{
	public function initialize() {

		parent::initialize();

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

        $this->addBehavior(
            new AttributesValueTranslationCacheBehavior()
        );
    }
}
