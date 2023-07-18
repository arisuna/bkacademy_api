<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class AssignmentDependant extends \Reloday\Application\Models\AssignmentDependantExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('dependant_id', 'Reloday\Gms\Models\Dependant', 'id', [
            'alias' => 'Dependant',
            'cache' => [
                'key' => 'DEPENDANT_' . $this->getDependantId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);
    }
}
