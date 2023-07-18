<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\App\Models;

class RecoveryPasswordRequest extends \Reloday\Application\Models\RecoveryPasswordRequestExt {
	/**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->belongsTo('user_login_id', 'Reloday\App\Models\UserLogin', 'id', ['alias' => 'UserLogin']);
    }
}