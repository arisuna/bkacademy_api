<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;

use Reloday\Gms\Models\ModuleModel;
use \Reloday\Gms\Models\TaskHistory;
use \Reloday\Gms\Models\Task;
use Aws;
/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TaskhistoryController extends BaseController
{
	/**
     * @Route("/taskhistory", paths={module="gms"}, methods={"GET"}, name="gms-taskhistory-index")
     */
    public function indexAction()
    {

    }

}
