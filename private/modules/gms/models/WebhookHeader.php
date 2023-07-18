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

class WebhookHeader extends \Reloday\Application\Models\WebhookHeaderExt
{

	public function initialize(){
		parent::initialize();

        $this->belongsTo('webhook_id', 'Reloday\Gms\Models\Webhook', 'id', [
            'alias' => 'Webhook',
            'reusable' => false
        ]);
	}

}
