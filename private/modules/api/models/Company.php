<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Api\Models;

class Company extends \Reloday\Application\Models\CompanyExt
{

    public function initialize(){
        parent::initialize();
        $this->belongsTo('app_id', 'Reloday\Api\Models\App', 'id', ['alias' => 'App']);
    }
}