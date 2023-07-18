<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\RelodayQueue;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;

use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\SupportedLanguage;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class PasswordController extends BaseController
{
    /**
     * @Route("/password", paths={module="gms"}, methods={"GET"}, name="gms-password-index")
     */
    public function indexAction()
    {

    }

    /**
     * generate new password for UserProfile
     */
    public function generatePasswordForUserAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $action = 'generate';
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $data = $this->request->getJsonRawBody();
        $login = isset($data->login) ? $data->login : null;
        $user_login_id = isset($data->user_login_id) ? $data->user_login_id : null;
        $user_login_uuid = isset($data->user_login_uuid) ? $data->user_login_uuid : null;

        $user_login = null;
        if ($login != '') {
            $user_login = UserLogin::findFirstByEmail($login);
        } elseif ($user_login_id > 0) {
            $user_login = UserLogin::findFirstById($user_login_id);
        } elseif ($user_login_uuid != '') {
            $user_login = UserLogin::findFirstByUuid($user_login_uuid);
        }

        if ($user_login && $user_login->managedByGMS()) {

            $resResetPassword = $user_login->resetPassword();
            if ($resResetPassword['success'] = true) {

                if ($user_login->isEmployee() == true) {
                    $url_login = $user_login->getApp()->getLoginUrlForEmployee();
                } else {
                    $url_login = $user_login->getApp()->getLoginUrl();
                }
                $data_email_send = [
                    'action' => 'send_new_password',
                    'data' => [
                        'password' => $resResetPassword['data'],
                        'url_login' => $url_login,
                        'user_login' => $user_login->toArray(),
                    ]
                ];

                $user_profile = $user_login->getUserProfile();
                if (!$user_profile) {
                    $user_profile = $user_login->getEmployee();
                }



                $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                $dataArray = [
                    'action' => "sendMail",
                    'to' => $user_login->getEmail(),
                    'email' => $user_login->getEmail(),
                    'url_login' => $user_login->getApp()->getEmployeeUrl(),
                    'user_login' => $user_login->getEmail(),
                    'user_password' => $resResetPassword['data'],
                    'user_name' => $user_profile->getFullname(),
                    'company_name' => $user_profile->getCompany()->getName(),
                    'templateName' => EmailTemplateDefault::SEND_NEW_PASSWORD,
                    'language' => ModuleModel::$system_language,
                ];

                $resultCheck = $beanQueue->addQueue($dataArray);

                $return = [
                    'success' => true,
                    'data' => $resResetPassword['data'],
                    'message' => 'RESET_PASSWORD_SUCCESS_TEXT',
                    'dataArray' => $dataArray
                ];
            } else {
                $return = $res;
            }

        } else {
            $return = [
                'success' => false,
                'message' => 'RESET_PASSWORD_FAIL_TEXT',
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();

    }


    /**
     * generate new password randomly
     */
    public function generatePasswordRandomAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
//        $this->checkAcl('generate', $this->router->getControllerName());
        $password = \Reloday\Application\Lib\Helpers::password();
        $return = ['success' => true, 'data' => $password];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
