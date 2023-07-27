<?php

namespace SMXD\Media\Controllers\API;

use SMXD\Application\Lib\SMXDLetterImage;
use \SMXD\Media\Controllers\ModuleApiController;
use \SMXD\Application\Lib\Helpers;
use SMXD\Media\Models\MediaType;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\Media;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Intervention\Image\ImageManagerStatic as Image;
use SMXD\Application\Queue\MediaFileQueue;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use SMXD\Media\Models\MediaAttachment;
use SMXD\Media\Models\ObjectAvatar;
use SMXD\Media\Models\User;
use SMXD\Media\Models\Employee;

/**
 * Concrete implementation of Media module controller
 *
 * @RoutePrefix("/media/api")
 */
class DirectController extends ModuleApiController
{
    /**
     * @Route("/direct", paths={module="media"}, methods={"GET"}, name="media-direct-index")
     */
    public function indexAction()
    {

    }

    /**
     * @return mixed
     */
    public function getAvatarThumbAction($uuid)
    {
        $return = ['success' => false, 'data' => '', 'uuid' => $uuid];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

//            $avatar = MediaAttachment::__getAvatarAttachment($uuid);
            $avatar = ObjectAvatar::__getImageByObjectUuid($uuid);

            if ($avatar) {
                $createAtInSecond = Helpers::__convertDateToSecond($avatar->getCreatedAt());
                if (time() - $createAtInSecond >= 86400) {
                    $url = $avatar->getThumbCloudFrontUrl();
                } else {
                    $url = $avatar->getUrlThumb();
                    if ($url == false){
                        $url = $avatar->getThumbCloudFrontUrl();
                        if ($url == false){
                            goto else_function;
                        }
                    }
                }

                $this->response->setContentType($avatar->getMimeType());
                $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $avatar->getName() . "." . $avatar->getFileExtension() . '"');
                header('Location: ' . $url);
            } else {
                else_function:
                $user = User::findFirstByUuid($uuid);
                $name = "RELOTALENT";
                if ($user) {
                    $name = $user->getFirstname() . " " . $user->getLastname();
                } else {
                    $employee = Employee::findFirstByUuid($uuid);
                    if ($employee) {
                        $name = $employee->getFirstname() . " " . $employee->getLastname();
                    }
                }
                $avatar = new SMXDLetterImage($name, 'circle', 64);
                $avatar->setColorInternal();
                $content = $avatar->__toString();
                $this->response->setContentType(Media::MIME_TYPE_PNG);
                $this->response->setContent(base64_decode(explode(',', $content)[1]));
            }
        } else {
            $name = "RELOTALENT";
            $avatar = new SMXDLetterImage($name, 'circle', 64);
            $avatar->setColorInternal();
            $content = $avatar->__toString();
            $this->response->setContentType(Media::MIME_TYPE_PNG);
            $this->response->setContent(base64_decode(explode(',', $content)[1]));
        }
        return $this->response->send();
    }
}
