<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class Service extends \Reloday\Application\Models\ServiceExt
{
    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', "Reloday\Gms\Models\ServiceTab", "service_id", [
            'alias' => 'Tabs',
            'params' => [
                'order' => 'position ASC'
            ]]);
    }

}