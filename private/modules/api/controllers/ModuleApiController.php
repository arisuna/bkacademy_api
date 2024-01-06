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
            $accessToken = ModuleModel::__getAccessToken();
            $refreshToken = ModuleModel::__getRefreshToken();

            if ($accessToken && $refreshToken){
                $return = ModuleModel::__checkAndRefreshAuthenByToken($accessToken, $refreshToken);
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