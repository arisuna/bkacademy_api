<?php

namespace SMXD\Application\Lib;

use Phalcon\Http\Request\File;
use Phalcon\Utils\Slug as Slug;

class FileHelper
{
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

    /**
     * @param $file
     * @return \stdClass
     * @throws \Phalcon\Exception
     */
    public static function __getFileInfo(File $file)
    {
        $file_info = new \stdClass();
        $file_info->name = basename($file->getName(), '.' . strtolower($file->getExtension()));
        $file_info->basename = Slug::generate(basename($file->getName(), '.' . strtolower($file->getExtension())));
        $file_info->extension = strtolower($file->getExtension());
        $file_info->type = $file->getType();
        $file_info->size = self::__formatBytes($file->getSize());
        $file_info->key = $file->getKey();
        $file_info->real_type = $file->getRealType();
        return $file_info;
    }


    /**
     * @param $size
     * @param int $precision
     * @return string
     */
    public static function __formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * Get File_type
     */
    public static function __getFileType(String $extension)
    {
        foreach (self::$ext_types as $key => $config) {
            if ($key == $extension) {
                return $config['type_name'];
            }
        }
        return self::FILE_TYPE_OTHER_NAME;
    }

    /**
     * @param String $extension
     */
    public static function __isImage(String $extension)
    {
        return self::__getFileType($extension) == self::FILE_TYPE_IMAGE_NAME;
    }
}

?>
