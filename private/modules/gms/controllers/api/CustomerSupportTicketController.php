<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Reloday\Application\Lib\RelodayUrlHelper;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Gms\Models\CustomerSupportTicket;
use Reloday\Gms\Models\CustomerSupportTicketAnswers;
use Reloday\Gms\Models\ModuleModel;

use Reloday\Application\Lib\Helpers;
use \Phalcon\Security\Random;
use Reloday\Gms\Models\MediaAttachment;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use Reloday\Gms\Module;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CustomerSupportTicketController extends BaseController
{

    const LIMIT_PAGE = 10;

    /**
     * @Route("/customersupportticket", paths={module="gms"}, methods={"GET"}, name="gms-customersupportticket-index")
     */
    public function indexAction()
    {

    }

    /**
     * Get Data by page
     */

    public function ticketsAction()
    {
        $this->view->disable();

        $ower_id = ModuleModel::$user_profile->getId();

        $this->checkAjaxPost();

        $request = $this->request->getJsonRawBody();

        $currentPage = (int)$request->page;
        $limitPerPage = (int)$request->limit > 0 ? (int)$request->limit : self::LIMIT_PAGE;

        $condition = 'user_profile_id = ' . $ower_id;

        if (isset($request->search) && $request->search !== '') {
            $condition .= " AND title LIKE '%" . $request->search . "%'";
        }

        if (isset($request->filter) && $request->filter !== '') {
            $condition .= " AND status = " . $request->filter;
        }

        $tickets = CustomerSupportTicket::find([
            $condition,
            'order' => 'updated_at DESC, status DESC'
        ]);

        $paginator = new PaginatorModel([
            "data" => $tickets,
            "limit" => $limitPerPage,
            "page" => $currentPage
        ]);

        $pagination = $paginator->getPaginate();

        $data = [];

        if(count($pagination->items) > 0){
            foreach ($pagination->items as $ticket){
                $item = $ticket->toArray();
                $userProfile = $ticket->getUserProfile();
                $item['user_profile_uuid'] = $userProfile->getUuid();
                $item['user_profile_name'] = $userProfile->getFullname();
                $item['updated_at_time'] = strtotime($ticket->getUpdatedAt());
                $item['created_at_time'] = strtotime($ticket->getCreatedAt());
                $data[] = $item;
            }
        }


        $return = [
            'success' => true,
            'page' => $currentPage,
            'items' => $data,
            'before' => $pagination->before,
            'next' => $pagination->next,
            'last' => $pagination->last,
            'current' => $pagination->current,
            'total_items' => $pagination->total_items,
            'total_pages' => $pagination->total_pages
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Create Ask a Question
     */

    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $arrRequest = Helpers::__getRequestValuesArray();
        $arrRequest['user_profile_id'] = ModuleModel::$user_profile->getId();
        $attachments = $arrRequest['attachments'];

        $arrRequest['created_at'] = date('Y-m-d H:i:s');
        $arrRequest['summary'] = Helpers::getWords($arrRequest['detail'], 50);
        $ticket = new CustomerSupportTicket();
        $ticket->setData($arrRequest);
        $ticketResult = $ticket->__quickCreate();

        $return = ['success' => false, 'message' => 'TICKET_SAVE_FAIL_TEXT'];

        if ($ticketResult['success'] == true) {
            //create new
            if (isset($attachments) && count($attachments) > 0) {
                $resultAttachment = MediaAttachment::__createAttachments(['objectUuid' => $ticket->getUuid(),
                        'fileList' => $attachments]);
            }
            $resultCheck = $this->sendToAdmin($ticket);
            if ($resultCheck['success'] == true) {
                $ticket->__quickUpdate();
            }
            $return = ['success' => true, 'resultCheck' => $resultCheck];
        } else {
            $return = $ticketResult;
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Get Ticket's information
     */

    public function requestAction($ticketId)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($ticketId && Helpers::__checkId($ticketId)) {
            $ticket = CustomerSupportTicket::findFirstById($ticketId);

            if ($ticket && $ticket->belongsToGms()) {
                $data = $ticket->toArray();

                $userProfile = $ticket->getUserProfile();
                $data['user_profile_uuid'] = $userProfile->getUuid();
                $data['user_profile_name'] = $userProfile->getFullname();
                $data['updated_at_time'] = strtotime($ticket->getUpdatedAt());
                $data['created_at_time'] = strtotime($ticket->getCreatedAt());

                $return = [
                    'success' => true,
                    'user' => $userProfile,
                    'data' => $data
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Reply action
     * @return mixed
     */
    public function replyAction($ticketId)
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $return = ['success' => false, 'message' => 'REPLY_FAIL_TEXT'];

        if ($ticketId && Helpers::__checkId($ticketId)) {
            $ticket = CustomerSupportTicket::findFirstById($ticketId);
        }

        if ($ticketId && Helpers::__isValidUuid($ticketId)) {
            $ticket = CustomerSupportTicket::findFirstByUuid($ticketId);
        }



        if (isset($ticket) && $ticket && $ticket->belongsToGms()) {

            $inputData = Helpers::__getRequestValuesArray();
            $attachments = isset($inputData['attachments']) ? $inputData['attachments'] : [];
            $inputData['customer_support_ticket_id'] = $ticket->getId();
            $inputData['user_profile_id'] = ModuleModel::$user_profile->getId();
            $reply = new CustomerSupportTicketAnswers();
            $reply->setData($inputData);
            $reply->setTypeReply(CustomerSupportTicketAnswers::TYPE_REPLY_DEFAULT);

            $resultCreate = $reply->__quickCreate();
            if ($resultCreate['success'] == false) {
                $return = $resultCreate;
                $return['message'] = 'REPLY_SUPPORT_TICKET_FAIL_TEXT';
                goto end_of_function;
            }

            $params = [];
            $params['objectUuid'] = $reply->getUuid();
            $params['userProfile'] = ModuleModel::$user_profile;
            $params['files'] = $attachments;
            //add attachment to reply
            $resultAttachment = MediaAttachment::__createAttachments($params);

            $data = $reply->toArray();
            $data['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();

            $return = ['success' => true, 'data' => $data, 'message' => 'REPLY_SUPPORT_TICKET_SUCCESS_TEXT', 'resultAttachment' => $resultAttachment];

        }


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * GET All Reply
     */

    public function repliesAction($ticketId)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($ticketId && Helpers::__checkId($ticketId)) {
            $ticket = CustomerSupportTicket::findFirstById($ticketId);
        }

        if ($ticketId && Helpers::__isValidUuid($ticketId)) {
            $ticket = CustomerSupportTicket::findFirstByUuid($ticketId);
        }

        if (isset($ticket) && $ticket && $ticket->belongsToGms()) {

            $replies = CustomerSupportTicketAnswers::find([
                'conditions' => 'customer_support_ticket_id = :ticket_id:',
                'bind' => [
                    'ticket_id' => $ticket->getId()
                ],
                'order' => 'created_at DESC'
            ]);

            $repliesArray = [];

            foreach ($replies as $replyItem) {
                $item = $replyItem->toArray();
                $item['user_profile_uuid'] = $replyItem->getUserProfile() ? $replyItem->getUserProfile()->getUuid() : '';
                $repliesArray[] = $item;
            }
            $return = [
                'success' => true,
                'data' => $repliesArray
            ];
        }


        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @todo : send to jira project : example : Relotalent Support
     * send email to admin
     * @param $data
     */
    public function sendToAdmin($ticket)
    {
        /*
        $subject = "New Support Ticket/Question";
        $subject_html = $subject . " - " . ModuleModel::$user_profile->getNickname() . " " . ModuleModel::$user_profile->getFirstname() . " " . ModuleModel::$user_profile->getLastname();

        $body = [
            "<p style='font-size:14px;'>" . $subject_html . "</p>",
            "<p style='font-size:14px;'>",
            "<strong>Firstname : </strong>" . ModuleModel::$user_profile->getFirstname() . "<br/>",
            "<strong>Lastname : </strong>" . ModuleModel::$user_profile->getLastname() . "<br/>",
            "<strong>Company : </strong>" . ModuleModel::$company->getName() . "<br/>",
            "<strong>Customer App URL : </strong>" . ModuleModel::$app->getFrontendUrl() . "<br/>",
            "<strong>URL : </strong>" . $ticket->getUrl() . "<br/>",
            "<strong>Subject : </strong>" . $ticket->getTitle() . "<br/>",
            "<strong>Message : </strong>" . $ticket->getDetail() . "<br/>",
            "</p>",
        ];
        $body_html = implode('', $body);
        $relodayBeanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
        $dataArray = [
            'action' => "sendMail",
            'replyto' => ModuleModel::$user_profile->getWorkemail(),
            'sender_name' => ModuleModel::$user_profile->getFullname(),
            'to' => getenv('SUPPORT_ADMIN_EMAIL'),
            'body' => $body_html,
            'subject' => $subject,
            'subject_html' => $subject_html,
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'withoutTemplate' => true,
        ];
        $resultCheck = $relodayBeanQueue->addQueue($dataArray);
        */

        $subject = $ticket->getTitle();

        $subject_html = $subject . " - " . ModuleModel::$user_profile->getFirstname() . " " . ModuleModel::$user_profile->getLastname();

        try {
            $body_issue =
                $subject_html . "\n" .
                "Firstname : " . ModuleModel::$user_profile->getFirstname() . "\n" .
                "Lastname : " . ModuleModel::$user_profile->getLastname() . "\n" .
                "Company : " . ModuleModel::$company->getName() . "\n" .
                "Customer App URL : " . ModuleModel::$app->getFrontendUrl() . "\n" .
                "URL : " . $ticket->getUrl() . "\n" .
                "Subject : " . $ticket->getTitle() . "\n" .
                "Message : " . $ticket->getDetail() . "\n" .
                "Severity: " . $ticket->getSeverity() . "\n";

            $attachments = MediaAttachment::__get_attachments_from_uuid($ticket->getUuid());
            if (isset($attachments)) {
                $body_issue = $body_issue . "Attachment: ";
                foreach ($attachments as $attachment) {
                    $body_issue = $body_issue . $attachment['url_backend'] . "\n";
                    //RelodayUrlHelper::__getBackendUrl() . "/backend/#/media/item/" . $attachment['uuid'] . "\n";
                }
            }
            $issueService = new IssueService();
            $issueField = new IssueField();

            $issueField->setProjectKey("RS")
                ->setSummary($subject_html)
                ->setIssueType("Bug")
                ->setDescription($body_issue)
                ->setAssigneeName('julien');

            $resultCreate = $issueService->create($issueField);

            $resultCheck = ['success' => true, 'number' => $resultCreate->key];

        } catch (JraException $e) {
            $resultCheck = ['success' => false, 'message' => 'CREATE_ISSUE_FAIL_TEXT'];
        }

        return $resultCheck;
    }
}
