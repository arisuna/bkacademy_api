<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HttpStatusCode;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\MediaFolder;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MediaV1Controller extends BaseController
{
    const CONTROLLER_NAME = "media";
    /**
     * @Route("/uploader", paths={module="gms"}, methods={"GET"}, name="gms-uploader-index")
     */

    private $ext_type = [], $current_user, $thumbnail;
    private $data_response = [
        "success" => false,
        "msg" => "",
        "data" => ""
    ];

    /**
     *
     */
    public function initialize()
    {
        $this->view->disable();
        $this->current_user = ModuleModel::$user_login;
        $this->response->setContentType('application/json', 'UTF-8');
    }

    /**
     *
     */
    public function testAction()
    {
        die(__METHOD__);
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
                'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
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
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     * @throws \Exception
     */
    public function uploadAction()
    {
        $this->checkAclUpload(AclHelper::CONTROLLER_MEDIA);
        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }

        $isPrivate = $this->request->getQuery('isPrivate');
        $isPublic = $this->request->getQuery('isPublic');
        $folderUuid = $this->request->getQuery('folderUuid');

        $company = ModuleModel::$company;
        $files = $this->request->getUploadedFiles();
        $file = $files[0];

        if ($file->getError()) {
            $return['detail'] = $file->getError();
            $return['success'] = false;
            $return['message'] = "UPLOAD_FAIL_TEXT";
            goto end_upload_function;
        };

        $random = new Random();
        $uuid = $random->uuid();
        $file_info = new \stdClass();
        $file_info->name = basename($file->getName(), '.' . strtolower($file->getExtension()));
        $file_info->name_static = basename($file->getName(), '.' . strtolower($file->getExtension()));
        $file_info->basename = PhpSlug::generate(basename($file->getName(), '.' . strtolower($file->getExtension())));
        $file_info->extension = strtolower($file->getExtension());
        $file_info->type = $file->getType();
        $file_info->size = intval($file->getSize());
        $file_info->key = $file->getKey();
        $file_info->real_type = $file->getRealType();
        $file_info->file_name = $uuid . '.' . strtolower($file_info->extension);

        /** Set data to media dynamo*/
        $media = new Media();
        $media->setName($file_info->name);
        $media->setNameStatic($file_info->name);
        $media->setCompanyId($company ? $company->getId() : '');
        $media->setUuid($uuid);
        $media->setFileName($uuid . '.' . strtolower($file_info->extension));
        $media->setFileExtension(strtolower($file_info->extension));
        $media->setFileType(Media::__getFileType($file_info->extension));
        $media->setMimeType($file_info->type);
        $media->loadMediaType();
        $media->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $media->setIsHosted(intval(ModelHelper::YES));
        $media->setIsPrivate(intval(ModelHelper::YES));
        $media->setIsDeleted(intval(ModelHelper::NO));
        $media->setIsHidden(intval(ModelHelper::NO));
        $media->setSize($file->getSize());
        if ($isPublic) {
            $media->setIsPrivate(intval(ModelHelper::NO));
        }


        $checkFileExisted = $media->checkFileNameExisted();
        if ($checkFileExisted == true) {
            $return['success'] = false;
            $return['message'] = "FILE_NAME_SHOULD_BE_UNIQUE_TEXT";
            goto end_upload_function;
        }

        /** SAVE TO DYNAMO DB DATABASE */
        $this->db->begin();
        $resultSaveMedia = $media->__quickCreate();

        if ($resultSaveMedia['success'] == false) {
            $return = $resultSaveMedia;
            if (isset($return['errorMessage']) && is_array($return['errorMessage'])) {
                $return['message'] = reset($return['errorMessage']);
            } else {
                $return['message'] = 'UPLOAD_FAIL_TEXT';
            }
            $this->db->rollback();
            goto end_upload_function;
        }


        /** UPLOAD TO S3 */
        $resultUpload = $media->uploadToS3FromPath($file->getTempName());
        if ($resultUpload['success'] == false) {
            $return = $resultUpload;
            $this->db->rollback();
            if (isset($return['errorMessage']) && is_array($return['errorMessage']) && is_string(end($return['errorMessage']))) {
                $return['message'] = end($return['errorMessage']);
            } else {
                $return['message'] = "UPLOAD_FAIL_TEXT";
            }
            goto end_upload_function;
        }

        $this->db->commit();

        /** COUNT MEDIA */
        $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), [
            'my_files' => 1,
            'total_files' => 1,
            'sizes' => intval($file->getSize())
        ]);

        if ($resultMediaCount['success'] == false) {
            $return = $resultMediaCount;
            $return['message'] = "UPLOAD_FAIL_TEXT";
            goto end_upload_function;
        }

        $return['success'] = true;
        $return['message'] = 'UPLOAD_SUCCESS_TEXT';
        $return['data'] = array_merge((array)$file_info, $media->getParsedData());
        end_upload_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     * @throws \Exception
     */
    public function uploadv2Action()
    {
        $this->checkAclUpload(AclHelper::CONTROLLER_MEDIA);
        $isPrivate = $this->request->getQuery('isPrivate');
        $isPublic = $this->request->getQuery('isPublic');
        $folderUuid = $this->request->getQuery('folderUuid');

        $company = ModuleModel::$company;
        $name = Helpers::__getRequestValue('name');
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $type = Helpers::__getRequestValue('type');
        $size = Helpers::__getRequestValue('size');
        $uuid = Helpers::__getRequestValue('uuid');

        /** Set data to media dynamo*/
        $media = new Media();
        $media->setName(pathinfo($name)['filename']);
        $media->setNameStatic(pathinfo($name)['filename']);
        $media->setCompanyId($company ? $company->getId() : '');
        $media->setUuid($uuid);
        $media->setFileName($uuid . '.' . strtolower($extension));
        $media->setFileExtension(strtolower($extension));
        $media->setFileType(Media::__getFileType($extension));
        $media->setMimeType($type);
        $media->loadMediaType();
        $media->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $media->setIsHosted(intval(ModelHelper::YES));
        $media->setIsPrivate(intval(ModelHelper::YES));
        $media->setIsDeleted(intval(ModelHelper::NO));
        $media->setIsHidden(intval(ModelHelper::NO));
        $media->setSize($size);
        if ($isPublic) {
            $media->setIsPrivate(intval(ModelHelper::NO));
        }

        $checkFileExisted = Media::findFirst([
            "conditions" => "name = :name: and file_extension = :file_extension: and user_profile_uuid = :user_profile_uuid: and is_deleted=:is_deleted_no:",
            "bind" => [
                'name' => $media->getName(),
                'file_extension' => $media->getFileExtension(),
                'user_profile_uuid' => $media->getUserProfileUuid(),
                'is_deleted_no' => Media::IS_DELETE_NO,
            ]
        ]);
        $is_created_media = false;
        if ($checkFileExisted){
            $old_size = $checkFileExisted->getSize();
            $is_created_media = true;
            $media->setUuid($checkFileExisted->getUuid());
            $media->setId($checkFileExisted->getId());
        }

        /** SAVE TO DYNAMO DB DATABASE */
        $this->db->begin();
        if($is_created_media){
            $resultSaveMedia = $media->__quickUpdate();
        } else {
            $resultSaveMedia = $media->__quickCreate();
        }

        if ($resultSaveMedia['success'] == false) {
            $return = $resultSaveMedia;
            if (isset($return['errorMessage']) && is_array($return['errorMessage'])) {
                $return['message'] = reset($return['errorMessage']);
            } else {
                $return['message'] = 'UPLOAD_FAIL_TEXT';
            }
            goto end_upload_function;
        }

        /** COUNT MEDIA */
        if($is_created_media){
            $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), [
                'my_files' => 0,
                'total_files' => 0,
                'sizes' => $size - $old_size
            ]);
        } else {
            $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), [
                'my_files' => 1,
                'total_files' => 1,
                'sizes' => $size
            ]);
        }

        if ($resultMediaCount['success'] == false) {
            $return = $resultMediaCount;
            $return['message'] = "UPLOAD_FAIL_TEXT";
            $this->db->rollback();
            goto end_upload_function;
        }

        $this->db->commit();

        $return['success'] = true;
        $return['message'] = 'UPLOAD_SUCCESS_TEXT';
        $return['data'] = $media->getParsedData();
        end_upload_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_BAD_REQUEST);
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
     * @param $extension
     * @return mixed|string
     */
    private function getFileType($extension)
    {
        foreach ($this->ext_type as $key => $value) {
            if ($key == $extension) {
                return $value;
            }
        }
        return 'other';
    }

    /**
     * return upload by Url Action
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function uploadByUrlAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $objectUuid = Helpers::__getRequestValue('uuid');
        $url = Helpers::__getRequestValue('url');
        $url = str_replace(' ', '%20', $url);

        $return = [
            'success' => false,
            'message' => 'URL_INVALID_TEXT',
        ];

        if ($url != '' && Helpers::__isUrl($url)) {
            $_tmp = explode('/', $url);
            $real_name = end($_tmp);

            try {
                $file_content = Helpers::__urlGetContent($url);
                $file_info = new \finfo(FILEINFO_MIME_TYPE);
                $fileSize = strlen($file_content);
                $mime_type = $file_info->buffer($file_content);
            } catch (Exception $e) {
                $this->data_response['success'] = false;
                $this->data_response['message'] = $e->getMessage();
                goto end_upload_function;
            }

            $return = [
                'success' => false,
                'message' => 'FILE_IMAGE_NOT_FOUND_TEXT',
            ];

            if (isset(Media::$images_extensions[$mime_type])) {

                $file_info = new \stdClass();
                $file_info->name = $real_name;
                $file_info->basename = $real_name; //PhpSlug::generate($real_name);
                $file_info->extension = Media::$images_extensions[$mime_type]['extension'];
                $file_info->type = Media::$images_extensions[$mime_type]['type']; // type
                $file_info->mime = $mime_type;
                $file_info->size = $fileSize;
                $file_info->real_type = Media::$images_extensions[$mime_type]['type']; // type of file

                $this->db->begin();
                $uuid = ApplicationModel::uuid();
                $media = new Media();
                $media->setNameStatic($file_info->name);
                $media->setName($uuid); // for avoid the problem of filename
                $media->setUuid($uuid);
                $media->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
                $media->setCompanyId(ModuleModel::$company->getId());
                $media->setFilename($uuid . '.' . $file_info->extension);
                $media->setFileExtension($file_info->extension);
                $media->setFileType(Media::__getFileType($file_info->extension));
                $media->setMimeType($file_info->mime);
                $media->setCreatedAt(date('Y-m-d H:i:s'));
                $media->setUserLoginId((int)$this->current_user->getId());
                $media->setIsHosted(Media::STATUS_HOSTED);
                $media->setIsHidden(ModelHelper::YES);
                $media->setOriginUrl($url);
                $media->loadMediaType();
                $resultSave = $media->__quickCreate();

                if ($resultSave['success'] == false) {
                    $return = [
                        'success' => false,
                        'message' => 'UPLOAD_FAIL_TEXT',
                        'detail' => $resultSave['detail']
                    ];
                    goto end_upload_function;
                } else {
                    $mediaData = $media->getParsedData();
                    $return = [
                        'success' => true,
                        'message' => 'FILE_UPLOAD_SUCCESS_TEXT',
                        'data' => $mediaData
                    ];
                }


                $resultUpload = $media->uploadToS3FromContent($file_content);
                if ($resultUpload['success'] == false) {
                    $this->db->rollback();
                    $return = [
                        'success' => false,
                        'message' => 'UPLOAD_TO_S3_FAIL_TEXT',
                        'detail' => $resultUpload['detail']
                    ];
                    goto end_upload_function;
                } else {
                    $return['uploadS3'] = $resultUpload;
                }


                if ($objectUuid != '' && Helpers::__isValidUuid($objectUuid)) {

                    $returnAttachment = MediaAttachment::__createAttachment([
                        'objectUuid' => $objectUuid,
                        'file' => $media,
                        'userProfile' => ModuleModel::$user_profile,
                    ]);

                    if ($returnAttachment["success"] == false) {
                        $this->db->rollback();

                        $return = [
                            'success' => false,
                            'message' => 'CREATE_ATTACHMENT_FAIL_TEXT',
                        ];
                        goto end_upload_function;
                    } else {
                        $mediaData = $media->getParsedData();
                        $mediaData['can_delete'] = true;
                        $return = [
                            'success' => true,
                            'data' => $mediaData,
                            'message' => 'FILE_UPLOAD_SUCCESS_TEXT',
                        ];
                    }
                }
                $this->db->commit();
            }
        }

        end_upload_function:
        if ($return['success'] == false) {
            $this->response->setStatusCode(HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR, HttpStatusCode::getMessageForCode(HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR));
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function makePublicAction($uuid = "")
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex(self::CONTROLLER_NAME);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $media = Media::findFirstByUuid($uuid);
            if ($media && $media->belongsToCurrentUserProfile()) {
                $return = $media->makePublic();
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @throws \Exception
     */
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

        if ($media && ($media->belongsToCurrentUserProfile() || ModuleModel::$user_profile->isAdmin())) {
            $countParams = [];
            /** REMOVE MEDIA DYNAMO DB */
            $media = Media::findFirstByUuid($uuid);
            $removeMedia = $media->__quickRemove();

            if ($removeMedia['success'] == false) {
                $return = $removeMedia;
                $return['message'] = "FILE_DELETE_FAIL_TEXT";
                goto end_of_function;
            }

            $countParams['my_files'] = -1;
            $countParams['my_files_deleted'] = 1;
            $countParams['total_files'] = -1;

            /** COUNT MEDIA */
            $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), $countParams);
