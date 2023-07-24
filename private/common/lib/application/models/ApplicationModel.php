<?php

namespace SMXD\Application\Models;

use Aws\Exception\AwsException;
use Phalcon\Di;
use Phalcon\Http\Request;
use \Phalcon\Mvc\Model;
use Phalcon\Security;
use SMXD\Application\Aws\AwsCognito\AwsCognitoResult;
use SMXD\Application\Aws\AwsCognito\CognitoClient;
use SMXD\Application\Aws\AwsCognito\Exception\ChallengeException;
use SMXD\Application\Aws\AwsCognito\Exception\TokenExpiryException;
use SMXD\Application\Aws\AwsCognito\Exception\TokenVerificationException;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\JWTEncodedHelper;
use SMXD\Application\Lib\RequestHeaderHelper;

/**
 * Application model base class
 */
class ApplicationModel extends Model
{
    /**
     * @var
     */
    static $cognitoClient;
    /**
     * @var array
     */
    static $exceptionKeyList = [];

    /**
     * @return mixed
     */
    static function uuid()
    {
        $random = new \Phalcon\Security\Random();
        return $random->uuid();
    }

    /**
     * @return string
     */
    static function __getApiHostname()
    {
        $di = Di::getDefault();
        return (($di->getShared('config')->domain->ssl == true) ? 'https' : 'http') . "://" . $di->get('config')->domain->api;
    }

    /**
     * checkAuthByJWT
     */
    public static function checkAuthByJWT()
    {

    }

    /**
     *
     */
    public static function loginByJWT()
    {

    }

    /**
     *
     */
    public static function __getUserCognitoEmailException()
    {
        try {
            $di = Di::getDefault();
            $awsSecretManager = $di->get('aws')->createClient('SecretsManager');
            $value = $awsSecretManager->getSecretValue([
                'SecretId' => 'RelodayExceptionConfig'
            ]);

            $data = json_decode($value['SecretString'], true);
            return $data['userCognitoException'] ? explode(";", $data['userCognitoException']) : [];
        } catch (AwsException $e) {
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return array
     */
    public static function __startCognitoClient($region = null, $configuration = null)
    {
        if (!self::$cognitoClient instanceof CognitoClient || is_null(self::$cognitoClient)) {
            try {
                $di = Di::getDefault();
                $appConfig = $di->get('appConfig');

                if ($region == null) {
                    $awsCognitoClient = $di->get('awsCognitoService')->createCognitoIdentityProvider([
                        'region' => $appConfig->aws->awsCognitoRegion
                    ]);
                } else {
                    $awsCognitoClient = $di->get('awsCognitoService')->createCognitoIdentityProvider([
                        'region' => $region
                    ]);
                }

                $client = new CognitoClient($awsCognitoClient);
                $client->setDefaultConfiguration($configuration);
                self::$cognitoClient = $client;
            } catch (AwsException $e) {
                self::$cognitoClient = null;
            }
        }

        if (!self::$cognitoClient instanceof CognitoClient || is_null(self::$cognitoClient)) {
            return ['success' => false, 'message' => 'Can not start the AWS Cognito Engine'];
        } else {
            return ['success' => true];
        }
    }

    /**
     * @param $accessToken
     */
    public static function __verifyUserCognitoAccessToken($accessToken)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $awsCognitoUuid = self::$cognitoClient->verifyAccessToken($accessToken);
            return [
                'success' => true,
                'key' => $awsCognitoUuid
            ];
        } catch (TokenExpiryException $e) {
            $return = [
                'success' => false,
                'isExpired' => true,
                'errorCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'type' => 'TokenExpiryException',
            ];
            return $return;
        } catch (TokenVerificationException $e) {
            $return = [
                'success' => false,
                'isExpired' => false,
                'errorCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'type' => 'TokenVerificationException',
            ];
            return $return;
        } catch (\SMXD\Application\Aws\AwsCognito\Exception $e) {
            $return = [
                'success' => false,
                'isExpired' => false,
                'errorCode' => $e->getCode(),
                'message' => $e->getErrorCode(),
                'type' => 'AwsCognitoException'
            ];
            Helpers::__trackError($e);
            return $return;
        } catch (AwsException $e) {
            $return = [
                'success' => false,
                'isExpired' => false,
                'errorCode' => $e->getCode(),
                'message' => $e->getAwsErrorCode(),
                'type' => 'AwsException'
            ];
            Helpers::__trackError($e);
            return $return;
        } catch (\Exception $e) {
            $return = [
                'success' => false,
                'isExpired' => false,
                'errorCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'type' => 'Exception'

            ];
            Helpers::__trackError($e);
            return $return;
        }
    }

