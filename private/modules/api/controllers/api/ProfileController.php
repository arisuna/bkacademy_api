<?php

namespace SMXD\Api\controllers\API;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\User;
use SMXD\Api\Models\ModuleModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/api")
 */

class ProfileController extends BaseController
{
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('index');

        $profile_array = ModuleModel::$user->getParsedArray();

        $result = [
            'success' => true,
            'profile' => $profile_array
        ];

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
