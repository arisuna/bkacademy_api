<?php

namespace SMXD\Media\Controllers\API;

use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Intervention\Image\ImageManagerStatic as Image;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use SMXD\Application\Lib\PersonHelper;
use SMXD\Application\Lib\SMXDLetterImage;
use SMXD\Application\Lib\SMXDMediaHelper;
use SMXD\Application\Lib\SMXDS3Helper;
use SMXD\Application\Models\DependantExt;
use SMXD\Application\Models\MediaExt;
use SMXD\Application\Queue\MediaFileQueue;
use SMXD\Media\Controllers\ModuleApiController;
use SMXD\Media\Controllers\API\BaseController;
use SMXD\Application\Lib\Helpers;
use SMXD\Media\Models\Company;
use SMXD\Media\Models\MediaType;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\Media;
use SMXD\Media\Models\Contact;
use SMXD\Media\Models\MediaAttachment;
use SMXD\Media\Models\ObjectAvatar;
use SMXD\Media\Models\User;
use SMXD\Media\Models\Employee;

/**
 * Concrete implementation of Media module controller
 *
 * @RoutePrefix("/media/api")
 */
class AvatarController extends BaseController
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
    public function indexAction()
    {
        die(__FUNCTION__);
    }

    /** initialize */
    public function initialize()
    {
        $this->ext_type = SMXDMediaHelper::$ext_types;
        $this->current_user = ModuleModel::$user;
        $this->thumbnail = $this->config->thumbnail;
        $this->response->setContentType('application/json', 'UTF-8');

    }

    /**
     * @param $user_uuid
     * @return mixed
     */
    public function profileAction($user_uuid)
    {
        $this->view->disable();
        $profile = User::findFirstByUuid($user_uuid);
        $result = ['success' => false, 'data' => '', 'raw' => $profile];
        if ($profile) {
            $avatar = $profile->getAvatar();
            $result = [
                'success' => true,
                'data' => [
                    'image_url' => $avatar && $avatar['image_data']
                    && isset($avatar['image_data']) && isset($avatar['image_data']['url_thumb'])
                        ? $avatar['image_data']['url_thumb'] : "",
                    'name' => $profile->getFirstname() . " " . $profile->getLastname()
                ]
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @param $user_uuid
     * @return mixed
     */
    public function employeeAction($user_uuid)
    {
        $this->view->disable();
        $profile = Employee::findFirstByUuid($user_uuid);
        $result = ['success' => false, 'data' => '', 'raw' => $profile];
        if ($profile) {
            $avatar = $profile->getAvatar();
            $result = [
                'success' => true,
                'data' => [
                    'image_url' => $avatar && $avatar['image_data']
                    && isset($avatar['image_data']) && isset($avatar['image_data']['url_thumb'])
                        ? $avatar['image_data']['url_thumb'] : "",
                    'name' => $profile->getFirstname() . " " . $profile->getLastname()
                ]
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $user_uuid
     * @return mixed
     */
    public function getObjectAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);
        $result = ['success' => false, 'data' => '', 'message' => 'AVATAR_NOT_FOUND_TEXT'];
        if ($avatar && !is_null($avatar)) {

            $media = $avatar->toArray();
            $media['image_data']['url_thumb'] = $avatar->getUrlThumb();
            $media['image_data']['url_full'] = $avatar->getUrlThumb();
            $media['image_data']['url_download'] = $avatar->getUrlThumb();

            $result = [
                'success' => true,
                'data' => $media
            ];
        }else{
            $di = \Phalcon\DI::getDefault();
            $bucketName = $di->get('appConfig')->aws->bucket_thumb_name;
            $file_logo = 'no-image.png';
            $temp = SMXDS3Helper::__getPresignedUrl('finance/no-image.png', $bucketName, $file_logo, 'image/png');
            $media['image_data']['url_thumb'] = $temp;
            $media['image_data']['url_full'] = $temp;
            $media['image_data']['url_download'] = $temp;
            $result = [
                'success' => true,
                'data' => $media
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function uploadAction()
    {
        if ($this->request->hasFiles()) {

            $user = ModuleModel::$user;
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
            $media->setFileType($media->getFileType($file_info->extension));
            $media->setMimeType($file_info->type);
            $media->loadMediaType();
            $media->setCreatedAt(date('Y-m-d H:i:s'));
            $media->setUserLoginId((int)$this->current_user->getId());
            $media->setIsHosted(Media::STATUS_HOSTED);
            $result = $media->uploadToS3FromPath($file->getTempName());

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

                        $return['data']['image_data'] = [
                            "url_token" => $media->getUrlToken(),
                            "url_full" => $media->getUrlFull(),
                            "url" => 'thumbnail/',
                            "url_thumb" => $media->getUrlThumb(),
                            "name" => $media->getFilename()
                        ];
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

    /*
    * Upload Media File
    */
    public function uploadOldAction()
    {
        if ($this->request->hasFiles()) {

            $user = ModuleModel::$user;
            $user_login_token = ModuleModel::$user_login_token;

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

            /** MAKE UPLOAD FOLDER */
            $upload_path = $this->config->base_dir->data . 'upload/';

            $month = date('m');
            $year = date('Y');

            $folder_to_make = $upload_path . $year . '/' . $month;

            if ($this->makeFolder($year, $upload_path, 0777)) {
                if ($this->makeFolder($year . '/' . $month, $upload_path, 0777)) {
                    $is_mkdir = true;
                } else {
                    $is_mkdir = false;
                }
            } else {
                if ($this->makeFolder($year . '/' . $month, $upload_path, 0777)) {
                    $is_mkdir = true;
                } else {
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

                $return = [];
                try {
                    if ($media->save() === false) {
                        $error_message = $media->getMessages();

                        $return['success'] = false;
                        $return['message'] = implode('. ', $error_message);

                        goto end_upload_function;
                    } else {

                        $file->moveTo($upload_path . $year . '/' . $month . '/' . $uuid . '.' . $file_info->extension);
                        $file_info->filename = $media->getFilename();
                        $file_info->filePath = $year . '/' . $month . '/';

                        $return['success'] = true;
                        $return['data'] = (array)$file_info;
                        $return['data']['file_extension'] = $file_info->extension;
                        $return['data']['file_type'] = $this->getFileType(strtolower($media->getFileExtension()));
                        $return['data']['id'] = $media->getId();
                        $return['data']['name'] = $media->getName();
                        $return['data']['uuid'] = $media->getUuid();

                        $return['data']['image_data'] = [
                            "url_token" => $media->getUrlToken(),
                            "url_full" => $media->getUrlFull(),
                            "url_thumb" => $media->getUrlThumb(),
                            "name" => $media->getFilename()
                        ];
                    }
                } catch (\PDOException $e) {
                    $return['success'] = false;
                    $return['message'] = $e->getMessage();
                    goto end_upload_function;
                }

                //create thumb // use queuing
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
                        "delay" => 10,
                        "ttr" => 60,
                    ]);

                    if ($media->getFileType() == Media::FILE_TYPE_IMAGE_NAME) {
                        $mediaJobCreateThumb = [
                            'action' => 'create_thumb',
                            'data' => [
                                'media' => $media->toArray(),
                                'originDir' => $this->config->base_dir->data . 'upload/' .
                                    date('Y', strtotime($media->getCreatedAt())) . "/" .
                                    date('m', strtotime($media->getCreatedAt())) . "/",
                                'thumbDir' => $this->config->base_dir->data . 'upload/thumbnail/',
                                'bucketName' => $this->config->application->amazon_bucket_name,
                            ]
                        ];

                        $jobId_MediaUpload = $this->queue->put($mediaJobCreateThumb, [
                            "priority" => 250,
                            "delay" => 10,
                            "ttr" => 60,
                        ]);
                    }

                    //put to AMAZON SES
                } catch (Exception $e) {
                    $return['success'] = false;
                    $return['message'] = "COULD_NOT_CREATE_QUEUE";
                }
            } else {
                $return['success'] = false;
                $return['message'] = "COULD_NOT_CREATE_FOLDER";
                goto end_upload_function;
            }
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
            $media = $this->request->getPost('media');
            $type = $this->request->getPost('type');
            if ($uuid != '' && !is_null($media)) {
                $return = MediaAttachment::__create_attachment_from_uuid($uuid, $media, $type);
            }
        } else {
            $return = ['success' => false, 'message' => 'ACCESS_RESTRICT_TEXT'];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * [attachAction description]
     * @return [type] [description]
     */
    public function attach_avatarAction()
    {
        $this->view->disable();
        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        if ($this->request->isPost() && $this->request->isAjax()) {
            $uuid = $this->request->getPost('uuid');
            $media = $this->request->getPost('media');

            if ($uuid != '' && !is_null($media)) {
                $return = MediaAttachment::__create_attachment_from_uuid($uuid, $media, MediaAttachment::MEDIA_GROUP_AVATAR);
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [attachAction description]
     * @return [type] [description]
     */
    public function attach_logoAction()
    {
        $this->view->disable();
        $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
        if ($this->request->isPost() && $this->request->isAjax()) {
            $uuid = $this->request->getPost('uuid');
            $media = $this->request->getPost('media');
            $type = $this->request->getPost('type');

            if ($type == '') {
                $type = MediaAttachment::MEDIA_GROUP_LOGO;
            }
            if ($uuid != '' && !is_null($media)) {
                $return = MediaAttachment::__create_attachment_from_uuid($uuid, $media, $type);
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public function thumbdirectAction($uuid = '')
    {
        $this->view->disable();
        $token = $this->request->get('token');


        if ($token == '' || $uuid == '') {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        if (!$uuid) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);

        if ($avatar) {
//            $createAtInSecond = Helpers::__convertDateToSecond($avatar->getCreatedAt());
//            if (time() - $createAtInSecond >= 86400) {
//                $url = $avatar->getThumbCloudFrontUrl();
//            } else {
//                $url = $avatar->getUrlThumb();
//                if ($url == false) {
//                    $url = $avatar->getThumbCloudFrontUrl();
//                    if ($url == false) {
//                        goto else_function;
//                    }
//                }
//            }

            $url = $avatar->getTemporaryThumbS3Url();
            if ($url == false) {
                $url = $avatar->getThumbCloudFrontUrl();
                if ($url == false) {
                    goto else_function;
                }
            }

            $this->response->setContentType($avatar->getMimeType());
            $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
            header('Location: ' . $url);
        } else {
            else_function:
            $profile = PersonHelper::__getProfile($uuid);

            if ($profile) {
                $name = $profile->getFirstname() . " " . $profile->getLastname();
            } else {
                $name = "SMXD";
            }

            if (!$name || !trim($name)) {
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'block'
                ]);
            }

            $avatar = new SMXDLetterImage($name, 'circle', 64);

            $cloudFrontUrl = $avatar->getS3Url();

            $this->response->setContentType(Media::MIME_TYPE_PNG);
            $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getFileName() . '"');
            header('Location: ' . $cloudFrontUrl);
            return $this->response->send();
        }
    }

    /**
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public function thumbdirectV2Action($uuid = '')
    {
        $this->view->disable();
        $token = $this->request->get('token');
        if ($token == '' || $uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        if (Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);

        if ($avatar) {
            $createAtInSecond = Helpers::__convertDateToSecond($avatar->getCreatedAt());
            if (time() - $createAtInSecond >= 86400) {
                $url = $avatar->getThumbCloudFrontUrl();
            } else {
                $url = $avatar->getUrlThumb();
                if ($url == false) {
                    $url = $avatar->getThumbCloudFrontUrl();
                    if ($url == false) {
                        goto else_function;
                    }
                }
            }

            $this->response->setContentType($avatar->getMimeType());
            $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
            header('Location: ' . $url);
        } else {
            else_function:

            return $this->response->send();
        }
    }


    /**
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public function getThumbCloudfrontAction($uuid)
    {
        $this->view->disable();
        $token = $this->request->get('token');
        if ($token == '' || $uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

//        $avatar = MediaAttachment::__getAvatarAttachment($uuid);
        $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);

        if ($avatar) {
            //TODO use presigned URL instead of BodyContent

            $cloudFrontUrl = $avatar->getThumbCloudFrontUrl();
            $this->response->setContentType($avatar->getMimeType());
            $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
            header('Location: ' . $cloudFrontUrl);

        } else {
            $user = User::findFirstByUuidCache($uuid);
            $name = "RELOTALENT";
            if ($user) {
                $name = $user->getFirstname() . " " . $user->getLastnaxme();
            } else {
                $employee = Employee::findFirstByUuidCache($uuid);
                if ($employee) {
                    $name = $employee->getFirstname() . " " . $employee->getLastname();
                } else {
                    $depedant = DependantExt::findFirstByUuid($uuid);
                    if ($depedant) {
                        $name = $depedant->getFirstname() . " " . $depedant->getLastname();
                    }
                }
            }
            $avatar = new SMXDLetterImage($name, 'circle', 64);
            $avatar->setColorInternal();
            $content = $avatar->__toString();
            $this->response->setContentType(Media::MIME_TYPE_PNG);
            $this->response->setContent(base64_decode(explode(',', $content)[1]));
            return $this->response->send();
        }
    }

    /**
     * @param $uuid
     */
    public function getThumbDirectAction($uuid)
    {
        $this->view->disable();
        $token = $this->request->get('token');
        if ($token == '' || $uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }
        $avatar = MediaAttachment::__getAvatarAttachment($uuid);

        if ($avatar) {
            //TODO use presigned URL instead of BodyContent

            $imgData = $avatar->getUrlThumb();
            if ($imgData == false) {
                $imgData = $avatar->getThumbCloudFrontUrl();
            }

            if ($imgData != false) {
                $this->response->setContentType($avatar->getMimeType());
                $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
                header('Location: ' . $imgData);
                return $this->response->send();
            } else {
                goto else_function;
            }

        } else {
            else_function:
            $user = User::findFirstByUuidCache($uuid);
            $name = "RELOTALENT";
            if ($user) {
                $name = $user->getFirstname() . " " . $user->getLastname();
            } else {
                $employee = Employee::findFirstByUuidCache($uuid);
                if ($employee) {
                    $name = $employee->getFirstname() . " " . $employee->getLastname();
                } else {
                    $depedant = DependantExt::findFirstByUuid($uuid);
                    if ($depedant) {
                        $name = $depedant->getFirstname() . " " . $depedant->getLastname();
                    }
                }
            }
            $avatar = new SMXDLetterImage($name, 'circle', 64);
            $avatar->setColorInternal();
            $content = $avatar->__toString();
            $this->response->setContentType(Media::MIME_TYPE_PNG);
            $this->response->setContent(base64_decode(explode(',', $content)[1]));
            return $this->response->send();
        }
    }

    /**
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public function contactThumbAction($uuid)
    {
        $this->view->disable();
        $token = $this->request->get('token');

        if ($token == '' || $uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }
        $auth = ModuleModel::checkauth($token, $this->config);
        if (!$auth['success']) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'expire'
            ]);
        }

        $result = ['success' => false, 'data' => '', 'uuid' => $uuid];
        $avatar = MediaAttachment::__getAvatarAttachment($uuid);

        if ($avatar) {
            //TODO use presigned URL instead of BodyCOntent
            $imgData = $avatar->getUrlThumb();
            if ($imgData == false) {
                $imgData = $avatar->getThumbCloudFrontUrl();
            }

            if ($imgData != false) {
                $this->response->setContentType($avatar->getMimeType());
                $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
                header('Location: ' . $imgData);
                return $this->response->send();
            }
        }
        $contactProfile = Contact::findFirstByUuidCache($uuid);
        $name = "RELOTALENT";
        if ($contactProfile) {
            $name = $contactProfile->getFirstname() . " " . $contactProfile->getLastname();
        }
        $avatar = new SMXDLetterImage($name, 'circle', 64);
        $avatar->setColorInternal();
        $content = $avatar->__toString();
        $this->response->setContentType(Media::MIME_TYPE_PNG);
        $this->response->setContent(base64_decode(explode(',', $content)[1]));
        return $this->response->send();
    }


    /**
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public function companyLogoThumbAction($uuid)
    {
        $this->view->disable();
        $token = $this->request->get('token');

        if ($token == '' || $uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        $result = ['success' => false, 'data' => '', 'uuid' => $uuid];
//        $avatar = MediaAttachment::__getLogo($uuid);
        $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);

        if ($avatar) {
            //TODO use presigned URL instead of BodyCOntent
            $imgData = $avatar->getUrlThumb();
            if ($imgData == false) {
                $imgData = $avatar->getThumbCloudFrontUrl();
            }

            if ($imgData != false) {
                $this->response->setContentType($avatar->getMimeType());
                $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
                header('Location: ' . $imgData);
                return $this->response->send();
            }
        }
        $this->response->setContentType(Media::MIME_TYPE_SVG_XML);
        $this->response->setContent("<svg width=\"512\" height=\"512\" xmlns=\"http://www.w3.org/2000/svg\">
 <g>
  <title>background</title>
  <rect fill=\"none\" id=\"canvas_background\" height=\"402\" width=\"582\" y=\"-1\" x=\"-1\"/>
 </g>
 <g>
  <title>Layer 1</title>
  <path id=\"XMLID_3768_\" fill=\"#56aaff\" d=\"m101.9,416l64.2,0l0,-243.7l-64.2,0l0,243.7zm58.5,-13.2l-24.6,0l0,-217.3l24.6,0l0,217.3zm-52.9,-217.3l24.6,0l0,217.2l-24.6,0l0,-217.2z\"/>
  <path id=\"XMLID_3810_\" fill=\"#56aaff\" d=\"m345.9,172.3l0,243.7l64.2,0l0,-243.7l-64.2,0zm5.7,13.2l24.6,0l0,217.2l-24.6,0l0,-217.2zm52.9,217.3l-24.6,0l0,-217.3l24.6,0l0,217.3z\"/>
  <path id=\"XMLID_3814_\" fill=\"#56aaff\" d=\"m321.2,176.8l-24.9,0l0,-80.8l-81.2,0l0,80.8l-22.1,0l0,94l-24,0l0,144.7l173.6,0l0,-144.7l-21.2,0l-0.2,-94l0,0zm-27.8,3l24.4,0l0,26.2l-24.4,0l0,-26.2zm0,31.4l24.4,0l0,26.2l-24.4,0l0,-26.2zm0,31.4l24.4,0l0,26.2l-24.4,0l0,-26.2zm-39.7,-31.4l0,26.2l-24.4,0l0,-26.2l24.4,0zm-24.5,-5.2l0,-26.2l24.4,0l0,26.2l-24.4,0zm24.5,36.6l0,26.2l-24.4,0l0,-26.2l24.4,0zm-10.1,65.8l24.4,0l0,26.2l-24.4,0l0,-26.2l0,0zm0,-5.1l0,-26.2l24.4,0l0,26.2l-24.4,0zm17.7,-34.4l0,-26.2l24.4,0l0,26.2l-24.4,0zm24.5,-57.7l0,26.2l-24.4,0l0,-26.2l24.4,0zm-24.5,-5.2l0,-26.2l24.4,0l0,26.2l-24.4,0zm15.9,71.1l24.4,0l0,26.2l-24.4,0l0,-26.2zm-20.8,-173l32.4,0l0,32.1l-32.4,0l0,-32.1zm0,34.3l32.4,0l0,32.1l-32.4,0l0,-32.1zm-34.6,-34.3l32.4,0l0,32.1l-32.4,0l0,-32.1zm0,34.3l32.4,0l0,32.1l-32.4,0l0,-32.1zm-24.6,41.4l24.4,0l0,26.2l-24.4,0l0,-26.2zm0,31.4l24.4,0l0,26.2l-24.4,0l0,-26.2zm0,31.4l24.4,0l0,26.2l-24.4,0l0,-26.2zm-20.6,34.5l24.4,0l0,26.2l-24.4,0l0,-26.2zm0,31.3l24.4,0l0,26.2l-24.4,0l0,-26.2zm44.9,104l-2.6,0l0,-64.4l-39,0l0,64.4l-2.6,0l0,-66.9l44.1,0l0.1,66.9l0,0zm13,-77.7l-24.4,0l0,-26.2l24.4,0l0,26.2zm0,-31.4l-24.4,0l0,-26.2l24.4,0l0,26.2zm21.2,89.8l-16.3,0l0,-16.2l16.4,0l-0.1,16.2l0,0zm0,-17.4l-16.3,0l0,-16.2l16.4,0l-0.1,16.2l0,0zm17.5,17.4l-16.4,0l0,-16.2l16.4,0l0,16.2zm0,-17.4l-16.4,0l0,-16.2l16.4,0l0,16.2zm4,-67.3l24.4,0l0,26.2l-24.4,0l0,-26.2zm57.9,104l-2.6,0l0,-64.4l-39,0l0,64.4l-2.6,0l0,-66.9l44.1,0l0.1,66.9l0,0zm0,-77.7l-24.4,0l0,-26.2l24.4,0l0,26.2zm0,-57.6l0,26.2l-24.4,0l0,-26.2l24.4,0z\"/>
 </g>
</svg>");
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public function publicObjectPhotoThumbAction($uuid)
    {
        $this->view->disable();
        $token = $this->request->get('token');

        if ($token == '' || $uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        $result = ['success' => false, 'data' => '', 'uuid' => $uuid];
        $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);
//        $avatar = MediaAttachment::__getLogo($uuid);
//        if (!$avatar) $avatar = MediaAttachment::__getAvatarObject($uuid);

        if ($avatar) {
            //TODO use presigned URL instead of BodyCOntent

            $imgData = $avatar->getUrlThumb();
            if ($imgData == false) {
                $imgData = $avatar->getThumbCloudFrontUrl();
            }
            if ($imgData != false) {
//                $this->response->setHeader("Content-Type", $avatar->getMimeType());
                $this->response->setContentType($avatar->getMimeType());
                $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
                $this->response->setContent($imgData);
//                header('Location: ' . $imgData);
                return $this->response->send();
            }
        }
        $this->response->setContentType(Media::MIME_TYPE_SVG_XML);
        $this->response->setContent("<?xml version=\"1.0\" ?><svg id=\"Layer_1\" style=\"enable-background:new 0 0 200 200;\" version=\"1.1\" viewBox=\"0 0 200 200\" xml:space=\"preserve\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\"><style type=\"text/css\">
	.st0{fill:url(#SVGID_1_);}
	.st1{fill:url(#SVGID_2_);}
</style><g><linearGradient gradientUnits=\"userSpaceOnUse\" id=\"SVGID_1_\" x1=\"170.4592\" x2=\"31.2292\" y1=\"177.4471\" y2=\"38.217\"><stop offset=\"0\" style=\"stop-color:#007FE2\"/><stop offset=\"1\" style=\"stop-color:#39D3B8\"/></linearGradient><path class=\"st0\" d=\"M171.413,39.408H28.587c-1.981,0-3.587,1.606-3.587,3.587v63.993c0,1.981,1.606,3.587,3.587,3.587   s3.587-1.606,3.587-3.587V46.582h135.653v115.553l-44.592-45.951c-1.351-1.391-3.797-1.391-5.147,0l-18.455,19.019L67.19,101.77   c-1.351-1.391-3.797-1.391-5.147,0l-36.029,37.128c-0.055,0.057-0.094,0.123-0.144,0.182c-0.097,0.114-0.196,0.226-0.278,0.351   c-0.061,0.093-0.106,0.192-0.157,0.289c-0.058,0.109-0.12,0.214-0.167,0.329c-0.046,0.113-0.073,0.229-0.107,0.344   c-0.032,0.108-0.07,0.213-0.091,0.326c-0.027,0.143-0.034,0.288-0.044,0.433c-0.006,0.082-0.024,0.159-0.024,0.242v29.584   c0,1.981,1.606,3.587,3.587,3.587h112.699c1.981,0,3.587-1.606,3.587-3.587s-1.606-3.587-3.587-3.587H32.173V142.85l32.443-33.432   l39.207,40.402c0.703,0.724,1.638,1.089,2.574,1.089c0.899,0,1.802-0.337,2.498-1.013c1.421-1.379,1.456-3.65,0.076-5.072   l-4.341-4.473l16.031-16.52l48.179,49.647c0.689,0.71,1.622,1.089,2.575,1.089c0.453,0,0.909-0.086,1.346-0.263   c1.353-0.549,2.239-1.863,2.239-3.324V42.995C175,41.014,173.394,39.408,171.413,39.408z\"/><linearGradient gradientUnits=\"userSpaceOnUse\" id=\"SVGID_2_\" x1=\"201.5168\" x2=\"62.2868\" y1=\"146.3895\" y2=\"7.1595\"><stop offset=\"0\" style=\"stop-color:#007FE2\"/><stop offset=\"1\" style=\"stop-color:#39D3B8\"/></linearGradient><path class=\"st1\" d=\"M135.311,96.919c9.227,0,16.734-7.507,16.734-16.735s-7.507-16.734-16.734-16.734s-16.734,7.507-16.734,16.734   S126.084,96.919,135.311,96.919z M135.311,70.623c5.272,0,9.561,4.289,9.561,9.561c0,5.272-4.289,9.561-9.561,9.561   s-9.561-4.289-9.561-9.561C125.75,74.912,130.039,70.623,135.311,70.623z\"/></g></svg>");
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     * @throws \Exception
     */
    public function publicPhotoThumbAction($uuid)
    {
        $this->view->disable();
        $token = $this->request->get('token');

        if ($token == '' || $uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        $result = ['success' => false, 'data' => '', 'uuid' => $uuid];
        $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);
//        $avatar = MediaAttachment::__getLogo($uuid);
//        if (!$avatar) $avatar = MediaAttachment::__getAvatarObject($uuid);

        if ($avatar) {
            //TODO use presigned URL instead of BodyCOntent
            $imgData = $avatar->getThumbContent();
            if ($imgData != false) {
                $this->response->setHeader("Content-Type", $avatar->getMimeType());
                $this->response->setContent($imgData);
                return $this->response->send();
            }
        }

        $this->response->setContentType(Media::MIME_TYPE_SVG_XML);
        $this->response->setContent("<?xml version=\"1.0\" ?><svg id=\"Layer_1\" style=\"enable-background:new 0 0 200 200;\" version=\"1.1\" viewBox=\"0 0 200 200\" xml:space=\"preserve\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\"><style type=\"text/css\">
	.st0{fill:url(#SVGID_1_);}
	.st1{fill:url(#SVGID_2_);}
</style><g><linearGradient gradientUnits=\"userSpaceOnUse\" id=\"SVGID_1_\" x1=\"170.4592\" x2=\"31.2292\" y1=\"177.4471\" y2=\"38.217\"><stop offset=\"0\" style=\"stop-color:#007FE2\"/><stop offset=\"1\" style=\"stop-color:#39D3B8\"/></linearGradient><path class=\"st0\" d=\"M171.413,39.408H28.587c-1.981,0-3.587,1.606-3.587,3.587v63.993c0,1.981,1.606,3.587,3.587,3.587   s3.587-1.606,3.587-3.587V46.582h135.653v115.553l-44.592-45.951c-1.351-1.391-3.797-1.391-5.147,0l-18.455,19.019L67.19,101.77   c-1.351-1.391-3.797-1.391-5.147,0l-36.029,37.128c-0.055,0.057-0.094,0.123-0.144,0.182c-0.097,0.114-0.196,0.226-0.278,0.351   c-0.061,0.093-0.106,0.192-0.157,0.289c-0.058,0.109-0.12,0.214-0.167,0.329c-0.046,0.113-0.073,0.229-0.107,0.344   c-0.032,0.108-0.07,0.213-0.091,0.326c-0.027,0.143-0.034,0.288-0.044,0.433c-0.006,0.082-0.024,0.159-0.024,0.242v29.584   c0,1.981,1.606,3.587,3.587,3.587h112.699c1.981,0,3.587-1.606,3.587-3.587s-1.606-3.587-3.587-3.587H32.173V142.85l32.443-33.432   l39.207,40.402c0.703,0.724,1.638,1.089,2.574,1.089c0.899,0,1.802-0.337,2.498-1.013c1.421-1.379,1.456-3.65,0.076-5.072   l-4.341-4.473l16.031-16.52l48.179,49.647c0.689,0.71,1.622,1.089,2.575,1.089c0.453,0,0.909-0.086,1.346-0.263   c1.353-0.549,2.239-1.863,2.239-3.324V42.995C175,41.014,173.394,39.408,171.413,39.408z\"/><linearGradient gradientUnits=\"userSpaceOnUse\" id=\"SVGID_2_\" x1=\"201.5168\" x2=\"62.2868\" y1=\"146.3895\" y2=\"7.1595\"><stop offset=\"0\" style=\"stop-color:#007FE2\"/><stop offset=\"1\" style=\"stop-color:#39D3B8\"/></linearGradient><path class=\"st1\" d=\"M135.311,96.919c9.227,0,16.734-7.507,16.734-16.735s-7.507-16.734-16.734-16.734s-16.734,7.507-16.734,16.734   S126.084,96.919,135.311,96.919z M135.311,70.623c5.272,0,9.561,4.289,9.561,9.561c0,5.272-4.289,9.561-9.561,9.561   s-9.561-4.289-9.561-9.561C125.75,74.912,130.039,70.623,135.311,70.623z\"/></g></svg>");
        return $this->response->send();
    }
}
