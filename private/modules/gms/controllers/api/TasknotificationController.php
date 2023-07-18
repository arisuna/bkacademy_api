<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TasknotificationController extends ModuleApiController
{
	/**
     * @Route("/tasknotification", paths={module="gms"}, methods={"GET"}, name="gms-tasknotification-index")
     */
    public function indexAction()
    {

    }
}
