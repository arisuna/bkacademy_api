<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Exception;
use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HttpStatusCode;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Gms\Models\MediaFolder;
use Reloday\Gms\Models\MediaType;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Media;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Intervention\Image\ImageManagerStatic as Image;
use Reloday\Application\Queue\MediaFileQueue;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Module;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MediaOldV1Controller extends BaseController
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
    public function testAction(){
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
        $creationDateTime = Helpers::__getRequestValue('creationDateTime');
        $fileType = Helpers::__getRequestValue('fileType');

        $return = Media::__findWithFilters([
            'query' => $query,
            'isHidden' => false,
            'page' => $page,
            'userProfileUuid' => ModuleModel::$user_profile->getUuid(),
            'folderUuid' => $folderUuid,
            'fileType' => $fileType,
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
     * @param string $folderUuid
     * @throws \Phalcon\Security\Exception
     */
    public function uploadAction()
    {
        $this->checkAclUpload(AclHelper::CONTROLLER_MEDIA);
        if ($this->request->hasFiles() == false) {
            $return = ['success' => false, 'message' => 'FILE_NOT_FOUND_TEXT'];
            goto end_upload_function;
        }

        $isHidden = $this->request->getQuery('isHidden');
        $isPrivate = $this->request->getQuery('isPrivate');
        $isPublic = $this->request->getQuery('isPrivate');


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


        /** SAVE TO DATABASE */
        $media = new Media();
        $media->setName($file_info->name);
        $media->setCompanyId($company ? $company->getId() : '');
        $media->setUuid($uuid);
        $media->setFilename($uuid . '.' . strtolower($file_info->extension));
        $media->setFileExtension(strtolower($file_info->extension));
        $media->setFileType(Media::__getFileType($file_info->extension));
        $media->setMimeType($file_info->type);
        $media->loadMediaType();
        $media->setCreatedAt(date('Y-m-d H:i:s'));
        $media->setUserLoginId((int)ModuleModel::$user_login->getId());
        $media->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $media->setIsHosted(Media::STATUS_HOSTED);
        if ($isHidden == true) {
            $media->setIsHidden(ModelHelper::YES);
            $media->setName($uuid);
            $media->setNameStatic($file_info->name);
        }
        if ($isPublic == true) {
            $media->setIsPrivate(ModelHelper::NO);
        }

        if ($isPrivate == true) {
            $media->setIsPrivate(ModelHelper::YES);
        }




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
                    $returnAttachment = MediaAttachment::__create_attachment_from_uuid($objectUuid, $media, MediaAttachment::MEDIA_GROUP_IMAGE);
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

        if ($media && $media->belongsToCurrentUserProfile()) {
            $this->db->begin();
            $resultDelete = $media->__quickRemove();

            if ($resultDelete['success'] == false) {
                $this->db->rollback();
                $return = $resultDelete;
                $return['message'] = 'FILE_DELETE_FAIL_TEXT';
                goto end_of_function;
            }
            /*
             * remove media don't remove attachment
            $attachments = MediaAttachment::find([
                "conditions" => "media_uuid = :media_uuid:",
                "bind" => [
                    "media_uuid" => $uuid
                ]]);

            if ($attachments->count()) {
                $resultDelete = ModelHelper::__quickRemoveCollection($attachments);
                if ($resultDelete['success'] == false) {
                    $this->db->rollback();
                    $return = $resultDelete;
                    $return['message'] = 'FILE_DELETE_FAIL_TEXT';
                    goto end_of_function;
                }
            }
            */

            $this->db->commit();
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
            $media->setName(Helpers::__getRequestValue('name'));
            //we should change the static name because this name if the display name
            $media->setNameStatic(Helpers::__getRequestValue('name'));
            $resultUpdate = $media->__quickUpdate();

            if ($resultUpdate['success'] == false) {
                $return = $resultUpdate;
                $return['message'] = 'FILE_UPDATE_FAIL_TEXT';
                goto end_of_function;
            }
            $return = ['success' => true, 'message' => 'FILE_UPDATE_SUCCESS_TEXT', 'data' => $media->getParsedData()];
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
        $resultCreate = $mediaFolder->__quickCreate();
        if ($resultCreate['success'] == false) {
            if (is_array($resultCreate['detail'])) {
                $resultCreate['message'] = reset($resultCreate['detail']);
            } else {
                $resultCreate['message'] = 'FOLDER_CREATE_FAIL_TEXT';
            }

        } else {
            $resultCreate['message'] = 'FOLDER_CREATE_SUCCESS_TEXT';
            $resultCreate['data'] = $mediaFolder;
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

        if ($mediaFolder && $mediaFolder->canEditFolder()) {
            $this->db->begin();
            $resultDelete = $mediaFolder->__quickRemove();
            if ($resultDelete['success'] == false) {
                $this->db->rollback();
                $return = $resultDelete;
                $return['message'] = isset($return['systemMessage']) ? $return['systemMessage'] : 'FOLDER_DELETE_FAIL_TEXT';
                goto end_of_function;
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

        $media->setData([
            'folder_uuid' => $folderUuid ? $folderUuid : null,
            'user_profile_uuid' => ModuleModel::$user_profile->getUuid(),
            'company_uuid' => ModuleModel::$company->getUuid(),
            'name' => $name,
        ]);

        //var_dump($media->toArray()); die();

        $validationFileName = $media->checkFileName();
        if ($validationFileName == true) {
            $return = [
                'success' => true,
                'message' => 'FILE_NAME_AVAILABLE_TEXT',
            ];
        } else {
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

        $size = Media::sum([
            'column' => 'size',
            'conditions' => 'user_profile_uuid = :user_profile_uuid:',
            'bind' => ['user_profile_uuid' => ModuleModel::$user_profile->getUuid()]
        ]);

        $totalItems = Media::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid:',
            'bind' => ['user_profile_uuid' => ModuleModel::$user_profile->getUuid()]
        ]);

        $totalFolders = MediaFolder::count([
            'conditions' => 'user_profile_uuid = :user_profile_uuid:',
            'bind' => ['user_profile_uuid' => ModuleModel::$user_profile->getUuid()]
        ]);

        $return = [
            'success' => true,
            'totalSize' => $size,
            'totalItems' => $totalItems,
            'totalFolders' => $totalFolders,
            'totalSizeHuman' => Helpers::__formatBytes($size, 2)
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

}
