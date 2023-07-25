<?php

namespace SMXD\App\Models;

class AttributesValue extends \SMXD\Application\Models\AttributesValueExt
{

	const  ATTRIBUTE_VALUE_STANDARD = 1;
	const  ATTRIBUTE_VALUE_NOT_STANDARD = 0;
	const  ATTRIBUTE_VALUE_ARCHIVED = 1;
	const  ATTRIBUTE_VALUE_NOT_ARCHIVED = 0;
	
	const GENDER_MALE = 'Mr.';
	const GENDER_FEMALE_1 = 'Ms.';
	const GENDER_FEMALE_2 = 'Mrs.';

    /**
     *
     */
	public function initialize(){
		parent::initialize();
		$this->hasMany('id', '\SMXD\App\Models\AttributesValueTranslation', 'attributes_value_id', array('alias' => 'translation'));
	}
}
