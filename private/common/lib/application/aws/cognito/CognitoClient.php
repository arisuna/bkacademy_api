<?php

namespace SMXD\Application\Aws\AwsCognito;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Exception;
/*
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\Converter\StandardConverter;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
*/

use Phalcon\Di;
use SMXD\Application\Aws\AwsCognito\Exception\ChallengeException;
use SMXD\Application\Aws\AwsCognito\Exception\CognitoResponseException;
use SMXD\Application\Aws\AwsCognito\Exception\TokenExpiryException;
use SMXD\Application\Aws\AwsCognito\Exception\TokenVerificationException;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\JWTEncodedHelper;
use SMXD\Application\Lib\JWTExt;

class CognitoClient
{
    const CHALLENGE_NEW_PASSWORD_REQUIRED = 'NEW_PASSWORD_REQUIRED';
    const UNCONFIRMED = 'UNCONFIRMED';
    const CONFIRMED = 'CONFIRMED';
    const ARCHIVED = 'ARCHIVED';
    const COMPROMISED = 'COMPROMISED';
    const UNKNOWN = 'UNKNOWN';
    const RESET_REQUIRED = 'RESET_REQUIRED';
    const FORCE_CHANGE_PASSWORD = 'FORCE_CHANGE_PASSWORD';

    const ERROR_LIMIT_EXCEEDED_EXCEPTION = 'LimitExceededException';
    const ATTRIBUTE_EMAIL_VERIFIED = 'email_verified';
    const ATTRIBUTE_LOGIN_URL = 'custom:login_url';
    const ATTRIBUTE_URL_REDIRECT = 'custom:url_redirect';
    /**
     * @var string
     */
    protected $appClientId;

    /**
     * @var string
     */
    protected $appClientSecret;

    /**
     * @var CognitoIdentityProviderClient
     */
    protected $client;

    /**
     * @var JWKSet
     */
    protected $jwtWebKeys;

    /**
     * @var string
     */
    protected $region;

    /**
     * @var string
     */
    protected $userPoolId;

