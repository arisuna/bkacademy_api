<?php

namespace SMXD\Application\Lib;

use Aws\S3\Exception;
use Aws\S3\Exception\S3Exception;
use Aws\Exception\AwsException;
use Guzzle\Http\Client;
use Phalcon\Di;

class SMXDS3Helper
{

    // ACL flags
    const ACL_PRIVATE = 'private';
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PUBLIC_READ_WRITE = 'public-read-write';
    const ACL_AUTHENTICATED_READ = 'authenticated-read';

    const STORAGE_CLASS_STANDARD = 'STANDARD';
    const STORAGE_CLASS_RRS = 'REDUCED_REDUNDANCY';

    const SSE_NONE = '';
    const SSE_AES256 = 'AES256';


    protected $S3Client;

    /**
     * SMXDS3Helper constructor.
     */
    public function __construct()
    {
        $di = Di::getDefault();
        $this->S3Client = $di->get('aws')->createS3();
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __uploadSingleFile($fileFullName = '', $fileContent = '', $bucketName = '', $acl = self::ACL_AUTHENTICATED_READ, $contentType = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        try {
            // Upload data.
            $uploadArray = [
                'Bucket' => $bucketName,
                'Key' => $fileFullName,
                'Body' => $fileContent,
                'ACL' => $acl
            ];

            if ($contentType != '') {
                $uploadArray['ContentType'] = $contentType;
            }
            $result = $s3->putObject($uploadArray);
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (S3Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __uploadSingleFilePublic($fileFullName = '', $fileContent = '', $bucketName = '', $contentType = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        try {
            // Upload data.
            $uploadArray = [
                'Bucket' => $bucketName,
                'Key' => $fileFullName,
                'Body' => $fileContent,
                'ACL' => self::ACL_PUBLIC_READ
            ];

            if ($contentType != '') {
                $uploadArray['ContentType'] = $contentType;
            }
            $result = $s3->putObject($uploadArray);
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @param $bucketName
     * @param $fileExpiration
     * @return array
     */
    static public function __uploadSingleFileWithExpiration($fileFullName = '', $fileContent = '', $bucketName, $fileExpiration)
    {

        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') return ['success' => false, 'detail' => 'bucketNulled'];
        try {
            // Upload data.
            $result = $s3->putObject(array(
                'Bucket' => $bucketName,
                'Key' => $fileFullName,
                'Body' => $fileContent,
                'Expires' => $fileExpiration,
                'ACL' => self::ACL_AUTHENTICATED_READ
            ));
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }


    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __uploadSingleFileWithFilePathAndExpiration($writer,$fileFullName = '', $filePath = '', $fileExpiration)
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();

       $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        try {
            // Upload data.
            $result = $s3->putObject(array(
                'Bucket' => $bucketName,
                'Key' => $fileFullName,
                'SourceFile' => $filePath,
                'Expires' => $fileExpiration,
                'ACL' => self::ACL_PUBLIC_READ
            ));
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e];
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __uploadSingleFileWithFilePath($fileFullName = '', $filePath = '', $bucketName = '', $acl = self::ACL_AUTHENTICATED_READ)
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();

        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;

        try {
            // Upload data.
            $result = $s3->putObject(array(
                'Bucket' => $bucketName,
                'Key' => $fileFullName,
                'SourceFile' => $filePath,
                'ACL' => $acl
            ));
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e];
        }
    }

    /**
     * @param $bucketname
     * @return array
     */
    static public function __createBucket($bucketName)
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();

        try {
            // Upload data.
            $result = $s3->createBucket(array(
                'Bucket' => $bucketName,
                'ACL' => self::ACL_AUTHENTICATED_READ
            ));
            $s3->waitUntil('BucketExists', array('Bucket' => $bucketName));
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    static public function __getPresignedUrlToUpload($fileName, $mimeType = '', $bucketName = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        try {
            // Upload data.
            $uploadArray = [
                'Bucket' => $bucketName,
                'Key' => $fileName,
                'ContentType' => $mimeType,
                'ACL' => self::ACL_PUBLIC_READ
            ];
            $cmd = $s3->getCommand('PutObject', $uploadArray);
            $request = $s3->createPresignedRequest($cmd, '+100 minutes');
            // Get the actual presigned-url
            $presignedUrl = (string)$request->getUri();

            return ['success' => true, 'data' => $presignedUrl];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __getPresignedUrl($filePath = '', $bucketName = '', $fileName = '', $mimeType = '', $downloadable = true)
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;

        try {
            $di = \Phalcon\DI::getDefault();

            if ($s3->doesObjectExist($bucketName, $filePath) == true) {

                if ($downloadable == true) {
                    $cmd = $s3->getCommand('GetObject', [
                        'Bucket' => $bucketName,
                        'Key' => $filePath,
                        'ResponseContentType' => $mimeType,
                        'ResponseContentLanguage' => 'en-US',
                        'ResponseContentDisposition' => 'attachment; filename=' . $fileName,
                        'ResponseCacheControl' => 'No-cache',
                        'ResponseExpires' => gmdate(DATE_RFC2822, time() + 3600),
                    ]);
                } else {
                    $cmd = $s3->getCommand('GetObject', [
                        'Bucket' => $bucketName,
                        'Key' => $filePath,
                        'ResponseContentType' => $mimeType,
                        'ResponseContentLanguage' => 'en-US',
                        'ResponseContentDisposition' => 'inline; filename=' . $fileName,
                        'ResponseCacheControl' => 'No-cache',
                        'ResponseExpires' => gmdate(DATE_RFC2822, time() + 3600),
                    ]);
                }
                $request = $s3->createPresignedRequest($cmd, '+10 minutes');
                // Get the actual presigned-url
                $presignedUrl = (string)$request->getUri();

                return $presignedUrl;
            } else {
                return false;
            }
        } catch (S3Exception $e) {
            return false;
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (\Guzzle\Http\Exception\ClientException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __getBodyObject($filePath = '', $bucketName = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;

        try {
            // Upload data.
            $result = $s3->getObject(array(
                'Bucket' => $bucketName,
                'Key' => $filePath,
            ));
            return ['success' => true, 'data' => (string)$result['Body']];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param string $filePath
     * @param string $bucketName
     * @return array
     */
    static public function __getSizeObject($filePath = '', $bucketName = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;

        try {
            // Upload data.
            $result = $s3->headObject(array(
                'Bucket' => $bucketName,
                'Key' => $filePath,
            ));
            // Print the URL to the object.
            return ['success' => true, 'data' => $result['ContentLength']];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }


    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __getBodyObjectWithMimeType($filePath = '', $bucketName = '', $fileName = '', $mimeType = '')
    {

        $presignedUrl = self::__getPresignedUrl($filePath, $bucketName, $fileName, $mimeType);

        if ($presignedUrl) {
            try {
                // Create a vanilla Guzzle HTTP client for accessing the URLs
                $http = new \GuzzleHttp\Client;
                // Get the contents of the object using the pre-signed URL
                $amazonResponse = $http->get($presignedUrl);

                return $amazonResponse->getBody();

            } catch (S3Exception $e) {
                return false;
            } catch (AwsException $e) {
                return false;
            } catch (\Guzzle\Http\Exception\ClientException $e) {
                return false;
            } catch (Exception $e) {
                return false;
            }
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __mediaExist($filePath = '', $bucketName = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        try {
            // Upload data.
            $result = $s3->doesObjectExist($bucketName, $filePath);
            // Print the URL to the object.
            return ['success' => true, 'result' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'result' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'result' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'result' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __copyMedia($fromFilePath, $toFilePath, $fromBucketName = '', $toBucketName = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($fromBucketName == '') $fromBucketName = $di->getShared('appConfig')->aws->bucket_name;
        if ($toBucketName == '') $toBucketName = $di->getShared('appConfig')->aws->bucket_name;
        try {
            // Upload data.
            $result = $s3->copyObject(array(
                'Bucket' => $fromBucketName,
                'Key' => $toFilePath,
                'CopySource' => $toBucketName . "/" . $fromFilePath
            ));
            // Print the URL to the object.
            return ['success' => true, 'result' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage(), 'awsCode' => $e->getAwsErrorCode(), 'awsMessage' => $e->getAwsErrorMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage(), 'awsCode' => $e->getAwsErrorCode(), 'awsMessage' => $e->getAwsErrorMessage()];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @return mixed
     */
    public static function __getDefaultBucket()
    {
        $di = Di::getDefault();
        return $di->getShared('appConfig')->aws->bucket_name;
    }

    /**
     * @param string $fromFilePath
     * @param string $toFilePath
     * @return array
     */
    static public function __copyMediaItem($fromFilePath, $toFilePath, $bucketName='')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        try {
            // Upload data.
            $result = $s3->copyObject(array(
                'Bucket' => $bucketName,
                'Key' => $toFilePath,
                'CopySource' => $bucketName . "/" . $fromFilePath
            ));
            // Print the URL to the object.
            return ['success' => true, 'result' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

     /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __downloadObject($filePath = '', $bucketName = '', $targetFilePath = '')
    {
        $result = self::__getBodyObject($filePath, $bucketName);
        if ($result['success'] == true) {
            try {
                $file = fopen($targetFilePath, "w+");
                fputs($file, $result['data']);
                fclose($file);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            var_dump($result);
            return false;
        }
    }

         /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __removeObject($filePath = '', $bucketName = '')
    {
        $di = Di::getDefault();
        $s3 = $di->get('aws')->createS3();
        if ($bucketName == '') $bucketName = $di->getShared('appConfig')->aws->bucket_name;

        try {
            // Upload data.
            $result = $s3->deleteObject(array(
                'Bucket' => $bucketName,
                'Key' => $filePath,
            ));
            return ['success' => true, 'data' => (string)$result['Body']];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContent
     * @return array
     */
    static public function __uploadSingleFileWithRegion($region, $fileFullName = '', $fileContent = '', $bucketName = '', $acl = self::ACL_AUTHENTICATED_READ, $contentType = '')
    {
        $di = Di::getDefault();

        $s3 = $di->get('aws')->createS3();

        if ($bucketName == '') {
            $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        }
        try {
            // Upload data.
            $uploadArray = [
                'Bucket' => $bucketName,
                'Key' => $fileFullName,
                'Body' => $fileContent,
                'ACL' => $acl
            ];

            if ($contentType != '') {
                $uploadArray['ContentType'] = $contentType;
            }
            $result = $s3->putObject($uploadArray);
            // Print the URL to the object.
            return ['success' => true, 'detail' => $result];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }

    /**
     * @param string $fileFullName
     * @param string $fileContentWithPresignedURL
     * @return array
     */
    static public function __uploadSingleFileWithPresignedUrl($fileFullName = '', $fileContent = '', $bucketName = '', $acl = self::ACL_AUTHENTICATED_READ, $contentType = '')
    {
        $di = Di::getDefault();

        if ($bucketName == ''){
            $bucketName = $di->getShared('appConfig')->aws->bucket_name;
        }

        $s3 = $di->get('aws')->createS3([
            'signature' => 'v4',
            'Bucket' => $bucketName
        ]);

        try {

            $cmd = $s3->getCommand('PutObject', [
                'Bucket' => $bucketName,
                'Key' => $fileFullName,
                'ContentType' => $contentType,
                'Body' => '',
                //'ACL' => $acl
            ]);

            $request = $s3->createPresignedRequest($cmd, '+5 minutes');
            // Get the actual presigned-url
            $presignedUrl = (string)$request->getUri();

//            $provider = Request::getProvider();
//            $provider->header->set('Accept', $contentType);
//            $response = $provider->put(
//                $presignedUrl,
//                [
//                    'file' => $fileContent,
//                ]
//            );
//
//            var_dump($response);
//            die(__METHOD__);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $presignedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type' => $contentType,
                'Content-Length' => is_array($fileContent) || is_object($fileContent) ? sizeof($fileContent) : 0
            ]);

            $result = curl_exec($ch);

            // Print the URL to the object.
            return ['success' => true, 'detail' => $presignedUrl];
        } catch (S3Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (AwsException $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => $e->getMessage()];
        }
    }
}
