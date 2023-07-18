<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\BackgroundActionHelpers;
use Reloday\Application\Lib\DynamoHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\UserProfileExt;
use Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Controllers\API\BaseController as BaseController;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Task;
use \Reloday\Gms\Models\HistoryOld;
use \Reloday\Gms\Models\HistoryNotification;
use Reloday\Gms\Models\Timelog;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\ObjectMap;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Module;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class DataMemberController extends BaseController
{
    /**
     * @return mixed
     */
    public function setReporterAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl(AclHelper::ACTION_CHANGE_REPORTER);

        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_REPORTER_TEXT'];
        $object_uuid = Helpers::__getRequestValue('uuid');
        $user_profile_uuid = Helpers::__getRequestValue('user_profile_uuid');

        if (!($object_uuid != '' && Helpers::__isValidUuid($object_uuid))) {
            goto end_of_function;
        }
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_REPORTER_TEXT'];

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid) && $user_profile_uuid != '' && Helpers::__isValidUuid($user_profile_uuid)) {

            $profile = UserProfile::findFirstByUuid($user_profile_uuid);
            $reporter = DataUserMember::getDataReporter($object_uuid);

            $return = ['success' => false, 'message' => 'NO_CHANGE_OF_REPORTER_TEXT'];

            if (($profile && $reporter && $profile->getUuid() != $reporter->getUuid())) {
                $this->db->begin();
                $resultDeleteReporter = DataUserMember::deleteReporters($object_uuid);
                if ($resultDeleteReporter['success'] == false) {
                    $return = [
                        'success' => false,
                        'message' => 'SET_REPORTER_FAIL_TEXT',
                        'detail' => $resultDeleteReporter
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                $resultAddReporter = DataUserMember::addReporter($object_uuid, $profile, "object");
                if ($resultAddReporter['success'] == false) {
                    $return = [
                        'success' => false,
                        'message' => 'SET_REPORTER_FAIL_TEXT',
                        'detail' => $resultAddReporter
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }
                $this->db->commit();
                $return = [
                    'success' => true,
                    'message' => 'SET_REPORTER_SUCCESS_TEXT',
                ];
            } elseif (!$reporter) {
                $return = DataUserMember::addReporter($object_uuid, $profile, "object");
            }
        }

        end_of_function:
        if ($return['success'] == true && isset($profile)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification(['uuid' => $object_uuid], HistoryModel::TYPE_OTHERS, HistoryModel::HISTORY_SET_REPORTER, [
                'target_user' => $profile
            ]);

            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('object_uuid', $object_uuid);
            $this->dispatcher->setParam('target_user_uuid', $profile->getUuid());
            $this->dispatcher->setParam('target_user', $profile);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $task_uuid
     */
    public function getReporterAction(string $object_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'data' => [], 'message' => 'TASK_NOT_FOUND_TEXT'];

        if ($object_uuid != '') {
            $reporter = DataUserMember::getDataReporter($object_uuid);
            $item = array();

            if ($reporter) {
                $item = $reporter->toArray();
                $item['avatar'] = $reporter->getAvatar();
                $item['company_name'] = ($reporter->getCompany()) ? $reporter->getCompany()->getName() : null;
                $item['office_name'] = ($reporter->getOffice()) ? $reporter->getOffice()->getName() : null;

                $return = [
                    'success' => true,
                    'data' => $item
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'REPORTER_NOT_FOUND_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $task_uuid
     */
    public function getOwnerAction($object_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'data' => [], 'message' => 'OWNER_NOT_FOUND_TEXT'];

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $objectMap = RelodayObjectMapHelper::__getObjectWithCache($object_uuid);
            if ($objectMap && $objectMap->getObjectType() == RelodayObjectMapHelper::TABLE_TIMELOG) {
                $timelog = Timelog::findFirstByUuid($object_uuid);
                $ownerProfile = UserProfile::findFirstById($timelog->getUerProfileId());
                if ($ownerProfile) {
                    $item = $ownerProfile->toArray();
                    $item['avatar'] = $ownerProfile->getAvatar();
                    $item['company_name'] = ($ownerProfile->getCompany()) ? $ownerProfile->getCompany()->getName() : null;
                    $item['office_name'] = ($ownerProfile->getOffice()) ? $ownerProfile->getOffice()->getName() : null;

                    $return = [
                        'success' => true,
                        'data' => $item,
                    ];
                }

            } else {
                $relocation = Relocation::findFirstByUuid($object_uuid);
                $ownerProfile = DataUserMember::getDataOwner($object_uuid);
                if ($ownerProfile) {
                    $item = $ownerProfile->toArray();
                    $item['avatar'] = $ownerProfile->getAvatar();
                    $item['company_name'] = ($ownerProfile->getCompany()) ? $ownerProfile->getCompany()->getName() : null;
                    $item['office_name'] = ($ownerProfile->getOffice()) ? $ownerProfile->getOffice()->getName() : null;

                    $return = [
                        'success' => true,
                        'checkRelocation' => $relocation ? $relocation->getDataOwner() : null,
                        'data' => $item,
                    ];
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function setOwnerAction()
    {
        $this->view->disable();
        $this->checkAcl('change_owner', 'member');
        $this->checkAjax('PUT');

        $return = ['success' => false, 'message' => 'SET_OWNER_FAIL_TEXT'];
        $data = Helpers::__getRequestValuesArray();
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_OWNER_TEXT', 'detail' => $data];
        $object_uuid = Helpers::__getRequestValue('uuid');
        $user_profile_uuid = Helpers::__getRequestValue('user_profile_uuid');

        if (!($object_uuid != '' && Helpers::__isValidUuid($object_uuid) && $user_profile_uuid != '' && Helpers::__isValidUuid($user_profile_uuid))) {
            goto end_of_function;
        }

        /**
         * if add owner for task, if task creator is same with current user, and current user is the new owner
         */
        $objectMap = RelodayObjectMapHelper::__getObject($object_uuid);
        if (!$objectMap) {
            $objectTask = Task::findFirstByUuidCache($object_uuid);
        }

        if (($objectMap && $objectMap->isTask()) || (isset($objectTask) && $objectTask)) {
            $accessResult = $this->canAccessResource(AclHelper::CONTROLLER_DATA_MEMBER, AclHelper::ACTION_CHANGE_OWNER);
            if (!$accessResult['success']) {
                $taskCreator = DataUserMember::getDataCreator($object_uuid);
                if (!$taskCreator || $user_profile_uuid != ModuleModel::$user_profile->getUuid() || $taskCreator->getUuid() != ModuleModel::$user_profile->getUuid()) {
                    $accessResult['@user_profile'] = ModuleModel::$user_profile;
                    return $this->returnNotAllowOnly($accessResult);
                }
            }
            goto add_owner;
        } else {
            $this->checkAcl(AclHelper::ACTION_CHANGE_OWNER);
        }

        add_owner:
        $profile = UserProfile::findFirstByUuid($user_profile_uuid);
        $owner = DataUserMember::getDataOwner($object_uuid, $profile->getCompanyId());
        $data['owner'] = $owner;
        $data['profile'] = $profile;
        if (!$profile) {
            $return = ['success' => false, 'message' => 'MEMBER_NOT_FOUND_TEXT', 'params' => $data];
            goto end_of_function;
        }

        if ($profile && $owner && $profile->getUuid() == $owner->getUuid()) {
            $return = ['success' => false, 'message' => 'NO_CHANGE_OF_OWNER_TEXT', 'params' => $data];
            goto end_of_function;
        }
        $this->db->begin();
        //delete all current owner
        $returnDeleteOwner = DataUserMember::deleteOwners($object_uuid, $profile->getCompanyId());
        if ($returnDeleteOwner['success'] == false) {
            $return = [
                'success' => false,
                'params' => $returnDeleteOwner,
                'message' => 'DELETE_OWNER_FAIL_TEXT',
            ];
            $this->db->rollback();
            goto end_of_function;
        }


        //add new owner
        $returnAddOwner = DataUserMember::addOwner($object_uuid, $profile, DataUserMember::MEMBER_TYPE_OBJECT_TEXT, $profile->getCompanyId());
        if ($returnAddOwner['success'] == false) {
            $return = [
                'success' => false,
                'resultDeleteOwner' => $returnDeleteOwner,
                'resultAddOwner' => $returnAddOwner,
                'message' => 'SET_OWNER_FAIL_TEXT',
            ];
            $this->db->rollback();
            goto end_of_function;
        }

        $this->db->commit();

        $return['success'] = true;
        $return['message'] = 'SET_OWNER_SUCCESS_TEXT';

        end_of_function:


        if ($return['success'] == true && isset($profile)) {

            //Check User Preference setting (is_sync_owner)
            if(ModuleModel::$user_profile->getIsSyncOwner() == ModelHelper::YES){
                //Service
                $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($object_uuid);
                if($relocationServiceCompany){
                    $return['$apiQueueResults'] =  $relocationServiceCompany->startSyncTaskOwner($profile);
                    $return['message'] = 'CHECK_BULK_CHANGE_SERVICE_TASKS_OWNER_TEXT_SUCCESS_AND_WAIT_TEXT';
                }
                //Relocation
            }


            $object = Assignment::findFirstByUuid($object_uuid);
            if(!$object){
                $object = Relocation::findFirstByUuid($object_uuid);
            }

            if(!$object){
                $object = RelocationServiceCompany::findFirstByUuid($object_uuid);
            }

            if(!$object){
                $object = Task::findFirstByUuid($object_uuid);
            }

            if($object){
                $return['$apiResults'] = NotificationServiceHelper::__addNotification(['uuid' => $object_uuid], HistoryModel::TYPE_OTHERS, HistoryModel::HISTORY_SET_OWNER, [
                    'target_user' => $profile
                ]);
            }

            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('object_uuid', $object_uuid);
            $this->dispatcher->setParam('target_user_uuid', $profile->getUuid());
            $this->dispatcher->setParam('target_user', $profile);

//            if ($objectMap && $objectMap->isTask() == true) {
//                $update_reminder_counter_queue = RelodayQueue::__getQueueUpdateReminderCounter();
//                $dataArray = [
//                    'action' => "update_reminder_counter",
//                    'object_uuid' => $object_uuid,
//                    'old_owner_uuid' => isset($owner) && $owner ? $owner->getUuid() : [],
//                    'new_owner_uuid' => $profile->getUuid()
//                ];
//                $return['update_reminder_counter'] = $update_reminder_counter_queue->addQueue($dataArray);
//            }
        }

        $return['input'] = $data;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function setOwnerHrAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl(AclHelper::ACTION_CHANGE_OWNER);
        $return = ['success' => false, 'message' => 'SET_OWNER_FAIL_TEXT'];

        $data = Helpers::__getRequestValuesArray();
        $return = ['success' => false, 'message' => 'IMPOSSIBLE_EDIT_OWNER_TEXT', 'detail' => $data];
        $object_uuid = Helpers::__getRequestValue('uuid');
        $user_profile_uuid = Helpers::__getRequestValue('user_profile_uuid');
        $company_id = Helpers::__getRequestValue('company_id');

        if (!($object_uuid != '' &&
            Helpers::__isValidUuid($object_uuid) &&
            $user_profile_uuid != '' &&
            Helpers::__isValidUuid($user_profile_uuid) &&
            is_numeric($company_id) &&
            $company_id > 0)) {
            goto end_of_function;
        }

        $company = Company::findFirstById($company_id);
        if (!$company || $company->isHr() == false || $company->belongsToGms() == false) {
            $return = ['success' => true, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $profile = UserProfile::findFirstByUuid($user_profile_uuid);
        $owner = DataUserMember::getDataOwner($object_uuid, $company_id);
        $data['owner'] = $owner;
        $data['profile'] = $profile;

        if (!$profile) {
            $return = ['success' => false, 'message' => 'MEMBER_NOT_FOUND_TEXT', 'params' => $data];
            goto end_of_function;
        }

        if ($profile && $owner && $profile->getUuid() == $owner->getUuid()) {
            $return = ['success' => false, 'message' => 'NO_CHANGE_OF_OWNER_TEXT', 'params' => $data];
            goto end_of_function;
        }

        $this->db->begin();
        //add new owner
        $returnAddOwner = DataUserMember::addOwner($object_uuid, $profile, DataUserMember::MEMBER_TYPE_OBJECT_TEXT, $company_id);
        if ($returnAddOwner['success'] == false) {
            $return = [
                'success' => false,
                'params' => $returnAddOwner,
                'message' => 'SET_OWNER_FAIL_TEXT',
            ];
            $this->db->rollback();
            goto end_of_function;
        }

        $this->db->commit();
        $return = [
            'success' => true,
            'message' => 'SET_OWNER_SUCCESS_TEXT',
        ];

        end_of_function:

        if ($return['success'] == true && isset($profile)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification(['uuid' => $object_uuid], HistoryModel::TYPE_OTHERS, HistoryModel::HISTORY_SET_OWNER, [
                'target_user' => $profile
            ]);
        }
        $return['input'] = $data;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Delete OWNER
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteOwnerAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl(AclHelper::ACTION_CHANGE_OWNER);
        $return = ['success' => false, 'message' => 'SET_OWNER_FAIL_TEXT'];
        $company_id = Helpers::__getRequestValue('company_id');
        $object_uuid = Helpers::__getRequestValue('uuid');

        $company = Company::findFirstByIdCache($company_id);
        if (!$company || $company->isHr() == false || $company->belongsToGms() == false) {
            $return = ['success' => true, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        $return = DataUserMember::deleteOwners($object_uuid, $company_id);
        if ($return['success'] == false) {
            $return = [
                'success' => false,
                'detail' => $return['detail'],
                'message' => 'SET_OWNER_FAIL_TEXT',
            ];
            goto end_of_function;
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * get all members of list
     * @param $object_uuid
     * @return mixed
     */
    public function listAction($object_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        return $this->getListMemberFromDb($object_uuid);
    }

    /**
     * @param $object_uuid
     */
    public function getReporterFromDb($object_uuid)
    {

    }


    /**
     * get all viewers of list
     * @param $object_uuid
     * @return mixed
     */
    public function getViewersAction($object_uuid = '')
    {
        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];
        $this->view->disable();
        $this->checkAjaxGet();

        if ($object_uuid == '') {
            $object_uuid = Helpers::__getRequestValue('object_uuid');
        }

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $viewers = DataUserMember::__getDataViewers($object_uuid);
            $return = [
                'success' => true,
                'data' => $viewers
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $object_uuid
     */
    public function getListMemberFromDb($object_uuid = '', $options = [])
    {
        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];

        if ($object_uuid == '') {
            $data = $this->request->getJsonRawBody();
            $object_uuid = isset($data->object_uuid) && $data->object_uuid != '' ? $data->object_uuid : '';
        }

        if ($object_uuid == '') {
            $members = UserProfile::getGmsWorkers();
            $viewer_uuids = [];
        }

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {
            $viewer_uuids = DataUserMember::getViewersUuids($object_uuid);
            $members = $this->getPotentialViewers($object_uuid);
        }


        if (isset($members) && isset($viewer_uuids) && $members->count() > 0) {
            $users_array = [];
            foreach ($members as $user) {
                /* check selected */
                $users_array[$user->getUuid()] = $user->toArray();
                $users_array[$user->getUuid()]['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
                $users_array[$user->getUuid()]['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;

                if (isset($viewer_uuids[$user->getUuid()]) && $viewer_uuids[$user->getUuid()] != '') {
                    $users_array[$user->getUuid()]['selected'] = true;
                    $users_array[$user->getUuid()]['is_viewer'] = true;
                } else {
                    $users_array[$user->getUuid()]['selected'] = false;
                    $users_array[$user->getUuid()]['is_viewer'] = false;
                }
            }
            $return = [
                'success' => true,
                'data' => array_values($users_array)
            ];
        } else {
            $return = [
                'success' => false,
                'message' => 'VIEWERS_NOT_FOUND_TEXT',
                'data' => []
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $object_uuid
     */
    public function getListMemberGmsFromDb($object_uuid)
    {
        if ($object_uuid == '') {
            $data = $this->request->getJsonRawBody();
            $object_uuid = isset($data->object_uuid) && $data->object_uuid != '' ? $data->object_uuid : '';
        }

        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {

            $viewer_uuids = DataUserMember::getViewersUuids($object_uuid);
            $members = UserProfile::getGmsWorkers();

            if (isset($members) && isset($viewer_uuids) && $members->count() > 0) {
                $users_array = [];
                foreach ($members as $user) {
                    /* check selected */
                    $users_array[$user->getUuid()] = $user->toArray();
                    $users_array[$user->getUuid()]['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
                    $users_array[$user->getUuid()]['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;

                    if (isset($viewer_uuids[$user->getUuid()]) && $viewer_uuids[$user->getUuid()] != '') {
                        $users_array[$user->getUuid()]['selected'] = true;
                    } else {
                        $users_array[$user->getUuid()]['selected'] = false;
                    }
                }
                $return = [
                    'success' => true,
                    'data' => array_values($users_array)
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'VIEWERS_NOT_FOUND_TEXT',
                    'data' => []
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $object_uuid
     */
    public function getListFromCloud($object_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        if ($object_uuid == '') {
            $data = $this->request->getJsonRawBody();
            $object_uuid = isset($data->object_uuid) && $data->object_uuid != '' ? $data->object_uuid : '';
        }
        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];
        if ($object_uuid != '') {
            $objectType = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
            $viewer_uuids = DynamoHelper::__getViewerIdsOfObject($object_uuid);

            if ($objectType == HistoryHelper::TYPE_ASSIGNMENT) {
                $assignment = Assignment::findFirstByUuid($object_uuid);
                if ($assignment) {
                    $members = UserProfile::getGmsWorkers();
                }
            } elseif ($objectType == HistoryHelper::TYPE_RELOCATION) {
                $relocation = Relocation::findFirstByUuid($object_uuid);
                if ($relocation) {
                    $members = UserProfile::getGmsWorkers();
                }
            } elseif ($objectType == HistoryHelper::TYPE_SERVICE) {
                $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($object_uuid);
                if ($relocationServiceCompany) {
                    $members = UserProfile::getGmsWorkers();
                }
            } elseif ($objectType == HistoryHelper::TYPE_TASK) {
                $task = Task::findFirstByUuid($object_uuid);
                if ($task) {
                    if ($task->getLinkType() == Task::LINK_TYPE_RELOCATION) {
                        if ($task->getRelocation()) {
                            $members = UserProfile::getGmsWorkers();
                        } else {
                            $members = UserProfile::getGmsWorkers();
                        }
                    } elseif ($task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT) {
                        if ($task->getAssignment()) {
                            $members = UserProfile::getGmsWorkers();
                        } else {
                            $members = UserProfile::getGmsWorkers();
                        }
                    } elseif ($task->getLinkType() == Task::LINK_TYPE_SERVICE) {
                        if ($task->getRelocationServiceCompany()) {
                            $members = UserProfile::getGmsWorkers();
                        } else {
                            $members = UserProfile::getGmsWorkers();
                        }
                    } else {
                        $members = UserProfile::getGmsWorkers();
                    }
                    $viewer_uuids = DataUserMember::getViewersUuids($task->getUuid());
                }
            } else {
                $members = UserProfile::getGmsWorkers();
            }
            if (isset($members) && isset($viewer_uuids) && $members->count() > 0) {
                $users_array = [];
                foreach ($members as $user) {
                    /* check selected */
                    $users_array[$user->getId()] = $user->toArray();
                    $users_array[$user->getId()]['avatar'] = $user->getAvatar();
                    $users_array[$user->getId()]['company_name'] = ($user->getCompany()) ? $user->getCompany()->getName() : null;
                    $users_array[$user->getId()]['office_name'] = ($user->getOffice()) ? $user->getOffice()->getName() : null;
                    if (isset($viewer_uuids[$user->getUuid()]) && $viewer_uuids[$user->getUuid()] != '') {
                        $users_array[$user->getId()]['selected'] = true;
                    } else {
                        $users_array[$user->getId()]['selected'] = false;
                    }
                }
                $return = [
                    'success' => true,
                    'data' => array_values($users_array)
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'VIEWERS_NOT_FOUND_TEXT',
                    'data' => []
                ];
            }
        }
    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public function getPotentialOwnersAction($object_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $data = $this->getPotentialOwner($object_uuid);
        $return = [
            'success' => true,
            'data' => $data
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public function getPotentialReportersAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        if ($object_uuid == '') {
            $data = $this->request->getJsonRawBody();
            $object_uuid = isset($data->object_uuid) && $data->object_uuid != '' ? $data->object_uuid : '';
        }
        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];
        if ($object_uuid != '') {
            $data = $this->getPotentialReporter($object_uuid);
            $return = [
                'success' => true,
                'data' => $data
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $object_uuid
     * @return mixed
     */
    public function getPotentialViewersAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $return = [
            'success' => true,
            'data' => $this->getPotentialViewers(),
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function searchViewersAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $return = UserProfile::__findGmsViewersWithFilter([
            'object_uuid' => Helpers::__getRequestValue('object_uuid'),
            'query' => Helpers::__getRequestValue('query'),
            'page' => Helpers::__getRequestValue('page')
        ], [["field" => 'firstname', "order" => 'asc']]);

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $uuid
     */
    public function getPotentialOwner()
    {
        $members = UserProfile::getGmsWorkers();
        return $members;
    }


    /**
     * @param $uuid
     */
    public function getPotentialReporter($object_uuid)
    {
        $members = UserProfile::getGmsWorkers();
        return $members;
    }


    /**
     * @param $uuid
     */
    public function getPotentialViewers($object_uuid = '')
    {
        $members = UserProfile::getGmsWorkers();
        return $members;
    }

    /**
     * get all members of list
     * @param $object_uuid
     * @return mixed
     */
    public function getCurrentMembersGmsAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        return $this->getListMemberGmsFromDb($object_uuid);
    }

    /**
     * @param $task_uuid
     */
    public function getCreatorAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($object_uuid != '') {
            $reporter = DataUserMember::getDataCreator($object_uuid);

            if ($reporter) {
                $item = $reporter->toArray();
                $item['avatar'] = $reporter->getAvatar();
                $item['company_name'] = ($reporter->getCompany()) ? $reporter->getCompany()->getName() : null;
                $item['office_name'] = ($reporter->getOffice()) ? $reporter->getOffice()->getName() : null;

                $return = [
                    'success' => true,
                    'data' => $item
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'CREATOR_NOT_FOUND_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * For Task ONLY
     * @return mixed
     */
    public function setOwnerObjectAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl(AclHelper::ACTION_CHANGE_OWNER);
        $this->response->setJsonContent(['success' => true]);
        return $this->response->send();
    }


    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function setViewerAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl(AclHelper::ACTION_CHANGE_VIEWER);

        $data = Helpers::__getRequestValuesObject();
        $object_uuid = Helpers::__getRequestValue('object_uuid');
        $member_uuid = Helpers::__getRequestValue('member_uuid');
        $selected = Helpers::__getRequestValue('selected');
        $is_comment = Helpers::__getRequestValue('is_comment');
        $return = ['success' => false, 'input' => (array)$data, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($object_uuid != '' && $member_uuid != '') {

            $user = UserProfile::findFirstByUuid($member_uuid);
            $return = ['success' => false, 'data' => $user, 'message' => 'VIEWER_PROFILE_NOT_FOUND_TEXT'];

            if ($user && ($user->belongsToGms() || $user->manageByGms())) {
                if ($selected === true) {
                    $data_user_member = DataUserMember::findFirst([
                        "conditions" => "object_uuid = :object_uuid: AND user_profile_uuid = :user_profile_uuid:",
                        'bind' => [
                            'object_uuid' => $object_uuid,
                            'user_profile_uuid' => $user->getUuid(),
                        ]
                    ]);

                    if (!$data_user_member) {

                        $returnCreate = DataUserMember::addViewer($object_uuid, $user);

                        if ($returnCreate['success'] == true) {
                            $return = ['success' => true, 'data' => $user, 'message' => 'VIEWER_ADDED_SUCCESS_TEXT'];
                        } else {
                            $return = ['success' => false, 'data' => $user, 'message' => 'VIEWER_ADDED_FAIL_TEXT', 'detail' => $returnCreate];
                        }
                    } else {
                        $return = ['success' => false, 'data' => $user, 'message' => 'VIEWER_EXIST_TEXT'];
                    }
                } elseif ($selected === false) {
                    $data_user_member = DataUserMember::findFirst([
                        "conditions" => "object_uuid = :object_uuid: AND user_profile_uuid = :user_profile_uuid: AND member_type_id = :member_type_id:",
                        'bind' => [
                            'object_uuid' => $object_uuid,
                            'user_profile_uuid' => $user->getUuid(),
                            'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                        ]
                    ]);
                    $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_REMOVE_SUCCESS_TEXT'];
                    if ($data_user_member) {
                        if (!$data_user_member->delete()) {
                            $return = ['success' => false, 'data' => [], 'message' => 'VIEWER_REMOVE_FAIL_TEXT'];
                        }
                    }
                } else {
                    $return = ['success' => false, 'data' => [], 'message' => 'VIEWER_SELECTED_UNDEFINED_TEXT'];
                }
            }
        }
        end:

        if ($return['success'] == true && isset($user)) {
            if ($user->belongsToGms()) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification(['uuid' => $object_uuid], HistoryModel::TYPE_OTHERS, HistoryModel::HISTORY_ADD_VIEWER, [
                    'target_user' => $user
                ]);

                $this->dispatcher->setParam('return', $return);
                $this->dispatcher->setParam('object_uuid', $object_uuid);
                $this->dispatcher->setParam('target_user_uuid', $user->getUuid());
                $this->dispatcher->setParam('target_user', $user);
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function removeViewerAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl(AclHelper::ACTION_CHANGE_VIEWER, AclHelper::CONTROLLER_DATA_MEMBER);
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];

        $object_uuid = Helpers::__getRequestValue('object_uuid');
        $user_profile_uuid = Helpers::__getRequestValue('user_profile_uuid');
        if ($user_profile_uuid == null || $user_profile_uuid == '') {
            $user_profile_uuid = Helpers::__getRequestValue('member_uuid');
        }

        if ($object_uuid &&
            Helpers::__isValidUuid($object_uuid) &&
            $user_profile_uuid &&
            Helpers::__isValidUuid($user_profile_uuid)) {

            $user = UserProfile::findFirstByUuid($user_profile_uuid);

            $return = ['success' => false, 'data' => $user, 'message' => 'VIEWER_PROFILE_NOT_FOUND_TEXT'];

            if ($user && ($user->belongsToGms() || $user->managedByGms())) {

                $data_user_member = DataUserMember::findFirst([
                    "conditions" => "object_uuid = :object_uuid: AND user_profile_uuid = :user_profile_uuid: AND member_type_id = :member_type_id:",
                    'bind' => [
                        'object_uuid' => $object_uuid,
                        'user_profile_uuid' => $user->getUuid(),
                        'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                    ]
                ]);

                $return = ['success' => true, 'data' => [], 'message' => 'VIEWER_REMOVE_SUCCESS_TEXT'];
                if ($data_user_member) {
                    if (!$data_user_member->delete()) {
                        $return = ['success' => false, 'data' => [], 'message' => 'VIEWER_REMOVE_FAIL_TEXT'];
                    }
                }
            }
        }
        end:
        if ($return['success'] == true && isset($user)) {
            $return['$apiResults'] = NotificationServiceHelper::__addNotification(['uuid' => $object_uuid], HistoryModel::TYPE_OTHERS, HistoryModel::HISTORY_REMOVE_VIEWER, [
                'target_user' => $user
            ]);

            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('object_uuid', $object_uuid);
            $this->dispatcher->setParam('target_user_uuid', $user->getUuid());
            $this->dispatcher->setParam('target_user', $user);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $action
     * @param string $controller_name
     */
    public function checkAcl($action, $controller_name = '')
    {
        return parent::checkAcl($action, AclHelper::CONTROLLER_DATA_MEMBER); // TODO: Change the autogenerated stub
    }

    /**
     * @param $task_uuid
     */
    public function getHrOwnerAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];

        $object_uuid = Helpers::__getRequestValue('uuid');
        $company_id = Helpers::__getRequestValue('company_id');

        if ($object_uuid != '') {
            $objectMap = RelodayObjectMapHelper::__getObjectWithCache($object_uuid);
            if ($objectMap) {
                $ownerProfile = DataUserMember::getDataOwner($object_uuid, $company_id);
                if ($ownerProfile) {
                    $item = $ownerProfile->toArray();
                    $item['avatar'] = $ownerProfile->getAvatar();
                    $return = [
                        'success' => true,
                        'data' => $item,
                    ];
                } else {
                    $return = [
                        'success' => false,
                        'message' => 'OWNER_NOT_FOUND_TEXT'
                    ];
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $task_uuid
     */
    public function removeOwnerAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl('change_owner', 'member');
        $object_uuid = Helpers::__getRequestValue('uuid');
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($object_uuid != '') {
            $owner = DataUserMember::getDataOwner($object_uuid);
            if ($owner) {
                $this->dispatcher->setParam('target_user_uuid', $owner->getUuid());
                $this->dispatcher->setParam('target_user', $owner);
                $resultDelete = DataUserMember::__deleteMemberFromUuid($object_uuid, $owner, DataUserMember::MEMBER_TYPE_OWNER);
                if ($resultDelete['success']) {
                    $return = [
                        'success' => true,
                        'message' => 'OWNER_REMOVED_SUCCESS_TEXT'
                    ];
                } else {
                    $return = [
                        'success' => false,
                        'message' => 'CAN_NOT_PERFORM_THIS_ACTION'
                    ];
                }
            } else {
                $return = [
                    'success' => true,
                    'message' => 'OWNER_REMOVED_SUCCESS_TEXT'
                ];
            }
        }

        if($return['success']){
            //Check User Preference setting (is_sync_owner)
            if(ModuleModel::$user_profile->getIsSyncOwner() == ModelHelper::YES){
                //Service
                $relocationServiceCompany = RelocationServiceCompany::findFirstByUuid($object_uuid);
                if($relocationServiceCompany){
                    $return['$apiQueueResults'] =  $relocationServiceCompany->startRemoveTaskOwner();
                    $return['message'] = 'CHECK_BULK_CHANGE_SERVICE_TASKS_OWNER_TEXT_SUCCESS_AND_WAIT_TEXT';
                }
                //Relocation
            }

            $this->dispatcher->setParam('return', $return);
            $this->dispatcher->setParam('object_uuid', $object_uuid);

        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
