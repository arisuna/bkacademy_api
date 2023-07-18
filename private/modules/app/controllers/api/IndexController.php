<?php

namespace Reloday\App\Controllers\API;

use Phalcon\Acl\Adapter\Memory;
use Phalcon\Acl\Resource;
use Phalcon\Acl\Role;
use \Reloday\App\Controllers\ModuleApiController;
use \Reloday\App\Models\App as App;
use Reloday\Application\Lib\RelodayUrlHelper;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class IndexController extends ModuleApiController
{
    /**
     * @Route("/index", paths={module="gms"}, methods={"GET"}, name="gms-index-index")
     */

    protected $app_script;

    /**
     *
     */
    public function initialize()
    {

    }

    public function indexAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT']);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getEnvAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => true, 'message' => 'ZONE_EU_DETECTED']);
        return $this->response->send();
    }
}
