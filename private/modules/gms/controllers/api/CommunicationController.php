<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security\Random;
use Reloday\Application\Lib\GoogleHelper;
use Reloday\Application\Lib\Helpers;

use Reloday\Application\Lib\ImapHelper;
use Reloday\Application\Lib\OutlookHelper;
use Reloday\Application\Lib\RelodayQueue;
use \Reloday\Gms\Models\CommunicationTopic;
use Reloday\Gms\Models\CommunicationTopicMessage;
use Reloday\Gms\Models\Contact;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\OldCommunicationTopic;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\UserCommunicationEmail;
use Reloday\Gms\Models\UserProfile;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Help\AutofillEmailTemplateHelper;

/**
 * Concrete implementation of Hr module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CommunicationController extends BaseController
{
    /**
     * Create Message
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createMessageAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $this->db->begin();
        $attachments = Helpers::__getRequestValue("items");
        $from = Helpers::__getRequestValue("from");
        $is_flagged = intval(Helpers::__getRequestValue("is_flagged"));
        $email = UserCommunicationEmail::findFirst(["conditions" => "user_profile_id = :user_profile_id: AND status <> :status_archived: AND imap_email = :email:",
            "bind" => [
                'user_profile_id' => ModuleModel::$user_profile->getId(),
                'status_archived' => UserCommunicationEmail::STATUS_ARCHIVED,
                'email' => $from
            ]]);
        if (!$email instanceof UserCommunicationEmail) {
            $this->db->rollback();
            if($from != ''){
                $return = ["success" => false, "message" => "EMAIL_SENDER_NOT_AVAILABLE_TEXT", "detail" => $email];
            } else {
                $return = ["success" => false, "message" => "FROM_EMAIL_REQUIRED_TEXT", "detail" => $email];
            }
            goto end_of_function;
        }
        $communication_topic_id = Helpers::__getRequestValue("communication_topic_id");
        if ($communication_topic_id > 0) {
            $topic = CommunicationTopic::findFirstById($communication_topic_id);
            if (count($attachments) > 0) {
                $topic->setHasAttachment(Helpers::YES);
            } else {
                $topic->setHasAttachment(Helpers::NO);
            }
            $topic->setLastSent(time());
            if($topic->getNumberOfDraft() == 1){
                $topic->setHasDraft(Helpers::NO);
                $topic->setIsDraft(Helpers::NO);
            }
            if($topic->getNumberOfMessage() == 1 && $topic->getNumberOfDraft() == 1){
                $topic->setSubject(Helpers::__getRequestValue("subject"));
                $topic->setFirstSenderUserCommunicationEmailId($email->getId());
                $topic->setFirstSenderEmail($from);
                $topic->setFirstSenderName($email->getName());
            }
            $resultCreateTopic = $topic->__quickUpdate();
            if (!$resultCreateTopic["success"]) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateTopic];
                goto end_of_function;
            }
        } else {
            $topic = new CommunicationTopic();
            $topic->setCompanyId(intval(ModuleModel::$company->getId()));
            $topic->setOwnerUserProfileId(intval(ModuleModel::$user_profile->getId()));
            $assignee = Helpers::__getRequestValue("assignee");
            if ($assignee != null && property_exists($assignee, 'id')) {
                $topic->setEmployeeId($assignee->id);
            }
            $relocation = Helpers::__getRequestValue("relocation");
            if ($relocation != null && property_exists($relocation, 'id')) {
                $topic->setRelocationId($relocation->id);
            }
            $assignment = Helpers::__getRequestValue("assignment");
            if ($assignment != null && property_exists($assignment, 'id')) {
                $topic->setAssignmentId($assignment->id);
            }
            $topic->setRelocationServiceCompanyId(Helpers::__getRequestValue("relocation_service_company_id"));
            $relocation_service_company = RelocationServiceCompany::findFirstById($topic->getRelocationServiceCompanyId());
            if($relocation_service_company){
                $topic->setServiceCompanyId($relocation_service_company->getServiceCompanyId());
            } else {
                $topic->setServiceCompanyId(intval(Helpers::__getRequestValue("service_company_id")));
            }
            $topic->setTaskUuid(Helpers::__getRequestValue("task_uuid"));
            $topic->setSubject(Helpers::__getRequestValue("object"));
            $topic->setStatus(CommunicationTopic::STATUS_ACTIVE);
            $topic->setFirstSenderUserCommunicationEmailId($email->getId());
            $topic->setFirstSenderEmail($from);
            $topic->setFirstSenderName($email->getName());
            $topic->setLastSent(time());

            if (count($attachments) > 0) {
                $topic->setHasAttachment(Helpers::YES);
            } else {
                $topic->setHasAttachment(Helpers::NO);
            }
            $flags = [];
            if ($is_flagged == 1) {
                $flags = [intval(ModuleModel::$user_profile->getId())];
            }
            $topic->setFlags(json_encode($flags));
            $topic->setData();
            /****************** followers ***************/
            $follower_ids = array();
            $followers = Helpers::__getRequestValue('followers');
            if (count($followers) > 0) {
                foreach ($followers as $follower) {
                    if(!in_array( $follower->id, $follower_ids)) {
                        $follower_ids[] = $follower->id;
                    }
                }
            }
            $topic->setFollowers(json_encode($follower_ids));
            $resultCreateTopic = $topic->__quickCreate();
            if (!$resultCreateTopic["success"]) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateTopic];
                goto end_of_function;
            }
        }


        // create message of topic
        $uuid = Helpers::__getRequestValue("uuid");
        $is_update_message = false;
        if(Helpers::__isValidUuid($uuid)){
            $message = CommunicationTopicMessage::findFirstByUuid($uuid);
            $is_update_message = true;
        } else {
            $message = new CommunicationTopicMessage();
            $random = new Random();
            $uuid = $random->uuid();
            $message->setUuid($uuid);
            $message->setCommunicationTopicId($topic->getId());
        }
        $message->setReplyTo(Helpers::__getRequestValue("reply_to"));
        if ($communication_topic_id > 0) {
            $message->setPosition(count($topic->getTopicMessages()) + 1);
        } else {
            $message->setPosition(1);
        }
        $message->setSenderEmail($from);
        $message->setSenderName(ModuleModel::$user_profile->getFirstName() . ' ' . ModuleModel::$user_profile->getLastName());
        $message->setSenderUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $message->setSenderUserCommunicationEmailId($email->getId());
        $message->setStatus(CommunicationTopicMessage::STATUS_PENDING);
        $message->setIsSendDirection(Helpers::YES);
        if (count($attachments) > 0) {
            $message->setHasAttachment(Helpers::YES);
        } else {
            $message->setHasAttachment(Helpers::NO);
        }


        $to_array = Helpers::__getRequestValue("to");
        $cc_array = Helpers::__getRequestValue("cc");
        $bcc_array = Helpers::__getRequestValue("cci");
        $to = [];
        $cc = [];
        $bcc = [];
        if (is_array($to_array) && count($to_array) > 0) {
            foreach ($to_array as $item) {
                $item = str_replace(' ', '', $item);
                $to[] = $item;
            }
        } else {
            $this->db->rollback();
            $return = [
                'detail' => Helpers::__getRequestValue("to"),
                'success' => false,
                'message' => 'RECIPIENT_EMPTY_TEXT',
            ];
            goto end_of_function;
        }
        if (is_array($cc_array) && count($cc_array) > 0) {
            foreach ($cc_array as $item) {
                $item = str_replace(' ', '', $item);
                $cc[] = $item;
            }
        }
        if (is_array($bcc_array) && count($bcc_array) > 0) {
            foreach ($bcc_array as $item) {
                $item = str_replace(' ', '', $item);
                $bcc[] = $item;
            }
        }
        $recipients = [];
        if (count($to) > 0) {
            $recipients["to"] = $to_array;
        }
        if (count($cc) > 0) {
            $recipients["cc"] = $cc_array;
        }
        if (count($bcc) > 0) {
            $recipients["bcc"] = $bcc_array;
        }
        $message->setRecipients(json_encode($recipients));
        if($is_update_message){
            $resultCreateMessage = $message->__quickUpdate();
        } else {
            $resultCreateMessage = $message->__quickCreate();
        }
        if (!$resultCreateMessage["success"]) {
            $this->db->rollback();
            $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "topic" => $topic, "data_message" => $message];
            goto end_of_function;
        }
        $resultUpload = $message->transferContentToS3(rawurldecode(base64_decode(Helpers::__getRequestValue("content"))));
        if ($resultUpload['success'] == false) {
            $this->db->rollback();
            $return = [
                'detail' => $resultUpload['detail'],
                'success' => false,
                'message' => 'MESSAGE_ADDED_FAIL_TEXT',
            ];
            goto end_of_function;
        }
        // create message recipient

        /***** attachment *******/
        if (count($attachments) > 0) {
            $not_attached_yet = [];
            foreach ($attachments as $attachment){
                if(property_exists($attachment, 'media_attachment_uuid') && $attachment->media_attachment_uuid != null && $attachment->media_attachment_uuid != ''){
                    // do nothing
                } else {
                    $not_attached_yet[] = $attachment;
                }
            }

            $resultAttachment = MediaAttachment::__createAttachments([
                'objectUuid' => $message->getUuid(),
                'fileList' => $not_attached_yet,
            ]);
            if(count($not_attached_yet) > 0) {
                if (!$resultAttachment["success"]) {
                    if ($resultAttachment['detail']['message'] == "MEDIA_ALREADY_ATTACHED_TEXT") {
                        // do nothing
                    } else {
                        $this->db->rollback();
                        $return = ["success" => false,
                            "message" => "DATA_CREATE_FAIL_TEXT",
                            "topic" => $topic,
                            "data_message" => $message,
                            "attachment" => $resultAttachment];
                        goto end_of_function;
                    }
                }
            }
        }
        /**** send email ****/
        $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_COMMUNICATION'));
        $returnQueue = $beanQueue->addQueue([
            'action' => "sendCommunicationEmail",
            'uuid' => $message->getUuid(),
            'params' => [
                'uuid' => $message->getUuid(),
            ]
        ]);
        $this->db->commit();
        $return = [
            "success" => true,
            "message" => "COMMUNICATION_SUCCESSFULLY_SENT_TEXT",
            "topic" => $topic,
            "data_message" => $message->toArray(),
            "returnQueue" => $returnQueue
        ];
        end_of_function:
        $return['email_type'] = $email ? $email->getType() : null;
        $return['destination'] = isset($to) ? $to : null;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Create Message
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createDraftMessageAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $this->db->begin();
        $attachments = Helpers::__getRequestValue("items");
        $from = Helpers::__getRequestValue("from");
        $is_flagged = intval(Helpers::__getRequestValue("is_flagged"));
        $email = UserCommunicationEmail::findFirst(["conditions" => "user_profile_id = :user_profile_id: AND status <> :status_archived: AND imap_email = :email:",
            "bind" => [
                'user_profile_id' => ModuleModel::$user_profile->getId(),
                'status_archived' => UserCommunicationEmail::STATUS_ARCHIVED,
                'email' => $from
            ]]);
