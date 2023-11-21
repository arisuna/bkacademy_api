<?php

namespace SMXD\Application\Lib;


use Mpdf\Cache;
use SMXD\Application\Models\Acl;
use SMXD\Application\Models\AclExt;
use SMXD\Application\Models\WebAclExt;
use SMXD\Application\Models\CompanyTypeExt;
use SMXD\Application\Models\ModuleExt;
use SMXD\Application\Models\ObjectMap;
use SMXD\Application\Models\ObjectMapExt;
use SMXD\Application\Models\PlanExt;
use SMXD\Application\Models\SubscriptionAclExt;
use SMXD\Application\Models\SubscriptionExt;
use SMXD\Application\Models\StaffUserGroup;
use SMXD\Application\Models\UserGroupAclCompanyExt;
use SMXD\Application\Models\StaffUserGroupAclExt;
use SMXD\Application\Models\StaffUserGroupExt;
use SMXD\Application\Lib\HistoryModel;

class AclHelper
{

    const ACTION_CREATE = 'create';
    const ACTION_DELETE = 'delete';
    const ACTION_EDIT = 'edit';
    const ACTION_UPDATE = 'update';
    const ACTION_INDEX = 'index';
    const ACTION_MANAGE = 'manage';
    const ACTION_DELETE_OWN = 'delete_own';
    const ACTION_UPLOAD = 'upload';
    const ACTION_CHANGE_STATUS = 'change_status';
    const ACTION_MANAGE_DOCUMENTS = 'manage_documents';
    const ACTION_MANAGE_TASK = 'manage_task';
    const ACTION_LOGIN = 'login';
    const ACTION_MANAGE_DEPENDANT = 'manage_dependant';
    const ACTION_RELOAD = 'reload';
    const ACTION_RESET = 'reset';
    const ACTION_REPORT = 'report';
    const ACTION_DOWNLOAD = 'download';
    const ACTION_CHANGE_VIEWER = 'change_viewer';
    const ACTION_CHANGE_REPORTER = 'change_reporter';
    const ACTION_CHANGE_OWNER = 'change_owner';
    const ACTION_VIEW = 'view';
    const ACTION_CONFIG = 'config';
    const ACTION_USE = 'use';
    const ACTION_APPLY = 'config';

    const CONTROLLER_ADMIN = 'admin';
    const CONTROLLER_USER = 'user';
    const CONTROLLER_CRM_USER = 'crm_user';
    const CONTROLLER_PRODUCT = 'product';

    const CONTROLLER_END_USER = 'end_user';

    static $user;

    /**
     * @param $user
     */
    public static function __setUser($User)
    {
        self::$user = $User;
    }

    /**
     * @param string $controller
     * @param string $action
     * @return array
     * //TODO check permission use JWT in the future
     */
    public static function __canAccessResource($controller, $action)
    {
        // Check user in group
        if (!is_object(self::$user) || empty(self::$user) || !method_exists(self::$user, 'getUserGroupId')) {
            return [
                'success' => false,
                'message' => 'TOKEN_EXPIRED_TEXT',
                'method' => __METHOD__,
            ];
        }


        $result = self::__checkPermissionDetail(
            $controller ? $controller : '',
            $action ? $action : '',
            self::$user->getCompany() ? self::$user->getCompany()->getCompanyTypeId() : 0
        );
        return $result;

    }

    /**
     * @param string $controller
     * @param string $action
     * @return array
     */
    public static function __checkPermission($controller = '', $action = '')
    {
        $hasPermission = false;
        $list_permission = self::__loadListPermission();

        if ($list_permission != null && is_array($list_permission) && count($list_permission) > 0) {
            if (!isset($list_permission[$controller]) || !isset($list_permission[$controller][$action])) {
                $hasPermission = false;
            } else {
                if (isset($list_permission[$controller]) && isset($list_permission[$controller][$action])) {
                    $hasPermission = true;
                }
            }
            foreach ($list_permission as $key_controller => $v_action_list) {
                if ($controller == $key_controller) {
                    if (is_array($v_action_list)) {
                        foreach ($v_action_list as $act) {
                            if ($act == $action) {
                                $hasPermission = true;
                            }
                        }
                    }
                    break;
                }
            }
            return [
                'success' => $hasPermission,
                'aclDetails' => $controller . "/" . $action,
                'message' => $hasPermission ? 'YOU_HAVE_PERMISSION_ACCESSED_TEXT' : 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'
            ];
        } else {
            return [
                'success' => false,
                'aclDetails' => $controller . "/" . $action,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'
            ];
        }
    }

