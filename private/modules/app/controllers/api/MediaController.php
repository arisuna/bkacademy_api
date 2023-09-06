<?php

namespace SMXD\app\controllers\api;

use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HttpStatusCode;
use SMXD\Application\Lib\ModelHelper;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\Media;
use SMXD\Application\Lib\SMXDS3Helper;
use SMXD\Application\Models\ApplicationModel;

class MediaController extends BaseController
{
    public function uploadAction()
    {
//        $this->checkAclUpload(AclHelper::CONTROLLER_MEDIA);

        $isPublic = $this->request->getQuery('isPublic');
        $name = Helpers::__getRequestValue('name');
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $type = Helpers::__getRequestValue('type');
        $size = Helpers::__getRequestValue('size');
        $uuid = Helpers::__getRequestValue('uuid');
        $companyId = Helpers::__getRequestValue('company_id');

        $media = new Media();
        $media->setName(pathinfo($name)['filename']);
        $media->setNameStatic(pathinfo($name)['filename']);
        $media->setCompanyId($companyId ? (int)$companyId : 0);
        $media->setUuid($uuid);
        $media->setFileName($uuid . '.' . strtolower($extension));
        $media->setFileExtension(strtolower($extension));
        $media->setFileType(Media::__getFileType($extension));
        $media->setMimeType($type);
        $media->loadMediaType();
        $media->setUserUuid(ModuleModel::$user->getUuid());
        $media->setIsHosted(intval(ModelHelper::YES));
        $media->setIsPrivate(intval(ModelHelper::YES));
        $media->setIsDeleted(intval(ModelHelper::NO));
        $media->setIsHidden(intval(ModelHelper::NO));
        $media->setSize($size);
        if ($isPublic) {
            $media->setIsPrivate(intval(ModelHelper::NO));
        }

        $checkFileExisted = Media::findFirst([
            "conditions" => "name = :name: and file_extension = :file_extension: and user_uuid = :user_uuid: and is_deleted=:is_deleted_no:",
            "bind" => [
                'name' => $media->getName(),
                'file_extension' => $media->getFileExtension(),
                'user_uuid' => $media->getUserUuid(),
                'is_deleted_no' => Media::IS_DELETE_NO,
            ]
        ]);
        $is_created_media = false;

        if ($checkFileExisted) {
            $old_size = $checkFileExisted->getSize();
            $is_created_media = true;
            $media->setUuid($checkFileExisted->getUuid());
            $media->setId($checkFileExisted->getId());
        }

        /** SAVE TO DYNAMO DB DATABASE */
        $this->db->begin();
        if ($is_created_media) {
            $resultSaveMedia = $media->__quickUpdate();
        } else {
            $resultSaveMedia = $media->__quickCreate();
        }

        if (!$resultSaveMedia['success']) {
            $return = $resultSaveMedia;
            if (isset($return['errorMessage']) && is_array($return['errorMessage'])) {
                $return['message'] = reset($return['errorMessage']);
            } else {
                $return['message'] = 'UPLOAD_FAIL_TEXT';
            }
            goto end_upload_function;
        }

        $this->db->commit();

        $return['success'] = true;
        $return['message'] = 'UPLOAD_SUCCESS_TEXT';
        $return['data'] = $media->getParsedData();

        end_upload_function:
        if (!$return['success']) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    public function removeAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        /***** check attachments permission ***/
        if (is_null($uuid) || is_null($uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $media = Media::findFirstByUuid($uuid);


        if (!$media) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $countParams = [];
        /** REMOVE MEDIA DYNAMO DB */
        $media = Media::findFirstByUuid($uuid);
        $removeMedia = $media->__quickRemove();

        if ($removeMedia['success'] == false) {
            $return = $removeMedia;
            $return['message'] = "FILE_DELETE_FAIL_TEXT";
            goto end_of_function;
        }

        $return = ['success' => true, 'message' => 'FILE_DELETE_SUCCESS_TEXT'];

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    public function getUploadUrlAction()
    {
        $fileName = Helpers::__getRequestValue('name');
        $fileType = Helpers::__getRequestValue('type');
        $fileObject = Helpers::__getRequestValue('object');
        $objectUuid = Helpers::__getRequestValue('objectUuid');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $checkFileExisted = Media::findFirst([
            "conditions" => "name = :name: and file_extension = :file_extension: and user_uuid = :user_uuid: and is_deleted=:is_deleted_no:",
            "bind" => [
                'name' => pathinfo($fileName)['filename'],
                'file_extension' => $extension,
                'user_uuid' => ModuleModel::$user->getUuid(),
                'is_deleted_no' => Media::IS_DELETE_NO,
            ]
        ]);


        if ($checkFileExisted) {
            $uuid = $checkFileExisted->getUuid();
        } else {
            $uuid = Helpers::__getRequestValue('uuid');
            if (!Helpers::__isValidUuid($uuid)) {
                $uuid = ApplicationModel::uuid();
            }
        }

        $fileNameUpload = 'object_image_default/' . $uuid . '/' . $uuid . '.' . $extension;
        if ($objectUuid) {
            $fileNameUpload = $objectUuid . '/' . $uuid . '.' . $extension;
        }
        if ($fileObject) {
            $fileNameUpload = $fileObject . $uuid . '.' . $extension;
            if ($objectUuid) {
                $fileNameUpload = $fileObject . '/' . $objectUuid . '/' . $uuid . '.' . $extension;
            }
        }

        $return = SmxdS3Helper::__getPresignedUrlToUpload($fileNameUpload, $fileType);

        $return['uuid'] = $uuid;
        $return['$fileNameUpload'] = $fileNameUpload;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}