//        if (!$email instanceof UserCommunicationEmail) {
//            $this->db->rollback();
//            $return = ["success" => false, "message" => "FROM_ADDRESS_NOT_EXIST_TEXT", "detail" => $email];
//            goto end_of_function;
//        }
        $communication_topic_id = Helpers::__getRequestValue("communication_topic_id");
        if ($communication_topic_id > 0) {
            $topic = CommunicationTopic::findFirstById($communication_topic_id);
            if (count($attachments) > 0) {
                $topic->setHasAttachment(Helpers::YES);
            } else {
                $topic->setHasAttachment(Helpers::NO);
            }
            $topic->setHasDraft(Helpers::YES);
            if($topic->getNumberOfMessage() == 1 && $topic->getNumberOfDraft() == 1){
                $topic->setSubject(Helpers::__getRequestValue("subject"));
                if($email instanceof  UserCommunicationEmail) {
                    $topic->setFirstSenderUserCommunicationEmailId($email->getId());
                    $topic->setFirstSenderEmail($from);
                    $topic->setFirstSenderName($email->getName());
                }
            }
            $resultCreateTopic = $topic->__quickUpdate();
            if (!$resultCreateTopic["success"]) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateTopic];
                goto end_of_function;
            }
        } else {
            $topic = new CommunicationTopic();
            $topic->setCompanyId(intval(ModuleModel::$company->getId()));
            $topic->setIsDraft(Helpers::YES);
            $topic->setOwnerUserProfileId(intval(ModuleModel::$user_profile->getId()));
            $assignee = Helpers::__getRequestValue("assignee");
            if ($assignee != null && property_exists($assignee, 'id')) {
                $topic->setEmployeeId($assignee->id);
            }
            $relocation = Helpers::__getRequestValue("relocation");
            if ($relocation != null && property_exists($relocation, 'id')) {
                $topic->setRelocationId($relocation->id);
            }
            $assignment = Helpers::__getRequestValue("assignment");
            if ($assignment != null && property_exists($assignment, 'id')) {
                $topic->setAssignmentId($assignment->id);
            }
            $topic->setRelocationServiceCompanyId(Helpers::__getRequestValue("relocation_service_company_id"));
            $relocation_service_company = RelocationServiceCompany::findFirstById($topic->getRelocationServiceCompanyId());
            if($relocation_service_company){
                $topic->setServiceCompanyId($relocation_service_company->getServiceCompanyId());
            } else {
                $topic->setServiceCompanyId(intval(Helpers::__getRequestValue("service_company_id")));
            }
            $topic->setTaskUuid(Helpers::__getRequestValue("task_uuid"));
            $topic->setSubject(Helpers::__getRequestValue("object"));
            $topic->setStatus(CommunicationTopic::STATUS_ACTIVE);
            if($email instanceof  UserCommunicationEmail) {
                $topic->setFirstSenderUserCommunicationEmailId($email->getId());
                $topic->setFirstSenderEmail($from);
                $topic->setFirstSenderName($email->getName());
            }
            $topic->setHasDraft(Helpers::YES);
            if (count($attachments) > 0) {
                $topic->setHasAttachment(Helpers::YES);
            } else {
                $topic->setHasAttachment(Helpers::NO);
            }
            $flags = [];
            if ($is_flagged == 1) {
                $flags = [intval(ModuleModel::$user_profile->getId())];
            }
            $topic->setFlags(json_encode($flags));
            $topic->setData();
            /****************** followers ***************/
            $follower_ids = array();
            $followers = Helpers::__getRequestValue('followers');
            if (count($followers) > 0) {
                foreach ($followers as $follower) {
                    if(!in_array( $follower->id, $follower_ids)) {
                        $follower_ids[] = $follower->id;
                    }
                }
            }
            $topic->setFollowers(json_encode($follower_ids));
            $resultCreateTopic = $topic->__quickCreate();
            if (!$resultCreateTopic["success"]) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateTopic];
                goto end_of_function;
            }
        }


        // create message of topic
        $uuid = Helpers::__getRequestValue("uuid");
        $is_update_message = false;
        if(Helpers::__isValidUuid($uuid)){
            $message = CommunicationTopicMessage::findFirstByUuid($uuid);
            $is_update_message = true;
        } else {
            $message = new CommunicationTopicMessage();
            $random = new Random();
            $uuid = $random->uuid();
            $message->setUuid($uuid);
            $message->setCommunicationTopicId($topic->getId());
        }
        $message->setReplyTo(Helpers::__getRequestValue("reply_to"));
        if ($communication_topic_id > 0) {
            $message->setPosition(count($topic->getTopicMessages()) + 1);
        } else {
            $message->setPosition(1);
        }
        $message->setSenderEmail($from);
        $message->setSenderName(ModuleModel::$user_profile->getFirstName() . ' ' . ModuleModel::$user_profile->getLastName());
        $message->setSenderUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $message->setSenderUserCommunicationEmailId($email ? $email->getId() : 0);
        $message->setStatus(CommunicationTopicMessage::STATUS_DRAFT);
        $message->setIsSendDirection(Helpers::YES);
        if (count($attachments) > 0) {
            $message->setHasAttachment(Helpers::YES);
        } else {
            $message->setHasAttachment(Helpers::NO);
        }

        $to_array = Helpers::__getRequestValue("to");
        $cc_array = Helpers::__getRequestValue("cc");
        $bcc_array = Helpers::__getRequestValue("cci");
        $to = [];
        $cc = [];
        $bcc = [];
        if (is_array($to_array) && count($to_array) > 0) {
            foreach ($to_array as $item) {
                $item = str_replace(' ', '', $item);
                $to[] = $item;
            }
        }
        if (is_array($cc_array) && count($cc_array) > 0) {
            foreach ($cc_array as $item) {
                $item = str_replace(' ', '', $item);
                $cc[] = $item;
            }
        }
        if (is_array($bcc_array) && count($bcc_array) > 0) {
            foreach ($bcc_array as $item) {
                $item = str_replace(' ', '', $item);
                $bcc[] = $item;
            }
        }
        $recipients = [];
        if (count($to) > 0) {
            $recipients["to"] = $to_array;
        }
        if (count($cc) > 0) {
            $recipients["cc"] = $cc_array;
        }
        if (count($bcc) > 0) {
            $recipients["bcc"] = $bcc_array;
        }
        $message->setRecipients(json_encode($recipients));
        if($is_update_message){
            $resultCreateMessage = $message->__quickUpdate();
        } else {
            $resultCreateMessage = $message->__quickCreate();
        }
        if (!$resultCreateMessage["success"]) {
            $this->db->rollback();
            $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "topic" => $topic, "data_message" => $message, "detail" => $resultCreateMessage];
            goto end_of_function;
        }
        $resultUpload = $message->transferContentToS3(Helpers::__getRequestValue("content"));
        if ($resultUpload['success'] == false) {
            $this->db->rollback();
            $return = [
                'detail' => $resultUpload['detail'],
                'success' => false,
                'message' => 'MESSAGE_ADDED_FAIL_TEXT',
            ];
            goto end_of_function;
        }
        // create message recipient

        /***** attachment *******/
        if (count($attachments) > 0) {

            $not_attached_yet = [];
            foreach ($attachments as $attachment){
                if(property_exists($attachment, 'media_attachment_uuid') && $attachment->media_attachment_uuid != null && $attachment->media_attachment_uuid != ''){
                    // do nothing
                } else {
                    $not_attached_yet[] = $attachment;
                }
            }
            if(count($not_attached_yet) > 0) {
                $resultAttachment = MediaAttachment::__createAttachments([
                    'objectUuid' => $message->getUuid(),
                    'fileList' => $not_attached_yet,
                ]);

                if (!$resultAttachment["success"]) {
                    if ($resultAttachment['detail']['message'] == "MEDIA_ALREADY_ATTACHED_TEXT") {
                        // do nothing
                    } else {
                        $this->db->rollback();
                        $return = ["success" => false,
                            "message" => "DATA_CREATE_FAIL_TEXT",
                            "topic" => $topic,
                            "data_message" => $message,
                            "attachment" => $resultAttachment];
                        goto end_of_function;
                    }
                }
            }
        }
        $this->db->commit();
        $return = [
            "success" => true,
            "message" => "MESSAGE_SUCCESSFULLY_SAVED_TEXT",
            "topic" => $topic,
            "data_message" => $message->toArray()
        ];
        end_of_function:
        $return['email_type'] = $email ? $email->getType() : null;
        $return['destination'] = isset($to) ? $to : null;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {

        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl('index');

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
        /****** assignments *****/
        $assignments = isset($filters->assignments) ? $filters->assignments : [];
        $assignment_ids = [];
        if (count($assignments) > 0) {
            foreach ($assignments as $assignment) {
                //$assignees_uuids[] = ['S' => $assignee->uuid];
                $assignment_ids[] = $assignment->id;
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

        /****** followers *****/
        $senders = isset($filters->senders) ? $filters->senders : [];
        $senders_ids = [];
        if (count($senders) > 0) {
            foreach ($senders as $sender) {
                $senders_ids[] = $sender->id;
            }
        }

        $options = array();
        $options['followers_ids'] = $followers_ids;
        $options['service_ids'] = $service_ids;
        $options['employee_ids'] = $assignees_ids;
        $options['assignment_ids'] = $assignment_ids;
        $options['sender_ids'] = $senders_ids;
        $options['user_profile_id'] = ModuleModel::$user_profile->getId();// CURRENT USER AS FOLLOWER OR MMEBER

        $options['page'] = $page;
        $trash = Helpers::__getRequestValue("trash");
        if ($trash == true) {
            $options["trash"] = true;
        } else {
            $options["trash"] = false;
        }

        $options["query"] = Helpers::__getRequestValue("keyword");
        $options["is_sent"] = Helpers::__getRequestValue("is_sent");
        $options["is_draft"] = Helpers::__getRequestValue("is_draft");
        $options["include_sent"] = Helpers::__getRequestValue("include_sent");
        $options["include_draft"] = Helpers::__getRequestValue("include_draft");
        $options["relocation_uuid"] = Helpers::__getRequestValue("relocation_uuid");
        $options["task_uuid"] = Helpers::__getRequestValue("task_uuid");
        $options["relocation_service_company_uuid"] = Helpers::__getRequestValue("relocation_service_company_uuid");
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        $resultItems = CommunicationTopic::__findWithFilter($options, $ordersConfig);

        $cacheName = "OLD_COMMUNICATION_COUNT_" . ModuleModel::$user_profile->getUuid();
        $cachedObject = CacheHelper::__getCacheValue($cacheName, CacheHelper::__TIME_1_YEAR);
        if ($cachedObject == null) {
            $cachedObject = OldCommunicationTopic::countTotalThread();
            if ($cachedObject) {
                CacheHelper::__updateCacheValue($cacheName, $cachedObject, CacheHelper::__TIME_1_YEAR);
            }
        }

        if ($resultItems['success'] == true) {
            $return = [
                // 'options' => $options,
                // 'sql' => $resultItems['sql'],
                'success' => true,
                'data' => $resultItems['data'],
                'next' => $resultItems['next'],
                'last' => $resultItems['last'],
                'before' => $resultItems['before'],
                'current' => $resultItems['current'],
                'totalItems' => $resultItems['total_items'],
                'totalPages' => $resultItems['total_pages'],
                'old_threads' => intval($cachedObject)
            ];
        } else {
            $return = $resultItems;
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getContentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $mainTopic = CommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {

                $mainTopicData = $mainTopic->toArray();

                $mainTopicData['owner'] = [
                    'id' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getId() : '',
                    'uuid' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getUuid() : '',
                    'email' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getWorkemail() : '',
                    'workemail' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getWorkemail() : '',
                    'firstname' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getFirstname() : '',
                    'lastname' => $mainTopic->getOwnerUserProfile() ? $mainTopic->getOwnerUserProfile()->getLastname() : '',
                ];
                $mainTopicData['service_company_name'] = $mainTopic->getServiceCompany() ? $mainTopic->getServiceCompany()->getName() : '';
                $mainTopicData['last_updated_time'] = strtotime($mainTopic->getUpdatedAt());
                $mainTopicData['followers'] = $mainTopic->getFollowerUserProfiles();
                $mainTopicData['relocation'] = $mainTopic->getRelocation();
                $mainTopicData['assignment'] = $mainTopic->getAssignment();
                $mainTopicData['number_of_message'] = $mainTopic->getNumberOfMessage();
                $mainTopicData['number_of_draft'] = $mainTopic->getNumberOfDraft();
                $mainTopicData['is_owner'] =  $mainTopic->getOwnerUserProfile()->getId() == ModuleModel::$user_profile->getId() ? 1 : 0;

                if ($mainTopic->getRelocation()){
                    $mainTopicData['relocation'] = $mainTopic->getRelocation()->toArray();
                    $mainTopicData['assignee'] = $mainTopic->getEmployee()->toArray();
                    $mainTopicData['relocation']['folders'] = $mainTopic->getRelocation()->getFolders();
                    $mainTopicData['relocation_service_company_id'] = $mainTopic->getRelocationServiceCompanyId();
                }else{
                    $mainTopicData['relocation'] = null;
                    $mainTopicData['assignee'] = null;
                    $mainTopicData['relocation_service_company_id'] = null;
                }
                $mainTopicData['assignee'] = [
                    'id' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getId() : '',
                    'uuid' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getUuid() : '',
                    'email' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'workemail' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'firstname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getFirstname() : '',
                    'lastname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getLastname() : '',
                ];


                if($mainTopic->getFlags() != null && $mainTopic->getFlags() != "[]") {
                    $flags = json_decode($mainTopic->getFlags(), true);
                    $mainTopicData['is_flagged'] = in_array(ModuleModel::$user_profile->getId(), $flags);
                } else {
                    $mainTopicData['is_flagged'] = false;
                }

                $messages = $mainTopic->getTopicMessages();
                $resultMessage = [];
                $references = [];
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $item = $message->toArray();
                        $content = str_replace('%7B%7Btoken%7D%7D', ModuleModel::$user_login_token ? base64_encode(ModuleModel::$user_login_token) : "", $message->getContentFromS3());
                        $item["content"] = $content;
                        $resultMessage[] = $item;
                    }
                }
                $mainTopicData['messages'] = $resultMessage;
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
        $this->checkAjax(['GET', 'POST']);
        $this->checkAcl('index');

        $uuid = Helpers::__getRequestValue('uuid');
        $inboxes = [];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $mainTopic = CommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {

                $messages = $mainTopic->getTopicMessages();
                $resultMessage = [];
                $references = [];
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $item = $message->toArray();
                        $resultMessage[] = $item;
                        $references[] = $message->getReferenceId();
                    }
                }

                $firstMessage = $mainTopic->getFirstMessage();
                $email = $mainTopic->getFirstSenderUserCommunicationEmail();

//                if (!$email) {
//                    $return = ['success' => false, 'data' => [], 'message' => 'EMAIL_NOT_EXIST_TEXT'];
//                    goto end_of_function;
//                }

                if($email) {
                    $this->db->begin();

                    if ($email->getType() == UserCommunicationEmail::OTHER) {
                        $readInbox = ImapHelper::readMailbox($email, $mainTopic, $references, ModuleModel::$company, ModuleModel::$user_login, ModuleModel::$user_profile);
                        if (!$readInbox["success"]) {
                            $this->db->rollback();
                            $return = $readInbox;
                            goto end_of_function;
                        }
                        $inboxes = $readInbox["items"];
                    }
                    else if ($email->getType() == UserCommunicationEmail::GOOGLE) {
                        $googleHelper = new GoogleHelper();
                        $readInbox = $googleHelper->readMailbox($email, $mainTopic, $firstMessage, ModuleModel::$company, ModuleModel::$user_login, ModuleModel::$user_profile);
                        if (!$readInbox["success"]) {
                            $this->db->rollback();
                            $return = $readInbox;
                            goto end_of_function;
                        }
                        $inboxes = $readInbox["items"];
                    }
                    else if ($email->getType() == UserCommunicationEmail::OUTLOOK) {
                        $outlookHelper = new OutlookHelper();
                        $readInbox = $outlookHelper->readMailbox($email, $mainTopic, ModuleModel::$company, ModuleModel::$user_login, ModuleModel::$user_profile);
                        if (!$readInbox["success"]) {
                            $this->db->rollback();
                            $return = $readInbox;
                            goto end_of_function;
                        }

                        $email->setToken($readInbox["token"]);
                        $updateToken = $email->__quickUpdate();
                        if (!$updateToken["success"]) {
                            $this->db->rollback();
                            $return = ["success" => false,
                                "message" => "DATA_CREATE_FAIL_TEXT",
                                "topic" => $mainTopic,
                                "token" => $updateToken];
                            goto end_of_function;
                        }
                        $inboxes = $readInbox["items"];
                    }

                    load_message:
                    $resultAttachs = [];
//                if (!isset($inboxes) || !is_array($inboxes)) {
//                    $return = [
//                        'success' => false,
//                        'message' => 'INBOX_NOT_FOUND_TEXT',
//                        'readInbox' => $readInbox
//                    ];
//                    goto end_of_function;
//                }
                    foreach ($inboxes as $indexItem => $inboxItem) {
                        $communicationTopicMessage = CommunicationTopicMessage::findFirstByReferenceId($inboxItem["reference_id"]);
                        if (!$communicationTopicMessage instanceof CommunicationTopicMessage) {
                            $nextmessage = new CommunicationTopicMessage();
                            $nextmessage->setUuid(Helpers::__uuid());
                            $nextmessage->setCommunicationTopicId($mainTopic->getId());
                            $nextmessage->setPosition(count($messages) + $indexItem + 1);
                            $nextmessage->setSenderEmail($inboxItem["from_address"]);
                            $nextmessage->setSenderName($inboxItem["from_name"]);
                            $nextmessage->setReferenceId($inboxItem["reference_id"]);
                            $nextmessage->setStatus(CommunicationTopicMessage::STATUS_SENT);
                            $content = $inboxItem["content"];
                            $recipients = [];
                            $recipients["to"] = [];
                            $recipients["cc"] = [];
                            $tos = isset($inboxItem["to"]) && is_array($inboxItem["to"]) ? $inboxItem["to"] : [];
                            $ccs = isset($inboxItem["cc"]) && is_array($inboxItem["cc"]) ? $inboxItem["cc"] : [];
                            //to
                            if (count($tos) > 0) {
                                foreach ($tos as $itemEmail) {
                                    $contact = Contact::findFirst([
                                        "conditions" => "company_id = :company_id: AND email = :email:",
                                        "bind" => [
                                            'company_id' => ModuleModel::$company->getId(),
                                            'email' => $itemEmail
                                        ]
                                    ]);
                                    if (!$contact) {
                                        $contact = new Contact();
                                        $contact->setEmail($itemEmail);
                                        $contact->setCreatorUserProfileId(ModuleModel::$user_profile->getId());
                                        $contact->setCompanyId(ModuleModel::$company->getId());
                                        $contact->setData();
                                        $resultContact = $contact->__quickCreate();
                                        if (!$resultContact["success"]) {
                                            $this->db->rollback();
                                            $return = [
                                                "success" => false,
                                                "message" => "DATA_CREATE_FAIL_TEXT",
                                                "errorTYpe" => "errorCreateContact",
                                                "detail" => $resultContact['detail'],
                                                "contact" => $resultContact
                                            ];
                                            goto end_of_function;
                                        }
                                    }
                                    $recipients["to"][] = $itemEmail;
                                }
                            }
                            //cc
                            if (count($ccs) > 0) {
                                foreach ($ccs as $itemEmail) {
                                    $contact = Contact::findFirst(["conditions" => "company_id = :company_id: AND email = :email:",
                                        "bind" => [
                                            'company_id' => ModuleModel::$company->getId(),
                                            'email' => $itemEmail
                                        ]]);
                                    if (!$contact instanceof Contact) {
                                        $contact = new Contact();
                                        $contact->setEmail($itemEmail);
                                        $contact->setCreatorUserProfileId(ModuleModel::$user_profile->getId());
                                        $contact->setCompanyId(ModuleModel::$company->getId());
                                        $contact->setData();
                                        $resultContact = $contact->__quickCreate();
                                        if (!$resultContact["success"]) {
                                            $this->db->rollback();
                                            $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "contact" => $resultContact];
                                            goto end_of_function;
                                        }
                                    }
                                    $recipients["cc"][] = $itemEmail;
                                }
                            }
                            $nextmessage->setRecipients(json_encode($recipients));
                            if ($nextmessage->getSenderEmail() == $mainTopic->getFirstSenderEmail()) {
                                $nextmessage->setIsSendDirection(Helpers::YES);
                            } else {
                                $nextmessage->setIsSendDirection(Helpers::NO);
                            }
                            $attachments = isset($inboxItem["attachments"]) && is_array($inboxItem["attachments"]) ? $inboxItem["attachments"] : [];
                            if (count($attachments) > 0) {
                                $nextmessage->setHasAttachment(Helpers::YES);
                            } else {
                                $nextmessage->setHasAttachment(Helpers::NO);
                            }
                            $resultCreateMessage = $nextmessage->__quickCreate();
                            if (!$resultCreateMessage["success"]) {
                                $this->db->rollback();
                                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateMessage];
                                goto end_of_function;
                            }
                            $encoding = mb_detect_encoding($content, 'UTF-8, ISO-8859-1, WINDOWS-1252, WINDOWS-1251', true);
                            if ($encoding != 'UTF-8') {
                                $content = iconv($encoding, 'UTF-8//IGNORE', $content);
                            }
                            $resultUpload = $nextmessage->transferContentToS3($content);
                            if ($resultUpload['success'] == false) {
                                $this->db->rollback();
                                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultUpload];
                                goto end_of_function;
                            }
                            $newmessage = $nextmessage->toArray();

                            if (count($attachments) > 0) {
                                $resultAttach = MediaAttachment::__createAttachments([
                                    'objectUuid' => $nextmessage->getUuid(),
                                    'fileList' => $attachments,
                                ]);

                                if (!$resultAttach["success"]) {
                                    $this->db->rollback();
                                    $return = ['success' => false, 'detail' => $resultAttach, 'message' => 'DATA_CREATE_FAIL_TEXT'];
                                    goto end_of_function;
                                }
                                $resultAttachs[] = $resultAttach;
                                if ($mainTopic->getHasAttachment() != Helpers::YES) {
                                    $mainTopic->setHasAttachment(Helpers::YES);
                                }
                            }
                            if ($nextmessage->getSenderEmail() == $mainTopic->getFirstSenderEmail()) {
                                $mainTopic->setLastSent(time());
                            } else {
                                $mainTopic->setLastReceive(time());
                            }
                            $resultCreateTopic = $mainTopic->__quickUpdate();
                            if (!$resultCreateTopic["success"]) {
                                $this->db->rollback();
                                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateTopic];
                                goto end_of_function;
                            }
                            array_unshift($resultMessage, $newmessage);
                        }
                    }
                    $this->db->commit();
                }
                $return = [
                    'success' => true,
                    'data' => $resultMessage,
                    "message" => isset($inboxes) ? $inboxes : null,
                    'readInbox' => isset($readInbox) ? $readInbox : null,
//                    "readInbox" => isset($readInbox) ? $readInbox : null,
//                    "resultAttachs" => $resultAttachs
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            }

            end_of_function:
            $this->response->setJsonContent($return);
            return $this->response->send();
        }
    }

    /**
     *
     */
    public function getMessageAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'POST']);
        $this->checkAcl('index');

        $uuid = Helpers::__getRequestValue('uuid');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $message = CommunicationTopicMessage::findFirstByUuid($uuid);
            $mainTopic = $message->getCommunicationTopic();
            if ($mainTopic && $mainTopic->belongsToGms()) {
                $item = $message->toArray();
                $item["recipients"] = json_decode($message->getRecipients());
                $content = str_replace('%7B%7Btoken%7D%7D', ModuleModel::$user_login_token ? base64_encode(ModuleModel::$user_login_token) : "", $message->getContentFromS3());
                if(Helpers::__isBase64($content)){
                    $item["content"] = rawurldecode(base64_decode($content));
                } else {
                    $item["content"] = $content;
                }
            }
        }

        $return = [
            'success' => true,
            'data' => $item
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function getFollowersAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index');
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $mainTopic = CommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {

                $followers = $mainTopic->getFollowerUserProfiles();
                $follower_uuids = [];
                if (count($followers) > 0) {
                    foreach ($followers as $follower) {
                        $follower_uuids[$follower->getUuid()] = $follower;
                    }
                }

                $workers = UserProfile::__getWorkers();
                $users = [];
                foreach ($workers as $key => $worker) {
                    $users[$key] = $worker->toArray();
//                    if ($worker->getId() != $mainTopic->getOwnerUserProfileId()) {
                        if (isset($follower_uuids[$worker->getUuid()])) {
                            $users[$key]['selected'] = true;
                        } else {
                            $users[$key]['selected'] = false;
                        }
//                    } else {
//                        $users[$key]['selected'] = false;
//                    }
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
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function addFollowerAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('edit');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('topic_uuid');
        $member_uuid = Helpers::__getRequestValue('member_uuid');

        if (!($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid) && $member_uuid != '' && Helpers::__isValidUuid($member_uuid))) {
            goto end_of_function;
        }
        $mainTopic = CommunicationTopic::findFirstByUuidCache($topic_uuid);

        if (!($mainTopic && $mainTopic->belongsToGms())) {
            goto end_of_function;
        }

        $user = UserProfile::findFirstByUuidCache($member_uuid);

        if ($user && ($user->belongsToGms() || $user->manageByGms())) {
            if ($mainTopic->getFollowers() != null && $mainTopic->getFollowers() != "[]") {
                $followers = json_decode($mainTopic->getFollowers(), true);
                if (!in_array($user->getId(), $followers)) {
                    $followers[] = intval($user->getId());
                }
            } else {
                $followers = [intval($user->getId())];
            }
            $mainTopic->setFollowers(json_encode($followers));
            $return = $mainTopic->__quickUpdate();
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function addAllUsersAsFollowerAction($topic_uuid ='')
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('edit');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid))) {
            goto end_of_function;
        }
        $mainTopic = CommunicationTopic::findFirstByUuidCache($topic_uuid);

        if (!($mainTopic && $mainTopic->belongsToGms())) {
            goto end_of_function;
        }

        $contacts = UserProfile::getGmsWorkers();
        if ($mainTopic->getFollowers() != null && $mainTopic->getFollowers() != "[]") {
            $followers = json_decode($mainTopic->getFollowers(), true);
        } else {
            $followers = [];
        }
        $new_followers = [];
        foreach ($contacts as $contact) {
                if (!in_array($contact->getId(), $followers)) {
                    $followers[] = intval($contact->getId());
                    $new_followers[] = $contact;
                }
        }
        $mainTopic->setFollowers(json_encode($followers));
        $result = $mainTopic->__quickUpdate();
        if(!$result["success"]){
            $return = $result;
        } else {
            $return = ['success' => true, 'message' => '', 'data' => $new_followers, 'users' => $contacts];
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
        $this->checkAcl('edit');


        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('topic_uuid');
        $member_uuid = Helpers::__getRequestValue('member_uuid');

        if (!($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid) && $member_uuid != '' && Helpers::__isValidUuid($member_uuid))) {
            goto end_of_function;
        }

        $mainTopic = CommunicationTopic::findFirstByUuidCache($topic_uuid);

        if (!($mainTopic && $mainTopic->belongsToGms())) {
            goto end_of_function;
        }

        $user = UserProfile::findFirstByUuidCache($member_uuid);
        if (!($user && ($user->belongsToGms() || $user->manageByGms()))) {
            goto end_of_function;
        }
        if ($mainTopic->getFollowers() != null && $mainTopic->getFollowers() != "[]") {
            $followers = json_decode($mainTopic->getFollowers(), true);
            $new_followers = [];
            foreach ($followers as $follower) {
                if ($follower != $user->getId()) {
                    $new_followers[] = $follower;
                }
            }
            $mainTopic->setFollowers(json_encode($new_followers));
            $return = $mainTopic->__quickUpdate();
        }
        end_of_function:
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
        $this->checkAclIndex();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $mainTopic = CommunicationTopic::findFirstByUuid($topic_uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {
                if ($mainTopic->getFlags() != null && $mainTopic->getFlags() != "[]") {
                    $flags = json_decode($mainTopic->getFlags(), true);
                    if (!in_array(ModuleModel::$user_profile->getId(), $flags)) {
                        $flags[] = intval(ModuleModel::$user_profile->getId());
                    }
                } else {
                    $flags = [intval(ModuleModel::$user_profile->getId())];
                }
                $mainTopic->setFlags(json_encode($flags));
                $return = $mainTopic->__quickUpdate();
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
        $this->checkAcl('edit');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $mainTopic = CommunicationTopic::findFirstByUuid($topic_uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {
                if ($mainTopic->getFlags() != null && $mainTopic->getFlags() != "[]") {
                    $flags = json_decode($mainTopic->getFlags(), true);
                    $new_flags = [];
                    foreach ($flags as $flag) {
                        if ($flag != ModuleModel::$user_profile->getId()) {
                            $new_flags[] = $flag;
                        }
                    }
                    $mainTopic->setFlags(json_encode($new_flags));
                    $return = $mainTopic->__quickUpdate();
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
        $this->checkAclEdit();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuids = Helpers::__getRequestValues();

        if (ModuleModel::$user_profile->isAdminOrManager() == false) {
            $return = ['success' => false, 'data' => [], 'message' => 'PERMISSION_NOT_FOUND_TEXT'];
        } else {
            if (count($topic_uuids) > 0) {
                $this->db->begin();
                foreach ($topic_uuids as $topic_uuid) {
                    if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {

                        $itemTopic = CommunicationTopic::findFirstByUuid($topic_uuid);
                        if ($itemTopic && $itemTopic->belongsToGms()) {
                            $itemTopic->setStatus(CommunicationTopic::STATUS_ARCHIVED);
                            $updateTopicConversationId = $itemTopic->__quickUpdate();
                            if (!$updateTopicConversationId["success"]) {
                                $this->db->rollback();
                                $return = ["success" => false, "message" => "DATA_REMOVE_FAIL_TEXT", "detail" => $updateTopicConversationId];
                                goto end_of_function;
                            }
                        }
                    }
                }
                $this->db->commit();
                $return = ['success' => true, 'data' => [], 'message' => 'COMMUNICATION_REMOVE_SUCCESS_TEXT'];
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }

        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function deleteForeverMessageAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuids = Helpers::__getRequestValues();

        if (ModuleModel::$user_profile->isAdminOrManager() == false) {
            $return = ['success' => false, 'data' => [], 'message' => 'PERMISSION_NOT_FOUND_TEXT'];
        } else {
            if (count($topic_uuids) > 0) {
                $this->db->begin();
                foreach ($topic_uuids as $topic_uuid) {
                    if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {

                        $itemTopic = CommunicationTopic::findFirstByUuid($topic_uuid);
                        if ($itemTopic && $itemTopic->belongsToGms()) {
                            $updateTopicConversationId = $itemTopic->remove();
                            if (!$updateTopicConversationId["success"]) {
                                $this->db->rollback();
                                $return = ["success" => false, "message" => "COMMUNICATION_PERMANENTLY_DELETED_FAIL_TEXT", "detail" => $updateTopicConversationId];
                                goto end_of_function;
                            }
                        }
                    }
                }
                $this->db->commit();
                $return = ['success' => true, 'data' => [], 'message' => 'COMMUNICATION_PERMANENTLY_DELETED_SUCCESS_TEXT'];
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }

        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function restoreMessageAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit();

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuids = Helpers::__getRequestValues();

        if (ModuleModel::$user_profile->isAdminOrManager() == false) {
            $return = ['success' => false, 'data' => [], 'message' => 'PERMISSION_NOT_FOUND_TEXT'];
        } else {
            if (count($topic_uuids) > 0) {
                $this->db->begin();
                foreach ($topic_uuids as $topic_uuid) {
                    if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {

                        $itemTopic = CommunicationTopic::findFirstByUuid($topic_uuid);
                        if ($itemTopic && $itemTopic->belongsToGms()) {
                            $itemTopic->setStatus(CommunicationTopic::STATUS_ACTIVE);
                            $updateTopicConversationId = $itemTopic->__quickUpdate();
                            if (!$updateTopicConversationId["success"]) {
                                $this->db->rollback();
                                $return = ["success" => false, "message" => "DATA_RESTORE_FAIL_TEXT", "detail" => $updateTopicConversationId];
                                goto end_of_function;
                            }
                        }
                    }
                }
                $this->db->commit();
                $return = ['success' => true, 'data' => [], 'message' => 'COMMUNICATION_RESTORE_SUCCESS_TEXT'];
            } else {
                $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
            }

        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function checkLastUpdateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index');

        $return = ['success' => true, 'data' => 0];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $lastUpdateTime = CommunicationTopic::findLastUpdate($uuid);
            $return = ['success' => true, 'data' => $lastUpdateTime];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getContactsAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAcl('index');

        $uuid = Helpers::__getRequestValue('uuid');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $mainTopic = CommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {
                $contacts = $mainTopic->getContactsProfiles();

                $assignee = [
                    'uuid' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getUuid() : '',
                    'email' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'workemail' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'firstname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getFirstname() : '',
                    'lastname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getLastname() : '',
                ];

                if ($mainTopic->getIncludeAssignee() == CommunicationTopic::INCLUDE_ASSIGNEE_YES) {
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

    public function searchSenderAction()
    {
        $topics = CommunicationTopic::getRelatedTopic();
        $senders = [];
        foreach ($topics as $topic) {
            $firstMessage = $topic->getFirstMessage();
            if ($firstMessage instanceof CommunicationTopicMessage) {
                $senders[$firstMessage->getSenderEmail()]["uuid"] = $firstMessage->getSenderEmail();
                $senders[$firstMessage->getSenderEmail()]["name"] = $firstMessage->getSenderEmail();
            }
        }
        $return = ["success" => true, "data" => $senders];

        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    public function fillFieldsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclIndex();
        $data = $this->request->getJsonRawBody();
        $content =  isset($data->content) ? rawurldecode(base64_decode($data->content)) : null;
        $assignee = Helpers::__getRequestValue("assignee");
        if ($assignee != null && property_exists($assignee, 'id')) {
            ModuleModel::$employee = Employee::findFirstById($assignee->id);
        }
        $relocation = Helpers::__getRequestValue("relocation");
        if ($relocation != null && property_exists($relocation, 'id')) {
            ModuleModel::$relocation = Relocation::findFirstById($relocation->id);
            ModuleModel::$assignment = ModuleModel::$relocation->getAssignment();
        }
        $assignment = Helpers::__getRequestValue("assignment");
        if ($assignment != null && property_exists($assignment, 'id')) {
            ModuleModel::$assignment = Assignment::findFirstById($assignment->id);
        }
        $relocation_service_company = RelocationServiceCompany::findFirstById(Helpers::__getRequestValue("relocation_service_company_id"));
        if($relocation_service_company){
            ModuleModel::$relocationServiceCompany = $relocation_service_company;
            ModuleModel::$serviceCompany = $relocation_service_company->getServiceCompany();
        } else {
            ModuleModel::$serviceCompany = ServiceCompany::findFirstById(Helpers::__getRequestValue("service_company_id"));
        }

        $return = AutofillEmailTemplateHelper::fillContent($content);

        $this->response->setJsonContent($return);
        return $this->response->send();

    }
}
