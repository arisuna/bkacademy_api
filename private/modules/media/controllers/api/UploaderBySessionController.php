<?php

namespace SMXD\Media\Controllers\API;

use \SMXD\Media\Controllers\ModuleApiController;
use SMXD\Media\Models\MediaAttachment;

/**
 * Concrete implementation of Media module controller
 *
 * @RoutePrefix("/media/api")
 */
class UploaderBySessionController extends ModuleApiController
{
	/**
     * @Route("/uploaderbysession", paths={module="media"}, methods={"GET"}, name="media-uploaderbysession-index")
     */
    /**
     * @Route("/uploader", paths={module="gms"}, methods={"GET"}, name="gms-uploader-index")
     */

    private $ext_type = [], $current_user, $thumbnail;
    private $data_response = [
        "success" => false,
        "msg" => "",
        "data" => ""
    ];



    public function initialize()
    {
        $this->view->disable();

        //Get JSON File types
        $ext_type = file_get_contents($this->config->base_dir->public . 'server/file-type.json');
        $ext_type = json_decode($ext_type);

        foreach ($ext_type[0] as $keyT => $type) {
            foreach ($type as $key => $value) {
                $this->ext_type[$value] = $keyT;
            }
        }
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

        if($this->request->isPost()){

            $user = ModuleModel::$user;
            $user_login_token = ModuleModel::$user_login_token;

            $params = $this->request->getJsonRawBody();
            $search_text = $params->search;
            $page = $params->page;

            if( $search_text != ""){
                $media_db = Media::find([
                    "conditions" => "user_login_id = :user_login_id: AND name LIKE :search_text:",
                    "bind"   => [
                        "user_login_id" => $user->getUserLoginId(),
                        "search_text"   => "%".$search_text."%"
                    ],
                    "order"  => "created_at DESC",
                ]);
            }else{
                $media_db = Media::find([
                    "conditions" => "user_login_id = :user_login_id:",
                    "bind"   => [
                        "user_login_id" => $user->getUserLoginId(),
                    ],
                    "order"  => "created_at DESC",
                ]);
            }

            $paginator = new PaginatorModel([
                "data"  => $media_db,
                "limit" => 10,
                "page"  => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $media_db_array = [];

            $upload_path = $this->config->base_dir->data . 'upload/';

            Image::configure(['driver' => 'imagick']);

            foreach ($pagination->items as $media ) {

                $file_type = $this->getFileType(strtolower($media->getFileExtension()));

                $month = date('m', strtotime($media->getCreatedAt()));
                $year = date('Y', strtotime($media->getCreatedAt()));

                $file_path = $upload_path . $year . '/' . $month . '/' . $media->getFilename();
                $item['id'] = $media->getId();
                $item['uuid'] = $media->getUuid();
                $item['name'] = $media->getName();
                $item['file_type'] = $file_type;
                $item['file_extension'] = $media->getFileExtension();
                if ($item['file_type'] == 'image') {
                    if (file_exists( $file_path ) ){
                        if (!file_exists($this->config->base_dir->data . 'upload/thumbnail/' . $media->getFilename())) {
                            $image = Image::make($file_path)->resize(200, null, function ($constraint) {
                                $constraint->aspectRatio();
                            });
                            $image->save($this->config->base_dir->data . 'upload/thumbnail/' . $media->getFilename());
                        }
                    }
                }

                $item['image_data'] = [
                    "url_token"     => $media->getUrlToken(),
                    "url_full"      => $media->getUrlFull(),
                    "url_thumb"     => $media->getUrlThumb(),
                    "url_download"  => $media->getUrlDownload(),
                    "name"          => $media->getFilename()
                ];

                /*$item['image_data'] = [
                    "url"           => 'thumbnail/',
                    "name"          => $media->getFilename(),
                    "url_token"     => '/media/file/load/'.$media->getUuid().'/'.$media->getFileType()."/".$user_login_token->getToken()."/name/".urlencode($media->getName().".".$media->getFileExtension())
                ];*/
                $media_db_array[] = $item;
            }

        }

        $this->data_response['success']     = true;
        $this->data_response['data']        = $media_db_array;
        $this->data_response['page']        = ['totalItems' => $pagination->total_items];
        $this->response->setJsonContent($this->data_response);
        $this->response->send();
    }

    /*
    * Upload Media File
    */
    public function uploadAction()
    {
        if ($this->request->hasFiles()) {

            $user = ModuleModel::$user;
            $user_login_token = ModuleModel::$user_login_token;

            $files = $this->request->getUploadedFiles();
            $file = $files[0];

            $random = new Random();
            $uuid = $random->uuid();

            $file_info = new \stdClass();

            $file_info->name        = basename($file->getName(), '.' . $file->getExtension());
            $file_info->basename    = PhpSlug::generate(basename($file->getName(), '.' . $file->getExtension()));
            $file_info->extension   = $file->getExtension();
            $file_info->type        = $file->getType();
            $file_info->size        = $this->formatBytes($file->getSize());
            $file_info->key         = $file->getKey();
            $file_info->real_type   = $file->getRealType();

            /** MAKE UPLOAD FOLDER */
            $upload_path = $this->config->base_dir->data . 'upload/';

            $month = date('m');
            $year = date('Y');

            $folder_to_make = $upload_path . $year . '/' . $month;

            if ($this->makeFolder($year, $upload_path, 0777)) {
                if ($this->makeFolder($year . '/' . $month, $upload_path, 0777)) {
                    $is_mkdir = true;
                }
                else {
                    $is_mkdir = false;
                }
            }
            else {
                if ($this->makeFolder($year . '/' . $month, $upload_path, 0777)) {
                    $is_mkdir = true;
                }
                else {
                    $is_mkdir = false;
                }
            }

            /** SAVE TO DATABASE */
            if ($is_mkdir) {
                $media = new Media();

                $media->setName($file_info->name);
                $media->setUuid($uuid);
                $media->setFilename($uuid . '.' . $file_info->extension);
                $media->setFileExtension($file_info->extension);
                $media->setFileType($this->getFileType($file_info->extension));
                $media->setMimeType($file_info->type);
                $media->setMediaTypeId($this->getMediaType(strtolower($file_info->extension)));
                $media->setCreatedAt(date('Y-m-d H:i:s'));
                $media->setUserLoginId((int)$this->current_user->getId());

                try{
                    if ($media->save() === false) {
                        $error_message = $media->getMessages();

                        $this->data_response['success'] = false;
                        $this->data_response['message'] = implode('. ', $error_message );

                        goto end_upload_function;
                    }else{

                        $file->moveTo($upload_path . $year . '/' . $month . '/' . $uuid . '.' . $file_info->extension);
                        $file_info->filename = $media->getFilename();
                        $file_info->filePath = $year . '/' . $month . '/';

                        $this->data_response['success']                 = true;
                        $this->data_response['data']                    = (array)$file_info;
                        $this->data_response['data']['file_extension']  = $file_info->extension;
                        $this->data_response['data']['file_type']       = $this->getFileType(strtolower($media->getFileExtension()));
                        $this->data_response['data']['id']              = $media->getId();
                        $this->data_response['data']['name']            = $media->getName();

                        $this->data_response['data']['image_data']      = [
                            "url_token"     => $media->getUrlToken(),
                            "url_full"      => $media->getUrlFull(),
                            "url"           => 'thumbnail/',
                            "url_thumb"     => $media->getUrlThumb(),
                            "name"          => $media->getFilename()
                        ];
                    }
                }catch(\PDOException $e){
                    $this->data_response['success'] = false;
                    $this->data_response['message'] = $e->getMessage();
                    goto end_upload_function;
                }

                //create thumb // use queuing
                try{
                    $this->queue->choose('image_reloday');
                    $mediaJobUploadAmazonS3 = [
                        'action'        => 'upload',
                        'data'          => [
                            'media'         => $media->toArray(),
                            'awsAccessKey'  => $this->config->application->amazon_access_key_id,
                            'awsSecretKey'  => $this->config->application->amazon_secret_id,
                            'bucketName'    => $this->config->application->amazon_bucket_name,
                            'originDir'     => $this->config->base_dir->data . 'upload/'.
                                date('Y', strtotime($media->getCreatedAt()))."/".
                                date('m', strtotime($media->getCreatedAt()))."/",
                        ]
                    ];
                    $jobId_MediaUpload = $this->queue->put($mediaJobUploadAmazonS3,[
                        "priority" => 250,
                        "delay"    => 1,
                        "ttr"      => 60,
                    ]);

                    /*
                     * @TODO remove all task create thumb because we use lambda function
                    if( $media->getFileType() == 'image'){
                        $mediaJobCreateThumb = [
                            'action'        => 'create_thumb',
                            'data'          => [
                                'media'         => $media->toArray(),
                                'originDir'     => $this->config->base_dir->data . 'upload/'.
                                    date('Y', strtotime($media->getCreatedAt()))."/".
                                    date('m', strtotime($media->getCreatedAt()))."/",
                                'thumbDir'      => $this->config->base_dir->data . 'upload/thumbnail/',
                                'awsAccessKey'  => $this->config->application->amazon_access_key_id,
                                'awsSecretKey'  => $this->config->application->amazon_secret_id,
                                'bucketName'    => $this->config->application->amazon_bucket_name,
                            ]
                        ];

                        $jobId_MediaUpload = $this->queue->put($mediaJobCreateThumb,[
                            "priority" => 250,
                            "delay"    => 1,
                            "ttr"      => 60,
                        ]);
                    }
                    */

                    //put to AMAZON SES
                }catch(Exception $e){
                    $this->data_response['success'] = false;
                    $this->data_response['message'] = "COULD_NOT_CREATE_QUEUE" ;
                }
            } else {
                $this->data_response['success'] = false;
                $this->data_response['message'] = "COULD_NOT_CREATE_FOLDER";
                goto end_upload_function;
            }
        }

        end_upload_function:
        $this->response->setJsonContent($this->data_response);
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
     * Get MediaType
     */
    private function getMediaType($extension){
        $mediatypes = MediaType::find();
        if( file_exists($this->config->base_dir->public . 'server/media-type.json') ){
            $types = file_get_contents($this->config->base_dir->public . 'server/media-type.json');
            $types = json_decode($types);
        }else{
            $array_to_insert = [];
            $file = fopen($this->config->base_dir->public . 'server/media-type.json',"w+");
            foreach( $mediatypes as $type ){
                $array_to_insert[] = ['name' => $type->getName(), 'id' => $type->getId(), 'extensions' => $type->getDataExtensions() ];
            }
            fputs($file, json_encode($array_to_insert));
            fclose($file);
            $types = file_get_contents($this->config->base_dir->public . 'server/media-type.json');
            $types = json_decode($types);
        }

        foreach ($types as $key => $type) {
            if (in_array($extension, $type->extensions ) ) {
                return $type->id;
            }
        }
        return null;
    }
    /**
     * Create Folder
     */

    private function makeFolder($name, $path, $permission = 0755) {

        $folder = $path .  $name;

        if (!file_exists($folder . '/')) {
            if (mkdir($folder, $permission)) {
                return true;
            }
            else {
                return false;
            }
        }else{
            return true;
        }
    }
    /**
     * [attachAction description]
     * @return [type] [description]
     */
    public function attachAction(){

        $this->view->disable();
        $return = ['success' => false, 'message' => 'Upload was fail'];

        if( $this->request->isPost() && $this->request->isAjax() ){
            $uuid = $this->request->getPost('uuid');
            $attachments = $this->request->getPost('attachments');
            $type = $this->request->getPost('type');
            if( $uuid != '' && count( $attachments )  > 0 ){
                $return = MediaAttachment::__create_attachments_from_uuid( $uuid, $attachments , $type );
            }
        }

        $this->response->setJsonContent( $return );
        return $this->response->send();
    }


    /**
     * [attachAction description]
     * @return [type] [description]
     */
    public function attach_payloadAction(){

        $this->view->disable();
        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];

        if( $this->request->isPost() && $this->request->isAjax() ){
            $dataInput = $this->request->getJsonRawBody(true);

            $uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid']:'';
            $attachments = isset($dataInput['attachments']) && is_array($dataInput['attachments']) ? $dataInput['attachments']:'';
            $type = isset($dataInput['type']) && $dataInput['type'] != '' ? $dataInput['type']:'';

            if( $uuid != '' && count( $attachments )  > 0 ){
                $return = MediaAttachment::__create_attachments_from_uuid( $uuid, $attachments , $type );
            }
        }
        $this->response->setJsonContent( $return );
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function listAction($uuid){
        $this->view->disable();
        $return = ['success' => false, 'message' => 'Data was not found'];

        if( !$this->request->isGet() || !$this->request->isAjax() ) {
            exit('Access denied');
        }

        if( $uuid != ''){
            $attachments = MediaAttachment::__get_attachments_from_uuid( $uuid );
            $return = ['success' => true, 'data' => $attachments ];
        }

        $this->response->setJsonContent( $return );
        return $this->response->send();
    }
}
