<?php

namespace SMXD\api\controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\User;
use SMXD\Api\Models\ModuleModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/api")
 */

class ProfileController extends ModuleApiController
{
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('index');

        $user = User::findFirst([
            'conditions' => 'email = :email: and status <> :deleted:',
            'bind' => [
                'email' => 'admin@smxdtest.com',
                'deleted' => User::STATUS_DELETED
            ]
        ]);
        $profile_array = $user->getParsedArray();

        $result = [
            'success' => true,
            'profile' => $profile_array
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
