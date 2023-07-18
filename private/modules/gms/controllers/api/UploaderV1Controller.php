<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Exception;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\MediaType;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Media;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Intervention\Image\ImageManagerStatic as Image;
use Reloday\Application\Queue\MediaFileQueue;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Reloday\Gms\Models\MediaAttachment;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class UploaderV1Controller extends BaseController
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
        $this->thumbnail = $this->config->thumbnail;
        $this->response->setContentType('application/json', 'UTF-8');
    }

    /*
    * Index page html
    */
    public function indexAction()
    {
        $media_db_array = [];

        if ($this->request->isPost()) {

            $user_profile = ModuleModel::$user_profile;
            $user_login_token = ModuleModel::$user_login_token;

            $params = $this->request->getJsonRawBody();
            $search_text = $params->search;
            $page = $params->page;

            if ($search_text != "") {
                $media_db = Media::find([
                    "conditions" => "user_login_id = :user_login_id: AND name LIKE :search_text: AND is_private = :is_private_yes:",
                    "bind" => [
                        "user_login_id" => $user_profile->getUserLoginId(),
                        "search_text" => "%" . $search_text . "%",
                        "is_private_yes" => Media::IS_PRIVATE_YES,
                    ],
                    "order" => "created_at DESC",
                ]);
            } else {
                $media_db = Media::find([
                    "conditions" => "user_login_id = :user_login_id: AND is_private = :is_private_yes:",
                    "bind" => [
                        "user_login_id" => $user_profile->getUserLoginId(),
                        "is_private_yes" => Media::IS_PRIVATE_YES,
                    ],
                    "order" => "created_at DESC",
                ]);
            }

            $paginator = new PaginatorModel([
                "data" => $media_db,
                "limit" => 10,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $media_db_array = [];
            foreach ($pagination->items as $media) {
                $file_type = $media->getFileType(strtolower($media->getFileExtension()));
                $item['id'] = $media->getId();
                $item['uuid'] = $media->getUuid();
                $item['name'] = $media->getName();
                $item['file_type'] = $file_type;
                $item['file_extension'] = $media->getFileExtension();

                $item['image_data'] = [
                    "url_token" => $media->getUrlToken(),
                    "url_full" => $media->getUrlFull(),
                    "url_thumb" => $media->getUrlThumb(),
                    "url_download" => $media->getUrlDownload(),
                    "name" => $media->getFilename()
                ];
                $media_db_array[] = $item;
            }
        }

        $this->data_response['success'] = true;
        $this->data_response['data'] = $media_db_array;
        $this->data_response['page'] = ['totalItems' => $pagination->total_items];
        $this->response->setJsonContent($this->data_response);
        $this->response->send();
    }

    /*
    * Upload Media File
    */
    public function uploadAction()
    {

        if ($this->request->hasFiles()) {
            $return = ['success' => false ,'msg' => 'FILE_NOT_FOUND_TEXT'];
        }

        $user_profile = ModuleModel::$user_profile;
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
        $media->setName($file_info->name);
        $media->setCompanyId($company ? $company->getId() : '');
        $media->setUuid($uuid);
        $media->setFilename($uuid . '.' . $file_info->extension);
        $media->setFileExtension($file_info->extension);
        $media->setFileType(Media::__getFileType($file_info->extension));
        $media->setMimeType($file_info->type);
        $media->loadMediaType();
        $media->setCreatedAt(date('Y-m-d H:i:s'));
        $media->setUserLoginId((int)ModuleModel::$user_login->getId());
        $media->setIsHosted(Media::STATUS_HOSTED);
        $resultUpload = $media->uploadToS3FromPath($file->getTempName());

        if ($resultUpload['success'] == false) {
            $return = $resultUpload;
            $return['message'] = "NO_FILES_TEXT";
            goto end_upload_function;
        }


        $resultSaveMedia = $media->__quickCreate();

        if ($resultSaveMedia['success'] == false) {
            $return = $resultSaveMedia;
            goto end_upload_function;
        } else {
            $return['success'] = true;
            $return['data'] = (array)$file_info;
            $return['data']['file_extension'] = $file_info->extension;
            $return['data']['file_type'] = $media->getFileType(strtolower($media->getFileExtension()));
            $return['data']['id'] = $media->getId();
            $return['data']['name'] = $media->getName();
            $return['data']['image_data'] = [
                "url_token" => $media->getUrlToken(),
                "url_full" => $media->getUrlFull(),
                "url" => 'thumbnail/',
                "url_thumb" => $media->getUrlThumb(),
                "name" => $media->getFilename()
            ];
        }


        end_upload_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    /*
    * Upload Media File
    */
    public function uploadPublicAction()
    {
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
            $media->setName($file_info->name);
            $media->setCompanyId($company ? $company->getId() : '');
            $media->setUuid($uuid);
            $media->setFilename($uuid . '.' . $file_info->extension);
            $media->setFileExtension($file_info->extension);
            $media->setFileType(Media::__getFileType($file_info->extension));
            $media->setMimeType($file_info->type);
            $media->loadMediaType();
            $media->setCreatedAt(date('Y-m-d H:i:s'));
            $media->setUserLoginId((int)$this->current_user->getId());
            $media->setIsHosted(Media::STATUS_HOSTED);
            $media->setIsPrivate(Media::IS_PRIVATE_NO);

            $result = $media->resizePublicImageAndUploadToS3($file->getTempName());

            if ($result['success'] == true) {
                try {
                    if ($media->save() === false) {
                        $error_message = $media->getMessages();
                        $return['success'] = false;
                        $return['message'] = implode('. ', $error_message);
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
                } catch (\PDOException $e) {
                    $return['success'] = false;
                    $return['message'] = $e->getMessage();
                    goto end_upload_function;
                } catch (Exception $e) {
                    $return['success'] = false;
                    $return['message'] = $e->getMessage();
                    goto end_upload_function;
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


    /** Format Size of file */
    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * Get File_type
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
     * Create Folder
     */

    private function makeFolder($name, $path, $permission = 0755)
    {
        $folder = $path . $name;
        if (!file_exists($folder . '/')) {
            if (mkdir($folder, $permission)) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }



    /**
     * @param $uuid
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
            if (is_bool($shared)) {
                $attachments = MediaAttachment::__get_attachments_from_uuid($uuid, $type, $shared);
            } else {
                $attachments = MediaAttachment::__get_attachments_from_uuid($uuid, $type);
            }
            $return = ['success' => true, 'data' => $attachments];
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
            $attachments = MediaAttachment::__get_attachments_from_uuid($uuid, null, MediaAttachment::IS_SHARED_YES);
            $return = ['success' => true, 'data' => $attachments];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
