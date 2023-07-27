<?php

namespace SMXD\Media\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Media\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class MediaAttachmentSharing extends \SMXD\Application\Models\MediaAttachmentSharingExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}
}
