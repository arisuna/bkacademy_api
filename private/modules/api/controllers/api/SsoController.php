<?php
/**
 * Created by PhpStorm.
 * User: ducvuhoang
 * Date: 24/08/2021
 * Time: 11:34
 */

namespace SMXD\Api\Controllers\API;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Application\Lib\SamlHelper;

class SsoController extends ModuleApiController
{
    public function spMetadataAction()
    {
        $spSsoDescriptor = SamlHelper::__generateSpMetadata();
        $metadata = SamlHelper::__serializeMetadataToXml($spSsoDescriptor);
        $this->response->setHeader('Content-Type', 'application/xml');
        $this->response->setContent($metadata);
        return $this->response->send();
    }
}