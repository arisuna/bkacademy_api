<?php

namespace SMXD\App\Controllers;

use Phalcon\Exception;
use SMXD\Application\Controllers\ApplicationApiController;
use SMXD\Application\Lib\HttpStatusCode;

/**
 * Base class of App module API controller
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
     * @return \Phalcon\Translate\Adapter\NativeArray
     */
    public function _getTranslation()
    {
        // Get language
        $language = $this->request->get('_l');
        if (!$language) {
            $language = $this->session->get('_l');
            if (!$language) {
                $this->request->getBestLanguage();
            }
        }
        $this->session->set('_l', $language);

        $messages = array();

        //Check if we have a translation file for that lang
        if (file_exists(__DIR__ . "/../messages/" . $language . ".php")) {
            require __DIR__ . "/../messages/" . $language . ".php";
        } else {
            // fallback to some default
            if (file_exists(__DIR__ . "/../messages/en.php"))
                require __DIR__ . "/../messages/en.php";
        }

        //Return a translation object
        return new \Phalcon\Translate\Adapter\NativeArray(array(
            "content" => $messages
        ));
    }

    /**
     * @param $dispatcher
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkPrelightRequest();
        if ($this->request->getHttpHost() == getenv('API_DOMAIN')) {

        }
    }

    /**
     * check authu of user
     * @param string $msg
     * @return bool
     * //TODO : use JWT in the future
     */
    public function checkAuthMessage($auth = [], $redirectUrl = '')
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
                        'url_redirect' => $redirectUrl
                    ]);
                    $this->response->send();
                    exit();
                } else {
                    $url = $this->router->getRewriteUri();
                    if (substr($url, 0, 1) == '/') {
                        $url = substr($url, 1);
                    }
                    $this->response->redirect($redirectUrl . '?returnUrl=' . base64_encode($url));
                    return false;
                }
            }
        }
    }
}