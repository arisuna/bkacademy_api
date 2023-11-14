<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 4/23/20
 * Time: 2:38 PM
 */

namespace SMXD\Api\Controllers\API;


use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use Phalcon\Utils\Slug as PhpSlug;
use SMXD\App\Models\ObjectAvatar;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HttpStatusCode;
use SMXD\Application\Models\ObjectAvatarExt;

class ObjectAvatarController extends BaseController
{
    /**
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     */
    public function uploadAction()
    {
        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }

        $object_uuid = $this->request->getQuery('objectUuid');
        $object_name = $this->request->getQuery('objectName');

        if ($object_uuid == '') {
            $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
            goto end_upload_function;
        }

        $company = ModuleModel::$company;
        $files = $this->request->getUploadedFiles();
        $file = $files[0];

        $random = new Random();
        $uuid = $random->uuid();
        $file_info = new \stdClass();
        $file_info->name = basename($file->getName(), '.' . strtolower($file->getExtension()));
        $file_info->basename = PhpSlug::generate(basename($file->getName(), '.' . strtolower($file->getExtension())));
        $file_info->extension = strtolower($file->getExtension());
        $file_info->type = $file->getType();
        $file_info->size = $this->formatBytes($file->getSize());
        $file_info->key = $file->getKey();
        $file_info->real_type = $file->getRealType();

        /** CHECK FILE TYPE IMAGE */

        if (!in_array($file_info->extension, ObjectAvatar::$ext_groups[ObjectAvatar::FILE_TYPE_IMAGE])) {
            $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
            goto end_upload_function;
        }


        /** REMOVE IF EXISTED */
        $existed_avatar = ObjectAvatar::__getImageByObjectUuid($object_uuid, 'object');
        if ($existed_avatar) {
            $delete = $existed_avatar->__quickRemove();
            if ($delete['success'] == false) {
                $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
                goto end_upload_function;
            }
        }
        /** SAVE TO DATABASE */
        $user_avatar = new ObjectAvatar();
        $user_avatar->setName($file_info->name);
        $user_avatar->setObjectUuid($object_uuid);
        $user_avatar->setObjectName($object_name);
        $user_avatar->setCompanyUuid($company ? $company->getUuid() : '');
        $user_avatar->setUuid($uuid);
        $user_avatar->setFilename($object_uuid . '.' . strtolower($file_info->extension));
        $user_avatar->setFileExtension(strtolower($file_info->extension));
        $user_avatar->setFileType(ObjectAvatarExt::__getFileType($file_info->extension));
        $user_avatar->setMimeType($file_info->type);
        $user_avatar->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $user_avatar->loadMediaType();
        $user_avatar->addDefaultFilePath();

        $resultSaveUserAvatar = $user_avatar->__quickCreate();


        if ($resultSaveUserAvatar['success'] == false) {
            $return = $resultSaveUserAvatar;
            if (is_array($return['detail'])) {
                $return['message'] = reset($return['detail']);
            } else {
                $return['message'] = 'UPLOAD_FAIL_TEXT';
            }
            goto end_upload_function;
        }
        $resultUpload = $user_avatar->uploadToS3FromPath($file->getTempName());
        if ($resultUpload['success'] == false) {
            $return = $resultUpload;
            $return['message'] = "UPLOAD_FAIL_TEXT";
            goto end_upload_function;
        }
        $return['success'] = true;
        $return['message'] = 'UPLOAD_SUCCESS_TEXT';
        $return['data'] = array_merge((array)$file_info, $user_avatar->getParsedData());
        end_upload_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    public function uploadV2Action()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $company = ModuleModel::$company;
        $name = Helpers::__getRequestValue('name');
        $extension = Helpers::__getRequestValue('extension');
        $type = Helpers::__getRequestValue('type');
        $size = Helpers::__getRequestValue('size');

        /** Set data to media dynamo*/
        $object_uuid = Helpers::__getRequestValue('objectUuid');
        $object_name = Helpers::__getRequestValue('objectName');

        if ($object_uuid == '') {
            $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
            goto end_upload_function;
        }


        /** REMOVE IF EXISTED */
        $existed_avatar = ObjectAvatar::__getImageByObjectUuid($object_uuid, 'object');
        if ($existed_avatar) {
            $delete = $existed_avatar->__quickRemove();
            if (!$delete['success']) {
                $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
                goto end_upload_function;
            }
        }
        $random = new Random();
        $uuid = $random->uuid();

        /** SAVE TO DATABASE */
        $user_avatar = new ObjectAvatar();
        $user_avatar->setName($name);
        $user_avatar->setObjectUuid($object_uuid);
        $user_avatar->setObjectName($object_name);
        $user_avatar->setCompanyUuid($company ? $company->getUuid() : '');
        $user_avatar->setUuid($uuid);
        $user_avatar->setFilename($object_uuid . '.' . strtolower($extension));
        $user_avatar->setFileExtension(strtolower($extension));
        $user_avatar->setFileType(ObjectAvatarExt::__getFileType($extension));
        $user_avatar->setMimeType($type);
        $user_avatar->setUserProfileUuid(ModuleModel::$user->getUuid());
        $user_avatar->loadMediaType();
        $user_avatar->addDefaultFilePath();
        $user_avatar->setCreatedAt(time());
        $user_avatar->setUpdatedAt(time());

        $return = $user_avatar->__quickCreate();
        if (!$return['success']) {
            if (is_array($return['detail'])) {
                $return['message'] = reset($return['detail']);
            } else {
                $return['message'] = 'UPLOAD_FAIL_TEXT';
            }
            goto end_upload_function;
        } else {

            $return['data'] = $user_avatar->getParsedData();
        }

        end_upload_function:
        if (!$return['success']) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    /**
     * Get Avatar Url
     * @return array
     */
    public function getAvatarAction()
    {
        $this->checkAjaxPut();
        $return = ['success' => true, 'data' => ''];
        $uuid = Helpers::__getRequestValue('uuid');
        $userAvatar = ObjectAvatar::__getImageByObjectUuid($uuid);
        if ($userAvatar) {
            $return['data'] = $userAvatar->getUrlThumb();
        }

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /** Format Size of file */
    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * @param $object_uuid
     */
    public function removeAvatarAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        /***** check attachments permission ***/
        if (is_null($object_uuid) || is_null($object_uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $objectAvatar = ObjectAvatar::__getImageByObjectUuid($object_uuid, 'object');

        if (!$objectAvatar) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $resultDelete = $objectAvatar->__quickRemove();

        if ($resultDelete['success'] == false) {
            $return = $resultDelete;
            $return['message'] = 'FILE_DELETE_FAIL_TEXT';
            goto end_of_function;
        }

        $return = ['success' => true, 'message' => 'FILE_DELETE_SUCCESS_TEXT'];
        goto end_of_function;

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $user_uuid
     * @return mixed
     */
    public function getObjectAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $user_avatar = ObjectAvatar::__getImageByObjectUuid($uuid);
        $result = ['success' => false, 'data' => '', 'message' => 'AVATAR_NOT_FOUND_TEXT'];
        if ($user_avatar && !is_null($user_avatar)) {

            $avatar = $user_avatar->__toArray();
            $avatar['image_data']['url_thumb'] = $user_avatar->getUrlThumb() ? $user_avatar->getUrlThumb() : $user_avatar->getTemporaryThumbS3Url();

            $result = [
                'success' => true,
                'data' => $avatar
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
