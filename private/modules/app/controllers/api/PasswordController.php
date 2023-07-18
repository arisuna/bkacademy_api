<?php

namespace Reloday\App\Controllers\API;

use \Reloday\App\Controllers\ModuleApiController;
use \Phalcon\Validation\Validator\Email as EmailValidator;
use \Phalcon\Validation;
use \Phalcon\Validation\Validator\PresenceOf as PresenceOf;
use Reloday\App\Models\EmailTemplateDefault;
use Reloday\App\Models\ModuleModel;
use Reloday\App\Models\SupportedLanguage;
use \Reloday\App\Models\UserLogin;
use \Reloday\App\Models\RecoveryPasswordRequest;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayUrlHelper;
use Phalcon\Di;
use Reloday\Application\Aws\AwsCognito\CognitoClient;


/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class PasswordController extends ModuleApiController
{
    /**
     * @Route("/password", paths={module="app"}, methods={"GET"}, name="app-password-index")
     */
    public function indexAction()
    {

    }

    /**
     * create request password
     * @return [type]
     */
    public function resetAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $result = ['detail' => [], 'success' => false, 'message' => ''];

        $result = ["success" => false, "msg" => "PASSWORD_RESET_SUCCESS_TEXT"];
        $data = $this->request->getJsonRawBody();
        $email = Helpers::__getRequestValue('email');
        $credential = Helpers::__getRequestValue('email');

        $result = ModuleModel::__startCognitoClient();
        if ($result['success'] == false) {
            goto end_of_function;
        }

        try {
            $client = ModuleModel::$cognitoClient;
            $client->setDefaultConfiguration();
            $validation = new Validation();
            $validation->add('email', new EmailValidator([
                'message' => 'EMAIL_NOT_VALID'
            ]));
            $messages = $validation->validate($data);

            if (count($messages)) {
                $error_message = "";
                foreach ($messages as $message) {
                    $error_message .= $message;
                }
                $return = ["success" => false, "msg" => $error_message];
            }
            $appConfig = $di->get('appConfig');

            $user_login = UserLogin::findFirstByEmail($email);

            if ($user_login) {
                $user_profile = $user_login->getUserProfile();
                $employee = $user_login->getEmployee();
            } else {
                $user_profile = false;
                $employee = false;
            }

            $authenticationResponse = $client->sendForgottenPasswordRequest($credential);
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $result = ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];


            goto end_of_function;
        }
        //var_dump($authenticationResponse);

        $result = ['success' => true];


        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return [type]
     */
    public function openAction()
    {

        $this->view->disable();
    }

    /**
     * submit and reset password
     * @return [type]
     */
    public function resetsubmitAction()
    {
        $this->view->disable();
        //submit new passowrd

        if ($this->request->isPost()) {

            $key_data = $this->request->getJsonRawBody();
            $hash = isset($key_data->hash) ? $key_data->hash : "";
            $email = isset($key_data->email) ? $key_data->email : "";
            $password = isset($key_data->password) ? $key_data->password : "";
            $repeat_password = isset($key_data->password) ? $key_data->repeatpassword : "";

            $key_decode = base64_decode($hash);
            $recoveryRequest = RecoveryPasswordRequest::findFirstByHash($key_decode);

            if ($email == "" || $password == "" || $repeat_password == "") {
                $return = [
                    'result' => false,
                    'success' => false,
                    'message' => 'ERROR_SESSION_EXPIRED_TEXT'
                ];
                goto end_of_function;
            }

            if (!$recoveryRequest instanceof RecoveryPasswordRequest) {
                $return = [
                    'result' => false,
                    'success' => false,
                    'message' => 'RECOVERY_KEY_NOT_FOUND_TEXT'
                ];
                goto end_of_function;
            } else {
                if (strtotime($recoveryRequest->getCreatedAt()) + 86400 < time() || $recoveryRequest->getStatus() == 0) {
                    $return = [
                        'result' => false,
                        'success' => false,
                        'message' => 'RECOVERY_KEY_EXPIRED_TEXT'
                    ];
                    goto end_of_function;
                } else {
                    $userLogin = $recoveryRequest->getUserLogin();

                    if ($userLogin->getEmail() != $email) {
                        $return = [
                            'result' => false,
                            'success' => false,
                            'message' => 'LOGIN_INVALID_EMAIL_TEXT'
                        ];
                        goto end_of_function;
                    }

                    $this->db->begin();
                    $userLogin->setPassword($this->security->hash($password));
                    $resulSaveLogin = $userLogin->__quickUpdate();

                    if ($resulSaveLogin['success'] == false) {
                        $this->db->rollback();
                        $return = ["success" => false, "msg" => "DATA_SAVE_FAIL_TEXT"];
                        goto end_of_function;
                    }


                    $recoveryRequest->setStatus(RecoveryPasswordRequest::STATUS_NOT_ACTIVE);
                    $resultSave = $recoveryRequest->__quickUpdate();
                    if ($resultSave['success'] == false) {
                        $this->db->rollback();
                        $return = ["success" => false, "msg" => "DATA_SAVE_FAIL_TEXT"];
                        goto end_of_function;
                    }

                    $this->db->commit();
                    $user_profile = $userLogin->getUserProfile();
                    $employee = $userLogin->getEmployee();

                    if ($employee) {
                        $url_login = $userLogin->getApp()->getEmployeeUrl();
                    } elseif ($user_profile) {
                        $url_login = $userLogin->getApp()->getFrontendUrl();
                    }

                    $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                    $dataArray = [
                        //todo : 10 parameters maxi
                        'action' => "sendMail",
                        'to' => $userLogin->getEmail(),
                        'email' => $userLogin->getEmail(),
                        'user_login' => $userLogin->getEmail(),
                        'url_login' => $url_login,
                        'user_password' => $password,
                        'user_name' => ($user_profile ? $user_profile->getFullname() : $employee->getFullname()),
                        'templateName' => EmailTemplateDefault::SEND_NEW_PASSWORD,
                        'language' => SupportedLanguage::LANG_EN
                    ];
                    $resultCheck = $beanQueue->addQueue($dataArray);

                    $return = [
                        'result' => true,
                        'success' => true,
                        'resultCheck' => $resultCheck,
                        'data' => [],
                        'message' => 'PASSWORD_UPDATED_TEXT'
                    ];
                }
            }

        } else {
            $return = [
                'result' => false,
                'success' => false,
                'data' => [],
                'message' => 'POST_REQUEST_ONLY_TEXT'
            ];
            goto end_of_function;
        }


        end_of_function:
        //send email of edit password
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * check request
     * @return mixed
     */
    public function checkrequestAction()
    {
        $this->view->disable();
        $hash = Helpers::__getRequestValue('hash');
        $key_decode = base64_decode($hash);
        $recoveryRequest = RecoveryPasswordRequest::findFirstByHash($key_decode);

        if (!$recoveryRequest instanceof RecoveryPasswordRequest) {
            $return = [
                'result' => false,
                'success' => false,
                'message' => 'RECOVERY_KEY_NOT_FOUND_TEXT'
            ];
        } else {
            if (strtotime($recoveryRequest->getCreatedAt()) + 86400 < time() || $recoveryRequest->getStatus() == 0) {
                $return = [
                    'result' => false,
                    'success' => false,
                    'message' => 'RECOVERY_KEY_EXPIRED_TEXT',
                    'raw' => $recoveryRequest,
                ];
            } else {
                $user_data = $recoveryRequest->toArray();
                $return = [
                    'result' => true,
                    'success' => true,
                    'data' => $user_data,
                    'message' => 'ACTIVATION_KEY_EXIST_AND_VALID_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