    /**
     * CognitoClient constructor.
     *
     * @param CognitoIdentityProviderClient $client
     */
    public function __construct(CognitoIdentityProviderClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    public function authenticate($username, $password)
    {
        try {
            $response = $this->client->initiateAuth([
                'AuthFlow' => 'USER_PASSWORD_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'PASSWORD' => $password,
                    'SECRET_HASH' => $this->cognitoSecretHash($username),
                ],
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);
            return $this->handleAuthenticateResponse($response->toArray());
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }


    /**
     * @param string $username
     * @param string $password
     *
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    public function adminAuthenticate($username, $password)
    {
        try {
            $response = $this->client->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'PASSWORD' => $password,
                    'SECRET_HASH' => $this->cognitoSecretHash($username),
                ],
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $this->handleAuthenticateResponse($response->toArray());
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        } catch (ChallengeException $e) {
            throw $e;
        }
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    public function adminAuthenticateNoPassword($username)
    {
        try {
            $response = $this->client->adminInitiateAuth([
                'AuthFlow' => 'CUSTOM_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'SECRET_HASH' => $this->cognitoSecretHash($username),
                ],
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $this->handleAuthenticateResponse($response->toArray());
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        } catch (ChallengeException $e) {
            throw $e;
        }
    }

    /**
     * @param string $username
     *
     * @return array
     * @throws Exception
     */
    public function adminDeleteUser($username)
    {
        try {
            $response = $this->client->adminDeleteUser([
                'Username' => $username,
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $response->toArray();
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     *
     * @return array
     * @throws Exception
     */
    public function adminDisableUser($username)
    {
        try {
            $response = $this->client->adminDisableUser([
                'Username' => $username,
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $response->toArray();
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param $username
     * @return array
     * @throws Exception
     */
    public function adminConfirmSignup($username)
    {
        try {
            $response = $this->client->adminConfirmSignUp([
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
                'Username' => $username,
            ]);
            return $response->toArray();
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     *
     * @return array
     * @throws Exception
     */
    public function adminEnableUser($username)
    {
        try {
            $response = $this->client->adminEnableUser([
                'Username' => $username,
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $response->toArray();
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $challengeName
     * @param array $challengeResponses
     * @param string $session
     *
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    public function respondToAuthChallenge($challengeName, $credential, $password, $session)
    {
        try {
            $response = $this->client->respondToAuthChallenge([
                'ChallengeName' => $challengeName,
                'ChallengeResponses' => [
                    'USERNAME' => $credential,
                    'NEW_PASSWORD' => $password,
                    'SECRET_HASH' => $this->cognitoSecretHash($credential),
                ],
                'ClientId' => $this->appClientId,
                'Session' => $session,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $this->handleAuthenticateResponse($response->toArray());
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $challengeName
     * @param array $challengeResponses
     * @param string $session
     *
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    public function adminRespondToAuthChallenge($challengeName, $credential, $password)
    {
        try {
            $response = $this->client->adminRespondToAuthChallenge([
                'ChallengeName' => $challengeName,
                'ChallengeResponses' => [
                    'USERNAME' => $credential,
                    'NEW_PASSWORD' => $password,
                    'SECRET_HASH' => $this->cognitoSecretHash($credential),
                ],
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $this->handleAuthenticateResponse($response->toArray());
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     * @param string $newPassword
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    public function respondToNewPasswordRequiredChallenge($username, $newPassword, $session = null)
    {
        return $this->respondToAuthChallenge(
            self::CHALLENGE_NEW_PASSWORD_REQUIRED,
            [
                'NEW_PASSWORD' => $newPassword,
                'USERNAME' => $username,
                'SECRET_HASH' => $this->cognitoSecretHash($username),
            ],
            $session
        );
    }


    /**
     * @param string $username
     * @param string $newPassword
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    public function adminRespondToNewPasswordRequiredChallenge($username, $newPassword)
    {
        try {
            $response = $this->client->adminRespondToAuthChallenge([
                'ChallengeName' => self::CHALLENGE_NEW_PASSWORD_REQUIRED,
                'ChallengeResponses' => [
                    'USERNAME' => $username,
                    'NEW_PASSWORD' => $newPassword,
                    'SECRET_HASH' => $this->cognitoSecretHash($username),
                ],
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $this->handleAuthenticateResponse($response->toArray());
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     * @param string $refreshToken
     * @return string
     * @throws Exception
     */
    public function refreshAuthentication($username, $refreshToken)
    {
        try {
            $response = $this->client->initiateAuth([
                'AuthFlow' => 'REFRESH_TOKEN_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'REFRESH_TOKEN' => $refreshToken,
                    'SECRET_HASH' => $this->appClientSecret,
                ],
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ])->toArray();
            return $response['AuthenticationResult'];
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     * @param string $refreshToken
     * @return string
     * @throws Exception
     */
    public function adminRefreshAuthentication($username, $refreshToken)
    {
        try {
            $response = $this->client->adminInitiateAuth([
                'AuthFlow' => 'REFRESH_TOKEN_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'REFRESH_TOKEN' => $refreshToken,
                    'SECRET_HASH' => $this->appClientSecret,
                ],
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ])->toArray();

            return $response['AuthenticationResult'];
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $accessToken
     * @param string $previousPassword
     * @param string $proposedPassword
     * @throws Exception
     * @throws TokenExpiryException
     * @throws TokenVerificationException
     */
    public function changePassword($accessToken, $previousPassword, $proposedPassword)
    {
        $this->verifyAccessToken($accessToken);

        try {
            $this->client->changePassword([
                'AccessToken' => $accessToken,
                'PreviousPassword' => $previousPassword,
                'ProposedPassword' => $proposedPassword,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $confirmationCode
     * @param string $username
     * @throws Exception
     */
    public function confirmUserRegistration($confirmationCode, $username)
    {
        try {
            $this->client->confirmSignUp([
                'ClientId' => $this->appClientId,
                'ConfirmationCode' => $confirmationCode,
                'SecretHash' => $this->cognitoSecretHash($username),
                'Username' => $username,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $accessToken
     * @throws Exception
     * @throws TokenExpiryException
     * @throws TokenVerificationException
     */
    public function getUser($accessToken, $verifyAccessToken = false)
    {
        if ($verifyAccessToken == true) {
            $var = $this->verifyAccessToken($accessToken);
        }
        //

        try {
            return $this->client->getUser([
                'AccessToken' => $accessToken,
            ]);

        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $accessToken
     * @throws Exception
     * @throws TokenExpiryException
     * @throws TokenVerificationException
     */
    public function deleteUser($accessToken)
    {
        $this->verifyAccessToken($accessToken);

        try {
            $this->client->deleteUser([
                'AccessToken' => $accessToken,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @return JWKSet
     */
    public function getJwtWebKeys()
    {
        if (!$this->jwtWebKeys) {
            $json = $this->downloadJwtWebKeys();
            $this->jwtWebKeys = JWTEncodedHelper::decodeAwsCognitoSignature($json);
        }
        return $this->jwtWebKeys;
    }

    /**
     * @param JWKSet $jwtWebKeys
     */
    public function setJwtWebKeys(JWKSet $jwtWebKeys)
    {
        $this->jwtWebKeys = $jwtWebKeys;
    }

    /**
     * @return string
     */
    protected function downloadJwtWebKeys()
    {
        $url = sprintf(
            'https://cognito-idp.%s.amazonaws.com/%s/.well-known/jwks.json',
            $this->region,
            $this->userPoolId
        );
        $di = \Phalcon\DI::getDefault();
        $cacheName = 'UserCognito_' . date('Ymd') . base64_encode($url);
        $cacheManager = $di->get('cache');
        if ($cacheManager) {
            if ($cacheManager->exists($cacheName)) {
                return $cacheManager->get($cacheName);
            } else {
                $cacheManager->save($cacheName, file_get_contents($url));
                return $cacheManager->get($cacheName);
            }
        }
    }

    /**
     * @return mixed
     */
    public function getCognitoCacheJWTWebkeys()
    {
        $url = sprintf(
            'https://cognito-idp.%s.amazonaws.com/%s/.well-known/jwks.json',
            $this->region,
            $this->userPoolId
        );
        $di = \Phalcon\DI::getDefault();
        $cacheName = 'UserCognito_' . date('Ymd') . base64_encode($url);
        $cacheManager = $di->get('cache');
        return ['key' => $cacheName, 'value' => $cacheManager->get($cacheName)];
    }

    /**
     * Create AWS User with final password, cognito will send CONFIRMATION CODE of this FINAL PASSWORD
     * @param string $username
     * @param string $password
     * @param array $attributes
     * @return string
     * @throws Exception
     */
    public function registerUser($username, $password, array $attributes = [])
    {
        $userAttributes = [];
        foreach ($attributes as $key => $value) {
            $userAttributes[] = [
                'Name' => (string)$key,
                'Value' => (string)$value,
            ];
        }

        try {
            $response = $this->client->signUp([
                'ClientId' => $this->appClientId,
                'Password' => $password,
                'SecretHash' => $this->cognitoSecretHash($username),
                'UserAttributes' => $userAttributes,
                'Username' => $username,
            ]);

            return $response['UserSub'];
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }


    /**
     * Create User Congito With Temporary Password/ No CONFIRMATION CODE is Sent
     * because the USER will confirm when he connect the first time, in LOGIN PROCESS
     * @param string $username
     * @param string $password
     * @param array $attributes
     * @return string
     * @throws Exception
     */
    public function adminRegisterUserOnly(String $username, String $temporaryPassword, array $attributes = [])
    {
        $userAttributes = [];
        foreach ($attributes as $key => $value) {
            $userAttributes[] = [
                'Name' => (string)$key,
                'Value' => (string)$value,
            ];
        }

        if (!isset($attributes['email_verified'])) {
            $userAttributes[] = [
                'Name' => 'email_verified',
                'Value' => 'true'
            ];
        }

        try {
            $response = $this->client->AdminCreateUser([
                'ForceAliasCreation' => true,
                'MessageAction' => 'SUPPRESS',
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
                'TemporaryPassword' => $temporaryPassword,
                'SecretHash' => $this->cognitoSecretHash($username),
                'UserAttributes' => $userAttributes,
                'Username' => $username,
            ]);

            $result = $response->toArray();
            if (isset($result['User']) && isset($result['User']['Username']) && is_string($result['User']['Username'])) {
                return $result['User']['Username'];
            } else {
                throw new Exception('Aws Cognito Failed');
            }
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * Create User Congito With Temporary Password/ No CONFIRMATION CODE is Sent
     * because the USER will confirm when he connect the first time, in LOGIN PROCESS
     * @param string $username
     * @param string $password
     * @param array $attributes
     * @return string
     * @throws Exception
     */
    public function adminSetUserPassword(String $username, String $password)
    {

        try {
            $response = $this->client->adminSetUserPassword([
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
                'Password' => $password,
                'Permanent' => true,
                'Username' => $username,
            ]);

            $result = $response->toArray();
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @param array $attributes
     * @return string
     * @throws Exception
     */
    public function adminRegisterUser($username, $password, array $attributes = [])
    {
        $userAttributes = [];
        foreach ($attributes as $key => $value) {
            $userAttributes[] = [
                'Name' => (string)$key,
                'Value' => (string)$value,
            ];
        }

        $userAttributes[] = [
            'Name' => 'email_verified',
            'Value' => 'true'
        ];

        try {
            $response = $this->client->AdminCreateUser([
                'ForceAliasCreation' => true,
                'MessageAction' => 'RESEND',
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
                'TemporaryPassword' => $password,
                'SecretHash' => $this->cognitoSecretHash($username),
                'UserAttributes' => $userAttributes,
                'Username' => $username,
            ]);

            $result = $response->toArray();
            if (isset($result['User']) && isset($result['User']['Username']) && is_string($result['User']['Username'])) {
                return $result['User']['Username'];
            } else {
                throw new Exception('Aws Cognito Failed');
            }
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $confirmationCode
     * @param string $username
     * @param string $proposedPassword
     * @throws Exception
     */
    public function resetPassword($confirmationCode, $username, $proposedPassword)
    {
        try {
            $this->client->confirmForgotPassword([
                'ClientId' => $this->appClientId,
                'ConfirmationCode' => $confirmationCode,
                'Password' => $proposedPassword,
                'SecretHash' => $this->cognitoSecretHash($username),
                'Username' => $username,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     * @throws Exception
     */
    public function resendRegistrationConfirmationCode($username)
    {
        try {
            return $this->client->resendConfirmationCode([
                'ClientId' => $this->appClientId,
                'SecretHash' => $this->cognitoSecretHash($username),
                'Username' => $username,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     * @throws Exception
     */
    public function sendForgottenPasswordRequest($username)
    {
        try {
            $this->client->forgotPassword([
                'ClientId' => $this->appClientId,
                'SecretHash' => $this->cognitoSecretHash($username),
                'Username' => $username,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $appClientId
     */
    public function setAppClientId($appClientId)
    {
        $this->appClientId = $appClientId;
    }

    /**
     * @param string $appClientSecret
     */
    public function setAppClientSecret($appClientSecret)
    {
        $this->appClientSecret = $appClientSecret;
    }

    /**
     * @param CognitoIdentityProviderClient $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @param string $region
     */
    public function setRegion($region)
    {
        $this->region = $region;
    }

    /**
     * set default configuration of cognito
     */
    public function setDefaultConfiguration($configuration = null)
    {
        if ($configuration == null) {
            $di = Di::getDefault();
            $appConfig = $di->get('appConfig');
            $this->setAppClientId($appConfig->aws->awsAppClientId);
            $this->setAppClientSecret($appConfig->aws->awsAppClientSecret);
            $this->setRegion($appConfig->aws->awsCognitoRegion);
            $this->setUserPoolId($appConfig->aws->awsUserPoolId);
        } else {
            $this->setAppClientId($configuration['awsAppClientId']);
            $this->setAppClientSecret($configuration['awsAppClientSecret']);
            $this->setRegion($configuration['awsCognitoRegion']);
            $this->setUserPoolId($configuration['awsUserPoolId']);
        }
    }

    /**
     * @param string $userPoolId
     */
    public function setUserPoolId($userPoolId)
    {
        $this->userPoolId = $userPoolId;
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws TokenVerificationException
     */
    public function decodeAccessToken($accessToken)
    {
        $keySet = $this->getJwtWebKeys();
        return JWTEncodedHelper::decodeWithKey($accessToken, $keySet, 'RS256', true);
    }

    /**
     * Verifies the given access token and returns the username
     * @param string $accessToken
     * @return string
     * @throws TokenVerificationException
     *
     * @throws TokenExpiryException
     */
    public function verifyAccessToken($accessToken)
    {
        JWTExt::$leeway = Di::getDefault()->get('appConfig')->jwt->leeway;;

        $jwtPayload = $this->decodeAccessToken($accessToken);

        if (JWTEncodedHelper::__isExpired() == true) {
            throw new TokenExpiryException('invalid exp');
        }

        $expectedIss = sprintf('https://cognito-idp.%s.amazonaws.com/%s', $this->region, $this->userPoolId);

        if (JWTEncodedHelper::$errorType != '') {
            throw new TokenVerificationException(JWTEncodedHelper::$errorMessage);
        }

        if ($jwtPayload['iss'] !== $expectedIss) {
            throw new TokenVerificationException('invalid iss');
        }

        if ($jwtPayload['token_use'] !== 'access') {
            throw new TokenVerificationException('invalid token_use');
        }

        if ($jwtPayload['exp'] < time()) {
            throw new TokenExpiryException('invalid exp');
        }

        return $jwtPayload['username'];
    }

    /**
     * @param string $username
     *
     * @return string
     */
    public function cognitoSecretHash($username)
    {
        return $this->hash($username . $this->appClientId);
    }

    /**
     * @param string $message
     *
     * @return string
     */
    protected function hash($message)
    {
        $hash = hash_hmac(
            'sha256',
            $message,
            $this->appClientSecret,
            true
        );

        return base64_encode($hash);
    }

    /**
     * @param array $response
     * @return array
     * @throws ChallengeException
     * @throws Exception
     */
    protected function handleAuthenticateResponse(array $response)
    {
        if (isset($response['AuthenticationResult'])) {
            return $response['AuthenticationResult'];
        }


        if (isset($response['ChallengeName'])) {
            $exception = ChallengeException::createFromAuthenticateResponse($response);
            throw $exception;
        }

        throw new Exception('Could not handle AdminInitiateAuth response');
    }

    /**
     * @param string $accessToken
     * @throws Exception
     * @throws TokenExpiryException
     * @throws TokenVerificationException
     */
    public function updateUser($accessToken, $arrayValues = [])
    {
        $this->verifyAccessToken($accessToken);

        $arrayParams = [];

        foreach ($arrayValues as $key => $value) {
            if ($key == 'uuid' || $key == 'username') {
                continue;
            }
            $arrayParams[] = [
                'Name' => $key,
                'Value' => (string)$value,
            ];
        }

        try {
            return $this->client->updateUserAttributes([
                'AccessToken' => $accessToken,
                'UserAttributes' => $arrayParams,
            ]);
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     *
     * @return array
     * @throws Exception
     */
    public function adminGetUser($username)
    {
        try {
            $response = $this->client->adminGetUser([
                'Username' => $username,
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
            ]);

            return $response->toArray();
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $accessToken
     * @return array
     * @throws TokenVerificationException
     */
    public function __old_decodeAccessToken($accessToken)
    {
        $keySet = $this->getJwtWebKeys();

        $algorithmManager = AlgorithmManager::create([
            new RS256(),
        ]);

        $serializerManager = new CompactSerializer(new StandardConverter());

        $jws = $serializerManager->unserialize($accessToken);
        $jwsVerifier = new JWSVerifier(
            $algorithmManager
        );

        if (!$jwsVerifier->verifyWithKeySet($jws, $keySet, 0)) {
            throw new TokenVerificationException('could not verify token');
        }

        return json_decode($jws->getPayload(), true);

    }

    /**
     * @param $userName
     * @return array
     * @throws Exception
     */
    public function adminResetUserPassword($userName)
    {
        try {
            $response = $this->client->adminResetUserPassword([
                'Username' => $userName,
                'UserPoolId' => $this->userPoolId,
            ]);
            $result = $response->toArray();
            $result['success'] = true;
            return $result;
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param String $userName
     * @param String $userAttribute
     * @param String $userAttributeValue
     * @return array
     * @throws Exception
     */
    public function adminUpdateUserAttributes(String $userName, String $userAttribute, String $userAttributeValue)
    {
        try {
            $response = $this->client->adminUpdateUserAttributes([
                'UserAttributes' => [ // REQUIRED
                    [
                        'Name' => $userAttribute, // REQUIRED
                        'Value' => $userAttributeValue,
                    ],
                ],
                'Username' => $userName,
                'UserPoolId' => $this->userPoolId,
            ]);
            $result = $response->toArray();
            $result['success'] = true;
            return $result;
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @param array $attributes
     * @return string
     * @throws Exception
     */
    public function adminRegisterUserResend($username, $password, array $attributes = [])
    {
        $userAttributes = [];
        foreach ($attributes as $key => $value) {
            $userAttributes[] = [
                'Name' => (string)$key,
                'Value' => (string)$value,
            ];
        }

        $userAttributes[] = [
            'Name' => 'email_verified',
            'Value' => 'true'
        ];

        try {
            $response = $this->client->AdminCreateUser([
                'ForceAliasCreation' => true,
                'MessageAction' => 'RESEND',
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
                'TemporaryPassword' => $password,
                'SecretHash' => $this->cognitoSecretHash($username),
                'UserAttributes' => $userAttributes,
                'Username' => $username,
            ]);

            $result = $response->toArray();
            if (isset($result['User']) && isset($result['User']['Username']) && is_string($result['User']['Username'])) {
                return $result['User']['Username'];
            } else {
                throw new Exception('Aws Cognito Failed');
            }
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

    /**
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * @param $userName
     * @return mixed
     * @throws Exception
     */
    public function adminAddUserToGroup($userName, $groupName)
    {
        try {
            $response = $this->client->adminAddUserToGroup([
                'GroupName' => $groupName, // REQUIRED
                'ClientId' => $this->appClientId,
                'UserPoolId' => $this->userPoolId,
                'Username' => $userName, // REQUIRED
            ]);

            $result = $response->toArray();
            $result['success'] = true;
        } catch (CognitoIdentityProviderException $e) {
            throw CognitoResponseException::createFromCognitoException($e);
        }
    }

}