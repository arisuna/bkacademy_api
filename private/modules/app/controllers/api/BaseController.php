<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Exception;
use SMXD\App\Controllers\ModuleApiController;

/**
 * Base class of App module API controller
 */
class BaseController extends ModuleApiController
{
    /**
     * [beforeExecuteRoute description]
     * @param  [type] $dispatcher [description]
     * @return [type]             [description]
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {

        try {
            $logged = false;
            $msg = '';
            if (is_array($this->session->getName('auth'))) {
                $msg = 'LOGIN_REQUIRED';
            } else {
                if (!$this->session->getName('auth')['isLogged']) {
                    $msg = 'LOGIN_REQUIRED';
                } else {
                    $logged = true;
                }
            }

            // Require logged
            if (!$logged) {
                $this->authRedirect($msg);
                return false;
            }
        } catch (Exception $e) {
            // Exception here
        }
    }

    /**
     * [authRedirect description]
     * @param  string $msg [description]
     * @return [type]      [description]
     */
    public function authRedirect($msg = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $controller = $this->router->getControllerName();
        $action = $this->router->getActionName();

        if ($controller != 'auth' && $action != 'login') {

            if ($this->request->isAjax()) {

                echo json_encode([
                    'success' => false,
                    'message' => 'LOGIN_REQUIRED',
                    'required' => 'login', // If has parameter, page need reload or redirect to login page,
                    'url_redirect' => '/app/auth/login'
                ]);

            } else {
                $url = $this->router->getRewriteUri();
                if (substr($url, 0, 1) == '/') {
                    $url = substr($url, 1);
                }

                if ($msg)
                    $this->flashSession->warning($this->closeMessageBox . $msg);
                $this->response->redirect('app/auth/login?returnUrl=' . base64_encode($url));
            }
        }
    }
}