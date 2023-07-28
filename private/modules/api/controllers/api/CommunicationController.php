<?php

namespace SMXD\Api\Controllers\API;

use Aws;
use \SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\App;
use SMXD\Api\Models\Assignment;
use SMXD\Api\Models\Company;
use SMXD\Api\Models\Media;
use SMXD\Api\Models\MediaAttachment;
use SMXD\Api\Models\Task;
use SMXD\Api\Models\User;
use SMXD\Api\Models\CommunicationTopic;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\SMXDDynamoORM;
use SMXD\Application\Lib\RelodayMailer;
use SMXD\Application\Lib\SMXDQueue;
use SMXD\Application\Lib\SMXDS3Helper;

/**
 * Concrete implementation of Api module controller
 *
 * @RoutePrefix("/api/api")
 */
class CommunicationController extends ModuleApiController
{
    /**
     * @Route("/communication", paths={module="api"}, methods={"GET"}, name="api-communication-index")
     */
    public function mailgunAction()
    {

        $this->view->disable();
        $return = ['success' => false, 'message' => 'No Comments', 'method' => __METHOD__];

        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $return = $this->createReplyEmail($data);
        }


        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function createReplyEmail($postData)
    {
        $random = new \Phalcon\Security\Random();
        $fileUuid = $random->uuid();
        $fileName = $fileUuid . ".json";
        $resultS3 = SMXDS3Helper::__uploadSingleFileWithExpiration(
            $fileName,
            json_encode($postData),
            getenv('AMAZON_BUCKET_EMAIL_INBOUND'),
            time() + 72 * 60 * 60
        );
        $resultSQS = false;

        $environment = isset($postData['domain']) && $postData['domain'] == 'communication.digitalexpat.com' ? 'local' : null;

        if ($resultS3['success'] == true) {



            if( isset($postData['recipient']) && $postData['recipient'] != '' ){
                $to = $postData['recipient'];
            }elseif( isset($postData['to']) && $postData['to'] != '' ){
                $to = $postData['to'];
            }elseif( isset($postData['To']) && $postData['To'] != '' ){
                $to = $postData['To'];
            }else{
                $to = null;
            }
            if( isset($postData['sender']) && $postData['sender'] != '' ){
                $from = $postData['sender'];
            }elseif( isset($postData['Sender']) && $postData['Sender'] != '' ){
                $from = $postData['Sender'];
            }elseif( isset($postData['from']) && $postData['from'] != '' ){
                $from = $postData['from'];
            }elseif( isset($postData['From']) && $postData['From'] != '' ){
                $from = $postData['From'];
            }elseif (isset($postData['X-Envelope-From'])) {
                $from = $postData['X-Envelope-From'];
            } else{
                $from = null;
            }

            $dataArray = [
                'action' => "receiveCommunication",
                'fileUuid' => $fileUuid,
                'domain' => isset($postData['domain']) ? $postData['domain'] : '',
                'messageId' => isset($postData['Message-Id']) ? $postData['Message-Id'] : '',
                'to' => $to,
                'from' => $from,
            ];
            $queueName = getenv('QUEUE_RECEIVE_COMMUNICATION');
            $relodayQueue = new SMXDQueue($queueName, $environment);
            $resultSQS = $relodayQueue->addQueue($dataArray);
        }


        return [
            'success' => true,
            'message' => 'EMAIL_LOADED',
            'method' => __METHOD__,
            'resultS3' => $resultS3,
            'resultSQS' => $resultSQS
        ];
    }
    /**
     * @Route("/communication", paths={module="api"}, methods={"GET"}, name="api-communication-index")
     */
    public function forwardAction()
    {

        $this->view->disable();
        $return = ['success' => false, 'message' => 'No Comments', 'method' => __METHOD__];

        if ($this->request->isPost()) {
            $postData = $this->request->getPost();
            $relodayQueue = new SMXDQueue(getenv('QUEUE_FORWARD_COMMUNICATION'));
            $data = [
                "action" => "forward",
                "data" => $postData["message-url"]
            ];
            $return = $relodayQueue->addQueue($data);
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


}
