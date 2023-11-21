<?php

namespace SMXD\api\controllers\api;

use Phalcon\Security\Random;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\controllers\api\BaseController;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HttpStatusCode;
use SMXD\Application\Lib\ModelHelper;
use SMXD\App\Models\StaffUserGroup;
use Phalcon\Utils\Slug as PhpSlug;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Models\Media;
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

        /** SAVE TO DYNAMO DB DATABASE */
        $this->db->begin();
        $resultSaveMedia = $media->__quickCreate();

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

//        $checkFileExisted = Media::findFirst([
//            "conditions" => "name = :name: and file_extension = :file_extension: and user_uuid = :user_uuid: and is_deleted=:is_deleted_no:",
//            "bind" => [
//                'name' => pathinfo($fileName)['filename'],
//                'file_extension' => $extension,
//                'user_uuid' => ModuleModel::$user->getUuid(),
//                'is_deleted_no' => Media::IS_DELETE_NO,
//            ]
//        ]);
//
//
//        if ($checkFileExisted) {
//            $uuid = $checkFileExisted->getUuid();
//        } else {
//            $uuid = Helpers::__getRequestValue('uuid');
//            if (!Helpers::__isValidUuid($uuid)) {
//                $uuid = ApplicationModel::uuid();
//            }
//        }

        $uuid = Helpers::__getRequestValue('uuid');
        if (!Helpers::__isValidUuid($uuid)) {
            $uuid = ApplicationModel::uuid();
        }

        $fileNameUpload = 'smxd_medias' . '/' . $uuid . '.' . $extension;
        if ($fileObject && $fileObject == 'avatar') {
            $uuid = Helpers::__getRequestValue('avatar_object_uuid');
            $fileNameUpload = 'avatar/' . 'smxd_medias' . '/' . $uuid . '.' . $extension;
        }
        if ($fileObject && $fileObject != 'avatar') {
            $fileNameUpload = $fileObject . '/' . 'smxd_medias' . '/' . $uuid . '.' . $extension;
        }

        $return = SmxdS3Helper::__getPresignedUrlToUpload($fileNameUpload, $fileType);

        $return['uuid'] = $uuid;
        $return['$fileNameUpload'] = $fileNameUpload;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Upload an image and resize and push to AWS
     * @throws \Phalcon\Security\Exception
     */
    public function uploadImagePublicAction()
    {
        $companyId = Helpers::__getRequestValue('companyId');

        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }

        $return = [];
        if ($this->request->hasFiles()) {
            $files = $this->request->getUploadedFiles();

            $file = $files[0];
            $random = new Random();
            $uuid = $random->uuid();
            $file_info = new \stdClass();
            $file_info->name = basename($file->getName(), '.' . $file->getExtension());
            $file_info->basename = PhpSlug::generate(basename($file->getName(), '.' . $file->getExtension()));
            $file_info->extension = $file->getExtension();
            $file_info->type = $file->getType();
            $file_info->size = $this->formatBytes($file->getSize());
            $file_info->key = $file->getKey();
            $file_info->real_type = $file->getRealType();

            /** SAVE TO DATABASE */
            $media = new Media();
            $media->setNameStatic($file_info->name);
            $media->setName($uuid);
            $media->setCompanyId( $companyId ? (int)$companyId : 0);
            $media->setUuid($uuid);
            $media->setFilename($uuid . '.' . $file_info->extension);
            $media->setFileExtension($file_info->extension);
            $media->setFileType(Media::__getFileType($file_info->extension));
            $media->setMimeType($file_info->type);
            $media->loadMediaType();
            $media->setCreatedAt(date('Y-m-d H:i:s'));
            $media->setUserUuid(ModuleModel::$user->getUuid());
            $media->setIsHidden(ModelHelper::YES);
            $media->setIsHosted(Media::STATUS_HOSTED);
            $media->setIsPrivate(Media::IS_PRIVATE_NO);
            $result = $media->resizePublicImageAndUploadToS3($file->getTempName());

            if ($result['success'] == true) {
                $returnCreate = $media->__quickCreate();
                if ($returnCreate['success'] == false) {
                    $return['success'] = false;
                    $return['message'] = is_array($returnCreate['detail']) ? reset($returnCreate['detail']) : $returnCreate['detail'];
                    goto end_upload_function;
                } else {
                    $return['bucketName'] = isset($result['bucketName']) ? $result['bucketName'] : null;
                    $return['success'] = true;
                    $return['data'] = (array)$file_info;
                    $return['data']['file_extension'] = $file_info->extension;
                    $return['data']['file_type'] = $media->getFileType(strtolower($media->getFileExtension()));

                    $return['data']['id'] = $media->getId();
                    $return['data']['name'] = $media->getName();
                    $return['data']['url_public'] = $media->getPublicUrl();
                }
            }else{
                $return = $result;
            }
        } else {
            $return['success'] = false;
            $return['message'] = "NO_FILES_TEXT";
            goto end_upload_function;
        }

        end_upload_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @throws \Phalcon\Security\Exception
     */
    public function uploadPublicAction()
    {
        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }
        $return = [];
        if ($this->request->hasFiles()) {
            $user_profile = ModuleModel::$user;
            $user_login_token = ModuleModel::$user_token;
            $company = ModuleModel::$company;
            $files = $this->request->getUploadedFiles();
            $file = $files[0];
            $random = new Random();
            $uuid = $random->uuid();
            $file_info = new \stdClass();
            $file_info->name = basename($file->getName(), '.' . $file->getExtension());
            $file_info->basename = PhpSlug::generate(basename($file->getName(), '.' . $file->getExtension()));
            $file_info->extension = $file->getExtension();
            $file_info->type = $file->getType();
            $file_info->size = $this->formatBytes($file->getSize());
            $file_info->key = $file->getKey();
            $file_info->real_type = $file->getRealType();

            /** SAVE TO DATABASE */
            $media = new Media();
            $media->setNameStatic($file_info->name);
            $media->setName($uuid);
            $media->setCompanyId($company ? $company->getId() : '');
            $media->setUuid($uuid);
            $media->setFilename($uuid . '.' . $file_info->extension);
            $media->setFileExtension($file_info->extension);
            $media->setFileType(Media::__getFileType($file_info->extension));
            $media->setMimeType($file_info->type);
            $media->loadMediaType();
            $media->setCreatedAt(date('Y-m-d H:i:s'));
            $media->setUserLoginId((int)$this->current_user->getId());
            $media->setIsHidden(ModelHelper::YES);
            $media->setIsHosted(Media::STATUS_HOSTED);
            $media->setIsPrivate(Media::IS_PRIVATE_NO);
            $media->setUserProfileUuid(ModuleModel::$user->getUuid());
            $result = $media->resizePublicImageAndUploadToS3($file->getTempName());
            if ($result['success'] == true) {
                $returnCreate = $media->__quickCreate();;
                if ($returnCreate['success'] == false) {
                    $return['success'] = false;
                    $return['message'] = is_array($returnCreate['detail']) ? reset($returnCreate['detail']) : $returnCreate['detail'];
                    goto end_upload_function;
                } else {
                    $return['success'] = true;
                    $return['data'] = (array)$file_info;
                    $return['data']['file_extension'] = $file_info->extension;
                    $return['data']['file_type'] = $media->getFileType(strtolower($media->getFileExtension()));
                    $return['data']['id'] = $media->getId();
                    $return['data']['name'] = $media->getName();
                    $return['data']['url_public'] = $media->getPublicUrl();
                }
            }
        } else {
            $return['success'] = false;
            $return['message'] = "NO_FILES_TEXT";
            goto end_upload_function;
        }
        end_upload_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /*
  * Index page html
  */
    public function indexAction()
    {
        $this->checkAjaxPutGet();
        $query = Helpers::__getRequestValue('query');
        $page = Helpers::__getRequestValue('page');
        $folderUuid = Helpers::__getRequestValue('folderUuid');
        $creationDate = Helpers::__getRequestValue('creationDate');
        $creationDateFolder = Helpers::__getRequestValue('creationDateFolder');
        $creationDateTime = Helpers::__getRequestValue('creationDateTime');
        $fileType = Helpers::__getRequestValue('fileType');
        $isPrivate = Helpers::__getRequestValue('isPrivate');
        $limit = Helpers::__getRequestValue('limit');

        if ($isPrivate == true) {
            $return = Media::__findWithFilters([
                'query' => $query,
                'isHidden' => false,
                'isDeleted' => false,
                'page' => $page,
                'user_uuid' => ModuleModel::$user->getUuid(),
                'folderUuid' => $folderUuid,
                'fileType' => $fileType,
                'creationDate' => $folderUuid ? $creationDateFolder : $creationDate,
                'creationDateTime' => $creationDateTime,
                'isPrivate' => true,
                'limit' => $limit ?: Media::LIMIT_PER_PAGE,
            ]);
        } else {
            $return = Media::__findWithFilters([
                'query' => $query,
                'isHidden' => false,
                'isDeleted' => false,
                'page' => $page,
                'creationDate' => $creationDate,
                'creationDateTime' => $creationDateTime,
                'company_id' => ModuleModel::$company->getId(),
                'isPrivate' => false,
                'limit' => $limit ?: Media::LIMIT_PER_PAGE,
            ]);
        }


        if ($return['success'] == true) {
            $return['totalItems'] = $return['total_items'];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getUserMediaSizeAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();


        //Get private file
        $myMediaInfo = Media::__findWithFilters([
            'isDeleted' => false,
            'page' => 1,
            'user_uuid' => ModuleModel::$user->getUuid(),
            'isPrivate' => true,
            'limit' => 1
        ]);


        $return = [
            'success' => true,
            'totalSize' => isset($myMediaInfo['sizes']) ? $myMediaInfo['sizes'] : 0,
            'myItems' =>  isset($myMediaInfo['total_items']) ? $myMediaInfo['total_items'] : 0,
            'totalItems' => isset($myMediaInfo['total_items']) ? $myMediaInfo['total_items'] : 0,
            'totalFolders' => isset($myMediaInfo['folders']) ? $myMediaInfo['folders'] : 0,
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * Remove multiple medias
     */
    public function removeMultipleAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        /***** check attachments permission ***/
        $media_uuids = Helpers::__getRequestValue('media_uuids');
        if (is_null($media_uuids) || count($media_uuids) == 0) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $medias = Media::find([
            'conditions' => 'uuid IN ({uuids:array})',
            'bind' => [
                'uuids' => $media_uuids
            ]
        ]);
        $this->db->begin();
        $filesDeleted = [];
        foreach ($medias as $media) {
            if ($media && ($media->belongsToCurrentUserProfile() || ModuleModel::$user->isAdmin())) {
                $countParams = [];
                $removeMedia = $media->__quickRemove();
                $filesDeleted[] = $media;
                if ($removeMedia['success'] == false) {
                    $return = $removeMedia;
                    $return['message'] = "FILE_DELETE_FAIL_TEXT";
                    $this->db->rollback();
                    goto end_of_function;
                }


            } else {
                $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
                $this->db->rollback();
                goto end_of_function;
            }
        }

        $return = ['success' => true, 'message' => 'FILE_DELETE_SUCCESS_TEXT', 'data' => $filesDeleted];
        $this->db->commit();

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * Clone media
     * @throws \Phalcon\Security\Exception
     * @throws \Exception
     */
    public function copyMediaAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $uuid = Helpers::__getRequestValue('uuid');
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

        if ($media) {
            // Media copy
            $prefix_copy = 'Copy of';
            $random = new Random();
            $randomUuid = $random->uuid();

            $num = 0;

            /** SAVE TO DYNAMO DATABASE */
            $newMedia = new Media();
            $newMedia->setUuid($randomUuid);
            $newMedia->setCompanyId(ModuleModel::$company ? ModuleModel::$company->getId() : null);
            $newMedia->setFilename($randomUuid . '.' . strtolower($media->getFileExtension()));
            $newMedia->setName($media->getName());
            $newMedia->setNameStatic($media->getNameStatic());
            $newMedia->setUserUuid(ModuleModel::$user->getUuid());
            $newMedia->setFileExtension($media->getFileExtension());
            $newMedia->setFileType($media->getFileType());
            $newMedia->setMimeType($media->getMimeType());
            $newMedia->loadMediaType();
            $newMedia->setIsHosted(intval(ModelHelper::YES));
            $newMedia->setIsPrivate(intval(ModelHelper::YES));
            $newMedia->setIsDeleted(intval(ModelHelper::NO));
            $newMedia->setIsHidden(intval(ModelHelper::NO));
            if ($media->getSize()) {
                $newMedia->setSize(intval($media->getSize()));
            }

            if ($media->getFolderUuid() && $media->getUserUuid() == ModuleModel::$user->getUuid()) {
                $newMedia->setFolderUuid($media->getFolderUuid());
            }

            $newMedia->addDefaultFilePath();

            $existed = $newMedia->checkFileNameExisted();
            while ($existed == true) {
                if ($num < 1) {
                    $newMedia->setName($prefix_copy . ' ' . $media->getName());
                    $newMedia->setNameStatic($prefix_copy . ' ' . $media->getName());
                } else {
                    $newMedia->setName($prefix_copy . ' ' . $media->getName() . "($num)");
                    $newMedia->setNameStatic($prefix_copy . ' ' . $media->getName() . "($num)");
                }
                $existed = $newMedia->checkFileNameExisted();
                $num++;
            }

            //Create data on Dynamo
            $this->db->begin();
            $resultNewMedia = $newMedia->__quickCreate();
            if ($resultNewMedia['success'] == false) {
                $return = $resultNewMedia;
                $return['success'] = false;
                $return['message'] = 'FILE_COPY_FAIL_TEXT';
                $this->db->rollback();
                goto end_of_function;
            }

            // Copy file s3
            $fromFilePath = $media->getRealFilePath();
            $toFilePath = $newMedia->getFilePath();

            $resultCopyFile = SMXDS3Helper::__copyMedia($fromFilePath, $toFilePath);

            if ($resultCopyFile['success'] == false) {
                $return = $resultCopyFile;
                $return['success'] = false;
                $return['message'] = "FILE_COPY_TO_S3_FAIL_TEXT";
                $this->db->rollback();
                goto end_of_function;
            }
            $this->db->commit();


            $return['success'] = true;
            $return['message'] = 'FILE_COPY_SUCCESS_TEXT';
            $return['data'] = $newMedia->getParsedData();
            goto end_of_function;
        } else {
            $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            goto end_of_function;
        }


        end_of_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $uuid
     * @throws \Exception
     */
    public function renameAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();

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

        if ($media && $media->belongsToCurrentUserProfile()) {
            $name = Helpers::__getRequestValue('name');

            //UPDATE DYNAMODB
            $media->setName($name);
            $media->setNameStatic($name);

            $result = $media->__quickUpdate();

            if ($result['success'] == false) {
                $return = [
                    'success' => false,
                    'message' => 'FILE_UPDATE_FAIL_TEXT'
                ];
                goto end_of_function;
            }

            /**
             * TODO: UPDATE FILE INFO ON ATTACHMENT
             */
            $return = [
                'success' => true,
                'message' => 'FILE_UPDATE_SUCCESS_TEXT',
                'data' => $media->getParsedData()
            ];
            goto end_of_function;

        } else {
            $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            goto end_of_function;
        }


        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (!$uuid) {
            goto end_of_function;
        }

        $media = Media::findFirstByUuid($uuid);
        if (!$media) {
            goto end_of_function;
        }

        $return = [
            'success' => true,
            'data' => $media->getParsedData()
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}