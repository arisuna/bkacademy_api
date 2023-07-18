<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */
namespace Reloday\Gms\Models;

class UserLoginToken extends \Reloday\Application\Models\UserLoginTokenExt {

    public function initialize()
    {
    	parent::initialize();
        $this->belongsTo('user_login_id', '\Reloday\Gms\Models\UserLogin', 'id', array('alias' => 'login'));
    }
}