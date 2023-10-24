<?php

namespace SMXD\Api\Controllers\API;

use LightSaml\Model\Protocol\AuthnRequest;
use Phalcon\Di;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\User;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\CognitoAppHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\SamlHelper;
use SMXD\Application\Lib\SMXDUrlHelper;
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

    public function verifyAccountAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $phone = Helpers::__getRequestValue('phone');

        if ($phone == '' || !$phone) {
            $return = ['success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $user = User::findFirst([
            'conditions' => 'phone = :phone: and status <> :deleted:',
            'bind' => [
                'phone' => $phone,
                'deleted' => User::STATUS_DELETED
            ]
        ]);

        if (!$user) {
            $return = ['success' => false, 'message' => 'Login not found!'];
            goto end_of_function;
        }

        $return = ApplicationModel::__customInit($user->getEmail());

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    public function loginAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $result = ['detail' => [], 'success' => false, 'message' => 'INVALID_VERIFICATION_CODE_TEXT'];


        $credential = Helpers::__getRequestValue('credential');
        $code = Helpers::__getRequestValue('code');
        $session = Helpers::__getRequestValue('session');

        $user = User::findFirst([
            'conditions' => 'phone = :phone: and status <> :deleted:',
            'bind' => [
                'phone' => $credential,
                'deleted' => User::STATUS_DELETED
            ]
        ]);

        if (!$user) {
            $result = ['detail' => [], 'success' => false, 'message' => 'LOGIN_FAILED_TEXT'];
            goto end_of_function;
        }


        $return = ApplicationModel::__customLogin($user->getEmail(), $session, $code);
        if ($return['success']) {
            if (isset($return['detail']['AccessToken']) && isset($return['detail']['RefreshToken'])) {
                $result = [
                    'success' => true,
                    'detail' => $return,
                    'token' => $return['detail']['AccessToken'],
                    'refreshToken' => $return['detail']['RefreshToken'],
                ];

                $redirectUrl = SMXDUrlHelper::__getDashboardUrl();
                $result['redirectUrl'] = $redirectUrl;
            }
        } else {
            $result = $return;
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
