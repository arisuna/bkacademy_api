<?php

namespace Reloday\Gms\Controllers\API;

use Aws;

use ParagonIE\Sodium\Core\Curve25519\H;
use Reloday\Application\DynamoDb\ORM\DynamoCommentModelExt;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\PushHelper;
use Reloday\Application\Lib\RelodayDynamoORMException;
use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\DynamodbModel;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Models\ApplicationModel;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController as BaseController;
use Reloday\Gms\Help\HistoryHelper;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Comment;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\HousingProposition;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\Relocation;
use \Reloday\Gms\Models\Task as Task;
use \Reloday\Gms\Models\ModuleModel as ModuleModel;
use \Reloday\Application\Lib\Helpers;
use \Reloday\Application\Lib\ConstantHelper;
use \Reloday\Application\Lib\JWTEncodedHelper as JWTEncodedHelper;
use \Reloday\Gms\Models\DynamoComment as DynamoComment;
use \Reloday\Application\Models\RelodayComments as RelodayComments;
use \Phalcon\Security\Random;
use \Reloday\Gms\Models\EmailTemplateDefault as EmailTemplateDefault;
use \Reloday\Gms\Models\SupportedLanguage;
use \Reloday\Application\Lib\RelodayQueue;
use \Reloday\Application\Lib\TextHelper;
use Reloday\Gms\Models\AssignmentRequest;
use Reloday\Gms\Models\UserProfile;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CommentsController extends BaseController
{
    /**
     *
     */
    public function initialize()
    {
        $this->view->disable();
    }

    /**
     * @Route("/comments", paths={module="gms"}, methods={"GET"}, name="gms-comments-index")
     */
    public function indexAction()
    {

    }

    /**
     * @param string $object_uuid
     * @return mixed
     */
    public function getComments($object_uuid = '')
    {
        return $this->listAction($object_uuid);
    }

    /**
     * @param string $object_uuid
     */
    public function listAction(string $object_uuid = '')
    {

        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAcl('index', $this->router->getControllerName());

        $limitSearch = $this->request->get('limit') ? $this->request->get('limit') : false;
        $page = Helpers::__getRequestValue('page');
        $sorts = Helpers::__getRequestValue('sorts');

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        $params = [
            'limit' => $limitSearch,
            'object_uuid' => $object_uuid,
            'page' => $page,
            'sorts' => $sorts
        ];

        $is_report = Helpers::__getRequestValue('is_report');
        if($is_report >= 0){
            $params['report'] = $is_report;
        }

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {

            $return = Comment::__findWithFilter($params);

            if ($return['query']) {
                $return['query'] = '';
            }
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * save comment to post
     */
    public function createCommentAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $data = $this->request->getJsonRawBody();
        $comment = Helpers::__getRequestValueAsArray('comment');
        $object_uuid = Helpers::__getRequestValueAsArray('object_uuid');
        $assignee = Employee::findFirstByUuidCache($object_uuid);
        if (($assignee && $assignee->belongsToGms())) {
            $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
        } else {
            $this->checkAclCreate();
        }
        $message = rawurldecode(base64_decode($comment['message']));
        $attchmentItems = isset($comment['items']) ? $comment['items'] : null;
        $active_report = isset($comment['active_report']) && $comment['active_report'] == true ? true : false;
        $profiles = $comment['persons'];

        if ($active_report == false) {
            $active_report = strval(Comment::REPORT_NO);
        } else {
            $active_report = strval(Comment::REPORT_YES);
        }

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {

            /** @var set members $users */
            $users = [];
            $userProfiles = [];
            if (count($profiles) > 0) {
                foreach ($profiles as $profile) {
                    $user = [];
                    if (isset($profile['uuid']) && Helpers::__isValidUuid($profile['uuid'])) {
                        $userProfile = UserProfile::findFirstByUuidCache($profile['uuid']);
                        if ($userProfile) {
                            $userProfiles[] = $userProfile;
                            $user['user_profile_uuid'] = $userProfile->getUuid();
                            $user['nickname'] = $userProfile->getNickname();
                            $user['email'] = $userProfile->getPrincipalEmail();
                            $user['workemail'] = $userProfile->getPrincipalEmail();;
                            $user['firstname'] = $userProfile->getFirstname();
                            $user['lastname'] = $userProfile->getLastname();
                            $users[$userProfile->getUuid()] = $user;
                        }

                    }
                }
            }


            $commentUuid = Helpers::__uuid();
            $commentObject = new Comment();
            $commentObject->setUuid($commentUuid);
            $commentObject->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
            $commentObject->setCompanyUuid(ModuleModel::$company->getUuid());
            $commentObject->setObjectUuid($object_uuid);
            $commentObject->setMessage(TextHelper::convert2markdown($message));
            $commentObject->setReport($active_report);
            if (count($profiles) > 0) {
                $commentObject->setPersons(json_encode($users));
            }

            if (isset($data->comment->external_email) && $data->comment->external_email != '') {
                $commentObject->setExternalEmail($data->comment->external_email);
            }

            $return = $commentObject->__quickCreate();


            if ($return['success'] == true) {
                $comment = $commentObject->parseDataToArray();
                $comment['message'] = $message;
                $comment['editable'] = true;
                $comment['user_uuid'] = $commentObject->getUserProfileUuid();
                $comment['users'] = $users;
                $comment['profiles'] = $users;
                $return = [
                    'success' => true,
                    'message' => 'COMMENT_ADDED_SUCCESS_TEXT',
                    'data' => $comment
                ];

                //add counter comments

                $resultAddCounterComment = RelodayObjectMapHelper::__increaseCounter($object_uuid, 'comment_count');
                $return['$resultAddCounterComment'] = $resultAddCounterComment;

                $returnPusher = PushHelper::__sendNewChatMessage($commentObject->getObjectUuid(), $comment);
                $return['$returnPusher'] = $returnPusher;

                ModuleModel::$task = false;
                $taskData = Task::__getTaskByUuid($commentObject->getObjectUuid());
                if ($taskData && $taskData instanceof Task) {
                    ModuleModel::$task = $taskData;
                    //tag user
                    $this->sendNotificationToTagUser($taskData, $userProfiles, $commentObject);

                    if ($taskData instanceof Task) {
                        $assignment = Assignment::findFirstByUuid($taskData->getObjectUuid());
                        if ($assignment) {
                            $this->sendNotificationToDspUser($taskData, $commentObject, $userProfiles, $assignment->getCompanyId());
                        }
                    }
                } else {
                    $this->sendCommentToEmployeeHousing($commentObject);
                }

                $relocation = Relocation::findFirstByUuid($commentObject->getObjectUuid());
                if ($relocation && $relocation instanceof Relocation) {
                    //tag user
                    ModuleModel::$relocation = $relocation;
                    $this->sendNotificationToTagUser($relocation, $userProfiles, $commentObject);
                }

                $assignment = Assignment::findFirstByUuid($commentObject->getObjectUuid());
                if ($assignment && $assignment instanceof Assignment) {
                    //tag user
                    ModuleModel::$assignment = $assignment;
                    $this->sendNotificationToTagUser($assignment, $userProfiles, $commentObject);
                }
                $initiationData = AssignmentRequest::findFirstByUuid($commentObject->getObjectUuid());
                if ($initiationData && $initiationData instanceof AssignmentRequest) {
                    //tag user
                    $this->sendNotificationToTagUser($initiationData, $userProfiles, $commentObject);
                }

                $housingProposition = HousingProposition::findFirstByUuid($commentObject->getObjectUuid());
                if ($housingProposition && $housingProposition instanceof HousingProposition) {
                    //tag user
                    ModuleModel::$housingProposition = $housingProposition;
                }

                if (count($attchmentItems) > 0) {

                    $resultAttachment1 = MediaAttachment::__createAttachments([
                        'objectUuid' => $commentUuid,
                        'fileList' => $attchmentItems,
                    ]);

                    $resultAttachment2 = MediaAttachment::__createAttachments([
                        'objectUuid' => $object_uuid,
                        'fileList' => $attchmentItems,
                    ]);

                    $return['resultAttachment1'] = $resultAttachment1;
                    $return['resultAttachment2'] = $resultAttachment2;

                }


            } else {
                $return = [
                    'success' => false,
                    'message' => 'COMMENT_ADDED_FAIL_TEXT',
                ];
            }
        }


        end_of_function:

        /*** add notitification **/
        if ($return['success'] == true && isset($commentObject) && $commentObject) {

            ModuleModel::$comment = $commentObject;
            $this->dispatcher->setParam('return', $return);

            if (isset($taskData) && $taskData) {
                $return['$apiResults'][] = NotificationServiceHelper::__addNotification($taskData, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_ADD_COMMENT, [
                    'comment' => $commentObject->getMessage(),
                ]);
            } else {
//                $return['$apiResults'][] = NotificationServiceHelper::__addNotification($commentObject, HistoryModel::TYPE_COMMENT, HistoryModel::HISTORY_ADD_COMMENT);
            }

            /** tagged Users */
            if (isset($taskData) && $taskData && isset($userProfiles) && is_array($userProfiles) && count($userProfiles) > 0) {
                foreach ($userProfiles as $userProfile) {
                    $return['$apiResults'][] = NotificationServiceHelper::__addNotification($taskData, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_TAG_USER, [
                        'comment' => TextHelper::convert2html($commentObject->getMessage()),
                        'target_user' => $userProfile->toArray()
                    ], false);

                    $return['$apiResultsTag'][] = NotificationServiceHelper::__addNotificationForTagUser($taskData, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_TAG_USER, [
                        'comment' => TextHelper::convert2html($commentObject->getMessage()),
                        'target_user' => $userProfile->toArray(),
                    ]);
                }
            }

            if (isset($initiationData) && $initiationData && isset($userProfiles) && is_array($userProfiles) && count($userProfiles) > 0) {
                foreach ($userProfiles as $userProfile) {
                    $return['$apiResults'][] = NotificationServiceHelper::__addNotification($initiationData, HistoryModel::TYPE_ASSIGNMENT_REQUEST, HistoryModel::HISTORY_TAG_USER, [
                        'comment' => TextHelper::convert2html($commentObject->getMessage()),
                        'target_user' => $userProfile->toArray()
                    ], false);
                }
            }

            if (isset($assignment) && $assignment && isset($userProfiles) && is_array($userProfiles) && count($userProfiles) > 0) {


                foreach ($userProfiles as $userProfile) {
//
                    $return['$apiResults'][] = NotificationServiceHelper::__addNotificationForTagUser($assignment, HistoryModel::TYPE_ASSIGNMENT, HistoryModel::HISTORY_TAG_USER, [
                        'comment' => TextHelper::convert2html($commentObject->getMessage()),
                        'target_user' => $userProfile->toArray(),
                    ]);
                }
            }

            if (isset($relocation) && $relocation && isset($userProfiles) && is_array($userProfiles) && count($userProfiles) > 0) {
                foreach ($userProfiles as $userProfile) {
                    $return['$apiResults'][] = NotificationServiceHelper::__addNotificationForTagUser($relocation, HistoryModel::TYPE_RELOCATION, HistoryModel::HISTORY_TAG_USER, [
                        'comment' => TextHelper::convert2html($commentObject->getMessage()),
                        'target_user' => $userProfile->toArray(),
                    ]);
                }
            }

            if (isset($taskData) && $taskData) {

                /*
                $return['$apiResults'][] = NotificationServiceHelper::__addNotificationToAccount($targetCompanyId, $taskData, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_ADD_COMMENT, [
                    'comment' => $commentObject->message,
                ]);

                if (isset($taskData) && $taskData && isset($userProfiles) && is_array($userProfiles) && count($userProfiles) > 0) {
                    foreach ($userProfiles as $userProfile) {
                        $return['$apiResults'][] = NotificationServiceHelper::__addNotificationToAccount($targetCompanyId, $taskData, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_TAG_USER, [
                            'comment' => $commentObject->message,
                            'target_user' => $userProfile->toArray()
                        ]);
                    }
                }
                */


            }
        }

        $return['items'] = $attchmentItems;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * save comment to post
     */
    public function createCommentSimpleAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $data = $this->request->getJsonRawBody();
        $object_uuid = $data->object_uuid;
        $comment = Helpers::__getRequestValueAsArray('comment');
        $message = rawurldecode(base64_decode($comment['message']));
        $mainObjectUuid = $comment['object_uuid'];
        $commetParentObject = Comment::findFirstByUuid($mainObjectUuid);
        if ($commetParentObject) {
            $mainObjectUuid = $commetParentObject->getObjectUuid();
        }

        $active_report = isset($comment['active_report']) && $comment['active_report'] == true ? true : false;
        $profiles = $comment['persons'];

        if ($active_report == false) {
            $active_report = strval(DynamoComment::REPORT_NO);
        } else {
            $active_report = strval(DynamoComment::REPORT_YES);
        }

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {

            /** @var set members $users */
            $users = [];
            if (count($profiles) > 0) {
                foreach ($profiles as $profile) {
                    $user = [];
                    if (isset($profile['uuid']) && $profile['uuid'] != '') $user['user_profile_uuid'] = $profile['uuid'];
                    if (isset($profile['nickname']) && $profile['nickname'] != '') $user['nickname'] = $profile['nickname'];
                    if (isset($profile['workemail']) && $profile['workemail'] != '') $user['workemail'] = $profile['workemail'];
                    if (isset($profile['firstname']) && $profile['firstname'] != '') $user['firstname'] = $profile['firstname'];
                    if (isset($profile['lastname']) && $profile['lastname'] != '') $user['lastname'] = $profile['lastname'];
                    if (count($user) > 0) $users[$profile['uuid']] = $user;
                }
            }

            $commentObject = new Comment();
            $commentObject->setUuid(Helpers::__uuid());
            $commentObject->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
            $commentObject->setCompanyUuid(ModuleModel::$company->getUuid());
            $commentObject->setObjectUuid($object_uuid);
            $commentObject->setMessage(TextHelper::convert2markdown($message));
            $commentObject->setReport($active_report);
            if (count($profiles) > 0) {
                $commentObject->setPersons(json_encode($users));
            }

            $result = $commentObject->__quickCreate();

            if ($result['success'] == true) {

                $comment = $commentObject->parseDataToArray();

                $return = [
                    'success' => true,
                    'active_report' => $active_report,
                    'message' => 'COMMENT_ADDED_SUCCESS_TEXT',
                    'data' => $comment
                ];

                $resultAddCounterComment = RelodayObjectMapHelper::__increaseCounter($mainObjectUuid, 'comment_count');
                $return['$resultAddCounterComment'] = $resultAddCounterComment;

//                if ($commetParentObject) {
//                    $updateRepliesCount = DynamoCommentModelExt::__updateRepliesCounter($commetParentObject->getUuid(), 1);
//                    $return['$updateRepliesCount'] = $updateRepliesCount;
//                }

            } else {
                $return = [
                    'success' => false,
                    'message' => 'COMMENT_ADDED_FAIL_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function createCommentReplyAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $mainCommentUuid = Helpers::__getRequestValue('main_comment_uuid');
        $comment = Helpers::__getRequestValueAsArray('comment');
        $message = $comment['message'];
        $mainObjectUuid = $comment['object_uuid'];

        $commetParentObject = Comment::findFirstByUuid($mainCommentUuid);
        if ($commetParentObject) {
            $mainObjectUuid = $commetParentObject->getObjectUuid();
        }

        $active_report = isset($comment['active_report']) && $comment['active_report'] == true ? true : false;
        $profiles = $comment['persons'];

        if ($active_report == false) {
            $active_report = strval(DynamoComment::REPORT_NO);
        } else {
            $active_report = strval(DynamoComment::REPORT_YES);
        }

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        if ($mainCommentUuid != '' && Helpers::__isValidUuid($mainCommentUuid)) {
            /** @var set members $users */
            $users = [];
            if (count($profiles) > 0) {
                foreach ($profiles as $profile) {
                    $user = [];
                    if (isset($profile['uuid']) && $profile['uuid'] != '') $user['user_profile_uuid'] = $profile['uuid'];
                    if (isset($profile['nickname']) && $profile['nickname'] != '') $user['nickname'] = $profile['nickname'];
                    if (isset($profile['workemail']) && $profile['workemail'] != '') $user['workemail'] = $profile['workemail'];
                    if (isset($profile['firstname']) && $profile['firstname'] != '') $user['firstname'] = $profile['firstname'];
                    if (isset($profile['lastname']) && $profile['lastname'] != '') $user['lastname'] = $profile['lastname'];
                    if (count($user) > 0) $users[$profile['uuid']] = $user;
                }
            }

            $commentObject = new Comment();
            $commentObject->setUuid(Helpers::__uuid());
            $commentObject->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
            $commentObject->setCompanyUuid(ModuleModel::$company->getUuid());
            $commentObject->setObjectUuid($mainCommentUuid);
            $commentObject->setMessage(TextHelper::convert2markdown($message));
            $commentObject->setReport($active_report);
            if (count($profiles) > 0) {
                $commentObject->setPersons(json_encode($users));
            }
            $result = $commentObject->__quickCreate();

            if ($result['success'] == true) {


                $mainComment = Comment::findFirstByUuid($mainCommentUuid);
                if ($mainComment) {
                    $mainObjectUuid = $mainComment->getObjectUuid();
                }


                $comment = $commentObject->parseDataToArray();

                $return = [
                    'success' => true,
                    'active_report' => $active_report,
                    'message' => 'COMMENT_ADDED_SUCCESS_TEXT',
                    'data' => $comment
                ];


                $resultAddCounterComment = RelodayObjectMapHelper::__increaseCounter($mainObjectUuid, 'comment_count');
                $return['$resultAddCounterComment'] = $resultAddCounterComment;

//                if ($commetParentObject) {
//                    $updateRepliesCount = DynamoCommentModelExt::__updateRepliesCounter($commetParentObject->getUuid(), 1);
//                    $return['$updateRepliesCount'] = $updateRepliesCount;
//                }

            } else {
                $return = [
                    'success' => false,
                    'result' => $result,
                    'data' => $commentObject->parseDataToArray(),
                    'message' => 'COMMENT_ADDED_FAIL_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function sendToExternalRecipientAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclIndex();


        $data = $this->request->getJsonRawBody();
        $comment = Helpers::__getRequestValue('comment');
        $task_uuid = Helpers::__getRequestValue('task_uuid');
        $subject = isset($comment->subject) && $comment->subject !== '' ? $comment->subject : '';
        $message = isset($comment->message) && $comment->message !== '' ? $comment->message : '';
        $external_email = isset($comment->external_email) && $comment->external_email !== '' ? $comment->external_email : '';
        $templateEmail = isset($comment->template) && is_object($comment->template) && !empty($comment->template) ? $comment->template : false;
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($task_uuid != '' && Helpers::__isValidUuid($task_uuid) && Helpers::__isEmail($external_email)) {

            $task = Task::findFirstByUuid($task_uuid);

            if ($task instanceof Task && $task->belongsToGms() == true) {

                try {

                    $relocation = $task->getForceRelocation();
                    $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));

                    $dataArray = [
                        'action' => "sendMail",
                        'username' => ModuleModel::$user_profile->getFirstname() . "  " . ModuleModel::$user_profile->getLastname(),
                        'firstname' => ModuleModel::$user_profile->getFirstname(),
                        'lastname' => ModuleModel::$user_profile->getLastname(),
                        'email' => $external_email,
                        'relocation_number' => ($relocation) ? $relocation->getNumber() : '',
                        'url' => $task->getFrontendUrl(),
                        'url_reply' => 'mailto:' . $task->getSenderEmailCommentWithEmail($external_email) . "?Subject=" . urlencode($task->getName()),
                        'subject' => $subject,
                        'comment' => $message,
                        'templateName' => EmailTemplateDefault::ADD_EXTERNAL_COMMENT,
                        'language' => ModuleModel::$system_language
                    ];

                    if ($templateEmail) {
                        $subject = isset($templateEmail->subject) ? $templateEmail->subject : '';
                        $dataArray['comment_template_title'] = $subject;
                        $dataArray['templateName'] = EmailTemplateDefault::ADD_EXTERNAL_COMMENT_TEMPLATE;
                    }

                    $beanQueue->addQueue($dataArray);

                    $return = [
                        'success' => true,
                        'message' => 'SEND_MAIL_SUCCESS_TEXT',
                    ];
                } catch (\Exception $e) {
                    $return = [
                        'success' => false,
                        'message' => 'SEND_MAIL_FAIL_TEXT',
                        'detail' => $e->getMessage()
                    ];
                }
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $object_uuid
     */
    public function detailCommentAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();


        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $commentObject = Comment::findFirstByUuid($uuid);

            $return = [
                'success' => true,
                'message' => 'YOU_DONT_HAVE_PERMISSION_ACCESSED_TEXT',
            ];

            if ($commentObject->getObjectUuid() != '') {
                $assignee = Employee::findFirstByUuidCache($commentObject->getObjectUuid());
                if (($assignee && $assignee->belongsToGms())) {
                    $this->checkAclIndex(AclHelper::CONTROLLER_EMPLOYEE);
                } else {
                    $this->checkAclIndex();
                }
                if ($commentObject->getUserProfileUuid() == ModuleModel::$user_profile->getUuid() ||
                    ModuleModel::$user_profile->isAdminOrManager() || ($assignee && $assignee->belongsToGms())
                ) {
                    $data = $commentObject->parseDataToArray();
                    $data['message'] = TextHelper::convert2html($commentObject->getMessage());
                    $return = [
                        'success' => true,
                        'data' => $data,
                    ];
                }
            } else {
                $return = [
                    'success' => true,
                    'message' => 'DATA_NOT_FOUND_TEXT',
                ];
            }
        } else {
            $return = [
                'success' => true,
                'message' => 'DATA_NOT_FOUND_TEXT',
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * save comment to post
     */
    public function updateCommentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
//        $this->checkAclIndex();

        $data = $this->request->getJsonRawBody();
        $message = rawurldecode(base64_decode($data->message));
        $active_report = isset($data->active_report) && $data->active_report == true ? true : false;
        if ($active_report == false) {
            $active_report = strval(DynamoComment::REPORT_NO);
        } else {
            $active_report = strval(DynamoComment::REPORT_YES);
        }
        $profiles = $data->persons;

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $commentObject = Comment::findFirstByUuid($uuid);

            $return = [
                'success' => false,
                'message' => 'YOU_DONT_HAVE_PERMISSION_ACCESSED_TEXT',
            ];

            if ($commentObject->getObjectUuid() != '') {
                $assignee = Employee::findFirstByUuidCache($commentObject->getObjectUuid());
                if (($assignee && $assignee->belongsToGms())) {
                    $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
                } else {
                    $this->checkAclIndex();
                }

                //Check creator object
                $isAccessed = false;
                $objectType = RelodayObjectMapHelper::__getObjectType($commentObject->getObjectUuid());
                if ($objectType == RelodayObjectMapHelper::TABLE_ASSIGNMENT || $objectType == RelodayObjectMapHelper::TABLE_RELOCATION || $objectType == RelodayObjectMapHelper::TABLE_TASK) {
                    $creatorUser = DataUserMember::__getDataCreator($commentObject->getObjectUuid());
                    if ($creatorUser && ModuleModel::$user_profile->getUuid() == $creatorUser->getUuid()) {
                        $isAccessed = true;
                    }
                }

                if ($commentObject->getUserProfileUuid() == ModuleModel::$user_profile->getUuid() ||
                    ModuleModel::$user_profile->isAdminOrManager() || ($assignee && $assignee->belongsToGms()) || $isAccessed
                ) {

                    $users = [];
                    if (count($profiles) > 0) {
                        foreach ($profiles as $profile) {
                            $user = [];
                            if (isset($profile->uuid) && $profile->uuid != '') $user['user_profile_uuid'] = $profile->uuid;
                            if (isset($profile->nickname) && $profile->nickname != '') $user['nickname'] = $profile->nickname;
                            if (isset($profile->workemail) && $profile->workemail != '') $user['workemail'] = $profile->workemail;
                            if (isset($profile->firstname) && $profile->firstname != '') $user['firstname'] = $profile->firstname;
                            if (isset($profile->lastname) && $profile->lastname != '') $user['lastname'] = $profile->lastname;
                            if (count($user) > 0) $users[$profile->uuid] = $user;
                        }
                    }
                    $commentObject->setReport($active_report);
                    $commentObject->setMessage(TextHelper::convert2markdown($message));
                    if (count($profiles) > 0) {
                        $commentObject->setPersons(json_encode($users));

                    }

                    $return = $commentObject->__quickUpdate();

                    if ($return['success'] == true) {
                        $return['data'] = $commentObject->parseDataToArray();
                    }
                }
            } else {
                $return = [
                    'success' => true,
                    'message' => 'DATA_NOT_FOUND_TEXT',
                ];
            }
        } else {
            $return = [
                'success' => true,
                'message' => 'DATA_NOT_FOUND_TEXT',
            ];
        }

        end_of_function:

        /*** add notitification **/
        if ($return['success'] == true && isset($commentObject) && $commentObject) {
            $taskData = Task::__getTaskByUuid($commentObject->getObjectUuid());
            if ($taskData) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($taskData, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_UPDATE_COMMENT, [
                    'comment' => $commentObject->getMessage(),
                ]);
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * save comment to post
     */
    public function deleteCommentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclIndex();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $commentObject = Comment::findFirstByUuid($uuid);

            $return = [
                'success' => false,
                'message' => 'YOU_DONT_HAVE_PERMISSION_ACCESSED_TEXT',
            ];

            if ($commentObject && $commentObject->getObjectUuid() != '') {
                //Check creator object
                $isAccessed = false;
                $objectType = RelodayObjectMapHelper::__getObjectType($commentObject->getObjectUuid());
                if ($objectType == RelodayObjectMapHelper::TABLE_ASSIGNMENT || $objectType == RelodayObjectMapHelper::TABLE_RELOCATION || $objectType == RelodayObjectMapHelper::TABLE_TASK) {
                    $creatorUser = DataUserMember::__getDataCreator($commentObject->getObjectUuid());
                    if ($creatorUser && ModuleModel::$user_profile->getUuid() == $creatorUser->getUuid()) {
                        $isAccessed = true;
                    }
                }

                if ($commentObject->getUserProfileUuid() == ModuleModel::$user_profile->getUuid() ||
                    ModuleModel::$user_profile->isAdminOrManager() || $isAccessed) {

                    $return = $commentObject->__quickRemove();
                    if ($return['success'] == true) {
                        $return['message'] = 'COMMENT_DELETE_SUCCESS_TEXT';
                    } else {
                        $return['message'] = 'COMMENT_DELETE_FAIL_TEXT';
                        goto end_of_function;
                    }

                    $mainObjectUuid = $commentObject->getObjectUuid();
                    $commetParentObject = Comment::findFirstByUuid($mainObjectUuid);
                    if ($commetParentObject) {
                        $mainObjectUuid = $commetParentObject->getObjectUuid();
                    }

//                    $repliesCount = intval($commentObject->getRepliesCount());
                    $resultRemoveCounterComment = RelodayObjectMapHelper::__decreaseCounter($mainObjectUuid, 'comment_count', 1);
//                    $return['$resultRemoveCounterComment'] = $resultRemoveCounterComment;
                }
            }
        }

        end_of_function:
        if ($return['success'] == true && isset($commentObject) && $commentObject) {
            $taskData = Task::__getTaskByUuid($commentObject->getObjectUuid());
            if ($taskData) {
                $return['$apiResults'] = NotificationServiceHelper::__addNotification($taskData, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_REMOVE_COMMENT, [
                    'comment' => $commentObject->getMessage(),
                ]);
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $commentObject
     */
    public function sendCommentToEmployeeHousing($commentObject)
    {
        $queueSendMail = RelodayQueue::__getQueueSendMail();
        $housingProposition = HousingProposition::findFirstByUuid($commentObject->object_uuid);
        $resultCheck = ['success' => false];
        if ($housingProposition && $housingProposition->getStatus() != HousingProposition::STATUS_TO_SUGGEST) {
            $employee = $housingProposition->getEmployee();
            $property = $housingProposition->getProperty();
            if ($employee && $property) {
                $dataArray = [
                    'action' => "sendMail",
                    'sender_name' => 'Notification',
                    'to' => $employee->getWorkemail(),
                    'email' => $employee->getWorkemail(),
                    'language' => ModuleModel::$system_language,
                    'templateName' => EmailTemplateDefault::DSP_COMMENT_PROPERTY,
                    'params' => [
                        'username' => ModuleModel::$user_profile->getFullname(),
                        'property_name' => $property->getName(),
                        'comment' => $commentObject->getMessage(),
                        'company_name' => ModuleModel::$company->getName(),
                        'date' => date('d M Y - H:i:s'),
                        'url' => $employee->getEmployeeUrl() . "/#/app/my-housing-proposals/detail/" . $housingProposition->getUuid()
                    ]
                ];
                $resultCheck = $queueSendMail->addQueue($dataArray);
            }
        }

        return $resultCheck;
    }

    /**
     * @param $data (Task, Assignment, Relocation)
     * @param array $userProfiles
     * @return array
     */
    public function sendNotificationToTagUser($data, $userProfiles = [], $commentObject = null)
    {
        $queueSendMail = RelodayQueue::__getQueueSendMail();
        $resultCheck = ['success' => false];
        if (count($userProfiles) > 0) {
            foreach ($userProfiles as $targetUserProfile) {
                if ($data instanceof Task) {
                    $dataArray = [
                        'action' => "sendMail",
                        'sender_name' => 'Notification',
                        'to' => $targetUserProfile->getPrincipalEmail(),
                        'email' => $targetUserProfile->getPrincipalEmail(),
                        'language' => ModuleModel::$system_language,
                        'templateName' => EmailTemplateDefault::YOU_WERE_TAGGED,
                        'params' => [
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'user_company_name' => ModuleModel::$user_profile->getCompanyName(),
                            'comment' => $commentObject ? $commentObject->getMessage() : '',
                            //target of Action
                            'assignee_name' => $data->getEmployee() ? $data->getEmployee()->getFullname() : '',
                            'targetuser' => $targetUserProfile->getFullname(),
                            'target_company_name' => $targetUserProfile->getCompanyName(),
                            'task_url' => $targetUserProfile->getCompany()->getFrontendUrl() . "/#/app/tasks/page/" . $data->getUuid(),
                            'task_name' => $data->getName(),
                            'task_number' => $data->getNumber(),
                            'task_priority' => $data->getTaskPriority(ModuleModel::$system_language ?: SupportedLanguage::LANG_EN),
                            'time' => date('d M Y - H:i:s'),
                            'date' => date('d M Y - H:i:s'),
                        ]
                    ];
                    $resultCheck = $queueSendMail->addQueue($dataArray);
                } else if ($data instanceof Relocation) {
                    $dataArray = [
                        'action' => "sendMail",
                        'sender_name' => 'Notification',
                        'to' => $targetUserProfile->getPrincipalEmail(),
                        'email' => $targetUserProfile->getPrincipalEmail(),
                        'language' => ModuleModel::$system_language,
                        'templateName' => EmailTemplateDefault::YOU_WERE_TAGGED_IN_RELOCATION,
                        'params' => [
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'user_company_name' => ModuleModel::$user_profile->getCompanyName(),
                            'assignee_name' => $data->getAssignment()->getEmployee()->getFullname(),
                            'comment' => $commentObject ? $commentObject->getMessage() : '',
                            //target of Action
                            'url' => $targetUserProfile->getCompany()->getFrontendUrl() . "/#/app/relocation/dashboard/" . $data->getUuid(),
                            'relocation_number' => $data->getNumber(),
                            'time' => date('Y-m-d H:i:s'),
                            'date' => date('Y-m-d H:i:s'),
                        ]
                    ];
                    $resultCheck = $queueSendMail->addQueue($dataArray);
                } else if ($data instanceof Assignment) {
                    $dataArray = [
                        'action' => "sendMail",
                        'sender_name' => 'Notification',
                        'to' => $targetUserProfile->getPrincipalEmail(),
                        'email' => $targetUserProfile->getPrincipalEmail(),
                        'language' => ModuleModel::$system_language,
                        'templateName' => EmailTemplateDefault::YOU_WERE_TAGGED_IN_ASSIGNMENT,
                        'params' => [
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'user_company_name' => ModuleModel::$user_profile->getCompanyName(),
                            'targetuser' => $targetUserProfile->getFullname(),
                            'target_company_name' => $targetUserProfile->getCompanyName(),
                            'assignee_name' => $data->getEmployee() ? $data->getEmployee()->getFullname() : '',
                            'comment' => $commentObject ? $commentObject->getMessage() : '',
                            //target of Action
                            'url' => $targetUserProfile->getCompany()->getFrontendUrl() . "/#/app/assignment/dashboard/" . $data->getUuid(),
                            'assignment_number' => $data->getNumber(),
                            'time' => date('Y-m-d H:i:s'),
                            'date' => date('Y-m-d H:i:s'),
                        ]
                    ];
                    $resultCheck = $queueSendMail->addQueue($dataArray);
                } else if ($data instanceof AssignmentRequest) {
                    $dataArray = [
                        'action' => "sendMail",
                        'sender_name' => 'Notification',
                        'to' => $targetUserProfile->getPrincipalEmail(),
                        'email' => $targetUserProfile->getPrincipalEmail(),
                        'language' => ModuleModel::$system_language,
                        'templateName' => EmailTemplateDefault::YOU_WERE_TAGGED_IN_INITIATION,
                        'params' => [
                            'username' => ModuleModel::$user_profile->getFullname(),
                            'user_company_name' => ModuleModel::$user_profile->getCompanyName(),
                            //target of Action
                            'assignment_url' => $targetUserProfile->getCompany()->getFrontendUrl() . "/#/app/assignment/dashboard/" . $data->getAssignment()->getUuid() . "?requestUuid=" . $data->getUuid(),
                            'assignment_number' => $data->getAssignment()->getNumber(),
                            'assignee_name' => $data->getAssignment()->getEmployee()->getFullname(),
                            'time' => date('Y-m-d H:i:s'),
                            'date' => date('Y-m-d H:i:s'),
                            'comment' => $commentObject->getMessage()
                        ]
                    ];
                    $resultCheck = $queueSendMail->addQueue($dataArray);
                }

            }
        }
        return $resultCheck;
    }


    /**
     * @param $taskData
     * @param array $userProfiles
     */
    public function sendNotificationToDspUser($taskData, $commentObject, $userProfiles = [], $targetCompanyId = 0)
    {

        $queueSendMail = RelodayQueue::__getQueueSendMail();
        $resultCheck = ['success' => false];

        if ($targetCompanyId == 0 || $targetCompanyId == ModuleModel::$company->getId()) {
            return $resultCheck;
        }

        $members = DataUserMember::__getDataMembers($taskData->getUuid(), $targetCompanyId);


        if (count($members) > 0) {
            foreach ($members as $member) {
                $dataArray = [
                    'action' => "sendMail",
                    'sender_name' => 'Notification',
                    'to' => $member->getPrincipalEmail(),
                    'email' => $member->getPrincipalEmail(),
                    'language' => ModuleModel::$system_language,
                    'templateName' => EmailTemplateDefault::ADD_COMMENT,
                    'params' => [

                        'username' => ModuleModel::$user_profile->getFullname(),
                        'user_company_name' => ModuleModel::$user_profile->getCompanyName(),
                        //target of Action

                        'task_url' => $member->getCompany()->getFrontendUrl() . "/#/app/tasks/page/" . $taskData->getUuid(),
                        'task_name' => $taskData->getName(),
                        'task_number' => $taskData->getNumber(),

                        'comment' => $commentObject->getMessage(),


                        'time' => date('d M Y - H:i:s'),
                        'date' => date('d M Y - H:i:s'),
                    ]
                ];


                $resultCheck = $queueSendMail->addQueue($dataArray);

                //Tag user

//                foreach ($userProfiles as $targetUserProfile) {
//                    $dataArray = [
//                        'action' => "sendMail",
//                        'sender_name' => 'Notification',
//                        'to' => $member->getPrincipalEmail(),
//                        'email' => $member->getPrincipalEmail(),
//                        'language' => ModuleModel::$system_language,
//                        'templateName' => EmailTemplateDefault::TAG_USER,
//                        'params' => [
//                            'username' => ModuleModel::$user_profile->getFullname(),
//                            'user_company_name' => ModuleModel::$user_profile->getCompanyName(),
//                            //target of Action
//                            'targetuser' => $targetUserProfile->getFullname(),
//                            'target_company_name' => $targetUserProfile->getCompanyName(),
//
//                            'task_url' => $member->getCompany()->getFrontendUrl() . "/#/app/tasks/page/" . $taskData->getUuid(),
//                            'task_name' => $taskData->getName(),
//                            'task_number' => $taskData->getNumber(),
//                            'time' => date('Y-m-d H:i:s'),
//                            'date' => date('Y-m-d H:i:s'),
//                        ]
//                    ];
//                }
//                $resultCheck = $queueSendMail->addQueue($dataArray);
            }
        }
        return $resultCheck;
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCountAction($uuid)
    {
        $return = ['success' => true, 'data' => 0];
        $objectMap = RelodayObjectMapHelper::__getObject($uuid);
        if ($objectMap) {
            $return = ['success' => true, 'data' => $objectMap->getCommentCount()];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $object_uuid
     */
    public function getLastAction($object_uuid = '')
    {

        $this->view->disable();
        $this->checkAjaxGet();
//        $this->checkAclIndex(AclHelper::CONTROLLER_EMPLOYEE); // it must be get last acl to get comment of assignee

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];

        if ($object_uuid != '' && Helpers::__isValidUuid($object_uuid)) {

            $commentItem = Comment::findFirst([
                'conditions' => 'object_uuid = :object_uuid: and company_uuid = :company_uuid:',
                'bind' => [
                    'object_uuid' => $object_uuid,
                    'company_uuid' => ModuleModel::$company->getUuid(),
                ],
                'order' => 'created_at DESC'
            ]);
            $commentArray = [];

            if ($commentItem) {
                $user_profile_uuid = $commentItem->getUserProfileUuid();
                $commentArray = $commentItem->parseDataToArray();
                $commentArray['editable'] = $commentItem->belongsToUser();
            }

            $return = [
                'success' => true,
                'data' => $commentArray,
            ];
            goto end_of_function;

        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function seenAction($object_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $return = [
            'success' => false
        ];

        $company_uuid = Helpers::__getRequestValue('company_uuid');

        if ($object_uuid && $company_uuid) {
            $this->db->begin();

            $result = Comment::seenInitiationMessages($object_uuid, $company_uuid);
            if (!$result) {
                $this->db->rollback();
            } else {
                $this->db->commit();
            }

            $return = [
                'success' => $result
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function getCompleteMessengerAction($object_uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => false
        ];

        if ($object_uuid != '') {
            $result = Comment::findFirst([
                'conditions' => 'object_uuid = :object_uuid: and is_complete_messenger = :is_complete_messenger:',
                'bind' => [
                    'object_uuid' => $object_uuid,
                    'is_complete_messenger' => Comment::IS_COMPLETE_MESSENGER,
                ],
                'order' => 'created_at DESC'
            ]);

            if ($result) {
                $return = [
                    'success' => true,
                    'data' => $result->parseDataToArray()
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
/*
 * $objectDynamoComment = new DynamoComment();
            $requestQuery = [
                'TableName' => $objectDynamoComment->getTableName(),
                'IndexName' => 'ObjectUuidCreatedAtIndex',
                'KeyConditionExpression' => "object_uuid = :v_object_uuid",
                //'FilterExpression' => 'report > :v_report',
                'ExpressionAttributeValues' => [
                    ':v_object_uuid' => ['S' => $object_uuid],
                    //':v_report' => ['N' => '-1']
                ],
                'Count' => true,
                'ScanIndexForward' => false
            ];
            if ($limitSearch > 0) {
                $requestQuery['Limit'] = (int)$limitSearch;
            }

            if ($startKeySearch != false) {
                $requestQuery['ExclusiveStartKey'] = json_decode(json_encode($startKeySearch), true);
            }

            try {
                $response = $objectDynamoComment->__query($requestQuery);
            } catch (DynamoDbException $e) {
                $return = [
                    'success' => false,
                    'data' => [],
                    'message' => 'DATA_NOT_FOUND_TEXT',
                    'detail' => $e->getMessage()
                ];

                goto end_of_function;
            }
            $comments = [];

            //var_dump( $response ); die();

            if (isset($response['success']) && $response['success'] == false) {
                $return = $response;
            } else {

                $countObject = $response['Count'];
                $lastObject = $response['LastEvaluatedKey'];
                $lastObjectUuid = isset($response['LastEvaluatedKey']['uuid']['S']) ? $response['LastEvaluatedKey']['uuid']['S'] : null;


                foreach ($response['Items'] as $comment) {

                    $data = isset($comment['data']) && isset($comment['data']['S']) && $comment['data']['S'] != '' ? JWTEncodedHelper::decode($comment['data']['S']) : null;

                    if (!is_null($data)) {
                        $users = isset($data->users) ? $data->users : [];
                    }


                    $user_profile_uuid = isset($comment['user_profile_uuid']) && isset($comment['user_profile_uuid']['S']) ? ($comment['user_profile_uuid']['S']) : "";

                    $comments[$comment['uuid']['S']] = [
                        'uuid' => $comment['uuid']['S'],
                        'task_uuid' => $comment['object_uuid']['S'],
                        'object_uuid' => $comment['object_uuid']['S'],
                        'user_name' => isset($comment['user_name']) && isset($comment['user_name']['S']) ? $comment['user_name']['S'] : "",
                        'user_full_name' => isset($comment['user_name']) && isset($comment['user_name']['S']) ? $comment['user_name']['S'] : "",
                        'message' => isset($comment['message']) ? $comment['message']['S'] : "",
                        'user_uuid' => $user_profile_uuid,
                        'user_profile_uuid' => $user_profile_uuid,
                        'time' => $comment['created_at']['N'],
                        'created_at' => $comment['created_at']['N'],
                        'email' => isset($comment['email']) ? $comment['email']['S'] : "",
                        'report' => isset($comment['report']) && isset($comment['report']['N']) ? isset($comment['report']['N']) : "",
                        'date' => date('Y-m-d H:i:s', $comment['created_at']['N']),
                        'users' => isset($comment['persons']) && isset($comment['persons']['M']) ? isset($comment['report']['M']) : "",
                        'external_email' => isset($comment['external_email']) && isset($comment['external_email']['S']) ? $comment['external_email']['S'] : "",
                        'editable' => ModuleModel::$user_profile->isAdminOrManager() || ModuleModel::$user_profile->getUuid() == $user_profile_uuid
                        //'raw_data' => isset( $comment['data'] ) ? $comment['data']:""
                    ];
                }


                $return = [
                    'success' => true,
                    'data' => $comments,
                    'lastObject' => $lastObject,
                    'lastObjectUuid' => $lastObjectUuid,
                    'count' => $countObject,
                ];
            }
 */
