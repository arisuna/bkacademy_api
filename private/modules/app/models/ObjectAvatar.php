<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class ObjectAvatar extends \SMXD\Application\Models\ObjectAvatarExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
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
