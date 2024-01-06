<?php

namespace SMXD\App\Controllers\API;

use SMXD\Application\Lib\SMXDDynamoORMException;
use Phalcon\Http\Client\Exception;
use Phalcon\Test\Mvc\Model\Behavior\Helper;
use SMXD\Application\Lib\DynamoHelper;
use \SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HistoryModel;
use SMXD\Application\Lib\PushHelper;
use SMXD\Application\Lib\SMXDDynamoORM;
use SMXD\Application\Lib\RelodayObjectMapHelper;
use \SMXD\App\Controllers\ModuleApiController;
use \SMXD\App\Controllers\API\BaseController;
use Aws\Exception\AwsException;
use SMXD\App\Help\HistoryHelper;
use SMXD\App\Models\DataUserMember;
use SMXD\App\Models\Employee;
use SMXD\App\Models\History;
use \SMXD\App\Models\ModuleModel;
use \SMXD\App\Models\HistoryOld;
use \SMXD\App\Models\HistoryNotification;
use \SMXD\App\Models\ObjectMap;

use \SMXD\Application\Lib\JWTEncodedHelper;
use \Phalcon\Security\Random;
use \SMXD\App\Models\Task;
use \SMXD\App\Models\Assignment;
use \SMXD\App\Models\Relocation;
use \SMXD\App\Models\HistoryAction;
use \SMXD\App\Models\RelocationServiceCompany;
use \Firebase\JWT\JWT;
use Aws;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\Exception\DynamoDbException;
use SMXD\Application\Lib\ConstantHelper;
use SMXD\App\Models\User;
use SMXD\App\Models\UserReadNotification;
use SMXD\App\Module;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class NotificationController extends BaseController
{
    /**
     * init
     */
    public function initialize()
    {
        $this->view->disable();
    }

    /**
     * save notification to SQS
     * @return mixed
     */
    public function addNotificationAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $data = Helpers::__getRequestValuesArray();
        $object_uuid = Helpers::__getRequestValue('uuid');
        $file_name = Helpers::__getRequestValue('file_name');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'rawData' => $data];
        if (!($object_uuid != '' && Helpers::__isValidUuid($object_uuid))) {
            goto end_of_function;
        }
        $data['creator_user_uuid'] = ModuleModel::$user->getUuid();
        $data['creator_company_id'] = ModuleModel::$company->getId();
        if(isset($file_name) && $file_name){
            $data['file_name'] = $file_name;
        }
        $dataParams = [
            'sender_name' => 'Notification',
            'root_company_id' => ModuleModel::$company->getId(),
            'language' => ModuleModel::$system_language,
            'params' => $data,
        ];
        $resultQueue = $queueSendMail->addQueue($dataParams);
        $return = ['success' => true, '$resultQueue' => $resultQueue];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * save notification to SQS
     * @return mixed
     */
    public function addNotificationToAccountAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $data = Helpers::__getRequestValuesArray();
        $object_uuid = Helpers::__getRequestValue('uuid');
        $target_company_id = Helpers::__getRequestValue('target_company_id');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'rawData' => $data];
        if (!($object_uuid != '' && Helpers::__isValidUuid($object_uuid))) {
            goto end_of_function;
        }
        $data['creator_user_uuid'] = ModuleModel::$user->getUuid();
        $data['creator_company_id'] = ModuleModel::$company->getId();
        $dataParams = [
            'sender_name' => 'Notification',
            'root_company_id' => ModuleModel::$company->getId(),
            'target_company_id' => $target_company_id,
            'language' => ModuleModel::$system_language,
            'params' => $data,
        ];
        $return = ['success' => true];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * send notification
     * save notification to SQS
     * @return mixed
     */
    public function addNotificationFullAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $data = Helpers::__getRequestValues();
        $object_uuid = isset($data->uuid) && $data->uuid != '' ? $data->uuid : null;
        $action = isset($data->action) && $data->action != '' ? $data->action : "HISTORY_UPDATE";
        $type = isset($data->type) && $data->type != '' ? $data->type : HistoryModel::TYPE_OTHERS;
        $taskInput = Helpers::__getRequestValue('task');
        $target_user_uuid = Helpers::__getRequestValue('target_user_uuid');
        $target_user = Helpers::__getRequestValue('target_user');
        $comment = Helpers::__getRequestValue('comment');


        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $historyObjectData = [];
        if (!($object_uuid != '' && Helpers::__isValidUuid($object_uuid))) {
            goto end_of_function;
        }

        if ($object_uuid != '') {
            $type = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
        }

        $members = [];
        $members = DataUserMember::__getAllMembersOfObjectWithRole($object_uuid);
        $historyObjectData = HistoryOld::__getHistoryObject($this->language_key, false, $data);
        $historyActionObject = HistoryOld::$historyAction;
        if (!$historyActionObject || $historyActionObject == null) {
            $return = ['success' => false, 'message' => 'ACTION_NOT_FOUND_TEXT'];
            goto end_of_function;
        }


        /** @var add currentUser to membersList $currentUser */
        if (!isset($members[ModuleModel::$user->getUuid()])) {
            $currentUser = ModuleModel::$user->toArray();
            $currentUser['principal_email'] = ModuleModel::$user->getPrincipalEmail();
            $currentUser['is_current_user'] = true;
            $currentUser['is_owner'] = false;
            $currentUser['is_reporter'] = false;
            $currentUser['is_viewer'] = false;
            $currentUser['is_active'] = ModuleModel::$user->isActive();
            $members[ModuleModel::$user->getUuid()] = $currentUser;
        }


        if ((is_object($members) && $members->count()) || (is_array($members) && count($members) > 0)) {
            foreach ($members as $member) {
                $member = (array)$member;
                if (!isset($member['is_active']) || $member['is_active'] == false) {
                    continue;
                }
                if ($member['is_current_user'] == true && $historyActionObject->canNotifyCurrentUser() == true) {
                    //nothing to say
                    $member['hasNotificationEmail'] = true;
                } elseif ($member['is_owner'] == true && $historyActionObject->canNotifyOwner() == true) {
                    //nothing to say
                    $member['hasNotificationEmail'] = true;
                } elseif ($member['is_reporter'] == true && $historyActionObject->canNotifyReporter() == true) {
                    //nothing to say
                    $member['hasNotificationEmail'] = true;
                } elseif ($member['is_viewer'] == true && $historyActionObject->canNotifyViewer() == true) {
                    //nothing to say
                    $member['hasNotificationEmail'] = true;
                } else {
                    $member['hasNotificationEmail'] = false;
                    continue;
                }

                //check Member Is HR or DSP Member


                $dataParams = [
                    'to' => $member['principal_email'],
                    'sender_name' => 'Notification',
                    'language' => ModuleModel::$system_language,
                    'templateName' => $historyObjectData['templateName'],
                    'params' => $historyObjectData,
                ];

                //@TODO recheck
                    $return['success'] = true;
                    $return['message'] = 'ADD_NOTIFICATION_SUCCESS_TEXT';
                    $return['hasResultQueueSendMail'] = true;
            }

            $return['countMembers'] = count($members);
            $return['$historyActionObject'] = $historyActionObject->getMyCompanyNotificationSetting();
            $return['message'] = 'ADD_NOTIFICATION_SUCCESS_TEXT';
            $return['success'] = true;
        }


        end_of_function:
        $return['lastDataParams'] = isset($dataParams) ? $dataParams : null;
        $return['size'] = isset($historyObjectData) && strlen(serialize($historyObjectData)) * 8 / 1000;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save notification in the Notification Box
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Phalcon\Security\Exception
     */
    public function addNotificationForUserAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $object_uuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'detail' => []];

        if (!$object_uuid || Helpers::__isValidUuid($object_uuid) == false) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;

        }

        if ($object_uuid != '') {
            $type = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
        }

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $historyObjectData = HistoryHelper::__getHistoryObject(ModuleModel::$language);
            SMXDDynamoORM::__init();
            $viewers = DataUserMember::getMembersUuids($object_uuid);
            //comments : exception (action started by current user
            if (!isset($viewers[ModuleModel::$user->getUuid()])) {
                $viewers[ModuleModel::$user->getUuid()] = ModuleModel::$user->getUuid();
            }
            if (count($viewers)) {
                foreach ($viewers as $user_uuid) {
                    $profile = User::findFirstByUuid($user_uuid);
                    if ($profile) {

                        $modelHistoryNotification = new HistoryNotification();
                        $modelHistoryNotification->setData($historyObjectData);
                        $modelHistoryNotification->setUuid(Helpers::__uuid());
                        $modelHistoryNotification->setUserUuid($user_uuid);
                        $modelHistoryNotification->setObjectType($historyObjectData['type']);
                        $modelHistoryNotification->setMessage(($historyObjectData['message'] != '') ? $historyObjectData['message'] : $historyObjectData['user_action'] . "_TEXT");
                        $modelHistoryNotification->setCompanyUuid(ModuleModel::$company->getUuid());
                        $modelHistoryNotification->setIp($this->request->getClientAddress());

                        $return = $modelHistoryNotification->__quickCreate();

                        if ($return['success'] == true){
                            $return['message'] = 'HISTORY_SAVE_SUCCESS_TEXT';
                        }else{
                            $return['message'] = 'HISTORY_SAVE_FAIL_TEXT';
                        }
                    }
                }
                /**** send to channel ***/
                foreach ($viewers as $user_uuid) {
                    $channels[] = $user_uuid;
                };
            } else {
                $return = ['success' => false, 'message' => 'NO_VIEWERS_TEXT', 'detail' => []];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function sendPushNotificationForUserAction()
    {
        $this->checkAjaxPost();

        $object_uuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'detail' => []];
        $data = Helpers::__getRequestValues();


        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {

            $membersUuids = DataUserMember::getMembersUuids($object_uuid);
            if (count($membersUuids)) {
                foreach ($membersUuids as $user_uuid) {
                     if ($user_uuid != ModuleModel::$user->getUuid()) {
                        $channels[] = $user_uuid;
                    }
                };
                if($data->action == 'HISTORY_REMOVE' && $data->type == 'S'){
                    $channels[] = ModuleModel::$user->getUuid();
                }
                $historyObjectData = HistoryHelper::__getHistoryObject(ModuleModel::$language, $data);
                if (is_string($historyObjectData['object'] )){
                    $historyObjectData['object'] = json_decode($historyObjectData['object'], true);
                }
                if (is_string($historyObjectData['params'])){
                    $historyObjectData['params'] = json_decode($historyObjectData['params'], true);
                }

                if (isset($channels) && count($channels) > 0) {
                    $pushHelpers = new PushHelper();
                    $return = $pushHelpers->sendMultipleChannels($channels, PushHelper::EVENT_GET_NEW_NOTIFICATION, $historyObjectData);
                    $return['channels'] = $channels;
                }
                $return['success'] = true;
                $return['data'] = $historyObjectData;

            } else {
                $return = ['success' => false, 'message' => 'NO_VIEWERS_TEXT', 'detail' => []];
            }
        }
        $return['simple_data'] = $data;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $time
     * @return mixed
     */
    public function getSimpleListNotificationAction($time = '')
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($time == '' || $time == 0 || $time == 'undefined' || !is_numeric($time)) {
            $time = $this->request->getQuery('time', 'int');
        }
        if ($time == '') {
            $time = time();
        }
        $limitSearch = Helpers::__getRequestValue('page_size');
        $page = Helpers::__getRequestValue('page');
        $todayStart = Helpers::__getRequestValue('todayStart');
        $unread = Helpers::__getRequestValue('unread');
        $isHistoryComment = Helpers::__getRequestValue('isHistoryComment');

        $params = [
            'limit' => $limitSearch,
            'todayStart' => $todayStart,
            'user_uuid' => ModuleModel::$user->getUuid(),
            'isAdminOrManager' => ModuleModel::$user->isAdminOrManager(),
            'lastTimeRead' => strtotime('today - 30 days'),
            'page' => $page,
        ];
        if ($unread){
            $params['unread'] = true;
        }

        if ($isHistoryComment){
            $params['isHistoryComment'] = true;
        }

        $return = HistoryNotification::__findWithFilter($params);

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * count number of notification
     * @return mixed
     */
    public function countNotificationAction()
    {
        $this->checkAjaxGet();
        $user_data = ModuleModel::$user->getUserData();

        if ($user_data) {
            $lastTimeReadNotification = $user_data->getNotificationReadAt();
        } else {
            $lastTimeReadNotification = 0;
        }

        //$lastTimeReadNotification = time() - 7 * 24 * 3600;

        $return = HistoryNotification::__findWithFilter([
            'limit' => 1,
            'user_uuid' => ModuleModel::$user->getUuid(),
            'lastTimeRead' => (double)$lastTimeReadNotification
        ]);

        $return['count'] = $return['total_items'];
        $return['min_count_possible'] = $return['total_items'];
        $return['profile'] = ModuleModel::$user->getUuid();
        $return['notificationReadAt'] = $lastTimeReadNotification;


        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * read notification
     */
    public function readNotificationSimpleAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '') {
            $notification = HistoryNotification::findFirstByUuid($uuid);
            $notification->setReadtime(time());

            $return = $notification->__quickUpdate();

        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function setReadTimeNotificationAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $User = ModuleModel::$user;
//        $return = $User->saveUserData([
//            'notification_read_at' => time(),
//        ]);
        $return['success'] = true;
        $return['data'] = $User;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * load last unread notification
     */
    public function quickloadlatestAction()
    {

    }

    /**
     * @param string $time
     * @return mixed
     */
    public function todayAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $return = $this->getNotificationHistoryToday();
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return array
     */
    public function getNotificationHistoryToday()
    {
        SMXDDynamoORM::__init();
        $startKeySearch = Helpers::__getRequestValue('lastObject') ? Helpers::__getRequestValue('lastObject') : (
        $this->request->get('lastObject') ? $this->request->get('lastObject') : false);
        $user_uuid = Helpers::__getRequestValue('user_uuid');
        $page = Helpers::__getRequestValue('page');
        $user_uuid = Helpers::__getRequestValue('user_uuid');
        $time = $this->request->getQuery('time', 'int');
        if ($time == '') {
            $time = time();
        }

        SMXDDynamoORM::__init();

        $limitSearch = Helpers::__getRequestValue('limit') ? Helpers::__getRequestValue('limit') : 20;

        if(isset($user_uuid)  && $user_uuid != null && $user_uuid != '' ){
//            $notificationObject = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayHistory')
//                ->index('CompanyUuidCreatedAtIndex')
//                ->limit(20)
//                ->where('company_uuid', ModuleModel::$company->getUuid())
//                ->where('created_at', '<', strval($time))
//                ->filter('user_uuid', $user_uuid);

            $return = History::__findWithFilter([
               'user_uuid' => $user_uuid,
               'company_uuid' => ModuleModel::$company->getUuid(),
                'page' => $page ? $page : 1,
                'limit' => $limitSearch
            ]);
        } else {
//            $notificationObject = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayHistory')
//                ->index('CompanyUuidCreatedAtIndex')
//                ->limit(20)
//                ->where('company_uuid', ModuleModel::$company->getUuid())
//                ->where('created_at', '<', strval($time));

            $return = History::__findWithFilter([
                'company_uuid' => ModuleModel::$company->getUuid(),
                'page' => $page ? $page : 1,
                'limit' => $limitSearch
            ]);
        }

        end_of_function:
        return $return;

    }

    /**
     * from AWS_NOTIFICCATION_HISTORY
     * filter by USER PROFILE
     * @return array
     */
    public function getNotificationHistoryTodayByUser($user_uuid)
    {

        SMXDDynamoORM::__init();

        $notificationObject = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayHistoryNotification')
            ->index('UserUuidCreatedAtIndex')
            ->limit(20)
            ->where('user_uuid', $user_uuid)
            ->where('created_at', '<', strval(time()));

        $notifications = $notificationObject->findMany(['ScanIndexForward' => false]);

        $items = [];

        foreach ($notifications as $item) {

            $items[] = [
                'uuid' => $item->uuid,
                'object_uuid' => $item->object_uuid,
                'action' => $item->action,
                'user_name' => $item->user_name,
                'content' => $item->message,
                'user_uuid' => $item->user_uuid,
                'time' => $item->created_at,
                'created_at' => $item->created_at,
                'date' => date('Y-m-d H:i:s', $item->created_at),
                'type' => $item->type != '' ? $item->type : (isset($item->object) &&
                isset($item->object['type']) &&
                isset($item->object['type']['S']) ? $item->object['type']['S'] : null),
                'object' => Helpers::__parseDynamodbObjectParams($item->object),
                'params' => Helpers::__parseDynamodbObjectParams($item->params),
                'readtime' => $item->readtime ? $item->readtime : -1,
                'host' => $this->request->getHttpHost()
            ];
        }

        if ($notifications && is_object($notificationObject) && method_exists($notificationObject, 'getLastEvaluatedKey')) {
            $lastEvaluateObject = $notificationObject->getLastEvaluatedKey();
        }

        $return = [
            'success' => true,
            'data' => $items,
            'lastevaluatedtime' => isset($lastEvaluateObject) && is_object($lastEvaluateObject) && isset($lastEvaluateObject->created_at) ? $lastEvaluateObject->created_at : "0",
            'last' => isset($lastEvaluateObject) && is_object($lastEvaluateObject) ? $lastEvaluateObject : null,
            'count' => is_object($notificationObject) ? $notificationObject->getCount() : 0,
            'profile' => $user_uuid
        ];
        return $return;
    }

    /**
     * get last notification
     * @param string $time
     * @return mixed
     */
    /*
    public function getLastNotificationAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName() );

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        try {

            DynamoORM::configure("key", $this->getDi()->getShared('appConfig')->aws->access_key);
            DynamoORM::configure("secret", $this->getDi()->getShared('appConfig')->aws->secret);
            DynamoORM::configure("region", $this->getDi()->getShared('appConfig')->aws->region);

            $modelHistoryNotificationObject = DynamoORM::factory('\SMXD\Application\Models\RelodayHistoryNotification')
                ->index('UserUuidReadtimeIndex')
                ->where('user_uuid', ModuleModel::$user->getUuid())
                ->where('readtime', strval(HistoryNotification::DEFAULT_READ))
                ->limit(3);

            $historyNotificationList = $modelHistoryNotificationObject->findMany(['ScanIndexForward' => false]);
            $items = [];

            foreach ($historyNotificationList as $item) {
                $items[] = [
                    'uuid' => $item->uuid,
                    'object_uuid' => $item->uuid,
                    'user_action' => $item->user_action,
                    'user_name' => $item->user_name,
                    'content' => ($item->message != '') ? $item->message : $item->user_action . "_TEXT",
                    'user_uuid' => $item->user_uuid,
                    'time' => $item->created_at,
                    'created_at' => $item->created_at,
                    'date' => date('Y-m-d H:i:s', $item->created_at),
                    'type' => $item->type,
                    'object' => Helpers::__parseDynamodbObjectParams($item->object),
                    'user' => Helpers::__parseDynamodbObjectParams($item->user),
                    'params' => Helpers::__parseDynamodbObjectParams($item->params),
                    'readtime' => $item->readtime > 0 ? $item->readtime : -1,
                    'host' => $this->request->getHttpHost()
                ];
            }
            $return = ['success' => true, 'data' => $items ];
        } catch (DynamoDbException $e) {
            $return = ['success' => false, 'message' => $e->getMessage()];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
    */

    /**
     * from AWS_HISTORY
     * filter by Object uuid
     */
    public function getObjectFeedAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $limit = Helpers::__getRequestValue('limit') ? Helpers::__getRequestValue('limit') : 20;
        $objectUuid = Helpers::__getRequestValue('objectUuid') ? Helpers::__getRequestValue('objectUuid') : '';
        $isCompany = Helpers::__getRequestValue('isCompany');
        $page = Helpers::__getRequestValue('page');

        $return = History::__findWithFilter([
            'object_uuid' => $objectUuid,
            'company_uuid' => ModuleModel::$company->getUuid(),
            'limit' => $limit,
            'page' => $page ? $page : 1
        ]);


        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Mark as read or unread
     * @return \Phalcon\Http\ResponseInterface
     */
    public function changeReadNotificationAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid') ? Helpers::__getRequestValue('uuid') : '';
        $unread = Helpers::__getRequestValue('unread');

        $historyNotification = HistoryNotification::findFirstByUuid($uuid);
        if(!$historyNotification){
            goto end_of_function;
        }

        if($unread){
            $return = $historyNotification->setUnRead();
            $return['isRead'] = true;
        }else{
            $return = $historyNotification->setRead();
            $return['isRead'] = false;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Mark all as read
     * Last 30 days
     * @return \Phalcon\Http\ResponseInterface
     */
    public function markAllReadAction(){
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => true, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $notifications = HistoryNotification::find([
            'conditions' => 'user_uuid = :user_uuid: and company_uuid = :company_uuid: and is_read = :unread: and created_at > :lastTimeRead:',
            'bind' => [
                'user_uuid' => ModuleModel::$user->getUuid(),
                'company_uuid' => ModuleModel::$company->getUuid(),
                'lastTimeRead' => strtotime('today - 30 days'),
                'unread' => HistoryNotification::IS_UNREAD,
            ]
        ]);

        if(count($notifications) == 0){
            goto end_of_function;
        }

        foreach ($notifications as $item){
            $result = $item->setRead();
        }

        $return = ['success' => true, 'message' => 'DATA_UPDATED_SUCCESSFULLY', 'isUpdated' => true];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Add notificationt to Target User
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Phalcon\Security\Exception
     */
    public function addNotificationForTagUserAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $object_uuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'detail' => []];

        if (!$object_uuid || Helpers::__isValidUuid($object_uuid) == false) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;

        }

        if ($object_uuid != '') {
            $type = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
        }

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $historyObjectData = HistoryHelper::__getHistoryObject($this->language_key);
            SMXDDynamoORM::__init();
            $targetUser = Helpers::__getRequestValue('target_user');
            if($targetUser){
                if(is_object($targetUser)){
                    $UserUuid = $targetUser->uuid;
                }else{
                    $UserUuid = $targetUser['uuid'];
                }
                $profile = User::findFirstByUuid($UserUuid);
                if ($profile && $profile->getUuid() != ModuleModel::$user->getUuid()) {

                    $modelHistoryNotification = new HistoryNotification();
                    $modelHistoryNotification->setData($historyObjectData);
                    $modelHistoryNotification->setUuid(Helpers::__uuid());
                    $modelHistoryNotification->setUserUuid($profile->getUuid());
                    $modelHistoryNotification->setObjectType($historyObjectData['type']);
                    $modelHistoryNotification->setMessage(($historyObjectData['message'] != '') ? $historyObjectData['message'] : $historyObjectData['user_action'] . "_TEXT");
                    $modelHistoryNotification->setCompanyUuid(ModuleModel::$company->getUuid());
                    $modelHistoryNotification->setIp($this->request->getClientAddress());

                    $return = $modelHistoryNotification->__quickCreate();

                    if ($return['success'] == true){
                        $return['message'] = 'HISTORY_SAVE_SUCCESS_TEXT';
                    }else{
                        $return['message'] = 'HISTORY_SAVE_FAIL_TEXT';
                    }
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * save notification to SQS
     * @return mixed
     */
    public function assigneeAddNotificationAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $data = Helpers::__getRequestValuesArray();
        $object_uuid = Helpers::__getRequestValue('uuid');
        $employee_uuid = Helpers::__getRequestValue('employee_uuid');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'rawData' => $data];
        if (!($object_uuid != '' && Helpers::__isValidUuid($object_uuid))) {
            goto end_of_function;
        }

        $employee = Employee::findFirstByUuid($employee_uuid);
        if (!$employee) {
            $return = ['success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT', 'rawData' => $data];
            goto end_of_function;
        }

        $data['creator_user_uuid'] = $employee->getUuid();
        $data['creator_company_id'] = $employee->getCompanyId();


        $dataParams = [
            'sender_name' => 'Notification',
            'root_company_id' => ModuleModel::$company->getId(),
            'language' => ModuleModel::$company->getLanguage(),
            'params' => $data,
        ];
        $return = ['success' => true, '$resultQueue' => $resultQueue];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
