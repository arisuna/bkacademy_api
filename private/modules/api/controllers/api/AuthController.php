<?php

namespace SMXD\Api\Controllers\API;

use Firebase\JWT\JWT;
use LightSaml\Model\Protocol\AuthnRequest;
use Phalcon\Di;
use SMXD\Application\Lib\ValidationHelper;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\User;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\CognitoAppHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\JWTEncodedHelper;
use SMXD\Application\Lib\SamlHelper;
use SMXD\Application\Lib\SMXDUrlHelper;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\ConstantExt;
use SMXD\Application\Models\SsoIdpConfigExt;
use SMXD\Application\Models\SupportedLanguageExt;
use SMXD\Application\Models\UserAuthorKeyExt;
use SMXD\Application\Validation\AuthenticationValidation;

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
            $return = ['success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'];
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


        $credential = Helpers::__getRequestValue('phone');
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
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

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


    public function requestSignupAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $phone = Helpers::__getRequestValue('phone');

        if ($phone == '' || !$phone) {
            $return = ['success' => false, 'message' => 'PHONE_NOT_VALID_TEXT'];
            goto end_of_function;
        }
        //if phone exist

        $user = User::findFirst([
            'conditions' => 'phone = :phone: and status <> :deleted:',
            'bind' => [
                'phone' => $phone,
                'deleted' => User::STATUS_DELETED
            ]
        ]);

        if ($user) {
            $return = ['success' => false, 'message' => 'PHONE_MUST_UNIQUE_TEXT'];
            goto end_of_function;
        }
        //if email exist

        $email = Helpers::__getRequestValue('email');
        if ($email == '' || !$email || !Helpers::__isEmail($email)) {
            $return = ['success' => false, 'message' => 'EMAIL_NOT_VALID_TEXT'];
            goto end_of_function;
        }
        $checkIfExist = User::findFirst([
            'conditions' => 'status <> :deleted: and email = :email:',
            'bind' => [
                'deleted' => User::STATUS_DELETED,
                'email' => $email
            ]
            ]);
        if($checkIfExist){
            $return = [
                'success' => false,
                'message' => 'EMAIL_MUST_UNIQUE_TEXT'
            ];
            goto end_of_function;
        }

        //if user exist not exist


        $model = new User();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);
        $model->setStatus(User::STATUS_ACTIVE);
        $model->setIsActive(Helpers::YES);
        $model->setUserGroupId(null);
        $model->setLoginStatus(User::LOGIN_STATUS_PENDING);

        $this->db->begin();
        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success'] == true) {
            $password = Helpers::password(10);

            $return = ModuleModel::__adminRegisterUserCognito(['email' => $model->getEmail(), 'password' => $password], $model);

            if ($return['success'] == false) {
                $this->db->rollback();
            } else {
                $this->db->commit();
                $return = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT'
                ];
            }
        } else {
            $this->db->rollback();
            $return = ([
                'success' => false,
                'detail' => $resultCreate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ]);
        }

        //send SMS OTP to check Pre-Sign, if Presign OK > and

        $return = ApplicationModel::__customInit($user->getEmail());

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @return void
     */
    public function confirmSignupAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $dataInput = [
            'phone' => Helpers::__getRequestValue('phone'),
            'code' => Helpers::__getRequestValue('code')
        ];

        $validation = new AuthenticationValidation();
        $validationReturn = ValidationHelper::__isValid($dataInput, $validation);
        if ($validationReturn['success'] == false) {
            $return = $validationReturn;
            goto end_of_function;
        }

        $user = User::findFirst([
            'conditions' => 'phone = :phone: and status <> :deleted:',
            'bind' => [
                'phone' => $dataInput['phone'],
                'deleted' => User::STATUS_DELETED
            ]
        ]);

        //if user not  exist
        if (!$user) {
            $return = ['success' => false, 'message' => 'USER_NOT_FOUND_TEXT'];
            goto end_of_function;
        }
        if($user->getLoginStatus() != User::LOGIN_STATUS_PENDING){
            $return = ['success' => false, 'message' => 'USER_STATUS_NOT_VALID_TEXT'];
            goto end_of_function;
        }
        //check OTP
        $result = ApplicationModel::__customLogin($user->getEmail(), $session, $code);
        if ($result['success']) {
            if (isset($result['detail']['AccessToken']) && isset($result['detail']['RefreshToken'])) {
                $user->setLoginStatus(User::LOGIN_STATUS_PENDING);
                $resultUpdate = $model->__quickUpdate();
                if ($resultUpdate['success'] == true) {

                    $return = [
                        'success' => true,
                        'detail' => $resultUpdate,
                        'token' => $resultUpdate['detail']['AccessToken'],
                        'refreshToken' => $resultUpdate['detail']['RefreshToken'],
                    ];

                    $redirectUrl = SMXDUrlHelper::__getDashboardUrl();
                    $return['redirectUrl'] = $redirectUrl;
                } else {
                    $return = $resultUpdate;
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

}
