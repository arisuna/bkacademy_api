<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ExtractDataAssignmentController extends ModuleApiController
{
	/**
     * @Route("/extractdataassignment", paths={module="gms"}, methods={"GET"}, name="gms-extractdataassignment-index")
     */
    public function indexAction()
    {

    }
}
