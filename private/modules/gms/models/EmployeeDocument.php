<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class EmployeeDocument extends \Reloday\Application\Models\EmployeeDocumentExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee',
            'cache' => [
                'key' => 'EMPLOYEE_' . $this->getId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ]
        ]);
    }
}
