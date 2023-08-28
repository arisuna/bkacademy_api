<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\SMXDS3Helper;
use SMXD\Application\Traits\ModelTraits;

class ObjectAvatarExt extends ObjectAvatar
{

    use ModelTraits;

    /** status archived */
    const STATUS_ARCHIVED = -1;
    /** status active */
    const STATUS_ACTIVE = 1;
    /** status draft */
    const STATUS_DRAFT = 0;

    public $url_thumb;

    const FILE_TYPE_IMAGE = 2;
    const FILE_TYPE_OTHER = 6;

    const BUCKET_PREFIX = 'reloday';
    const FILE_TYPE_IMAGE_NAME = 'image';

    const MIME_TYPE_PNG = 'image/png';
    const MIME_TYPE_JPG = 'image/jpg';
    const MIME_TYPE_JPEG = 'image/jpeg';
    const MIME_TYPE_GIF = 'image/gif';
    const MIME_TYPE_SVG = 'image/svg';
    const MIME_TYPE_BMP = 'image/bmp';
    const MIME_TYPE_SVG_XML = 'image/svg+xml';

    static $images_extensions = [
        self::MIME_TYPE_PNG => [
            'extension' => 'png',
            'type' => self::FILE_TYPE_IMAGE
        ],
        self::MIME_TYPE_JPG => [
            'extension' => 'jpg',
            'type' => self::FILE_TYPE_IMAGE
        ],
        self::MIME_TYPE_JPEG => [
            'extension' => 'jpg',
            'type' => self::FILE_TYPE_IMAGE
        ],
        self::MIME_TYPE_GIF => [
            'extension' => 'gif',
            'type' => self::FILE_TYPE_IMAGE
        ],
        self::MIME_TYPE_SVG => [
            'extension' => 'svg',
            'type' => self::FILE_TYPE_IMAGE
        ],
        self::MIME_TYPE_BMP => [
            'extension' => 'bmp',
            'type' => self::FILE_TYPE_IMAGE
        ]
    ];

    static $ext_types = [

        /** image */
        'jpg' => ['type' => self::FILE_TYPE_IMAGE, 'type_name' => self::FILE_TYPE_IMAGE_NAME],
        'gif' => ['type' => self::FILE_TYPE_IMAGE, 'type_name' => self::FILE_TYPE_IMAGE_NAME],
        'jpeg' => ['type' => self::FILE_TYPE_IMAGE, 'type_name' => self::FILE_TYPE_IMAGE_NAME],
        'png' => ['type' => self::FILE_TYPE_IMAGE, 'type_name' => self::FILE_TYPE_IMAGE_NAME],
        'tif' => ['type' => self::FILE_TYPE_IMAGE, 'type_name' => self::FILE_TYPE_IMAGE_NAME],
        'tiff' => ['type' => self::FILE_TYPE_IMAGE, 'type_name' => self::FILE_TYPE_IMAGE_NAME],
        'bmp' => ['type' => self::FILE_TYPE_IMAGE, 'type_name' => self::FILE_TYPE_IMAGE_NAME],
    ];

    static $ext_groups = array(
        self::FILE_TYPE_IMAGE =>
            array(
                0 => 'jpg',
                1 => 'gif',
                2 => 'jpeg',
                3 => 'png',
                4 => 'tif',
                5 => 'tiff',
                6 => 'bmp',
            ),
    );

    static $mimeTypes = array(
        'png' =>
            array(
                0 => 'image/png',
                1 => 'image/x-png',
            ),
        'bmp' =>
            array(
                0 => 'image/bmp',
                1 => 'image/x-bmp',
                2 => 'image/x-bitmap',
                3 => 'image/x-xbitmap',
                4 => 'image/x-win-bitmap',
                5 => 'image/x-windows-bmp',
                6 => 'image/ms-bmp',
                7 => 'image/x-ms-bmp',
                8 => 'application/bmp',
                9 => 'application/x-bmp',
                10 => 'application/x-win-bitmap',
            ),
        'gif' =>
            array(
                0 => 'image/gif',
            ),
        'jpeg' =>
            array(
                0 => 'image/jpeg',
                1 => 'image/pjpeg',
            ),
        'tiff' =>
            array(
                0 => 'image/tiff',
            ),
    );

    const IMAGE_MAX_WIDTH = 800;
    const IMAGE_MAX_HEIGHT = 640;


