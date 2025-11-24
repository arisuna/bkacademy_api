<?php

namespace SMXD\App\Controllers\API;

use Firebase\JWT\JWT;
use Phalcon\Config;
use Phalcon\Exception;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\CognitoAppHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\JWTEncodedHelper;
use SMXD\Application\Lib\JWTExt;
use SMXD\Application\Lib\SMXDUrlHelper;
use SMXD\Application\Lib\SamlHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\EmailAwsCognitoExceptionExt;
use SMXD\Application\Models\ModelExt;
use SMXD\App\Models\Company;
use SMXD\App\Models\CompanyType;
use SMXD\App\Models\InvitationRequest;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\ObjectAvatar;
use SMXD\App\Models\App;
use \SMXD\App\Controllers\ModuleApiController;
use Phalcon\Di;
use SMXD\Application\Aws\AwsCognito\CognitoClient;
use Aws\Exception\AwsException;
use SMXD\App\Models\User;
use SMXD\App\Models\UserRequestToken;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class AuthController extends ModuleApiController
{
    /**
     * @throws \Exception
     */
    public function checkAuthConnectedAction()
    {
        $this->view->disable();
        $return = ['success' => false];
        $this->checkAjaxGet();

        $accessToken = ModuleModel::__getAccessToken();
        $refreshToken = ModuleModel::__getRefreshToken();
        if (Helpers::__isNull($accessToken)) {
            $return = [
                'success' => false,
                'message' => 'SESSION_NOT_FOUND_TEXT',
                'required' => 'login'
            ];
            //$this->checkAuthMessage($return);
            goto end_of_function;
        }

        $return = ModuleModel::__checkAndRefreshAuthenByToken($accessToken, $refreshToken);

        if ($return['success'] == false) {
            //$this->checkAuthMessage($return);
            $userPayload = JWTEncodedHelper::__getPayload($accessToken);
            $return['payLoad'] = $userPayload;
            $return['payLoad']['curentTime'] = time();
            $return['payLoad']['curentDateTime'] = date('Y-m-d H:i:s');
            $return['payLoad']['iat'] = date('Y-m-d H:i:s', $userPayload['iat']);
            $return['payLoad']['exp'] = date('Y-m-d H:i:s', $userPayload['exp']);
            $return['jwtLeeway'] = JWT::$leeway;
            goto end_of_function;
        }

        if ($return['success'] == true) {
            $this->response->setHeader('Token-Key', $return['accessToken']);
            $this->response->setHeader('Refresh-Token', $return['refreshToken']);
            $return = [
                'isRefreshed' => $return['isRefreshed'],
                'user' => ModuleModel::$user,
                'token' => ModuleModel::$user_token,
                'refreshToken' => $return['refreshToken'],
                'success' => true,
                'message' => 'LOGIN_SUCCESS_TEXT'
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * Just call ajax action
     */
    public function loginAction()
    {
        $this->view->disable();
        $result = ['detail' => [], 'success' => false, 'message' => ''];

        $this->checkAjaxPost();

        $credential = Helpers::__getRequestValue('credential');
        $password = Helpers::__getRequestValue('password');

        $user = User::findFirst([
            'conditions' => 'email = :email: and status <> :deleted:',
            'bind' => [
                'email' => $credential,
                'deleted' => User::STATUS_DELETED
            ]
        ]);

        if (!$user) {
            $result = ['success' => false, 'message' => 'Login not found!'];
            goto end_of_function;
        }
        if(!password_verify($password, $user->getPassword())){
            $result = ['success' => false, 'message' => 'INVALID_PASSWORD_TEXT'];
            goto end_of_function;
        }

        $return = ApplicationModel::__customLogin($user);
        if ($return['success'] == true) {
            $result = [
                'success' =>  true,
                'detail' => $return,
                'token' => $return['detail']['AccessToken'],
                'refreshToken' => $return['detail']['RefreshToken'],
            ];

            $redirectUrl = SMXDUrlHelper::__getDashboardUrl();
            $result['redirectUrl'] = $redirectUrl;
        } else {
            $result = $return;
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     *
     */
    public function verifyAccountAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $email = Helpers::__getRequestValue('email');

        if ($email == '' || !Helpers::__isEmail($email)) {
            $return = ['success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $user = User::findFirst([
            'conditions' => 'email = :email: and status <> :deleted:',
            'bind' => [
                'email' => $email,
                'deleted' => User::STATUS_DELETED
            ]
        ]);

        if (!$user) {
            $return = ['success' => false, 'message' => 'Login not found!'];
            goto end_of_function;
        }

        $return = ['success' => true];

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }
}