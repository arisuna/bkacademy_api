<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class AttributesCompanyValue extends \Reloday\Application\Models\AttributesCompanyValueExt
{

    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', '\Reloday\Gms\Models\AttributesCompanyValueTranslation', 'attributes_company_value_id', array('alias' => 'translations'));
        $this->belongsTo('attributes_template_id', '\Reloday\Gms\Models\AttributesTemplateValue', 'id', array('alias' => 'origin'));
    }

    public static function __create($attribute)
    {

        $model = new self();
        $model->setAttributesTemplateId($attribute['id']);
        $model->setCompanyId($attribute['company_id']);
        $model->setValue($attribute['value']);
        $model->save();
        return $model;
    }
}