<?php

namespace SMXD\app\controllers\api;

use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\App\Models\MediaAttachment;
use SMXD\App\Models\Media;
use SMXD\App\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AttachmentsController extends BaseController
{
    const CONTROLLER_NAME = 'attachments';

    public function getAttachmentAction(): \Phalcon\Http\ResponseInterface
    {
        $this->view->disable();
        $this->checkAjax(['POST']);

        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');

        $image = MediaAttachment::__getImageByObjUuidAndType($uuid, $type);

        $result = ['success' => false, 'data' => '', 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($image) {
            $img = $image->__toArray();

            $result = [
                'success' => true,
                'data' => $img
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Remove Attachment
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function removeAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        /***** check attachments permission ***/

        $data = $this->request->getJsonRawBody();
        $object_uuid = $data->object_uuid ?? null;
        $object_name = $data->object_name ?? null;
        $media_uuid = $data->media_uuid ?? null;
        $media_attachment_uuid = $data->media_attachment_uuid ?? null;
        if (is_null($object_uuid) || is_null($media_uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT', 'data' => $data];
            goto end_of_function;
        }

        if ($media_attachment_uuid && Helpers::__getRequestValue('media_attachment_uuid')) {
            $mediaAttachment = MediaAttachment::findFirstByUuid($media_attachment_uuid);
        } else {
            $mediaAttachment = MediaAttachment::findFirst([
                "conditions" => "object_uuid = :object_uuid: AND media_uuid = :media_uuid:",
                "bind" => [
                    "object_uuid" => $object_uuid,
                    "media_uuid" => $media_uuid
                ]
            ]);
        }

        if ($mediaAttachment) {
//            if ($canDeleteAll['success'] == true) {
            $resultDelete = $mediaAttachment->__quickRemove();
            if ($resultDelete['success']) {
                $return = ['success' => true, 'message' => 'ATTACHMENT_DELETE_SUCCESS_TEXT', 'data' => $data];
                goto end_of_function;
            } else {
                $return = $resultDelete;
                $return['success'] = 'ATTACHMENT_DELETE_FAIL_TEXT';
                goto end_of_function;
            }
//            }


//            if ($canDeleteOwn['success'] == true) {
            if ($mediaAttachment->belongsToUser()) {
                //if belong to User and Delete All
                $resultDelete = $mediaAttachment->__quickRemove();
                if ($resultDelete['success'] == true) {
                    $return = ['success' => true, 'message' => 'ATTACHMENT_DELETE_SUCCESS_TEXT', 'data' => $data];
                    goto end_of_function;
                } else {
                    $return = $resultDelete;
                    $return['success'] = 'ATTACHMENT_DELETE_FAIL_TEXT';
                    goto end_of_function;
                }
            } else {
                $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT', 'data' => $data];
                goto end_of_function;
            }
//            }
        } else {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'data' => $data];
            goto end_of_function;
        }
        goto end_of_function;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Attachment only 1 file to object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function attachSingleFileAction()
    {

        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclCreate(self::CONTROLLER_NAME);

        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $file = Helpers::__getRequestValueAsArray('file');
        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');

        if ($uuid != '' &&
            Helpers::__isValidUuid($uuid) &&
            (is_array($file) || is_object($file))) {
            $return = MediaAttachment::__create_attachment_from_uuid($uuid, $file, $type, $shared, ModuleModel::$user);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Attachm multiple files in object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function attachMultipleFilesAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
//        $this->checkAclCreate(self::CONTROLLER_NAME);

        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $entity_uuid = Helpers::__getRequestValue('entity_uuid');
        $attachments = Helpers::__getRequestValue('attachments');
        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');

        if ($uuid != '' &&
            Helpers::__isValidUuid($uuid) &&
            is_array($attachments) &&
            count($attachments) > 0) {

            if($type == MediaAttachment::USER_ID_FRONT || $type == MediaAttachment::USER_ID_BACK){
                $existedAttachment = MediaAttachment::findFirst([
                    'conditions' => 'user_uuid = :user_uuid: and object_uuid = :object_uuid: and object_name = :type:',
                    'bind' =>[
                        'user_uuid' => ModuleModel::$user->getUuid(),
                        'object_uuid' => ModuleModel::$user->getUuid(),
                        'type' => $type
                    ]
                ]);
                if($existedAttachment){
                    $existedAttachment->__quickRemove();
                }
            }

            if($type == MediaAttachment::COMPANY_VERIFICATION){
                $existedAttachments = MediaAttachment::find([
                    'conditions' => 'object_uuid = :object_uuid: and object_name = :type:',
                    'bind' =>[
                        'object_uuid' => $uuid,
                        'type' => $type
                    ]
                ]);
                if($existedAttachments && count($existedAttachments) > 0){
                    foreach ($existedAttachments as $existed){
                        $existed->__quickRemove();
                    }
                }
            }

            if (count($attachments) > 0) {
                $this->db->begin();
                $items = [];
                foreach ($attachments as $attachment) {
                    $attachResult = MediaAttachment::__createAttachment([
                        'objectUuid' => $uuid,
                        'file' => $attachment,
                        'objectName' => $type,
                        'user' => ModuleModel::$user,
                    ]);

                    if ($attachResult['success'] == true) {
                        //share to my own company
                        $mediaAttachment = $attachResult['data'];
//                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $uuid], ModuleModel::$company);
//                        if ($shareResult['success'] == false) {
//                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
//                            goto end_of_function;
//                        }
                        $items[] = $mediaAttachment;
                    } else {
                        $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $attachResult, 'message' => $attachResult['message']];
                        goto end_of_function;
                    }
                }
                $this->db->commit();
                $return = ['success' => true, 'data' => $items, 'message' => 'ATTACH_SUCCESS_TEXT'];
                goto end_of_function;
            }
            /*
            $return = MediaAttachment::__createAttachments([
                'objectUuid' => $uuid,
                'objectName' => $type,
                'isShared' => $shared,
                'fileList' => $attachments,
                'userProfile' => ModuleModel::$user,
                'entityUuid' => $entity_uuid
            ]);
            */
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * List attachment of object
     * @param string $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function listAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid == '') $uuid = Helpers::__getRequestValue('uuid');
        $shared = Helpers::__getRequestValue('shared');
        $type = Helpers::__getRequestValue('type');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $return = MediaAttachment::__findWithFilter([
                'limit' => 1000,
                'object_uuid' => $uuid,
                'object_name' => $type,
            ]);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function listSharedAction($uuid)
    {
        $this->view->disable();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $this->checkAjaxGet();

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $attachments = MediaAttachment::__get_attachments_from_uuid($uuid, null, MediaAttachment::IS_SHARED_YES, null, null, ModuleModel::$company->getUuid());
            $return = ['success' => true, 'data' => $attachments];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function changeThumbAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $uuid = Helpers::__getRequestValue('uuid');

        if (is_null($uuid) || !Helpers::__isValidUuid($uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $media = MediaAttachment::findFirstByUuid($uuid);

        if (!$media) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $mediaIsThumb = MediaAttachment::findFirst([
            "conditions" => "is_thumb = :is_thumb: and object_uuid = :object_uuid:",
            "bind" => [
                'object_uuid' => $media->getObjectuuid(),
                'is_thumb' => MediaAttachment::IS_THUMB_YES

            ]
        ]);
        if ($mediaIsThumb) {
            $mediaIsThumb->setIsThumb(MediaAttachment::IS_THUMB_FALSE);
            $resReset = $mediaIsThumb->__quickUpdate();
            if (!$resReset['success']) {
                $return = $resReset;
                $return['message'] = 'DATA_SAVE_FAIL_TEXT';
                goto end_of_function;
            }
        }

        $media->setIsThumb(MediaAttachment::IS_THUMB_YES);

        $res = $media->__quickUpdate();
        $return = ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
        if (!$res['success']) {
            $return = $res;
            $return['message'] = 'DATA_SAVE_FAIL_TEXT';
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

}
