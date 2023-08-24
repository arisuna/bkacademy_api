<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 4/29/20
 * Time: 1:47 PM
 */

namespace SMXD\app\controllers\api;


use Phalcon\Security\Random;
use Phalcon\Validation;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\FileHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HttpStatusCode;
use SMXD\App\Controllers\API\BaseController;
use SMXD\App\Models\ModuleModel;
use Phalcon\Utils\Slug as PhpSlug;
use SMXD\App\Models\ObjectAvatar;


class ObjectImageController extends BaseController
{

    /**
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     */
    public function uploadAvatarAction()
    {
        return $this->doUploadImage('avatar');
    }

    /**
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     */
    public function uploadLogoAction()
    {
        return $this->doUploadImage('logo');
    }

    /**
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     */
    public function uploadSquaredLogoAction()
    {
        return $this->doUploadImage('squared_logo');
    }

    /**
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     */
    public function uploadRectangularLogoAction()
    {
        return $this->doUploadImage('rectangular_logo');
    }

    /**
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     */
    public function uploadLogoLoginAction()
    {
        return $this->doUploadImage('logo_login');
    }


    public function uploadIconAction()
    {
        return $this->doUploadImage('icon');
    }

    /**
     * Get Avatar Url
     * @return array
     */
    public function getImageAction()
    {
        $this->checkAjaxPut();
        $return = ['success' => true, 'data' => ''];
        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $image = ObjectAvatar::__getImageByUuidAndType($uuid, $type);
        if ($image) {
            $return['data'] = $image->getUrlThumb();
        }

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $size
     * @param int $precision
     * @return string
     */
    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * @param $object_uuid
     */
    public function removeImageAction($object_uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        /***** check attachments permission ***/
        if (is_null($object_uuid) || is_null($object_uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $type = Helpers::__getRequestValue('type');

        $image = ObjectAvatar::__getImageByUuidAndType($object_uuid, $type, 'object');

        if (!$image)   {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($image) {
            $resultDelete = $image->__quickRemove();

            if ($resultDelete['success'] == false) {
                $return = $resultDelete;
                $return['message'] = 'FILE_DELETE_FAIL_TEXT';
                goto end_of_function;
            }

            $return = ['success' => true, 'message' => 'FILE_DELETE_SUCCESS_TEXT'];
            goto end_of_function;


        } else {
            $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            goto end_of_function;
        }


        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $user_uuid
     * @return mixed
     */
    public function getObjectAction()
    {
        $this->view->disable();
        $this->checkAjax(['POST']);

        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');

        $image = ObjectAvatar::__getImageByUuidAndType($uuid, $type);


        $result = ['success' => false, 'data' => '', 'message' => 'AVATAR_NOT_FOUND_TEXT'];
        if ($image && !is_null($image)) {

            $img = $image->__toArray();
            $img['image_data']['url_thumb'] = $image->getUrlThumb() ? $image->getUrlThumb() : $image->getTemporaryThumbS3Url();

            $result = [
                'success' => true,
                'data' => $img
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * $type = 'logo|avatar|image'
     * @param String $type
     * @throws \Phalcon\Exception
     */
    public function doUploadImage(String $type)
    {

        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }

        $object_uuid = $this->request->getQuery('objectUuid');
        $object_name = $this->request->getQuery('objectName');

        if ($object_uuid == '' || $object_name == '') {
            $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
            goto end_upload_function;
        }

        $files = $this->request->getUploadedFiles();
        $file = $files[0];
        $imageContent = '';

        $isImage = FileHelper::__isImage($file->getExtension());

        if ($isImage == false) {
            $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
            goto end_upload_function;
        }

        //resize logoGD
        if (extension_loaded('gd')) {
            $image = new \Phalcon\Image\Adapter\GD($file->getTempName());
        } elseif (extension_loaded('imagick')) {
            $image = new \Phalcon\Image\Adapter\Imagick($file->getTempName());
        } else {
            $return = ['success' => false, 'message' => 'Can not resize image'];
            goto end_upload_function;
        }

        if ($type == 'squared_logo' || $type == 'logo') {
            $image->resize(
                300,
                null,
                \Phalcon\Image::WIDTH
            );
//            $width = 300;
//            $height = 300;
//            $offsetX = 0;
//            $offsetY = (($image->getHeight() - $height) / 2);
//            $image->crop($width, $height, $offsetX, $offsetY);
            $imageContent = $image->render();
        } elseif ($type == 'avatar') {
            $image->resize(
                250,
                null,
                \Phalcon\Image::WIDTH
            );
//            $width = 250;
//            $height = 250;
//            $offsetX = 0;
//            $offsetY = (($image->getHeight() - $height) / 2);
//            $image->crop($width, $height, $offsetX, $offsetY);
            $imageContent = $image->render();
        } elseif ($type == 'icon') {
            $image->resize(
                100,
                null,
                \Phalcon\Image::WIDTH
            );
            $imageContent = $image->render();
        }elseif ($type == 'rectangular_logo') {
            $image->resize(
                500,
                null,
                \Phalcon\Image::WIDTH
            );
            $imageContent = $image->render();
        }

        $uuid = Helpers::__uuid();
        $file_info = FileHelper::__getFileInfo($file);


        /** REMOVE IF EXISTED */
        $existed_avatar = ObjectAvatar::__getImageByUuidAndType($object_uuid, $object_name, 'object');

        if ($existed_avatar) {
            $existed_avatar->__quickRemove();
        }

        /** SAVE TO DATABASE */
        $obj_image = new ObjectAvatar();
        $obj_image->setName($file_info->name);
        $obj_image->setObjectUuid($object_uuid);
        $obj_image->setObjectName($object_name);
        $obj_image->setUuid($uuid);
        $obj_image->setFilename($uuid . '.' . $file_info->extension);
        $obj_image->setFileExtension(strtolower($file_info->extension));
        $obj_image->setFileType(FileHelper::__getFileType($file_info->extension));
        $obj_image->setMimeType($file_info->type);
        $obj_image->loadMediaType();
        $obj_image->addDefaultFilePath();
        $resultSaveUserAvatar = $obj_image->__quickCreate();


        if ($resultSaveUserAvatar['success'] == false) {
            $return = $resultSaveUserAvatar;
            if (is_array($return['detail'])) {
                $return['message'] = reset($return['detail']);
            } else {
                $return['message'] = 'Upload fail';
            }
            goto end_upload_function;
        }

        $resultUpload = $obj_image->uploadToS3FromBinary($imageContent);
        $resultUploadThumb = $obj_image->uploadToThumbFromBinary($imageContent);

        if ($resultUpload['success'] == false) {
            $return = $resultUpload;
            $return['message'] = "UPLOAD_FAIL_TEXT";
            goto end_upload_function;
        }


        $return['upload'] = $resultUpload;
        $return['success'] = true;
        $return['message'] = 'UPLOAD_SUCCESS_TEXT';
        $return['data'] = array_merge((array)$file_info, $obj_image->getParsedData());


        end_upload_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