    const PATH_PREFIX = 'object_image';
    const USER_AVATAR = 'avatar';
    const USER_LOGO = 'logo';

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable(
            array(
                'beforeValidationOnCreate' => array(
                    'field' => array(
                        'created_at', 'updated_at'
                    ),
                    'format' => 'Y-m-d H:i:s'
                ),
                'beforeValidationOnUpdate' => array(
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                )
            )
        ));
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {

        ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }

    /**
     * @param $extension
     * @return mixed
     * Get File_type
     */
    static function __getFileType($extension)
    {
        foreach (self::$ext_types as $key => $config) {
            if ($key == $extension) {
                return $config['type_name'];
            }
        }
        return 'other';
    }

    /**
     * @return mixed|string
     */
    public function getFileType()
    {
        return self::__getFileType(strtolower($this->getFileExtension()));
    }

    /**
     * get real file path in AMAZON S3
     * @return string
     */
    public function getRealFilePath()
    {
        if ($this->getFilePath() == null || $this->getFilePath() == '')
            if ($this->getCompanyUuid() > 0) {
                return (self::PATH_PREFIX . '/' . $this->getCompanyUuid() . "/" . $this->getRealFileName());
            } else {
                return $this->getMediaType()->getAmazonPath() . "/" . $this->getRealFileName();
            }
        else
            return $this->getFilePath();
    }

    /**
     * set Media Type
     */
    public function loadMediaType()
    {
        return $this->setMediaTypeId(
            isset(self::$ext_types[$this->getFileExtension()]) ? self::$ext_types[$this->getFileExtension()]['type'] : self::FILE_TYPE_OTHER
        );
    }

    /**
     * @return string
     */
    public function getThumbCloudFrontUrl()
    {
        $bucketName = self::getAppConfig()->aws->bucket_thumb_name;
        $di = \Phalcon\DI::getDefault();
        $s3client = $di->get('aws')->createS3();
        $file = "thumb/" . $this->getUuid() . "." . $this->getFileExtension();
        if ($s3client->doesObjectExist($bucketName, $file) == true) {
            return "https://cloud-static.relotalent.com/thumb/" . $this->getUuid() . "." . $this->getFileExtension();
        } else {
            return "https://cloud-static.relotalent.com/thumb/" . $this->getObjectUuid() . "." . $this->getFileExtension();
        }

    }

    static function getAppConfig()
    {
        $config = \Phalcon\DI\FactoryDefault::getDefault()->getShared('appConfig');
        return $config;
    }

    /**
     * @return array
     */
    public function getTemporaryThumbS3Url()
    {
        $filePath = "thumb/" . $this->getUuid() . "." . $this->getFileExtension();
        $bucketName = self::getAppConfig()->aws->bucket_thumb_name;
        $fileName = $this->getUuid() . "." . $this->getFileExtension();
        $di = \Phalcon\DI::getDefault();
        $s3client = $di->get('aws')->createS3();
        if ($s3client->doesObjectExist($bucketName, $filePath) == true) {
            return SMXDS3Helper::__getPresignedUrl($filePath, $bucketName, $fileName, $this->getMimeType(), false);
        } else {
            $filePath = "thumb/" . $this->getObjectUuid() . "." . $this->getFileExtension();
            $fileName = $this->getObjectUuid() . "." . $this->getFileExtension();
            return SMXDS3Helper::__getPresignedUrl($filePath, $bucketName, $fileName, $this->getMimeType(), false);
        }

    }

    /**
     * @return array
     */
    public function getPresignedS3Url()
    {
        $di = \Phalcon\DI::getDefault();
        $bucketName = $di->get('appConfig')->aws->bucket_name;
        $fileName = trim(Helpers::__generateSlug($this->getName())) . "." . $this->getFileExtension();

        if (!$this->getFilePath()) {
            return null;
        }

        //var_dump($fileName); die();

        return SMXDS3Helper::__getPresignedUrl($this->getFilePath(), $bucketName, $fileName, $this->getMimeType());
    }


    /**
     * @return array
     */
    public function __toArray()
    {
        $item = $this->toArray();
        return $item;
    }

    /**
     * [getUrlThumb description]
     * @return [type] [description]
     */
    public function getUrlThumb()
    {
        return $this->getPresignedS3Url();
    }

    /**
     * @return mixed
     */
    public function getParsedData()
    {
        $item = [];
        $item['uuid'] = $this->getUuid();
        $item['name'] = $this->getName();
        $item['file_type'] = $this->getFileType();
        $item['file_extension'] = $this->getFileExtension();
////
        $item['company_uuid'] = $this->getCompanyUuid();
        $item['user_uuid'] = $this->getUserUuid();

        $item['file_size'] = intval($this->getSize());
        $item['file_size_human_format'] = $this->getSizeHumainFormat();
        $item['s3_full_path'] = $this->getRealFilePath();
        // Convert second to millisecond | created_at and updated_at
        $item['created_at'] = $this->getCreatedAt() * 1000;
        $item['updated_at'] = !is_null($this->getUpdatedAt()) ? ((int)$this->getUpdatedAt() * 1000) : null;
        $item['image_data'] = [
            "url_thumb" => $this->getUrlThumb(),
            "name" => $this->getFilename()
        ];

        return $item;
    }

    /**
     * [get media list attached to a data with uuid]
     * @param string $uuid [description]
     * @return [type]       [description]
     */
    public static function __getLastestImage($objectUuid = '', $objectName = '', $returnType = "array")
    {
        if ($objectName == '') {
            $user_avatar = (new static)::findFirst([
                'conditions' => 'object_uuid = :object_uuid:',
                'bind' => [
                    'object_uuid' => $objectUuid
                ]
            ]);
        } else {
            $user_avatar = (new static)::findFirst([
                'conditions' => 'object_uuid = :object_uuid: AND object_name = :object_name:',
                'bind' => [
                    'object_uuid' => $objectUuid,
                    'object_name' => $objectName,
                ]
            ]);
        }

        if ($returnType == "array") {
            $image = [];
            if ($user_avatar) {
                $image['image_data'] = [];
                $image['image_data']['url_thumb'] = $user_avatar->getUrlThumb();
                $image['uuid'] = $user_avatar->getUuid();
                $image['object_uuid'] = $user_avatar->getObjectUuid();
            }
            return $image;
        } else {
            if ($user_avatar) {
                return $user_avatar;
            } else {
                return [];
            }
        }
    }

    public static function __getPublicAvatarUrl()
    {
        $di = \Phalcon\DI::getDefault();
        $bucketAvatarUrl = $di->get('appConfig')->aws->bucket_avatar_url;
        return $bucketAvatarUrl . '/' . self::getFilePath();
    }

    public function getCompany()
    {
        return CompanyExt::findFirstByUuid($this->getCompanyUuid());
    }

    /**
     * @return string
     */
    public function getRealFileName()
    {
        return $this->getObjectUuid() . "." . $this->getFileExtension();
    }

    /**
     *
     */
    public function addDefaultFilePath()
    {
        if ($this->getFilePath() == '') {
            if ($this->getCompany()) {
                $path = self::PATH_PREFIX . "/" . $this->getCompany()->getUuid() . "/" . $this->getRealFileName();
                $this->setFilePath($path);
            }else{
                $path = self::PATH_PREFIX . "/" . $this->getObjectUuid() . "/" . $this->getRealFileName();
                $this->setFilePath($path);
            }
        }
    }

    /**
     * @param $temporaryFilePath
     * @return array
     */
    public function uploadToS3FromPath($temporaryFilePath)
    {
        $this->addDefaultFilePath();
        $fileName = $this->getFilePath();
        return SMXDS3Helper::__uploadSingleFileWithFilePath($fileName, $temporaryFilePath);
    }

    /**
     * @return string
     */
    public function getSizeHumainFormat()
    {
        return Helpers::__formatBytes($this->getSize());
    }

    /****** THUMB *****/

    /**
     * get thumb directory from S3
     * @return string
     */
    public function getThumbDirectoryS3()
    {
        return 'thumb';
    }

    /**
     * get thumb directory from S3
     * @return string
     */
    public static function __getThumbDirectoryS3()
    {
        return 'thumb';
    }


    /**
     * get Thumb File Path
     */
    public function getThumbFilePath()
    {
        $config = self::getAppConfig();
        $upload_path = $config->base_dir->data . 'upload/';
        $month = date('m', strtotime($this->getCreatedAt()));
        $year = date('Y', strtotime($this->getCreatedAt()));
        $file_path = $upload_path . $year . '/' . $month . '/' . $this->getFilename();
        $thumb_file_path = $config->base_dir->data . 'upload/thumbnail/' . $this->getFilename();
        return $thumb_file_path;
    }

    /**
     * @return bool|\Psr\Http\Message\StreamInterface
     */
    public function getThumbFromS3()
    {
        $bucketName = self::getAppConfig()->aws->bucket_thumb_name;
        $file = $this->getFilePath();
        $file = self::__getThumbDirectoryS3() . "/" . $this->getUuid() . '.' . $this->getFileExtension();
        try {
            $di = \Phalcon\DI::getDefault();
            $s3client = $di->get('aws')->createS3();
            if ($s3client->doesObjectExist($bucketName, $file) == false) {
                $file = self::__getThumbDirectoryS3() . "/" . $this->getFileName();
                if ($s3client->doesObjectExist($bucketName, $file) == false) {
                    return false;
                }
            }

            $cmd = $s3client->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key' => $file,
                'ResponseContentType' => $this->getMimeType(),
                'ResponseContentLanguage' => 'en-US',
                'ResponseContentDisposition' => 'attachment; filename=' . $this->getFilename(),
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

        } catch (ClientException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * get Thumb file content
     * @return bool|\Psr\Http\Message\StreamInterface|string
     */
    public function getThumbContent()
    {
        $thumb_file_path = $this->getThumbFilePath();
        if ($this->getFileType() == self::FILE_TYPE_IMAGE_NAME) {
            if (file_exists($thumb_file_path)) {
                return $this->getThumbFromDirectory();
            } else {
                $dataBody = $this->getThumbFromS3();
                if ($dataBody !== false) {
                    return $dataBody;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }


    /**
     * @param $this
     */
    public function getThumbFromDirectory()
    {
        $thumb_file_path = $this->getThumbFilePath();
        $file_path = $this->getFilePathFromDirectory();

        if (file_exists($thumb_file_path)) {
            return file_get_contents($thumb_file_path);
        } elseif (file_exists($file_path)) {
            $resultCreate = $this->createThumbInDirectory();
            if ($resultCreate == true) {
                return file_get_contents($thumb_file_path);
            } else {
                if (file_exists($this->getEmptyThumbFilePath())) {
                    return file_get_contents($this->getEmptyThumbFilePath());
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * get Empty thumb file path
     * @return string
     */
    public function getEmptyThumbFilePath()
    {
        $config = self::getAppConfig();
        return $config->base_dir->public . '/resources/img/files/undefined.png';
    }

    /**
     *
     */
    public function createThumbInDirectory()
    {
        $thumb_file_path = $this->getThumbFilePath();
        $file_path = $this->getFilePathFromDirectory();
        $image = new \Phalcon\Image\Adapter\Imagick($file_path);
        $image->resize(null, 150, \Phalcon\Image::HEIGHT);
        try {
            if ($image->save($thumb_file_path)) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getFilePathFromDirectory()
    {
        $config = $this->getDiAppConfig();
        $upload_path = $config->base_dir->data . 'upload/';
        $month = date('m', strtotime($this->getCreatedAt()));
        $year = date('Y', strtotime($this->getCreatedAt()));
        $file_path = $upload_path . $year . '/' . $month . '/' . $this->getFilename();
        return $file_path;
    }

    /**
     * @return mixed
     */
    public function getDiAppConfig()
    {
        try {
            $config = \Phalcon\DI\FactoryDefault::getDefault()->getShared('appConfig');
            return $config;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return mixed
     */
    public function getMediaType()
    {
        if ($this->getMediaTypeId() > 0)
            return MediaTypeExt::__findFirstByIdWithCache($this->getMediaTypeId());
        else
            return false;
    }

    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getImageByObjectUuid($uuid, $returnType = 'object')
    {
        $image = self::__getLastestImage($uuid, '', $returnType);
        if ($image) {
            return $image;
        } else {
            return null;
        }
    }

    /**
     * @param $temporaryFilePath
     * @return array
     */
    public function uploadToS3FromBinary(String $temporyFileContent)
    {
        $this->addDefaultFilePath();
        $fileName = $this->getFilePath();

        return SMXDS3Helper::__uploadSingleFileWithPresignedUrl($fileName, $temporyFileContent, $this->getBucketName(), SMXDS3Helper::ACL_AUTHENTICATED_READ, $this->getMimeType());
    }

    /**
     * @param String $temporyFileContent
     * @return array
     */
    public function uploadToThumbFromBinary(String $temporyFileContent)
    {
        $fileName = $this->getThumbFilePath();
        return SMXDS3Helper::__uploadSingleFileWithPresignedUrl($fileName, $temporyFileContent, $this->getThumbBucketName(), SMXDS3Helper::ACL_PUBLIC_READ, $this->getMimeType());
    }

    /**
     * @return mixed|null
     */
    public function getBucketName()
    {
        return getenv('AMAZON_BUCKET_NAME');
    }

    /**
     * @return mixed|null
     */
    public function getThumbBucketName()
    {
        return getenv('AMAZON_BUCKET_PUBLIC_NAME');
    }
}
