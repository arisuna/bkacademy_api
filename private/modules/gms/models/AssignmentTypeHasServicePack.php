<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class AssignmentTypeHasServicePack extends \Reloday\Application\Models\AssignmentTypeHasServicePackExt {

	public function initialize()
    {
    	parent::initialize();
        $this->belongsTo('service_pack_id', '\Reloday\Gms\Models\ServicePack', 'id', array('alias' => 'service_packs'));
        $this->belongsTo('assignment_type_id', '\Reloday\Gms\Models\AssignmentType', 'id' , array('alias' => 'assignment_type') );
    }

}