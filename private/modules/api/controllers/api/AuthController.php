<?php

namespace SMXD\Api\Controllers\API;

use LightSaml\Model\Protocol\AuthnRequest;
use Phalcon\Di;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\CognitoAppHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\SamlHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\ConstantExt;
use SMXD\Application\Models\SsoIdpConfigExt;
use SMXD\Application\Models\SupportedLanguageExt;
use SMXD\Application\Models\UserAuthorKeyExt;

/**
 * Concrete implementation of Api module controller
 *
 * @RoutePrefix("/api/api")
 */
class AuthController extends ModuleApiController
{
    /**
     * @Route("/index", paths={module="api"}, methods={"GET"}, name="api-index-index")
     */
    public function indexAction()
    {

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addonAction()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type');

        $this->checkPrelightRequest();

        $this->view->disable();
        $req = $this->request;
        $token = $req->getPost('_t'); // Token key of user

        // Find user by token key
        $User = UserAuthorKeyExt::__findUserByAddonKey($token);
        if ($User) {
            $return = [
                'success' => true,
                'msg' => 'Authorized'
            ];

        } else {
            $return = [
                'success' => false,
                'token' => $token,
                'msg' => 'Invalid key'
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function errorAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false]);
        return $this->response->send();
    }

    /**
     *
     */
    public function loginErrorAction()
    {
        $this->view->enable();
        $this->view->setLayout("error");
        $this->view->pick('verify/sso_invalid');
        $this->response->setStatusCode(404, "Not Found Content");
        $this->response->send();
    }

}
