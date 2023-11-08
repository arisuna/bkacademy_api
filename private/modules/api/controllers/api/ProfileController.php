<?php

namespace SMXD\Api\Controllers\API;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\BusinessOrder;
use SMXD\Api\Models\User;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

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


    public function getListOrdersAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $profile = ModuleModel::$user;

        $businessOrders = BusinessOrder::find([
            'conditions' => 'creator_end_user_id = :creator_end_user_id:',
            'bind' => [
                'creator_end_user_id' => $profile->getId()
            ],
            'order' => 'created_at desc'
        ]);

        $data = [];
        foreach ($businessOrders as $businessOrder) {
            $data[] = $businessOrder->parsedDataToArray();
        }

        $result = [
            'success' => true,
            'data' => $data
        ];

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'PROFILE_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user_uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid'] : "";
        $firstname = isset($dataInput['firstname']) && $dataInput['firstname'] != '' ? $dataInput['firstname'] : "";
        $lastname = isset($dataInput['lastname']) && $dataInput['lastname'] != '' ? $dataInput['lastname'] : "";
        $email = isset($dataInput['email']) && $dataInput['email'] != '' ? $dataInput['email'] : "";
        $birthdate = isset($dataInput['birthdate']) && $dataInput['birthdate'] != '' ? $dataInput['birthdate'] : "";

        if ($user_uuid != '' && $firstname && $lastname) {
            $user = ModuleModel::$user;
            if ($user->getUuid() == $user_uuid) {
                $user->setBirthdate($birthdate);
                $user->setEmail($email);
                $user->setFirstname($firstname);
                $user->setLastname($lastname);

                $modelResult = $user->__quickUpdate();

                if ($modelResult instanceof User) {
                    $result = [
                        'success' => true,
                        'message' => 'USER_PROFILE_SAVE_SUCCESS_TEXT',
                        'data' => $modelResult,
                    ];
                }
            }
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
