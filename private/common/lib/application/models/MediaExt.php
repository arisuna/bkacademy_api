<?php


namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Relation;
use Intervention\Image\ImageManagerStatic as Image;
use Phalcon\Http\Client\Provider\Exception;
use \Aws\Credentials\CredentialProvider;
use \Guzzle\Http\Client;
use Phalcon\Http\Request;
use Phalcon\Validation;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\SMXDS3Helper;
use SMXD\Application\Lib\SMXDUrlHelper;
use SMXD\Application\Traits\ModelTraits;
use SMXD\Application\Validator\FileNameValidator;

class MediaExt extends Media
{
    use ModelTraits;

    /** @var [varchar] [url of token] */
    public $url_token;
    /** @var [varchar] [url of full load] */
    public $url_full;
    /** @var [varchar] [url of thumbnail] */
    public $url_thumb;


    const FILE_TYPE_DOCUMENT = 1;
    const FILE_TYPE_IMAGE = 2;
    const FILE_TYPE_COMPRESSED = 3;
    const FILE_TYPE_AUDIO = 4;
    const FILE_TYPE_VIDEO = 5;
    const FILE_TYPE_OTHER = 6;

    const FILE_TYPE_DOCUMENT_NAME = 'document';
    const FILE_TYPE_IMAGE_NAME = 'image';
    const FILE_TYPE_VIDEO_NAME = 'video';
    const FILE_TYPE_AUDIO_NAME = 'audio';
    const FILE_TYPE_COMPRESSED_NAME = 'compressed';
    const FILE_TYPE_OTHER_NAME = 'other';

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
        'xspf' =>
            array(
                0 => 'application/xspf+xml',
            ),
        'vlc' =>
            array(
                0 => 'application/videolan',
            ),
        'wmv' =>
            array(
                0 => 'video/x-ms-wmv',
                1 => 'video/x-ms-asf',
            ),
        'au' =>
            array(
                0 => 'audio/x-au',
            ),
        'ac3' =>
            array(
                0 => 'audio/ac3',
            ),
        'flac' =>
            array(
                0 => 'audio/x-flac',
            ),
        'ogg' =>
            array(
                0 => 'audio/ogg',
                1 => 'video/ogg',
                2 => 'application/ogg',
            ),
        'kmz' =>
            array(
                0 => 'application/vnd.google-earth.kmz',
            ),
        'kml' =>
            array(
                0 => 'application/vnd.google-earth.kml+xml',
            ),
        'rtx' =>
            array(
                0 => 'text/richtext',
            ),
        'rtf' =>
            array(
                0 => 'text/rtf',
            ),
        'jar' =>
            array(
                0 => 'application/java-archive',
                1 => 'application/x-java-application',
                2 => 'application/x-jar',
            ),
        'zip' =>
            array(
                0 => 'application/x-zip',
                1 => 'application/zip',
                2 => 'application/x-zip-compressed',
                3 => 'application/s-compressed',
                4 => 'multipart/x-zip',
            ),
        '7zip' =>
            array(
                0 => 'application/x-compressed',
            ),
        'xml' =>
            array(
                0 => 'application/xml',
                1 => 'text/xml',
            ),
        'svg' =>
            array(
                0 => 'image/svg+xml',
            ),
        '3g2' =>
            array(
                0 => 'video/3gpp2',
            ),
        '3gp' =>
            array(
                0 => 'video/3gp',
                1 => 'video/3gpp',
            ),
        'mp4' =>
            array(
                0 => 'video/mp4',
            ),
        'm4a' =>
            array(
                0 => 'audio/x-m4a',
            ),
        'f4v' =>
            array(
                0 => 'video/x-f4v',
            ),
        'flv' =>
            array(
                0 => 'video/x-flv',
            ),
        'webm' =>
            array(
                0 => 'video/webm',
            ),
        'aac' =>
            array(
                0 => 'audio/x-acc',
            ),
        'm4u' =>
            array(
                0 => 'application/vnd.mpegurl',
            ),
        'pdf' =>
            array(
                0 => 'application/pdf',
                1 => 'application/octet-stream',
            ),
        'pptx' =>
            array(
                0 => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ),
        'ppt' =>
            array(
                0 => 'application/powerpoint',
                1 => 'application/vnd.ms-powerpoint',
                2 => 'application/vnd.ms-office',
                3 => 'application/msword',
            ),
        'docx' =>
            array(
                0 => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        'xlsx' =>
            array(
                0 => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                1 => 'application/vnd.ms-excel',
            ),
        'xl' =>
            array(
                0 => 'application/excel',
            ),
        'xls' =>
            array(
                0 => 'application/msexcel',
                1 => 'application/x-msexcel',
                2 => 'application/x-ms-excel',
                3 => 'application/x-excel',
                4 => 'application/x-dos_ms_excel',
                5 => 'application/xls',
                6 => 'application/x-xls',
            ),
        'xsl' =>
            array(
                0 => 'text/xsl',
            ),
        'mpeg' =>
            array(
                0 => 'video/mpeg',
            ),
        'mov' =>
            array(
                0 => 'video/quicktime',
            ),
        'avi' =>
            array(
                0 => 'video/x-msvideo',
                1 => 'video/msvideo',
                2 => 'video/avi',
                3 => 'application/x-troff-msvideo',
            ),
        'movie' =>
            array(
                0 => 'video/x-sgi-movie',
            ),
        'log' =>
            array(
                0 => 'text/x-log',
            ),
        'txt' =>
            array(
                0 => 'text/plain',
            ),
        'css' =>
            array(
                0 => 'text/css',
            ),
        'html' =>
            array(
                0 => 'text/html',
            ),
        'wav' =>
            array(
                0 => 'audio/x-wav',
                1 => 'audio/wave',
                2 => 'audio/wav',
            ),
        'xhtml' =>
            array(
                0 => 'application/xhtml+xml',
            ),
        'tar' =>
            array(
                0 => 'application/x-tar',
            ),
        'tgz' =>
            array(
                0 => 'application/x-gzip-compressed',
            ),
        'psd' =>
            array(
                0 => 'application/x-photoshop',
                1 => 'image/vnd.adobe.photoshop',
            ),
        'exe' =>
            array(
                0 => 'application/x-msdownload',
            ),
        'js' =>
            array(
                0 => 'application/x-javascript',
            ),
        'mp3' =>
            array(
                0 => 'audio/mpeg',
                1 => 'audio/mpg',
                2 => 'audio/mpeg3',
                3 => 'audio/mp3',
            ),
        'rar' =>
            array(
                0 => 'application/x-rar',
                1 => 'application/rar',
                2 => 'application/x-rar-compressed',
            ),
        'gzip' =>
            array(
                0 => 'application/x-gzip',
            ),
        'hqx' =>
            array(
                0 => 'application/mac-binhex40',
                1 => 'application/mac-binhex',
                2 => 'application/x-binhex40',
                3 => 'application/x-mac-binhex40',
            ),
        'cpt' =>
            array(
                0 => 'application/mac-compactpro',
            ),
        'bin' =>
            array(
                0 => 'application/macbinary',
                1 => 'application/mac-binary',
                2 => 'application/x-binary',
                3 => 'application/x-macbinary',
            ),
        'oda' =>
            array(
                0 => 'application/oda',
            ),
        'ai' =>
            array(
                0 => 'application/postscript',
            ),
        'smil' =>
            array(
                0 => 'application/smil',
            ),
        'mif' =>
            array(
                0 => 'application/vnd.mif',
            ),
        'wbxml' =>
            array(
                0 => 'application/wbxml',
            ),
        'wmlc' =>
            array(
                0 => 'application/wmlc',
            ),
        'dcr' =>
            array(
                0 => 'application/x-director',
            ),
        'dvi' =>
            array(
                0 => 'application/x-dvi',
            ),
        'gtar' =>
            array(
                0 => 'application/x-gtar',
            ),
        'php' =>
            array(
                0 => 'application/x-httpd-php',
                1 => 'application/php',
                2 => 'application/x-php',
                3 => 'text/php',
                4 => 'text/x-php',
                5 => 'application/x-httpd-php-source',
            ),
        'swf' =>
            array(
                0 => 'application/x-shockwave-flash',
            ),
        'sit' =>
            array(
                0 => 'application/x-stuffit',
            ),
        'z' =>
            array(
                0 => 'application/x-compress',
            ),
        'mid' =>
            array(
                0 => 'audio/midi',
            ),
        'aif' =>
            array(
                0 => 'audio/x-aiff',
                1 => 'audio/aiff',
            ),
        'ram' =>
            array(
                0 => 'audio/x-pn-realaudio',
            ),
        'rpm' =>
            array(
                0 => 'audio/x-pn-realaudio-plugin',
            ),
        'ra' =>
            array(
                0 => 'audio/x-realaudio',
            ),
        'rv' =>
            array(
                0 => 'video/vnd.rn-realvideo',
            ),
        'jp2' =>
            array(
                0 => 'image/jp2',
                1 => 'video/mj2',
                2 => 'image/jpx',
                3 => 'image/jpm',
            ),
        'tiff' =>
            array(
                0 => 'image/tiff',
            ),
        'eml' =>
            array(
                0 => 'message/rfc822',
            ),
        'pem' =>
            array(
                0 => 'application/x-x509-user-cert',
                1 => 'application/x-pem-file',
            ),
        'p10' =>
            array(
                0 => 'application/x-pkcs10',
                1 => 'application/pkcs10',
            ),
        'p12' =>
            array(
                0 => 'application/x-pkcs12',
            ),
        'p7a' =>
            array(
                0 => 'application/x-pkcs7-signature',
            ),
        'p7c' =>
            array(
                0 => 'application/pkcs7-mime',
                1 => 'application/x-pkcs7-mime',
            ),
        'p7r' =>
            array(
                0 => 'application/x-pkcs7-certreqresp',
            ),
        'p7s' =>
            array(
                0 => 'application/pkcs7-signature',
            ),
        'crt' =>
            array(
                0 => 'application/x-x509-ca-cert',
                1 => 'application/pkix-cert',
            ),
        'crl' =>
            array(
                0 => 'application/pkix-crl',
                1 => 'application/pkcs-crl',
            ),
        'pgp' =>
            array(
                0 => 'application/pgp',
            ),
        'gpg' =>
            array(
                0 => 'application/gpg-keys',
            ),
        'rsa' =>
            array(
                0 => 'application/x-pkcs7',
            ),
        'ics' =>
            array(
                0 => 'text/calendar',
            ),
        'zsh' =>
            array(
                0 => 'text/x-scriptzsh',
            ),
        'cdr' =>
            array(
                0 => 'application/cdr',
                1 => 'application/coreldraw',
                2 => 'application/x-cdr',
                3 => 'application/x-coreldraw',
                4 => 'image/cdr',
                5 => 'image/x-cdr',
                6 => 'zz-application/zz-winassoc-cdr',
            ),
        'wma' =>
            array(
                0 => 'audio/x-ms-wma',
            ),
        'vcf' =>
            array(
                0 => 'text/x-vcard',
            ),
        'srt' =>
            array(
                0 => 'text/srt',
            ),
        'vtt' =>
            array(
                0 => 'text/vtt',
            ),
        'ico' =>
            array(
                0 => 'image/x-icon',
                1 => 'image/x-ico',
                2 => 'image/vnd.microsoft.icon',
            ),
        'csv' =>
            array(
                0 => 'text/x-comma-separated-values',
                1 => 'text/comma-separated-values',
                2 => 'application/vnd.msexcel',
            ),
        'json' =>
            array(
                0 => 'application/json',
                1 => 'text/json',
            ),
    );

