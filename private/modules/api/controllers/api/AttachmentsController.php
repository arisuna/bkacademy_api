<?php

namespace SMXD\Api\Controllers\API;

use SMXD\App\Controllers\ModuleApiController;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Api\Models\MediaAttachment;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Models\MediaAttachmentExt;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AttachmentsController extends ModuleApiController
{
    const CONTROLLER_NAME = 'attachments';

    public function getAttachmentAction(): \Phalcon\Http\ResponseInterface
    {
        $this->view->disable();
        $this->checkAjax(['POST']);
        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $is_thumb = Helpers::__getRequestValue('is_thumb');

        if ($is_thumb == MediaAttachmentExt::IS_THUMB_YES) {
            $image = MediaAttachment::__getImageByObjUuidAndIsThumb($uuid, MediaAttachmentExt::IS_THUMB_YES);
        } else {
            $image = MediaAttachment::__getImageByObjUuidAndType($uuid, $type);
        }

        $result = ['success' => false, 'data' => '', 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($image) {
            $img = $image->toArray();

            $img['image_data']['url_thumb'] = $image->getTemporaryThumbS3Url();

            $result = [
                'success' => true,
                'data' => $img
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * List attachment of object
     * @param string $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function listAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid == '') $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $return = MediaAttachment::__findWithFilter([
                'limit' => 20,
                'object_uuid' => $uuid,
                'object_name' => $type,
            ], $ordersConfig);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
