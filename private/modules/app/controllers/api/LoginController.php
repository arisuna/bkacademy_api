<?php

namespace SMXD\App\Controllers\API;

use \SMXD\App\Controllers\ModuleApiController;
use \SMXD\App\Models\UserLogin;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use SMXD\Application\Lib\EmailHelper;
use SMXD\Application\Lib\Helpers;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class LoginController extends ModuleApiController
{
    /**
     * @Route("/login", paths={module="app"}, methods={"GET"}, name="app-login-index")
     */
    public function indexAction()
    {

    }

    /**
     * [checkAction description]
     * @param string $hash [description]
     * @return [type]       [description]
     */
    public function checkAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $email = Helpers::__getRequestValue('email');
        $userProfileUuid = Helpers::__getRequestValue('uuid');

        $validation = new Validation();
        $validation->add(
            "email",
            new EmailValidator([
                "message" => "EMAIL_NOT_VALIDATE_TEXT",
            ])
        );
        $validation->add(
            "email",
            new PresenceOfValidator([
                "message" => "EMAIL_NOT_VALIDATE_TEXT",
            ])
        );
        $messages = $validation->validate(['email' => $email]);
        if (count($messages)) {
            foreach ($messages as $message) {
                $return = [
                    'success' => false,
                    'message' => (string)$message,
                ];
            }
        } else {
            $result = EmailHelper::__isAvailable($email, $userProfileUuid);
            //$result = UserLogin::ifEmailAvailable($email);

            if ($result == true) {
                $return = [
                    'success' => true,
                    'message' => 'EMAIL_AVAILABLE_TEXT',
                    'email' => $email,
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'EMAIL_NOT_AVAILABLE_TEXT',
                    'email' => $email,
                ];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
