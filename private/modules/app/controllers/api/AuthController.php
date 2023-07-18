<?php

namespace Reloday\App\Controllers\API;

use Elasticsearch\Endpoints\Cat\Help;
use Phalcon\Acl\Adapter\Memory;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Security\Random;
use Phalcon\Http\Response;
use Phalcon\Utils\Slug as Slug;

use Reloday\App\Controllers\ModuleApiController;
use Reloday\App\Models\UserProfile;
use Reloday\App\Models\App;
use Reloday\App\Models\UserLogin;
use Reloday\App\Models\UserLoginToken;
use Reloday\App\Models\UserRequestToken;
use Reloday\App\Models\EmailTemplateDefault;
use Reloday\App\Models\SupportedLanguage;
use Reloday\App\Models\Company;
use Reloday\Application\Lib\ChargeBeeHelper;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Application\Models;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Validation\CreateAccountValidation;
use Reloday\Application\Validation\CreateAccountAppValidation;


use \Firebase\JWT\JWT;
use Reloday\App\Models\ModuleModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class AuthController extends ModuleApiController
{
    /**
     * @param string $params
     */
    public function saveRegisterRequestAction($params = '')
    {
        //@TODO need capchat for protection

        $this->view->disable();
        $this->checkAjaxPost();

        $appConfig = $this->getDi()->getShared('appConfig');
        $secret = $appConfig->captcha->secret_key;
        $gRecaptchaResponse = Helpers::__getRequestValue('g-recaptcha-response');
        $remoteIp = $this->request->getClientAddress();

        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        $resp = $recaptcha->verify($gRecaptchaResponse, $remoteIp);
        if ($resp->isSuccess()) {
            // verified!
            // if Domain Name Validation turned off don't forget to check hostname field
            // if($resp->getHostName() === $_SERVER['SERVER_NAME']) {  }
            // nothing to check
        } else {
            $errors = $resp->getErrorCodes();
            $return = [
                'success' => false,
                'message' => 'ERROR_SESSION_EXPIRED_TEXT'
            ];

            goto end_of_function;
        }

        $user_data = $this->request->getJsonRawBody();
        $email = strtolower(Helpers::__getRequestValue('email'));
        $app_type_id = Helpers::__getRequestValue('app_type');
        $firstname = Helpers::__getRequestValue('firstname');
        $lastname = Helpers::__getRequestValue('lastname');
        $company_name = Helpers::__getRequestValue('company');
        $telephone = Helpers::__getRequestValue('telephone');
        $language = Helpers::__getRequestValue('language');
        $language = $language != '' && in_array($language, SupportedLanguage::$languages) ? $language : SupportedLanguage::LANG_EN;
        /************* check validation of create account ************/
        $paramsData = [
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'company' => $company_name,
            'app_type' => $app_type_id,
            'telephone' => $telephone
        ];
        $validation = new CreateAccountValidation();

        $messagesValidation = $validation->validate($paramsData);

        if (count($messagesValidation) > 0) {
            $return = [
                'detail' => $messagesValidation,
                'success' => false,
                'message' => 'CREATE_REQUEST_TOKEN_FAIL_TEXT'
            ];
            goto end_of_function;
        }

        if (isset(App::$appTypeRegister[$app_type_id])) {
            $email_available = UserLogin::ifEmailAvailable($email);

            if ($email_available == true) {

                $request_token = new UserRequestToken();
                $request_token->setHash($this->security->hash($email));
                $request_token->setEndedAt(date('Y-m-d H:i:s', time() + $this->config->application->request_token_expired * 3600));
                $request_token->setData(json_encode($user_data));
                $request_token->setStatus(UserRequestToken::STATUS_ACTIVE);

                try {
                    if (!$request_token->save()) {
                        $return = [
                            'success' => false,
                            'message' => 'CREATE_REQUEST_TOKEN_FAIL_TEXT'
                        ];
                        goto end_of_function;
                    }
                } catch (\PDOException $e) {
                    $return = [
                        'detail' => $e->getMessage(),
                        'success' => false,
                        'message' => 'CREATE_REQUEST_TOKEN_FAIL_TEXT'
                    ];
                    goto end_of_function;

                } catch (Exception $e) {
                    $return = [
                        'detail' => $e->getMessage(),
                        'success' => false,
                        'message' => 'CREATE_REQUEST_TOKEN_FAIL_TEXT'
                    ];
                    goto end_of_function;
                }

                $urlActive = RelodayUrlHelper::__getMainUrl() . '/register/#/check/' . base64_encode($request_token->getHash());
                $destinationEmail = $email;

                $beanQueue = RelodayQueue::__getQueueSendMail();
                $dataArray = [
                    'action' => "sendMail",
                    'to' => $destinationEmail,
                    'email' => $destinationEmail,
                    'url' => $urlActive,
                    'user_name' => $firstname . " " . $lastname,
                    'company_name' => $company_name,
                    'templateName' => EmailTemplateDefault::CONFIRM_APPLICATION,
                    'language' => $language
                ];

                if ($destinationEmail && Helpers::__isEmail($destinationEmail)) {
                    $resultCheck = $beanQueue->addQueue($dataArray);

                    if ($resultCheck['success'] == true) {
                        $return = [
                            'success' => true,
                            '$resultCheck' => $resultCheck,
                            'message' => 'SEND_MAIL_CONFIRM_SUCCESS_TEXT'
                        ];
                    } else {
                        $return = [
                            'success' => false,
                            'message' => 'ERROR_CONFIRM_YOUR_EMAIL_TEXT'
                        ];
                    }

                } else {
                    $return = [
                        'success' => false,
                        'message' => 'ERROR_CONFIRM_YOUR_EMAIL_TEXT'
                    ];
                }
            } else {
                $return = [
                    'result' => false,
                    'success' => false,
                    'message' => 'EMAIL_LOGIN_EXIST_TEXT'
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * verify an user request token
     * if the token is validated --> show form and create app and user
     */
    public function verificationAction($encode_key = '')
    {
        $this->view->disable();
        $key_decode = base64_decode($encode_key);
        $user_request_token = UserRequestToken::findFirstByHash($key_decode);
        if (!$user_request_token instanceof UserRequestToken) {
            $this->flashSession->warning($this->closeMessageBox . 'ACTIVATION KEY NOT FOUND');
        } else {
            if (strtotime($user_request_token->getEndedAt()) < time()) {
                $this->flashSession->warning($this->closeMessageBox . 'KEY HAS EXPIRED');
            } else {

            }
        }
        $this->view->setVars([]);
    }


    /** check request **/
    public function checkRegisterRequestTokenAction()
    {
        $this->view->disable();
        $hash = Helpers::__getRequestValue('hash');

        $key_decode = base64_decode($hash);
        $user_request_token = UserRequestToken::findFirstByHash($key_decode);

        if (!$user_request_token instanceof UserRequestToken) {
            $return = [
                'result' => false,
                'success' => false,
                'message' => 'ACTIVATION_KEY_NOT_FOUND_TEXT'
            ];
        } else {
            if (strtotime($user_request_token->getEndedAt()) < time() || $user_request_token->getStatus() != UserRequestToken::STATUS_ACTIVE) {
                $return = [
                    'success' => false,
                    'message' => 'ACTIVATION_KEY_EXPIRED_TEXT'
                ];
            } else {
                $user_data = $user_request_token->getDataFromJson();
                $return = [
                    'success' => true,
                    'data' => $user_data,
                    'message' => 'ACTIVATION_KEY_EXIST_AND_VALID_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /** check request **/
    public function checkInvitationRequestTokenAction()
    {
        $this->view->disable();
        $hash = Helpers::__getRequestValue('hash');

        $key_decode = base64_decode($hash);
        $user_request_token = Models\InvitationRequestExt::findFirstByToken($key_decode);

        if (!$user_request_token instanceof Models\InvitationRequestExt) {
            $return = [
                'result' => false,
                'success' => false,
                'data' => $user_request_token,
                'key_decode' => $key_decode,
                'message' => 'INVITATION_REQUEST_NOT_FOUND_TEXT'
            ];
        } else {
            if ($user_request_token->getStatus() == Models\InvitationRequestExt::STATUS_SUCCESS) {
                $return = [
                    'result' => false,
                    'success' => false,
                    'data' => $user_request_token,
                    'key_decode' => $key_decode,
                    'message' => 'INVITATION_REQUEST_ALREADY_SUCCESS_TEXT'
                ];
            } else {
                $app_type = $user_request_token->isFromDsp() ? Models\CompanyTypeExt::TYPE_HR : Models\CompanyTypeExt::TYPE_GMS;
                $plan = Models\PlanExt::findFirst([
                    "conditions" => "status = :status:  and company_type_id = :company_type_id: and is_default = 1",
                    "bind" => [
                        "status" => Models\PlanExt::STATUS_ACTIVE,
                        "company_type_id" => $app_type,
                    ]
                ]);
                if (!$plan instanceof Models\PlanExt) {
                    $plan = Models\PlanExt::findFirst([
                        "conditions" => "status = :status:  and company_type_id = :company_type_id:",
                        "bind" => [
                            "status" => Models\PlanExt::STATUS_ACTIVE,
                            "company_type_id" => $app_type,
                        ]
                    ]);
                    if (!$plan instanceof Models\PlanExt) {
                        $return = [
                            'success' => false,
                            'message' => "DATA_NOT_FOUND_TEXT",
                            'detail' => $plan,
                        ];
                        goto end_of_function;
                    }
                }
                $item = $plan->toArray();
                $item['price'] = round($plan->getPrice() / 100, 0);
                $item["content"] = [];
                $plan_contents = $plan->getPlanContents();
                if (count($plan_contents) > 0) {
                    foreach ($plan_contents as $plan_content) {

                        $item["content"][$plan_content->getLanguage()] = $plan_content->toArray();
                        $item["content"][$plan_content->getLanguage()]["features"] = json_decode($plan_content->getFeatures());
                        $item["content"][$plan_content->getLanguage()]["invitation_content"] = json_decode($plan_content->getInvitationContent());
                    }
                }
                $return = [
                    'success' => true,
                    'data' => $user_request_token,
                    'plan' => $item,
                    'message' => 'ACTIVATION_KEY_EXIST_AND_VALID_TEXT'
                ];
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * crete application and many data (user / company / login)
     * @return [type] [description]
     */
    public function createApplicationAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();


        $data_request = $this->request->getJsonRawBody();

        $hash = Helpers::__getRequestValue('hash');
        $company_name = Helpers::__getRequestValue('company');
        $url = Helpers::__getRequestValue('url');
        $user_email = Helpers::__getRequestValue('email');
        $password = Helpers::__getRequestValue('new_password');
        $new_password_confirm = Helpers::__getRequestValue('new_password_confirm');

        if($password != $new_password_confirm){
            $return = [
                'success' => false,
                'password' => $password,
                'new_password_confirm' => $new_password_confirm,
                'message' => "ERROR_APP_SAVE_FAILED_TEXT"
            ];
            goto end_of_function;
        }
//        $resultValidationPassword = Helpers::__validatePassword($password);
//        if ($resultValidationPassword['success'] == false) {
//            $return = ['detail' => $password, 'success' => false, 'message' => 'PASSWORD_TOO_WEAK_TEXT'];
//            goto end_of_function;
//        }
        /**** pre-validation ***/
        $paramsData = [
            'hash' => $hash,
            'company' => $company_name,
            'url' => $url,
        ];
        $validation = new CreateAccountAppValidation();
        $messagesValidation = $validation->validate($paramsData);
        if (count($messagesValidation) > 0) {
            $return = [
                'success' => false,
                'detail' => $validation->getMessagesArray(),
                'message' => $validation->getFirstMessage()
            ];
            goto end_of_function;
        }
        /**** end of pre-validation ***/

        $key_decode = base64_decode($hash);
        $userRequestToken = UserRequestToken::findFirstByHash($key_decode);
        if ($userRequestToken instanceof UserRequestToken && $userRequestToken->isValid()) {
            $userRequestTokenData = $userRequestToken->getDataFromJson();
            $firstname = $userRequestTokenData->firstname;
            $lastname = $userRequestTokenData->lastname;
            $email = $userRequestTokenData->email;
            $app_type = $userRequestTokenData->app_type;
            $plan_id = $userRequestTokenData->plan_id;
            $language=$userRequestTokenData->language;
            $telephone = $userRequestTokenData->telephone;
            $language=$language != '' && in_array($language, SupportedLanguage::$languages) ? $language : SupportedLanguage::LANG_EN;
            $email_available = UserLogin::ifEmailAvailable($userRequestTokenData->email);

            if ($email_available == true) {

                $this->db->begin();
                $random = new Random();

                //CREATE APP
                $app = new App();
                $app->setUuid($random->uuid());
                $app->setHash($random->base64Safe());
                $app->setName($company_name);
                $app->setUrl($url);
                $app->setLanguage($language);
                $app->setCompanyId(0);

                if ($userRequestTokenData->app_type == App::APP_TYPE_HR) {
                    $app->setType(App::APP_TYPE_HR);
                } elseif ($userRequestTokenData->app_type == App::APP_TYPE_GMS) {
                    $app->setType(App::APP_TYPE_GMS);
                } elseif ($userRequestTokenData->app_type == App::APP_TYPE_EE) {
                    $app->setType(App::APP_TYPE_EE);
                }
                //--------------------------create application------------------------

                $resultApp = $app->__quickCreate();
                if ($resultApp['success'] == false) {
                    $this->db->rollback();
                    $return  = $resultApp;
//                    $return = [
//                        'result' => false,
//                        'success' => false,
//                        'message' => "ERROR_APP_SAVE_FAILED_TEXT",
//                        'detail' => $resultApp['detail']
//                    ];
                    goto end_of_function;
                }


                //--------------------------create company------------------------

                $company = new Company();
                $company->setUuid($random->uuid());
                $company->setName($company_name);
                $company->setEmail($email);
                $company->setAppId($app->getId());
                $company->setPhone($telephone);
                $company->setStatus(Company::STATUS_ACTIVATED);
                if ($userRequestTokenData->app_type == 1) {
                    $company->setCompanyTypeId(Company::TYPE_HR);
                } elseif ($userRequestTokenData->app_type == 2) {
                    $company->setCompanyTypeId(Company::TYPE_GMS);
                } else {
                    $company->setCompanyTypeId(0);
                }

                $resultCompany = $company->__quickCreate();
                if ($resultCompany['success'] == false) {
                    $this->db->rollback(); //rollback
                    $return  = $resultCompany;
//                    $return = [
//                        'success' => false,
//                        'message' => "ERROR_COMPANY_SAVE_FAILED_TEXT",
//                        'detail' => $resultCompany,
//                        'object' => $company
//                    ];
                    goto end_of_function;
                }

                //--------------------------create user_login------------------------
                $userLoginData = [
                    'email' => $user_email,
                    'password' => $password,
                    'app_id' => $app->getId(),
                    'user_group_id' => $userRequestTokenData->app_type == App::APP_TYPE_HR ? Models\UserLoginExt::USER_GROUP_HR_ADMIN :
                        ($userRequestTokenData->app_type == App::APP_TYPE_GMS ? Models\UserLoginExt::USER_GROUP_GMS_ADMIN : 0)
                ];

                $user_login = UserLogin::__createNewUserLogin($userLoginData);
                if ($user_login instanceof UserLogin) {
                    //continue to next step
                } else {
                    $this->db->rollback();
                    $return = $user_login;
                    goto end_of_function;
                }

                //--------------------------create user profile------------------------
                $user_profile = new UserProfile();
                $user_profile->setUuid($random->uuid());
                $user_profile->setWorkemail($user_email);
                $user_profile->setFirstname($firstname);
                $user_profile->setLastname($lastname);
                $user_profile->setStatus(Models\UserLoginExt::STATUS_ACTIVATED);
                $user_profile->setUserLoginId($user_login->getId());
                $user_profile->setCompanyId($company->getId());
                $user_profile->setActive(UserProfile::ACTIVATED); // default activated
                $user_profile->setHasAccessStatus();

                $nickname = $user_profile->generateNicknameFirst();
                $user_profile->setNickname($nickname);

                if ($userRequestTokenData->app_type == 1) {
                    $user_profile->setUserGroupId(Models\UserLoginExt::USER_GROUP_HR_ADMIN);
                } elseif ($userRequestTokenData->app_type == 2) {
                    $user_profile->setUserGroupId(Models\UserLoginExt::USER_GROUP_GMS_ADMIN);
                }

                $resultUserProfile = $user_profile->__quickCreate();

                if ($resultUserProfile['success'] == false) {
                    $this->db->rollback(); //rollback
                    $return  = $resultUserProfile;
//                    $return = [
//                        'success' => false,
//                        'message' => "ERROR_USER_PROFILE_TEXT",
//                        'detail' => $resultUserProfile,
//                    ];
                    goto end_of_function;
                }

                //---------------------------create ChargeBee Subscription--------------

                if ($plan_id > 0) {
                    $plan = Models\PlanExt::findFirst([
                        "conditions" => "id = :plan_id: and status = :status:  and company_type_id = :company_type_id:",
                        "bind" => [
                            "plan_id" => $plan_id,
                            "status" => Models\PlanExt::STATUS_ACTIVE,
                            "company_type_id" => $company->getCompanyTypeId(),
                        ]
                    ]);
                } else {
                    $plan = Models\PlanExt::findFirst([
                        "conditions" => "status = :status:  and company_type_id = :company_type_id: and is_default = 1",
                        "bind" => [
                            "status" => Models\PlanExt::STATUS_ACTIVE,
                            "company_type_id" => $company->getCompanyTypeId(),
                        ]
                    ]);
                }
                if (!$plan instanceof Models\PlanExt) {
                    $this->db->rollback(); //rollback
                    $return = [
                        'success' => false,
                        'message' => "ERROR_UPDATE_DATA_TEXT",
                        'detail' => $plan,
                    ];
                    goto end_of_function;
                }
                $chargebeeHelper = new ChargeBeeHelper();
                $customer = $chargebeeHelper->createCustomer([
                    "firstName" => $firstname,
                    "lastName" => $lastname,
                    "company" => $company_name,
                    "email" => $email,
                ]);
                $chargebee_subscription = $chargebeeHelper->createSupscription($customer->id, $plan->getChargebeeReferenceId());
//                $chargebee_subscription - $chargebeeHelper->getSubscription($customer->id);
                $chargebeeCustomer = new Models\ChargebeeCustomerExt();
                $chargebeeCustomer->setUuid($random->uuid());
                $chargebeeCustomer->setCompanyId($company->getId());
                $chargebeeCustomer->setUserProfileId($user_profile->getId());
                $chargebeeCustomer->setChargebeeReferenceId($customer->id);
                $resultCustomer = $chargebeeCustomer->__quickCreate();

                if ($resultCustomer['success'] == false) {
                    $this->db->rollback(); //rollback
                    $return  = $resultCustomer;
//                    $return = [
//                        'success' => false,
//                        'message' => "ERROR_UPDATE_DATA_TEXT",
//                        'customer' => $resultCustomer,
//                        'customer1' => $customer
//                    ];
                    goto end_of_function;
                }

                $subscription = new Models\SubscriptionExt();
                $subscription->setUuid($random->uuid());
                $subscription->setPlanId($plan->getId());
                $subscription->setCompanyId($company->getId());
                $subscription->setStatus(Models\SubscriptionExt::STATUS_LIST[$chargebee_subscription->status]);
                $subscription->setChargebeeReferenceId($chargebee_subscription->id);
                $subscription->setIsTrial($subscription->getStatus() == Models\SubscriptionExt::STATUS_TRIAL ? 1 : 0);
                if ($subscription->getIsTrial() == 1) {
                    $now = time();
                    $subscription->setExpiredDate($now + 30 * 60 * 60 * 24);
                }
                $subscription->setNextPaymentDate($chargebee_subscription->nextBillingAt);
                $resultSubscription = $subscription->__quickCreate();
                if ($resultSubscription['success'] == false) {
                    $this->db->rollback(); //rollback
                    $return  = $resultSubscription;
//                    $return = [
//                        'success' => false,
//                        'message' => "ERROR_UPDATE_DATA_TEXT",
//                        'subscription' => $resultSubscription,
//                    ];
                    goto end_of_function;
                }


                $registerCognitoResult = Models\ApplicationModel::__addNewUserCognito([
                    'email' => $user_email,
                    'password' => $password,
                    'temporary_password' => Helpers::password(16),
                    'loginUrl' => $app->getLoginUrl()
                ], $user_login, true);


                if ($registerCognitoResult['success'] == false) {
                    $return = [
                        'success' => false,
                        'message' => "ERROR_AWS_TEXT",
                        'detail' => $registerCognitoResult,
                    ];
                    $this->db->rollback();
                    goto end_of_function;
                }

                //--------------------------edit user request token-----------------------

                $cognitoResultLogin = ModuleModel::__loginUserCognitoByEmail($user_email, $password);

                if (!$cognitoResultLogin['success']) {
                    $return = [
                        'success' => false,
                        'message' => "ERROR_AWS_TEXT",
                        'detail' => $cognitoResultLogin,
                    ];
                    goto end_of_function;
                }
                $userRequestToken->setIsLogin(Helpers::NO);
                $userRequestToken->setUserLoginId($user_login->getId());
                $userRequestToken->setFirstLoginToken(json_encode($cognitoResultLogin));
                $userRequestToken->setStatus(UserRequestToken::STATUS_ARCHIVED);
                $resultUserRequestToken = $userRequestToken->__quickUpdate();

                if ($resultUserRequestToken['success'] == false) {
                    $return  = $resultUserRequestToken;
                    $this->db->rollback(); //rollback
//                    $return = [
//                        'success' => false,
//                        'message' => "ERROR_UPDATE_DATA_TEXT",
//                        'detail' => $resultUserRequestToken,
//                    ];
                    goto end_of_function;
                }

                //--------------------------create user profile------------------------
                $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                $dataArray = [
                    'action' => "sendMail",
                    'to' => $user_profile->getWorkemail(),
                    'user_login' => $user_profile->getWorkemail(),
                    'user_password' => $password,
                    'url_login' => $app->getFrontendUrl(),
                    'firstname' => $user_profile->getFirstname(),
                    'lastname' => $user_profile->getLastname(),
                    'company_name' => $company->getName(),
                    'templateName' => EmailTemplateDefault::CREATION_APPLICATION_SUCCESS,
                    'language' => $language
                ];

                $resultCheck = $beanQueue->addQueue($dataArray);

                $dataArrayToSupport = [
                    'action' => "sendMail",
                    'to' => getenv('SUPPORT_ADMIN_EMAIL'),
                    'email' => getenv('SUPPORT_ADMIN_EMAIL'),
                    'language' => 'en',
                    'templateName' => EmailTemplateDefault::NEW_ACCOUNT_CREATED_CSM_NOTIFICATION,
                    'params' => [
                        'register_name' => $user_profile->getFirstname() . " " . $user_profile->getLastname(),
                        'register_email' => $user_profile->getWorkemail(),
                        'company_name' => $company->getName(),
                        'company_type' => $company->getCompanyTypeId() == Models\CompanyTypeExt::TYPE_HR ? 'HR' : 'DSP',
                        "phone" => $telephone
                    ]
                ];
                $resultSendToAdmin = $beanQueue->addQueue($dataArrayToSupport);
                $this->db->commit();

                //--------------------------RETURN------------------------
                $return = [
                    'data' => [
                        'name' => $company->getName(),
                        'url' => RelodayUrlHelper::__getMainUrl() . '/register/#/thankyou_final?rfsn_ci=' . $chargebee_subscription->id
                    ],
                    'resulCheck' => $resultCheck,
                    'resultSendToAdmin' => $resultSendToAdmin,
                    'success' => true,
                    'message' => 'CREATE_APP_SUCCESS_TEXT',
                    'customer' => $customer,
                    'subscription' => $chargebee_subscription,
                    "thankyou_url" => RelodayUrlHelper::__getMainUrl() . '/register/#/thankyou_final?rfsn_ci=' . $chargebee_subscription->id
                ];

            } else {
                $return = [
                    'success' => false,
                    'message' => 'EMAIL_LOGIN_EXIST_TEXT'
                ];
            }
        } else {
            $return = [
                'success' => false,
                'message' => 'TOKEN_INVALID_TEXT'
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return Response|\Phalcon\Http\ResponseInterface
     * @throws \Phalcon\Security\Exception
     */
    public function createAppFromInvitationRequestAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $appConfig = $this->getDi()->getShared('appConfig');
        $secret = $appConfig->captcha->secret_key;
        $gRecaptchaResponse = Helpers::__getRequestValue('g_recaptcha_response');
        $remoteIp = $this->request->getClientAddress();
        $create_contract = false;

        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        $resp = $recaptcha->verify($gRecaptchaResponse, $remoteIp);
        if ($resp->isSuccess()) {
            // verified!
            // if Domain Name Validation turned off don't forget to check hostname field
            // if($resp->getHostName() === $_SERVER['SERVER_NAME']) {  }
            // nothing to check
        } else {
            $errors = $resp->getErrorCodes();
            $return = [
                'success' => false,
                'message' => 'ERROR_SESSION_EXPIRED_TEXT'
            ];

            goto end_of_function;
        }
        $data_request = $this->request->getJsonRawBody();

        $id = Helpers::__getRequestValue('id');
        $company_name = Helpers::__getRequestValue('company_name');
        $url = Helpers::__getRequestValue('url');
        $invitation_request = Models\InvitationRequestExt::findFirstById($id);
        $password = Helpers::__getRequestValue('new_password');
        $new_password_confirm = Helpers::__getRequestValue('new_password_confirm');
        $language = Helpers::__getRequestValue('language');
        $telephone = Helpers::__getRequestValue('telephone');
        $language = $language != '' && in_array($language, SupportedLanguage::$languages) ? $language : SupportedLanguage::LANG_EN;
        if($password != $new_password_confirm){
            $return = [
                'success' => false,
                'password' => $password,
                'new_password_confirm' => $new_password_confirm,
                'message' => "ERROR_APP_SAVE_FAILED_TEXT"
            ];
            goto end_of_function;
        }
        if ($invitation_request instanceof Models\InvitationRequestExt) {
            $invitee_company = Models\CompanyExt::findFirstById($invitation_request->getFromCompanyId());
            if ($invitee_company instanceof Models\CompanyExt) {
                $firstname = Helpers::__getRequestValue('firstname');
                $lastname = Helpers::__getRequestValue('lastname');
                $email = Helpers::__getRequestValue('email');
                $telephone = Helpers::__getRequestValue('telephone');
                $app_type = $invitation_request->isFromDsp() ? Models\CompanyTypeExt::TYPE_HR : Models\CompanyTypeExt::TYPE_GMS;


                $email_available = UserLogin::ifEmailAvailable($email);

                if ($email_available == true) {

                    $this->db->begin();
                    $random = new Random();

                    //CREATE APP
                    $app = new App();
                    $app->setUuid($random->uuid());
                    $app->setHash($random->base64Safe());
                    $app->setName($company_name);
                    $app->setUrl($url);
                    $app->setLanguage($language);
                    $app->setCompanyId(0);
                    $app->setType($app_type);
                    //--------------------------create application------------------------

                    $resultApp = $app->__quickCreate();
                    if ($resultApp['success'] == false) {
                        $this->db->rollback();
                        $return  = $resultApp;
//                        $return = [
//                            'result' => false,
//                            'success' => false,
//                            'message' => "ERROR_APP_SAVE_FAILED_TEXT",
//                            'detail' => $resultApp['detail']
//                        ];
                        goto end_of_function;
                    }


                    if ($invitation_request->getToCompanyId() > 0) {

                        $company = Company::findFirstById($invitation_request->getToCompanyId());
                        if (!$company instanceof Company) {
                            $this->db->rollback(); //rollback
                            $return = [
                                'success' => false,
                                'message' => "ERROR_COMPANY_SAVE_FAILED_TEXT",
                                'detail' => 'company do not exsit in system',
                                'object' => $company
                            ];
                            goto end_of_function;
                        }
                        if ($company->getCompanyTypeId() == Models\CompanyTypeExt::TYPE_HR && $invitation_request->isFromDsp()) {
                            $company->setName($company_name);
                            $company->setEmail($email);
                            $company->setAppId($app->getId());
                            $company->setStatus(Company::STATUS_ACTIVATED);
                            $company->setPhone($telephone);
                            $resultCompany = $company->__quickUpdate();
                            if ($resultCompany['success'] == false) {
                                $this->db->rollback(); //rollback
                                $return  = $resultCompany;
//                                $return = [
//                                    'success' => false,
//                                    'message' => "ERROR_COMPANY_SAVE_FAILED_TEXT",
//                                    'detail' => $resultCompany['details'],
//                                    'object' => $company
//                                ];
                                goto end_of_function;
                            }
                        } else if ($company->getCompanyTypeId() == Models\CompanyTypeExt::TYPE_GMS && $invitation_request->isFromHr()) {
                            $company->setName($company_name);
                            $company->setEmail($email);
                            $company->setAppId($app->getId());
                            $company->setPhone($telephone);
                            $company->setStatus(Company::STATUS_ACTIVATED);
                            $resultCompany = $company->__quickUpdate();
                            if ($resultCompany['success'] == false) {
                                $this->db->rollback(); //rollback
                                $return  = $resultCompany;
//                                $return = [
//                                    'success' => false,
//                                    'message' => "ERROR_COMPANY_SAVE_FAILED_TEXT",
//                                    'detail' => $resultCompany['details'],
//                                    'object' => $company
//                                ];
                                goto end_of_function;
                            }
                        } else {
                            $this->db->rollback(); //rollback
                            $return = [
                                'success' => false,
                                'message' => "ERROR_COMPANY_SAVE_FAILED_TEXT",
                                'detail' => 'company type do not suitable with invitation request',
                                'object' => $company,
                                'invitation' => $invitation_request
                            ];
                            goto end_of_function;
                        }
                    } else {
                        //--------------------------create company------------------------

                        $company = new Company();
                        $company->setUuid($random->uuid());
                        $company->setName($company_name);
                        $company->setEmail($email);
                        $company->setAppId($app->getId());
                        $company->setStatus(Company::STATUS_ACTIVATED);
                        $company->setPhone($telephone);
                        $company->setCompanyTypeId($app_type);

                        $resultCompany = $company->__quickCreate();
                        if ($resultCompany['success'] == false) {
                            $this->db->rollback(); //rollback
                            $return  = $resultCompany;
//                            $return = [
//                                'success' => false,
//                                'message' => "ERROR_COMPANY_SAVE_FAILED_TEXT",
//                                'detail' => $resultCompany['details'],
//                                'object' => $company
//                            ];
                            goto end_of_function;
                        }
                    }

                    //--------------------------create user_login------------------------
                    $userLoginData = [
                        'email' => $email,
                        'password' => $password,
                        'app_id' => $app->getId(),
                        'user_group_id' => $app_type == App::APP_TYPE_HR ? Models\UserLoginExt::USER_GROUP_HR_ADMIN :
                            ($app_type == App::APP_TYPE_GMS ? Models\UserLoginExt::USER_GROUP_GMS_ADMIN : 0)
                    ];

                    $user_login = UserLogin::__createNewUserLogin($userLoginData);
                    if ($user_login instanceof UserLogin) {
                        //continue to next step
                    } else {
                        $this->db->rollback();
                        $return = $user_login;
                        goto end_of_function;
                    }

                    //--------------------------create user profile------------------------
                    $user_profile = new UserProfile();
                    $user_profile->setUuid($random->uuid());
                    $user_profile->setWorkemail($email);
                    $user_profile->setFirstname($firstname);
                    $user_profile->setLastname($lastname);
                    $user_profile->setStatus(Models\UserLoginExt::STATUS_ACTIVATED);
                    $user_profile->setUserLoginId($user_login->getId());
                    $user_profile->setCompanyId($company->getId());
                    $user_profile->setActive(UserProfile::ACTIVATED); // default activated
                    $user_profile->setHasAccessStatus();

                    $nickname = $user_profile->generateNicknameFirst();
                    $user_profile->setNickname($nickname);

                    if ($app_type == 1) {
                        $user_profile->setUserGroupId(Models\UserLoginExt::USER_GROUP_HR_ADMIN);
                    } elseif ($app_type == 2) {
                        $user_profile->setUserGroupId(Models\UserLoginExt::USER_GROUP_GMS_ADMIN);
                    }

                    $resultUserProfile = $user_profile->__quickCreate();

                    if ($resultUserProfile['success'] == false) {
                        $this->db->rollback(); //rollback
                        $return  = $resultUserProfile;
//                        $return = [
//                            'success' => false,
//                            'message' => "ERROR_USER_PROFILE_TEXT",
//                            'detail' => $resultUserProfile,
//                        ];
                        goto end_of_function;
                    }

                    if (!$invitation_request->getToCompanyId() > 0) {
                        $contract = new Models\ContractExt();
                        $create_contract = true;
                        if ($invitation_request->isFromDsp()) {
                            $contract->setFromCompanyId($company->getId());
                            $contract->setToCompanyId($invitation_request->getFromCompanyId());
                            $contract->setName($company->getName() . " - " . $invitee_company->getName() . " - " . date('Ymd'));
                        } else {
                            $contract->setFromCompanyId($invitation_request->getFromCompanyId());
                            $contract->setToCompanyId($company->getId());
                            $contract->setName($invitee_company->getName() . " - " . $company->getName() . " - " . date('Ymd'));
                        }
                        $contract->setStatus(Models\ContractExt::STATUS_ACTIVATED);

                        $rest = $contract->__quickCreate();
                        if ($rest['success'] == false) {
                            $this->db->rollback(); //rollback
                            $return  = $rest;
//                            $return = [
//                                'success' => false,
//                                'message' => "ERROR_CONTRACT_TEXT",
//                                'detail' => $rest,
//                            ];
                            goto end_of_function;
                        }
                    } else {
                        $create_contract = false;
                        $contract = Models\ContractExt::findFirst([
                            "conditions" => "from_company_id = :from_company_id: and to_company_id = :to_company_id:",
                            "bind" => [
                                "from_company_id" => $invitation_request->isFromDsp() ? $company->getId() : $invitation_request->getFromCompanyId(),
                                "to_company_id" => $invitation_request->isFromDsp() ?  $invitation_request->getFromCompanyId() : $company->getId()
                            ]
                        ]);
                        if(!$contract instanceof Models\ContractExt) {
                            $this->db->rollback(); //rollback
                            $return = [
                                'success' => false,
                                'message' => "ERROR_CONTRACT_TEXT",
                                'detail' => $contract,
                            ];
                            goto end_of_function;
                        }
                    }

                    //init contract permission

                    $contractPermissionItems = Models\ContractPermissionItemExt::find();

                    if(count($contractPermissionItems) > 0){
                        foreach ($contractPermissionItems as $permission){
                            $newLinked = new Models\ContractPermissionExt();
                            $newLinked->setContractId($contract->getId());
                            $newLinked->setContractPermissionItemId($permission->getId());
                            $newLinked->setController($permission->getController());
                            $newLinked->setAction($permission->getAction());
                            $newLinked->setCreatorUserProfileId($user_profile->getId());
                            $creat_permission =  $newLinked->__quickCreate();
                            if ($creat_permission['success'] == false) {
                                $this->db->rollback(); //rollback
                                $return  = $creat_permission;
//                                $return = [
//                                    'success' => false,
//                                    'message' => "ERROR_CONTRACT_TEXT",
//                                    'detail' => $creat_permission,
//                                ];
                                goto end_of_function;
                            }
                        }
                    }

                    //---------------------------create ChargeBee Subscription--------------


                    $plan = Models\PlanExt::findFirst([
                        "conditions" => "status = :status:  and company_type_id = :company_type_id: and is_default = 1",
                        "bind" => [
                            "status" => Models\PlanExt::STATUS_ACTIVE,
                            "company_type_id" => $company->getCompanyTypeId(),
                        ]
                    ]);
                    if (!$plan instanceof Models\PlanExt) {
                        $plan = Models\PlanExt::findFirst([
                            "conditions" => "status = :status:  and company_type_id = :company_type_id:",
                            "bind" => [
                                "status" => Models\PlanExt::STATUS_ACTIVE,
                                "company_type_id" => $company->getCompanyTypeId(),
                            ]
                        ]);
                        if (!$plan instanceof Models\PlanExt) {
                            $this->db->rollback(); //rollback
                            $return = [
                                'success' => false,
                                'message' => "ERROR_UPDATE_DATA_TEXT",
                                'detail' => $plan,
                            ];
                            goto end_of_function;
                        }
                    }
                    $chargebeeHelper = new ChargeBeeHelper();
                    $customer = $chargebeeHelper->createCustomer([
                        "firstName" => $firstname,
                        "lastName" => $lastname,
                        "company" => $company_name,
                        "email" => $email,
                    ]);
                    $chargebee_subscription = $chargebeeHelper->createSupscription($customer->id, $plan->getChargebeeReferenceId());
//                    $chargebee_subscription - $chargebeeHelper->getSubscription($customer->id);
                    $chargebeeCustomer = new Models\ChargebeeCustomerExt();
                    $chargebeeCustomer->setUuid($random->uuid());
                    $chargebeeCustomer->setCompanyId($company->getId());
                    $chargebeeCustomer->setUserProfileId($user_profile->getId());
                    $chargebeeCustomer->setChargebeeReferenceId($customer->id);
                    $resultCustomer = $chargebeeCustomer->__quickCreate();

                    if ($resultCustomer['success'] == false) {
                        $this->db->rollback(); //rollback
                        $return  = $resultCustomer;
//                        $return = [
//                            'success' => false,
//                            'message' => "ERROR_UPDATE_DATA_TEXT",
//                            'customer' => $resultCustomer,
//                            'customer1' => $customer
//                        ];
                        goto end_of_function;
                    }

                    $subscription = new Models\SubscriptionExt();
                    $subscription->setUuid($random->uuid());
                    $subscription->setPlanId($plan->getId());
                    $subscription->setCompanyId($company->getId());
                    $subscription->setStatus(Models\SubscriptionExt::STATUS_LIST[$chargebee_subscription->status]);
                    $subscription->setChargebeeReferenceId($chargebee_subscription->id);
                    $subscription->setIsTrial($subscription->getStatus() == Models\SubscriptionExt::STATUS_TRIAL ? 1 : 0);
                    if ($subscription->getIsTrial() == 1) {
                        $now = time();
                        $subscription->setExpiredDate($now + 30 * 60 * 60 * 24);
                    }
                    $subscription->setNextPaymentDate($chargebee_subscription->nextBillingAt);
                    $resultSubscription = $subscription->__quickCreate();
                    if ($resultSubscription['success'] == false) {
                        $this->db->rollback(); //rollback
                        $return  = $resultSubscription;
//                        $return = [
//                            'success' => false,
//                            'message' => "ERROR_UPDATE_DATA_TEXT",
//                            'subscription' => $resultSubscription,
//                        ];
                        goto end_of_function;
                    }
                    $registerCognitoResult = Models\ApplicationModel::__addNewUserCognito([
                        'email' => $email,
                        'password' => $password,
                        'temporary_password' => Helpers::password(16),
                        'loginUrl' => $app->getLoginUrl()
                    ], $user_login, true);


                    if ($registerCognitoResult['success'] == false) {
                        $return = [
                            'success' => false,
                            'message' => "ERROR_AWS_TEXT",
                            'detail' => $registerCognitoResult,
                        ];
                        goto end_of_function;
                    }

                    $cognitoResultLogin = ModuleModel::__loginUserCognitoByEmail($email, $password);

                    if (!$cognitoResultLogin['success']) {
                        $return = [
                            'success' => false,
                            'message' => "ERROR_AWS_TEXT",
                            'detail' => $cognitoResultLogin,
                        ];
                        goto end_of_function;
                    }
                    //--------------------------edit user request token-----------------------

                    $invitation_request->setStatus(Models\InvitationRequestExt::STATUS_SUCCESS);
                    $invitation_request->setIsExecuted(1);
                    if (!$invitation_request->getToCompanyId() > 0) {
                        $invitation_request->setToCompanyId($company->getId());
                    }
                    $invitation_request->setCompanyName($company_name);
                    $invitation_request->setFirstname($firstname);
                    $invitation_request->setLastname($lastname);
                    $invitation_request->setIsLogin(Helpers::NO);
                    $invitation_request->setFirstLoginToken(json_encode($cognitoResultLogin));
                    $resultInvitationRequestUpdate = $invitation_request->__quickUpdate();

                    if ($resultInvitationRequestUpdate['success'] == false) {
                        $this->db->rollback(); //rollback
                        $return  = $resultInvitationRequestUpdate;
//                        $return = [
//                            'success' => false,
//                            'message' => "ERROR_UPDATE_DATA_TEXT",
//                            'detail' => $resultInvitationRequestUpdate['details'],
//                        ];
                        goto end_of_function;
                    }

                    //--------------------------create user profile------------------------
                    $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
                    $dataArray = [
                        'action' => "sendMail",
                        'to' => $user_profile->getWorkemail(),
                        'user_login' => $user_profile->getWorkemail(),
                        'user_password' => $password,
                        'url_login' => $app->getFrontendUrl(),
                        'firstname' => $user_profile->getFirstname(),
                        'lastname' => $user_profile->getLastname(),
                        'company_name' => $company->getName(),
                        'templateName' => EmailTemplateDefault::CREATION_APPLICATION_SUCCESS,
                        'language' => $language
                    ];

                    $resultCheck = $beanQueue->addQueue($dataArray);
                    $this->db->commit();
                    if($create_contract){
                        $beanQueue2 = new RelodayQueue(getenv('QUEUE_WEBHOOK_WORKER'));

                        $resultQueue = $beanQueue2->addQueue([
                            'action' => "create",
                            'params' => [
                                'uuid' => $contract->getUuid(),
                                'object_type' => $contract->getSource(),
                                'action' => 'create',
                                'action_display' => 'create'
                            ]
                        ]);
                    } else {
                        $beanQueue2 = new RelodayQueue(getenv('QUEUE_WEBHOOK_WORKER'));
    
                        $resultQueue = $beanQueue2->addQueue([
                            'action' => "update",
                            'params' => [
                                'uuid' => $contract->getUuid(),
                                'object_type' => $contract->getSource(),
                                'action' => 'update',
                                'action_display' => 'update'
                            ]
                        ]);
                    }

                    //--------------------------invitation ------------------------
                    $inviter = Models\UserProfileExt::findFirstByIdCache($invitation_request->getInviterUserProfileId());
                    $resultCheck1 = "";
                    if ($inviter instanceof Models\UserProfileExt) {
                        $dataArray1 = [
                            'action' => "sendMail",
                            'to' => $inviter->getWorkemail(),
                            'email' => $inviter->getWorkemail(),
                            'language' => $inviter->getLanguage(),
                            'templateName' => EmailTemplateDefault::INVITATION_CONNECTION_ACCEPTED,
                            'params' => [
                                'inviter_name' => $inviter->getFirstname() . " " . $inviter->getLastname(),
                                'inviter_company' => $inviter->getCompanyName(),
                                'company_name' => $company_name,
                                'url' => $invitation_request->getDirection() == Models\InvitationRequestExt::DIRECTION_FROM_DSP_TO_HR ? RelodayUrlHelper::__getMainUrl() . '/gms/#/app/admin-page/invitations' : RelodayUrlHelper::__getMainUrl() . '/hr/#/app/providers/invitations',
                            ]
                        ];
                        $resultCheck1 = $beanQueue->addQueue($dataArray1);
                    }
                    $return = [
                        'data' => [
                            'name' => $company->getName(),
                            'url' => RelodayUrlHelper::__getMainUrl() . '/register/#/thankyou_final?rfsn_ci=' . $chargebee_subscription->id
                        ],
                        'resulCheck' => $resultCheck,
                        'resulCheck1' => $resultCheck1,
                        'inviter' =>$inviter,
                        'success' => true,
                        'message' => 'CREATE_APP_SUCCESS_TEXT',
                        'customer' => $customer,
                        'subscription' => $chargebee_subscription,
                        "thankyou_url" => RelodayUrlHelper::__getMainUrl() . '/register/#/thankyou_final?rfsn_ci=' . $chargebee_subscription->id
                    ];

                } else {
                    $return = [
                        'success' => false,
                        'message' => 'EMAIL_LOGIN_EXIST_TEXT'
                    ];
                }
            }
        } else {
            $return = [
                'success' => false,
                'message' => 'TOKEN_INVALID_TEXT'
            ];
        }

        end_of_function:
        $return['$registerCognitoResult'] = isset($registerCognitoResult) ? $registerCognitoResult : false;
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return Response|\Phalcon\Http\ResponseInterface
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
            !$userLogin->getEmployeeOrUserProfile() ||
            !$userLogin->getApp()) {
            $return = [
                'result' => false,
                'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        $return = [
            'success' => true,
            'data' => [
                'email' => $userLogin->getEmail(),
                'avatarUrl' => $userLogin->getEmployeeOrUserProfile()->getAvatarUrl(),
                'appUrl' => $userLogin->getEmployeeOrUserProfile()->getAppUrl()
            ]
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Verify aws cognito confirm
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
            $return['message'] = 'VERIFICATION_CODE_MISMATCH_TEXT';
        } else {
            $return['message'] = 'ALREADY_GOT_VERIFICATION_CODE_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @throws \Exception
     */
    public function resendCodeAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $return = ['detail' => [], 'success' => false, 'message' => ''];
        $credential = Helpers::__getRequestValue('credential');
        $token_key = Helpers::__getRequestValue('token');
        $language_key = Helpers::__getHeaderValue(Helpers::LANGUAGE_KEY);
        $auth = ModuleModel::__checkAuthenByOldToken($token_key, $language_key);
        $return = ModuleModel::__resendUserCognitoVerificationCode($credential);
        if ($return['success'] == true) {
            $return['message'] = 'SEND_MAIL_CONFIRM_SUCCESS_TEXT';
        } else {
            $return['message'] = 'SEND_MAIL_CONFIRM_ERROR_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function resetPasswordRequestAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $result = ['detail' => [], 'success' => false, 'message' => ''];
        $email = Helpers::__getRequestValue('email');

        if (!Helpers::__isEmail($email)) {
            $result = [
                'success' => false,
                'message' => 'EMAIL_INVALID_TEXT'
            ];
            goto end_of_function;
        }
        $userLogin = UserLogin::findFirstByEmail($email);
        if (!$userLogin) {
            $result = [
                'success' => false,
                'errorType' => 'userLoginNotFound',
                'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }


        if (!$userLogin->getUserProfile() && !$userLogin->getEmployee()) {
            $result = [
                'success' => false,
                'errorType' => 'userProfileNotFound',
                'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }

        if ($userLogin->getUserProfile() && (!$userLogin->getUserProfile()->isGms() && !$userLogin->getUserProfile()->isHr())) {
            $result = [
                'success' => false,
                'errorType' => 'accountTypeNotFound',
                'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
            ];
            goto end_of_function;
        }
        if ($userLogin->isConvertedToUserCognito()) {
            if ($userLogin->isCognitoEmailVerified() == false || $userLogin->isForceChangePassword() == true) {
                $result = $userLogin->forceResetPassword();
                $result['isTemporaryPasswordSent'] = true;
            } else {
                $result = ModuleModel::__sendUserCognitoRecoveryPasswordRequest($email);
                $result['isVerificationCodeSent'] = true;
            }

        } else {
            $resultResetPassword = $userLogin->resetPassword();
            if ($resultResetPassword["success"] == false) {
                $result = $resultResetPassword;
                goto end_of_function;
            }
            $result = ModuleModel::__adminRegisterUserCognito([
                'email' => $email,
                'password' => $resultResetPassword["password"],
                'loginUrl' => $userLogin->getLoginUrl()
            ], $userLogin);

            $result['isVerificationCodeSent'] = true;
            $result["checkEmail"] = true;
        }
        end_of_function:
        if ($result['success'] == true) {
            if (!isset($result['message']) || $result['message'] == '') {
                $result['message'] = 'RESET_PASSWORD_REQUEST_SEND_SUCCESS_TEXT';
            }
        } else {

            if (isset($result['errorType']) && $result['errorType'] == CognitoClient::ERROR_LIMIT_EXCEEDED_EXCEPTION) {
                $result['message'] = 'RESET_PASSWORD_EXCEED_LIMIT_TEXT';
            }
            if (!isset($result['message']) || $result['message'] == '') {
                $result['message'] = 'RESET_PASSWORD_REQUEST_SEND_FAIL_TEXT';
            }
        }
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
}
