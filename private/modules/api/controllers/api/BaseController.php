<?php

namespace SMXD\Api\Controllers\API;

use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HttpStatusCode;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\ModuleModel;

/**
 * Base class of App module API controller
 */
class BaseController extends ModuleApiController
{
    /**
     * @var language key of Controller
     */
    public $language_key;

    /**
     * before execute controller/action
     * @param \Phalcon\Mvc\Dispatcher $dispatcher
     * @throws \Exception
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkMaintenanceStatus();
        /** check prelight request of AWS */
        $this->checkPrelightRequest();
        /** check access request of AWS */
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

        $return = ModuleModel::__checkAndRefreshAuthenByToken($accessToken, $refreshToken);
        if (!$return['success']) {
            $this->checkAuthMessage($return);
        }
        if ($return['success'] == true) {
            $this->response->setHeader('Token-Key', $return['accessToken']);
            $this->response->setHeader('Refresh-Token', $return['refreshToken']);
        }
    }

    /**
     *  after Execute Route
     */
    public function afterExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        parent::afterExecuteRoute($dispatcher); // TODO: Change the autogenerated stub
    }

    /**
     * @param string $controller
     * @param string $action
     * @return array
     * //TODO check permission use JWT in the future
     */
    public function canAccessResource($controller = '', $action = '')
    {
        // Check user in group
        if (!is_object(ModuleModel::$user) || empty(ModuleModel::$user) || !method_exists(ModuleModel::$user, 'getUserGroupId')) {
            return [
                'success' => false,
                'message' => 'TOKEN_EXPIRED_TEXT',
                'method' => __METHOD__,
            ];
        }
        $controller = $controller ? $controller : $this->router->getControllerName();
        $action = $action ? $action : $this->router->getActionName();
        AclHelper::__setUser(ModuleModel::$user);
        $result = AclHelper::__checkPermissionDetail($controller, $action);
        return $result;
    }

    /**
     * Check Ajax Method
     * @param $method
     */
    public function checkAjax($method)
    {
        $check = true;
        if (is_string($method)) {
            if ($method == '') $method = 'GET';
            if ($this->request->isAjax()) {
                if ($method == 'GET' && !$this->request->isGet()) {
                    $check = false;
                } elseif ($method == 'POST' && !$this->request->isPost()) {
                    $check = false;
                } elseif ($method == 'DELETE' && !$this->request->isDelete()) {
                    $check = false;
                } elseif ($method == 'PUT' && !$this->request->isPut()) {
                    $check = false;
                }
            } else {
                $check = false;
            }
        } elseif (is_array($method)) {
            if ($this->request->isAjax()) {
                if (!in_array($this->request->getMethod(), $method)) {
                    $check = false;
                }
            } else {
                $check = false;
            }
        } else {
            $check = false;
        }

        if ($check == false) {
            exit(json_encode([
                'success' => false,
                'message' => 'Restrict Access',
            ]));
        }
    }

    /**
     *
     */
    public function checkAjaxPut()
    {
        return $this->checkAjax('PUT');
    }

    /**
     *
     */
    public function checkAjaxPost()
    {
        return $this->checkAjax('POST');
    }

    /**
     *
     */
    public function checkAjaxPutPost()
    {
        return $this->checkAjax(['POST', 'PUT']);
    }

    public function checkAjaxPutGet()
    {
        return $this->checkAjax(['PUT', 'GET']);
    }

    /**
     *
     */
    public function checkAjaxDelete()
    {
        return $this->checkAjax('DELETE');
    }

    /**
     *
     */
    public function checkAjaxGet()
    {
        return $this->checkAjax('GET');
    }

    /**
     * @param $action
     */
    public function checkAcl($action, $controller_name = '')
    {
        if ($controller_name == '') {
            $controller_name = $this->router->getControllerName();
        }

        if (is_string($action)) {
            $access = $this->canAccessResource($controller_name, $action);
            if (!$access['success']) {
                return $this->returnNotAllowedMessage($access);
            } // --------------
            if (!$access['success']) {
                $access['controller'] = $controller_name;
                $access['action'] = $action;
                exit(json_encode($access));
            } // --------------
        } elseif (is_array($action) && count($action) > 0) {
            foreach ($action as $actionItemValue) {
                if (is_string($actionItemValue)) {
                    $access = $this->canAccessResource($controller_name, $actionItemValue);
                    if (!$access['success']) {
                        return $this->returnNotAllowedMessage($access);
                    }
                    if (!$access['success']) {
                        $access['controller'] = $controller_name;
                        $access['action'] = $action;
                        exit(json_encode($access));
                    }// --------------
                }
            }
        }
    }

    /**
     * if one permission in group validated
     * @param array $actionArray
     */
    public function checkAclMultiple($actionArray = [])
    {
        if (count($actionArray) == 0) return ['success' => true];

        foreach ($actionArray as $key => $actionItemValue) {
            if (is_array($actionItemValue)) {
                if (isset($actionItemValue['controller']) && isset($actionItemValue['action'])) {
                    $access = $this->canAccessResource($actionItemValue['controller'], $actionItemValue['action']);
                    if ($access['success']) {
                        return $access;
                    }
                }
            }
        }

        return $this->returnNotAllowedMessage();
    }


    /**
     *
     */
    public function checkPermissionCreateEdit($controllername = '')
    {
        if ($controllername == '') $controllername = $this->dispatcher->getControllerName();
        $actionArray = [
            ['controller' => $controllername, 'action' => AclHelper::ACTION_CREATE],
            ['controller' => $controllername, 'action' => AclHelper::ACTION_EDIT],
        ];
        $access = $this->checkAclMultiple($actionArray);
        if (!$access['success']) {
            return $this->returnNotAllowedMessage();
            exit(json_encode($access));
        }
    }

    /**
     * if one permission in group failed
     * @param array $actionArray
     */
    public function checkAclSimul($actionArray = [])
    {
        foreach ($actionArray as $actionItemValue) {
            if (is_array($actionItemValue)) {
                $access = $this->canAccessResource($actionItemValue['controller'], $actionItemValue['action']);
                if (!$access['success']) {
                    return $this->returnNotAllowedMessage();
                    return $access;
                }// --------------
            }
        }
        return ['success' => true];
    }

    /**
     * @param string $controller_name
     */
    public function checkAclDelete($controller_name = '')
    {
        return $this->checkAcl('delete', $controller_name);
    }

    /**
     * @param string $controller_name
     */
    public function checkAclEdit($controller_name = '')
    {
        return $this->checkAcl('edit', $controller_name);
    }

    /**
     * @param string $controller_name
     */
    public function checkAclIndex($controller_name = '')
    {
        return $this->checkAcl('index', $controller_name);
    }

    /**
     * @param string $controller_name
     */
    public function checkAclUpdate($controller_name = '')
    {
        return $this->checkAcl('update', $controller_name);
    }

    /**
     * @param string $controller_name
     */
    public function checkAclView($controller_name = '')
    {
        return $this->checkAcl('view', $controller_name);
    }

    /**
     * @param string $controller_name
     */
    public function checkAclCreate($controller_name = '')
    {
        return $this->checkAcl('create', $controller_name);
    }

    /**
     * @param null $accessResult
     */
    public function returnNotAllowedMessage($accessResult = null)
    {
        $this->response->setStatusCode(HttpStatusCode::HTTP_FORBIDDEN);
        if ($accessResult == null) {
            $accessResult = ['success' => false, 'message' => 'METHOD_NOT_ALLOWED_TEXT'];
        }
        $this->response->setJsonContent($accessResult);
        $this->response->send();
        exit();
    }

    /**
     * @param null $accessResult
     */
    public function returnNotAllowOnly($accessResult = null)
    {
        if ($accessResult == null) {
            $accessResult = ['success' => false, 'message' => 'METHOD_NOT_ALLOWED_TEXT'];
        }
        $this->response->setStatusCode(HttpStatusCode::HTTP_FORBIDDEN);
        $this->response->setJsonContent($accessResult);
        $this->response->send();
        exit();
    }

    /**
     *
     */
    public function checkMaintenanceStatus()
    {
        if (getenv('APP_MAINTENANCE') === true || getenv('APP_MAINTENANCE') == 'true') {
            $this->view->disable();
            $this->response->setJsonContent(['success' => false, 'message' => 'MAINTENANCE_IN_PROGRESS_TEXT']);
            $this->response->send();
            exit();
        }
    }

    /**
     * check password , use only if request need password
     */
    public function checkPasswordBeforeExecute()
    {

        $password = '';
        if (Helpers::__existRequestValue('password')) {
            $password = Helpers::__getRequestValue('password');
        } else if (Helpers::__existHeaderValue('password')) {
            $password = Helpers::__getHeaderValue('password');
        }

        $checkPassword = ModuleModel::__loginUserCognitoByEmail(ModuleModel::$user->getEmail(), $password);
        if ($checkPassword['success'] == false) {
            $return = $checkPassword;
            $return['message'] = 'PASSWORD_INCORRECT_TEXT';
            $this->view->disable();
            $this->response->setJsonContent($return);
            $this->response->send();
            exit();
        }
    }

    public function checkAuthMessage($auth = [])
    {
        $controller = $this->router->getControllerName();
        $action = $this->router->getActionName();
        //not apply for loginAction
        if (in_array($controller, ['index', 'auth']) && $action == 'login') {
            // Not redirect form
        } else {
            if ($auth['success'] == false) {
                if ($this->request->isAjax()) {
                    $this->view->disable();
                    $this->response->setStatusCode(HttpStatusCode::HTTP_UNAUTHORIZED, HttpStatusCode::getMessageForCode(HttpStatusCode::HTTP_UNAUTHORIZED));
                    $this->response->setJsonContent([
                        'success' => false,
                        'errorDetails' => $auth,
                        'errorType' => 'loginRequired',
                        'message' => 'ERROR_SESSION_EXPIRED_TEXT',
                        'required' => 'login',
                        'url_redirect' => '/'
                    ]);
                    $this->response->send();
                    exit();
                } else {
                    $url = $this->router->getRewriteUri();
                    if (substr($url, 0, 1) == '/') {
                        $url = substr($url, 1);
                    }
                    $this->response->redirect('/' . '?returnUrl=' . base64_encode($url));
                    return false;
                }
            }
        }
    }
}
