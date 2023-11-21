<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Regex as RegexValidator;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\Email as EmailValidator;
use SMXD\Application\Aws\AwsCognito\CognitoClient;
use SMXD\Application\Lib\AttributeHelper;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ConstantHelper;
use SMXD\Application\Lib\Helpers as Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\SMXDCachePrefixHelper;
use SMXD\Application\Behavior\UserCacheBehavior;
use SMXD\Application\Lib\SequenceHelper;
use Phalcon\Utils\Slug as Slug;

use Phalcon\Security\Random;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\TextHelper;
use SMXD\Application\Traits\ModelTraits;

use Phalcon\Acl;
use Phalcon\Acl\Adapter\Memory;
use Phalcon\Mvc\Model\Validator;
use Phalcon\Security;
use Phalcon\Validation\Validator\StringLength as StringLength;
use SMXD\Application\Lib\CognitoAppHelper;

class UserExt extends User
{
    use ModelTraits;
    const STATUS_DELETED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const ACTIVATED = 1;
    const INACTIVATED = 0;

    const LVL_3 = 3;
    const LVL_2 = 2;
    const LVL_1 = 1;
    const LVL_0 = 0;

    const LOGIN_STATUS_INACTIVE = 1;
    const LOGIN_STATUS_LOGIN_MISSING = 2;
    const LOGIN_STATUS_PENDING = 3;
    const LOGIN_STATUS_HAS_ACCESS = 4;

    protected $cognitoLogin;
    /**
     * add read connection service and write connection
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
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
        $this->addBehavior(new \Phalcon\Mvc\Model\Behavior\SoftDelete([
            'field' => 'status',
            'value' => self::STATUS_DELETED
        ]));

        $this->addBehavior(
            new UserCacheBehavior()
        );

        $this->belongsTo('company_id', 'SMXD\Application\Models\CompanyExt', 'id', [
            'alias' => 'Company',
        ]);

        $this->belongsTo('user_group_id', 'SMXD\Application\Models\StaffUserGroupExt', 'id', [
            'alias' => 'UserGroup',
        ]);

        $this->hasMany('id', 'SMXD\Application\Models\UserSettingExt', 'user_id', [
            'alias' => 'UserSetting'
        ]);
    }


    /**
     *
     */
    public function afterDelete()
    {
        $this->setDeletedAt(time());
    }

