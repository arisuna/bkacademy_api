<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Models\ApplicationModel;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectMap;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ObjectMappingController extends BaseController
{
    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $custom_data = Helpers::__getRequestValuesArray();
        $table = Helpers::__getRequestValue('table');

        $objectData = [
            'uuid' => ApplicationModel::uuid(),
            'object_type' => $table,
            'creator_user_profile_uuid' => ModuleModel::$user_profile->getUuid()
        ];
        $resultCreate = RelodayObjectMapHelper::__createObject($objectData['uuid'], $table, false, $objectData);
        if ($resultCreate['success'] == true) {
            $return = [
                'success' => true,
                'data' => $objectData,
                'message' => 'CREATE_OBJECT_SUCCESS_TEXT'
            ];
        } else {
            $return = $resultCreate;
            $return['message'] = 'CREATE_OBJECT_FAIL_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $objectData = RelodayObjectMapHelper::__getObjectWithCache($uuid);
            if ($objectData) {
                $return = ['success' => true, 'data' => $objectData];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createNewUuidAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->response->setJsonContent(['success' => true, 'data' => Helpers::__uuid()]);
        return $this->response->send();
    }
}