    const STATUS_HOSTED = 1;
    const STATUS_NOT_HOSTED = 0;

    const IS_PRIVATE_YES = 1;
    const IS_PRIVATE_NO = 0;

    const IMAGE_MAX_WIDTH = 800;
    const IMAGE_MAX_HEIGHT = 640;

    const IS_DELETE_YES = 1;
    const IS_DELETE_NO = 0;

    /**
     * @return string
     */
    static function getTable()
    {
        $instance = new Media();
        return $instance->getSource();
    }

    /**
     * initialize the model
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
        $this->useDynamicUpdate(true);
        $this->keepSnapshots(true);
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

        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\SoftDelete([
            'field' => 'is_deleted',
            'value' => self::IS_DELETE_YES
        ]));

        $this->belongsTo('media_type_id', 'SMXD\Application\Models\MediaTypeExt', 'id', [
            'alias' => 'MediaType',
            'reusable' => true
        ]);

        $this->belongsTo('company_id', 'SMXD\Application\Models\CompanyExt', 'id', [
            'alias' => 'Company',
            'reusable' => true,
        ]);


        $this->belongsTo('user_uuid', 'SMXD\Application\Models\UserExt', 'uuid', [
            'alias' => 'User',
        ]);

//        $this->belongsTo('folder_uuid', 'SMXD\Application\Models\MediaFolderExt', 'uuid', [
//            'alias' => 'MediaFolder',
//        ]);
    }


    /**
     * @return bool
     */
    public function validation()
    {
        $validator = new Validation();
        $validator->add(
            'name',
            new Validation\Validator\PresenceOf([
                'model' => $this,
                'message' => 'FILE_NAME_REQUIRED_TEXT'
            ])
        );
        /*
        $validator->add(
            'name',
            new FileNameValidator([
                'model' => $this,
                'message' => 'FILE_NAME_INVALID_TEXT'
            ])
        );
        */
//        $validator->add(
//            'user_uuid',
//            new Validation\Validator\PresenceOf([
//                'model' => $this,
//                'message' => 'FILE_CREATOR_REQUIRED_TEXT'
//            ])
//        );
//        if ($this->getFolderUuid() != '' && !is_null($this->getFolderUuid())) {
//            $validator->add(
//                ['name', 'user_uuid', 'file_extension', 'folder_uuid'],
//                new Validation\Validator\Uniqueness([
//                    'model' => $this,
//                    'message' => 'FILE_NAME_SHOULD_BE_UNIQUE_TEXT',
//                ])
//            );
//        } else {
//            $validator->add(
//                ['name', 'user_uuid', 'file_extension'],
//                new Validation\Validator\Uniqueness([
//                    'model' => $this,
//                    'message' => 'FILE_NAME_SHOULD_BE_UNIQUE_TEXT',
//                ])
//            );
//        }
        return $this->validate($validator);
    }

