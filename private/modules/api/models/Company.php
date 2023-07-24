<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Api\Models;

class Company extends \SMXD\Application\Models\CompanyExt
{

    public function initialize(){
        parent::initialize();
        $this->belongsTo('app_id', 'SMXD\Api\Models\App', 'id', ['alias' => 'App']);
    }
}