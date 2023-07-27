<?php

namespace SMXD\Media\Controllers\API;

use \SMXD\Media\Controllers\ModuleApiController;
use SMXD\Media\Models\MediaAttachment;
use SMXD\Media\Models\Media;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class BackendAttachmentsController extends ModuleApiController
{
    /**
     * @Route("/attachments", paths={module="gms"}, methods={"GET"}, name="gms-attachments-index")
     */
    public function indexAction()
    {

    }

    /**
     * remove an attachment from data
     * @return [type] [description]
     */
    public function removeAction()
    {
        $this->view->disable();
        $data = $this->request->getJsonRawBody();

        $object_uuid = isset($data->object_uuid) ? $data->object_uuid : null;
        $object_name = isset($data->object_name) ? $data->object_name : null;
        $media_id = isset($data->media_id) ? $data->media_id : null;

        if (is_null($object_uuid) || is_null($media_id)) {
            $return = ['success' => false, 'msg' => 'PARAMS_NOT_ENOUGH', 'data' => $data];
            goto end_of_function;
        }


        $media = MediaAttachment::find([
            "conditions" => "object_uuid = :object_uuid: AND media_id = :media_id:",
            "bind" => [
                "object_uuid" => $object_uuid,
                "media_id" => $media_id
            ]]);

        if ($media) {
            $media->delete();
            $return = ['success' => true, 'msg' => 'ATTACHMENT_DELETE', 'data' => $data];
            goto end_of_function;
        } else {
            $return = ['success' => false, 'msg' => 'DATA_NOT_FOUND', 'data' => $data];
        }
        goto end_of_function;

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }
}
