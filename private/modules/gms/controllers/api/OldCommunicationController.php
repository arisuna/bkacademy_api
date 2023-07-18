<?php

namespace Reloday\Gms\Controllers\API;

use Aws\Exception\AwsException;
use Reloday\Application\Lib\DynamoHelper;
use Reloday\Application\Lib\RelodayBatchModel;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Application\Validation\OldMainCommunicationTopicValidation;
use \Reloday\Gms\Controllers\ModuleApiController;

use Reloday\Application\Lib\RelodayQueue;
use \Reloday\Gms\Models\OldCommunicationTopic;
use Reloday\Gms\Models\OldCommunicationTopicContact;
use \Reloday\Gms\Models\OldCommunicationTopicFollower;
use \Reloday\Application\Lib\RelodayCommunicationTopicHelper;
use Reloday\Gms\Models\OldCommunicationTopicRead;
use Reloday\Gms\Models\Contact;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\Task;
use \Phalcon\Security;
use \Phalcon\Security\Random;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class OldCommunicationController extends BaseController
{
    /**
     * create Message
     * @return mixed
     */
    public function createMessageAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('create','communication');

        $dataArray = Helpers::__getRequestValuesArray();


        $validation = new OldMainCommunicationTopicValidation();
        $messagesValidation = $validation->validate($dataArray);

        if ($messagesValidation->count() > 0) {
            $return = [
                'success' => false,
                'message' => $messagesValidation->current()->getMessage()
            ];
            goto end_of_function;
        }


        $this->db->begin();

        /****************** followers ***************/
        $follower_uuids = array();
        $followers = Helpers::__getRequestValue('followers');
        if (count($followers) > 0) {
            foreach ($followers as $follower) {
                $follower = (array)$follower;
                $follower_uuids[] = $follower['member_uuid'];
            }
        }
        $follower_uuids[] = ModuleModel::$user_profile->getUuid();
        /****************** basic data ***************/
        $recipient_email = Helpers::__coalesce(Helpers::__getRequestValue('recipient_email'), Helpers::__getRequestValue('to'));
        $recipientEmailArrayList = Helpers::__parListEmailFromInput($recipient_email);
        $service_company_id = Helpers::__getRequestValue('service_company_id');
        $subject = Helpers::__getRequestValue('subject');
        $content = Helpers::__getRequestValue('content');
        //$cc = Helpers::__getRequestValue('cc');
        //$ccArrayList = Helpers::__parListEmailFromInput($cc);
        $service = Helpers::__getRequestValue('service');
        $contacts = Helpers::__getRequestValue('contacts');
        $employee_uuid = Helpers::__getRequestValue('employee_uuid');
        $assignee = Helpers::__getRequestValue('assignee');
        if ($employee_uuid == null && $assignee && isset($assignee->uuid)) {
            $employee_uuid = $assignee->uuid;
        }
        if ($service_company_id == null && $service && isset($service->id)) {
            $service_company_id = $service->id;
        }
        /*** task and communication ****/
        $task_uuid = Helpers::__getRequestValue('task_uuid');
        $communication_uuid = Helpers::__getRequestValue('communication_uuid');
        /*********** include assignee ******/
        $is_flagged = Helpers::__getRequestValue('is_flagged');
        $is_flagged = (is_bool($is_flagged) && $is_flagged == true) || (is_numeric($is_flagged) && $is_flagged == OldCommunicationTopic::IS_FLAGGED_YES) ? true : false;

        /*********** include assignee ******/
        $include_assignee = Helpers::__getRequestValue('include_assignee');
        $include_assignee = (is_bool($include_assignee) && $include_assignee == true) || (is_numeric($include_assignee) && $include_assignee == 1) ? true : false;

        /*********** attachments ******/
        $itemAttachments = Helpers::__getRequestValue('items');
        if ($itemAttachments == null) {
            $itemAttachments = Helpers::__getRequestValue('attachments');
        }
        /****************** array data ***************/
        $dataMainTopic = [
            'company_id' => ModuleModel::$company->getId(),
            'sender_user_profile_id' => ModuleModel::$user_profile->getId(),
            'relocation_id' => isset($dataArray['relocation_id']) ? $dataArray['relocation_id'] : '',
            'relocation_service_company_id' => isset($dataArray['relocation_service_company_id']) && is_numeric($dataArray['relocation_service_company_id']) ? $dataArray['relocation_service_company_id'] : '',
            'service_company_id' => $service_company_id ? $service_company_id : '',
            'task_uuid' => isset($dataArray['task_uuid']) ? $dataArray['task_uuid'] : '',
            'recipient_email' => $recipientEmailArrayList ? $recipientEmailArrayList : '',
            'subject' => isset($dataArray['subject']) ? $dataArray['subject'] : '',
            'follower_uuids' => $follower_uuids,
            'is_flagged' => $is_flagged,
            'have_attachment' => count($itemAttachments) > 0 ? true : false,
        ];

        if ($communication_uuid != '' && Helpers::__isValidUuid($communication_uuid)) {
            $communicationTopic = OldCommunicationTopic::findFirstByUuidCache($communication_uuid);
            if ($communicationTopic && $communicationTopic->belongsToGms()) {
                $dataMainTopic['communication_topic_id'] = $communicationTopic->getId();
                $dataMainTopic['is_replied'] = OldCommunicationTopic::IS_REPLIED_YES;
            } else {
                $dataMainTopic['is_replied'] = OldCommunicationTopic::IS_REPLIED_NO;
            }
        } else {
            $dataMainTopic['is_replied'] = OldCommunicationTopic::IS_REPLIED_NO;
        }

        if ($employee_uuid != '' && Helpers::__isValidUuid($employee_uuid)) {
            $employee = Employee::findFirstByUuidCache($employee_uuid);
            if ($employee && $employee->belongsToGms()) {
                $dataMainTopic['employee_id'] = $employee->getId();
                $dataMainTopic['employee_company_id'] = $employee->getCompanyId();
            }
        }

        //check task
        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid)) {
            $task = Task::findFirstByUuid($task_uuid);
            if ($task && $task->belongsToGms()) {
                $dataMainTopic['task_uuid'] = $task->getId();
                $dataMainTopic['relocation_id'] = $task->getRelocationId();
                $dataMainTopic['relocation_service_company_id'] = $task->getRelocationServiceCompanyId();
                if ($task->getRelocationServiceCompanyId() > 0 && $task->getMainRelocationServiceCompany()) {
                    $dataMainTopic['service_company_id'] = $task->getMainRelocationServiceCompany()->getServiceCompany() ? $task->getMainRelocationServiceCompany()->getServiceCompany()->getId() : null;
                }

                $employee = $task->getEmployee();
                if ($employee && $employee->belongsToGms()) {
                    $dataMainTopic['employee_id'] = $employee->getId();
                    $dataMainTopic['employee_company_id'] = $employee->getCompanyId();
                }
            }
        }
        //$dataMainTopic['communication_topic_id'] = null;
        $dataMainTopic['from_name'] = ModuleModel::$user_profile->getFirstname() . " " . ModuleModel::$user_profile->getLastname();
        $dataMainTopic['from_email'] = ModuleModel::$user_profile->getWorkemail();
        $dataMainTopic['sender_user_profile_id'] = ModuleModel::$user_profile->getId();

        /*** create main topic ***/
        $newMainTopic = new OldCommunicationTopic();
        $newMainTopic = $newMainTopic->__create($dataMainTopic);

        if ($newMainTopic instanceof OldCommunicationTopic) {
            $itemTopicUuid = $newMainTopic->getUuid();
            if (count($itemAttachments) > 0) {
                foreach ($itemAttachments as $itemAttachment) {
                    $resultAttachment = MediaAttachment::__create_attachment_from_uuid($itemTopicUuid, $itemAttachment);
                    if ($resultAttachment['success'] == false) {
                        $this->db->rollback();
                        $return = $resultAttachment;
                        goto end_of_function;
                    }
                }
            }

            //TODO add read for this user
            $newRead = new OldCommunicationTopicRead();
            $newRead->setData([
                'topic_id' => $newMainTopic->getId(),
                'main_topic_id' => $newMainTopic->getMainTopicId(),
                'user_profile_id' => ModuleModel::$user_profile->getId(),
            ]);
            $restNewRead = $newRead->__quickCreate();
            if ($restNewRead['success'] == false) {
                $this->db->rollback();
                $return = $restNewRead;
                goto end_of_function;
            }

            if (count($follower_uuids) > 0) {
                $userProfiles = UserProfile::find([
                    'columns' => 'workemail, uuid, id',
                    'conditions' => 'uuid IN ({uuids:array})',
                    'bind' => [
                        'uuids' => $follower_uuids
                    ]
                ]);
                if (count($userProfiles) > 0) {
                    foreach ($userProfiles as $userProfile) {
                        $follower = new OldCommunicationTopicFollower();
                        $resultInsertFollower = $follower->__create([
                                'communication_topic_id' => $newMainTopic->getId(), 'user_profile_id' => $userProfile['id']]
                        );
                        if (!$resultInsertFollower instanceof OldCommunicationTopicFollower) {
                            $this->db->rollback();
                            $return = $resultInsertFollower;
                            goto end_of_function;
                        }
                    }
                }
            }

            /** set contacts */
            if (is_array($contacts) && count($contacts) > 0) {
                $resultSetContacts = $newMainTopic->setContacts($contacts);
                if ($resultSetContacts['success'] == false) {
                    $this->db->rollback();
                    $return = $resultSetContacts;
                    goto end_of_function;
                }
            }

            /***** add to queue ****/
            $newMainTopic->setContent($content);
            $resultAddQueue = $newMainTopic->sendQueueToSQS();

            if ($resultAddQueue['success'] == false) {
                $this->db->rollback();
                $return = [
                    'detail' => $resultAddQueue['detail'],
                    'success' => false,
                    'message' => 'MESSAGE_ADDED_FAIL_TEXT',
                ];
                goto end_of_function;
            }
            if (count($follower_uuids) > 0) {
                $resultAddPusher = OldCommunicationTopic::sendToCountMessageQueue($follower_uuids);
            }
            //move file to S3
            //send content to S3
            $fileName = OldCommunicationTopic::FOLDER_S3 . "/" . $itemTopicUuid . ".json";
            $resultUpload = RelodayS3Helper::__uploadSingleFile($fileName, json_encode([
                'subject' => $subject,
                'content' => $content,
            ]));


            if ($resultUpload['success'] == false) {
                $this->db->rollback();
                $return = [
                    'detail' => $resultUpload['detail'],
                    'success' => false,
                    'message' => 'MESSAGE_ADDED_FAIL_TEXT',
                ];
                goto end_of_function;
            }

            $return = [
                'resultSQS' => $resultAddQueue,
                'resultS3' => $resultUpload,
                'have_attachment' => count($itemAttachments) > 0 ? true : false,
                'success_attachment' => isset($resultAttachment) ? $resultAttachment['success'] : false,
                'attachment_result' => isset($resultAttachment) && $resultAttachment ? $resultAttachment : null,
                'success' => true,
                'message' => 'MESSAGE_ADDED_SUCCESS_TEXT',
            ];

            $this->db->commit();
        } else {
            $return = $newMainTopic;
            if (is_array($return['detail'])) {
                $return['message'] = reset($return['detail']);
            }
            $this->db->rollback();
        }

        end_of_function:
        $return['dataMainTopic'] = isset($dataMainTopic) ? $dataMainTopic : null;
        $return['dataArray'] = $dataArray;
        $return['attachments'] = isset($itemAttachments) ? $itemAttachments : null;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * by
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);
        $this->checkAcl('index','communication');

        $filters = Helpers::__getRequestValue('filters');
        $page = Helpers::__getRequestValue('page');
        /****** assigness *****/
        $assignees = isset($filters->assignees) ? $filters->assignees : [];
        $assignees_ids = [];
        if (count($assignees) > 0) {
            foreach ($assignees as $assignee) {
                //$assignees_uuids[] = ['S' => $assignee->uuid];
                $assignees_ids[] = $assignee->id;
            }
        }
        /****** services *****/
        $services = isset($filters->services) ? $filters->services : [];
        $service_ids = [];
        if (count($services) > 0) {
            foreach ($services as $service) {
                $service_ids[] = $service->id;
            }
        }
        /****** followers *****/
        $followers = isset($filters->followers) ? $filters->followers : [];
        $followers_ids = [];
        if (count($followers) > 0) {
            foreach ($followers as $follower) {
                $followers_ids[] = $follower->id;
            }
        }

        $options = array();
        $options['sender_user_profile_id'] = ModuleModel::$user_profile->getId();
        $options['followers_ids'] = $followers_ids;
        $options['service_ids'] = $service_ids;
        $options['employee_ids'] = $assignees_ids;
        $options['user_profile_id'] = ModuleModel::$user_profile->getId();// CURRENT USER AS FOLLOWER OR MMEBER

        $options['page'] = $page;

        $itemArray = [];
        $resultItems = OldCommunicationTopic::__findWithFilter($options);

        if ($resultItems['success'] == true) {

            $return = [
                'options' => $options,
                'success' => true,
                'data' => $resultItems['data'],
                'next' => $resultItems['next'],
                'last' => $resultItems['last'],
                'before' => $resultItems['before'],
                'current' => $resultItems['current'],
                'totalItems' => $resultItems['total_items'],
                'totalPages' => $resultItems['total_pages'],
            ];
        } else {
            $return = $resultItems;
        }

        $return['dbReadConnection'] = $this->di->getShared((new OldCommunicationTopic())->getReadConnectionService())->getDescriptor();
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function getRepliesAction($uuid)
    {

    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getContentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index','communication');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $mainTopic = OldCommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {

                $mainTopicData = $mainTopic->toArray();
                $mainTopicData['assignee'] = [
                    'uuid' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getUuid() : '',
                    'email' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'workemail' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'firstname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getFirstname() : '',
                    'lastname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getLastname() : '',
                ];
                $mainTopicData['recipient_email_list'] = $mainTopic->parseRecipientEmail();

                $mainTopicData['content'] = $mainTopic->getContentFromS3();

                $mainTopicData['owner'] = [
                    'id' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getId() : '',
                    'uuid' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getUuid() : '',
                    'email' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getWorkemail() : '',
                    'workemail' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getWorkemail() : '',
                    'firstname' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getFirstname() : '',
                    'lastname' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getLastname() : '',
                ];

                $mainTopicData['attachments'] = MediaAttachment::__get_attachments_from_uuid($mainTopic->getUuid());
                $mainTopicData['contacts'] = $mainTopic->getContactsProfiles();

                if ($mainTopic->getIncludeAssignee() == OldCommunicationTopic::INCLUDE_ASSIGNEE_YES) {
                    $mainTopicData['contacts'][] = $mainTopicData['assignee'];
                }
                $mainTopicData['last_updated_time'] = strtotime($mainTopic->getUpdatedAt());
                $return = [
                    'success' => true,
                    'data' => $mainTopicData,
                ];

            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }

            end_of_function:

            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }

    /**
     *
     */
    public function getMessagesAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAcl('index','communication');

        $uuid = Helpers::__getRequestValue('uuid');
        $page = Helpers::__getRequestValue('page');
        $lastTopicId = Helpers::__getRequestValue('lastTopicId');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $mainTopic = OldCommunicationTopic::findFirstByUuid($uuid);
            if ($mainTopic && $mainTopic->belongsToGms()) {

                $options = [
                    'communication_topic_id' => $mainTopic->getId(),
                    'page' => $page,
                ];

                if (is_numeric($lastTopicId) && $lastTopicId > 0) {
                    $options['lastTopicId'] = $lastTopicId;
                }

                $options['load_content'] = true;

                $resultMessage = OldCommunicationTopic::__findWithFilter($options);

                if ($resultMessage['success'] == true) {

                    $return = [
                        'success' => true,
                        'data' => $resultMessage['data'],
                        'next' => $resultMessage['next'],
                        'last' => $resultMessage['last'],
                        'before' => $resultMessage['before'],
                        'current' => $resultMessage['current'],
                        'total_pages' => $resultMessage['total_pages'],
                        'total_items' => $resultMessage['total_items'],
                    ];
                } else {
                    $return = $resultMessage;
                }
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }

            end_of_function:
            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }


    /**
     * @param $uuid
     */
    public function getFollowersAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index','communication');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $mainTopic = OldCommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {

                $followers = $mainTopic->getCommunicationTopicFollowerProfiles();
                $follower_uuids = [];
                foreach ($followers as $follower) {
                    $follower_uuids[$follower['uuid']] = $follower['id'];
                }

                $workers = UserProfile::getGmsWorkers();
                $users = [];
                foreach ($workers as $key => $worker) {
                    $users[$key] = $worker->toArray();
                    if ($worker->getId() != $mainTopic->getSenderUserProfileId()) {
                        if (isset($follower_uuids[$worker->getUuid()])) {
                            $users[$key]['selected'] = true;
                        } else {
                            $users[$key]['selected'] = false;
                        }
                    }
                }

                $return = [
                    'success' => true,
                    'data' => $users,
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }

            end_of_function:
            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }

    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function addFollowerAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('edit','communication');


        $data = $this->request->getJsonRawBody();
        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('topic_uuid');
        $member_uuid = Helpers::__getRequestValue('member_uuid');
        $selected = Helpers::__getRequestValue('selected');

        if (!($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid) && $member_uuid != '' && Helpers::__isValidUuid($member_uuid))) {
            goto end_of_function;
        }
        $mainTopic = OldCommunicationTopic::findFirstByUuidCache($topic_uuid);

        if (!($mainTopic && $mainTopic->belongsToGms())) {
            goto end_of_function;
        }

        $user = UserProfile::findFirstByUuidCache($member_uuid);

        if ($user && ($user->belongsToGms() || $user->manageByGms())) {
            $dataFollower = OldCommunicationTopicFollower::findFirst([
                'conditions' => 'communication_topic_id = :communication_topic_id: AND user_profile_id = :user_profile_id:',
                'bind' => [
                    'communication_topic_id' => $mainTopic->getId(),
                    'user_profile_id' => $user->getId(),
                ]
            ]);
            if (!$dataFollower) {
                $this->db->begin();
                $dataFollower = new OldCommunicationTopicFollower();
                $dataFollowerResult = $dataFollower->__create([
                    'communication_topic_id' => $mainTopic->getId(),
                    'user_profile_id' => $user->getId()
                ]);

                if (!($dataFollowerResult instanceof OldCommunicationTopicFollower)) {
                    $this->db->rollback();
                    $return = ['success' => false, 'message' => 'FOLLOWER_ADD_FAIL_TEXT'];
                    goto end_of_function;
                }


                //update CommunicationTopicFollower
                $follower_uuids = [];
                $followers = $mainTopic->getCommunicationTopicFollowerProfiles();
                if (count($followers) == 0) {
                    $this->db->commit();
                    $return = ['success' => true, 'message' => 'FOLLOWER_ADD_SUCCESS_TEXT', 'detail' => '0 followers'];
                    goto end_of_function;
                }

                foreach ($followers as $follower) {
                    $follower_uuids[] = $follower['uuid'];
                }
                $mainTopic->setData(['follower_uuids' => $follower_uuids]);
                $resultMainTopic = $mainTopic->__quickUpdate();
                if ($resultMainTopic['success'] == false) {
                    $this->db->rollback();
                    $return = ['success' => false, 'message' => 'FOLLOWER_ADD_FAIL_TEXT'];
                    goto end_of_function;
                }

                $this->db->commit();
                $return = ['success' => true, 'message' => 'FOLLOWER_ADD_SUCCESS_TEXT', 'detail' => count($followers) . ' followers'];
                goto end_of_function;
            } else {
                $return = ['success' => true, 'data' => $user];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /***
     * @param $uuid
     */
    public function removeFollowerAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit','communication');


        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('topic_uuid');
        $member_uuid = Helpers::__getRequestValue('member_uuid');
        $selected = Helpers::__getRequestValue('selected');

        if (!($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid) && $member_uuid != '' && Helpers::__isValidUuid($member_uuid))) {
            goto end_of_function;
        }

        $mainTopic = OldCommunicationTopic::findFirstByUuidCache($topic_uuid);

        if (!($mainTopic && $mainTopic->belongsToGms())) {
            goto end_of_function;
        }

        if ($selected != false) {
            goto end_of_function;
        }

        $user = UserProfile::findFirstByUuidCache($member_uuid);
        if (!($user && ($user->belongsToGms() || $user->manageByGms()))) {
            goto end_of_function;
        }

        $dataFollower = OldCommunicationTopicFollower::findFirst([
            'conditions' => 'communication_topic_id = :communication_topic_id: AND user_profile_id = :user_profile_id:',
            'bind' => [
                'communication_topic_id' => $mainTopic->getId(),
                'user_profile_id' => $user->getId(),
            ]
        ]);
        if ($dataFollower) {
            $this->db->begin();
            $dataFollowerResult = $dataFollower->remove();

            if ($dataFollowerResult['success'] == true) {
                $follower_uuids = [];
                $followers = $mainTopic->getCommunicationTopicFollowerProfiles();
                if (count($followers) == 0) {
                    $this->db->commit();
                    $return = ['success' => true, 'message' => 'FOLLOWER_ADD_SUCCESS_TEXT', 'detail' => '0 followers'];
                    goto end_of_function;
                }
                foreach ($followers as $follower) {
                    $follower_uuids[] = $follower['uuid'];
                }
                $mainTopic->setData(['follower_uuids' => $follower_uuids]);
                $resultMainTopic = $mainTopic->__quickUpdate();
                if ($resultMainTopic['success'] == false) {
                    $this->db->rollback();
                    $return = ['success' => false, 'message' => 'FOLLOWER_ADD_FAIL_TEXT'];
                    goto end_of_function;
                }
                $this->db->commit();
                $return = ['success' => true, 'message' => 'FOLLOWER_REMOVE_SUCCESS_TEXT'];
            } else {
                $return = ['success' => false, 'message' => 'FOLLOWER_REMOVE_FAIL_TEXT'];
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function setReadMainAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('index','communication');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('topic_uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $itemTopic = OldCommunicationTopic::findFirstByUuid($topic_uuid);

            if ($itemTopic && $itemTopic->belongsToGms()) {
                $res = ($itemTopic->setReadMain());
                if ($res['success'] == true) {
                    $return = ['success' => true, 'message' => 'SUCCESS_TEXT'];
                } else {
                    $return = $res;
                }
            }


        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [setReadItemAction description]
     * @return [type] [description]
     */
    public function setReadItemAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('index','communication');


        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('topic_uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $itemTopic = OldCommunicationTopic::findFirstByUuid($topic_uuid);

            if ($itemTopic && $itemTopic->belongsToGms()) {
                $res = ($itemTopic->setReadItem());
                if ($res['success'] == true) {
                    $return = ['success' => true, 'message' => 'SUCCESS_TEXT'];
                } else {
                    $return = $res;
                }
            }


        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function setUnreadAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('index','communication');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('topic_uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $itemTopic = OldCommunicationTopic::findFirstByUuid($topic_uuid);

            if ($itemTopic && $itemTopic->belongsToGms()) {
                $res = $itemTopic->setUnread();

                if ($res['success'] == true) {
                    $return = ['success' => true, 'message' => 'SUCCESS_TEXT'];
                } else {
                    $return = $res;
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function setFlagAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('index','communication');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $itemTopic = OldCommunicationTopic::findFirstByUuid($topic_uuid);

            if ($itemTopic && $itemTopic->belongsToGms()) {
                $res = ($itemTopic->setFlag());
                if ($res['success'] == true) {
                    $return = ['success' => true, 'message' => 'SUCCESS_TEXT'];
                } else {
                    $return = $res;
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function setUnflagAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit','communication');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $itemTopic = OldCommunicationTopic::findFirstByUuid($topic_uuid);

            if ($itemTopic && $itemTopic->belongsToGms()) {
                $res = ($itemTopic->setUnflag());
                if ($res['success'] == true) {
                    $return = ['success' => true, 'message' => 'SUCCESS_TEXT'];
                } else {
                    $return = $res;
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function deleteMessageAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit','communication');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('uuid');

        if (ModuleModel::$user_profile->isAdminOrManager() == false) {
            $return = ['success' => false, 'data' => [], 'message' => 'PERMISSION_NOT_FOUND_TEXT'];
        } else {
            if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
                $itemTopic = OldCommunicationTopic::findFirstByUuid($topic_uuid);

                if ($itemTopic && $itemTopic->belongsToGms()) {
                    $usersProfiles = $itemTopic->getCommunicationTopicFollowerProfiles();
                    $res = ($itemTopic->remove());
                    if ($res['success'] == true) {

                        $userProfileUuids = [];
                        if (count($usersProfiles)) {
                            foreach ($usersProfiles as $userProfile) {
                                $userProfileUuids[] = $userProfile['uuid'];
                            }
                        }
                        OldCommunicationTopic::sendToCountMessageQueue($userProfileUuids);
                        $return = ['success' => true, 'message' => 'SUCCESS_TEXT', 'userProfileUuids' => $usersProfiles];
                    } else {
                        $return = $res;
                    }
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * count unread messages
     */
    public function countUnreadMessagesAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index','communication');

        $this->response->setJsonContent([
            'success' => true,
            'read' => [
                'threads' => OldCommunicationTopic::countReadThreads(),
                'replies' => OldCommunicationTopic::countReadReplies()
            ],
            'total' => [
                'threads' => OldCommunicationTopic::countTotalThread(),
                'replies' => OldCommunicationTopic::countTotalReplies()
            ],
            'data' => OldCommunicationTopic::countUnreadMessage()
        ]);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function checkLastUpdateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index','communication');

        $return = ['success' => true, 'data' => 0];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $lastUpdateTime = OldCommunicationTopic::findLastUpdate($uuid);
            $return = ['success' => true, 'data' => $lastUpdateTime];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function saveCommunicationContactsAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit','communication');

        $uuid = Helpers::__getRequestValue('uuid');
        $include_assignee = Helpers::__getRequestValue('include_assignee');
        $contacts = Helpers::__getRequestValue('contacts');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $mainTopic = OldCommunicationTopic::findFirstByUuid($uuid);
            if ($mainTopic && $mainTopic->belongsToGms()) {

                //$this->db->begin();
                if ($include_assignee == OldCommunicationTopic::INCLUDE_ASSIGNEE_YES || $include_assignee == OldCommunicationTopic::INCLUDE_ASSIGNEE_NO) {
                    $mainTopic->setIncludeAssignee($include_assignee);
                }

                $contactProfiles = $mainTopic->getContactsProfiles();
                if (count($contactProfiles) == 0) {
                    if ($mainTopic->getIncludeAssignee() == OldCommunicationTopic::INCLUDE_ASSIGNEE_NO) {
                        $return = ['success' => false, 'data' => [], 'message' => 'RECIPIENTS_REQUIRED_TEXT'];
                        goto end_of_function;
                    }
                }

                $resultSave = $mainTopic->__quickUpdate();
                if ($resultSave['success'] == false) {
                    $return = $resultSave;
                    goto end_of_function;
                }
                $return = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT'
                ];

            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }

            end_of_function:
            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getContactsAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAcl('index','communication');

        $uuid = Helpers::__getRequestValue('uuid');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $mainTopic = OldCommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {
                $contacts = $mainTopic->getContactsProfiles();

                $assignee = [
                    'uuid' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getUuid() : '',
                    'email' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'workemail' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'firstname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getFirstname() : '',
                    'lastname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getLastname() : '',
                ];

                if ($mainTopic->getIncludeAssignee() == OldCommunicationTopic::INCLUDE_ASSIGNEE_YES) {
                    $contacts[] = $assignee;
                }
                $return = [
                    'success' => true,
                    'data' => $contacts,
                ];

            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }

            end_of_function:
            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }


    /**
     * [add new contact to communication]
     * @return [type] [description]
     */
    public function addContactAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('edit','communication');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $contact = Helpers::__getRequestValue('contact');

        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT', 'detail' => 'UUID_NOT_FOUND'];
            goto end_of_function;
        }

        if (!$contact || !isset(((array)$contact)['email'])) {
            $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT', 'detail' => 'CONTACT_NOT_FOUND'];
            goto end_of_function;
        }
        $mainTopic = OldCommunicationTopic::findFirstByUuidCache($uuid);

        if (!($mainTopic && $mainTopic->belongsToGms())) {
            $return = ['success' => false, 'data' => [], 'message' => 'COMMUNICATION_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $contactObject = false;
        $contact = (array)$contact;
        if (isset($contact['id']) && is_numeric($contact['id'])) {
            $contactObject = Contact::findFirstById($contact['id']);
        }

        if (!$contactObject && isset($contact['uuid']) && is_string($contact['uuid']) && Helpers::__isValidUuid($contact['uuid'])) {
            $contactObject = Contact::findFirstByUuid($contact['uuid']);
        }

        if (!$contactObject && isset($contact['email']) && is_string($contact['email']) && Helpers::__isEmail($contact['email'])) {
            $contactObject = Contact::findFirstByEmail($contact['email']);
        }


        if (isset($contactObject) && $contactObject && $contactObject->belongsToGms() && $contactObject->getEmail() == $contact['email']) {
            $relation = new OldCommunicationTopicContact();
            $relation->setCommunicationTopicId($mainTopic->getId());
            $relation->setContactId($contactObject->getId());
            $return = $relation->__quickCreate();
            //$return = ['success' => true];
            goto end_of_function;
        }

        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * [add new contact to communication]
     * @return [type] [description]
     */
    public function deleteContactAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('edit','communication');

        $return = ['success' => false, 'data' => [], 'message' => 'CONTACT_NOT_FOUNT_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $contact = Helpers::__getRequestValue('contact');

        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            $return = ['success' => false, 'data' => [], 'message' => 'COMMUNICATION_NOT_FOUNT_TEXT'];
            goto end_of_function;
        }

        if (!$contact || !isset(((array)$contact)['email'])) {
            goto end_of_function;
        }


        $mainTopic = OldCommunicationTopic::findFirstByUuidCache($uuid);
        if (!($mainTopic && $mainTopic->belongsToGms())) {
            $return = ['success' => false, 'data' => [], 'message' => 'COMMUNICATION_NOT_FOUNT_TEXT'];
            goto end_of_function;
        }

        $contactProfiles = $mainTopic->getContactsProfiles();
        if (count($contactProfiles) == 1) {
            if ($mainTopic->getIncludeAssignee() == OldCommunicationTopic::INCLUDE_ASSIGNEE_NO) {
                $return = ['success' => false, 'data' => [], 'message' => 'RECIPIENTS_REQUIRED_TEXT'];
                goto end_of_function;
            }
        }

        if (count($contactProfiles) == 0) {
            if ($mainTopic->getEmployee() && $mainTopic->getEmployee()->getWorkemail() == ((array)$contact)['email']
                && $mainTopic->getIncludeAssignee() == OldCommunicationTopic::INCLUDE_ASSIGNEE_YES) {
                $return = ['success' => false, 'data' => [], 'message' => 'RECIPIENTS_REQUIRED_TEXT'];
                goto end_of_function;
            }
        }


        if ($mainTopic->getIncludeAssignee() == OldCommunicationTopic::INCLUDE_ASSIGNEE_YES
            && $mainTopic->getEmployee()->getWorkemail() == ((array)$contact)['email']) {
            $mainTopic->setIncludeAssignee(OldCommunicationTopic::INCLUDE_ASSIGNEE_NO);
            $return = $mainTopic->__quickUpdate();
            $return['data'] = $mainTopic;
            goto end_of_function;
        }


        $contact = (array)$contact;
        if (isset($contact['id']) && is_numeric($contact['id'])) {
            $contactObject = Contact::findFirstById($contact['id']);
        }

        if (!$contactObject && isset($contact['uuid']) && is_string($contact['uuid']) && Helpers::__isValidUuid($contact['uuid'])) {
            $contactObject = Contact::findFirstByUuid($contact['uuid']);
        }

        if (!$contactObject && isset($contact['email']) && is_string($contact['email']) && Helpers::__isEmail($contact['email'])) {
            $contactObject = Contact::findFirstByEmailCompany($contact['email']);
        }

        if (isset($contactObject) && $contactObject && $contactObject->belongsToGms() && $contactObject->getEmail() == $contact['email']) {
            $relation = OldCommunicationTopicContact::findFirst([
                'conditions' => 'communication_topic_id = :communication_topic_id: AND contact_id = :contact_id:',
                'bind' => [
                    'communication_topic_id' => $mainTopic->getId(),
                    'contact_id' => $contactObject->getId()
                ]
            ]);
            if ($relation) {
                $return = $relation->__quickRemove();
                goto end_of_function;
            } else {
                $return = ['success' => true, 'message' => 'DATA_DELETE_SUCCESS_TEXT'];
                goto end_of_function;
            }
        } else {
            $return = ['success' => false, 'data' => $contactObject, 'message' => 'CONTACT_NOT_FOUND_TEXT'];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
