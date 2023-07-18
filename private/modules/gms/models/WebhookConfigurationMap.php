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

class WebhookConfigurationMap extends \Reloday\Application\Models\WebhookConfigurationMapExt
{

	public function initialize(){
		parent::initialize();
        $this->belongsTo('webhook_configuration_id', 'Reloday\Gms\Models\WebhookConfiguration', 'id', [
            'alias' => 'WebhookConfiguration',
            'reusable' => false
        ]);
	}

}
