<?php

namespace SMXD\Application\Models;

use Phalcon\Acl;
use Phalcon\Acl\Adapter\Memory;
use Phalcon\Http\Client\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Validator;
use Phalcon\Mvc\Model\Validator\Regex;
use Phalcon\Security;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\Regex as RegexValidator;
use Phalcon\Validation\Validator\PasswordStrength as PasswordStrength;
use Phalcon\Validation\Validator\StringLength as StringLength;
use SMXD\Application\Aws\AwsCognito\CognitoClient;
use SMXD\Application\Lib\CognitoAppHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class UserLoginExt extends UserLogin
{

    use ModelTraits;

    const STATUS_INACTIVATED = 0;
    const STATUS_ACTIVATED = 1;
    const STATUS_DELETED = -1;
    const STATUS_ARCHIVED = -1;

    const AWS_FIELD_LOGIN_URL = 'custom:login_url';


    protected $cognitoLogin;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
        $this->hasSnapshotData(true);
        $this->useDynamicUpdate(true);

        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable(
            array(
                'beforeValidationOnCreate' => array(
                    'field' => array(
                        'created_at', 'updated_at'
                    ),
                    'format' => 'Y-m-d H:i:s'
                ),
                'beforeValidationOnUpdate' => array(
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                )
            )
        ));

        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\SoftDelete(
            [
                'field' => 'status',
                'value' => self::STATUS_DELETED
            ]
        ));

        $this->hasOne('id', 'SMXD\Application\Models\UserExt', 'user_login_id', [
            'alias' => 'UserProfile',
            // 'reusable' => true,
        ]);

    }

    /**
     * @return mixed
     */
    public function beforeValidation()
    {
        // Validate login
        $validator = new Validation();

        $validator->add(
            'email', //your field name
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'EMAIL_REQUIRED_TEXT'
            ])
        );

        $validator->add(
            'email', //your field name
            new EmailValidator([
                'model' => $this,
                'message' => 'LOGIN_INVALID_EMAIL_TEXT'
            ])
        );

        if ($this->getEmail() != '') {
            $validator->add(
                'email', //your field name
                new UniquenessValidator([
                    'model' => $this,
                    'message' => 'EMAIL_LOGIN_EXIST_TEXT'
                ])
            );
        }
        $validator->add(
            'password', //your field name
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'PASSWORD_EMPTY_TEXT'
            ])
        );


        $validator->add(
            'password', //your field name
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'PASSWORD_EMPTY_TEXT'
            ])
        );


        /** sanitize email */
        $this->setEmail(Helpers::__sanitizeEmail($this->getEmail()));

        return $this->validate($validator);
    }

    /**
     *
     */
    public function beforeSave()
    {

    }

    /**
     *
     */
    public function beforeUpdate()
    {
        if ($this->isDeleted() == true && $this->hasChangedInteger('status') && $this->getOldFieldValue('status') !== $this->getStatus()) {
            $this->setEmailDeleted($this->getEmail());
            $this->setEmail(Helpers::__uuid() . "@reloday.com");
        }
    }


    /**
     * @return array
     */
    public function resetPassword()
    {
        $password = \SMXD\Application\Lib\Helpers::password();
        $security = new Security();
        $this->setPassword($security->hash($password));
        $resultUpdate = $this->__quickUpdate();
        if ($resultUpdate['success'] == true) {
            $resultUpdate['data'] = $password;
            $resultUpdate['password'] = $password;
        }
        return $resultUpdate;
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {
        ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/
        $email = Helpers::__getCustomValue('email', $custom);
        if ($email != '' && Helpers::__isEmail($email) && $email != $this->getEmail()) {
            $this->setEmail($email);
        }

        $password = Helpers::__getCustomValue('password', $custom);
        if ($password != '' && is_string($password)) {
            $resultValidationPassword = Helpers::__validatePassword($password);
            if ($resultValidationPassword['success'] == true) {
                $security = new Security();
                $this->setPassword($security->hash($password)); //HASH PASSWORD
            } else {
                return $resultValidationPassword;
            }
        }
        /****** END YOUR CODE **/
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getStatus() == self::STATUS_DELETED);
    }

    /**
     * @param $email
     * @return bool
     */
    public static function __ifEmailAvailable($email)
    {
        $userLogin = self::__findFirstWithCache([
            'conditions' => 'email = :email: AND status = :status_active:',
            'bind' => [
                'status_active' => self::STATUS_ACTIVATED,
                'email' => $email
            ]
        ]);

        if ($userLogin) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @return bool
     */
    public function isDesactivated()
    {
        return $this->getStatus() == self::STATUS_INACTIVATED;
    }

    /**
     * @return bool
     */
    public function isConvertedToUserCognito()
    {
        if ($this->getAwsUuid() != '') {
            $result = ApplicationModel::__getUserCognitoByUsername($this->getAwsUuid());
            if ($result['success'] == true) {
                $this->setCognitoLogin($result['user']);
                return true;
            } else {
                return false;
            }
        } else {
            $result = ApplicationModel::__getUserCognitoByEmail($this->getEmail());
            if ($result['success'] == true) {
                $this->setCognitoLogin($result['user']);
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @return bool
     */
    public function isForceChangePassword()
    {
        if ($this->getCognitoLogin() == null) {
            if ($this->isConvertedToUserCognito() == true) {
                $cognitoLogin = $this->getCognitoLogin();
                return $cognitoLogin['isForceChangePassword'];
            }
            return false;
        }
        $cognitoLogin = $this->getCognitoLogin();
        return $cognitoLogin['isForceChangePassword'];
    }

    /**
     * @return bool
     */
    public function isCognitoEmailVerified()
    {
        if ($this->getCognitoLogin() == null) {
            if ($this->isConvertedToUserCognito() == true) {
                $cognitoLogin = $this->getCognitoLogin();
                return $cognitoLogin['isEmailVerified'];
            }
            return false;
        }
        $cognitoLogin = $this->getCognitoLogin();
        return $cognitoLogin['isEmailVerified'];
    }

    /**
     * @return mixed
     */
    public function getCognitoLogin()
    {
        return $this->cognitoLogin;
    }

    /**
     * @param $login
     */
    public function setCognitoLogin($login)
    {
        $this->cognitoLogin = $login;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->getStatus() == self::STATUS_ACTIVATED;
    }

    /**
     * @return array
     */
    public function forceResetPassword()
    {
        ApplicationModel::__startCognitoClient();
        if ($this->isConvertedToUserCognito()) {
            $cognitoLogin = $this->getCognitoLogin();

            $loginUrl = $this->getEmployeeOrUserProfile()->getAppUrl();

            if ($this->isCognitoEmailVerified() == false) {
                $resultSent = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_EMAIL_VERIFIED, 'true');
            }
            if ($cognitoLogin && $cognitoLogin['userStatus'] == CognitoClient::CONFIRMED) {
                $resultLoginUrl = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_LOGIN_URL, $loginUrl);
                $resultUrlRedirect = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_URL_REDIRECT, getenv('API_DOMAIN'));

                $resultSent = ApplicationModel::__forceChangeUserCognitoPassword($this->getEmail());
            } elseif ($cognitoLogin['userStatus'] == CognitoClient::RESET_REQUIRED) {
                $resultLoginUrl = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_LOGIN_URL, $loginUrl);
                $resultUrlRedirect = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_URL_REDIRECT, getenv('API_DOMAIN'));

                $resultSent = ApplicationModel::__forceChangeUserCognitoPassword($this->getEmail());
            } elseif ($cognitoLogin && $cognitoLogin['userStatus'] == CognitoClient::FORCE_CHANGE_PASSWORD) {
                $resultSent = $this->forceCreateCognitoLogin();
            } else {
                $resultSent = ApplicationModel::__sendUserCognitoRecoveryPasswordRequest($this->getEmail());
            }
            return $resultSent;
        } else {
            $resultSent = $this->forceCreateCognitoLogin();
            return $resultSent;
        }
    }

    /**
     * Update Password of User Login Cognito
     * @param string $password
     */
    public function forceUpdatePasswordCognitoLogin($password = '')
    {

        ApplicationModel::__startCognitoClient();

        if ($this->isConvertedToUserCognito()) {
            $cognitoLogin = $this->getCognitoLogin();

            $loginUrl = $this->getEmployeeOrUserProfile()->getAppUrl();

            if ($this->isCognitoEmailVerified() == false) {
                $resultSent = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_EMAIL_VERIFIED, 'true');
            }

            if ($cognitoLogin && $cognitoLogin['userStatus'] == CognitoClient::CONFIRMED) {
                $resultSent = CognitoAppHelper::__forceUpdatePassword($this->getEmail(), $password);
            } elseif ($cognitoLogin['userStatus'] == CognitoClient::RESET_REQUIRED) {
                $resultLoginUrl = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_LOGIN_URL, $loginUrl);
                $resultUrlRedirect = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_URL_REDIRECT, getenv('API_DOMAIN'));
                $resultSent = CognitoAppHelper::__forceUpdatePassword($this->getEmail(), $password);
            } elseif ($cognitoLogin && $cognitoLogin['userStatus'] == CognitoClient::FORCE_CHANGE_PASSWORD) {
                $resultSent = $this->forceCreateCognitoLogin($password);
            } elseif ($cognitoLogin && $cognitoLogin['userStatus'] == CognitoClient::UNCONFIRMED) {
                $resultLoginUrl = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_LOGIN_URL, $loginUrl);
                $resultUrlRedirect = ApplicationModel::$cognitoClient->adminUpdateUserAttributes($this->getEmail(), CognitoClient::ATTRIBUTE_URL_REDIRECT, getenv('API_DOMAIN'));

                $resultSent = CognitoAppHelper::__forceUpdatePassword($this->getEmail(), $password);
            } else {
                $resultSent = ['success' => false, 'cognitoLoginStatus' => $cognitoLogin['userStatus']];
            }
            return $resultSent;
        } else {
            $resultSent = ['success' => false];
            return $resultSent;
        }
    }

    /**
     * @return array
     */
    public function forceCreateCognitoLogin($password = '')
    {
        ApplicationModel::__startCognitoClient();

        if ($password == '') {
            $password = Helpers::password();
            $resultResetPassword = $this->resetPassword();
            if ($resultResetPassword["success"] == false) {
                $result = $resultResetPassword;
                return $result;
            }
            $password = $resultResetPassword['password'];
        }

        $result = ApplicationModel::__adminRegisterUserCognito([
            'email' => $this->getEmail(),
            'password' => $password,
        ], $this);


        $result["checkEmail"] = true;
        return $result;
    }


    /**
     * @return array
     */
    public function createCognitoLogin($password = '', $userProfile = null)
    {
        if ($password == '') {
            $resultResetPassword = $this->resetPassword();
            if ($resultResetPassword["success"] == false) {
                $result = $resultResetPassword;
                return $result;
            }
            $password = $resultResetPassword['password'];
        }

        $varDump = [
            'email' => $this->getEmail(),
            'password' => $password,
            'loginUrl' => is_object($userProfile) && $userProfile->isEmployee() == true ? $userProfile->getEmployeeUrl() : $this->getLoginUrl()
        ];

        $result = ApplicationModel::__addNewUserCognito([
            'email' => $this->getEmail(),
            'password' => $password,
            'loginUrl' => is_object($userProfile) && $userProfile->isEmployee() == true ? $userProfile->getEmployeeUrl() : $this->getLoginUrl()
        ], $this, false);


        $result["checkEmail"] = true;
        return $result;
    }

    /**
     * @return array
     */
    public function adminForceUpdateEmail()
    {
        $result = ApplicationModel::__adminForceUpdateUserAttributes($this->getAwsUuid(), 'email', $this->getEmail());
        return $result;
    }

    /**
     * @return array
     */
    public static function skippedColumns()
    {
        return [
            'password',
            'activation',
        ];
    }

    /**
     *
     */
    public function beforeDelete()
    {
        $this->setEmailDeleted($this->getEmail());
        $this->setEmail(Helpers::__uuid() . "@reloday.com");
    }

    /**
     * @param array $custom
     */
    public function createNewUserLogin($data = [])
    {
        $password = Helpers::__getCustomValue('password', $data);
        $userGroupId = Helpers::__getCustomValue('user_group_id', $data);
        $appId = Helpers::__getCustomValue('app_id', $data);
        $email = Helpers::__getCustomValue('email', $data);
        $awsUuid = Helpers::__getCustomValue('awsUuid', $data);
        $model = $this;
        $model->setEmail($email);
        if ($password != '' && is_string($password)) {
            $resultValidationPassword = Helpers::__validatePassword($password);
            if ($resultValidationPassword['success'] == true) {
                $security = new Security();
                $model->setPassword($security->hash($password)); //HASH PASSWORD
            } else {
                return $resultValidationPassword;
            }
        }

        if($awsUuid){
            $model->setAwsUuid($awsUuid);
        }

        $model->setUserGroupId($userGroupId);
        $model->setAppId($appId);
        $model->setStatus(self::STATUS_ACTIVATED);
        return $model->__quickCreate();
    }

    /**
     * @param array $custom
     */
    public static function __createNewUserLogin($data = [])
    {
        $password = Helpers::__getCustomValue('password', $data);
        $userGroupId = Helpers::__getCustomValue('user_group_id', $data);
        $appId = Helpers::__getCustomValue('app_id', $data);
        $email = Helpers::__getCustomValue('email', $data);
        $model = new self();
        $model->setEmail($email);
        if ($password != '' && is_string($password)) {
            $resultValidationPassword = Helpers::__validatePassword($password);
            if ($resultValidationPassword['success'] == true) {
                $security = new Security();
                $model->setPassword($security->hash($password)); //HASH PASSWORD
            } else {
                return $resultValidationPassword;
            }
        }
        $model->setUserGroupId($userGroupId);
        $model->setAppId($appId);
        $model->setStatus(self::STATUS_ACTIVATED);
        return $model->__quickCreate();
    }

    /**
     * Updated email if user deactivated
     */
    public function clearUserLoginWhenUserDeactivated(){
        $resultCognito = $this->isConvertedToUserCognito();
        if ($resultCognito){
            $cognitoLogin = $this->getCognitoLogin();
            if ($this->getAwsUuid()){
                $resultDeleteCognito = ApplicationModel::__adminDeleteUser($this->getAwsUuid());
            }else{
                $resultDeleteCognito = ApplicationModel::__adminDeleteUser($this->getEmail());
            }
        }

        $resultRemove = $this->__quickRemove();
        return $resultRemove;
    }

    /**
     * Updated email if user deactivated
     */
    public function removeCognitoUser(){
        $resultCognito = $this->isConvertedToUserCognito();
        if ($resultCognito){
            $cognitoLogin = $this->getCognitoLogin();
            if ($this->getAwsUuid()){
                $resultDeleteCognito = ApplicationModel::__adminDeleteUser($this->getAwsUuid());
            }else{
                $resultDeleteCognito = ApplicationModel::__adminDeleteUser($this->getEmail());
            }

            return $resultDeleteCognito;
        }

        return ['success' => true];
    }

    public function getUserSsoIdpConfig(){
        $ssoIdpConfig = SsoIdpConfigExt::findFirst([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => $this->getUserProfile()->getCompany()->getId(),
            ],
            "order" => "created_at DESC",
        ]);

        if($ssoIdpConfig && $ssoIdpConfig->getIsActive() == ModelHelper::YES){
            return $ssoIdpConfig;
        }else{
            return false;

        }
    }

    public function getEmployeeSsoIdpConfig(){
        $ssoIdpConfig = SsoIdpConfigExt::findFirst([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => $this->getEmployee()->getCompany()->getId(),
            ],
            "order" => "created_at DESC",
        ]);
        if($ssoIdpConfig && $ssoIdpConfig->getIsActive() == ModelHelper::YES){
            return $ssoIdpConfig;
        }else{
            return false;

        }
    }


    public function getValidUserLoginSso($ipAddress){
        $userLoginSso = UserLoginSsoExt::findFirst([
            'conditions' => 'user_login_id = :user_login_id: and lifetime > :current_time: and ip_address = :ip_address: and is_alive = :alive_yes:',
            'bind' => [
                'user_login_id' => $this->id,
                'current_time' => time(),
                'ip_address' => $ipAddress,
                'alive_yes' => UserLoginSsoExt::ALIVE_YES,
            ],
            'order' => 'created_at DESC',
        ]);

        return $userLoginSso;
    }

    /**
     * @param $ipAddress
     * @param $requestId
     * @return mixed
     */
    public function getUserLoginSsoWithRequest($ipAddress, $requestId){
        $userLoginSso = UserLoginSsoExt::findFirst([
            'conditions' => 'user_login_id = :user_login_id: and request_id = :request_id: and is_alive = :alive_no: and ip_address = :ip_address:',
            'bind' => [
                'user_login_id' => $this->id,
                'alive_no' => UserLoginSsoExt::ALIVE_NO,
                'ip_address' => $ipAddress,
                'request_id' => $requestId,
            ],
            'order' => 'created_at DESC',
        ]);

        return $userLoginSso;
    }

    /**
     * @return array
     */
    public function updateDateConnectedAt(){
        if ($this->getFirstconnectAt() == null){
            $this->setFirstconnectAt(date('Y-m-d H:i:s'));
        }
        $this->setLastconnectAt(date('Y-m-d H:i:s'));
        return $this->__quickUpdate();
    }
}