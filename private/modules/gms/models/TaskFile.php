<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class TaskFile extends \Reloday\Application\Models\TaskFileExt
{

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();

        $this->belongsTo('media_uuid', 'Reloday\Gms\Models\Media', 'uuid', [
            'alias' => 'Media'
        ]);

        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\Task', 'uuid', [
            'alias' => 'Task'
        ]);
	}

    public function belongsToCompany(){
        return ModuleModel::$company->getUuid() == $this->getCompanyUuid();
    }

    /**
     * @return mixed
     */
    public function parsedDataToArray(){
        $item = $this->toArray();
        $media = $this->getMedia() ? $this->getMedia()->getParsedData() : null;
        $item['media'] = $media;
        $item['return_files'] = $this->getParsedReturnFiles();
        $item['isEdit'] = false;
        $item['created_at'] = strtotime($this->getCreatedAt()) * 1000;
        $item['updated_at'] = strtotime($this->getUpdatedAt()) * 1000;
        $item['isEditable'] = $this->belongsToCompany();
        return $item;
    }

    /**
     * @param $objectUuid
     * @return array
     */
    public static function findByObjectUuid($objectUuid = ""){
        $data = [];

        if(!$objectUuid){
            return $data;
        }

        $taskFiles =  self::find([
            'conditions' => 'object_uuid = :object_uuid: and is_return_file = :is_return_file:',
            'bind' => [
                'object_uuid' => $objectUuid,
                'is_return_file' => ModelHelper::NO
            ]
        ]);

        foreach ($taskFiles as $taskFile){
            $item = $taskFile->parsedDataToArray();
            $data[] = $item;
        }
        return $data;
    }

    /**
     * Clone task file to AssigneeFolder
     */
    public function cloneToAssigneeFolder(){
        //Clone to folder assignee
        $task = $this->getTask();

        if($task && $task->getTaskType() == Task::TASK_TYPE_EE_TASK){
            $folders['success'] = false;
            if($task->getLinkType() == Task::LINK_TYPE_ASSIGNMENT){
                $assignment = $task->getAssignment();
                $folders = $assignment->getFolders();
            }

            if($task->getLinkType() == Task::LINK_TYPE_RELOCATION){
                $relocation = $task->getRelocation();
                $folders = $relocation->getFolders();
            }

            if($folders['success']){
                $folderEE = isset($folders['object_folder_dsp_ee']) ? $folders['object_folder_dsp_ee'] : null;

                if($folderEE){
                    $eeAttachments = MediaAttachment::__findWithFilter([
                        'limit' => 1,
                        'page' => 1,
                        'object_uuid' => $folderEE->getUuid(),
                        'media_uuid' => $this->getMediaUuid(),
                    ]);
                    if ($eeAttachments['success'] && count($eeAttachments['data']) > 0) {
                        return ['success' => true];
                    }

                    $attachment = new MediaAttachment();
                    $attachment->setUuid(Helpers::__uuid());
                    $attachment->setObjectUuid($folderEE->getUuid());
                    $attachment->setMediaUuid($this->getMediaUuid());
                    $attachment->setMediaId($this->getMedia() ? $this->getMedia()->getId() : null);
                    $attachment->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
                    $attachment->setOwnerCompanyId(ModuleModel::$company->getId());
                    $attachment->setIsShared(ModelHelper::NO);
                    $attachment->setObjectName(MediaAttachment::MEDIA_OBJECT_DEFAULT_NAME);
                    $attachment->setCreatedAt(date('Y-m-d H:i:s'));
                    $attachment->setUpdatedAt(date('Y-m-d H:i:s'));

                    $resultAttachment = $attachment->__quickCreate();

                    return $resultAttachment;
                }

            }
        }

        return ['success' => true];
    }

    public function getParsedReturnFiles(){
        $returnFiles = self::find([
            'conditions' => 'is_return_file = :is_return_file: and task_file_uuid = :task_file_uuid:',
            'bind' => [
                'is_return_file' => ModelHelper::YES,
                'task_file_uuid' => $this->getUuid()
            ]
        ]);

        $data = [];

        foreach ($returnFiles as $returnFile){
            $item = $returnFile->toArray();
            $media = $returnFile->getMedia() ? $returnFile->getMedia()->getParsedData() : null;
            $item['media'] = $media;
            $data[] = $item;
        }

        return $data;
    }


    /**
     * @param $objectUuid
     * @return array
     */
    public static function __findReturnByObjectUuid($objectUuid = ""){
        $data = [];

        if(!$objectUuid){
            return $data;
        }

        $taskFiles =  self::find([
            'conditions' => 'object_uuid = :object_uuid: and is_return_file = :is_return_file: and (task_file_uuid is null or task_file_uuid = "")',
            'bind' => [
                'object_uuid' => $objectUuid,
                'is_return_file' => ModelHelper::YES
            ]
        ]);

        foreach ($taskFiles as $taskFile){
            $item = $taskFile->parsedDataToArray();
            $data[] = $item;
        }
        return $data;
    }
}
