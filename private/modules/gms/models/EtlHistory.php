<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\CompanyTypeExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class EtlHistory extends \Reloday\Application\Models\EtlHistoryExt
{

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
    }
}
