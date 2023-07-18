<?php
/**
 * Created by PhpStorm.
 * User: ducvuhoang
 * Date: 24/08/2021
 * Time: 11:34
 */

namespace Reloday\Api\Controllers\API;

use Reloday\Api\Controllers\ModuleApiController;
use Reloday\Application\Lib\SamlHelper;

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