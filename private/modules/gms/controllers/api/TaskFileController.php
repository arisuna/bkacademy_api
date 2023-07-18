<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\NotificationHelpers;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Notification;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\TaskFile;
use Reloday\Gms\Models\TaskTemplate;

class TaskFileController extends BaseController
{
    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $objectUuid = Helpers::__getRequestValue('object_uuid');
        $taskFiles = TaskFile::findByObjectUuid($objectUuid);


        $return = [
            'success' => true,
            'data' => $taskFiles
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $isNew = false;
        $item = Helpers::__getRequestValue('item');
        $objectUuid = Helpers::__getRequestValue('object_uuid');
        $ignore = Helpers::__getRequestValue('ignore');

        $this->db->begin();
        $object = TaskTemplate::findFirstByUuid($objectUuid);
        if (!$object) {
            $object = Task::findFirstByUuid($objectUuid);
        }

        if (!$object) {
            goto end_of_function;
        }

        if ($item && is_object($item)) {
            $item = (array)$item;
        }

        if (isset($item['media']) && is_object($item['media'])) {
            $item['media'] = (array)$item['media'];
        }

        if (isset($item['id']) && $item['id'] > 0) {
            $taskFile = TaskFile::findFirstById($item['id']);
        } else {
            $existedFile = TaskFile::__checkExistedTaskFile($object->getUuid(), $item['media']['uuid'], ModuleModel::$company->getUuid());
            if($existedFile){
                $return['message'] = "MEDIA_ALREADY_ATTACHED_TEXT";
                goto end_of_function;
            }

            $taskFile = new TaskFile();
            $taskFile->setUuid(Helpers::__uuid());
            $isNew = true;
        }

        $taskFile->setObjectUuid($object->getUuid());
        $taskFile->setCompanyUuid(ModuleModel::$company->getUuid());

        if (!isset($ignore) || $ignore != 'name' ) {
            if ($item['name']) {
                $taskFile->setName($item['name']);
            } else {
                $taskFile->setName($item['media']['name']);
            }
        }

        if ($item['is_request_file']) {
            $taskFile->setIsRequestFile(ModelHelper::YES);
        } else {
            $taskFile->setIsRequestFile(ModelHelper::NO);
        }

        $taskFile->setMediaUuid($item['media']['uuid']);

        $resultTaskFile = $taskFile->__quickSave();

        if (!$resultTaskFile['success']) {
            $return = $resultTaskFile;
            $this->db->rollback();
            $return['message'] = 'FILE_SAVE_FAILED_TEXT';
            goto end_of_function;
        }

        //Check has file
        $object->checkHasFile();

        $return['success'] = true;
        $return['message'] = 'FILE_SAVE_SUCCESS_TEXT';
        $return['data'] = $taskFile->parsedDataToArray();
        $this->db->commit();
        end_of_function:

        if($return['success']){
            if($object instanceof Task && $isNew){
                if($object->getTaskType() == Task::TASK_TYPE_INTERNAL_TASK){
                    $return['$apiResults'] = NotificationServiceHelper::__addNotification($object, HistoryModel::TYPE_TASK, HistoryModel::HISTORY_ADD_ATTACHMENT,  [
                        'file_name' => $taskFile->getName()
                    ]);
                }

                ModuleModel::$task = $object;
                ModuleModel::$taskFile = $taskFile;
                $this->dispatcher->setParam('return', $return);
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function deleteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];


        $taskFile = TaskFile::findFirstByUuid($uuid);
        ModuleModel::$taskFile = $taskFile;
        if ($taskFile) {
            $object = TaskTemplate::findFirstByUuid($taskFile->getObjectUuid());
            if (!$object) {
                $object = Task::findFirstByUuid($taskFile->getObjectUuid());
            }

            $this->db->begin();
            //Remove All Return file.
            $returnFiles = $taskFile->getReturnFiles();
            $returnFiles->delete();

            $return = $taskFile->__quickRemove();

            if (!$return['success']) {
                $this->db->rollback();
            } else {
                $this->db->commit();
                $object->checkHasFile();
            }
        }

        end_of_function:
        if($return['success']){
            if($object instanceof Task){
                ModuleModel::$task = $object;
                $this->dispatcher->setParam('return', $return);
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return void
     */
    public function listReturnAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $objectUuid = Helpers::__getRequestValue('object_uuid');
        $taskFiles = TaskFile::__findReturnByObjectUuid($objectUuid);


        $return = [
            'success' => true,
            'data' => $taskFiles
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}