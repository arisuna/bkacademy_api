<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */
namespace Reloday\Gms\Models;

class UserRequestToken extends \Reloday\Application\Models\UserRequestTokenExt {

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize(){
        parent::initialize();
        $this->belongsTo('user_login_id', 'Reloday\Gms\Models\UserLogin', 'id', [
            'alias' => 'UserLogin',
            'reusable' => true,
        ]);
    }

}