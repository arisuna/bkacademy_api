<?php

namespace SMXD\Api\Controllers\API;

use Aws;
use \SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Task;
use SMXD\Api\Models\User;
use SMXD\Application\Models\DynamoCommentModel as CommentModel;

/**
 * Concrete implementation of Api module controller
 *
 * @RoutePrefix("/api/api")
 */
class MailController extends ModuleApiController
{
    /**
     * @Route("/mail", paths={module="api"}, methods={"GET"}, name="api-mail-index")
     */
    public function indexAction()
    {

    }

    /**
     *
     */
    public function load_snsAction()
    {
        //Load mail from SNS

        //if mail = task
        //=> parseMailContent with task => add comments to

        var_dump($this->request->getPost());
        die();

    }

    /**
     *
     */
    public function receiveAction()
    {

        $data = $this->request->getPost();
        $file = fopen($this->moduleConfig->application->mailsDir . "/" . date('YmdHis') . ".txt", "w+");
        fputs($file, json_encode($data));
        fclose($file);
        $this->view->disable();
        $this->response->setJsonContent(['success' => true]);
        return $this->response->send();

    }

    /**
     * save file notification
     */
    public function notificationAction()
    {
        $return = ['success' => false, 'message' => 'No Comments', 'method' => __METHOD__];
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $return = $this->sendNewComment($data);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function parsenotifyAction()
    {
        $return = ['success' => false, 'message' => 'No Comments', 'method' => __METHOD__];
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $return = $this->sendNewComment($data);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * test nofification
     */
    public function testnotifyAction()
    {
        $return = ['success' => false, 'message' => 'No Comments', 'method' => __METHOD__];
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $return['data'] = $data;
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * send comment to dynamodb
     * @param array $data
     */
    public function sendNewComment($data = array())
    {
        $return = ['success' => false, 'message' => 'No Comments'];
        $matches = [];

        if (isset($data['sender'])) {
            $sender = $data['sender'];
        } else {
            return $return;
        }

        if (isset($data['recipient'])) {
            $recipient = $data['recipient'];
        } else {
            return $return;
        }

        if (isset($data['stripped-text'])) {
            $comment_message = $data['stripped-text'];
        } else {
            return $return;
        }

        if (preg_match("#([a-z0-9A-Z-]+)\+([a-z0-9A-Z]+)\+task_([a-z0-9A-Z]+)#", $recipient, $matches)) {
            if (isset($matches[2]) && isset($matches[3])) {
                $user_uuid = base64_decode($matches[2]);
                $task_uuid = base64_decode($matches[3]);
                $task = Task::findFirstByUuid($task_uuid);
                if ($task) {
                    $users = $task->getMembers([
                        'conditions' => 'uuid = :uuid:',
                        'bind' => [
                            'uuid' => $user_uuid,
                        ],
                        'limit' => 1,
                    ]);

                    if ($users->count() > 0) {
                        $user = $users[0];
                        $params = [
                            'task' => $task,
                            'profile' => $user,
                            'message' => $comment_message
                        ];
                        $return = CommentModel::create($params);
                        //add new comment with $comment_message
                    }
                } elseif (Task::__existInCloud($task_uuid) == true) {
                    $params = [
                        'task_uuid' => $task,
                        'user_uuid' => $user_uuid,
                        'message' => $comment_message
                    ];
                    $return = CommentModel::create($params);
                } else {
                    $return = ['success' => false, 'message' => 'Task Not Exist'];
                }
            }

        } elseif (preg_match("#^task_([a-z0-9A-Z]+)_([a-z0-9A-Z]+)#", $recipient, $matches)) {
            if (isset($matches[1]) && isset($matches[2])) {
                $task_uuid = base64_decode($matches[1]);
                $email = base64_decode($matches[2]);
                $task = Task::findFirstByUuid($task_uuid);
                //add new comment with $comment_message
                if ($task) {
                    $params = [
                        'task' => $task,
                        'email' => $email,
                        'message' => $comment_message
                    ];
                    $return = CommentModel::create($params);
                } elseif (Task::__existInCloud($task_uuid) == true) {
                    $params = [
                        'task_uuid' => $task_uuid,
                        'email' => $email,
                        'message' => $comment_message
                    ];
                    $return = CommentModel::create($params);
                } else {
                    $return = ['success' => false, 'message' => 'Task Not Exist'];
                }
            }
        } elseif (preg_match("#task_([a-z0-9A-Z]+)#", $recipient, $matches)) {
            if (isset($matches[1])) {
                $task_uuid = base64_decode($matches[1]);
                $task = Task::findFirstByUuid($task_uuid);
                //add new comment with $comment_message
                if ($task) {
                    $data = [
                        'object_uuid' => $task->getUuid(),
                        'email' => $sender,
                        'message' => $comment_message
                    ];
                    $newComment = new CommentModel();
                    $newComment->addData($data);
                    $return = $newComment->create();
                } elseif (Task::__existInCloud($task_uuid) == true) {
                    $params = [
                        'object_uuid' => $task_uuid,
                        'email' => $sender,
                        'message' => $comment_message
                    ];
                    $newComment = new CommentModel();
                    $newComment->addData($data);
                    $return = $newComment->create();
                } else {
                    $return = ['success' => false, 'message' => 'Task Not Exist'];
                }
            }
        } else {
            $return = ['success' => false, 'message' => 'Recipient Not Valid'];
        }
        return $return;
    }

}
