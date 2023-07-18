<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Gms\Models\MediaType;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Media;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Reloday\Application\Lib\RelodayMediaHelper;

use Intervention\Image\ImageManagerStatic as Image;
use Reloday\Application\Queue\MediaFileQueue;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Reloday\Gms\Models\MediaAttachment;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MediaController extends BaseController
{
    const CONTROLLER_NAME = "media";

    /**
     * @Route("/media", paths={module="gms"}, methods={"GET"}, name="gms-media-index")
     */
    public function indexAction()
    {
        die(__FUNCTION__);

    }

    /**
     * @param $uuid
     */
    public function getListByUuidAction($uuid)
    {

    }

    /**
     *
     */
    public function testAction(){
        die(__METHOD__);
    }

    /**
     *
     */
    public function uploadAction()
    {

    }

    /**
     *
     */
    public function downloadAction()
    {

    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getSizeAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $size = Media::sum([
            'column' => 'size',
            'conditions' => 'user_profile_uuid = :user_profile_uuid',
            'bins' => ['user_profile_uuid' => ModuleModel::$user_profile->getUuid(),]
        ]);

        $return = [
            'success' => true,
            'size' => $size,
            'sizeHuman' => Helpers::__formatBytes($size, 2)
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function makePublicAction($uuid = "")
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex(self::CONTROLLER_NAME);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $media = Media::findFirstByUuid($uuid);
            if ($media && $media->belongsToCurrentUserProfile()) {
                $return = $media->makePublic();
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        if (!$uuid) {
            goto end_of_function;
        }

        $media = Media::findFirstByUuid($uuid);
        if (!$media) {
            goto end_of_function;
        }

        $return = [
            'success' => true,
            'data' => $media->getParsedData()
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
