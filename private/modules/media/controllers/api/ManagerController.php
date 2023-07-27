<?php

namespace SMXD\Media\Controllers\API;

use SMXD\Media\Models\MediaType;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\Media;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Intervention\Image\ImageManagerStatic as Image;
use SMXD\Application\Queue\MediaFileQueue;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use SMXD\Media\Models\MediaAttachment;
/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ManagerController extends BaseController
{


}
