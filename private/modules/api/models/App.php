<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Api\Models;

class App extends \Reloday\Application\Models\AppExt {


    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->hasOne('id', 'Reloday\Api\Models\Company', 'app_id', [
            'alias' => 'Company'
        ]);
    }
}