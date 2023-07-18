<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayDynamoORM;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\ReminderConfig;
use Reloday\Gms\Models\ReminderUserDeclined;
use \Reloday\Gms\Models\Task as Task;
use \Reloday\Gms\Models\UserProfile as UserProfile;
use \Reloday\Gms\Models\DataUserMember as DataUserMember;
use \Reloday\Gms\Models\ModuleModel as ModuleModel;
use \Reloday\Gms\Models\HistoryOld as History;
use \Reloday\Gms\Models\MediaAttachment;
use \Reloday\Application\Models\RelodayReminderItem as RelodayReminderItem;
use Reloday\Gms\Module;
use \Firebase\JWT\JWT;
use Aws;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\Exception\DynamoDbException;
use Phalcon\Security\Random;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ReminderControllerV1 extends BaseController
{
    /**
     * @Route("/reminder", paths={module="gms"}, methods={"GET"}, name="gms-reminder-index")
     */
    public function indexAction()
    {

    }

    /**
     * save Item Reminder
     * @param $task_uuid
     */
    public function createReminderItemAction($task_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex();


        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
            'detail' => $this->request->getPut(),
            'task_uuid' => $task_uuid
        ];

        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {
            $task = Task::findFirstByUuid($task_uuid);
            if ($task && $task->belongsToGms() &&
                $task->checkMyEditPermission() == true
            ) {

                if ($task->checkReminderApplyActive() == false) {
                    $return = ['success' => false,
                        'message' => 'DATA_NOT_ENOUGH_TEXT',
                        'detail' => $this->request->getPut()
                    ];
                } else {

                    RelodayDynamoORM::__init();

                    try {
                        $reminderObject = RelodayDynamoORM::factory('\Reloday\Application\Models\RelodayReminderItem')->create();
                        $random = new Random();

                        $data = json_decode(json_encode(Helpers::__getRequestValue('data')), true);
                        $reminderObject->uuid = $random->uuid();
                        $reminderObject->created_at = time();
                        $reminderObject->execute_at = Helpers::__getRequestValue('execute_at');
                        $reminderObject->user_profile_uuid = Helpers::__getRequestValue('user_profile_uuid');
                        $reminderObject->user_name = Helpers::__getRequestValue('user_name');
                        $reminderObject->task_uuid = $task->getUuid();
                        $reminderObject->data = $data;
                        $reminderObject->begin_at = Helpers::__getRequestValue('begin_at');
                        $reminderObject->end_at = Helpers::__getRequestValue('end_at');
                        $reminderObject->quantity = Helpers::__getRequestValue('quantity');
                        $reminderObject->save();

                        $return = ['success' => true,
                            'message' => 'REMINDER_SAVE_SUCCESS_TEXT',
                        ];

                    } catch (Exception $e) {
                        $return = ['success' => true,
                            'message' => 'REMINDER_SAVE_SUCCESS_TEXT',
                            'detail' => $e->getMessage()
                        ];
                    }


                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function getReminderConfigAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

    }

    /**
     *
     */
    public function generateAction($task_uuid)
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex();
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
            'detail' => $this->request->getPut(),
            'task_uuid' => $task_uuid
        ];

        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {
            $task = Task::findFirstByUuid($task_uuid);
            if ($task && $task->belongsToGms() &&
                $task->checkMyEditPermission() == true
            ) {

                if ($task->checkReminderApplyActive() == false) {
                    $return = ['success' => false,
                        'message' => 'DATA_NOT_ENOUGH_TEXT',
                        'detail' => $this->request->getPut()
                    ];
                } else {
                    $begin_at = $task->getReminderBeginAt();
                    $end_at = $task->getReminderEndAt();
                    $recurrence_second = $task->getReminderRecurrenceSecond();
                    $recurrence_number = $task->getRecurrenceNumber();
                    $bloc = [
                        'begin_at' => $begin_at,
                        'end_at' => $end_at,
                        'begin_at_date' => date('Y-m-d H:i:s', $begin_at),
                        'end_at_date' => date('Y-m-d H:i:s', $end_at),
                        'recurrence_second' => $recurrence_second,
                        'recurrence_number' => $recurrence_number
                    ];
                    $return['data'] = $bloc;
                    //$profiles = $task->getMembers();
                    //$profiles = array_unique($profiles->toArray(), SORT_REGULAR); //magic method to check array_unique
                    $ownerTarget = $task->getDataOwner();
                    if ($ownerTarget) {
                        $profiles = [$ownerTarget];
                    } else {
                        $profiles = [];
                    }


                    $data = $task->getDynamoArrayData();
                    $resultFinal = true;
                    $arrayReminder = [];
                    for ($i = 1; $i <= $recurrence_number; $i++) {
                        foreach ($profiles as $profile) {
                            $random = new Random();
                            $arrayReminder[] = [
                                'uuid' => $random->uuid(),
                                'created_at' => time(),
                                'execute_at' => ($begin_at + $i * $recurrence_second),
                                'user_profile_uuid' => is_array($profile) ? $profile['uuid'] : $profile->getUuid(),
                                'user_name' => is_array($profile) ? $profile['firstname'] . " " . (array)$profile['lastname'] : $profile->getFirstname() . " " . $profile->getLastname(),
                                'task_uuid' => $task->getUuid(),
                                'data' => $data,
                                'begin_at' => $begin_at,
                                'end_at' => $end_at,
                                'quantity' => $recurrence_number,
                            ];
                        }
                    }

                    if ($resultFinal == false) {
                        $return = ['success' => false, 'message' => 'REMINDER_SAVE_FAIL_TEXT', 'detail' => $data, 'encoded' => $result];
                    } else {
                        $return = ['success' => true, 'message' => 'REMINDER_SAVE_SUCCESS_TEXT', 'detail' => $data, 'encoded' => $result];
                    }
                    $return['data'] = $arrayReminder;
                    $return['profiles'] = $profiles;
                    $return['bloc'] = $bloc;
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function stopAction($reminderUuid)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
            'reminderUuid' => $reminderUuid
        ];

        if ($reminderUuid != '' && Helpers::__isValidUuid($reminderUuid)) {
            $reminderConfig = ReminderConfig::findFirstByUuidCache($reminderUuid);
            if ($reminderConfig && $reminderConfig->belongsToGms()) {
                $userProfileUuid = ModuleModel::$user_profile->getUuid();
                $reminderUserDeclined = new ReminderUserDeclined;
                $reminderUserDeclined->setData([
                    'user_profile_uuid' => $userProfileUuid,
                    'reminder_config_id' => $reminderConfig->getId(),
                ]);
                $return = $reminderUserDeclined->__quickCreate();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * show 5 last reminder
     */
    public function getLastReminderItemsAction()
    {

        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        RelodayDynamoORM::__init();

        $reminderObject = RelodayDynamoORM::factory('\Reloday\Application\Models\RelodayReminderItem')
            ->index('UserProfileUuidExecuteAtIndex')
            ->where('user_profile_uuid', ModuleModel::$user_profile->getUuid())
            ->where('execute_at', '~', [strval(time() - 5 * 60), strval(time())])
            ->limit(20);

        $reminderList = $reminderObject->findMany(['ScanIndexForward' => true]);
        $items = [];
        if (count($reminderList) > 0) {
            foreach ($reminderList as $item) {
                $data = Helpers::__parseDynamodbObjectParams($item->data);
                $items[] = [
                    'uuid' => $item->uuid,
                    'task_uuid' => $item->task_uuid,
                    'user_name' => $item->user_name,
                    'user_profile_uuid' => $item->user_profile_uuid,
                    'created_at' => $item->created_at,
                    'execute_at' => $item->execute_at,
                    'date' => date('Y-m-d H:i:s', $item->created_at),
                    'name' => $data['name'],
                    'frontend_url' => $data['frontend_url'],
                ];
            }

            $return = [
                'success' => true,
                'data' => $items,
                'lastevaluatedtime' => $reminderObject->getLastEvaluatedKey() ? $reminderObject->getLastEvaluatedKey()->created_at : "0",
                'last' => $reminderObject->getLastEvaluatedKey() ? $reminderObject->getLastEvaluatedKey() : "0",
                'count' => $reminderObject->getCount(),
                'profile' => ModuleModel::$user_profile->getUuid(),
                'execute_at' => strval(time()),
            ];
        }


        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function standByReminderAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $reminder_uuid = Helpers::__getRequestValue('uuid');
        $task_uuid = Helpers::__getRequestValue('task_uuid');

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
            'uuid' => $reminder_uuid,
            'task_uuid' => $task_uuid
        ];

        RelodayDynamoORM::__init();

        if ($reminder_uuid != '' && $task_uuid != '' && Helpers::__isValidUuid($reminder_uuid) && Helpers::__isValidUuid($task_uuid)) {
            try {
                $reminderObject = RelodayDynamoORM::factory('\Reloday\Application\Models\RelodayReminderItem')->findOne($reminder_uuid);
                if ($reminderObject && $reminderObject->uuid != '') {
                    $reminderObject->delete();
                    $return = ['success' => true];
                }
            } catch (DynamoDbException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            } catch (Exception $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * delete all reminder of the current serie of reminder
     */
    public function clearAllReminderOfUserAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();
        $reminder_uuid = Helpers::__getRequestValue('uuid');
        $task_uuid = Helpers::__getRequestValue('task_uuid');


        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
            'detail' => $this->request->getPut(),
            'uuid' => $reminder_uuid,
            'task_uuid' => $task_uuid
        ];

        if ($reminder_uuid != '' && $task_uuid != '') {

            RelodayDynamoORM::__init();

            if ($reminder_uuid != '' && $task_uuid != '') {
                try {
                    $reminderObjectList = RelodayDynamoORM::factory('\Reloday\Application\Models\RelodayReminderItem')
                        ->index('TaskUuidUserProfileUuidIndex')
                        ->where('task_uuid', $task_uuid)
                        ->where('user_profile_uuid', ModuleModel::$user_profile->getUuid())
                        ->findMany();
                    if (count($reminderObjectList) > 0) {
                        foreach ($reminderObjectList as $reminderObject) {
                            $reminderObject->delete();
                        }
                    }
                    $return = ['success' => true];
                } catch (DynamoDbException $e) {
                    $return = ['success' => false, 'message' => $e->getMessage()];
                } catch (Exception $e) {
                    $return = ['success' => false, 'message' => $e->getMessage()];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $task_uuid
     * @return array
     */
    public function deleteAllReminder($task_uuid)
    {

        if ($task_uuid && Helpers::__isValidUuid($task_uuid)) {

            //get all
            try {
                RelodayDynamoORM::__init();
                $notificationOrm = RelodayDynamoORM::factory('\Reloday\Application\Models\RelodayReminderItem');
                $reminderItems = $notificationOrm->index('TaskUuidUserProfileUuidIndex')->where('task_uuid', $task_uuid)->findMany();

            } catch (DynamoDbException $e) {
                $return = ['success' => false, 'detail' => $e->getMessage(), 'message' => 'CLEAN_REMINDER_FAIL_TEXT',];
                return $return;
            } catch (AwsException $e) {
                $return = ['success' => false, 'detail' => $e->getMessage(), 'message' => 'CLEAN_REMINDER_FAIL_TEXT',];
                return $return;
            } catch (Exception $e) {
                $return = ['success' => false, 'detail' => $e->getMessage(), 'message' => 'CLEAN_REMINDER_FAIL_TEXT',];
                return $return;
            }

            //delete each
            try {
                foreach ($reminderItems as $reminderItem) {
                    $reminderItem->delete();
                }
                $return = ['success' => true];
                return $return;
            } catch (DynamoDbException $e) {
                $return = ['success' => false, 'message' => 'CLEAN_REMINDER_FAIL_TEXT', 'raw_message' => $e->getMessage()];
                return $return;
            } catch (AwsException $e) {
                $return = ['success' => false, 'detail' => $e->getMessage(), 'message' => 'CLEAN_REMINDER_FAIL_TEXT',];
                return $return;
            } catch (Exception $e) {
                $return = ['success' => false, 'message' => 'CLEAN_REMINDER_FAIL_TEXT', 'raw_message' => $e->getMessage()];
                return $return;
            }
        } else {
            $return = ['success' => false, 'message' => 'CLEAN_REMINDER_FAIL_TEXT'];
            return $return;
        }
    }

    /**
     * @param $task_uuid
     */
    public function createNewReminderForTaskAction($task_uuid)
    {
        $this->view->disable();
        $this->checkAcl('POST');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($task_uuid && Helpers::__isValidUuid($task_uuid)) {
            $task = Task::findFirstByUuid($task_uuid);
            if ($task && $task->belongsToGms() && $task->isActive()) {

                //TODO check start date vs enddate and vs number of reminderItems;
                $reminderConfig = new ReminderConfig();

                $db = $reminderConfig->getWriteConnection();
                $db->begin();
                $params = [
                    'object_uuid' => $task_uuid,
                    'reminder_at' => Helpers::__convertToSecond(Helpers::__getRequestValue('reminder_at')),
                    'reminder_time' => Helpers::__getRequestValue('reminder_time'),
                    'reminder_time_unit' => Helpers::__getRequestValue('reminder_time_unit'),
                    'before_after' => Helpers::__getRequestValue('before_after'),
                    'recurrence_time' => Helpers::__getRequestValue('recurrence_time'),
                    'recurrence_time_unit' => Helpers::__getRequestValue('recurrence_time_unit'),
                    'service_event_id' => Helpers::__getRequestValue('service_event_id'),
                    'reminder_date' => Helpers::__getRequestValue('reminder_date'),
                    'company_id' => ModuleModel::$company->getId(),
                ];

                $reminderConfig->setData($params);
                $return = $reminderConfig->__quickSave();

                if ($return['success'] == true) {
                    $reminderConfig->sendRequestCreateReminderItem();
                    if ($reminderConfig->checkReminderApplyActive() == true) {
                        $reminderItemArray = $reminderConfig->getReminderItemArray();
                        if (count($reminderItemArray) > 0) {
                            foreach ($reminderItemArray as $reminderItemConfig) {
                                $resultItem = $reminderConfig->createReminderItem($reminderItemConfig);
                                if ($resultItem['success'] == false) {
                                    $db->rollback();
                                    $return = $resultItem;
                                    goto end_of_function;
                                }
                            }
                            $return = ['success' => true, 'message' => 'SAVE_REMINDER_SUCCESS_TEXT'];
                        } else {
                            $return = ['success' => false, 'message' => 'NO_REMINDER_ITEM_TEXT'];
                        }
                    }

                    $db->commit();
                    $return['data'] = $reminderConfig;
                    $return['message'] = 'SAVE_REMINDER_SUCCESS_TEXT';
                } else {
                    $db->rollback();
                    if (is_array($return['detail'])) {
                        $return['message'] = reset($return['detail']);
                    } else {

                        $return['message'] = 'SAVE_REMINDER_FAIL_TEXT';
                    }
                }

                $return['params'] = $params;
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $task_uuid
     */
    public function deleteReminderConfigAction($uuid)
    {
        $this->view->disable();
        $this->checkAcl('POST');
        $this->checkAjax('index', 'reminder');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $reminder = ReminderConfig::findFirstByUuid($uuid);

            if ($reminder && $reminder->belongsToGms()) {

                $reminder->removeAllItemsInDynamoDb();

                $return = $reminder->remove();

                if ($return['success'] == true) {

                    $return['message'] = 'DELETE_REMINDER_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'DELETE_REMINDER_FAIL_TEXT';
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
