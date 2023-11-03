<?php

namespace SMXD\Api\Controllers\API;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\BusinessOrder;
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
        $this->checkAjaxGet();

        $profile_array = ModuleModel::$user->getParsedArray();

        $result = [
            'success' => true,
            'profile' => $profile_array
        ];

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    public function getListOrdersAction(){
        $this->view->disable();
        $this->checkAjaxPutGet();

        $profile = ModuleModel::$user;

        $businessOrders = BusinessOrder::find([
            'conditions' => 'creator_end_user_id = :creator_end_user_id:',
            'bind' => [
                'creator_end_user_id' => $profile->getId()
            ]
        ]);

        $data = [];
        foreach ($businessOrders as $businessOrder){
            $data[] = $businessOrder->parsedDataToArray();
        }

        $result = [
            'success' => true,
            'data' => $data
        ];

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
