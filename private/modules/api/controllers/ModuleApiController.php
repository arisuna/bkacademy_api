<?php

namespace SMXD\Api\Controllers;

use SMXD\Application\Controllers\ApplicationApiController;

/**
 * Base class of Api module API controller
 */
class ModuleApiController extends ApplicationApiController
{
    public function initialize()
    {
        //$this->view->disable();
    }

    /**
     * @param Dispatcher $dispatcher
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkPrelightRequest();
        if ($this->request->getHttpHost() != getenv('API_DOMAIN')) {
            die('404');
        }
    }
}