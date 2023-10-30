<?php

namespace SMXD\Media\Controllers\API;

use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\SMXDLetterImage;
use SMXD\Application\Lib\SMXDS3Helper;
use SMXD\Application\Models\MediaType;
use \SMXD\Media\Controllers\ModuleApiController;
use \SMXD\Media\Models\Media as MediaFile;
use \Aws\Credentials\CredentialProvider;
use \Guzzle\Http\Client;


use Phalcon\Acl;
use Phalcon\Http\Request;
use SMXD\Media\Models\Media;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\UserGroup;
use SMXD\Media\Models\User;

/**
 * Concrete implementation of Media module controller
 *
 * @RoutePrefix("/media/api")
 */
class FileController extends ModuleApiController
{
    public $ext_type = [];
    public $current_user;

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        $this->view->disable();
        $this->ext_type = MediaFile::$ext_types;
        $this->current_user = ModuleModel::$user;
    }


    /**
     * load file from token and uuid
     * need to load file with protection from amazon pdf
     * @return [type]
     */
    public function loadAction()
    {
        $this->view->disable();
        $this->checkToken();
        $uuid = Helpers::__getRequestValue('uuid');
        $media = MediaFile::findFirstByUuid($uuid);

        if (!$media) {
            $return = ['success' => false, 'message' => 'Media Not Found'];
            $this->response->setJsonContent($return);
            $this->response->send();
        }
        if ($media->getIsHosted() == MediaFile::STATUS_HOSTED) {
            $presignedUrl = $media->getTemporaryFileUrlS3();
            if ($presignedUrl) {
                try {
                    $this->response->setContentType($media->getMimeType());
                    $fileName = $media->getNameStatic() != '' ? $media->getNameStatic() : $media->getName();
                    $fileName = urlencode($fileName);

                    $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $fileName . "." . $media->getFileExtension() . '"');
                    header('Location: ' . $presignedUrl);
                } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                    return $this->dispatcher->forward([
                        'controller' => 'index',
                        'action' => 'notFound'
                    ]);
                }
            } else {
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'notFound'
                ]);
            }


        } else {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'notFound'
            ]);
        }
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface|void
     * @throws \Exception
     */
    public function thumbnailAction()
    {
        $this->view->disable();
        $token = Helpers::__getRequestValue('token');
        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $name = Helpers::__getRequestValue('name');
        $token = base64_decode($token);
        try {
            if ($token == '' || $uuid == '' || $type == '' || $name == '') {
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'thumbNotFound'
                ]);
            }
            $auth = ModuleModel::__checkAuthenByAccessToken($token, $this->config);

            if ($auth['success'] == false && $auth['isExpired'] == false) {
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'thumbExpired'
                ]);
            }

            $media = MediaFile::findFirstByUuid($uuid);
            if (!$media) {
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'thumbNotFound'
                ]);
            }

            $file_type = $media->getFileType();
            if ($file_type == 'image') {
                if ($media->getIsHosted() == MediaFile::STATUS_HOSTED) {
                    $createAtInSecond = Helpers::__convertDateToSecond($media->getCreatedAt());
                    $url = $media->getTemporaryThumbS3Url();

                    $this->response->setContentType($media->getMimeType());
                    $this->response->setHeader("Content-Disposition", 'inline; filename="' . $media->getName() . "." . $media->getFileExtension() . '"');
                    header('Location: ' . $url);
                } else {
                    return $this->dispatcher->forward([
                        'controller' => 'index',
                        'action' => 'thumbNotFound'
                    ]);
                }
            } else {

                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'thumbNotFound'
                ]);
            }
        } catch (Phalcon\Exception $e) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'thumbNotFound'
            ]);
        }
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
                $array_to_insert[] = ['name' => $type->name, 'id' => $type->id, 'extensions' => $type->getDataExtensions()];
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
     * get file from S3
     */
    public function getFileFromS3($media)
    {
        if ($media->getIsHosted() == MediaFile::STATUS_HOSTED) {
            $bucketName = $this->config->application->amazon_bucket_name;
            $file = $media->mediatype->getAmazonPath() . "/" . $media->getFilename();
            $s3client = $this->getDi()->get('aws')->createS3();
            $cmd = $s3client->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key' => $file,
                'ResponseContentType' => $media->getMimeType(),
                'ResponseContentLanguage' => 'en-US',
                'ResponseContentDisposition' => 'attachment; filename=' . $media->getFilename(),
                'ResponseCacheControl' => 'No-cache',
                'ResponseExpires' => gmdate(DATE_RFC2822, time() + 3600),
            ]);
            $request = $s3client->createPresignedRequest($cmd, '+20 minutes');
            // Get the actual presigned-url
            $presignedUrl = (string)$request->getUri();
            // Create a vanilla Guzzle HTTP client for accessing the URLs
            $http = new \GuzzleHttp\Client;
            // > 403
            try {
                // Get the contents of the object using the pre-signed URL
                $amazonResponse = $http->get($presignedUrl);
                return $amazonResponse->getBody();
            } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                $amazonResponse = $e->getResponse();
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @return bool|\Psr\Http\Message\StreamInterface
     */
    public function getThumbFromS3($media)
    {
        if ($media->getIsHosted() == MediaFile::STATUS_HOSTED) {

            $bucketName = $this->getDi()->get('appConfig')->aws->bucket_thumb_name;
            $file = $media->getThumbDirectoryS3() . "/" . $media->getFilename();
            try {
                $s3client = $this->getDi()->get('aws')->createS3();
                if ($s3client->doesObjectExist($bucketName, $file) == true) {
                    $cmd = $s3client->getCommand('GetObject', [
                        'Bucket' => $bucketName,
                        'Key' => $file,
                        'ResponseContentType' => $media->getMimeType(),
                        'ResponseContentLanguage' => 'en-US',
                        'ResponseContentDisposition' => 'attachment; filename=' . $media->getFilename(),
                        'ResponseCacheControl' => 'No-cache',
                        'ResponseExpires' => gmdate(DATE_RFC2822, time() + 3600),
                    ]);
                    $request = $s3client->createPresignedRequest($cmd, '+20 minutes');
                    // Get the actual presigned-url
                    $presignedUrl = (string)$request->getUri();
                    // Create a vanilla Guzzle HTTP client for accessing the URLs
                    $http = new \GuzzleHttp\Client;
                    // > 403

                    // Get the contents of the object using the pre-signed URL
                    $amazonResponse = $http->get($presignedUrl);
                    return $amazonResponse->getBody();
                } else {
                    return false;
                }
            } catch (\Guzzle\Http\Exception\ClientException $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param $media
     */
    public function getThumb($media)
    {
        $upload_path = $this->config->base_dir->data . 'upload/';
        $file_type = $media->getFileType();
        $month = date('m', strtotime($media->getCreatedAt()));
        $year = date('Y', strtotime($media->getCreatedAt()));
        $file_path = $upload_path . $year . '/' . $month . '/' . $media->getFilename();
        $thumb_file_path = $this->config->base_dir->data . 'upload/thumbnail/' . $media->getFilename();

        if (file_exists($thumb_file_path)) {
            $this->response->setHeader("Content-Type", $media->getMimeType());
            return $this->response->setContent(file_get_contents($thumb_file_path));
        } elseif (file_exists($file_path)) {
            $image = new \Phalcon\Image\Adapter\Imagick($file_path);
            $image->resize(null, 150, \Phalcon\Image::HEIGHT);
            if ($image->save($thumb_file_path)) {
                $this->response->setHeader("Content-Type", $media->getMimeType());
                return $this->response->setContent(file_get_contents($thumb_file_path));
            } else {
                $thumb_file_path = $this->config->base_dir->public . '/resources/img/files/undefined.png';
                $this->response->setHeader("Content-Type", "image/png");
                return $this->response->setContent(file_get_contents($thumb_file_path));
            }
        } else {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'notFound'
            ]);
        }
    }

    /**
     * load file from token and uuid
     * need to load file with protection from amazon pdf
     * @return [type]
     */
    public function fullAction()
    {
        $this->view->disable();
        $this->checkToken();
        $uuid = Helpers::__getRequestValue('uuid');
        $media = MediaFile::findFirstByUuid($uuid);

        if (!$media) {
            $return = ['success' => false, 'message' => 'Media Not Found'];
            $this->response->setJsonContent($return);
            $this->response->send();
        }
        if ($media->getIsHosted() == MediaFile::STATUS_HOSTED) {
            $presignedUrl = $media->getTemporaryFileUrlS3();
            if ($presignedUrl) {
                try {
                    $this->response->setContentType($media->getMimeType());
                    $fileName = $media->getNameStatic() != '' ? $media->getNameStatic() : $media->getName();
                    $fileName = urlencode($fileName);
                    $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $fileName . "." . $media->getFileExtension() . '"');
                    header('Location: ' . $presignedUrl);
                } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                    return $this->dispatcher->forward([
                        'controller' => 'index',
                        'action' => 'notFound'
                    ]);
                }
            } else {
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'notFound'
                ]);
            }


        } else {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'notFound'
            ]);
        }
    }

    /**
     * @throws \Exception
     */
    public function convertPdfAction()
    {
        $this->view->disable();
        $uuid = Helpers::__getRequestValue('uuid');
        $this->checkToken();

        if (ModuleModel::$user && ModuleModel::$user->isSystemUser()) {
//            AclHelper::__setUser(ModuleModel::$user);
//            $canAccessRessource = AclHelper::__canAccessResource(AclHelper::CONTROLLER_MEDIA, AclHelper::ACTION_DOWNLOAD);
//            if ($canAccessRessource['success'] == false) {
//                return $this->dispatcher->forward([
//                    'controller' => 'index',
//                    'action' => 'permissionNotFound'
//                ]);
//            }
        }

        $media = MediaFile::findFirstByUuid($uuid);

        if (!$media) {
            $return = ['success' => false, 'message' => 'Media Not Found'];
            $this->response->setJsonContent($return);
            $this->response->send();
        }

        if ($media->getIsHosted() == MediaFile::STATUS_HOSTED) {
            $bucketName = $this->getDi()->get('appConfig')->aws->bucket_name;
            if ($media->isExistedInS3() == true) {
                $bodyContent = $media->getRawDataContentFromS3();

                $objReader = \PhpOffice\PhpWord\IOFactory::createReader();
                $objReader->load();

            } else {
                //media not exist in S3
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'notFoundWithMessage'
                ]);
                exit();
            }
        } else {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'notFoundWithMessage'
            ]);
            exit();
        }
    }

    /**
     * load file from token and uuid
     * need to load file with protection from amazon pdf
     * @return [type]
     */
    public function downloadAction()
    {
        $this->view->disable();
        $token = Helpers::__getRequestValue('token');
        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $name = Helpers::__getRequestValue('name');
        $accessToken = base64_decode($token);
        if ($token == '' || $uuid == '' || $type == '' || $name == '') {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
        }

        $auth = ModuleModel::__checkAuthenByAccessToken($accessToken);

        if ($auth['success'] == false) {
            $auth['token64'] = $token;
            $this->response->setJsonContent($auth);
            return $this->response->send();
        }

//        if (ModuleModel::$user && ModuleModel::$user->isSystemUser()) {
//            AclHelper::__setUser(ModuleModel::$user);
//
//            $canAccessRessource = AclHelper::__canAccessResource(AclHelper::CONTROLLER_MEDIA, AclHelper::ACTION_DOWNLOAD);
//
//            if ($canAccessRessource['success'] == false) {
//                return $this->dispatcher->forward([
//                    'controller' => 'index',
//                    'action' => 'permissionNotFound'
//                ]);
//            }
//        }

        $media = MediaFile::findFirstByUuid($uuid);

        if (!$media) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'notFoundWithMessage'
            ]);
            exit();
        }

        if ($media->getIsHosted() == MediaFile::STATUS_HOSTED) {
            $bucketName = $this->getDi()->get('appConfig')->aws->bucket_name;
            if ($media->isExistedInS3() == true) {
                $presignedUrl = $media->getTemporaryFileUrlS3();
                if ($presignedUrl) {
                    $http = new \GuzzleHttp\Client;
                    try {
                        $this->response->setContentType($media->getMimeType());
                        $fileName = $media->getNameStatic() != '' ? $media->getNameStatic() : $media->getName();
                        //$fileName = urlencode($fileName);
                        $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $fileName . "." . $media->getFileExtension() . '"');
                        header('Location: ' . $presignedUrl);
                    } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                        return $this->dispatcher->forward([
                            'controller' => 'index',
                            'action' => 'notFoundWithMessage'
                        ]);
                        exit();
                    }
                } else {
                    return $this->dispatcher->forward([
                        'controller' => 'index',
                        'action' => 'notFoundWithMessage'
                    ]);
                    exit();
                }
            } else {
                //media not exist in S3
                return $this->dispatcher->forward([
                    'controller' => 'index',
                    'action' => 'notFoundWithMessage'
                ]);
                exit();
            }
        } else {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'notFoundWithMessage'
            ]);
            exit();
        }
    }

    /**
     * @throws \Exception
     */
    public function checkToken()
    {
        $token = Helpers::__getRequestValue('token');
        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $name = Helpers::__getRequestValue('name');
        $token = base64_decode($token);

        if ($token == '' || $uuid == '' || $type == '' || $name == '') {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'block'
            ]);
            exit();
        }

        $auth = ModuleModel::__checkAuthenByAccessToken($token, $this->config);
        if (!$auth['success']) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'expire'
            ]);
            exit();
        }
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function testTokenAction()
    {
        $token = Helpers::__getRequestValue('token');
        $auth = ModuleModel::__checkAuthenByAccessToken($token);
        $this->response->setJsonContent($auth);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function testToken64Action($token64)
    {
        $token = base64_decode($token64);
        $auth = ModuleModel::__checkAuthenByAccessToken($token);
        $this->response->setJsonContent($auth);
        return $this->response->send();
    }
}