    /**
     * @return mixed
     */
    public function beforeValidation()
    {

        $validator = new Validation();

        $validator->add(
            'firstname', //your field name
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'FIRSTNAME_REQUIRED_TEXT'
            ])
        );

        $validator->add(
            'lastname', //your field name
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'LASTTNAME_REQUIRED_TEXT'
            ])
        );

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
                'message' => 'EMAIL_INVALID_TEXT'
            ])
        );

        /** sanitize email */
        $this->setEmail(Helpers::__sanitizeEmail($this->getEmail()));

        return $this->validate($validator);
    }

    /**
     *
     */
    function beforeValidationOnUpdate()
    {
        $validator = new Validation();

        $validator->add(
            'firstname', //your field name
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'FIRSTNAME_REQUIRED_TEXT'
            ])
        );

        $validator->add(
            'lastname', //your field name
            new PresenceOfValidator([
                'model' => $this,
                'message' => 'LASTTNAME_REQUIRED_TEXT'
            ])
        );

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
                'message' => 'EMAIL_INVALID_TEXT'
            ])
        );

        /** sanitize email */
        $this->setEmail(Helpers::__sanitizeEmail($this->getEmail()));

        return $this->validate($validator);
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return $this->getUserGroupId() == StaffUserGroupExt::GROUP_ADMIN;
    }

    /**
     * @return bool
     */
    public function isCrmAdmin()
    {
        return $this->getUserGroupId() == StaffUserGroupExt::GROUP_CRM_ADMIN;
    }

    /**
     * @return bool
     */
    public function isEndUser()
    {
        return $this->getIsEndUser() == Helpers::YES;
    }


    /**
     * generate nickname before save
     */
    public function beforeSave()
    {

    }

    /**
     * generate nickname before save
     */
    public function beforeValidationOnCreate()
    {

    }


    public function __save($custom = [])
    {
        $req = new Request();
        $model = $this;
        $data = $req->getPost();
        $model->setId(isset($custom['id']) ? $custom['id'] : 0);
        if ($req->isPut()) { // Request update
            $model = $this->findFirstById($req->getPut('id'));
            if (!$model instanceof $this) {
                return [
                    'success' => false,
                    'message' => 'USER_NOT_FOUND_TEXT'
                ];
            }
            $data = $req->getPut();
        }

        // Assign data to model
        if ($req->isPost()) {
            $model->setUuid(ApplicationModel::uuid());
        }

        $userGroupId = Helpers::getIntPositive($custom, 'user_group_id') ? Helpers::getIntPositive($custom, 'user_group_id') :
            (Helpers::getIntPositive($data, 'user_group_id') ? Helpers::getIntPositive($data, 'user_group_id') : (
            Helpers::getIntPositive($data, 'group_id') ? Helpers::getIntPositive($data, 'group_id') : $model->getUserGroupId()));

        if (is_array($userGroupId)) {
            $userGroupId = $userGroupId['value'];
        }
        if ($userGroupId > 0) {
            $model->setUserGroupId($userGroupId);
        }
        $model->setFirstname(isset($data['firstname']) ? $data['firstname'] : $model->getFirstname());
        $model->setLastname(isset($data['lastname']) ? $data['lastname'] : $model->getLastname());
        $model->setTitle(isset($data['title']) ? $data['title'] : $model->getTitle());
        $model->setBirthdate(isset($data['birth']) ? $data['birth'] : $model->getBirthdate());

        $company_id = isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId());
        $model->setCompanyId($company_id);

        $model->setPhone(isset($data['phone']) ? $data['phone'] : $model->getPhone());
        $model->setEmail(isset($data['email']) ? $data['email'] : $model->getEmail());
        $model->setIsActive(isset($data['is_active']) ? $data['is_active'] : $model->getIsActive());
        $model->setAddress(isset($data['address']) ? $data['address'] : $model->getAddress());
        $model->setStreet(isset($data['street']) ? $data['street'] : $model->getStreet());
        $model->setTown(isset($data['town']) ? $data['town'] : $model->getTown());
        $model->setZipcode(isset($data['zip_code']) ? $data['zip_code'] : $model->getZipcode());

        $country = isset($data['country_id']) ? $data['country_id'] : $model->getCountryId();
        if (is_array($country)) {
            $country = $country['value'];
        }
        $model->setCountryId($country);
        $type = isset($data['type_id']) ? $data['type_id'] : (isset($custom['type_id']) ? $custom['type_id'] : $model->getUserTypeId());
        if (is_array($type))
            $type = $type['value'];
        $model->setUserTypeId($type);
        $model->setStatus(isset($data['status']) ? (int)$data['status'] : $model->getStatus());

        try {
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_USER_FAIL_TEXT',
                    'detail' => $msg,
                    'raw' => $data
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_USER_FAIL_TEXT',
                'detail' => $e->getMessage(),
                'raw' => $data
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_USER_FAIL_TEXT',
                'detail' => $e->getMessage(),
                'raw' => $data
            ];
            return $result;
        }
    }

    /**
     * @param array $custom
     * @return array|UserExt
     */
    public function saveUser($custom = [])
    {
        $model = $this;

        $model->setData($custom);

        if ($model->getId() > 0) {
            $result = $model->__quickUpdate();
        } else {
            $result = $model->__quickCreate();
        }

        if ($result['success'] == false) {
            return $result;
        } else {
            return $model;
        }
    }

    /**
     * get  Avatar URL
     * @return string
     */
    public function getAvatarUrl()
    {
        return ApplicationModel::__getApiHostname() . "/media/direct/getAvatarThumb/" . $this->getUuid();
    }

    /**
     * @param $uuid
     * @return \SMXD\Application\Models\Employee
     */

    public static function findFirstByUuidCache($uuid)
    {
        return self::findFirst([
            'conditions' => 'uuid = :uuid:',
            'bind' => [
                'uuid' => $uuid,
            ],
            'cache' => [
                'key' => self::__getCacheNameUser($uuid),
                'lifetime' => SMXDCachePrefixHelper::CACHE_TIME_DAILY,
            ],
        ]);
    }

    /**
     * @param $uuid
     * @return \SMXD\Application\Models\Employee
     */
    /**
     * @param $id
     * @return User
     */
    public static function findFirstByIdCache($id, $lifeTime = SMXDCachePrefixHelper::CACHE_TIME_DAILY)
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $id,
            ],
            'cache' => [
                'key' => self::__getCacheNameUser($id),
                'lifetime' => $lifeTime,
            ],
        ]);
    }


    /**
     * @param $email
     * @return User
     */
    public static function findFirstByEmailCache($email)
    {
        return self::findFirst([
            'conditions' => 'email = :email:',
            'bind' => [
                'email' => $email,
            ],
            'cache' => [
                'key' => self::__getCacheNameUser($email),
                'lifetime' => SMXDCachePrefixHelper::CACHE_TIME_DAILY,
            ],
        ]);
    }


    /**
     * @param $parameters
     * @return string
     */
    protected static function _createKey($parameters)
    {
        $uniqueKey = [];

        foreach ($parameters as $key => $value) {
            if (is_scalar($value)) {
                $uniqueKey[] = $key . ':' . $value;
            } elseif (is_array($value)) {
                $uniqueKey[] = $key . ':[' . self::_createKey($value) . ']';
            }
        }

        return join(',', $uniqueKey);
    }

    /**
     * get cache Name Item
     */
    public function getCacheNameUser()
    {
        return "USER_" . $this->getUuid();
    }

    /**
     * @param $uuid
     * @return string
     */
    public static function __getCacheNameUser($uuid)
    {
        return "USER_" . $uuid;
    }

    /**
     * @return string
     */
    public function getFullname()
    {
        return $this->getFirstname() . " " . $this->getLastname();
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {
        ModelHelper::__setDataCustomOnly($this, $custom);
        /****** YOUR CODE ***/

        $userGroupId = Helpers::getIntPositive($custom, 'user_group_id') ? Helpers::getIntPositive($custom, 'user_group_id') : $this->getUserGroupId();
        if (is_numeric($userGroupId)) {
            $this->setUserGroupId($userGroupId);
        }
        $this->setBirthdate(isset($custom['birthdate']) ? $custom['birthdate'] : $this->getBirthdate());
        $company_id = isset($custom['company_id']) ? $custom['company_id'] : $this->getCompanyId();
        if (is_numeric($company_id)) {
            $this->setCompanyId($company_id);
        }
        $countryId = isset($custom['country_id']) ? $custom['country_id'] : $this->getCountryId();
        if (is_numeric($countryId)) {
            $this->setCountryId($countryId);
        }

        /*** default active ***/
        $active = isset($custom['is_active']) ? $custom['is_active'] : $this->getIsActive();
        if ((is_numeric($active) && $active !== '' && !is_null($active) && !empty($active))) {
            $this->setIsActive($active);
        } else {
            $this->setIsActive(self::INACTIVATED);
        }

        /**** default status **/
        $status = isset($custom['status']) ? $custom['status'] : $this->getStatus();
        if ($status == null || $status == '') {
            $status = self::STATUS_ACTIVE;
        }
        $this->setStatus($status);
        /** fix gender */
        if (array_key_exists('gender', $custom)) {
            if (is_numeric($custom['gender'])) {
                $this->setGender($custom['gender']);
            } elseif ($custom['gender'] == null || $custom['gender'] == '') {
                $this->setGender(null);
            }
        }
        /****** END YOUR CODE **/
    }

    /**
     * @param $email
     * @return bool
     */
    public static function __ifEmailAvailable($email)
    {
        $profile = self::findFirst([
            "conditions" => "email = :email: and status <> :deleted:",
            "bind" => [
                "email" => $email,
                "deleted" => self::STATUS_DELETED
            ]
        ]);
        if ($profile) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * @return string
     */
    public function getCompanyName()
    {
        return $this->getCompany() ? $this->getCompany()->getName() : '';
    }

    /**
     * @return bool|float|int|null
     */
    public function getFieldsDataStructure()
    {
        return ModelHelper::__getFieldsDataStructure($this);
    }

    /**
     * @return mixed
     */
    public function toArray($columns = NULL)
    {
        $array = parent::toArray($columns);
        $array['name'] = $array['firstname'] . ' ' . $array['lastname'];
        $metadata = $this->getDI()->get('modelsMetadata');
        $types = $metadata->getDataTypes($this);
        foreach ($types as $attribute => $type) {
            $array[$attribute] = ModelHelper::__getAttributeValue($type, $array[$attribute]);
        }
        return $array;
    }

    /**
     * @return string
     */
    public function getRoleName()
    {
        return $this->getUserGroup() ? $this->getUserGroup()->getLabel() : '';
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->getIsActive() == self::ACTIVATED;
    }

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->getStatus() == self::STATUS_DELETED;
    }

    /**
     * @return mixed
     */
    public function getCompanyByCache()
    {
        try {
            return $this->getCompany([
                'cache' => [
                    'key' => 'CACHE_COMPANY_' . $this->getCompanyId(),
                    'lifetime' => CacheHelper::__TIME_5_MINUTES
                ]
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all users belongs to group admin
     * @param int $companyId
     * @return mixed
     */
    public static function getAdmins($companyId)
    {
        $company = CompanyExt::findFirstById($companyId);

        $userGroupId = null;
        if ($company->isGms()) {
            $userGroupId = StaffUserGroupExt::GROUP_GMS_ADMIN;
        } else if ($company->isHr()) {
            $userGroupId = StaffUserGroupExt::GROUP_HR_ADMIN;
        }

        $users = [];

        if ($userGroupId) {
            $users = self::find([
                'conditions' => 'company_id = :company_id: AND user_group_id = :user_group_id: AND status = :status_active:',
                'bind' => [
                    'company_id' => $companyId,
                    'user_group_id' => $userGroupId,
                    'status_active' => self::STATUS_ACTIVE
                ]
            ]);
        }

        return $users;
    }

    /**
     * @return string
     */
    public function getUserGroupLabel($language = SupportedLanguageExt::LANG_EN)
    {
        if ($this->getUserGroupId() != '') {
            return ConstantHelper::__translate($this->getUserGroup()->getLabel(), $language);
        }
    }


    /**
     * @param string $language
     * @return string
     */
    public function getGenderLabel($language = SupportedLanguageExt::LANG_EN)
    {
        if (Helpers::__isNull($this->getGender())) return '';
        if ($this->getGender() == 1) return ConstantHelper::__translate('MASCULIN_TEXT', $language);
        if ($this->getGender() == -1) return ConstantHelper::__translate('OTHER_TEXT', $language);
        if ($this->getGender() == 0) return ConstantHelper::__translate('FEMININ_TEXT', $language);
        if ($this->getGender() == null || $this->getGender() == '') return '';
    }

    /**
     * Parse Array
     */
    public function parsedDataToArray(){
        $item = $this->toArray();
        $userGroup = $this->getUserGroup();
        $item['hasAwsCognito'] = $this->hasLogin();
        $item['role_name'] = $userGroup ? $userGroup->getLabel() : '';
        return $item;
    }

    /**
     * @return boolean
     */
    public function hasLogin(){
        $resultCognito = $this->isConvertedToUserCognito();
        if ($resultCognito){
            $cognitoLogin = $this->getCognitoLogin();
            if ($cognitoLogin && ($cognitoLogin['userStatus'] == CognitoClient::UNCONFIRMED || $cognitoLogin['userStatus'] == CognitoClient::FORCE_CHANGE_PASSWORD)){
                return false;
            }else{
                return true;
            }
        }
    }

    /**
     * Set login status for user
     */
    public function setInactiveStatus(){
        $this->setLoginStatus(self::LOGIN_STATUS_INACTIVE);
    }
    public function setLoginMissingStatus(){
        $this->setLoginStatus(self::LOGIN_STATUS_LOGIN_MISSING);
    }
    public function setPendingStatus(){
        $this->setLoginStatus(self::LOGIN_STATUS_PENDING);
    }
    public function setHasAccessStatus(){
        $this->setLoginStatus(self::LOGIN_STATUS_HAS_ACCESS);
    }

    /**
     * @param null $columns
     * @return mixed
     */
    public function toArrayInItem($columns = NULL, $language = 'en')
    {
        $excludeToDisplay = [
            'id',
            'company_id',
            'user_group_id',
        ];

        $result = parent::toArray();
        if (is_array($excludeToDisplay)) {
            foreach ($excludeToDisplay as $x) {
                unset($result[$x]);
            }
        }
        $result['user_group'] = $this->getUserGroupLabel();
        $result['is_active'] = $this->isActive();
        $result['company_name'] = $this->getCompanyName();
        return $result;
    }


    /**
     * @return mixed
     */
    public function getParsedArray()
    {
        $array = $this->toArray();
        // $array['avatar'] = $this->getAvatar();
        $array['isAdmin'] = $this->isAdmin();
        return $array;
    }


    /**
     * @return array|null
     */
    public function getAvatar()
    {
//        $avatar = MediaAttachment::__getLastAttachment($this->getUuid(), "avatar");
        $avatar = ObjectAvatar::__getAvatar($this->getUuid());
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }

    /**
     * @param $name
     * @return null
     */
    public function getUserSettingValue($name)
    {
        if ($name != '') {
            $config = $this->getUserSetting([
                'conditions' => 'name = :name:',
                'bind' => [
                    'name' => $name
                ]
            ]);
            if ($config->count() > 0) {
                return $config->getFirst()->getValue();
            }
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isConvertedToUserCognito()
    {
        if ($this->getAwsCognitoUuid() != '') {
            $result = ApplicationModel::__getUserCognitoByUsername($this->getAwsCognitoUuid());
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
            'password' => $password, 'phone_number' => $this->getPhone()
        ], $this);


        $result["checkEmail"] = true;
        return $result;
    }


    /**
     * @return array
     */
    public function createCognitoLogin($password = '', $user = null)
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
            'loginUrl' => $this->getLoginUrl()
        ];

        $result = ApplicationModel::__addNewUserCognito([
            'email' => $this->getEmail(),
            'password' => $password,
            'loginUrl' => $this->getLoginUrl()
        ], $this, false);


        $result["checkEmail"] = true;
        return $result;
    }

    /**
     * @return array
     */
    public function adminForceUpdateEmail()
    {
        $result = ApplicationModel::__adminForceUpdateUserAttributes($this->getAwsCognitoUuid(), 'email', $this->getEmail());
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
     * @param array $custom
     */
    public function createNewUserLogin($data = [])
    {
        $password = Helpers::__getCustomValue('password', $data);
        $userGroupId = Helpers::__getCustomValue('user_group_id', $data);
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
            $model->setAwsCognitoUuid($awsUuid);
        }

        return $model->__quickUpdate();
    }

    /**
     * Updated email if user deactivated
     */
    public function clearUserLoginWhenUserDeactivated(){
        $resultCognito = $this->isConvertedToUserCognito();
        if ($resultCognito){
            $cognitoLogin = $this->getCognitoLogin();
            if ($this->getAwsCognitoUuid()){
                $resultDeleteCognito = ApplicationModel::__adminDeleteUser($this->getAwsCognitoUuid());
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
            if ($this->getAwsCognitoUuid()){
                $resultDeleteCognito = ApplicationModel::__adminDeleteUser($this->getAwsCognitoUuid());
            }else{
                $resultDeleteCognito = ApplicationModel::__adminDeleteUser($this->getEmail());
            }

            return $resultDeleteCognito;
        }

        return ['success' => true];
    }

    /**
     *
     */
    public function __loadListPermission()
    {
        $user = $this;
        $cacheManager = \Phalcon\DI\FactoryDefault::getDefault()->getShared('cache');
        if ($user->isEndUser()) {
            $company = $user->getCompany();
            if($company && $company->getStatus() == Company::STATUS_VERIFIED) {
                $cacheName = CacheHelper::getWebAclCacheByLvl(self::LVL_3);
            } else {
                $cacheName = CacheHelper::getWebAclCacheByLvl($user->getLvl());
            }
           
        } else {
            $cacheName = CacheHelper::getAclCacheByGroupName($user->getUserGroupId());
        }
        
//        $permissions = $cacheManager->get($cacheName, getenv('CACHE_TIME'));
        $permissions = [];
        $acl_list = [];
        //1. load from JWT

        if (!is_null($permissions) && is_array($permissions) && count($permissions) > 0) {
            return ($permissions);
        } else {
            $menus = array();
        }
        if ($user->isEndUser()) {
            $company = $user->getCompany();
            if($company && $company->getStatus() == Company::STATUS_VERIFIED) {
                $acl_list = WebAclExt::__findWebAcls();
            } else {
                $lvl_acl = EndUserLvlWebAclExt::getAllPrivilegiesLvl($user->getLvl());
                $acl_ids = [];
                if (count($lvl_acl)) {
                    foreach ($lvl_acl as $item) {
                        $acl_ids[] = $item->getAclId();
                    }
                }
                if (count($acl_ids) > 0) {
                    // Get controller and action in list ACLs, order by level
                    $acl_list = WebAclExt::find([
                        'conditions' => 'id IN ({acl_ids:array}) AND status = :status_active: ',
                        'bind' => [
                            'acl_ids' => $acl_ids,
                            'status_active' => WebAclExt::STATUS_ACTIVATED,
                        ],
                        'order' => 'pos, lvl ASC'
                    ]);
                }
            }
        } else if (!$user->isAdmin() & !$user->isCrmAdmin()) {
            $groups_acl = StaffUserGroupAclExt::getAllPrivilegiesGroup($user->getUserGroupId());
            $acl_ids = [];
            if (count($groups_acl)) {
                foreach ($groups_acl as $item) {
                    $acl_ids[] = $item->getAclId();
                }
            }
            if (count($acl_ids) > 0) {
                // Get controller and action in list ACLs, order by level
                $acl_list = AclExt::find([
                    'conditions' => 'id IN ({acl_ids:array}) AND status = :status_active: ',
                    'bind' => [
                        'acl_ids' => $acl_ids,
                        'status_active' => AclExt::STATUS_ACTIVATED,
                    ],
                    'order' => 'pos, lvl ASC'
                ]);
            }
        } else if($user->isCrmAdmin()){
            $acl_list = AclExt::__findCrmAcls();
        }  else {
            $acl_list = AclExt::__findAdminAcls();
        }
        return ($acl_list);
    }
}
