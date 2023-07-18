<?php

namespace Reloday\Api\Controllers\API;

use \Reloday\Api\Controllers\ModuleApiController;
use Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Api module controller
 *
 * @RoutePrefix("/api/api")
 */
class IndexController extends ModuleApiController
{
	/**
     * @Route("/index", paths={module="api"}, methods={"GET"}, name="api-index-index")
     */
    public function indexAction()
    {

    }

    /**
     * @return mixed
     */
    public function errorAction(){
        $this->view->disable();
        $this->response->setJsonContent(['success' => false, 'errorType' => 'executionError']);
        return $this->response->send();
    }
}
