<?php


namespace Reloday\Gms\Models;

use Phalcon\Acl;
use Phalcon\Acl\Adapter\Memory;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Security;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\Regex as RegexValidator;

use Reloday\Application\Validation\UserPasswordValidation;
use Reloday\Gms\Models\UserGroupAcl;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\Acl as UserAcl;
use Reloday\Gms\Models\UserGroupAclCompany;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserLoginToken;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers as Helpers;


class UserAuthorKey extends \Reloday\Application\Models\UserAuthorKeyExt
{
    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'UserProfile'
        ]);
    }

}
