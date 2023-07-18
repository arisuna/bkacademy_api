<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Service;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ServiceTemplateController extends BaseController
{
    /**
     * @Route("/servicetemplate", paths={module="gms"}, methods={"GET"}, name="gms-servicetemplate-index")
     */
    public function getAction($id)
    {
        $this->checkAjaxGet();
        $return = ['success' => false, 'data' => null];
        if (Helpers::__isValidId($id)) {
            $serviceTemplate = Service::__findFirstByIdWithCache($id);
            if ($serviceTemplate) {
                $return = ['success' => true, 'data' => $serviceTemplate];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