    /**
     * @return array|bool
     */
    public static function __checkPermissionDetailAdmin($controller, $action)
    {
        return self::__checkPermissionDetail($controller, $action);
    }

    /**
     * @param $controller
     * @param $action
     * @return array|bool
     */
    public static function __checkPermissionDetail($controller, $action, $companyTypeId = 0)
    {
        $permission = self::__loadPermission($controller, $action, $companyTypeId);
        if (isset($permission['id']) && isset($permission['accessible']) && $permission['accessible'] == true) {
            return [
                'success' => true,
                'data' => $permission,
                'controller' => $controller,
                'action' => $action,
                'message' => 'YOU_HAVE_PERMISSION_ACCESSED_TEXT',
            ];
        }
        return [
            'success' => false,
            'data' => $permission,
            'controller' => $controller,
            'action' => $action,
            'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'
        ];
    }

    /**
     * @param $controller
     * @param $action
     * @return bool
     */
    public static function __loadSinglePermission($controller, $action, $companyTypeId = 0)
    {

        $aclItem = false;
        if ($companyTypeId == CompanyTypeExt::TYPE_GMS) {
            $aclItem = AclExt::__findGmsAcl($controller, $action);
        } elseif ($companyTypeId == CompanyTypeExt::TYPE_HR) {
            $aclItem = AclExt::__findHrAcl($controller, $action);
        }

        if (!$aclItem) {
            return ['accessible' == false];
        }

        $cacheManager = CacheHelper::__getCacheManager();
        $cacheName = CacheHelper::__getAclCacheByGroupAclName(
            self::$user->getUserGroupId(),
            $aclItem->getId()
        );

        $subscription = SubscriptionExt::findFirstByCompanyId(self::$user->getCompanyId());
        if (!$subscription) {
            return ['accessible' => false];
        }

        $return = CacheHelper::__getCacheValue($cacheName);
        if (is_array($return) && $return) {
            return $return;
        }

        $aclItem = SubscriptionExt::__loadSinglePermission(self::$user, $controller, $action);

        if ($aclItem) {
            $aclArrayItem = $aclItem->toArray();
            $aclArrayItem['accessible'] = true;
            $cacheManager->save($cacheName, $aclArrayItem, CacheHelper::__TIME_24H);
            return $aclArrayItem;
        }

        $cacheManager->save($cacheName, ['accessible' => false], CacheHelper::__TIME_24H);
        return ['accessible' => false];
    }

