<?php

namespace Reloday\Gms\Controllers\API;

use League\OAuth2\Client\Provider\GenericProvider;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use PHPMailer\PHPMailer\PHPMailer;
use Reloday\Application\Lib\AclHelper;
use Reloday\Gms\Models\CommunicationTopic;
use Reloday\Gms\Models\Contact;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\UserCommunicationEmail;
use \Reloday\Gms\Models\ModuleModel;
use \Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class CommunicationEmailController extends BaseController
{
    /**
     * @Route("/allowancetitle", paths={module="gms"}, methods={"GET"}, name="gms-allowancetitle-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);
        $templates = UserCommunicationEmail::loadByUserProfile();
        $results = ['success' => true, 'data' => $templates];
        $this->response->setJsonContent($results);
        $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        $uuid = Helpers::__getRequestValue('uuid');

        if ($uuid == '' || Helpers::__isValidUuid($uuid) == false) {
            $result = ["success" => false, "message" => "DATA_NOT_FOUND_TEXT"];
            goto end_of_function;
        }
        $userCommunicationEmail = UserCommunicationEmail::findFirstByUuid($uuid);
        if (!$userCommunicationEmail instanceof UserCommunicationEmail || $userCommunicationEmail->getUserProfileId() != ModuleModel::$user_profile->getId()) {
            $result = ["success" => false, "message" => "DATA_NOT_FOUND_TEXT"];
            goto end_of_function;
        }
        if ($userCommunicationEmail->getType() != UserCommunicationEmail::OTHER) {
            $result = ["success" => false, "message" => "DATA_NOT_FOUND_TEXT"];
            goto end_of_function;
        }
        $imap_email = Helpers::__getRequestValue("imap_email");
        $otheruserCommunicationEmail = UserCommunicationEmail::findFirst([
            "conditions" => "imap_email = :email: and uuid != :uuid:",
            "bind" => [
                "email" => $imap_email,
                "uuid" => $uuid
            ]
        ]);
        if ($otheruserCommunicationEmail instanceof UserCommunicationEmail && $userCommunicationEmail->belongsToMe()) {
            $result = ["success" => false, "message" => "SMTP_ACCOUNT_ACTIVATED"];
            goto end_of_function;
        }
        if ($otheruserCommunicationEmail instanceof UserCommunicationEmail && !$userCommunicationEmail->belongsToMe()) {
            $result = ["success" => false, "message" => "SMTP_ACCOUNT_ACTIVATED_BY_ANOTHER_USER_TEXT"];
            goto end_of_function;
        }
        $userCommunicationEmail->setData();
        $isSame = Helpers::__getRequestValue("same_email");
        if ($isSame) {
            $userCommunicationEmail->setSmtpEmail(Helpers::__getRequestValue("imap_email"));
            $userCommunicationEmail->setSmtpPassowrd(Helpers::__getRequestValue("imap_password"));
        }
        $smtp_auth_required = Helpers::__getRequestValue("smtp_auth_required");
        if ($smtp_auth_required) {
            $userCommunicationEmail->setSmtpAuthRequired(1);
        }

        $smtp_security = Helpers::__getRequestValue("smtp_security");
        $imap_security = Helpers::__getRequestValue("imap_security");

        if (!($smtp_security > 0)) {
            $smtp_port = Helpers::__getRequestValue("smtp_port");
            if ($smtp_port == 995) {
                $smtp_security = UserCommunicationEmail::SSL;
            } else if ($smtp_port == 993) {
                $smtp_security = UserCommunicationEmail::SSL;
            } else if ($smtp_port == 465) {
                $smtp_security = UserCommunicationEmail::SSL;
            } else if ($smtp_port == 587) {
                $smtp_security = UserCommunicationEmail::STARTTLS;
            }
            $userCommunicationEmail->setSmtpSecurity($smtp_security);
        }
        if (!($imap_security > 0)) {
            $imap_port = Helpers::__getRequestValue("imap_port");
            if ($imap_port == 995) {
                $imap_security = UserCommunicationEmail::SSL;
            } else if ($imap_port == 993) {
                $imap_security = UserCommunicationEmail::SSL;
            } else if ($imap_port == 465) {
                $imap_security = UserCommunicationEmail::SSL;
            } else if ($imap_port == 587) {
                $imap_security = UserCommunicationEmail::STARTTLS;
            }
            $userCommunicationEmail->setImapSecurity($imap_security);
        }
        $this->db->begin();
        $resultSave = $userCommunicationEmail->__quickUpdate();

        if ($resultSave['success'] == true) {
            $welcome_mail = new PHPMailer();
            $welcome_mail->IsSMTP();
            $welcome_mail->Host = $userCommunicationEmail->getSmtpAddress();
            $welcome_mail->Port = $userCommunicationEmail->getSmtpPort();
            $welcome_mail->SMTPAuth = $userCommunicationEmail->getSmtpAuthRequired();
            $welcome_mail->Username = $userCommunicationEmail->getSmtpEmail();
            $welcome_mail->Password = $userCommunicationEmail->getSmtpPassowrd();
            if ($userCommunicationEmail->getSmtpSecurity() != UserCommunicationEmail::NONE) {
                $welcome_mail->SMTPSecure = $userCommunicationEmail->getSmtpSecurity() == UserCommunicationEmail::TLS ? "tls" :
                    ($userCommunicationEmail->getSmtpSecurity() == UserCommunicationEmail::SSL ? "ssl" : "starttls");
            }
            $welcome_mail->From = $userCommunicationEmail->getSmtpEmail();
            $welcome_mail->addAddress($userCommunicationEmail->getSmtpEmail());
            $welcome_mail->addReplyTo(getenv("JIRA_USER"));
            $welcome_mail->IsHTML(true);
            $template = EmailTemplateDefault::__getTemplate(EmailTemplateDefault::SMTP_EMAIL_SETUP, ModuleModel::$language);
            $welcome_mail->Subject = $template['subject'];
            $welcome_mail->Body = $template['text'];
            if (!$welcome_mail->Send()) {
                try {
                    $this->db->rollback();
                } catch (\PDOException  $e) {
                    // just because my sql close transaction if that transaction idle in a timeout
                    // do nothing
                }
                $result = [
                    "success" => false,
                    "message" => "CONNECT_SMTP_ACCOUNT_FAIL_TEXT",
                    "detail" => $welcome_mail->ErrorInfo,
                ];
                goto end_of_function;
            }
            $this->db->commit();
            $result = ["success" => true, "message" => "CONNECT_SMTP_ACCOUNT_SUCCESSFULLY_TEXT", "data" => $userCommunicationEmail];
        } else {
            $this->db->rollback();
            $result = ["success" => false, "message" => "CONNECT_SMTP_ACCOUNT_FAIL_TEXT", "detail" => $resultSave['detail']];
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }


    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);
        $dataInput = Helpers::__getRequestValuesArray();

        $email = new UserCommunicationEmail();
        $email->setUserProfileId(ModuleModel::$user_profile->getId());
        $type = Helpers::__getRequestValue("type");
        if ($type == UserCommunicationEmail::OTHER) {
            $userCommunicationEmail = UserCommunicationEmail::findFirstByImapEmail(Helpers::__getRequestValue("imap_email"));
            if ($userCommunicationEmail && $userCommunicationEmail->belongsToMe()) {
                $result = ["success" => false, "message" => "SMTP_ACCOUNT_ACTIVATED"];
                goto end_of_function;
            } elseif ($userCommunicationEmail && $userCommunicationEmail->belongsToMe() == false) {
                //belongs to ANOTHER USER
                $result = ["success" => false, "message" => "SMTP_ACCOUNT_ACTIVATED_BY_ANOTHER_USER_TEXT"];
                goto end_of_function;
            } else {

                $email->setData($dataInput);
                $isSame = Helpers::__getRequestValue("same_email");
                if ($isSame) {
                    $email->setSmtpEmail(Helpers::__getRequestValue("imap_email"));
                    $email->setSmtpPassowrd(Helpers::__getRequestValue("imap_password"));
                }
                $smtp_auth_required = Helpers::__getRequestValue("smtp_auth_required");
                if ($smtp_auth_required) {
                    $email->setSmtpAuthRequired(1);
                }
                $smtp_security = Helpers::__getRequestValue("smtp_security");
                $imap_security = Helpers::__getRequestValue("imap_security");

                if (!($smtp_security > 0)) {
                    $smtp_port = Helpers::__getRequestValue("smtp_port");
                    if ($smtp_port == 995) {
                        $smtp_security = UserCommunicationEmail::SSL;
                    } else if ($smtp_port == 993) {
                        $smtp_security = UserCommunicationEmail::SSL;
                    } else if ($smtp_port == 465) {
                        $smtp_security = UserCommunicationEmail::SSL;
                    } else if ($smtp_port == 587) {
                        $smtp_security = UserCommunicationEmail::STARTTLS;
                    }
                    $email->setSmtpSecurity($smtp_security);
                }
                if (!($imap_security > 0)) {
                    $imap_port = Helpers::__getRequestValue("imap_port");
                    if ($imap_port == 995) {
                        $imap_security = UserCommunicationEmail::SSL;
                    } else if ($imap_port == 993) {
                        $imap_security = UserCommunicationEmail::SSL;
                    } else if ($imap_port == 465) {
                        $imap_security = UserCommunicationEmail::SSL;
                    } else if ($imap_port == 587) {
                        $imap_security = UserCommunicationEmail::STARTTLS;
                    }
                    $email->setImapSecurity($imap_security);
                }
                $this->db->begin();
                $resultSave = $email->__quickCreate();

                if ($resultSave['success'] == true) {
                    $welcome_mail = new PHPMailer();
                    $welcome_mail->IsSMTP();
                    $welcome_mail->Host = $email->getSmtpAddress();
                    $welcome_mail->Port = $email->getSmtpPort();
                    $welcome_mail->SMTPAuth = $email->getSmtpAuthRequired();
                    $welcome_mail->Username = $email->getSmtpEmail();
                    $welcome_mail->Password = $email->getSmtpPassowrd();
                    // $welcome_mail->SMTPDebug = 1;
                    if ($email->getSmtpSecurity() != UserCommunicationEmail::NONE) {
                        $welcome_mail->SMTPSecure = $email->getSmtpSecurity() == UserCommunicationEmail::TLS ? "tls" :
                            ($email->getSmtpSecurity() == UserCommunicationEmail::SSL ? "ssl" : "starttls");
                    }
                    $welcome_mail->From = $email->getSmtpEmail();
                    $welcome_mail->addAddress(getenv("JIRA_USER"));
                    // $welcome_mail->addReplyTo(getenv("JIRA_USER"));
                    $welcome_mail->IsHTML(true);
                    $template = EmailTemplateDefault::__getTemplate(EmailTemplateDefault::SMTP_EMAIL_SETUP, ModuleModel::$language);
                    $welcome_mail->Subject = $template['subject'];
                    $welcome_mail->Body = $template['text'];
                    if (!$welcome_mail->Send()) {
                        try {
                            $this->db->rollback();
                        } catch (\PDOException  $e) {
                            // just because my sql close transaction if that transaction idle in a timeout
                            // do nothing
                        }
                        $result = [
                            "success" => false,
                            "message" => "CONNECT_SMTP_ACCOUNT_FAIL_TEXT",
                            "detail" => $welcome_mail->ErrorInfo,
                        ];
                        goto end_of_function;
                    }
                    $threads = CommunicationTopic::find([
                        "conditions" => "owner_user_profile_id = :user_profile_id: and first_sender_email = :sender_email:",
                        "bind" => [
                            "user_profile_id" => ModuleModel::$user_profile->getId(),
                            "sender_email" => $email->getImapEmail()
                        ]
                    ]);
                    if(count($threads) > 0){
                        foreach ($threads as $thread){
                            $thread->setFirstSenderUserCommunicationEmailId($email->getId());
                            $updateThread = $thread->__quickUpdate();
                            if(!$resultSave['success']){
                                $this->db->rollback();
                                $result = [
                                    "success" => false,
                                    "message" => "CONNECT_SMTP_ACCOUNT_FAIL_TEXT",
                                    "detail" => $updateThread
                                ];
                                goto end_of_function;
                            }
                        }
                    }
                    $this->db->commit();
                    $result = ["success" => true, "message" => "CONNECT_SMTP_ACCOUNT_SUCCESSFULLY_TEXT", "data" => $email];
                } else {
                    $this->db->rollback();
                    $result = [
                        "success" => false,
                        "message" => "CONNECT_SMTP_ACCOUNT_FAIL_TEXT",
                        "detail" => isset($resultSave['errorMessage']) ? $resultSave['errorMessage'] : null,
                    ];
                    goto end_of_function;
                }
            }
        }
        else if ($type == UserCommunicationEmail::GOOGLE) {
            $email->setData($dataInput);
            $client = new \Google_Client();
            $client->setApplicationName(getenv("GOOGLE_APPLICATION_NAME"));
            $client->setScopes([\Google_Service_Gmail::GMAIL_COMPOSE, \Google_Service_Gmail::GMAIL_READONLY, \Google_Service_Oauth2::USERINFO_EMAIL]);

            $client->setClientId(getenv("GOOGLE_CLIENT_ID"));
            $client->setClientSecret(getenv("GOOGLE_CLIENT_SECRET"));
            $client->setRedirectUri(getenv("GOOGLE_REDIRECT_URL"));
            $client->setAccessType("offline");
            $client->setApprovalPrompt("force");
            $client->setPrompt("consent");
            try {
                $token = $client->fetchAccessTokenWithAuthCode($email->getAuthCode());
                $email->setToken(json_encode($token));
//                $client->setAccessToken($token);
                $google_oauth = new \Google_Service_Oauth2($client);
                $google_account = $google_oauth->userinfo->get();
                $google_account_email = $google_oauth->userinfo->get()->email;
                $display_name = $google_oauth->userinfo->get()->name;
                $email->setDisplayName($display_name);
                $email->setImapEmail($google_account_email);
                $email->setSmtpEmail($google_account_email);
                $email->setImapPassword('none');
                $email->setSmtpPassowrd('none');

                $userCommunicationEmail = UserCommunicationEmail::findFirstByImapEmail($google_account_email);
                if ($userCommunicationEmail && $userCommunicationEmail->belongsToMe()) {
                    $result = ["success" => false, "message" => "GOOGLE_ACCOUNT_ACTIVATED"];
                    goto end_of_function;
                } elseif ($userCommunicationEmail && $userCommunicationEmail->belongsToMe() == false) {
                    //belongs to ANOTHER USER
                    $result = ["success" => false, "message" => "GOOGLE_ACCOUNT_ACTIVATED_BY_ANOTHER_USER_TEXT"];
                    goto end_of_function;
                } else {
                    $this->db->begin();
                    $resultSave = $email->__quickCreate();
                    if ($resultSave['success'] == true) {
                        $threads = CommunicationTopic::find([
                            "conditions" => "owner_user_profile_id = :user_profile_id: and first_sender_email = :sender_email:",
                            "bind" => [
                                "user_profile_id" => ModuleModel::$user_profile->getId(),
                                "sender_email" => $email->getImapEmail()
                            ]
                        ]);
                        if(count($threads) > 0){
                            foreach ($threads as $thread){
                                $thread->setFirstSenderUserCommunicationEmailId($email->getId());
                                $updateThread = $thread->__quickUpdate();
                                if(!$resultSave['success']){
                                    $this->db->rollback();
                                    $result = [
                                        "success" => false,
                                        "message" => "CONNECT_SMTP_ACCOUNT_FAIL_TEXT",
                                        "detail" => $updateThread
                                    ];
                                    goto end_of_function;
                                }
                            }
                        }
                        $this->db->commit();
                        $result = ["success" => true, "message" => "CONNECT_GOOGLE_ACCOUNT_SUCCESSFULLY_TEXT", "data" => $email->toArray()];
                        goto end_of_function;
                    } else {
                        $this->db->rollback();
                        $result = ["success" => false, "message" => "CONNECT_GOOGLE_ACCOUNT_FAIL_TEXT", "detail" => $resultSave['detail'], "data" => $email, "token" => $token, "account" => $google_account];
                        goto end_of_function;
                    }
                }
            } catch (\Exception $e) {
                $result = ["success" => false, "message" => "CONNECT_GOOGLE_ACCOUNT_FAIL_TEXT", "detail" => $e->getMessage(), "token" => $token];
                goto end_of_function;
            }
        }
        else if ($type == UserCommunicationEmail::OUTLOOK) {
            $email->setData($dataInput);

            $client = new GenericProvider([
                'clientId' => getenv("OUTLOOK_APPLICATION_ID"),
                'clientSecret' => getenv("OUTLOOCK_APPLICATION_PASSWORD"),
                'redirectUri' => getenv("OUTLOOK_REDIRECT_URL"),
                'urlAuthorize' => getenv("OAUTH_AUTHORITY") . getenv("OAUTH_AUTHORIZE_ENDPOINT"),
                'urlAccessToken' => getenv("OAUTH_AUTHORITY") . getenv("OAUTH_TOKEN_ENDPOINT"),
                'urlResourceOwnerDetails' => '',
                'scopes' => getenv("OAUTH_SCOPES")
            ]);
            try {
                $token = $client->getAccessToken('authorization_code', ['code' => $email->getAuthCode()]);
                $email->setToken(json_encode($token));
                $graph = new Graph();
                $graph->setAccessToken($token->getToken());
                $user = $graph->createRequest('GET', '/me')->setReturnType(Model\User::class)->execute();
                $email->setDisplayName($user->getDisplayName());
                $email->setImapEmail($user->getMail());
                $email->setSmtpEmail($user->getMail());
                $email->setImapPassword('none');
                $email->setSmtpPassowrd('none');
                $userCommunicationEmail = UserCommunicationEmail::findFirstByImapEmail($user->getMail());
                if ($userCommunicationEmail && $userCommunicationEmail->belongsToMe()) {
                    $result = ["success" => false, "message" => "OUTLOOK_ACCOUNT_ACTIVATED"];
                    goto end_of_function;
                } elseif ($userCommunicationEmail && $userCommunicationEmail->belongsToMe() == false) {
                    //belongs to ANOTHER USER
                    $result = ["success" => false, "message" => "OUTLOOK_ACCOUNT_ACTIVATED_BY_ANOTHER_USER_TEXT"];
                    goto end_of_function;
                } else {
                    $this->db->begin();
                    $resultSave = $email->__quickCreate();
                    if ($resultSave['success'] == true) {
                        $threads = CommunicationTopic::find([
                            "conditions" => "owner_user_profile_id = :user_profile_id: and first_sender_email = :sender_email:",
                            "bind" => [
                                "user_profile_id" => ModuleModel::$user_profile->getId(),
                                "sender_email" => $email->getImapEmail()
                            ]
                        ]);
                        if(count($threads) > 0){
                            foreach ($threads as $thread){
                                $thread->setFirstSenderUserCommunicationEmailId($email->getId());
                                $updateThread = $thread->__quickUpdate();
                                if(!$resultSave['success']){
                                    $this->db->rollback();
                                    $result = [
                                        "success" => false,
                                        "message" => "CONNECT_SMTP_ACCOUNT_FAIL_TEXT",
                                        "detail" => $updateThread
                                    ];
                                    goto end_of_function;
                                }
                            }
                        }
                        $this->db->commit();
                        $result = ["success" => true, "message" => "CONNECT_OUTLOOK_ACCOUNT_SUCCESSFULLY_TEXT", "data" => $email->toArray()];
                    } else {
                        $this->db->rollback();
                        $result = [
                            "success" => false,
                            "message" => "CONNECT_OUTLOOK_ACCOUNT_FAIL_TEXT",
                            "data" => $email,
                            "token" => $token,
                            "account" => $user
                        ];
                        goto end_of_function;
                    }
                }
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                $result = [
                    "success" => false,
                    "message" => "CONNECT_OUTLOOK_ACCOUNT_FAIL_TEXT",
                    "detail" => $e->getMessage(),
                    "token" => isset($token) ? $token : null,
                ];
                goto end_of_function;
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function getGoogleAuthUrlAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);
        $client = new \Google_Client();
        $client->setApplicationName(getenv("GOOGLE_APPLICATION_NAME"));
        $client->setScopes([\Google_Service_Gmail::GMAIL_COMPOSE, \Google_Service_Gmail::GMAIL_READONLY, \Google_Service_Oauth2::USERINFO_EMAIL]);
        $client->setClientId(getenv("GOOGLE_CLIENT_ID"));
        $client->setClientSecret(getenv("GOOGLE_CLIENT_SECRET"));
        $client->setAccessType("offline");
        $client->setApprovalPrompt("force");
        $client->setPrompt("consent");
        $client->setRedirectUri(getenv("GOOGLE_REDIRECT_URL"));
        $authUrl = $client->createAuthUrl();
        $result = ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "url" => $authUrl];
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function getOutlookAuthUrlAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);
        $client = new GenericProvider([
            'clientId' => getenv("OUTLOOK_APPLICATION_ID"),
            'clientSecret' => getenv("OUTLOOCK_APPLICATION_PASSWORD"),
            'redirectUri' => getenv("OUTLOOK_REDIRECT_URL"),
            'urlAuthorize' => getenv("OAUTH_AUTHORITY") . getenv("OAUTH_AUTHORIZE_ENDPOINT"),
            'urlAccessToken' => getenv("OAUTH_AUTHORITY") . getenv("OAUTH_TOKEN_ENDPOINT"),
            'urlResourceOwnerDetails' => '',
            'scopes' => getenv("OAUTH_SCOPES")
        ]);
        $authUrl = $client->getAuthorizationUrl();
        $result = ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "url" => $authUrl];
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $id : id for allowance title
     */
    public function itemAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        if (is_numeric($id) && $id > 0) {
            $email = UserCommunicationEmail::findFirstById($id);
            if ($email && $email instanceof UserCommunicationEmail && $email->belongsToGms() == true) {
                $return = [
                    'success' => true,
                    'message' => 'DATA_FOUND_SUCCESS_TEXT',
                    'data' => $email
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function deactiveAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        $id = Helpers::__getRequestValue('id');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (isset($id) && is_numeric($id) && $id > 0) {
            $email = UserCommunicationEmail::findFirstById($id);
            if ($email && $email instanceof UserCommunicationEmail && $email->belongsToMe() == true) {

                $email->setStatus(UserCommunicationEmail::STATUS_ARCHIVED);
                $resultSave = $email->__quickUpdate();

                if ($resultSave['success'] == true) {

                    $result = ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $email];
                } else {
                    $result = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $resultSave['detail']];
                    goto end_of_function;
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function activeAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        $id = Helpers::__getRequestValue('id');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (isset($id) && is_numeric($id) && $id > 0) {
            $email = UserCommunicationEmail::findFirstById($id);
            if ($email && $email instanceof UserCommunicationEmail && $email->belongsToMe() == true) {

                $email->setStatus(UserCommunicationEmail::STATUS_ACTIVE);
                $resultSave = $email->__quickUpdate();

                if ($resultSave['success'] == true) {

                    $result = ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $email];
                } else {
                    $result = ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $resultSave['detail']];
                    goto end_of_function;
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        $return = [
            'success' => false,
            'message' => 'EMAIL_DELETED_FAIL_TEXT'
        ];

        if (is_numeric($id) && $id > 0) {
            $email = UserCommunicationEmail::findFirstById($id);
            if ($email && $email instanceof UserCommunicationEmail && $email->belongsToGms() == true) {
                $return = $email->__quickRemove();
                if ($return['success'] == true) {
                    $return['message'] = 'EMAIL_DELETED_SUCCESS_TEXT';
                }
            } else {
                $return = [
                    'success' => false,
                    'message' => 'EMAIL_DELETED_FAIL_TEXT'
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function updateSignatureAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);

        $id = Helpers::__getRequestValue('id');
        $signature = Helpers::__getRequestValue('signature');
        if(isset($signature) && $signature != null && $signature != ''){
            $signature = rawurldecode(base64_decode($signature));
        }

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (isset($id) && is_numeric($id) && $id > 0) {
            $email = UserCommunicationEmail::findFirstById($id);
            if ($email && $email instanceof UserCommunicationEmail && $email->belongsToGms() == true) {
                $email->setSignature($signature);

                $resultSave = $email->__quickUpdate();

                if ($resultSave['success'] == true) {
                    $result = ["success" => true, "message" => "SIGNATURE_SAVE_SUCCESS_TEXT", "data" => $email];
                } else {
                    $result = $resultSave;
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'SIGNATURE_SAVE_FAIL_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/allowancetitle", paths={module="gms"}, methods={"GET"}, name="gms-allowancetitle-index")
     */
    public function getMailsAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);
        $templates = UserCommunicationEmail::getMailsByUserProfile();
        $results = ['success' => true, 'data' => $templates];
        $this->response->setJsonContent($results);
        $this->response->send();
    }

    /**
     * @Route("/allowancetitle", paths={module="gms"}, methods={"GET"}, name="gms-allowancetitle-index")
     */
    public function checkTokenExpiredAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex(AclHelper::CONTROLLER_COMMUNICATION);
        $templates = UserCommunicationEmail::checkIfTokenExpired();
        $results = ['success' => true, 'data' => $templates];
        $this->response->setJsonContent($results);
        $this->response->send();
    }
}
