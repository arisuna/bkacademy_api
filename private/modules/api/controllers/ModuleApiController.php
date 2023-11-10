<?php

namespace SMXD\Api\Controllers;

use Exception;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Controllers\ApplicationApiController;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\HttpStatusCode;

/**
 * Base class of Api module API controller
 */
class ModuleApiController extends ApplicationApiController
{
    /**
     *  set cors config
     */
    public function afterExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkPrelightRequest();
    }

    /**
     * @param $dispatcher
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkPrelightRequest();
        if ($this->request->getHttpHost() == getenv('API_DOMAIN')) {

        }

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
            }
        } catch (Exception $e) {
        }
    }

    /**
     * check authu of user
     * @param string $msg
     * @return bool
     * //TODO : use JWT in the future
     */
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
                        'required' => 'login', // If has parameter, page need reload or redirect to login page,
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