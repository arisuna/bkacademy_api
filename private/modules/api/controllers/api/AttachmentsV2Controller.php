<?php

namespace SMXD\api\controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Controllers\api\BaseController;
use SMXD\Api\Models\Company;
use SMXD\Api\Models\User;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Api\Models\MediaAttachment;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Models\MediaAttachmentExt;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AttachmentsV2Controller extends BaseController
{
    const CONTROLLER_NAME = 'attachments';

    public function getAttachmentAction(): \Phalcon\Http\ResponseInterface
    {
        $this->view->disable();
        $this->checkAjax(['POST']);
        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $is_thumb = Helpers::__getRequestValue('is_thumb');

        if ($is_thumb == MediaAttachmentExt::IS_THUMB_YES) {
            $image = MediaAttachment::__getImageByObjUuidAndIsThumb($uuid, MediaAttachmentExt::IS_THUMB_YES);
        } else {
            $image = MediaAttachment::__getImageByObjUuidAndType($uuid, $type);
        }

        $result = ['success' => false, 'data' => '', 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($image) {
            $img = $image->toArray();

            $img['image_data']['url_thumb'] = $image->getTemporaryThumbS3Url();

            $result = [
                'success' => true,
                'data' => $img
            ];
        }

        $this->response->setJsonContent($result);
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
        $type = Helpers::__getRequestValue('type');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $return = MediaAttachment::__findWithFilter([
                'limit' => 20,
                'object_uuid' => $uuid,
                'object_name' => $type,
            ], $ordersConfig);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

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

            if (count($attachments) > 0) {
                $this->db->begin();
                $items = [];

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

                foreach ($attachments as $attachment) {
                    $attachResult = MediaAttachment::__createAttachment([
                        'objectUuid' => $uuid,
                        'file' => $attachment,
                        'objectName' => $type,
                        'userProfile' => ModuleModel::$user,
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

        if (!$mediaAttachment instanceof MediaAttachment) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'data' => $data];
            goto end_of_function;
        }

        $canDelete = true;
        switch ($mediaAttachment->getObjectName()) {
            case 'company':
                $company = Company::findFirstByUuid($mediaAttachment->getObjectUuid());
                if (!$company instanceof Company || $company->getId() != ModuleModel::$user->getCompanyId()) {
                    $canDelete = false;
                }

                break;
            case 'user_id_back':
            case 'user_id_front':
                $user = User::findFirstByUuid($mediaAttachment->getObjectUuid());
                if (!$user instanceof User || $user->getId() != ModuleModel::$user->getId()) {
                    $canDelete = false;
                }
                break;
            default:
                $canDelete = $mediaAttachment->belongsToUser();
                break;
        }



        if ($canDelete) {
            $resultDelete = $mediaAttachment->__quickRemove();
            if ($resultDelete['success']) {
                $return = ['success' => true, 'message' => 'FILE_DELETE_SUCCESS_TEXT', 'data' => $data];
                goto end_of_function;
            } else {
                $return = $resultDelete;
                $return['success'] = 'FILE_DELETE_SUCCESS_TEXT';
                goto end_of_function;
            }
        } else {
            $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT', 'data' => $data];
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
