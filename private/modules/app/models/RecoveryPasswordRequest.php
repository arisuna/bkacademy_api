<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;

class RecoveryPasswordRequest extends \SMXD\Application\Models\RecoveryPasswordRequestExt {
	/**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('user_login_id', 'SMXD\App\Models\UserLogin', 'id', ['alias' => 'UserLogin']);
    }
}