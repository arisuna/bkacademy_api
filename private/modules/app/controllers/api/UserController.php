<?php

namespace Reloday\App\Controllers\API;

use Phalcon\Config;
use Reloday\App\Models\Acl;
use Reloday\App\Models\App;
use Reloday\App\Models\Company;
use Reloday\App\Models\UserLogin;
use Reloday\App\Models\UserLoginToken;

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
