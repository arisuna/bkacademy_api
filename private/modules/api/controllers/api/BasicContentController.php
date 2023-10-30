<?php

namespace SMXD\Api\Controllers\API;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\BasicContent;
use SMXD\Application\Lib\Helpers;

class BasicContentController extends ModuleApiController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        if (Helpers::__isValidUuid($uuid)) {
            $data = BasicContent::findFirstByUuid($uuid);

        } else {
            $data = BasicContent::findFirstById($uuid);

        }

        $result = [
            'success' => true,
            'data' => $data
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}