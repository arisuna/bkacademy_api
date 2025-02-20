<?php

namespace SMXD\Application\Lib;

use SMXD\Application\Lib\SMXDDynamoORM;
use SMXD\Application\Lib\SMXDDynamoORMException;
use Aws\Exception\AwsException;
use Phalcon\Di;
use Phalcon\Crypt;
use Phalcon\Http\Client\Exception;
use SMXD\Application\Models\ApplicationModel;

class SMXDMediaHelper
{

    // ACL flags
    const OBJECT_TYPE_DOCUMENT = 0;
    const OBJECT_TYPE_LOGO = 1;
    const OBJECT_TYPE_AVATAR = 0;

    const FILE_TYPE_DOCUMENT = 1;
    const FILE_TYPE_IMAGE = 2;
    const FILE_TYPE_COMPRESSED = 3;
    const FILE_TYPE_VIDEO = 5;
    const FILE_TYPE_AUDIO = 4;


    const FILE_TYPE_DOCUMENT_NAME = 'document';
    const FILE_TYPE_IMAGE_NAME = 'image';
    const FILE_TYPE_VIDEO_NAME = 'video';
    const FILE_TYPE_AUDIO_NAME = 'audio';
    const FILE_TYPE_COMPRESSED_NAME = 'compressed';

    const CRYPT_KEY_MEDIA = 'reloday-singapore-2017';

    const BUCKET_PREFIX = 'reloday';

    const MIME_TYPE_PNG = 'image/png';
    const MIME_TYPE_JPG = 'image/jpg';
    const MIME_TYPE_JPEG = 'image/jpeg';
    const MIME_TYPE_GIF = 'image/gif';
    const MIME_TYPE_SVG = 'image/svg';
    const MIME_TYPE_BMP = 'image/bmp';

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

