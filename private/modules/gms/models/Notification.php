<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use PhpParser\Node\Expr\AssignOp\Mod;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class Notification extends \Reloday\Application\Models\NotificationExt
{

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('notification_group_id', 'Reloday\Gms\Models\NotificationGroup', 'id', [
            'alias' => 'NotificationGroup'
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\CompanyNotificationSetting', 'notification_id', [
            'alias' => 'CompanyNotificationSettings'
        ]);
    }

    /**
     * @return mixed
     */
    public function getMyCompanySetting()
    {
        return $this->getCompanyNotificationSettings([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId()
            ]
        ])->getFirst();
    }
}
