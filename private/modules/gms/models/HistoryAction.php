<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class HistoryAction extends \Reloday\Application\Models\HistoryActionExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    protected $companyNotificationSetting;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('email_template_default_id', 'Reloday\Application\Models\EmailTemplateDefaultExt', 'id', [
            'alias' => 'EmailTemplateDefault'
        ]);

        $this->belongsTo('notification_id', 'Reloday\Gms\Models\Notification', 'id', [
            'alias' => 'Notification'
        ]);
    }

    /**
     * @return null
     */
    public function getMyCompanyNotificationSetting()
    {
        if ($this->companyNotificationSetting == null) {
            $this->companyNotificationSetting = $this->getNotification() ? $this->getNotification()->getMyCompanySetting() : null;
        }
        return $this->companyNotificationSetting;
    }

    /**
     * @return bool
     */
    public function canNotifyOwner()
    {
        return $this->getMyCompanyNotificationSetting() ? $this->getMyCompanyNotificationSetting()->getIsOwner() == ModelHelper::YES : false;
    }

    /**
     * @return bool
     */
    public function canNotifyReporter()
    {
        return $this->getMyCompanyNotificationSetting() ? $this->getMyCompanyNotificationSetting()->getIsReporter() == ModelHelper::YES : false;
    }

    /**
     * @return bool
     */
    public function canNotifyCurrentUser()
    {
        return $this->getMyCompanyNotificationSetting() ? $this->getMyCompanyNotificationSetting()->getIsCurrentUser() == ModelHelper::YES : false;
    }

    /**
     * @return bool
     */
    public function canNotifyViewer()
    {
        return $this->getMyCompanyNotificationSetting() ? $this->getMyCompanyNotificationSetting()->getIsViewer() == ModelHelper::YES : false;
    }
}
