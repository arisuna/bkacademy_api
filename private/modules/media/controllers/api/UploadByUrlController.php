<?php

namespace SMXD\Media\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use SMXD\Application\Lib\Helpers;
use SMXD\Media\Models\MediaType;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\Media;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Intervention\Image\ImageManagerStatic as Image;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use SMXD\Media\Models\MediaAttachment;

/**
 * Concrete implementation of Media module controller
 *
 * @RoutePrefix("/media/api")
 */
class UploadByUrlController extends BaseController
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

    /*
    *
    */

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

        if ($this->request->isPost()) {

            $user = ModuleModel::$user;
            $user_login_token = ModuleModel::$user_token;

            $params = $this->request->getJsonRawBody();
            $search_text = $params->search;
            $page = $params->page;

            if ($search_text != "") {
                $media_db = Media::find([
                    "conditions" => "user_login_id = :user_login_id: AND name LIKE :search_text:",
                    "bind" => [
                        "user_login_id" => $user->getUserLoginId(),
                        "search_text" => "%" . $search_text . "%"
                    ],
                    "order" => "created_at DESC",
                ]);
            } else {
                $media_db = Media::find([
                    "conditions" => "user_login_id = :user_login_id:",
                    "bind" => [
                        "user_login_id" => $user->getUserLoginId(),
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

    /*
    * Upload Media File
    */
    public function uploadAction()
    {
        $random = new Random();
        $month = date('m');
        $year = date('Y');

        $upload_path = $this->config->base_dir->data . 'upload/';
        if ($this->makeFolder($year, $upload_path, 0777)) {
            if ($this->makeFolder($year . '/' . $month, $upload_path, 0777)) {
                $make_folder = true;
            } else {
                $make_folder = false;
            }
        } else {
            $make_folder = false;
        }

        /** SAVE TO DATABASE */
        if ($make_folder) {

            $new_path = $upload_path . DS . $year . DS . $month;
            $img_url = $this->request->getPost();
            if (is_array($img_url)) {
                foreach ($img_url as $_img) {

                    $uuid = $random->uuid();
                    $_tmp = explode('/', $_img);
                    $real_name = end($_tmp);
                    $img = $new_path . DS . $real_name;
                    try {
                        $file_size = file_put_contents($img, file_get_contents($_img));
                        $file = pathinfo($img);
                    } catch (Exception $e) {
                        $this->data_response['success'] = false;
                        $this->data_response['message'] = $e->getMessage();
                        goto end_upload_function;
                    }

                    $file_info = new \stdClass();

                    $file_info->name = $real_name;
                    $file_info->basename = $real_name; //PhpSlug::generate($real_name);
                    $file_info->extension = $file['extension'];
                    $file_info->type = mime_content_type($img); // mime
                    $file_info->size = $file_size;
                    $file_info->real_type = filetype($img); // type of file

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

                    try {
                        if ($media->save() === false) {
                            $error_message = $media->getMessages();
                            $this->data_response['success'] = false;
                            $this->data_response['message'] = implode('. ', $error_message);
                            goto end_upload_function;
                        } else {
                            $this->data_response['success'] = true;
                            $new_file = $new_path . DS . $uuid . '.' . $file_info->extension;
                            rename($img, $new_file);
                            $this->data_response['media_data'][] = ['uuid' => $uuid, 'id' => $media->getId()];
                        }
                    } catch (\PDOException $e) {
                        $this->data_response['success'] = false;
                        $this->data_response['message'] = $e->getMessage();
                        goto end_upload_function;
                    }

                    //create thumb // use queuing
                    try {
                        $this->__createThumb($media);
                    } catch (\Exception $e) {
                        $this->data_response['success'] = false;
                        $this->data_response['message'] = "COULD_NOT_CREATE_QUEUE";
                    }
                }
            }

        } else {
            $this->data_response['success'] = false;
            $this->data_response['message'] = "COULD_NOT_CREATE_FOLDER";
            goto end_upload_function;
        }

        end_upload_function:
        $this->response->setJsonContent($this->data_response);
        $this->response->send();
    }

    /**
     * @param Media $media
     */
    private function __createThumb(Media $media)
    {
        if ($this->queue) {
            try {
                $this->queue->choose('image_reloday');
                $mediaJobUploadAmazonS3 = [
                    'action' => 'upload',
                    'data' => [
                        'media' => $media->toArray(),
                        'bucketName' => $this->config->application->amazon_bucket_name,
                        'originDir' => $this->config->base_dir->data . 'upload/' .
                            date('Y', strtotime($media->getCreatedAt())) . "/" .
                            date('m', strtotime($media->getCreatedAt())) . "/",
                    ]
                ];
                $jobId_MediaUpload = $this->queue->put($mediaJobUploadAmazonS3, [
                    "priority" => 250,
                    "delay" => 1,
                    "ttr" => 60,
                ]);
            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
    }


    /**
     * @param Media $media
     */
    private function __addQueueTransferToS3(Media $media)
    {
        if ($this->queue) {
            try {
                $this->queue->choose('image_reloday');
                $mediaJobUploadAmazonS3 = [
                    'action' => 'upload',
                    'data' => [
                        'media' => $media->toArray(),
                        'bucketName' => $this->config->application->amazon_bucket_name,
                        'originDir' => $this->config->base_dir->data . 'upload/' .
                            date('Y', strtotime($media->getCreatedAt())) . "/" .
                            date('m', strtotime($media->getCreatedAt())) . "/",
                    ]
                ];
                $jobId_MediaUpload = $this->queue->put($mediaJobUploadAmazonS3, [
                    "priority" => 250,
                    "delay" => 1,
                    "ttr" => 60,
                ]);
            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
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
    private function getMediaType($extension)
    {
        $mediatypes = MediaType::find();
        if (file_exists($this->config->base_dir->public . 'server/media-type.json')) {
            $types = file_get_contents($this->config->base_dir->public . 'server/media-type.json');
            $types = json_decode($types);
        } else {
            $array_to_insert = [];
            $file = fopen($this->config->base_dir->public . 'server/media-type.json', "w+");
            foreach ($mediatypes as $type) {
                $array_to_insert[] = ['name' => $type->getName(), 'id' => $type->getId(), 'extensions' => $type->getDataExtensions()];
            }
            fputs($file, json_encode($array_to_insert));
            fclose($file);
            $types = file_get_contents($this->config->base_dir->public . 'server/media-type.json');
            $types = json_decode($types);
        }

        foreach ($types as $key => $type) {
            if (in_array($extension, $type->extensions)) {
                return $type->id;
            }
        }
        return null;
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
     * [attachAction description]
     * @return [type] [description]
     */
    public function attachAction()
    {

        $this->view->disable();
        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];

        if ($this->request->isPost() && $this->request->isAjax()) {
            $uuid = $this->request->getPost('uuid');
            $attachments = $this->request->getPost('attachments');
            $type = $this->request->getPost('type');
            if ($uuid != '' && count($attachments) > 0) {
                $return = MediaAttachment::__create_attachments_from_uuid($uuid, $attachments, $type);
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * upload direct to S3
     */
    public function uploadSingleAction()
    {
        $random = new Random();
        $month = date('m');
        $year = date('Y');

        $objectUuid = Helpers::__getRequestValue('uuid');
        $url = Helpers::__getRequestValue('url');

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if ($url != '' && Helpers::__isUrl($url)) {

            $uuid = $random->uuid();
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

                $media = new Media();
                $media->setName($file_info->name);
                $media->setUuid($uuid);
                $media->setFilename($uuid . '.' . $file_info->extension);
                $media->setFileExtension($file_info->extension);
                $media->setFileType($this->getFileType($file_info->extension));
                $media->setMimeType($file_info->mime);
                $media->setMediaTypeId($this->getMediaType(strtolower($file_info->extension)));
                $media->setCreatedAt(date('Y-m-d H:i:s'));
                $media->setUserLoginId((int)$this->current_user->getId());
                $media->setIsHosted(Media::STATUS_HOSTED);
                try {
                    if ($media->save() === false) {
                        $error_message = $media->getMessages();
                        $this->data_response['success'] = false;
                        $this->data_response['message'] = implode('. ', $error_message);
                        goto end_upload_function;
                    } else {
                        $return = [
                            'success' => true,
                            'data' => $media
                        ];
                    }
                } catch (\PDOException $e) {
                    $this->db->rollback();
                    $return = [
                        'success' => true,
                        'message' => 'UPLOAD_FAIL_TEXT',
                        'detail' => $e->getMessage()
                    ];
                    goto end_upload_function;
                } catch (Exception $e) {
                    $this->db->rollback();
                    $return = [
                        'success' => true,
                        'message' => 'UPLOAD_FAIL_TEXT',
                        'detail' => $e->getMessage()
                    ];
                    goto end_upload_function;
                }

                try {
                    $res = $this->__uploadToS3($media, $file_content);
                    if ($res['success'] == false) {
                        $this->db->rollback();

                        $return = [
                            'success' => false,
                            'message' => 'UPLOAD_TO_S3_FAIL_TEXT',
                            'detail' => $res['detail']
                        ];

                        goto end_upload_function;
                    }
                } catch (\Exception $e) {
                    $this->db->rollback();

                    $return = [
                        'success' => false,
                        'message' => 'CAN_NOT_UPLOAD_FILE_TEXT',
                        'detail' => $e->getMessage()
                    ];

                    goto end_upload_function;
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
                        $return = [
                            'success' => true,
                            'data' => $media,
                            'message' => 'FILE_UPLOAD_SUCCESS_TEXT',
                        ];
                    }
                }
                $this->db->commit();
            } else {

                $return = [
                    'success' => false,
                    'message' => 'FILE_IMAGE_NOT_FOUND_TEXT',
                ];
                goto end_upload_function;
            }
        } else {

            $return = [
                'success' => false,
                'message' => 'COULD_NOT_CREATE_FOLDER_TEXT',
            ];

            goto end_upload_function;
        }

        end_upload_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function __uploadToS3($media, $content)
    {

        $s3 = $this->getDi()->get('aws')->createS3();

        try {
            // Upload data.
            $fileFullName = $media->getMediaType()->getAmazonPath() . "/" . $media->getFilename();
            $result = $s3->putObject(array(
                'Bucket' => $this->moduleConfig->application->amazon_bucket_name,
                'Key' => $fileFullName,
                'Body' => $content,
                'ACL' => 'authenticated-read'
            ));
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (Aws\S3\Exception\S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Aws\Exception\AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }
}
