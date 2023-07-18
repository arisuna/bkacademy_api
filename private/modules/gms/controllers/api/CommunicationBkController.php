<?php

namespace Reloday\Gms\Controllers\API;

use League\OAuth2\Client\Provider\GenericProvider;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Message;
use Phalcon\Security\Random;
use PHPMailer\PHPMailer\PHPMailer;
use Reloday\Application\Lib\GoogleHelper;
use Reloday\Application\Lib\Helpers;

use Reloday\Application\Lib\ImapHelper;
use Reloday\Application\Lib\OutlookHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\SmtpHelper;
use \Reloday\Gms\Models\CommunicationTopic;
use \Reloday\Gms\Models\CommunicationTopicMessage;
use Reloday\Gms\Models\CommunicationTopicRecipient;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectMap;
use Reloday\Gms\Models\OldCommunicationTopic;
use Reloday\Gms\Models\UserCommunicationEmail;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\DataUserMember;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CommunicationBkController extends BaseController
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

        $from = Helpers::__getRequestValue("from");
        $email = UserCommunicationEmail::findFirst(["conditions" => "user_profile_id = :user_profile_id: AND status <> :status_archived: AND imap_email = :email:",
            "bind" => [
                'user_profile_id' => ModuleModel::$user_profile->getId(),
                'status_archived' => UserCommunicationEmail::STATUS_ARCHIVED,
                'email' => $from
            ]]);
        if (!$email instanceof UserCommunicationEmail) {
            $this->db->rollback();
            $return = ["success" => false, "message" => "FROM_ADDRESS_NOT_EXIST_TEXT", "detail" => $email];
            goto end_of_function;
        }
        $communication_topic_id = Helpers::__getRequestValue("communication_topic_id");
        if ($communication_topic_id > 0) {
            $topic = CommunicationTopic::findFirstById($communication_topic_id);
        } else {
            $topic = new CommunicationTopic();
            $topic->setCompanyId(intval(ModuleModel::$company->getId()));
            $topic->setOwnerUserProfileId(intval(ModuleModel::$user_profile->getId()));
            $assignee = Helpers::__getRequestValue("assignee");
            if ($assignee != null) {
                $topic->setEmployeeId(Helpers::__getRequestValue("assignee")->id);
            }
            $relocation = Helpers::__getRequestValue("relocation");
            if ($relocation != null) {
                $topic->setRelocationId(Helpers::__getRequestValue("relocation")->id);
            }
            $topic->setRelocationServiceCompanyId(Helpers::__getRequestValue("relocation_service_company_id"));
            $topic->setServiceCompanyId(intval(Helpers::__getRequestValue("service_company_id")));
            $topic->setSubject(Helpers::__getRequestValue("object"));
            $topic->setStatus(CommunicationTopic::STATUS_ACTIVE);
            $topic->setData();
            $resultCreateTopic = $topic->__quickCreate();
            if (!$resultCreateTopic["success"]) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateTopic];
                goto end_of_function;
            }
            $topic = $resultCreateTopic["data"];
            //create ObjectMap

            $resultSave = RelodayObjectMapHelper::__saveObject($topic->getUuid(), RelodayObjectMapHelper::TABLE_COMMUNICATION_TOPIC);
            if ($resultSave['success'] == false) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $objectMap];
                goto end_of_function;
            }


            //flag
            $flag = Helpers::__getRequestValue("is_flagged");

            if ($flag == true) {
                $res = ($topic->setFlag());
                if (!$res['success'] == true) {
                    $this->db->rollback();
                    $res['flag'] = $flag;
                    $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $res];
                    goto end_of_function;
                }
            }
            /****************** followers ***************/
            $follower_uuids = array();
            $followers = Helpers::__getRequestValue('followers');
            if (count($followers) > 0) {
                foreach ($followers as $follower) {

                    if (property_exists($follower, 'member_uuid')) {
                        $follower_uuids[] = $follower->member_uuid;
                        $user = UserProfile::findFirstByUuid($follower->member_uuid);
                    } elseif (property_exists($follower, 'uuid')) {
                        $follower_uuids[] = $follower->uuid;
                        $user = UserProfile::findFirstByUuid($follower->uuid);
                    } else {
                        continue;
                    }


                    $data_user_member_manager = new DataUserMember();
                    $model = $data_user_member_manager->__save([
                        'object_uuid' => $topic->getUuid(),
                        'user_profile_id' => $user->getId(),
                        'user_profile_uuid' => $user->getUuid(),
                        'user_login_id' => $user->getUserLoginId(),
                        'object_name' => $topic->getSource(),
                        'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                    ]);
                    if (!$model instanceof DataUserMember) {
                        $this->db->rollback();
                        $return = ["success" => false,
                            "message" => "DATA_CREATE_FAIL_TEXT",
                            "topic" => $topic,
                            "follower" => $model,
                            "objectMap" => $objectMap];
                        goto end_of_function;
                    }
                }
            }
        }

        $attachments = Helpers::__getRequestValue("items");

        // create message of topic
        $message = new CommunicationTopicMessage();
        $message->setCommunicationTopicId($topic->getId());
        $message->setPosition($topic->countTotalReplies() + 1);
        $message->setSenderEmail($from);
        $message->setSenderName(ModuleModel::$user_profile->getFirstName() . ' ' . ModuleModel::$user_profile->getLastName());
        $message->setSenderUserProfileUuid(ModuleModel::$user_profile->getUuId());
        $message->setSenderUserCommunicationEmailId($email->getId());
        $message->setData();
        $resultCreateMessage = $message->__quickCreate();
        if (!$resultCreateMessage["success"]) {
            $this->db->rollback();
            $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "topic" => $topic, "data_message" => $message];
            goto end_of_function;
        }
        $message = $resultCreateMessage["data"];
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

        $to_array = Helpers::__getRequestValue("to");
        $cc_array = Helpers::__getRequestValue("cc");
        $bcc_array = Helpers::__getRequestValue("cci");
        $to = [];
        $cc = [];
        $bcc = [];
        if (count($to_array) > 0) {
            foreach ($to_array as $item) {
                $item = str_replace(' ', '', $item);
                $to[] = $item;
            }
        }
        if (count($cc_array) > 0) {
            foreach ($cc_array as $item) {
                $item = str_replace(' ', '', $item);
                $cc[] = $item;
            }
        }
        if (count($bcc_array) > 0) {
            foreach ($bcc_array as $item) {
                $item = str_replace(' ', '', $item);
                $bcc[] = $item;
            }
        }

        //to
        if (count($to) > 0) {
            foreach ($to as $item) {
                $addRecipient = CommunicationTopicRecipient::createRecipient($message->getId(), $item, CommunicationTopicRecipient::TO);
                if (!$addRecipient["success"]) {
                    $this->db->rollback();
                    $return = ["success" => false,
                        "message" => "DATA_CREATE_FAIL_TEXT",
                        "addRecipient" => $addRecipient];
                    goto end_of_function;
                }
            }
        }
        //cc
        if (count($cc) > 0) {
            foreach ($cc as $item) {
                $addRecipient = CommunicationTopicRecipient::createRecipient($message->getId(), $item, CommunicationTopicRecipient::CC);
                if (!$addRecipient["success"]) {
                    $this->db->rollback();
                    $return = ["success" => false,
                        "message" => "DATA_CREATE_FAIL_TEXT",
                        "addRecipient" => $addRecipient];
                    goto end_of_function;
                }
            }
        }
        //bcc
        if (count($bcc) > 0) {
            foreach ($bcc as $item) {
                $addRecipient = CommunicationTopicRecipient::createRecipient($message->getId(), $item, CommunicationTopicRecipient::BCC);
                if (!$addRecipient["success"]) {
                    $this->db->rollback();
                    $return = ["success" => false,
                        "message" => "DATA_CREATE_FAIL_TEXT",
                        "addRecipient" => $addRecipient];
                    goto end_of_function;
                }
            }
        }

        /***** attachment *******/
        $medias = [];
        if (count($attachments) > 0) {

            $resultAttachment = MediaAttachment::__createAttachments([
                'objectUuid' => $message->getUuid(),
                'fileList' => $attachments,
            ]);

            if (!$resultAttachment["success"]) {
                $this->db->rollback();
                $return = ["success" => false,
                    "message" => "DATA_CREATE_FAIL_TEXT",
                    "topic" => $topic,
                    "data_message" => $message,
                    "attachment" => $resultAttachment];
                goto end_of_function;
            }
        }
        foreach ($attachments as $attachment) {
            $medias[] = Media::findFirstByUuid($attachment->uuid);
        }
        $subject = $topic->getSubject();
        $firstMessage = $topic->getFirstMessage();
        if ($firstMessage instanceof CommunicationTopicMessage) {
            $subject = "RE:" . $topic->getSubject();
        }

        /**** send email ****/
        if ($email->getType() == UserCommunicationEmail::GOOGLE) {
            $googleHelper = new GoogleHelper();

            $sendMail = $googleHelper->sendMail($email, $subject, $topic, $message, $to, $cc, $bcc, $medias);


            if (!$sendMail["success"]) {
                $this->db->rollback();
                $return = $sendMail;
                goto end_of_function;
            }
            $gmail_message_service = $sendMail["data"];

            $message->setReferenceId($gmail_message_service->getId());

            $updateId = $message->save();
            if (!$updateId) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $updateId];
                goto end_of_function;
            }

            if ($topic->getConversationId() == null || $topic->getConversationId() == "") {
                $topic->setConversationId($gmail_message_service->getThreadId());
                $updateTopicConversationId = $topic->__quickUpdate();
                if (!$updateTopicConversationId["success"]) {
                    $this->db->rollback();
                    $return = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $updateTopicConversationId];
                    goto end_of_function;
                }
            }

            $return = [
                "success" => true,
                "message" => "COMMUNICATION_SUCCESSFULLY_SENT_TEXT",
                "topic" => $topic,
                "data_message" => $message,
                "data" => $gmail_message_service, 'sendmessage' => $sendMail["sendmessage"]
            ];

        } else if ($email->getType() == UserCommunicationEmail::OUTLOOK) {
            $outlookHelper = new OutlookHelper();
            $sendMail = $outlookHelper->sendMail($email, $subject, $topic, $message, $to, $cc, $bcc, $medias);
            if (!$sendMail["success"]) {
                $this->db->rollback();
                $return = $sendMail;
                goto end_of_function;
            }

            $item = $sendMail["item"];

            $message->setReferenceId($item["internetMessageId"]);
            $updateId = $message->save();
            if (!$updateId) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $updateId];
                goto end_of_function;
            }
            if ($topic->getConversationId() == null || $topic->getConversationId() == "") {
                $topic->setConversationId($item["conversationId"]);
                $updateTopicConversationId = $topic->__quickUpdate();
                if (!$updateTopicConversationId["success"]) {
                    $this->db->rollback();
                    $return = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $updateTopicConversationId];
                    goto end_of_function;
                }
            }

            $return = [
                "success" => true,
                "message" => "COMMUNICATION_SUCCESSFULLY_SENT_TEXT",
                "topic" => $topic,
                "data_message" => $message,
                "result_send" => $sendMail
            ];

        } else {
            $smtp_mail = new SmtpHelper();
            $sendMail = $smtp_mail->sendMail($email, $topic, $message, $to, $cc, $bcc, $medias);
            if (!$sendMail["success"]) {
                $this->db->rollback();
                $return = $sendMail;
                goto end_of_function;
            }

            $message->setReferenceId($sendMail["id"]);
            $updateId = $message->save();
            if (!$updateId) {
                $this->db->rollback();
                $return = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT"];
                goto end_of_function;
            }
            $return = [
                "success" => true,
                "message" => "COMMUNICATION_SUCCESSFULLY_SENT_TEXT",
                "topic" => $topic,
                "data_message" => $message,
                "data" => $smtp_mail
            ];
        }
        $this->db->commit();

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
                $senders_ids[] = $sender->name;
            }
        }

        $options = array();
        $options['followers_ids'] = $followers_ids;
        $options['service_ids'] = $service_ids;
        $options['employee_ids'] = $assignees_ids;
        $options['senders'] = $senders_ids;
        $options['user_profile_id'] = ModuleModel::$user_profile->getId();// CURRENT USER AS FOLLOWER OR MMEBER

        $options['page'] = $page;
        $trash = Helpers::__getRequestValue("trash");
        if ($trash == true) {
            $options["trash"] = true;
        } else {
            $options["trash"] = false;
        }

        $options["query"] = Helpers::__getRequestValue("keyword");

        $resultItems = CommunicationTopic::__findWithFilter($options);

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
                'old_threads' => OldCommunicationTopic::countTotalThread()
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
                $mainTopicData['assignee'] = [
                    'uuid' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getUuid() : '',
                    'email' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'workemail' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getWorkemail() : '',
                    'firstname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getFirstname() : '',
                    'lastname' => $mainTopic->getEmployee() ? $mainTopic->getEmployee()->getLastname() : '',
                ];

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
                $mainTopicData['followers'] = $mainTopic->getFollowers();
                $mainTopicData['relocation'] = $mainTopic->getRelocation();

                $messages = CommunicationTopicMessage::find([
                    "conditions" => "communication_topic_id = :topic_id:",
                    "bind" => [
                        "topic_id" => $mainTopic->getId()
                    ],
                    "order" => "created_at desc"
                ]);
                $resultMessage = [];
                $references = [];
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $item = $message->toArray();
                        $to = [];
                        $cc = [];
                        $bcc = [];
                        $repicients = CommunicationTopicRecipient::find([
                            "conditions" => "communication_topic_message_id = :message_id:",
                            "bind" => [
                                "message_id" => $message->getId()
                            ]
                        ]);
                        if (count($repicients) > 0) {
                            foreach ($repicients as $recipient) {
                                if ($recipient->getType() == CommunicationTopicRecipient::TO) {
                                    $to[] = $recipient->getContact()->getEmail();
                                }
                                if ($recipient->getType() == CommunicationTopicRecipient::CC) {
                                    $cc[] = $recipient->getContact()->getEmail();
                                }
                                if ($recipient->getType() == CommunicationTopicRecipient::BCC) {
                                    $bcc[] = $recipient->getContact()->getEmail();
                                }
                            }
                        }
                        $item["to"] = $to;
                        $item["cc"] = $cc;
                        $item["bcc"] = $bcc;
                        $item["content"] = $message->getContentFromS3();
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

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $mainTopic = CommunicationTopic::findFirstByUuid($uuid);
            if ($mainTopic && $mainTopic->belongsToGms()) {

                $messages = CommunicationTopicMessage::find([
                    "conditions" => "communication_topic_id = :topic_id:",
                    "bind" => [
                        "topic_id" => $mainTopic->getId()
                    ],
                    "order" => "created_at desc"
                ]);
                $resultMessage = [];
                $references = [];
                if (count($messages) > 0) {
                    foreach ($messages as $message) {
                        $item = $message->toArray();
                        $to = [];
                        $cc = [];
                        $bcc = [];
                        $repicients = CommunicationTopicRecipient::find([
                            "conditions" => "communication_topic_message_id = :message_id:",
                            "bind" => [
                                "message_id" => $message->getId()
                            ]
                        ]);
                        if (count($repicients) > 0) {
                            foreach ($repicients as $recipient) {
                                if ($recipient->getType() == CommunicationTopicRecipient::TO) {
                                    $to[] = $recipient->getContact()->getEmail();
                                }
                                if ($recipient->getType() == CommunicationTopicRecipient::CC) {
                                    $cc[] = $recipient->getContact()->getEmail();
                                }
                                if ($recipient->getType() == CommunicationTopicRecipient::BCC) {
                                    $bcc[] = $recipient->getContact()->getEmail();
                                }
                            }
                        }
                        $item["to"] = $to;
                        $item["cc"] = $cc;
                        $item["bcc"] = $bcc;
//                        $item["content"] = $message->getContentFromS3();
                        $resultMessage[] = $item;
                        $references[] = $message->getReferenceId();
                    }
                }

                $firstMessage = $mainTopic->getFirstMessage();
                $email = $firstMessage->getEmail();
                if (!$email instanceof UserCommunicationEmail) {
                    $return = ['success' => false, 'data' => [], 'message' => 'EMAIL_NOT_EXIST_TEXT'];
                    goto end_of_function;
                }

                if ($email->getType() == UserCommunicationEmail::OTHER) {
                    $readInbox = ImapHelper::readMailbox($email, $mainTopic, $references, ModuleModel::$company, ModuleModel::$user_login, ModuleModel::$user_profile);
                    if (!$readInbox["success"]) {
                        $return = $readInbox;
                        goto end_of_function;
                    }
                    $inboxes = $readInbox["items"];
                } else if ($email->getType() == UserCommunicationEmail::GOOGLE) {
                    $googleHelper = new GoogleHelper();
                    $readInbox = $googleHelper->readMailbox($email, $mainTopic, $firstMessage, ModuleModel::$company, ModuleModel::$user_login, ModuleModel::$user_profile);
                    if (!$readInbox["success"]) {
                        $return = $readInbox;
                        goto end_of_function;
                    }
                    $inboxes = $readInbox["items"];
                } else if ($email->getType() == UserCommunicationEmail::OUTLOOK) {
                    $outlookHelper = new OutlookHelper();
                    $readInbox = $outlookHelper->readMailbox($email, $mainTopic, ModuleModel::$company, ModuleModel::$user_login, ModuleModel::$user_profile);
                    if (!$readInbox["success"]) {
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
                if (is_array($inboxes) && count($inboxes) > 0) {
                    foreach ($inboxes as $indexItem => $inboxItem) {
                        $this->db->begin();
                        $nextmessage = new CommunicationTopicMessage();
                        $nextmessage->setCommunicationTopicId($mainTopic->getId());
                        $nextmessage->setPosition($mainTopic->countTotalReplies() + $indexItem);

                        $nextmessage->setSenderEmail($inboxItem["from_address"]);
                        $nextmessage->setSenderName($inboxItem["from_name"]);
                        $nextmessage->setReferenceId($inboxItem["reference_id"]);
                        $content = $inboxItem["content"];
                        $nextmessage->setData();
                        $resultCreateMessage = $nextmessage->__quickCreate();
                        if (!$resultCreateMessage["success"]) {
                            $this->db->rollback();
                            $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultCreateMessage["message"]];
                            goto end_of_function;
                        }
                        $nextmessage = $resultCreateMessage["data"];
                        $resultUpload = $nextmessage->transferContentToS3($content);
                        if ($resultUpload['success'] == false) {
                            $this->db->rollback();
                            $return = ["success" => false, "message" => "DATA_CREATE_FAIL_TEXT", "detail" => $resultUpload];
                            goto end_of_function;
                        }
                        $newmessage = $nextmessage->toArray();
                        $newmessage["to"] = [];
                        $newmessage["cc"] = [];
//                    $newmessage["content"] = $content;
                        $tos = isset($inboxItem["to"]) && is_array($inboxItem["to"]) ? $inboxItem["to"] : [];
                        $ccs = isset($inboxItem["cc"]) && is_array($inboxItem["cc"]) ? $inboxItem["cc"] : [];
                        //to
                        if (count($tos) > 0) {
                            foreach ($tos as $item) {
                                $addRecipient = CommunicationTopicRecipient::createRecipient($nextmessage->getId(), $item, CommunicationTopicRecipient::TO);
                                if (!$addRecipient["success"]) {
                                    $this->db->rollback();
                                    $return = ["success" => false,
                                        "message" => "DATA_CREATE_FAIL_TEXT",
                                        "addRecipient" => $addRecipient];
                                    goto end_of_function;
                                }
                                $newmessage["to"][] = $item;
                            }
                        }
                        //cc
                        if (count($ccs) > 0) {
                            foreach ($ccs as $item) {
                                $addRecipient = CommunicationTopicRecipient::createRecipient($nextmessage->getId(), $item, CommunicationTopicRecipient::CC);
                                if (!$addRecipient["success"]) {
                                    $this->db->rollback();
                                    $return = ["success" => false,
                                        "message" => "DATA_CREATE_FAIL_TEXT",
                                        "addRecipient" => $addRecipient];
                                    goto end_of_function;
                                }
                                $newmessage["cc"][] = $item;
                            }
                        }
                        $attachments = isset($inboxItem["attachments"]) && is_array($inboxItem["attachments"]) ? $inboxItem["attachments"] : [];
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
                        }
                        $this->db->commit();
                        array_unshift($resultMessage, $newmessage);
                    }
                }
                $return = [
                    'success' => true,
                    'data' => $resultMessage,
                    "message" => $inboxes,
                    "readInbox" => $readInbox
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
                $to = [];
                $cc = [];
                $bcc = [];
                $repicients = CommunicationTopicRecipient::find([
                    "conditions" => "communication_topic_message_id = :message_id:",
                    "bind" => [
                        "message_id" => $message->getId()
                    ]
                ]);
                if (count($repicients) > 0) {
                    foreach ($repicients as $recipient) {
                        if ($recipient->getType() == CommunicationTopicRecipient::TO) {
                            $to[] = $recipient->getContact()->getEmail();
                        }
                        if ($recipient->getType() == CommunicationTopicRecipient::CC) {
                            $cc[] = $recipient->getContact()->getEmail();
                        }
                        if ($recipient->getType() == CommunicationTopicRecipient::BCC) {
                            $bcc[] = $recipient->getContact()->getEmail();
                        }
                    }
                }
                $item["to"] = $to;
                $item["cc"] = $cc;
                $item["bcc"] = $bcc;
                $item["content"] = $message->getContentFromS3();
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

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $mainTopic = CommunicationTopic::findFirstByUuid($uuid);

            if ($mainTopic && $mainTopic->belongsToGms()) {

                $followers = $mainTopic->getFollowers();
                $follower_uuids = [];
                if (count($followers) > 0) {
                    foreach ($followers as $follower) {
                        $follower_uuids[$follower->getUuid()] = $follower;
                    }
                }

                $workers = UserProfile::getGmsWorkers();
                $users = [];
                foreach ($workers as $key => $worker) {
                    $users[$key] = $worker->toArray();
                    if ($worker->getId() != $mainTopic->getOwnerUserProfileId()) {
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
            $dataFollower = DataUserMember::findFirst([
                'conditions' => 'object_uuid = :object_uuid: AND user_profile_id = :user_profile_id:',
                'bind' => [
                    'object_uuid' => $mainTopic->getUuid(),
                    'user_profile_id' => $user->getId(),
                ]
            ]);
            if (!$dataFollower instanceof DataUserMember) {
                $data_user_member_manager = new DataUserMember();
                $model = $data_user_member_manager->__save([
                    'object_uuid' => $mainTopic->getUuid(),
                    'user_profile_id' => $user->getId(),
                    'user_profile_uuid' => $user->getUuid(),
                    'user_login_id' => $user->getUserLoginId(),
                    'object_name' => $mainTopic->getSource(),
                    'member_type_id' => DataUserMember::MEMBER_TYPE_VIEWER
                ]);
                if (!$model instanceof DataUserMember) {
                    $return = ["success" => false,
                        "message" => "DATA_CREATE_FAIL_TEXT",
                        "topic" => $mainTopic,
                        "follower" => $model];
                    goto end_of_function;
                }

                $return = ['success' => true, 'message' => 'FOLLOWER_ADD_SUCCESS_TEXT', 'detail' => '1 follower'];
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

        $dataFollower = DataUserMember::findFirst([
            'conditions' => 'object_uuid = :object_uuid: AND user_profile_id = :user_profile_id:',
            'bind' => [
                'object_uuid' => $mainTopic->getUuid(),
                'user_profile_id' => $user->getId(),
            ]
        ]);
        if ($dataFollower) {
            $dataFollowerResult = $dataFollower->delete();

            if ($dataFollowerResult == true) {
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
            $itemTopic = CommunicationTopic::findFirstByUuid($topic_uuid);

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
        $this->checkAcl('edit');

        $return = ['success' => false, 'data' => [], 'message' => 'DATA_NOT_FOUND_TEXT'];
        $topic_uuid = Helpers::__getRequestValue('uuid');
        if ($topic_uuid != '' && Helpers::__isValidUuid($topic_uuid)) {
            $itemTopic = CommunicationTopic::findFirstByUuid($topic_uuid);

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
        $this->checkAclDelete();

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
}
