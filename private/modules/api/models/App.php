<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Api\Models;

class App extends \SMXD\Application\Models\AppExt {


    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->hasOne('id', 'SMXD\Api\Models\Company', 'app_id', [
            'alias' => 'Company'
        ]);
    }
}