    /**
     *
     * @param $userConfig
     */
    public static function __addNewUserCognito($userConfig, $userLoginObject = null, $is_verified = false)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;

        $loginUrl = "";
        $employee = EmployeeExt::findFirstByWorkemail($userConfig['email']);
        if ($employee) {
            $loginUrl = $employee->getAppUrl();
        } else {
            $user = UserProfileExt::findFirstByWorkemail($userConfig['email']);
            if ($user) {
                $loginUrl = $user->getAppUrl();
            }
        }

        try {
            //if $userConfig['email'] is in the list of changeSecurity => force to verify
            $checkException = EmailAwsCognitoExceptionExt::findFirstByEmail($userConfig['email']);

            if ($checkException || $is_verified) {
                //@TODO because Aws Exception is used for our test user demo

                if (!isset($userConfig['temporary_password']) || Helpers::__isNull($userConfig['temporary_password'])) {
                    $userConfig['temporary_password'] = Helpers::password(16);
                }

                $awsCognitoUserUuid = self::$cognitoClient->adminRegisterUserOnly($userConfig['email'], $userConfig['temporary_password'], [
                    'email' => $userConfig['email'],
                    'custom:login_url' => isset($userConfig['loginUrl']) ? $userConfig['loginUrl'] : $loginUrl,
                    'custom:url_redirect' => getenv('API_DOMAIN'),
                ]);
                $setUserPassword = self::$cognitoClient->adminSetUserPassword($awsCognitoUserUuid, $userConfig['password']);
            } else {
                $awsCognitoUserUuid = self::$cognitoClient->registerUser($userConfig['email'], $userConfig['password'], [
                    'email' => $userConfig['email'],
                    'custom:login_url' => isset($userConfig['loginUrl']) ? $userConfig['loginUrl'] : $loginUrl,
                    'custom:url_redirect' => getenv('API_DOMAIN'),
                ]);
            }


            if (is_null($userLoginObject)) {
                $userLogin = UserLoginExt::findFirstByEmail($userConfig['email']);
                $userLogin->setAwsUuid($awsCognitoUserUuid);
                $checkSave = $userLogin->__quickUpdate();
            } elseif (is_object($userLoginObject) && method_exists($userLoginObject, 'setAwsUuid')) {
                $userLoginObject->setAwsUuid($awsCognitoUserUuid);
                $checkSave = $userLoginObject->__quickUpdate();
            }

            if (isset($checkSave) && $checkSave['success'] == true) {
                return ['success' => true, 'key' => $awsCognitoUserUuid];
            } else {
                return ['success' => false, 'detail' => $checkSave['detail']];
            }
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $result = ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
            return $result;
        }
    }

    /**
     *
     * @param $userConfig
     */
    public static function __adminRegisterUserCognito($userConfig, $userLoginObject = null)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        $isExisted = false;
        try {
            $userProfile = self::$cognitoClient->adminGetUser($userConfig['email']);
            if ($userProfile) {
                $isExisted = true;
            }
        } catch (AwsException $e) {
            $isExisted = false;
        }

        try {

            $loginUrl = "";
            $employee = EmployeeExt::findFirstByWorkemail($userConfig['email']);
            if ($employee) {
                $loginUrl = $employee->getAppUrl();
            } else {
                $user = UserProfileExt::findFirstByWorkemail($userConfig['email']);
                if ($user) {
                    $loginUrl = $user->getAppUrl();
                }
            }

            if ($isExisted == false) {
                $awsCognitoUserUuid = self::$cognitoClient->adminRegisterUserOnly($userConfig['email'], $userConfig['password'], [
                    'email' => $userConfig['email'],
                    'custom:login_url' => isset($userConfig['loginUrl']) ? $userConfig['loginUrl'] : $loginUrl,
                    'custom:url_redirect' => getenv('API_DOMAIN'),
                ]);
            } else {
                $awsCognitoUserUuid = self::$cognitoClient->adminRegisterUser($userConfig['email'], $userConfig['password'], [
                    'email' => $userConfig['email'],
                    'custom:login_url' => isset($userConfig['loginUrl']) ? $userConfig['loginUrl'] : $loginUrl,
                    'custom:url_redirect' => getenv('API_DOMAIN'),
                ]);
            }

            if (is_null($userLoginObject)) {
                $userLogin = UserLoginExt::findFirstByEmail($userConfig['email']);
                $userLogin->setAwsUuid($awsCognitoUserUuid);
                $checkSave = $userLogin->__quickUpdate();
            } elseif (is_object($userLoginObject) && method_exists($userLoginObject, 'setAwsUuid')) {
                $userLoginObject->setAwsUuid($awsCognitoUserUuid);
                $checkSave = $userLoginObject->__quickUpdate();
            }

            if (isset($checkSave) && $checkSave['success'] == true) {
                return ['success' => true, 'awsUuid' => $awsCognitoUserUuid, 'key' => $awsCognitoUserUuid];
            } else {
                return ['success' => false, 'detail' => $awsCognitoUserUuid];
            }
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $result = ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
            return $result;
        }
    }


    /**
     * @throws \Exception
     */
    public static function __confirmUserCognito($confirmCode, $email)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $registerResponse = self::$cognitoClient->confirmUserRegistration($confirmCode, $email);
            return ['success' => true, 'response' => $registerResponse];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $result = ['success' => false, 'detail' => $e->getMessage(), 'type' => $exceptionType, 'message' => $e->getAwsErrorMessage()];
            return $result;
        }
    }

     /**
     * @param String $challengeName
     * @param String $email
     * @param String $password
     * @param $session
     * @return array
     */
    public static function __adminUpdateUserAttributes(String $userName, String $userAttribute, String $userAttributeValue)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $registerResponse = self::$cognitoClient->adminUpdateUserAttributes($userName, $userAttribute, $userAttributeValue);
            return $registerResponse;
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $result = ['success' => false, 'detail' => $e->getMessage(), 'type' => $exceptionType, 'message' => $e->getAwsErrorMessage()];
            return $result;
        }
    }

    /**
     * @throws \Exception
     */
    public static function __resendUserCognitoVerificationCode($email)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $response = self::$cognitoClient->resendRegistrationConfirmationCode($email);
            return ['success' => true, 'response' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'errorCode' => $e->getCode(), 'message' => $exceptionType];
        }
    }

    /**
     * @param $email
     * @return array
     */
    public static function __forceVerifyCognitoUser($email)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $forceResponse = self::$cognitoClient->adminConfirmSignup($email);
            return ['success' => true, 'response' => $forceResponse];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $result = ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
            return $result;
        }
    }

    /**
     * @return array
     */
    public static function __sendUserCognitoRecoveryPasswordRequest($email)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $response = self::$cognitoClient->sendForgottenPasswordRequest($email);
            return ['success' => true, 'detail' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $result = ['success' => false, 'detail' => $e->getMessage(), 'errorType' => $exceptionType];
            return $result;
        }
    }

    /**
     * @param $userConfig
     * @return array
     * @throws \Exception
     */
    public static function __changeUserCognitoPassword($userConfig)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;

        $code = $userConfig['confirmCode'];
        $email = $userConfig['email'];
        $password = $userConfig['password'];
        try {
            $response = self::$cognitoClient->resetPassword($code, $email, $password);
            return ['success' => true, 'detail' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            $exceptionMessage = 'CODE_INVALID_TEXT';

            switch ($exceptionType) {
                case 'CodeMismatchException':
                    break;
                case 'ExpiredCodeException':
                    $exceptionMessage = 'CODE_EXPIRED_TEXT';
                    break;
                case 'UserNotFoundException':
                    $exceptionMessage = 'USER_NOT_FOUND_TEXT';
                    break;
                case 'UserNotConfirmedException':
                    $exceptionMessage = 'USER_NOT_CONFIRMED_TEXT';
                    break;
                case 'NotAuthorizedException':
                    $exceptionMessage = 'USER_NOT_AUTHORIZED_TEXT';
                    break;
                case 'TooManyFailedAttemptsException':
                    $exceptionMessage = 'PLEASE_RETRY_LATER_TEXT';
                    break;
                case 'InvalidPasswordException':
                    $exceptionMessage = 'PASSWORD_INVALID_TEXT';
                    break;
                default:
                    $exceptionMessage = 'CODE_INVALID_TEXT';
            }
            return ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionMessage, 'exceptionType' => $exceptionType];
        }
    }


    /**
     * @param $userConfig
     * @return array
     * @throws \Exception
     */
    public static function __changeMyPassword($userConfig)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;

        $oldPassword = $userConfig['oldPassword'];
        $password = $userConfig['password'];

        try {
            $response = self::$cognitoClient->changePassword(self::__getAccessToken(), $oldPassword, $password);
            return ['success' => true, 'detail' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'code' => $exceptionType, 'message' => $exceptionType];
        }
    }


    /**
     * @param $email
     * @param $password
     * @return array
     */
    public static function __loginUserCognitoByEmail($email, $password)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;

        try {
            $authenticationResponse = self::$cognitoClient->authenticate($email, $password);
            return [
                'success' => true,
                'authenticationResponse' =>  $authenticationResponse,
                'accessToken' => $authenticationResponse['AccessToken'],
                'refreshToken' => $authenticationResponse['RefreshToken']
            ];
        } catch (ChallengeException $e) {

            return [
                'success' => false,
                'detail' => $e->getMessage(),
                "session" => $e->getSession(),
                'name' => $e->getChallengeName(),
                'response' => $e->getResponse(),
                'NewPasswordRequiredException' => $e->getChallengeName() == 'NEW_PASSWORD_REQUIRED',
                'UserNotFoundException' => false,
                'UserNotConfirmedException' => false,
            ];

        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return [
                'success' => false,
                'detail' => $e->getMessage(),
                'exceptionType' => $exceptionType,
                'UserNotFoundException' => $exceptionType == 'UserNotFoundException',
                'UserNotConfirmedException' => $exceptionType == 'UserNotConfirmedException',
            ];
        }
    }

    /**
     * @param $email
     * @param $password
     * @return array
     */
    public static function __loginUserCognitoByAdmin($email)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;

        try {
            $authenticationResponse = self::$cognitoClient->adminAuthenticateNoPassword($email);
            return [
                'success' => true,
                'authenticationResponse' =>  $authenticationResponse
            ];
        } catch (ChallengeException $e) {

            return [
                'success' => false,
                'detail' => $e->getMessage(),
                "session" => $e->getSession(),
                'name' => $e->getChallengeName(),
                'response' => $e->getResponse(),
                'NewPasswordRequiredException' => $e->getChallengeName() == 'NEW_PASSWORD_REQUIRED',
                'UserNotFoundException' => false,
                'UserNotConfirmedException' => false,
            ];

        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return [
                'success' => false,
                'detail' => $e->getMessage(),
                'exceptionType' => $exceptionType,
                'UserNotFoundException' => $exceptionType == 'UserNotFoundException',
                'UserNotConfirmedException' => $exceptionType == 'UserNotConfirmedException',
            ];
        }
    }

    /**
     * @param $username
     * @return array
     */
    public static function __loginMasterUserCognitoByAdmin($username)
    {
        if (getenv('MASTER_API_COGNITO_REGION') != '' &&
            getenv('MASTER_API_COGNITO_APP_CLIENT_ID') != '' &&
            getenv('MASTER_API_COGNITO_APP_SECRET') &&
            getenv('MASTER_API_COGNITO_USER_POOL_ID') != '') {
            $awsRegion = getenv('MASTER_API_COGNITO_REGION');
            self::__startCognitoClient($awsRegion, [
                'awsAppClientId' => getenv('MASTER_API_COGNITO_APP_CLIENT_ID'),
                'awsAppClientSecret' => getenv('MASTER_API_COGNITO_APP_SECRET'),
                'awsCognitoRegion' => getenv('MASTER_API_COGNITO_REGION'),
                'awsUserPoolId' => getenv('MASTER_API_COGNITO_USER_POOL_ID'),
            ]);

            try {
                $authenticationResponse = self::$cognitoClient->adminAuthenticateNoPassword($username);
                return [
                    'success' => true,
                    'authenticationResponse' =>  $authenticationResponse
                ];
            } catch (ChallengeException $e) {

                return [
                    'success' => false,
                    'detail' => $e->getMessage(),
                    "session" => $e->getSession(),
                    'name' => $e->getChallengeName(),
                    'response' => $e->getResponse(),
                    'NewPasswordRequiredException' => $e->getChallengeName() == 'NEW_PASSWORD_REQUIRED',
                    'UserNotFoundException' => false,
                    'UserNotConfirmedException' => false,
                ];

            } catch (AwsException $e) {
                $exceptionType = $e->getAwsErrorCode();
                return [
                    'success' => false,
                    'detail' => $e->getMessage(),
                    'exceptionType' => $exceptionType,
                    'UserNotFoundException' => $exceptionType == 'UserNotFoundException',
                    'UserNotConfirmedException' => $exceptionType == 'UserNotConfirmedException',
                ];
            }
        }
        return ['success' => false];



    }

    /**
     * @param String $challengeName
     * @param String $email
     * @param String $password
     * @param $session
     * @return array
     */
    public static function __respondToAuthChallenge(String $challengeName, String $email, String $password, $session)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {

            $loginUrl = "";
            $employee = EmployeeExt::findFirstByWorkemail($email);
            $user = [];
            if ($employee) {
                $loginUrl = $employee->getAppUrl();
            } else {
                $user = UserProfileExt::findFirstByWorkemail($email);
                if ($user) {
                    $loginUrl = $user->getAppUrl();
                }
            }

            //Update User attribute
            $resultLoginUrl = self::__adminForceUpdateUserAttributes($email, 'custom:login_url', $loginUrl);
            if ($resultLoginUrl['success'] == false) {
                return $resultLoginUrl;
            }

            $resultUrlRedirect = self::__adminForceUpdateUserAttributes($email, 'custom:url_redirect', getenv('API_DOMAIN'));
            if ($resultUrlRedirect['success'] == false) {
                return $resultUrlRedirect;
            }

            $authenticationResponse = self::$cognitoClient->respondToAuthChallenge($challengeName, $email, $password, $session);

            if ($user && $user->hasLogin()){
                $user->setHasAccessStatus();
                $resultUpdate = $user->__quickUpdate();
            }

            return [
                'success' => true,
                'accessToken' => $authenticationResponse['AccessToken'],
                'refreshToken' => $authenticationResponse['RefreshToken']
            ];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();

            return [
                'success' => false,
                'detail' => $e->getMessage(),
                'exceptionType' => $exceptionType,
                'UserNotFoundException' => $exceptionType == 'UserNotFoundException',
                'UserNotConfirmedException' => $exceptionType == 'UserNotConfirmedException',
            ];

        }

    }


    /**
     * @param $userConfig
     * @return array
     * @throws \Exception
     */
    public static function __getUserCognitoByEmail(string $email)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $userEmail = "";
            $loginUrl = "";
            $isEmailVerified = false;
            $response = self::$cognitoClient->adminGetUser($email);
            $attributes = [];
            if (isset($response['UserAttributes']) && is_array($response['UserAttributes']) && count($response['UserAttributes'])) {
                foreach ($response["UserAttributes"] as $userAttribute) {
                    if ($userAttribute['Name'] == "email") {
                        $userEmail = $userAttribute['Value'];
                    }
                    if ($userAttribute['Name'] == UserLoginExt::AWS_FIELD_LOGIN_URL) {
                        $loginUrl = $userAttribute['Value'];
                    }
                    if ($userAttribute['Name'] == 'email_verified') {
                        $isEmailVerified = $userAttribute['Value'] == "true" || $userAttribute['Value'] === true ? true : false;
                    }
                    $attributes[$userAttribute['Name']] = $userAttribute['Value'];
                }
            }

            return [
                'success' => true,
                'user' => [
                    'awsUuid' => isset($response['Username']) ? $response['Username'] : '',
                    'userStatus' => isset($response['UserStatus']) ? $response['UserStatus'] : '',
                    'attributes' => $attributes,
                    'isEmailVerified' => $isEmailVerified,
                    'isConfirmed' => isset($response['UserStatus']) && $response['UserStatus'] === CognitoClient::CONFIRMED ? true : false,
                    'isForceChangePassword' => isset($response['UserStatus']) && $response['UserStatus'] === CognitoClient::FORCE_CHANGE_PASSWORD ? true : false,
                    'isResetPasswordRequired' => isset($response['UserStatus']) && $response['UserStatus'] === CognitoClient::RESET_REQUIRED ? true : false,
                    'userCreateDate' => isset($response['UserCreateDate']) ? $response['UserCreateDate'] : '',
                    'email' => $email,
                    'loginUrl' => $loginUrl,
                    'isEnable' => isset($response['Enabled']) ? $response['Enabled'] : false,
                ]
            ];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
        }
    }

    /**
     * @param $userConfig
     * @return array
     * @throws \Exception
     */
    public static function __getUserCognitoByUsername($username)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $userEmail = "";
            $loginUrl = "";
            $response = self::$cognitoClient->adminGetUser($username);
            $attributes = [];
            if (isset($response['UserAttributes']) && is_array($response['UserAttributes']) && count($response['UserAttributes'])) {
                foreach ($response["UserAttributes"] as $userAttribute) {
                    if ($userAttribute['Name'] == "email") {
                        $userEmail = $userAttribute['Value'];
                    }
                    if ($userAttribute['Name'] == UserLoginExt::AWS_FIELD_LOGIN_URL) {
                        $loginUrl = $userAttribute['Value'];
                    }
                    if ($userAttribute['Name'] == 'email_verified') {
                        $isEmailVerified = $userAttribute['Value'] == "true" || $userAttribute['Value'] === true ? true : false;
                    }
                    $attributes[$userAttribute['Name']] = $userAttribute['Value'];
                }
            }
            return [
                'success' => true,
                'user' => [
                    'awsUuid' => isset($response['Username']) ? $response['Username'] : '',
                    'userStatus' => isset($response['UserStatus']) ? $response['UserStatus'] : '',
                    'isEmailVerified' => isset($isEmailVerified) ? $isEmailVerified : false,
                    'isConfirmed' => isset($response['UserStatus']) && $response['UserStatus'] === CognitoClient::CONFIRMED ? true : false,
                    'isForceChangePassword' => isset($response['UserStatus']) && $response['UserStatus'] === CognitoClient::FORCE_CHANGE_PASSWORD ? true : false,
                    'isResetPasswordRequired' => isset($response['UserStatus']) && $response['UserStatus'] === CognitoClient::RESET_REQUIRED ? true : false,
                    'userCreateDate' => isset($response['UserCreateDate']) ? $response['UserCreateDate'] : '',
                    'email' => $userEmail,
                    'loginUrl' => $loginUrl,
                    'isEnable' => isset($response['Enabled']) ? $response['Enabled'] : false,
                    'attributes' => $attributes,
                ]
            ];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
        }
    }

    /**
     * @param $credential
     * @param $password
     * @return mixed
     */
    public static function __loginUserOldMethod($credential, $password)
    {

        $result = ['success' => true];
        $userLogin = UserLogin::findFirstByEmail($credential);
        $check = self::__verifyUserLoginOldMethod($userLogin);
        if ($check['success'] == false) {
            return $check;
        }

        $security = new Security();
        if (!$security->checkHash($password, $userLogin->getPassword())) {
            $result['success'] = false;
            $result['message'] = 'WRONG_PASSWORD_TEXT';
            $result['detailData'] = __METHOD__;
            $result['field']['pwd'] = 'WRONG_PASSWORD_TEXT';
            goto end_of_function;
        }

        end_of_function:
        return $result;
    }

    /**
     * @param $userLogin
     */
    public static function __getUserTokenOldMethod($userLogin)
    {
        if (!is_object($userLogin)) {
            return false;
        }

        $request = new Request();
        if ($userLogin->getUserProfile() instanceof UserProfile) {
            $tokenUserData = $userLogin->getUserProfile()->toArray();
            $tokenUserData['is_gms'] = $userLogin->getUserProfile()->isGms();
            $tokenUserData['is_admin'] = $userLogin->getUserProfile()->isGmsAdmin();
            $tokenUserData['is_hr'] = $userLogin->getUserProfile()->isHr();
            $tokenUserData['is_employee'] = false;
        } else {
            $tokenUserData = $userLogin->getEmployee()->toArray();
            $tokenUserData['is_gms'] = false;
            $tokenUserData['is_admin'] = false;
            $tokenUserData['is_hr'] = false;
            $tokenUserData['is_employee'] = true;

        }

        $currentApp = $userLogin->getApp();

        $tokenUserData['iss'] = $request->getHttpHost();
        $tokenUserData['aud'] = $request->getHttpHost();
        $tokenUserData['app_name'] = $currentApp ? $currentApp->getName() : null;
        $tokenUserData['app_type'] = $currentApp ? $currentApp->getType() : null;
        $tokenUserData['email'] = $userLogin->getEmail();
        $tokenUserData['user_login_id'] = $userLogin->getId();

        $di = Di::getDefault();
        $tokenUserDataToken = JWTEncodedHelper::encode($tokenUserData, $di->getShared('appConfig')->application->session_timeout);
        return $tokenUserDataToken;
    }

    /**
     * @param $userLogin
     * @return array
     */
    public static function __verifyUserLoginOldMethod($userLogin)
    {
        $result = ['success' => true];
        if (!is_object($userLogin)) {
            $result = ['success' => false];
            goto end_of_function;
        }
        if (!method_exists($userLogin, "getUserProfile") || !$userLogin->getUserProfile()) {
            $result['message'] = 'LOGIN_NOT_FOUND_TEXT';
            goto end_of_function;
        }

        if ($userLogin->getStatus() != UserLogin::STATUS_ACTIVATED) {
            $result['message'] = 'USER_DESACTIVATED_TEXT';
            goto end_of_function;
        }
        if (!$userLogin instanceof UserLogin || !$userLogin->getUserProfile()) {
            $result['message'] = 'LOGIN_NOT_FOUND_TEXT';
            goto end_of_function;
        }

        if ($userLogin->getStatus() != UserLogin::STATUS_ACTIVATED) {
            $result['message'] = 'USER_DESACTIVATED_TEXT';
            goto end_of_function;
        }

        if (!$userLogin->getUserProfile() || !$userLogin->getUserProfile()->isActive() || $userLogin->getUserProfile()->isDeleted()) {
            $result['message'] = 'USER_DESACTIVATED_TEXT';
            goto end_of_function;
        }

        $app = $userLogin->getApp();
        if (!$app) {
            $result['message'] = 'APP_NOT_EXISTED_TEXT';
            goto end_of_function;
        }

        if (AppExt::$appType[$app->getType()] != \Phalcon\DI::getDefault()->getShared('moduleConfig')->application->appType) {
            $result['message'] = 'USER_NOT_EXISTED_TEXT';
            goto end_of_function;
        }

        end_of_function:
        return $result;
    }


    /**
     * @param $accessToken
     */
    public static function __refreshUserCognitoAccessToken($userName, $refreshToken)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $authenticationResponse = self::$cognitoClient->refreshAuthentication($userName, $refreshToken);
            if ($authenticationResponse && isset($authenticationResponse['AccessToken']) && $authenticationResponse['AccessToken'] !== '') {
                $accessToken = $authenticationResponse['AccessToken'];
                $newRefreshToken = isset($authenticationResponse['RefreshToken']) ? $authenticationResponse['RefreshToken'] : $refreshToken;
                return ['success' => true, 'accessToken' => $accessToken, 'refreshToken' => $newRefreshToken];
            } else {
                return ['success' => false, 'detail' => $authenticationResponse];
            }
        } catch (TokenVerificationException $e) {
            $return = [
                'success' => false,
                'errorType' => 'TokenVerificationException',
                'message' => $e->getMessage(),
            ];
            return $return;
        } catch (\SMXD\Application\Aws\AwsCognito\Exception $e) {
            $return = [
                'success' => false,
                'errorType' => $e->getErrorCode(),
                'message' => $e->getErrorCode(),
            ];
            return $return;
        } catch (AwsException $e) {
            $return = [
                'success' => false,
                'errorType' => $e->getAwsErrorCode(),
                'message' => $e->getAwsErrorCode(),
            ];
            return $return;
        } catch (\Exception $e) {
            $return = [
                'success' => false,
                'errorType' => $e->getMessage(),
                'message' => $e->getMessage()
            ];
            return $return;
        }
    }

    /**
     * @param $accessToken
     * @return array
     */
    public static function __getUserCognito($accessToken)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {

            $userProfileAws = self::$cognitoClient->getUser($accessToken);
            $userAttributes = new AwsCognitoResult($userProfileAws->get('UserAttributes'));
            return [
                'success' => true,
                'user' => [
                    'awsUuid' => $userProfileAws->get('Username'),
                    'userStatus' => isset($userProfileAws['UserStatus']) ? $userProfileAws['UserStatus'] : '',
                    'email' => $userAttributes->get('email'),
                    'loginUrl' => $userAttributes->get('custom:login_url'),
                    'isEnable' => isset($userProfileAws['Enabled']) ? $userProfileAws['Enabled'] : false,
                ]
            ];


        } catch (CognitoResponseException $e) {
            $return = [
                'success' => false,
                'message' => $e->getCode(),
            ];
            return $return;
        } catch (AwsException $e) {
            $return = [
                'success' => false,
                'message' => $e->getAwsErrorCode(),
            ];
            return $return;
        } catch (\Exception $e) {
            $return = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            return $return;
        }
    }

    /**
     * @param String $email
     * @return array
     */
    public static function __forceChangeUserCognitoPassword(String $email)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $response = self::$cognitoClient->adminResetUserPassword($email);
            return ['success' => true, 'detail' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
        }
    }

    /**
     * @param String $email
     * @param String $password
     * @return array
     */
    public static function __forceChangeStatusPassword(String $email, String $password)
    {

        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $response = self::$cognitoClient->respondToNewPasswordRequiredChallenge($email, $password);
            return ['success' => true, 'detail' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
        }
    }

    /**
     * @param String $userName
     * @param String $userAttribute
     * @param String $userAttributeValue
     * @return array
     */
    public static function __adminForceUpdateUserAttributes(String $userName, String $userAttribute, String $userAttributeValue)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $response = self::$cognitoClient->adminUpdateUserAttributes($userName, $userAttribute, $userAttributeValue);
            return ['success' => true, 'detail' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
        }
    }

    /**
     * @return mixed|null
     */
    public static function __getAccessToken()
    {
        $accessToken = Helpers::__getHeaderValue(Helpers::TOKEN);
        if ($accessToken == '' || $accessToken == null) $accessToken = Helpers::__getHeaderValue(Helpers::TOKEN_KEY);
        if ($accessToken == '' || $accessToken == null) $accessToken = Helpers::__getRequestValue(Helpers::TOKEN);
        if ($accessToken == '' || $accessToken == null) $accessToken = Helpers::__getRequestValue(Helpers::TOKEN_KEY);
        return $accessToken;
    }

    /**
     * @return mixed|null
     */
    public static function __getRefreshToken()
    {
        $refreshToken = Helpers::__getHeaderValue(Helpers::REFRESH_TOKEN);
        return $refreshToken;
    }

    /**
     * @return mixed|null
     */
    public static function __getCurrentAssignment()
    {
        $refreshToken = Helpers::__getHeaderValue(Helpers::CURRENT_ASSIGNMENT);
        return $refreshToken;
    }

    /**
     * @return mixed|null
     */
    public static function __getTimezoneOffset()
    {
        $tzOffset = Helpers::__getHeaderValue(Helpers::TIMEZONE_OFFSET);
        return $tzOffset;
    }

    /**
     * @return null
     */
    public static function __getRequestedPassword()
    {
        $password = Helpers::__getHeaderValue(RequestHeaderHelper::PASSWORD);
        return $password;
    }

    public static function __getJwtKeyCache()
    {
        $resultStart = self::__startCognitoClient();
        return self::$cognitoClient->getCognitoCacheJWTWebkeys();
    }

    /**
     * @return mixed|null
     */
    public static function __getApiKey()
    {
        $apiKey = Helpers::__getHeaderValue(Helpers::API_ACCESS_KEY);
        if ($apiKey == '' || $apiKey == null) $apiKey = Helpers::__getHeaderValue(Helpers::API_ACCESS_KEY);
        if ($apiKey == '' || $apiKey == null) $apiKey = Helpers::__getRequestValue(Helpers::API_ACCESS_KEY);
        if ($apiKey == '' || $apiKey == null) $apiKey = Helpers::__getRequestValue(Helpers::API_ACCESS_KEY);
        return $apiKey;
    }

    /**
     * @return mixed|null
     */
    public static function __getClientId()
    {
        $apiKey = Helpers::__getHeaderValue(Helpers::CLIENT_ID);
        return $apiKey;
    }

    /**
     * @return mixed|null
     */
    public static function __getClientSecret()
    {
        $apiKey = Helpers::__getHeaderValue(Helpers::CLIENT_SECRET);
        return $apiKey;
    }

    /**
     * @return mixed|null
     */
    public static function __getApiGatewayId()
    {
        $id = Helpers::__getHeaderValue(Helpers::API_GATEWAY_ID);
        return $id;
    }

    /**
     * @return mixed|null
     */
    public static function __getAuthorization()
    {
        $authorization = Helpers::__getHeaderValue(Helpers::AUTHORIZATION);
        return $authorization;
    }

    /**
     * @param $awsUuid
     * @return array
     */
    public static function __adminDeleteUser($awsUuid)
    {
        $resultStart = self::__startCognitoClient();
        if ($resultStart['success'] == false) return $resultStart;
        try {
            $response = self::$cognitoClient->adminDeleteUser($awsUuid);
            return ['success' => true, 'detail' => $response];
        } catch (AwsException $e) {
            $exceptionType = $e->getAwsErrorCode();
            return ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
        }
    }
}
