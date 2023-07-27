<?php

namespace SMXD\Media\Controllers;

use SMXD\Application\Controllers\ApplicationApiController;

use Phalcon\Acl;
use Phalcon\Exception;
use Phalcon\Http\Request;
use SMXD\Application\Lib\Helpers;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\UserGroup;
use SMXD\Media\Models\UserLogin;
use SMXD\Media\Models\User;

/**
 * Base class of Media module API controller
 */
class ModuleApiController extends ApplicationApiController
{
    /**
     * check beforeExecute all route
     * @param  \Phalcon\Mvc\Dispatcher $dispatcher
     * @return [null]
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkPrelightRequest();
        try {
            $tokenBasic64 = $this->dispatcher->getParam('token'); // Get token in url
            $tokenBasic64 = $tokenBasic64 == null || $tokenBasic64 == '' ? Helpers::__getRequestValue('token') : $tokenBasic64;
            if (Helpers::__isBase64($tokenBasic64)) {
                $accessToken = base64_decode($tokenBasic64, true);
            } else {
                $accessToken = $tokenBasic64;
            }
            if ($accessToken != '') {
                $auth = ModuleModel::__checkAuthenByAccessToken($accessToken);
                if (!$auth['success']) {

                } else {
                    $this->check_persmission($auth);
                }
            }
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param \Phalcon\Mvc\Dispatcher $dispatcher
     */
    public function afterExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->applyCrossDomainHeader();
    }

    /**
     * check persmission of viewer // check permission of viewer with the MEDIA( each media has a list of viewer )
     * @param  [type]
     * @return [type]
     */
    public function check_persmission($auth)
    {
        if ($auth['success'] == true) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function checkAuthToken($token)
    {
        $auth = ModuleModel::checkAuth($token, $this->config);
        if (!$auth['success']) {
            return $this->dispatcher->forward([
                'controller' => 'index',
                'action' => 'expire'
            ]);
        }
    }
}