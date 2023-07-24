<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\App;
use SMXD\App\Models\Company;
use SMXD\App\Models\UserLogin;
use SMXD\App\Models\UserLoginToken;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class UserController extends BaseController
{
    /**
     * @Route("/user", paths={module="app"}, methods={"GET"}, name="app-user-index")
     */
    public function indexAction()
    {

    }
}
