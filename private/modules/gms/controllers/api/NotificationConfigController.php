<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\NotificationGroup;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\CompanyNotificationSetting;
use Reloday\Gms\Models\Notification;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class NotificationConfigController extends BaseController
{
    /**
     * Init the controller
     */
    public function initializer()
    {
        $this->view->disable();
    }

    /**
     * @Route("/notificationconfig", paths={module="gms"}, methods={"GET"}, name="gms-notificationconfig-index")
     */
    public function initAction()
    {
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_NOTIFICATION_CONFIG);

        $notifications = Notification::findByIsGms(Helpers::YES);
        $hasTransaction = false;
        $this->db->begin();
        foreach ($notifications as $notification) {
            $notificationSetting = CompanyNotificationSetting::__findByNotificationId($notification->getId());
            if (!$notificationSetting) {
                $hasTransaction = true;
                $resultCreate = CompanyNotificationSetting::__createFromNotification($notification);
                if ($resultCreate['success'] == false) {
                    $return = ['success' => true];
                    $this->db->rollback();
                    goto end;
                }
            }
        }
        if ($hasTransaction == true) {
            $this->db->commit();
        }
        $return = ['success' => true];
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getGroupListAction()
    {
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_NOTIFICATION_CONFIG);

        $groups = NotificationGroup::__findListGms();
        $this->response->setJsonContent(['success' => true, 'data' => $groups]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_NOTIFICATION_CONFIG);
        $groups = CompanyNotificationSetting::__getCurrentList();
        $this->response->setJsonContent(['success' => true, 'data' => $groups]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getEventsListAction()
    {
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_NOTIFICATION_CONFIG);
        $notifications = Notification::findByIsGms(Helpers::YES);
        $this->response->setJsonContent(['success' => true, 'data' => $notifications]);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function saveSettingAction($id = '')
    {
        $this->checkAjaxPutPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_NOTIFICATION_CONFIG);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $notification_id = Helpers::__getRequestValue('notification_id');
        $isReporter = Helpers::__getRequestValue('is_reporter');
        $isOwner = Helpers::__getRequestValue('is_owner');
        $isViewer = Helpers::__getRequestValue('is_viewer');
        $isCurrentUser = Helpers::__getRequestValue('is_current_user');

        if (Helpers::__isValidId($id)) {
            $notificationSetting = CompanyNotificationSetting::findFirst($id);
            if (!$notificationSetting || $notificationSetting->belongsToGms() == false) {
                $notificationSetting = new CompanyNotificationSetting();
                $notificationSetting->setNotificationId($notification_id);
                $notificationSetting->setCompanyId(ModuleModel::$company->getId());
            }
        } else {
            $notificationSetting = new CompanyNotificationSetting();
            $notificationSetting->setNotificationId($notification_id);
            $notificationSetting->setCompanyId(ModuleModel::$company->getId());
        }


        if (($isCurrentUser === Helpers::YES || $isCurrentUser === Helpers::NO) && $notificationSetting->getNotification()->getNotificationGroup()->getEntityName() != NotificationGroup::EMPLOYEE_GROUP) {
            $notificationSetting->setIsCurrentUser($isCurrentUser);
        }

        if ($isOwner === Helpers::YES || $isOwner === Helpers::NO) {
            $notificationSetting->setIsOwner($isOwner);
        }

        if ($isViewer === Helpers::YES || $isViewer === Helpers::NO) {
            $notificationSetting->setIsViewer($isViewer);
        }

        if ($isReporter === Helpers::YES || $isReporter === Helpers::NO) {
            $notificationSetting->setIsReporter($isReporter);
        }

        $return = $notificationSetting->__quickUpdate();
        if ($return['success'] == true) {
            $this->clearCache();
            $return['message'] = 'NOTIFICATION_SETTING_SAVE_SUCCESS_TEXT';
        } else {
            $return['message'] = 'NOTIFICATION_SETTING_SAVE_FAIL_TEXT';
        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @param $id
     */
    public function bulkChangeAction()
    {
        $this->checkAjaxPutPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_NOTIFICATION_CONFIG);
        $hasTransaction = false;

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $notificationSettingIds = Helpers::__getRequestValueAsArray('ids');
        $isReporter = Helpers::__getRequestValue('is_reporter');
        $isOwner = Helpers::__getRequestValue('is_owner');
        $isViewer = Helpers::__getRequestValue('is_viewer');
        $isCurrentUser = Helpers::__getRequestValue('is_current_user');

        if (count($notificationSettingIds) > 0) {
            $this->db->begin();
            foreach ($notificationSettingIds as $notificationSettingId) {

                //$notificationSetting = CompanyNotificationSetting::findFirstById($notificationSettingId);

                $notificationSetting = CompanyNotificationSetting::findFirst([
                    'conditions' => 'notification_id = :notification_id: AND company_id = :company_id:',
                    'bind' => [
                        'company_id' => ModuleModel::$company->getId(),
                        'notification_id' => $notificationSettingId
                    ]
                ]);

                if (!$notificationSetting || $notificationSetting->belongsToGms() == false) {
                    $notificationSetting = new CompanyNotificationSetting();
                    $notificationSetting->setCompanyId(ModuleModel::$company->getId());
                    $notificationSetting->setNotificationId($notificationSettingId);
                }

                if (($isCurrentUser === Helpers::YES || $isCurrentUser === Helpers::NO) && $notificationSetting->getNotification()->getNotificationGroup()->getEntityName() != NotificationGroup::EMPLOYEE_GROUP) {
                    $notificationSetting->setIsCurrentUser($isCurrentUser);
                }

                if ($isOwner === Helpers::YES || $isOwner === Helpers::NO) {
                    $notificationSetting->setIsOwner($isOwner);
                }

                if ($isViewer === Helpers::YES || $isViewer === Helpers::NO) {
                    $notificationSetting->setIsViewer($isViewer);
                }

                if ($isReporter === Helpers::YES || $isReporter === Helpers::NO) {
                    $notificationSetting->setIsReporter($isReporter);
                }
                $hasTransaction = true;
                $return = $notificationSetting->__quickSave();
                if ($return['success'] == false) {
                    $return['message'] = 'NOTIFICATION_SETTING_SAVE_FAIL_TEXT';
                    $this->db->rollback();
                    goto end;
                }
            }
        }

        if ($hasTransaction == true) {
            $this->db->commit();
            $this->clearCache();
        }
        $return = [
            'success' => true,
            'message' => 'NOTIFICATION_SETTING_SAVE_SUCCESS_TEXT'
        ];

        end:
        $return['ids'] = $notificationSettingIds;
        $this->response->setJsonContent($return);
        return $this->response->send();

    }


    private function clearCache(){
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        $redis->flushDb();
        $redis->flushAll();
    }
}
