<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ObjectAvatar extends \SMXD\Application\Models\ObjectAvatarExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    /** @var [varchar] [url of thumbnail] */
    public $url_thumb;

    public function initialize()
    {
        parent::initialize();
    }

    public function getUrlThumb()
    {
        $this->url_thumb = $this->getPresignedS3Url();
        return $this->url_thumb;
    }

    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getImageByUuidAndType($uuid, $type, $returnType = 'object')
    {
        $image = self::__getLastestImage($uuid, $type, $returnType);
        if ($image) {
            return $image;
        } else {
            return null;
        }
    }
}
