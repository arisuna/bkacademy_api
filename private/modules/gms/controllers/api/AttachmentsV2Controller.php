<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AttachmentsV2Controller extends ModuleApiController
{
	/**
     * @Route("/attachmentsv2", paths={module="gms"}, methods={"GET"}, name="gms-attachmentsv2-index")
     */
    public function indexAction()
    {

    }
}