    public function beforeValidationOnCreate()
    {

    }

    /**
     * [beforeValidation description]
     * @return [type] [description]
     */
    public function beforeValidationOnUpdate()
    {
        //if soft delete
        $this->updateBeforeSoftDelete();
    }

    /**
     * @param $model
     * @return bool
     */
    public function validateFileName()
    {
        $validator = new Validation();

        $validator->add(
            ['name', 'user_uuid', 'file_extension', 'is_deleted'],
            new Validation\Validator\Uniqueness([
                'model' => $this,
                'message' => 'FILE_NAME_SHOULD_BE_UNIQUE_TEXT',
            ])
        );

        $data = [
            'name' => $this->getName(),
            'file_extension' => $this->getFileExtension(),
            'user_uuid' => $this->getUserUuid(),
            'is_deleted' => self::IS_DELETE_NO,
        ];
        return $validator->validate($data);
    }

    /**
     * @return bool
     */
    public function checkFileName()
    {
        $messages = $this->validateFileName();
        if ($messages->count() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function checkFileNameExisted()
    {
        $messages = $this->validateFileName();
        if ($messages->count() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * [afterFetch description]
     * @return [type] [description]
     */
    public function afterFetch()
    {
        if ($this->isDeleted()) {
            $this->name = $this->name_static;
        }

        $this->url_token = ApplicationModel::__getApiHostname() . '/media/file/load/' . $this->getUuid() . '/' . $this->getFileType() . "/{token}&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
        $this->url_full = ApplicationModel::__getApiHostname() . '/media/file/thumbnail/' . $this->getUuid() . '/' . $this->getFileType() . "/{token}&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
        $this->url_thumb = ApplicationModel::__getApiHostname() . '/media/file/thumbnail/' . $this->getUuid() . '/' . $this->getFileType() . "/{token}&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
        $this->url_download = ApplicationModel::__getApiHostname() . '/media/file/download/' . $this->getUuid() . '/' . $this->getFileType() . "/{token}&name=" . urlencode($this->getName() . "." . $this->getFileExtension());
    }

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
     * get Thumb file content
     * @return bool|\Psr\Http\Message\StreamInterface|string
     */
    public function getThumbContent()
    {
        $thumb_file_path = $this->getThumbFilePath();
        if ($this->getFileType() == self::FILE_TYPE_IMAGE_NAME) {
            if ($this->getIsHosted() == self::STATUS_HOSTED) {
                $dataBody = $this->getThumbFromS3();
                if ($dataBody !== false) {
                    return $dataBody;
                } else {
                    return false;
                }
            } elseif (file_exists($thumb_file_path)) {
                return $this->getThumbFromDirectory();
            }
        }
    }

    /**
     * @return bool|\Psr\Http\Message\StreamInterface
     */
    public function getThumbFromS3()
    {

        if ($this->getIsHosted() == self::STATUS_HOSTED) {

            $config = self::getAppConfig();
            $configEngine = self::getEngineConfig();
            $bucketName = self::getAppConfig()->aws->bucket_thumb_name;

            $file = self::__getThumbDirectoryS3() . "/" . $this->getFilename();

            try {

                $di = \Phalcon\DI::getDefault();
                $s3client = $di->get('aws')->createS3();

                if ($s3client->doesObjectExist($bucketName, $file) == true) {
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
                } else {
                    return false;
                }
            } catch (\Guzzle\Http\Exception\ClientException $e) {
                return false;
            } catch (Exception $e) {
                return false;
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
        } catch (Exception $e) {
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
    public static function getAppConfig()
    {
        $config = \Phalcon\DI\FactoryDefault::getDefault()->getShared('appConfig');
        return $config;
    }

    /**
     * @return mixed
     */
    public function getDiAppConfig()
    {

        try {
            $config = \Phalcon\DI\FactoryDefault::getDefault()->getShared('appConfig');
            return $config;
        } catch (Exception $e) {
            return false;
        }

        if ($config !== null) {
            return $config;
        } else {
            return false;
        }

    }

    /**
     * @return mixed
     */
    public function getEngineConfig()
    {
        $config = $this->getDi()->getShared('config');
        return $config;
    }

    /**
     * @return bool
     */
    public function getTemporaryThumbFileUrlS3()
    {

        if ($this->getIsHosted() == self::STATUS_HOSTED) {
            $bucketThumbName = self::getAppConfig()->aws->bucket_thumb_name;
            $file = self::__getThumbDirectoryS3() . "/" . $this->getFilename();
            try {
                $di = \Phalcon\DI::getDefault();
                $s3client = $di->get('aws')->createS3();
                if ($s3client->doesObjectExist($bucketThumbName, $file) == true) {
                    $cmd = $s3client->getCommand('GetObject', [
                        'Bucket' => $bucketThumbName,
                        'Key' => $file,
                        'ResponseContentType' => $this->getMimeType(),
                        'ResponseContentLanguage' => 'en-US',
                        'ResponseContentDisposition' => 'attachment; filename=' . $this->getFilename(),
                        'ResponseCacheControl' => 'No-cache',
                        'ResponseExpires' => gmdate(DATE_RFC2822, time() + 3600),
                    ]);
                    $request = $s3client->createPresignedRequest($cmd, '+10 minutes');
                    // Get the actual presigned-url
                    $presignedUrl = (string)$request->getUri();
                    return $presignedUrl;
                } else {
                    return false;
                }
            } catch (\Guzzle\Http\Exception\ClientException $e) {
                return false;
            } catch (Exception $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function getTemporaryFileUrlS3()
    {

        if ($this->getIsHosted() == self::STATUS_HOSTED) {

            $bucketName = self::getAppConfig()->aws->bucket_name;
            $file = $this->getRealFilePath();

            try {

                $di = \Phalcon\DI::getDefault();
                $s3client = $di->get('aws')->createS3();

                if ($s3client->doesObjectExist($bucketName, $file) == true) {
                    $cmd = $s3client->getCommand('GetObject', [
                        'Bucket' => $bucketName,
                        'Key' => $file,
                        'ResponseContentType' => $this->getMimeType(),
                        //'ResponseContentLanguage' => 'en-US',
                        'ResponseContentDisposition' => "attachment; filename=" . addslashes(rawurlencode($this->getDownloadFileName())) . ";" . "filename*=utf-8''" . rawurlencode($this->getDownloadFileName()),
                        'ResponseCacheControl' => 'No-cache',
                        'ResponseExpires' => gmdate(DATE_RFC2822, time() + 3600),
                    ]);

                    $request = $s3client->createPresignedRequest($cmd, '+10 minutes');
                    // Get the actual presigned-url
                    $presignedUrl = (string)$request->getUri();
                    return $presignedUrl;
                } else {
                    return false;
                }
            } catch (\Guzzle\Http\Exception\ClientException $e) {
                return false;
            } catch (Exception $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function getTemporaryFileUrlCloudFront()
    {
        if ($this->getTemporaryFileUrlS3() != false) {

            try {
                $di = \Phalcon\DI::getDefault();
                $file = getenv('CLOUDFRONT_URL') . $this->getRealFilePath();
                $cloudFrontClien = $di->get('aws')->createCloudFront();
                $signedUrl = $cloudFrontClien->getSignedUrl([
                    'url' => $file,
                    'expires' => time() + 3600,
                    'key_pair_id' => 'K37PI2BSOJQF89',
                    'private_key' => file_get_contents($di->getShared('appConfig')->application->keyDir . getenv('CLOUDFRONT_PRIVATE_PEM_FILE'))
                ]);
                return $signedUrl;
            } catch (ClientException $e) {
                var_dump($e->getMessage());
                die(__METHOD__);
                return false;
            } catch (\Exception $e) {
                var_dump($e->getTraceAsString());
                var_dump($e->getMessage());
                die(__METHOD__);
                return false;
            }
        } else {
            var_dump('d');
            die(__METHOD__);
            return false;
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
     * @param $temporaryFilePath
     * @return array
     */
    public function uploadToS3PublicFromPath($temporaryFilePath)
    {
        $di = \Phalcon\DI::getDefault();
        $bucketPublicName = $di->get('appConfig')->aws->bucket_public_name;
        $this->addDefaultFilePath();
        $fileName = $this->getFilePath();
        return SMXDS3Helper::__uploadSingleFileWithFilePath($fileName, $temporaryFilePath, $bucketPublicName);
    }

    /**
     * @param $temporaryFilePath
     * @return bool
     */
    public function resizePublicImageAndUploadToS3($temporaryFilePath)
    {
        $di = \Phalcon\DI::getDefault();
        $bucketPublicName = $di->get('appConfig')->aws->bucket_public_name;
        $fileName = $this->getDynamicRealPath();
        list($width, $height, $type, $attr) = getimagesize($temporaryFilePath);
        if (extension_loaded('imagick') || class_exists("Imagick")) {
            $image = new \Phalcon\Image\Adapter\Imagick($temporaryFilePath);
            if ($width > self::IMAGE_MAX_WIDTH) {
                $image->resize(self::IMAGE_MAX_WIDTH, null, \Phalcon\Image::WIDTH);
            } elseif ($height > self::IMAGE_MAX_HEIGHT) {
                $image->resize(null, self::IMAGE_MAX_HEIGHT, \Phalcon\Image::HEIGHT);
            }
            $imageRender = $image->render();
        } else {
            $image = Image::make($temporaryFilePath);
            if ($width > self::IMAGE_MAX_WIDTH) {
                $image->resize(self::IMAGE_MAX_WIDTH, null);
            } elseif ($height > self::IMAGE_MAX_HEIGHT) {
                $image->resize(null, self::IMAGE_MAX_HEIGHT);
            }
            $imageRender = (string)$image->encode($this->getFileExtension());
        }

        try {
            $result = SMXDS3Helper::__uploadSingleFilePublic($fileName, $imageRender, $bucketPublicName, $this->getMimeType());
            $result['bucketName'] = $bucketPublicName;
            return $result;
        } catch (\Phalcon\Image\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'bucketName' => $bucketPublicName];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'bucketName' => $bucketPublicName];
        }
    }

    /**
     * @param $temporaryFilePath
     * @return array
     */
    public function uploadToS3FromContent($fileContent)
    {
        $this->addDefaultFilePath();
        $fileName = $this->getFilePath();
        return SMXDS3Helper::__uploadSingleFile($fileName, $fileContent);
    }

    /**
     *
     */
    public function addDefaultFilePath()
    {
        if ($this->getFilePath() == '') {
            if ($this->getCompany()) {
                $path = $this->getCompany()->getUuid() . "/" . $this->getRealFileName();
                $this->setFilePath($path);
            }
        }
    }

    /**
     * @return string
     */
    public function getRealFileName()
    {
        return $this->getUuid() . "." . $this->getFileExtension();
    }

    /**
     * get real file path in AMAZON S3
     * @return string
     */
    public function getRealFilePath()
    {

//        $fileNameUpload =  $uuid . '/' . $uuid . '.' . $extension;
//        if ($objectUuid) {
//            $fileNameUpload = $objectUuid . '/' . $uuid . '.' . $extension;
//        }
//        if ($fileObject) {
//            $fileNameUpload = $fileObject . $uuid . '.' . $extension;
//            if ($objectUuid) {
//                $fileNameUpload = $fileObject . '/' . $objectUuid . '/' . $uuid . '.' . $extension;
//            }
//        }

        if ($this->getFilePath() == null || $this->getFilePath() == '')
            if ($this->getCompanyId() > 0) {
                return ($this->getCompany()->getUuid() . "/" . $this->getRealFileName());
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
     *
     */
    public function getRawDataContentFromS3()
    {
        return SMXDS3Helper::__getBodyObject($this->getRealFilePath());
    }

    /**
     * @return array
     */
    public function getSizeFromS3()
    {
        $resultSize = SMXDS3Helper::__getSizeObject($this->getRealFilePath());
        if ($resultSize['success'] == true) {
            return $resultSize['data'];
        } else {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getDownloadFileName()
    {
        if ($this->getNameStatic() != '') {
            return ($this->getNameStatic()) . "." . $this->getFileExtension();
        } else {
            return ($this->getName()) . "." . $this->getFileExtension();
        }

    }


    /**
     * [getTUrlToken description]
     * @return [type] [description]
     */
    public function getUrlToken($token = '')
    {
        return ApplicationModel::__getApiHostname() . '/media/file/load?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . $token . "&name=" . urlencode($this->getDownloadFileName());

    }

    /**
     * [getUrlFull description]
     * @return [type] [description]
     */
    public function getUrlFull($token = '')
    {
        return ApplicationModel::__getApiHostname() . '/media/file/full?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . $token . "&name=" . urlencode($this->getDownloadFileName());
    }

    /**
     * [getUrlThumb description]
     * @return [type] [description]
     */
    public function getUrlThumb($token = '')
    {
        return ApplicationModel::__getApiHostname() . '/media/file/thumbnail?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . $token . "&name=" . urlencode($this->getDownloadFileName());
    }

    /**
     * [getUrlDownload description]
     * @return [type] [description]
     */
    public function getUrlDownload($token = '')
    {
        return ApplicationModel::__getApiHostname() . '/media/file/download?uuid=' . $this->getUuid() . '&type=' . $this->getFileType() . "&token=" . $token . "&name=" . urlencode($this->getDownloadFileName());
    }


    /**
     * @return array
     */
    public function __quickUpdate()
    {
        return ModelHelper::__quickUpdate($this);
    }

    /**
     * @return array
     */
    public function __quickSave()
    {
        return ModelHelper::__quickSave($this);
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {

        /** @var [varchar] [set uunique id] */
        if (property_exists($this, 'uuid') && method_exists($this, 'getUuid') && method_exists($this, 'setUuid')) {
            if (property_exists($this, 'uuid') && method_exists($this, 'getUuid') && method_exists($this, 'setUuid')) {
                if ($this->getUuid() == '') {
                    $uuid = ApplicationModel::uuid();
                    $this->setUuid($uuid);
                }
            }
        }
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $this->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($this);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($this);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at') {

                if (!isset($fields_numeric[$field_name])) {
                    $field_name_value = Helpers::__getRequestValueFromCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $this->__get($field_name));
                    $field_name_value = $field_name_value != '' ? $field_name_value : $this->__get($field_name);
                    $this->__set($field_name, $field_name_value);

                } else {

                    $field_name_value = Helpers::__getRequestValueFromCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $this->__get($field_name));
                    if ($field_name_value != '' && !is_null($field_name_value)) {
                        $this->__set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }

    /**
     * [get description]
     * @param  {[type]} $name [description]
     * @return {[type]}       [description]
     */
    public function get($name)
    {
        return $this->$name;
    }

    /**
     * [set description]
     * @param {[type]} $name     [description]
     * @param {[type]} $variable [description]
     */
    public function set($name, $value)
    {
        $this->$name = $value;
    }

    /**
     * @param $mime
     * @return bool|int|string
     */
    public static function __getExtentionFromMime($mime)
    {

        foreach (self::$mimeTypes as $key => $value) {
            if (array_search($mime, $value) !== false) return $key;
        }
        return false;
    }

    /**
     * set file path before create
     */
    public function beforeCreate()
    {
        $this->setFilePath( "smxd_medias/" . $this->getRealFileName());

        if ($this->getNameStatic() == '' || is_null($this->getNameStatic())) {
            $this->setNameStatic($this->getName());
        }

    }

    /**
     * set file path before remove
     */
    public function beforeRemove()
    {
        return SMXDS3Helper::__removeObject($this->getRealFilePath());

    }

    /**
     * @return bool
     */
    public function isExistedInS3()
    {
        $res = SMXDS3Helper::__mediaExist($this->getRealFilePath());
        if ($res['success'] == true) {
            return $res['result'];
        } else {
            return false;
        }
    }

    /**
     * realth Path of V1
     * @return string|void
     */
    public function getV1RealPath()
    {
        return $this->getMediaType()->getAmazonPath() . "/" . $this->getRealFileName();
    }

    /**
     *
     */
    public function getDynamicRealPath()
    {
        return 'media/' . $this->getRealFileName();
    }

    /**
     *
     */
    public function getPublicUrl()
    {
        if ($this->getIsPrivate() == self::IS_PRIVATE_NO) {
            $di = \Phalcon\DI::getDefault();
            return $di->get('appConfig')->aws->bucket_public_url . "/" . $this->getDynamicRealPath();
        }
    }

    /**
     * @return array
     */
    public function __quickCreate()
    {
        return ModelHelper::__quickCreate($this);
    }

    /**
     * @return mixed
     */
    public function toArray($columns = NULL): array: array
    {
        $array = parent::toArray($columns);
        $metadata = $this->getDI()->get('modelsMetadata');
        $types = $metadata->getDataTypes($this);
        foreach ($types as $attribute => $type) {
            $array[$attribute] = ModelHelper::__getAttributeValue($type, $array[$attribute]);
        }
        return $array;
    }

    /**
     * Make a media public
     * @return array
     */
    public function makePublic()
    {
        $this->setIsPrivate(self::IS_PRIVATE_NO);
        $return = $this->__quickUpdate();
        return $return;
    }

    /**
     * @return string
     */
    public function getBackendUrl()
    {
        return SMXDUrlHelper::__getBackendUrl() . "/backend/#/app/media/item/" . $this->getUuid();
    }

    /**
     * @return string
     */
    public function getSizeHumainFormat()
    {
        return Helpers::__formatBytes($this->getSize());
    }

    /**
     * @return array
     */
    public function __quickRemove()
    {
        return ModelHelper::__quickRemove($this);
    }

    /**
     *
     */
    public function updateBeforeSoftDelete()
    {
        //soft delete
        if ($this->isDeleted()) {
            $this->setNameStatic($this->getName());
            $this->setName(time() . "-" . $this->getUuid() . "." . $this->getFileExtension());
        }
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->getIsDeleted() == ModelHelper::YES;
    }

    /**
     * @return string
     */
    public function getNameOfficial()
    {
        if ($this->getNameStatic() != '' && !is_null($this->getNameStatic())) {
            return $this->getNameStatic();
        }
        return $this->getName(); //TODO: Change the autogenerated stub
    }

    /**
     * @return string
     */
    public function getTemporaryThumbS3Url()
    {
        if ($this->getIsHosted() == self::STATUS_HOSTED) {
            $di = \Phalcon\DI::getDefault();
            $bucketName = $di->get('appConfig')->aws->bucket_name;
            $fileName = trim(Helpers::__generateSlug($this->getName())) . "." . $this->getFileExtension();

            if (!$this->getFilePath()) {
                return null;
            }

            return SMXDS3Helper::__getPresignedUrl($this->getFilePath(), $bucketName, $fileName, $this->getMimeType());
        }

        return '';
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findWithCache($parameters = null, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::find($parameters);
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findFirstWithCache($parameters = null, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        // Convert the parameters to an array
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::findFirst($parameters);
    }

    /**
     * @param $companyId
     * @return mixed
     */
    public static function __getTotalFiles($companyId)
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();

        $total_files = $queryBuilder->columns(['Media.uuid as uuid'])
            ->addFrom('\SMXD\Application\Models\MediaExt', 'Media')
            ->distinct(true)
            ->where('Media.is_hidden is null OR Media.is_hidden != 1')
            ->andWhere('Media.is_deleted = 0')
            ->andWhere('Media.company_id = :company_id:', [
                'company_id' => $companyId
            ])
            ->groupBy(['uuid'])
            ->getQuery()->execute()->count();
        return $total_files;
    }

    /**
     * @return MediaExt
     */
    public function loadFileType()
    {
        return $this->setFileType(
            isset(self::$ext_types[$this->getFileExtension()]) ? self::$ext_types[$this->getFileExtension()]['type_name'] : self::FILE_TYPE_OTHER
        );
    }

    /**
     * @return MediaExt
     */
    public function loadMimeType()
    {
        return $this->setMimeType(
            isset(self::$mimeTypes[$this->getFileExtension()]) ? self::$mimeTypes[$this->getFileExtension()][0] : ''
        );
    }

    /**
     * @return MediaExt
     */
    public function loadRealFilePath()
    {
        if ($this->getCompanyId() > 0 && $this->getCompany()) {
            $this->setFilePath($this->getCompany()->getUuid() . "/" . $this->getRealFileName());
        }
    }

    /**
     * Rename file
     */

    public function renameFileIfExisted()
    {
        $num = 0;
        $oldName = $this->getName();
        $existed = $this->checkFileNameExisted();
        while ($existed == true) {
            $suffix = $num + 1;
            $this->setName($oldName . "($suffix)");
            $this->setNameStatic($oldName . "($suffix)");
            $existed = $this->checkFileNameExisted();
            $num++;
        }
    }
}
