<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use phpDocumentor\Reflection\Types\This;
use Reloday\Application\Models\MediaAttachmentExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class WebhookConfiguration extends \Reloday\Application\Models\WebhookConfigurationExt
{

	public function initialize(){
		parent::initialize();
	}

	public function getChilds(){
		return self::find([
			"conditions" => "object_type = :object_type: and action != '*' and is_gms = 1",
			"bind" => [
				"object_type" => $this->getObjectType()
			]
			]);
	}

	public function getParent(){
		return self::findFirst([
			"conditions" => "object_type = :object_type: and action = '*' and is_gms = 1",
			"bind" => [
				"object_type" => $this->getObjectType()
			]
			]);
	}

}
