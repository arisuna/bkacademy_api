<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class AttributesCompanyValueTranslation extends \Reloday\Application\Models\AttributesCompanyValueTranslationExt {

	public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', '\Reloday\Gms\Models\AttributesCompanyValueTranslation', 'attributes_company_value_id', array('alias' => 'translations'));
        $this->belongsTo('attributes_company_value_id', '\Reloday\Gms\Models\AttributesCompanyValue', 'id', array('alias' => 'default'));
    }

}