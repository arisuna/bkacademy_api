<?php

namespace SMXD\Media\Controllers\API;

use SMXD\Media\Controllers\ModuleApiController;

use SMXD\Media\Models\MediaType;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\Media;
use SMXD\Media\Models\MediaAttachment;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Intervention\Image\ImageManagerStatic as Image;
use SMXD\Application\Queue\MediaFileQueue;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;

use SMXD\Application\Lib\SMXDMediaHelper;
use SMXD\Application\Lib\SMXDS3Helper;
use SMXD\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ItemController extends ModuleApiController
{

    /**
     * load file from token and uuid
     * need to load file with protection from amazon pdf
     * @return [type]
     */
    public function viewContentAction()
    {
        $this->view->disable();
        $token = $this->dispatcher->getParam('token');
        $uuid = $this->dispatcher->getParam('uuid');
        $name = $this->dispatcher->getParam('name');
        $params = $this->dispatcher->getParams();
        $token = base64_decode($token);

        if ($token == '' || $uuid == '' || !Helpers::__isValidUuid( $uuid ) || $name == '') {
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

        $mediaItem = new SMXDMediaHelper();
        $mediaItem->setMediaUuid( $uuid );
        $result = $mediaItem->getMediaFromDynamoDb();


        if( $result['success'] == true ){

            $presignedUrl = $mediaItem->getPresinedUrl();

            // Create a vanilla Guzzle HTTP client for accessing the URLs
            $http = new \GuzzleHttp\Client;

            // > 403
            try {
                // Get the contents of the object using the pre-signed URL
                $amazonResponse = $http->get($presignedUrl);
                $this->response->setContentType($mediaItem->getMimeType());
                $this->response->setContent($amazonResponse->getBody());
                return $this->response->send();
            } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                $amazonResponse = $e->getResponse();
                $this->response->setStatusCode(403, $amazonResponse);
                return $this->response->send();
            }
        }
    }

    /**
     * load file from token and uuid
     * need to load file with protection from amazon pdf
     * @return [type]
     */
    public function downloadContentAction()
    {
        $this->view->disable();
        $token = $this->dispatcher->getParam('token');
        $uuid = $this->dispatcher->getParam('uuid');
        $name = $this->dispatcher->getParam('name');
        $params = $this->dispatcher->getParams();
        $token = base64_decode($token);

        if ($token == '' || $uuid == '' || !Helpers::__isValidUuid( $uuid ) || $name == '') {
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

        $mediaItem = new SMXDMediaHelper();
        $mediaItem->setMediaUuid( $uuid );
        $result = $mediaItem->getMediaFromDynamoDb();

        if( $result['success'] == true ){
            $presignedUrl = $mediaItem->getPresinedUrl();
            // Create a vanilla Guzzle HTTP client for accessing the URLs
            $http = new \GuzzleHttp\Client;

            // > 403
            try {
                $this->response->setContentType($mediaItem->getMimeType());
                $fileName = $mediaItem->getNameStatic() != '' ? $mediaItem->getNameStatic() : $mediaItem->getName();
                $fileName = urlencode($fileName);
                $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $fileName . "." . $mediaItem->getFileExtension() . '"');
                header('Location: ' . $presignedUrl);
            } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                $amazonResponse = $e->getResponse();
                $this->response->setStatusCode(403, $amazonResponse);
                return $this->response->send();
            }
        } else {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'fail'
            ]);
        }
    }
    /**
     * [thumbnailAction description]
     * @return [type] [description]
     */
    public function getThumbContentAction()
    {
        $this->view->disable();
        $token = $this->dispatcher->getParam('token');
        $uuid = $this->dispatcher->getParam('uuid');
        $name = $this->dispatcher->getParam('name');
        $params = $this->dispatcher->getParams();
        $token = base64_decode($token);

        if ($token == '' || $uuid == '' || !Helpers::__isValidUuid( $uuid ) || $name == '') {
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

        $mediaItem = new SMXDMediaHelper();
        $mediaItem->setMediaUuid( $uuid );
        $result = $mediaItem->getMediaFromDynamoDb();

        if( $result['success'] == true ){

            $publicUrl = $mediaItem->getThumbPublicUrl();

            // Create a vanilla Guzzle HTTP client for accessing the URLs
            // > 403
            try {
                $this->response->setContentType($mediaItem->getMimeType());
                $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $mediaItem->getFileName() . "." . $mediaItem->getFileExtension() . '"');
                header('Location: ' . $publicUrl);
            } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                $amazonResponse = $e->getResponse();
                $this->response->setStatusCode(403, $amazonResponse);
                return $this->response->send();
            }
        } else {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'fail'
            ]);
        }



    }
}
