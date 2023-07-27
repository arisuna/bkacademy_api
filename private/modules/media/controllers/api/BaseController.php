<?php

namespace SMXD\Media\Controllers\API;

use Aws\Exception\AwsException;
use Microsoft\Graph\Model\AssignedPlan;
use Phalcon\Acl;
use Phalcon\Exception;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HttpStatusCode;
use SMXD\Media\Controllers\ModuleApiController;
use SMXD\Media\Models\ModuleModel;
use SMXD\Media\Models\UserGroup;
use SMXD\Media\Models\UserLogin;
use SMXD\Media\Models\User;
use Intervention\Image\ImageManager;
use Phalcon\Di;
use SMXD\Application\Aws\AwsCognito\CognitoClient;
use Phalcon\Config;

/**
 * Concrete implementation of Media module controller
 *
 * @RoutePrefix("/media/api")
 */
class BaseController extends ModuleApiController
{

    /**
     * @param \Phalcon\Mvc\Dispatcher $dispatcher
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        parent::beforeExecuteRoute($dispatcher);
        $accessToken = ModuleModel::__getAccessToken();
        $refreshToken = ModuleModel::__getRefreshToken();
        if (Helpers::__isNull($accessToken)) {
            $return = [
                'success' => false,
                'message' => 'SESSION_NOT_FOUND_TEXT',
                'required' => 'login'
            ];
            $this->checkAuthMessage($return);
        }
        $return = ModuleModel::__checkAndRefreshAuthenByCognitoToken($accessToken, $refreshToken);
        if (!$return['success']) {
            $this->checkAuthMessage($return);
        }
        if ($return['success'] == true) {
            $this->response->setHeader('Token-Key', $return['accessToken']);
            $this->response->setHeader('Refresh-Token', $return['refreshToken']);
        }

    }

    /**
     *
     */
    public function checkAjax($method)
    {
        if (is_string($method)) {
            if ($method == '') $method = 'GET';
            if ($this->request->isAjax()) {
                if ($method == 'GET' && !$this->request->isGet()) {
                    exit('Restrict access');
                } elseif ($method == 'POST' && !$this->request->isPost()) {
                    exit('Restrict access');
                } elseif ($method == 'DELETE' && !$this->request->isDelete()) {
                    exit('Restrict access');
                } elseif ($method == 'PUT' && !$this->request->isPut()) {
                    exit('Restrict access');
                }
            } else {
                exit('Restrict access');
            }
        } elseif (is_array($method)) {
            if ($this->request->isAjax()) {
                if (!in_array($this->request->getMethod(), $method)) {
                    exit('Access denied');
                }
            } else {
                exit('Restrict access');
            }
        } else {
            exit('Restrict access');
        }
    }

    /**
     * @param string $msg
     * @return bool
     */
    public function checkAuthMessage($auth = [])
    {
        if ($auth['success'] == false) {
            if ($this->request->isAjax()) {
                $this->view->disable();
                $this->response->setStatusCode(HttpStatusCode::HTTP_UNAUTHORIZED, HttpStatusCode::getMessageForCode(HttpStatusCode::HTTP_UNAUTHORIZED));
                $this->response->setJsonContent([
                    'token' => isset($auth['token']) ? $auth['token'] : false,
                    'success' => false,
                    'auth' => $auth,
                    'message' => isset($auth['message']) && $auth['message'] != '' ? $auth['message'] : 'LOGIN_REQUIRED_TEXT',
                    'required' => 'login',
                ]);
                $this->response->send();
                exit();
            }
        }
        return true;
    }
}
