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
use SMXD\Application\Models\UserLoginSsoExt;
use SMXD\App\Models\Company;
use SMXD\App\Models\CompanyType;
use SMXD\App\Models\InvitationRequest;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\ObjectAvatar;
use SMXD\App\Models\UserLoginToken;
use SMXD\App\Models\App;
use \SMXD\App\Controllers\ModuleApiController;
use SMXD\App\Models\UserLogin;
use Phalcon\Di;
use SMXD\Application\Aws\AwsCognito\CognitoClient;
use Aws\Exception\AwsException;
use SMXD\App\Models\User;
use SMXD\App\Models\UserRequestToken;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
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

        $return = ModuleModel::__checkAndRefreshAuthenByCognitoToken($accessToken, $refreshToken);

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
                'login' => ModuleModel::$user_login,
                'token' => ModuleModel::$user_login_token,
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
        $session = Helpers::__getRequestValue('session');

        $user = User::findFirst([
            'conditions' => 'email = :email: and status <> :deleted:',
            'bind' => [
                'email' => $credential,
                'deleted' => User::STATUS_DELETED
            ]
        ]);

        if (!$user) {
            $return = ['success' => false, 'message' => 'Login not found!'];
            goto end_of_function;
        }

        $return = ApplicationModel::__customLogin($credential, $session, $password);
        if ($return['success'] == true) {
            if(isset($return['detail']['AccessToken']) && isset($return['detail']['RefreshToken'])){
                $result = [
                    'success' =>  true,
                    'detail' => $return,
                    'token' => $return['detail']['AccessToken'],
                    'refreshToken' => $return['detail']['RefreshToken'],
                ];

                $redirectUrl = SMXDUrlHelper::__getDashboardUrl();
                $result['redirectUrl'] = $redirectUrl;
            } else {
                $result = ['detail' => [], 'success' => false, 'message' => 'INVALID_VERIFICATION_CODE_TEXT'];
            }
        } else {
            $result = $return;
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Logout action
     */
    public function logoutAction()
    {
        $this->view->disable();
        $token_key = $this->request->get('token');
        $login = UserLoginToken::findFirst("token='" . addslashes($token_key) . "'");
        if ($login instanceof UserLoginToken) {
            $login->delete();
        }
        echo json_encode([
            'success' => true,
            'message' => 'LOGOUT_SUCCESS_TEXT'
        ]);
        if (!$this->request->isAjax()) {
            $this->response->redirect('/gms/#/login');
        }
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

        $return = ApplicationModel::__customInit($email);

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function changeSecurityAction()
    {
        $this->view->disable();
        $result = ['detail' => [], 'success' => false, 'message' => ''];
        $this->checkAjax('POST');

        $credential = Helpers::__getRequestValue('email');
        $password = Helpers::__getRequestValue('new_password');
        $token_key = Helpers::__getRequestValue('_token');
        $language_key = Helpers::__getHeaderValue(Helpers::LANGUAGE_KEY);
        $auth = ModuleModel::__checkAuthenByOldToken($token_key, $language_key);
        $session = Helpers::__getRequestValue("session");
        $challengeName = Helpers::__getRequestValue("challengeName");

        if (!$auth['success']) {
            return $this->checkAuthMessage($auth);
        }

        if ($session != null && $session != "") {
            $result = ModuleModel::__respondToAuthChallenge($challengeName, $credential, $password, $session);
            $result["forceChangePassword"] = true;
        } else {

            $checkException = EmailAwsCognitoExceptionExt::findFirstByEmail($credential);
            if ($checkException) {
                $verificationCodeRequired = false;
            } else {
                $verificationCodeRequired = true;
            }


            $registerResult = ModuleModel::__addNewUserCognito([
                'email' => $credential,
                'password' => $password,
                'loginUrl' => ModuleModel::$app->getLoginUrl()
            ]);

            if ($registerResult['success'] == false) {
                $result = $registerResult;
                goto end_of_function;
            }

            $result = ['success' => true, 'detail' => $registerResult['key'], 'verificationCodeRequired' => $verificationCodeRequired];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function verifyCodeAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $return = ['detail' => [], 'success' => false, 'message' => ''];

        $credential = Helpers::__getRequestValue('credential');
        $code = Helpers::__getRequestValue('code');
        $token_key = Helpers::__getRequestValue('token');
        $language_key = Helpers::__getHeaderValue(Helpers::LANGUAGE_KEY);
        $auth = ModuleModel::__checkAuthenByOldToken($token_key, $language_key);

        if (!$auth['success']) {
            return $this->checkAuthMessage($auth);
        }
        $return = ModuleModel::__confirmUserCognito($code, $credential);
        if ($return['success'] == false) {
            $return['message'] = 'CAN_NOT_CONFIRM_USER_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function resendCodeAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $return = ['detail' => [], 'success' => false, 'message' => ''];
        $credential = Helpers::__getRequestValue('credential');
        //$token_key = Helpers::__getRequestValue('token');
        //$language_key = Helpers::__getHeaderValue(Helpers::LANGUAGE_KEY);
        //$auth = ModuleModel::__checkAuthenByOldToken($token_key, $language_key);
        $return = ModuleModel::__resendUserCognitoVerificationCode($credential);
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function resetAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $result = ['detail' => [], 'success' => false, 'message' => ''];
        $email = Helpers::__getRequestValue('email');
        $userLogin = UserLogin::findFirstByEmail($email);
        if (!$userLogin ||
            !$userLogin->getUser() ||
            !$userLogin->getUser()->isGms() ||
            !$userLogin->getApp()) {
            $result = [
                'result' => false,
                'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }
        $result = ModuleModel::__sendUserCognitoRecoveryPasswordRequest($email);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function changePasswordWithConfirmCodeAction()
    {
        $this->view->disable();
        $result = ['detail' => [], 'success' => false, 'message' => ''];
        $this->checkAjax('POST');

        $credential = Helpers::__getRequestValue('email');
        $password = Helpers::__getRequestValue('password');
        $code = Helpers::__getRequestValue('code');

        //add new user by cognito
        $result = ModuleModel::__changeUserCognitoPassword([
            'confirmCode' => $code,
            'email' => $credential,
            'password' => $password,
        ]);

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function changePassword()
    {
        $this->view->disable();
        $result = ['detail' => [], 'success' => false, 'message' => ''];
        $this->checkAjax('POST');

        $credential = Helpers::__getRequestValue('email');
        $password = Helpers::__getRequestValue('password');
        $code = Helpers::__getRequestValue('code');

        //add new user by cognito
        $result = ModuleModel::__changeUserCognitoPassword([
            'confirmCode' => $code,
            'email' => $credential,
            'password' => $password,
        ]);

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     * @throws \Exception
     */
    public function changeMyPasswordAction()
    {
        $this->view->disable();
        $result = ['success' => true, 'message' => 'PASSWORD_UPDATED_SUCCESS_TEXT'];
        $this->checkAjaxPost();

        $oldPassword = Helpers::__getRequestValue('user_login_old_password');
        $password = Helpers::__getRequestValue('user_login_password');
        $repeatPassword = Helpers::__getRequestValue('user_login_password_confirm');

        $result = ModuleModel::__changeMyPassword([
            'oldPassword' => $oldPassword,
            'password' => $password,
        ]);

        if ($result['success'] == true) {
            $result['message'] = 'PASSWORD_UPDATED_SUCCESS_TEXT';
        } else {
            $result['message'] = 'PASSWORD_UPDATED_FAIL_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function autoLoginAction()
    {
        $this->view->disable();
        $result = ['success' => false, 'message' => 'SESSION_NOT_FOUND_TEXT'];
        $this->checkAjaxPost();
        $encode_key = Helpers::__getRequestValue('hash');
        $key_decode = base64_decode($encode_key);
        $user_request_token = UserRequestToken::findFirstByHash($key_decode);
        $this->db->begin();
        if (!$user_request_token instanceof UserRequestToken) {
            $invitation_request = InvitationRequest::findFirstByToken($key_decode);
            if (!$invitation_request instanceof InvitationRequest) {
                $this->db->rollback();
                $result = ['success' => false, 'message' => 'SESSION_NOT_FOUND_TEXT'];
                goto end_of_function;
            } else {
                if ($invitation_request->getIsLogin() == Helpers::YES) {
                    $this->db->rollback();
                    $result = ['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT', 'invitation' => $invitation_request];
                    goto end_of_function;
                }
                $company_id = $invitation_request->getToCompanyId();
                $company = $invitation_request->getToCompany();
                if (!$company instanceof Company || $company->getCompanyTypeId() != CompanyType::TYPE_GMS) {
                    $this->db->rollback();
                    $result = ['success' => false, 'message' => 'SESSION_NOT_FOUND_TEXT', 'company' => $company];
                    goto end_of_function;
                }

                $invitation_request->setIsLogin(Helpers::YES);
                $update = $invitation_request->__quickUpdate();
                if (!$update['success']) {
                    $this->db->rollback();
                    $result = ['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT', 'update' => $update];
                    goto end_of_function;
                }

                $this->db->commit();
                $result = json_decode($invitation_request->getFirstLoginToken());
                goto end_of_function;
            }
        } else {
            if ($user_request_token->getIsLogin() == Helpers::YES) {
                $this->db->rollback();
                $result = ['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT'];
                goto end_of_function;
            }
            $user_login = $user_request_token->getUserLogin();
            if (!$user_login instanceof UserLogin) {
                $this->db->rollback();
                $result = ['success' => false, 'message' => 'SESSION_NOT_FOUND_TEXT'];
                goto end_of_function;
            }
            $user = $user_login->getUser();
            if (!$user instanceof User) {
                $this->db->rollback();
                $result = ['success' => false, 'message' => 'SESSION_NOT_FOUND_TEXT', 'user' => $user];
                goto end_of_function;
            }
            $company = $user->getCompany();
            if (!$company instanceof Company || $company->getCompanyTypeId() != CompanyType::TYPE_GMS) {
                $this->db->rollback();
                $result = ['success' => false, 'message' => 'SESSION_NOT_FOUND_TEXT', 'company' => $company];
                goto end_of_function;
            }
            $user_request_token->setIsLogin(Helpers::YES);
            $update = $user_request_token->__quickUpdate();
            if (!$update['success']) {
                $this->db->rollback();
                $result = ['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT'];
                goto end_of_function;
            }

            $this->db->commit();
            $result = json_decode($user_request_token->getFirstLoginToken());
            goto end_of_function;

        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     *
     */
    public function checkSamlAuthenticationAction()
    {
        $this->checkAjaxPost();
        $results = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT', 'data' => []];

        $uuid = Helpers::__getRequestValue('uuid');
        if (!$uuid) {
            $results['message'] = "UUID_NOT_FOUND_TEXT";
            goto end;
        }

        $userLoginSso = UserLoginSsoExt::findFirst([
            'conditions' => 'uuid = :uuid: and lifetime > :current_time: and ip_address = :ip_address: and is_alive = :alive_yes:',
            'bind' => [
                'uuid' => $uuid,
                'current_time' => time(),
                'ip_address' => $this->request->getClientAddress(),
                'alive_yes' => UserLoginSsoExt::ALIVE_YES
            ],
            'order' => 'created_at DESC',
        ]);

        if (!$userLoginSso) {
            $results['message'] = "SSO_NOT_FOUND_TEXT";
            $results['params'] = [
                'uuid' => $uuid,
                'current_time' => time(),
                'ip_address' => $this->request->getClientAddress(),
            ];
            goto end;
        }

        $results = [
            'success' => true,
            'token' => $userLoginSso->getAccessToken(),
            'refreshToken' => $userLoginSso->getRefreshToken()
        ];


        end:
        $this->response->setJsonContent($results);
        $this->response->send();
    }
}