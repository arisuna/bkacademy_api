<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\CompanyTypeExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class CompanyApiGatewayUsagePlan extends \Reloday\Application\Models\CompanyApiGatewayUsagePlanExt
{

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('api_gateway_usage_plan_id', 'Reloday\Gms\Models\ApiGatewayUsagePlan', 'id', [
            'alias' => 'ApiGatewayUsagePlan'
        ]);
    }
}
