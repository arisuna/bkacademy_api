<?php

namespace Reloday\Gms\Controllers\API;

use Firebase\JWT\JWT;
use Phalcon\Config;
use Phalcon\Exception;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\CognitoAppHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\JWTEncodedHelper;
use Reloday\Application\Lib\JWTExt;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Application\Lib\SamlHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\EmailAwsCognitoExceptionExt;
use Reloday\Application\Models\ModelExt;
use Reloday\Application\Models\UserLoginSsoExt;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyType;
use Reloday\Gms\Models\InvitationRequest;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectAvatar;
use Reloday\Gms\Models\UserLoginToken;
use Reloday\Gms\Models\App;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\UserLogin;
use Phalcon\Di;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Aws\Exception\AwsException;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\UserRequestToken;

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

        $this->blockActionCompanyDesactived();

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
                'user' => ModuleModel::$user_profile,
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

        //1 check Classic UserLogin
        $userLogin = UserLogin::findFirstByEmail($credential);

        if (!$userLogin ||
            !$userLogin->getUserProfile() ||
            !$userLogin->getUserProfile()->isGms() ||
            !$userLogin->getApp() ||
            $userLogin->getUserProfile()->isDeleted()) {
            $result = [
                'result' => false,
                'message' => 'INVALID_LOGIN_CREDENTIALS_TEXT'
            ];
            goto end_of_function;
        }

        if ($userLogin &&
            $userLogin->getUserProfile() &&
            $userLogin->getUserProfile()->isActive() == false) {

            $result = [
                'result' => false,
                'message' => 'INVALID_LOGIN_CREDENTIALS_TEXT'
            ];
            goto end_of_function;
        }

        if ($userLogin) {
            //2 check userCognito Exist => his first login
            ModuleModel::$user_profile = $userLogin->getUserProfile();
            ModuleModel::$company = $userLogin->getUserProfile()->getCompany();

            if ($userLogin->isConvertedToUserCognito() == false) {

                $resultLogin = ApplicationModel::__loginUserOldMethod($credential, $password);

                if ($resultLogin['success'] == false) {
                    $result = $resultLogin;
                    $result['data'] = $userLogin;
                    goto end_of_function;
                } else {
                    $userLogin = UserLogin::findFirstByEmail($credential);
                    $tokenUserDataToken = ApplicationModel::__getUserTokenOldMethod($userLogin);
                    $result = [
                        'success' => false,
                        'token' => $tokenUserDataToken,
                        'message' => 'UserNotFoundException',
                    ];
                    $result['data'] = $userLogin;
                    goto end_of_function;
                }
            } else {
                goto aws_cognito_login;
            }

        }

        //2 check cognitoLogin
        aws_cognito_login:

        $cognitoResultLogin = ModuleModel::__loginUserCognitoByEmail($credential, $password);

        if ($cognitoResultLogin['success'] == true) {
            $resultUpdateLastLogin = $userLogin->updateDateConnectedAt();
            ModuleModel::$user_login_token = $cognitoResultLogin['accessToken'];
            $result = ['success' => true, 'token' => $cognitoResultLogin['accessToken'], 'refreshToken' => $cognitoResultLogin['refreshToken'], 'result' => $cognitoResultLogin];
            goto check_sso;
        } else {
            //password correct and user not confirmed
            if (isset($cognitoResultLogin['UserNotFoundException']) && $cognitoResultLogin['UserNotFoundException'] == true) {
                $result = [
                    'success' => false,
                    'cognitoResultLogin' => $cognitoResultLogin,
                    'detail' => isset($cognitoResultLogin['message']) ? $cognitoResultLogin['message'] : '',
                    'message' => $cognitoResultLogin['exceptionType']
                ];
                goto end_of_function;
            } elseif (isset($cognitoResultLogin['UserNotConfirmedException']) && $cognitoResultLogin['UserNotConfirmedException'] == true) {
                $userLogin = UserLogin::findFirstByEmail($credential);
                $tokenUserDataToken = ApplicationModel::__getUserTokenOldMethod($userLogin);
                $result = [
                    'success' => false,
                    'token' => $tokenUserDataToken,
                    'message' => $cognitoResultLogin['exceptionType']
                ];
            } elseif (isset($cognitoResultLogin['NewPasswordRequiredException']) && $cognitoResultLogin['NewPasswordRequiredException'] == true) {
                $userLogin = UserLogin::findFirstByEmail($credential);
                $tokenUserDataToken = ApplicationModel::__getUserTokenOldMethod($userLogin);
                $result = [
                    'success' => false,
                    'token' => $tokenUserDataToken,
                    'session' => $cognitoResultLogin['session'],
                    'challengeName' => $cognitoResultLogin['name'],
                    'message' => "NewPasswordRequiredException"
                ];
                goto end_of_function;
            } else {
                $result = [
                    'success' => false,
                    'cognitoResultLogin' => $cognitoResultLogin,
                    'detail' => isset($cognitoResultLogin['message']) ? $cognitoResultLogin['message'] : null,
                    'errorType' => $cognitoResultLogin['exceptionType'],
                    'message' => $cognitoResultLogin['exceptionType']
                ];
            }
        }
        check_sso:
        if ($result['success'] == true) {
            $resultCopyCognito = CognitoAppHelper::__copyCognitoUser($credential, $password);
            if ($resultCopyCognito['success'] == true &&
                Helpers::__isValidUuid($resultCopyCognito['awsUuid']) &&
                $userLogin->getAwsUuidCopy() != $resultCopyCognito['awsUuid']) {
                $userLogin->setAwsUuidCopy($resultCopyCognito['awsUuid']);
                $resultUserSso = $userLogin->__quickUpdate();
                if ($resultUserSso['success'] == false) {
                    $resultSso['errorType'] = 'canNotUpdateUserLogin';
                    //goto end_of_function;
                }
            }

            $resultClear = UserLoginSsoExt::clearUnusedDataOfUser($userLogin->getId());
            $userLoginSsoValid = $userLogin->getValidUserLoginSso($this->request->getClientAddress());
            if (!$userLoginSsoValid) {
                $uuid = Helpers::__uuid();
                $newUserLoginSso = new UserLoginSsoExt();
                $newUserLoginSso->setUuid($uuid);
                $newUserLoginSso->setUserLoginId($userLogin->getId());
                $newUserLoginSso->setAccessToken($cognitoResultLogin['accessToken']);
                $newUserLoginSso->setRefreshToken($cognitoResultLogin['refreshToken']);
                //$newUserLoginSso->setSamlToken("");
                $newUserLoginSso->setLifetime(time() + CacheHelper::__TIME_10_MINUTES);
                $newUserLoginSso->setIpAddress($this->request->getClientAddress());
                $newUserLoginSso->setIsAlive(UserLoginSsoExt::ALIVE_YES);
//                var_dump(__METHOD__);
//                die();
                $resultSso = $newUserLoginSso->__quickCreate();
                if ($resultSso['success'] == false) {
                    $result = $resultSso;
                    $resultSso['errorType'] = 'canNotCreateLoginSSO';
                    goto end_of_function;
                }
            } else {
                $uuid = $userLoginSsoValid->getUuid();
                $userLoginSsoValid->setUuid($uuid);
                $userLoginSsoValid->setUserLoginId($userLogin->getId());
                $userLoginSsoValid->setAccessToken($cognitoResultLogin['accessToken']);
                $userLoginSsoValid->setRefreshToken($cognitoResultLogin['refreshToken']);
                //$newUserLoginSso->setSamlToken("");
                $userLoginSsoValid->setLifetime(time() + CacheHelper::__TIME_10_MINUTES);
                $userLoginSsoValid->setIpAddress($this->request->getClientAddress());
                $userLoginSsoValid->setIsAlive(UserLoginSsoExt::ALIVE_YES);
                $resultUserSso = $userLoginSsoValid->__quickUpdate();
                if ($resultUserSso['success'] == false) {
                    $result = $resultUserSso;
                    $resultUserSso['errorType'] = 'canNotUpdateLoginSSO';
                    goto end_of_function;
                }
            }

            $redirectUrl = $userLogin->getEmployeeOrUserProfile()->getAppUrl() . SamlHelper::DSP_HR_SAML_POSTFIX_URL . '/' . $uuid;
            $result['redirectUrl'] = $redirectUrl;
        }
        end_of_function:
        $this->blockActionCompanyDesactived();
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


        $userLogin = UserLogin::findFirstByEmail($email);

        if (!$userLogin ||
            !$userLogin->getUserProfile() ||
            !$userLogin->getUserProfile()->isGms() ||
            !$userLogin->getApp() ||
            $userLogin->getUserProfile()->isDeleted()) {
            $return = [
                'result' => false,
                'message' => 'USER_NOT_EXISTED_TEXT'
            ];
            goto end_of_function;
        }
        if ($userLogin &&
            $userLogin->getUserProfile() &&
            $userLogin->getUserProfile()->isActive() == false) {

            $return = [
                'result' => false,
                'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        $calledUrl = RelodayUrlHelper::__getCalledUrl();
        $appUrl = $userLogin->getApp()->getFrontendUrl();
        // Get logoURL
        $objectAvatar = ObjectAvatar::__getLogo($userLogin->getUserProfile()->getCompany()->getUuid());

        // Get SSO IDP CONFIG IF EXISTED
        $ssoIdpConfig = $userLogin->getUserSsoIdpConfig();
        $ssoArr = [];
        if ($ssoIdpConfig) {
            $ssoArr = [
                'sso_url_login' => $ssoIdpConfig->getSsoUrlLogin(),
            ];
            try {

                $authNRequest = $ssoIdpConfig->generateAuthNRequest([
                    'sp_api_response' => RelodayUrlHelper::PROTOCOL_HTTPS . "://" . getenv('API_DOMAIN') . SamlHelper::POSTFIX_API_CALLBACK
                ]);

                $serializeToXml = SamlHelper::__serializeAuthnRequestToXml($authNRequest['authnRequest']);
                $SAMLRequest = base64_encode($serializeToXml);
                $ssoArr['SAMLRequest'] = $SAMLRequest;

                //Precreate login sso with request id
                $userLoginSso = new UserLoginSsoExt();
                $userLoginSso->setUuid(Helpers::__uuid());
                $userLoginSso->setUserLoginId($userLogin->getId());
                $userLoginSso->setRequestId($authNRequest['ssoRequestId']);
                $userLoginSso->setIpAddress($this->request->getClientAddress());
                $userLoginSso->setIsAlive(UserLoginSsoExt::ALIVE_NO);
                $resultCreate = $userLoginSso->__quickCreate();

            } catch (\Exception $e) {
                \Sentry\captureException($e);
            }
        }

        $data = [
            'email' => $userLogin->getEmail(),
            'uuid' => $userLogin->getUserProfile()->getUuid(),
            'fullname' => $userLogin->getUserProfile()->getFullName(),
            'avatarUrl' => $userLogin->getUserProfile()->getAvatarUrl(),
            'appUrl' => $userLogin->getEmployeeOrUserProfile()->getAppUrl(),
            'calledUrl' => $calledUrl,
            'companyLogo' => $objectAvatar ? $objectAvatar['image_data']['url_thumb'] : null,
            'isExist' => true,
            'hasSsoConfig' => $userLogin->getUserSsoIdpConfig() ? true : false,
            'ssoIdpConfig' => $ssoArr,
            'hasLogin' => $userLogin->getUserProfile()->hasLogin(),
        ];

        $redirect = $this->getDI()->get('appConfig')->application->needRedirectAfterLogin == true ? trim($appUrl, "/") !== trim($calledUrl, "/") : false;
        if ($redirect == true) {
            $data['redirect'] = true;
        }
        $return = [
            'success' => true,
            'data' => $data
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    public function getCompanyBySubDomainAction($sub_domain)
    {
        $result = [
            "success" => false,
            "message" => "DATA_NOT_FOUND_TEXT"
        ];

        $app = App::findFirstByUrl($sub_domain);
        if ($app) {
            $company = $app->getCompany();

            if ($company) {
                $data = $company->toArray();

                // Logo
                $objectLogo = ObjectAvatar::__getLogo($company->getUuid());
                $data['companyLogo'] = $objectLogo ? $objectLogo['image_data']['url_thumb'] : null;

                $theme = $company->getTheme();
                if ($theme) {

                    $data['theme'] = $theme->toArray();
                    try {
                        $logo_img = ObjectAvatar::__getImageByUuidAndType($theme->getUuid(), 'theme_logo');

                        if ($logo_img) {
                            $data['theme']['logo_url'] = $logo_img->getUrlThumb();
                        } else {
                            $data['theme']['logo_url'] = '';
                        }
                    } catch (\Exception $exception) {
                        \Sentry\captureException($exception);
                        $data['theme']['logo_url'] = "";
                    }
                    try {
                        $icon_img = ObjectAvatar::__getImageByUuidAndType($theme->getUuid(), 'theme_icon');
                        if ($icon_img) {
                            $data['theme']['icon_url'] = $icon_img->getUrlThumb();
                        } else {
                            $data['theme']['icon_url'] = '';
                        }
                    } catch (\Exception $exception) {
                        \Sentry\captureException($exception);
                        $data['theme']['logo_url'] = "";
                    }
                    try {
                        $logo_login_img = ObjectAvatar::__getImageByUuidAndType($theme->getUuid(), 'theme_logo_login');
                        if ($logo_login_img) {
                            $data['theme']['logo_login_url'] = $logo_login_img->getUrlThumb();
                        } else {
                            $data['theme']['logo_login_url'] = '';
                        }
                    } catch (\Exception $exception) {
                        \Sentry\captureException($exception);
                        $data['theme']['logo_login_url'] = "";
                    }

                }
                $result = [
                    "success" => true,
                    'data' => $data,
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();

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
            !$userLogin->getUserProfile() ||
            !$userLogin->getUserProfile()->isGms() ||
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
            $user_profile = $user_login->getUserProfile();
            if (!$user_profile instanceof UserProfile) {
                $this->db->rollback();
                $result = ['success' => false, 'message' => 'SESSION_NOT_FOUND_TEXT', 'user_profile' => $user_profile];
                goto end_of_function;
            }
            $company = $user_profile->getCompany();
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