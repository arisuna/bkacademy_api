<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ServicenotificationController extends ModuleApiController
{
	/**
     * @Route("/servicenotification", paths={module="gms"}, methods={"GET"}, name="gms-servicenotification-index")
     */
    public function indexAction()
    {

    }
}
