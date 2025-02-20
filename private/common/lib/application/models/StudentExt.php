<?php
/**
 * Created by PhpStorm.
 * Student: binhnt
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

class StudentExt extends Student
{
    use ModelTraits;
    const STATUS_DELETED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const ACTIVATED = 1;
    const INACTIVATED = 0;

    const PENDING = 1;
    const NO_ACTION = 0;
    const APPROVED = 2;
    const REJECTED = -1;

    const LVL_3 = 3;
    const LVL_2 = 2;
    const LVL_1 = 1;
    const LVL_0 = 0;

    const LOGIN_STATUS_INACTIVE = 1;
    const LOGIN_STATUS_LOGIN_MISSING = 2;
    const LOGIN_STATUS_PENDING = 3;
    const LOGIN_STATUS_HAS_ACCESS = 4;

    const UNVERIFIED_STATUS = 0;
    const PENDING_VERIFIED_STATUS = 1;
    const VERIFIED_STATUS = 2;

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

        $this->hasMany('id', 'SMXD\Application\Models\AddressExt', 'end_user_id', [
            'alias' => 'Addresses',
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

        /** sanitize email */
        $this->setEmail(Helpers::__sanitizeEmail($this->getEmail()));

        return $this->validate($validator);
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
                    'message' => 'STUDENT_NOT_FOUND_TEXT'
                ];
            }
            $data = $req->getPut();
        }

        // Assign data to model
        if ($req->isPost()) {
            $model->setUuid(ApplicationModel::uuid());
        }
        $model->setFirstname(isset($data['firstname']) ? $data['firstname'] : $model->getFirstname());
        $model->setLastname(isset($data['lastname']) ? $data['lastname'] : $model->getLastname());
        $model->setTitle(isset($data['title']) ? $data['title'] : $model->getTitle());
        $model->setBirthdate(isset($data['birth']) ? $data['birth'] : $model->getBirthdate());

        $company_id = isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId());
        $model->setCompanyId($company_id);

        $model->setPhone(isset($data['phone']) ? $data['phone'] : $model->getPhone());
        $model->setEmail(isset($data['email']) ? $data['email'] : $model->getEmail());
        $model->setFacebook(isset($data['facebook']) ? $data['facebook'] : $model->getFacebook());
        $model->setMotherFirstname(isset($data['mother_firstname']) ? $data['mother_firstname'] : $model->getMotherFirstname());
        $model->setMotherLastname(isset($data['mother_lastname']) ? $data['mother_lastname'] : $model->getMotherLastname());
        $model->setMotherEmail(isset($data['mother_email']) ? $data['mother_email'] : $model->getMotherEmail());
        $model->setMotherFacebook(isset($data['mother_facebook']) ? $data['mother_facebook'] : $model->getMotherFacebook());
        $model->setMotherPhone(isset($data['mother_phone']) ? $data['mother_phone'] : $model->getMotherPhone());
        $model->setFatherFirstname(isset($data['father_firstname']) ? $data['father_firstname'] : $model->getFatherFirstname());
        $model->setFatherLastname(isset($data['father_lastname']) ? $data['father_lastname'] : $model->getFatherLastname());
        $model->setFatherEmail(isset($data['father_email']) ? $data['father_email'] : $model->getFatherEmail());
        $model->setFatherFacebook(isset($data['father_facebook']) ? $data['father_facebook'] : $model->getFatherFacebook());
        $model->setFatherPhone(isset($data['father_phone']) ? $data['father_phone'] : $model->getFatherPhone());
        $model->setIsActive(isset($data['is_active']) ? $data['is_active'] : $model->getIsActive());
        $model->setAddress(isset($data['address']) ? $data['address'] : $model->getAddress());
        $model->setStreet(isset($data['street']) ? $data['street'] : $model->getStreet());
        $model->setTown(isset($data['town']) ? $data['town'] : $model->getTown());
        $model->setZipcode(isset($data['zip_code']) ? $data['zip_code'] : $model->getZipcode());
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
                    'message' => 'SAVE_STUDENT_FAIL_TEXT',
                    'detail' => $msg,
                    'raw' => $data
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_STUDENT_FAIL_TEXT',
                'detail' => $e->getMessage(),
                'raw' => $data
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_STUDENT_FAIL_TEXT',
                'detail' => $e->getMessage(),
                'raw' => $data
            ];
            return $result;
        }
    }

    /**
     * @param array $custom
     * @return array|StudentExt
     */
    public function saveStudent($custom = [])
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
        ]);
    }

    /**
     * @param $uuid
     * @return \SMXD\Application\Models\Employee
     */
    /**
     * @param $id
     * @return Student
     */
    public static function findFirstByIdCache($id, $lifeTime = SMXDCachePrefixHelper::CACHE_TIME_DAILY)
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $id,
            ],
        ]);
    }


    /**
     * @param $email
     * @return Student
     */
    public static function findFirstByEmailCache($email)
    {
        return self::findFirst([
            'conditions' => 'email = :email:',
            'bind' => [
                'email' => $email,
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
    public function getCacheNameStudent()
    {
        return "STUDENT_" . $this->getUuid();
    }

    /**
     * @param $uuid
     * @return string
     */
    public static function __getCacheNameStudent($uuid)
    {
        return "STUDENT_" . $uuid;
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

        // $userGroupId = Helpers::getIntPositive($custom, 'user_group_id') ? Helpers::getIntPositive($custom, 'user_group_id') : $this->getStudentGroupId();
        // if (is_numeric($userGroupId)) {
        //     $this->setStudentGroupId($userGroupId);
        // }
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
     * @return bool|float|int|null
     */
    public function getFieldsDataStructure()
    {
        return ModelHelper::__getFieldsDataStructure($this);
    }

    /**
     * @return mixed
     */
    public function toArray($columns = NULL): array
    {
        $array = parent::toArray($columns);
        $array['name'] = $array['lastname'] . ' ' . $array['firstname'];
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
        return $this->getStudentGroup() ? $this->getStudentGroup()->getLabel() : '';
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
     * @return string
     */
    public function getStudentGroupLabel($language = SupportedLanguageExt::LANG_EN)
    {
        if ($this->getStudentGroupId() != '') {
            return ConstantHelper::__translate($this->getStudentGroup()->getLabel(), $language);
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
        $userGroup = $this->getStudentGroup();
        $item['hasAwsCognito'] = $this->hasLogin();
        $item['role_name'] = $userGroup ? $userGroup->getLabel() : '';
        return $item;
    }

    /**
     * @return boolean
     */
    public function hasLogin(){
        $resultCognito = $this->isConvertedToStudentCognito();
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
        ];

        $result = parent::toArray();
        if (is_array($excludeToDisplay)) {
            foreach ($excludeToDisplay as $x) {
                unset($result[$x]);
            }
        }
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
        $array['company_status'] = $this->getCompany() ? intval($this->getCompany()->getStatus()) : null;
        $array['default_address'] = $this->getDefaultAddress();

        return $array;
    }

    /**
     * @return \Phalcon\Mvc\Model\ResultInterface|Address|null
     */
    public function getDefaultAddress(){
        $defaultAddress = AddressExt::findFirst([
            'conditions' => 'is_default = 1 and end_user_id = :end_user_id:',
            'bind' => [
                'end_user_id' => $this->getId()
            ]
        ]);

        if ($defaultAddress){
            return $defaultAddress;
        }

        $defaultAddress2 = AddressExt::findFirst([
            'conditions' => 'end_user_id = :end_user_id:',
            'bind' => [
                'end_user_id' => $this->getId()
            ],
            'orders' => 'created_at ASC'
        ]);

        if ($defaultAddress2){
            return $defaultAddress2;
        }

        return null;
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
}