    /**
     * @param $controller
     * @param $action
     * @return bool
     */
    public static function __loadPermission($controller, $action, $companyTypeId = 0)
    {
        $aclItem = false;
        $user = self::$user;
        if ($user->isEndUser()) {
            $aclItem = WebAclExt::__findWebAcl($controller, $action);
        } else {
            if (self::$user->getUserGroupId() != StaffUserGroupExt::GROUP_ADMIN) {
                $aclItem = AclExt::__findCrmAcl($controller, $action);
            } else{
                $aclItem = AclExt::__findAdminAcl($controller, $action);
            }
        }

        if (!$aclItem) {
            Helpers::__createException("aclNotFound");
            return [
                'accessible' => false,
                'errorType' => 'aclNotFound'
            ];
        }

        $cacheManager = CacheHelper::__getCacheManager();
        $cacheName = CacheHelper::__getAclCacheByGroupAclName(
            self::$user->getUserGroupId(),
            $aclItem->getId()
        );

        $return = CacheHelper::__getCacheValue($cacheName);
        if (is_array($return) && $return) {
            return $return;
        } elseif ($return == false) {
            $return = [
                'accessible' => false,
                'cacheName' => $cacheName,
                'errorType' => 'cacheEmpty'
            ];
            //do nothing
           
        }
        $acl_ids = [];
        $acls = self::$user->__loadListPermission();

        if (count($acls) > 0) {
            foreach ($acls as $acl) {
                $acl_ids[$acl->getId()] = $acl;
            }
        }

        if (isset($acl_ids[$aclItem->getId()]) && ($acl_ids[$aclItem->getId()] instanceof AclExt ||  $acl_ids[$aclItem->getId()] instanceof WebAclExt)) {
            $aclArrayItem = $aclItem->toArray();
            $aclArrayItem['accessible'] = true;
            $aclArrayItem['cacheName'] = $cacheName;
            $cacheManager->save($cacheName, $aclArrayItem, CacheHelper::__TIME_1H);
            return $aclArrayItem;
        } else {
            $cacheManager->save($cacheName, ['accessible' => false, 'errorType' => 'notAccessible', 'cacheName' => $cacheName], CacheHelper::__TIME_1H);
            return ['accessible' => false, 'errorType' => 'notAccessible', 'cacheName' => $cacheName];
        }
    }

    /**
     *
     */

    public static function __loadListPermission()
    {
        $user = self::$user;
        $cacheManager = \Phalcon\DI\FactoryDefault::getDefault()->getShared('cache');
        $cacheName = CacheHelper::getAclCacheByGroupName($user->getUserGroupId());
//        $permissions = $cacheManager->get($cacheName, getenv('CACHE_TIME'));
        $permissions = null;
        //1. load from JWT

        if (!is_null($permissions) && is_array($permissions) && count($permissions) > 0) {
            return ($permissions);
        }
        $subscription = SubscriptionExt::findFirstByCompanyId(self::$user->getCompanyId());
        if (!$subscription instanceof SubscriptionExt) {
            if (self::$user->getUserGroupId() != StaffUserGroupExt::GMS_ADMIN
                && self::$user->getUserGroupId() != StaffUserGroupExt::HR_ADMIN) {
                return $permissions;
            } else {
                $permissions = [
                    "admin" => ["index"],
                    "subscription" => ["index"]
                ];
                $cacheManager->save($cacheName, $permissions, CacheHelper::__TIME_1H);
                return $permissions;
            }
        }
        $acl_list = $subscription->__loadListPermission(self::$user);

        if (isset($acl_list) && count($acl_list)) {
            $acl_list = $acl_list->toArray();
            foreach ($acl_list as $item) {
                if (!isset($permissions[$item['controller']])) {
                    $permissions[$item['controller']] = [];
                }
                $permissions[$item['controller']][] = $item['action'];
                if (!$item['status']) continue;
            }
        }


        $cacheManager->save($cacheName, $permissions, CacheHelper::__TIME_1H);
        return ($permissions);

    }

    /**
     * @param $action
     * @param $controller
     * @param $uuid
     */
    public static function __getControllerByUuid($object_uuid)
    {
        $typeObject = RelodayObjectMapHelper::__getHistoryTypeObject($object_uuid);
        $controller = "";
        if ($typeObject == HistoryModel::TYPE_TASK) {
            $controller = self::CONTROLLER_TASK;
        } elseif ($typeObject == HistoryModel::TYPE_ASSIGNMENT) {
            $controller = self::CONTROLLER_ASSIGNMENT;
        } elseif ($typeObject == HistoryModel::TYPE_RELOCATION) {
            $controller = self::CONTROLLER_RELOCATION;
        } elseif ($typeObject == HistoryModel::TYPE_SERVICE) {
            $controller = self::CONTROLLER_RELOCATION_SERVICE;
        }
        return $controller;
    }
}
