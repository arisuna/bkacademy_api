<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\RelodayDynamoORM;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use \Reloday\Application\Lib\Helpers;
use Reloday\Gms\Help\HistoryHelper;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\ModuleModel;
use \Reloday\Gms\Models\TaskHistory;
use \Reloday\Gms\Models\HistoryOld;
use \Reloday\Gms\Models\Task;
use \Reloday\Gms\Models\Assignment;
use \Reloday\Gms\Models\Relocation;
use \Reloday\Gms\Models\HistoryAction;
use \Reloday\Gms\Models\RelocationServiceCompany;
use \Firebase\JWT\JWT;
use Phalcon\Security\Random;
use Aws;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class HistoryController extends BaseController
{
    /**
     * @Route("/history", paths={module="gms"}, methods={"GET"}, name="gms-history-index")
     */
    public function indexAction()
    {

    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public function listAction($object_uuid = "")
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $return = ['success' => true, 'message' => 'DATA_NOT_FOUND_TEXT', 'data' => []];
        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $return = History::__findWithFilter([
                'object_uuid' => $object_uuid,
                'limit' => 50,
                'isNotFilterCompany' => true,
            ]);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * save history to dynamodb
     */
    public function saveHistoryAction()
    {

        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);
        //$this->checkAcl('index', $this->router->getControllerName());
        $customData = Helpers::__getRequestValuesArray();
        $object_uuid = Helpers::__getRequestValue('uuid');

        if (!($object_uuid != '' && Helpers::__isValidUuid($object_uuid))) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'detail' => []];

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $historyObjectData = HistoryHelper::__getHistoryObject(ModuleModel::$language);

            $historyObject = new History();
            $historyObject->setData($historyObjectData);
            $historyObject->setIp( $this->request->getClientAddress());
            $historyObject->setCompanyUuid(ModuleModel::$company->getUuid());
            $historyObject->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
            $historyObject->setUuid(Helpers::__uuid());
            $historyObject->setObjectUuid($object_uuid);

            $return = $historyObject->__quickCreate();

            if ($return['success'] == true){
                $return['data'] = $historyObject->parseDataToArray();
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
