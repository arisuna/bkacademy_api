<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Gms\Models\MediaType;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Media;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Reloday\Application\Lib\RelodayMediaHelper;

use Intervention\Image\ImageManagerStatic as Image;
use Reloday\Application\Queue\MediaFileQueue;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Reloday\Gms\Models\MediaAttachment;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class UploaderController extends BaseController
{
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
        $this->ext_type = RelodayMediaHelper::$ext_groups;
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
                    "conditions" => "user_login_id = :user_login_id: AND name LIKE :search_text:",
                    "bind" => [
                        "user_login_id" => $user_profile->user_login_id,
                        "search_text" => "%" . $search_text . "%"
                    ],
                    "order" => "created_at DESC",
                ]);
            } else {
                $media_db = Media::find([
                    "conditions" => "user_login_id = :user_login_id:",
                    "bind" => [
                        "user_login_id" => $user_profile->user_login_id,
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

            $upload_path = $this->config->base_dir->data . 'upload/';

            Image::configure(['driver' => 'imagick']);

            foreach ($pagination->items as $media) {

                $file_type = $this->getFileType(strtolower($media->getFileExtension()));

                $month = date('m', strtotime($media->getCreatedAt()));
                $year = date('Y', strtotime($media->getCreatedAt()));

                $file_path = $upload_path . $year . '/' . $month . '/' . $media->getFilename();


                $item['id'] = $media->getId();
                $item['name'] = $media->getName();
                $item['file_type'] = $file_type;
                $item['file_extension'] = $media->getFileExtension();

                if ($item['file_type'] == 'image') {
                    if (file_exists($file_path)) {
                        if (!file_exists($this->config->base_dir->data . 'upload/thumbnail/' . $media->getFilename())) {
                            $image = Image::make($file_path)->resize(200, null, function ($constraint) {
                                $constraint->aspectRatio();
                            });

                            $image->save($this->config->base_dir->data . 'upload/thumbnail/' . $media->getFilename());

                        }
                    }
                }
                $item['image_data'] = [
                    "url_token" => $media->getUrlToken(),
                    "url_full" => $media->getUrlFull(),
                    "url_thumb" => $media->getUrlThumb(),
                    "url_download" => $media->getUrlDownload(),
                    "name" => $media->getFilename()
                ];

                /*$item['image_data'] = [
                    "url"           => 'thumbnail/',
                    "name"          => $media->getFilename(),
                    "url_token"     => '/media/file/load/'.$media->getUuid().'/'.$media->getFileType()."/".$user_login_token->getToken()."/name/".urlencode($media->getName().".".$media->getFileExtension())
                ];*/
                $media_db_array[] = $item;
            }

        }

        $this->data_response['success'] = true;
        $this->data_response['data'] = $media_db_array;
        $this->data_response['page'] = ['totalItems' => $pagination->total_items];
        $this->response->setJsonContent($this->data_response);
        $this->response->send();
    }

    /**
     * upload file GMS
     */
    public function uploadMediaAction()
    {
        $this->checkAjax(['PUT', 'POST']);

        if ($this->request->hasFiles()) {

            $files = $this->request->getUploadedFiles();
            $file = $files[0];

            $random = new Random();
            $uuid = $random->uuid();

            $file_info = new \stdClass();
            $file_info->name = basename($file->getName(), '.' . $file->getExtension());
            $file_info->file_name = PhpSlug::generate(basename($file->getName(), '.' . $file->getExtension()));
            $file_info->file_extension = $file->getExtension();
            $file_info->file_type = $file->getType();
            $file_info->file_size = RelodayMediaHelper::__formatBytes($file->getSize());
            $file_info->file_key = $file->getKey();
            $file_info->file_real_type = $file->getRealType();
            $file_info->mime_type = $file->getRealType();

            //save to DynamoDBS
            $media_uuid = (new Random())->uuid();
            $relodayMediaHelper = new RelodayMediaHelper();
            $relodayMediaHelper->setFileName($file->getName(), '.' . $file->getExtension());
            $relodayMediaHelper->setMimeType($file->getType());
            $relodayMediaHelper->setFileExtension($file->getExtension());
            $relodayMediaHelper->setBucketName(getenv('AMAZON_BUCKET_NAME'));
            $relodayMediaHelper->setFileInfo((array)$file_info);
            $relodayMediaHelper->setFileSize(RelodayMediaHelper::__formatBytes($file->getSize()));
            $relodayMediaHelper->setCompanyUuid(ModuleModel::$company->getUuid());
            $relodayMediaHelper->setMediaUuid($media_uuid);
            $relodayMediaHelper->setUserProfileUuid(ModuleModel::$user_profile->getUuid());
            $relodayMediaHelper->createInDynamoDb();


            //move file to S3
            $relodayMediaHelper->uploadToS3FromPath($file->getTempName());


            $return = ['success' => false, 'data' => (array)$file_info];
            $return['success'] = true;
            $return['data'] = (array)$file_info;
            $return['data']['file_extension'] = $file_info->file_extension;
            $return['data']['file_type'] = $relodayMediaHelper->getFileTypeName();
            $return['data']['uuid'] = $relodayMediaHelper->getMediaUuid();
            $return['data']['name'] = $relodayMediaHelper->getFileName();

            $relodayMediaHelper->setCurrentSecurityToken(ModuleModel::$user_login_token);

            $return['data']['image_data'] = [
                "url_download" => $relodayMediaHelper->getUrlDownload(),
                "url_token" => $relodayMediaHelper->getUrlToken(),
                "url_full" => $relodayMediaHelper->getUrlFull(),
                "url_thumb" => $relodayMediaHelper->getUrlThumb(),
                "name" => $relodayMediaHelper->getFilename()
            ];

        } else {
            $return = [
                'success' => false,
                'message' => 'FILE_NOT_FOUND_TEXT'
            ];
            goto end_upload_function;
        }

        end_upload_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    /**
     * [attachAction description]
     * @return [type] [description]
     */
    public function createAttachmentAction()
    {
        $this->checkAjax('POST');
        $this->view->disable();
        $return = ['success' => false, 'message' => 'ATTACH_FAIL_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $media = Helpers::__getRequestValue('media');
        $type = Helpers::__getRequestValue('type');
        $media = (array)$media;
        $relodayHelper = new RelodayMediaHelper();
        $relodayHelper->setMediaUuid($media['uuid']);
        $relodayHelper->setObjectUuid($uuid);
        $relodayHelper->setObjectType($type);
        $relodayHelper->getMediaFromDynamoDb();
        $result = $relodayHelper->attachToObject();
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * remove an attachment from data
     * @return [type] [description]
     */
    public function removeAttachmentAction()
    {
        $this->view->disable();
        $this->checkAjax('DELETE');

        $object_uuid = $this->dispatcher->getParam('object_uuid');
        $media_uuid = $this->dispatcher->getParam('media_uuid');

        $return = ['success' => false, 'message' => 'ATTACHMENT_DELETE_FAIL_TEXT'];

        $relodayHelper = new RelodayMediaHelper();
        $relodayHelper->setMediaUuid($media_uuid);
        $relodayHelper->setObjectUuid($object_uuid);

        $return = $relodayHelper->deleteAttachment();
        if ($return['success'] == false) {
            $return['message'] = 'ATTACHMENT_DELETE_FAIL_TEXT';
        }else{
            $return['message'] = 'ATTACHMENT_DELETE_SUCCESS_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function getListByUuidAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $return = ['success' => true, 'data' => []];

        if( $uuid != '' && Helpers::__isValidUuid( $uuid )) {
            $dataList = [];
            $data = RelodayMediaHelper::__findAllByObjectUuid( $uuid );
            foreach( $data as $item ){
                $item->setCurrentSecurityToken( ModuleModel::$user_login_token );
                $dataList[] = $item->toArray();
            }
            $return['data'] = $dataList;
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function searchMediaAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
    }
}