//            $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), $countParams);

            if ($resultMediaCount['success'] == false) {
                $return = $resultMediaCount;
                $return['message'] = "FILE_DELETE_FAIL_TEXT";
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

            $existed = $media->checkFileNameExisted();

            if ($existed == true) {
                $return = [
                    'success' => false,
                    'message' => 'FILE_NAME_SHOULD_BE_UNIQUE_TEXT'
                ];
                goto end_of_function;
            }

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

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getMyFoldersListAction()
    {
        $this->checkAjaxPutGet();
        $query = Helpers::__getRequestValue('query');
        $page = Helpers::__getRequestValue('page');
        $creationDate = Helpers::__getRequestValue('creationDate');
        $creationDateTime = Helpers::__getRequestValue('creationDateTime');

        $return = MediaFolder::__findWithFilters([
            'query' => $query,
            'page' => $page,
            'userProfileUuid' => ModuleModel::$user_profile->getUuid(),
            'companyId' => ModuleModel::$user_profile->getUuid(),
            'creationDate' => $creationDate,
            'creationDateTime' => $creationDateTime,
            'isPrivate' => true,
        ]);

        if ($return['success'] == true) {
            $return['totalItems'] = $return['total_items'];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function createMyFolderAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $name = Helpers::__getRequestValue('name');
        $mediaFolder = new MediaFolder();
        $mediaFolder->setData([
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'creator_user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'is_private' => Helpers::YES,
            'is_company' => Helpers::NO,
            'company_id' => ModuleModel::$company->getId(),
            'name' => $name
        ]);
        $this->db->begin();
        $resultCreate = $mediaFolder->__quickCreate();
        if ($resultCreate['success'] == false) {
            $resultCreate['message'] = isset($resultCreate['errorMessageLast']) ? $resultCreate['errorMessageLast'] : 'FOLDER_CREATE_FAIL_TEXT';
            $this->db->rollback();
        } else {
            $resultCreate['message'] = 'FOLDER_CREATE_SUCCESS_TEXT';
            $resultCreate['data'] = $mediaFolder;

            /** COUNT MEDIA */
            $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), ['folders' => 1]);

            if ($resultMediaCount['success'] == false) {
                $this->db->rollback();
                $resultCreate = $resultMediaCount;
                $resultCreate['message'] = "FOLDER_CREATE_FAIL_TEXT";
            }
        }

        if ($resultCreate['success'] == true) {
            $this->db->commit();
        }

        $this->response->setJsonContent($resultCreate);
        $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function renameFolderAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $name = Helpers::__getRequestValue('name');

        /***** check attachments permission ***/
        if (is_null($uuid) || is_null($uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $mediaFolder = MediaFolder::findFirstByUuid($uuid);
        if (!$mediaFolder) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($mediaFolder && $mediaFolder->canEditFolder()) {
            $mediaFolder->setName(Helpers::__getRequestValue('name'));
            $resultUpdate = $mediaFolder->__quickUpdate();

            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
                $return['message'] = isset($return['systemMessage']) ? $return['systemMessage'] : 'FOLDER_UPDATE_FAIL_TEXT';
                goto end_of_function;
            }
            $return = ['success' => true, 'message' => 'FOLDER_UPDATE_SUCCESS_TEXT', 'data' => $mediaFolder];
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
     * @param $uuid
     */
    public function removeFolderAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        /***** check attachments permission ***/
        $uuid = Helpers::__getRequestValue('uuid');
        if (is_null($uuid) || is_null($uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $password = Helpers::__getRequestValue('password');
        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user_login->getEmail(), $password);
        if ($checkPassword['success'] == false) {
            $return = ['success' => false, 'message' => 'PASSWORD_INCORRECT_TEXT'];
            goto end_of_function;
        }

        $mediaFolder = MediaFolder::findFirstByUuid($uuid);
        if (!$mediaFolder) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $isAssigneeFolder = $mediaFolder->isAssigneeFolder();

        if ($mediaFolder && ($mediaFolder->canEditFolder() || $mediaFolder->belongsToCreatorUserProfile())) {
            $this->db->begin();

            $attachments = $mediaFolder->getMediaAttachments();
            if ($attachments) {
                $resultDelete = $attachments->delete();
                if (!$resultDelete) {
                    $this->db->rollback();
                    $return['message'] = 'FOLDER_DELETE_FAIL_TEXT';
                    goto end_of_function;
                }
            }

            $resultDelete = $mediaFolder->__quickRemove();
            if ($resultDelete['success'] == false) {
                $this->db->rollback();
                $return = $resultDelete;
                $return['message'] = isset($return['systemMessage']) ? $return['systemMessage'] : 'FOLDER_DELETE_FAIL_TEXT';
                goto end_of_function;
            }

            /** COUNT MEDIA */
            if (!$isAssigneeFolder){
                RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), ['folders' => -1]);
            }

            $this->db->commit();
            $return = ['success' => true, 'message' => 'FOLDER_DELETE_SUCCESS_TEXT', 'data' => $mediaFolder];
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
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function addMediaToFolderAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $mediaUuid = Helpers::__getRequestValue('mediaUuid');
        $folderUuid = Helpers::__getRequestValue('folderUuid ');

        /***** check attachments permission ***/
        if (is_null($mediaUuid) || !Helpers::__isValidUuid($mediaUuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if (is_null($folderUuid) || !Helpers::__isValidUuid($folderUuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $mediaFolder = MediaFolder::findFirstByUuid($folderUuid);
        if (!$mediaFolder) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $media = Media::findFirstByUuid($mediaUuid);
        if (!$media) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($mediaFolder && $mediaFolder->canEditFolder() && $media && $media->canEditMedia()) {
            $media->setFolderUuid($mediaFolder->getUuid());
            $resultUpdate = $media->__quickUpdate();
            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
                $return['message'] = 'ADD_MEDIA_TO_FOLDER_FAIL_TEXT';
                goto end_of_function;
            }

            $return = [
                'success' => true,
                'message' => 'ADD_MEDIA_TO_FOLDER_SUCCESS_TEXT',
                'data' => $media->getParsedData()
            ];
            goto end_of_function;
        } else {
            $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function changeMediaStatusAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $mediaUuid = Helpers::__getRequestValue('mediaUuid');
        $isPrivate = Helpers::__getRequestValue('isPrivate');

        /***** check attachments permission ***/
        if (is_null($mediaUuid) || !Helpers::__isValidUuid($mediaUuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if (is_null($isPrivate)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $media = Media::findFirstByUuid($mediaUuid);
        if (!$media) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($media && $media->canEditMedia()) {
            if (!is_null($isPrivate) && intval($isPrivate) == intval(ModelHelper::NO)) {
                $media->setIsPrivate(intval(ModelHelper::YES));
            }
            if (!is_null($isPrivate) && intval($isPrivate) == intval(ModelHelper::YES)) {
                $media->setIsPrivate(intval(ModelHelper::NO));
            }

            $resultUpdate = $media->__quickUpdate();
            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
                $return['message'] = 'CHANGE_MEDIA_STATUS_FAIL_TEXT';
                goto end_of_function;
            }

            $return = [
                'success' => true,
                'message' => 'CHANGE_MEDIA_STATUS_SUCCESS_TEXT',
                'data' => $media->getParsedData()
            ];
            goto end_of_function;
        } else {
            $return = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function checkMediaNameAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();

        $name = Helpers::__getRequestValue('name');
        $folderUuid = Helpers::__getRequestValue('folderUuid');
        $media = new Media();
        if (!$media) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        $media->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $media->setCompanyUuid(ModuleModel::$company->getUuid());
        $media->setName($name);

        //var_dump($media->toArray()); die();
        $validationFileName = true;
        $existed = $media->checkFileNameExisted();
        if ($existed == false) {
            $validationFileName = true;
            $return = [
                'success' => true,
                'message' => 'FILE_NAME_AVAILABLE_TEXT',
            ];
        } else {
            $validationFileName = false;
            $return = [
                'success' => false,
                'message' => 'FILE_NAME_SHOULD_BE_UNIQUE_TEXT',
            ];
        }
        $return['$validationFileName'] = $validationFileName;
        end_of_function:
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

//        $myMediaInfo = DynamoMediaCountExt::findFirstByUuid(ModuleModel::$user_profile->getUuid());
        $myMediaInfo = RelodayObjectMapHelper::__getInfoMediaCount(ModuleModel::$user_profile->getUuid());

        if (!$myMediaInfo) {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        //Get private file
        $publicResult = Media::__findWithFilters([
            'isHidden' => false,
            'isDeleted' => false,
            'page' => 1,
            'company_id' => ModuleModel::$company->getId(),
            'isPrivate' => false,
            'limit' => 1
        ]);

        $shareWithMeReturn = MediaAttachment::__findSharedWithMe([
            'limit' => 1,
            'page' => 1,
            'query' => '',
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
        ]);

        $return = [
            'success' => true,
            'totalSize' => isset($myMediaInfo['sizes']) ? $myMediaInfo['sizes'] : 0,
            'myItems' =>  isset($myMediaInfo['my_files']) ? $myMediaInfo['my_files'] : 0,
            'totalItems' => isset($myMediaInfo['total_files']) ? $myMediaInfo['total_files'] : 0,
            'totalFolders' => isset($myMediaInfo['folders']) ? $myMediaInfo['folders'] : 0,
            'totalSizeHuman' => isset($myMediaInfo['sizes']) ? Helpers::__formatBytes($myMediaInfo['sizes'], 2) : 0,
            'totalSharedWithEverybody' => is_array($publicResult) && isset($publicResult['total_items']) ? $publicResult['total_items'] : 0,
            'totalSharedWithMe' => is_array($shareWithMeReturn) && isset($shareWithMeReturn['total_items']) ? $shareWithMeReturn['total_items'] : 0,

        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }



    /**
     * @params: uuid
     * @params: folderUuid
     */
    /**
     * @param $uuid
     * @throws \Exception
     */
    public function moveFileAction($uuid)
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
            $folderUuid = Helpers::__getRequestValue('folder_uuid');
            $media->setFolderUuid($folderUuid);
            $resultUpdate = $media->__quickUpdate();

            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
                $return['message'] = 'FILE_MOVE_FAIL_TEXT';
                goto end_of_function;
            }

            $return = ['success' => true, 'message' => 'FILE_MOVE_SUCCESS_TEXT', 'data' => $media->getParsedData()];
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
     * Replace media
     * @throws \Exception
     */
    public function replaceMediaContentAction()
    {
        $this->checkAclUpload(AclHelper::CONTROLLER_MEDIA);

        if (!$this->request->hasFiles()) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        $uuid = Helpers::__getRequestValue('uuid');
        /***** check attachments permission ***/
        if (is_null($uuid) || is_null($uuid)) {
            $return = ['success' => false, 'message' => 'PARAMS_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $media = Media::findFirstByUuid($uuid);

        if ($media == null) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $files = $this->request->getUploadedFiles();
        $file = $files[0];
        $file_info = new \stdClass();
        $file_info->name = basename($file->getName(), '.' . strtolower($file->getExtension()));
        $file_info->basename = PhpSlug::generate(basename($file->getName(), '.' . strtolower($file->getExtension())));
        $file_info->extension = strtolower($file->getExtension());
        $file_info->type = $file->getType();
        $file_info->size = $this->formatBytes($file->getSize());
        $file_info->key = $file->getKey();
        $file_info->real_type = $file->getRealType();


        if ($media && $media->belongsToCurrentUserProfile()) {
            $oldMediaName = $media->getName();
            $newFileName = $uuid . '.' . strtolower($file_info->extension);

//            if($media->getFileName() != $newFileName)
//            {
//                var_dump($media->getFileName());
//                var_dump($newFileName);
//                die();
//                $return['success'] = false;
//                $return['message'] = "FILE_REPLACE_FAIL_TEXT";
//                goto end_of_function;
//            }

            // Update new file upload (media)
            $media->setName($file_info->name);
            $media->setNameStatic($file_info->name);
            $media->setFileExtension($file_info->extension);
            $media->setFileType(Media::__getFileType($file_info->extension));
            $media->setMimeType($file_info->type);
            $media->loadMediaType();
            $media->setFileName($file_info->name . '.' . $file_info->extension);
            $media->setFilePath($media->getCompany()->getUuid() . '/' . $file_info->name . '.' . $file_info->extension);
            $media->setSize($file->getSize());

            $exited = $media->checkFileNameExisted();
            if ($exited) {
                $return['success'] = false;
                $return['message'] = "FILE_NAME_SHOULD_BE_UNIQUE_TEXT";
                goto end_of_function;
            }

            $this->db->begin();
            $resultSaveMedia = $media->__quickUpdate();


            if ($resultSaveMedia['success'] == false) {
                $return = $resultSaveMedia;
                $return['success'] = false;
                $this->db->rollback();
                goto end_of_function;
            }
            /** TODO UPDATE MEDIA ATTACHMENT INFO */

            //Replace S3 file
            $replaceUploadS3 = $media->uploadToS3FromPath($file->getTempName());
            if ($replaceUploadS3['success'] == false) {
                $return = $replaceUploadS3;
                $return['success'] = false;
                $return['message'] = "UPLOAD_FAIL_TEXT";
                $this->db->rollback();
                goto end_of_function;
            }
            $this->db->commit();
            $return = ['success' => true, 'message' => 'FILE_REPLACE_SUCCESS_TEXT', 'data' => $media->getParsedData()];
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
            $newMedia->setCompanyId(ModuleModel::$company->getId());
            $newMedia->setFilename($randomUuid . '.' . strtolower($media->getFileExtension()));
            $newMedia->setName($media->getName());
            $newMedia->setNameStatic($media->getNameStatic());
            $newMedia->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
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

            if ($media->getFolderUuid() && $media->getUserProfileUuid() == ModuleModel::$user_profile->getUuid()) {
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
            $fromFilePath = $media->getFilePath();
            $toFilePath = $newMedia->getFilePath();

            $resultCopyFile = RelodayS3Helper::__copyMedia($fromFilePath, $toFilePath);

            if ($resultCopyFile['success'] == false) {
                $return = $resultCopyFile;
                $return['success'] = false;
                $return['message'] = "FILE_COPY_TO_S3_FAIL_TEXT";
                $this->db->rollback();
                goto end_of_function;
            }
            $this->db->commit();


            /** COUNT MEDIA */
            $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), [
                'my_files' => 1,
                'total_files' => 1,
                'sizes' => $newMedia->getSize()
            ]);

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
     */
    public function createAssigneeFolderAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $name = Helpers::__getRequestValue('name');
        $employeeUuid = Helpers::__getRequestValue('employeeUuid');
        $employee = Employee::findFirstByUuid($employeeUuid);
        if (!$employee || $employeeUuid == '' || !Helpers::__isValidUuid($employeeUuid)) {
            $resultCreate = [
                'success' => false,
                'message' => "DATA_NOT_FOUND_TEXT"
            ];
            goto end_of_function;
        }

        $mediaFolder = new MediaFolder();
        $mediaFolder->setData([
            'user_profile_uuid' => $employeeUuid,
            'creator_user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'is_private' => Helpers::YES,
            'is_company' => Helpers::NO,
            'company_id' => ModuleModel::$company->getId(),
            'name' => $name
        ]);

        $this->db->begin();

        $resultCreate = $mediaFolder->__quickCreate();
        if ($resultCreate['success'] == false) {
            $resultCreate['message'] = isset($resultCreate['errorMessageLast']) ? $resultCreate['errorMessageLast'] : 'FOLDER_CREATE_FAIL_TEXT';
            $this->db->rollback();
            goto end_of_function;

        } else {
            $resultCreate['message'] = 'FOLDER_CREATE_SUCCESS_TEXT';
            $resultCreate['data'] = $mediaFolder;
        }
        $this->db->commit();
        end_of_function:
        $this->response->setJsonContent($resultCreate);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAssigneeFoldersListAction()
    {
        $this->checkAjaxPutGet();
        $query = Helpers::__getRequestValue('query');
        $page = Helpers::__getRequestValue('page');
        $creationDate = Helpers::__getRequestValue('creationDate');
        $creationDateTime = Helpers::__getRequestValue('creationDateTime');
        $employeeUuid = Helpers::__getRequestValue('employeeUuid');

        $employee = Employee::findFirstByUuid($employeeUuid);
        if (!$employee || $employeeUuid == '' || !Helpers::__isValidUuid($employeeUuid)) {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        $return = MediaFolder::__findWithFilters([
            'query' => $query,
            'page' => $page,
            'userProfileUuid' => $employeeUuid,
            'companyId' => ModuleModel::$company->getId(),
            'creationDate' => $creationDate,
            'creationDateTime' => $creationDateTime,
            'isPrivate' => true,
        ]);

        if ($return['success'] == true) {
            $return['totalItems'] = $return['total_items'];
        }
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
        $this->checkAclUpload(AclHelper::CONTROLLER_MEDIA);
        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }
        $return = [];
        if ($this->request->hasFiles()) {
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
        $this->checkAclUpload(AclHelper::CONTROLLER_MEDIA);
        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }
        $return = [];
        if ($this->request->hasFiles()) {
            $user_profile = ModuleModel::$user_profile;
            $user_login_token = ModuleModel::$user_login_token;
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
            $media->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
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
            if ($media && ($media->belongsToCurrentUserProfile() || ModuleModel::$user_profile->isAdmin())) {
                $countParams = [];
                /** REMOVE MEDIA DYNAMO DB */
                $removeMedia = $media->__quickRemove();
                $filesDeleted[] = $media;
                if ($removeMedia['success'] == false) {
                    $return = $removeMedia;
                    $return['message'] = "FILE_DELETE_FAIL_TEXT";
                    $this->db->rollback();
                    goto end_of_function;
                }

                $countParams['my_files'] = -1;
                $countParams['my_files_deleted'] = 1;
                $countParams['total_files'] = -1;

                /** COUNT MEDIA */
                $resultMediaCount = RelodayObjectMapHelper::__calculateUserMediaCount(ModuleModel::$user_profile->getUuid(), $countParams);

                if ($resultMediaCount['success'] == false) {
                    $return = $resultMediaCount;
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

    public function getUploadUrlAction()
    {
        $fileName = Helpers::__getRequestValue('name');
        $fileType = Helpers::__getRequestValue('type');
        $fileObject = Helpers::__getRequestValue('object');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $checkFileExisted = Media::findFirst([
            "conditions" => "name = :name: and file_extension = :file_extension: and user_profile_uuid = :user_profile_uuid: and is_deleted=:is_deleted_no:",
            "bind" => [
                'name' => pathinfo($fileName)['filename'],
                'file_extension' => $extension,
                'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
                'is_deleted_no' => Media::IS_DELETE_NO,
            ]
        ]);
        if ($checkFileExisted){
            $uuid = $checkFileExisted->getUuid();
        } else {
            $uuid = Helpers::__getRequestValue('uuid');
            if (!Helpers::__isValidUuid($uuid)) {
                $uuid = ApplicationModel::uuid();
            }
        }

        $fileNameUpload = ModuleModel::$company->getUuid() . '/' . $uuid . '.' . $extension;
        if ($fileObject && $fileObject == 'avatar') {
            $uuid = Helpers::__getRequestValue('avatar_object_uuid');
            $fileNameUpload = 'avatar/' . ModuleModel::$company->getUuid() . '/' . $uuid . '.' . $extension;
        }
        if ($fileObject && $fileObject != 'avatar') {
            $fileNameUpload = $fileObject . '/' . ModuleModel::$company->getUuid() . '/' . $uuid . '.' . $extension;
        }

        $return = RelodayS3Helper::__getPresignedUrlToUpload($fileNameUpload, $fileType);

        $return['uuid'] = $uuid;
        $return['$fileNameUpload'] = $fileNameUpload;
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
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