        /** audio */
        'mp3' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'wav' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'wma' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'aif' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'iff' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'm3u' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'm4u' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'mid' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'mpa' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],
        'ra' => ['type' => self::FILE_TYPE_AUDIO, 'type_name' => self::FILE_TYPE_AUDIO_NAME],

        /** video */
        'avi' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'flv' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'mov' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'mp4' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'm4v' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'mpg' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'rm' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'swf' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'vob' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'wmv' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        '3g2' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        '3gp' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'asf' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],
        'asx' => ['type' => self::FILE_TYPE_VIDEO, 'type_name' => self::FILE_TYPE_VIDEO_NAME],

        /** compressed */
        '7z' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'deb' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'gz' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'pkg' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'rar' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'rpm' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'sit' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'sitx' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'tar.gz' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'zip' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],
        'zipx' => ['type' => self::FILE_TYPE_COMPRESSED, 'type_name' => self::FILE_TYPE_COMPRESSED_NAME],

        /** document */
        'csv' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'doc' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'docx' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'ppt' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'pptx' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'xls' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'xlsx' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'pdf' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'txt' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'log' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'odt' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'ods' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
        'odp' => ['type' => self::FILE_TYPE_DOCUMENT, 'type_name' => self::FILE_TYPE_DOCUMENT_NAME],
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
        self::FILE_TYPE_AUDIO =>
            array(
                0 => 'mp3',
                1 => 'wav',
                2 => 'wma',
                3 => 'aif',
                4 => 'iff',
                5 => 'm3u',
                6 => 'm4u',
                7 => 'mid',
                8 => 'mpa',
                9 => 'ra',
            ),
        self::FILE_TYPE_VIDEO =>
            array(
                0 => 'avi',
                1 => 'flv',
                2 => 'mov',
                3 => 'mp4',
                4 => 'm4v',
                5 => 'mpg',
                6 => 'rm',
                7 => 'swf',
                8 => 'vob',
                9 => 'wmv',
                10 => '3g2',
                11 => '3gp',
                12 => 'asf',
                13 => 'asx',
            ),
        self::FILE_TYPE_COMPRESSED =>
            array(
                0 => '7z',
                1 => 'deb',
                2 => 'gz',
                3 => 'pkg',
                4 => 'rar',
                5 => 'rpm',
                6 => 'sit',
                7 => 'sitx',
                8 => 'tar.gz',
                9 => 'zip',
                10 => 'zipx',
            ),
        self::FILE_TYPE_DOCUMENT =>
            array(
                0 => 'doc',
                1 => 'docx',
                2 => 'ppt',
                3 => 'pptx',
                4 => 'xls',
                5 => 'xlsx',
                6 => 'pdf',
                7 => 'txt',
                8 => 'log',
                9 => 'odt',
                10 => 'ods',
                11 => 'odp',
            ),
    );


    const STATUS_HOSTED = 1;
    const STATUS_NOT_HOSTED = 0;

    protected $presignedUrl;

    protected $objectMediaORM;

    protected $objectMediaAttachmentORM;

    protected $dataArray = [
        'object_uuid' => null,
        'user_uuid' => null,
        'company_uuid' => null,
        'media_uuid' => null,
        'file_info' => [],
        'file_path' => null,
        'file_name' => null,
        'bucket_name' => null,
        'mime_type' => null,
        'file_size' => null,
        'file_extension' => null,
        'file_type' => null,
        'object_type' => null,
    ];

    protected $currentSecurityToken;

    /**
     * SMXDMediaHelper constructor.
     */
    public function __construct()
    {
        return SMXDDynamoORM::__init();
    }


    /**
     * @return mixed
     */
    public function getMediaUuid()
    {
        if (isset($this->dataArray['media_uuid'])) return $this->dataArray['media_uuid'];
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function setMediaUuid($uuid)
    {
        $this->dataArray['media_uuid'] = $uuid;
    }


    /**
     * @return mixed
     */
    public function getCompanyUuid()
    {
        if (isset($this->dataArray['company_uuid'])) return $this->dataArray['company_uuid'];
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function setCompanyUuid($uuid)
    {
        $this->dataArray['company_uuid'] = $uuid;
    }


    /**
     * @return mixed
     */
    public function getFileInfo()
    {
        if (isset($this->dataArray['file_info'])) return $this->dataArray['file_info'];
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function setFileInfo($array)
    {
        $this->dataArray['file_info'] = $array;
    }


    /**
     * @return mixed
     */
    public function getFileName()
    {
        if (isset($this->dataArray['file_name'])) return $this->dataArray['file_name'];
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function setFileName($name)
    {
        $this->dataArray['file_name'] = $name;
    }

    /**
     * @return mixed
     */
    public function getFilePath()
    {
        if (isset($this->dataArray['file_path'])) return $this->dataArray['file_path'];
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function setFilePath($path)
    {
        $this->dataArray['file_path'] = $path;
    }

    /**
     * @return mixed
     */
    public function getFileSize()
    {
        if (isset($this->dataArray['file_size'])) return $this->dataArray['file_size'];
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function setFileSize($size)
    {
        $this->dataArray['file_size'] = $size;
    }


    /**
     * @return mixed
     */
    public function getMimeType()
    {
        if (isset($this->dataArray['mime_type'])) return $this->dataArray['mime_type'];
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function setMimeType($mime_type)
    {
        $this->dataArray['mime_type'] = $mime_type;
    }

    /**
     * @return mixed
     */
    public function getFileExtension()
    {
        if (isset($this->dataArray['file_extension']) && is_string($this->dataArray['file_extension'])) return $this->dataArray['file_extension'];
    }

    /**
     * @return mixed
     */
    public function setFileExtension($file_extension)
    {
        $this->dataArray['file_extension'] = $file_extension;
    }


    /**
     * @return mixed
     */
    public function getObjectUuid()
    {
        if (isset($this->dataArray['object_uuid'])) return $this->dataArray['object_uuid'];
    }

    /**
     * @return mixed
     */
    public function setObjectUuid($uuid)
    {
        $this->dataArray['object_uuid'] = $uuid;
    }


    /**
     * @return mixed
     */
    public function getUserUuid()
    {
        if (isset($this->dataArray['user_uuid'])) return $this->dataArray['user_uuid'];
    }

    /**
     * @return mixed
     */
    public function setUserUuid($uuid)
    {
        $this->dataArray['user_uuid'] = $uuid;
    }

    /**
     * @return mixed
     */
    public function getBucketName()
    {
        if (isset($this->dataArray['bucket_name'])) return $this->dataArray['bucket_name'];
    }

    /**
     * @return mixed
     */
    public function setBucketName($bucket_name)
    {
        $this->dataArray['bucket_name'] = $bucket_name;
    }


    /**
     * @return mixed
     */
    public function getObjectType()
    {
        if (isset($this->dataArray['object_type'])) return $this->dataArray['object_type'];
    }

    /**
     * @return mixed
     */
    public function setObjectType($type)
    {
        $this->dataArray['object_type'] = $type;
    }

    /**
     * @return mixed
     */
    public function getFileType()
    {
        if (isset($this->dataArray['file_type'])) return $this->dataArray['file_type'];
    }

    /**
     * @return mixed
     */
    public function getFileTypeName()
    {
        return isset(self::$ext_types[$this->getFileExtension()]) ? self::$ext_types[$this->getFileExtension()]['type_name'] : 'other';
    }

    /**
     * @return mixed
     */
    public function setFileType($type)
    {
        $this->dataArray['file_type'] = $type;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        if (isset($this->dataArray['created_at'])) return $this->dataArray['created_at'];
    }

    /**
     * @param $time
     */
    public function setCreatedAt($time)
    {
        $this->dataArray['created_at'] = $time;
    }

    /**
     *
     */
    public function addDefaultFilePath()
    {
        if ($this->getFilePath() == '') {
            if ($this->getCompanyUuid() != '') {
                $this->setFilePath($this->getCompanyUuid() . "/" . $this->getRealFileName());
            }
        }
    }

    /**
     * @return array
     */
    public function createInDynamoDb()
    {
        $this->addDefaultFilePath();
        $this->objectMediaORM = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayMedia')->create();
        $this->objectMediaORM->media_uuid = $this->getMediaUuid();
        $this->objectMediaORM->company_uuid = $this->getCompanyUuid();
        $this->objectMediaORM->object_uuid = $this->getObjectUuid();
        $this->objectMediaORM->user_uuid = $this->getUserUuid();
        $this->objectMediaORM->file_path = $this->getFilePath();
        $this->objectMediaORM->bucket_name = $this->getBucketName();
        $this->objectMediaORM->file_name = $this->getFileName();
        $this->objectMediaORM->file_info = DynamoHelper::__objectDataToMapArray($this->getFileInfo());
        $this->objectMediaORM->created_at = ($this->getCreatedAt() > 0 ? $this->getCreatedAt() : time());

        try {
            $result = $this->objectMediaORM->save();
            $return = [
                'success' => true,
                'message' => 'FILE_ADDED_SUCCESS_TEXT',
            ];
        } catch (AwsException $e) {
            $return = [
                'success' => false,
                'message' => 'FILE_ADDED_FAIL_TEXT',
                'object' => $this->objectMediaORM->asArray(),
                'detail' => $e->getMessage(),
            ];

        } catch (SMXDDynamoORMException $e) {
            $return = [
                'success' => false,
                'message' => 'FILE_ADDED_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];

        } catch (Exception $e) {
            $return = [
                'success' => false,
                'message' => 'FILE_ADDED_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];

        }
        return $return;
    }


    /**
     * @return array
     */
    public function getMediaFromDynamoDb()
    {
        if ($this->getMediaUuid() == null || $this->getMediaUuid() == '') return ['success' => false];

        $di = Di::getDefault();
        $cacheManager = $di->getShared('cacheRedisMedia');

        $media = $cacheManager->get($this->getMediaUuid());

        if ($media == null) {
            try {
                //get from CACHE REDIS
                $media = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayMedia')->findOne($this->getMediaUuid());
                // $cacheManager->save($this->getMediaUuid(), $media);
                $return = [
                    'success' => true,
                ];
            } catch (AwsException $e) {
                $return = [
                    'success' => false,
                    'detail' => $e->getMessage(),
                ];

            } catch (SMXDDynamoORMException $e) {
                $return = [
                    'success' => false,
                    'detail' => $e->getMessage(),
                ];

            } catch (Exception $e) {
                $return = [
                    'success' => false,
                    'detail' => $e->getMessage(),
                ];
            }
        } else {
            $return = [
                'success' => true,
            ];
        }

        if ($return['success'] == true) {
            $file_info = DynamoHelper::__mapArrayToArrayData($media->file_info);
            $this->setFileInfo($file_info);
            $this->setCreatedAt($media->created_at);
            $this->setBucketName($media->bucket_name);
            $this->setFileType($media->file_type);
            $this->setFileName($media->file_name);
            $this->setFilePath($media->file_path);

            $this->setMimeType(isset($file_info['mime_type']) ? $file_info['mime_type'] : (isset($file_info['file_real_type']) ? $file_info['file_real_type'] : ''));
            $this->setFileType(isset($file_info['file_type']) ? $file_info['file_type'] : '');
            $this->setFileExtension(isset($file_info['file_extension']) ? $file_info['file_extension'] : '');
            $this->setFileSize(isset($file_info['file_size']) ? $file_info['file_size'] : '');
        }

        return $return;
    }


    /**
     * @return array
     */
    public function deleteAttachment()
    {
        try {
            $mediagetObjectUuidAt = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayMediaAttachment')->findOne($this->getObjectUuid(), $this->getMediaUuid());

            if ($mediagetObjectUuidAt) {
                $mediagetObjectUuidAt->delete();
                $return = [
                    'success' => true,
                ];
            } else {
                $return = [
                    'success' => false,
                    'msg' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        } catch (AwsException $e) {
            $return = [
                'success' => false,
                'detail' => $e->getMessage(),
            ];

        } catch (SMXDDynamoORMException $e) {
            $return = [
                'success' => false,
                'detail' => $e->getMessage(),
            ];

        } catch (Exception $e) {
            $return = [
                'success' => false,
                'detail' => $e->getMessage(),
            ];
        }
        return $return;
    }

    /**
     *  attach file
     */
    public function attachToObject()
    {

        if ($this->getMediaUuid() != null && $this->getObjectUuid() != null) {

            $this->objectMediaAttachmentORM = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayMediaAttachment')->create();
            $this->objectMediaAttachmentORM->media_uuid = $this->getMediaUuid();
            $this->objectMediaAttachmentORM->object_uuid = $this->getObjectUuid();
            $this->objectMediaAttachmentORM->object_type = $this->getObjectType();
            $this->objectMediaAttachmentORM->file_info = DynamoHelper::__objectDataToMapArray($this->getFileInfo());
            $this->objectMediaAttachmentORM->created_at = ($this->getCreatedAt() > 0 ? $this->getCreatedAt() : time());

            try {
                $result = $this->objectMediaAttachmentORM->save();
                $return = [
                    'success' => true,
                    'message' => 'FILE_ATTACHED_SUCCESS_TEXT',
                ];
            } catch (AwsException $e) {
                $this->db->rollback();
                $return = [
                    'success' => false,
                    'message' => 'FILE_ADDED_FAIL_TEXT',
                    'object' => $this->objectMediaAttachmentORM->asArray(),
                    'detail' => $e->getMessage(),
                ];

            } catch (SMXDDynamoORMException $e) {
                $this->db->rollback();
                $return = [
                    'success' => false,
                    'message' => 'FILE_ADDED_FAIL_TEXT',
                    'detail' => $e->getMessage(),
                ];

            } catch (Exception $e) {
                $this->db->rollback();
                $return = [
                    'success' => false,
                    'message' => 'FILE_ADDED_FAIL_TEXT',
                    'detail' => $e->getMessage(),
                ];

            }
            return $return;

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
     *  remove from attachment
     */
    public function remove()
    {

        //remove file form
    }

    /**
     *  remove from attachment
     */
    public function removeAttachedFile()
    {
        //remove file form
    }


    /**
     * @return array|bool
     */
    public function getMediaFromAttachement()
    {
        if ($this->getObjectUuid() != '' && $this->getMediaUuid() != '') {
            try {
                $mediaAttachment = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayMediaAttachment')->findOne($this->getObjectUuid(), $this->getMediaUuid());
                $this->setCreatedAt($mediaAttachment->created_at);
                $this->setBucketName($mediaAttachment->bucket_name);
                $this->setFileType($mediaAttachment->file_type);
                $this->setFileInfo($mediaAttachment->file_info);


                $this->setMimeType(isset($mediaAttachment->file_info['mime_type']) ? $mediaAttachment->file_info['mime_type'] : '');
                $this->setFileType(isset($mediaAttachment->file_info['file_type']) ? $mediaAttachment->file_info['file_type'] : '');
                $this->setFileExtension(isset($mediaAttachment->file_info['file_extension']) ? $mediaAttachment->file_info['file_extension'] : '');
                $this->setFileSize(isset($mediaAttachment->file_info['file_size']) ? $mediaAttachment->file_info['file_size'] : '');

                return $this->toArray();

            } catch (AwsException $e) {
                return false;

            } catch (SMXDDynamoORMException $e) {
                return false;

            } catch (Exception $e) {
                return false;

            }
        }
        return false;
    }

    /**
     * @return string
     */
    public function getUserFileName()
    {
        return $this->getFileName() . "." . $this->getFileExtension();
    }

    /**
     * @return string
     */
    public function getRealFileName()
    {
        return $this->getMediaUuid() . "." . $this->getFileExtension();
    }


    /**
     * open full data on browser
     * [getTUrlToken description]
     * @return [type] [description]
     */
    public function getUrlToken()
    {
        $url = ApplicationModel::__getApiHostname() . '/media/item/viewContent/' . $this->getMediaUuid() . '/check/' . base64_encode($this->getCurrentSecurityToken()) . "/name/" . urlencode($this->getFileName() . "." . $this->getFileExtension());
        return $url;
    }

    /**
     * open full data on browser
     * [getUrlFull description]
     * @return [type] [description]
     */
    public function getUrlFull()
    {
        return ApplicationModel::__getApiHostname() . '/media/item/viewContent/' . $this->getMediaUuid() . '/check/' . base64_encode($this->getCurrentSecurityToken()) . "/name/" . urlencode($this->getFileName() . "." . $this->getFileExtension());
    }

    /**
     * open thumb when click on this link
     * [getUrlThumb description]
     * @return [type] [description]
     */
    public function getUrlThumb()
    {
        return ApplicationModel::__getApiHostname() . '/media/item/getThumbContent/' . $this->getMediaUuid() . '/check/' . base64_encode($this->getCurrentSecurityToken()) . "/name/" . urlencode($this->getFileName() . "." . $this->getFileExtension());
    }

    /**
     * download document when open link
     * [getUrlDownload description]
     * @return [type] [description]
     */
    public function getUrlDownload()
    {
        return ApplicationModel::__getApiHostname() . '/media/item/downloadContent/' . $this->getMediaUuid() . '/check/' .
            base64_encode($this->getCurrentSecurityToken()) . "/name/" .
            urlencode($this->getFileName() . "." .
                $this->getFileExtension());
    }


    /**
     * search with FULL TEXT
     */
    public function findList($keysearch, $page)
    {

    }

    /**
     *
     */
    public static function __findFileType($extension)
    {
        return self::$ext_types[$extension]['type'];
    }

    /** Format Size of file */
    public static function __formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * @param $token
     */
    public function setCurrentSecurityToken($token)
    {
        if (is_string($token)) $this->currentSecurityToken = $token;
    }

    /**
     * @param $token
     */
    public function getCurrentSecurityToken()
    {
        return $this->currentSecurityToken;
    }

    /**
     * @param $object_uuid
     * @return array|bool
     */
    public static function __findAllByObjectUuid($object_uuid)
    {

        try {
            $di = Di::getDefault();
            SMXDDynamoORM::__init();
            $mediaArray = SMXDDynamoORM::factory('\SMXD\Application\Models\RelodayMediaAttachment')
                ->where('object_uuid', $object_uuid)
                ->findMany();

            $array = [];
            if (count($mediaArray) > 0) {
                foreach ($mediaArray as $item) {
                    $mediaItem = new self();
                    $mediaItem->setMediaUuid($item->media_uuid);
                    $res = $mediaItem->getMediaFromDynamoDb();
                    if ($res['success'] == true) {
                        $array[] = $mediaItem;
                    }
                }
            }
            return $array;

        } catch (AwsException $e) {
            return false;

        } catch (SMXDDynamoORMException $e) {
            return false;

        } catch (Exception $e) {
            return false;

        }
    }

    /**
     *
     */
    public function toArray()
    {
        $return = $this->dataArray;
        $return['uuid'] = $this->dataArray['media_uuid'];
        $return['name'] = $this->dataArray['file_name'];
        $return['file_type'] = $this->getFileTypeName();
        $return['real_file_name'] = $this->getRealFileName();
        $return['image_data'] = [
            "url_download" => $this->getUrlDownload(),
            "url_token" => $this->getUrlToken(),
            "url_full" => $this->getUrlFull(),
            "url_thumb" => $this->getUrlThumb(),
            "name" => $this->getFilename()
        ];
        return $return;
    }

    /**
     * get presigned url of media
     * @return mixed
     */
    public function getPresinedUrl()
    {
        if ($this->presignedUrl == '') {
            $this->createPresignedUrl();
        }
        return $this->presignedUrl;
    }

    /**
     * create presigned url
     * @return array
     */
    public function createPresignedUrl()
    {
        $this->addDefaultFilePath();
        $fileName = trim($this->getFilePath(), "/");
        $di = Di::getDefault();
        $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        $awsRegion = $di->getShared('appConfig')->aws->region;

        try {
            $s3client = $di->get('aws')->createS3();
            $cmd = $s3client->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key' => $fileName,
                'ResponseContentType' => $this->getMimeType(),
                'ResponseContentLanguage' => 'en-US',
                'ResponseContentDisposition' => 'attachment; filename=' . $this->getFilename(),
                'ResponseCacheControl' => 'No-cache',
                'ResponseExpires' => gmdate(DATE_RFC2822, time() + 3600),
            ]);
            $request = $s3client->createPresignedRequest($cmd, '+10 minutes');
            // Get the actual presigned-url
            $presignedUrl = (string)$request->getUri();
            $this->presignedUrl = $presignedUrl;
            return ['success' => true];
        } catch (AwsException $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     *
     */
    public function getObjectContent()
    {

    }

    /**
     *
     */
    public function thumbIsExist()
    {

    }

    /**
     *
     */
    public function getThumbPresignedUrl()
    {
        $this->addDefaultFilePath();
        $fileName = trim($this->getFilePath(), "/");
        $di = Di::getDefault();
        $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        $awsRegion = $di->getShared('appConfig')->aws->region;

        try {
            $s3client = $di->get('aws')->createS3();
            $objectUrl = $s3client->getObjectUrl($bucketName, $fileName);
            return $objectUrl;
        } catch (AwsException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }

    }


    /**
     *
     */
    public function getThumbPublicUrl()
    {
        $this->addDefaultFilePath();
        $fileName = $this->getRealFileName();
        $di = Di::getDefault();
        $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        $bucketThumb = $di->getShared('appConfig')->aws->bucket_thumb_name;
        $awsRegion = $di->getShared('appConfig')->aws->region;


        try {
            $s3client = $di->get('aws')->createS3();
            $objectUrl = $s3client->getObjectUrl($bucketThumb, "thumb/" . $fileName);
            return $objectUrl;
        } catch (AwsException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     *
     */
    public function getThumbContent()
    {

    }

    /**
     * @param $data
     * @return float|int
     */
    public static function __getFileSizeBase64($data){
        return (strlen($data) * 3 / 4) - substr_count(substr($data, -2), '=');
    }

}