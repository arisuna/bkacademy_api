<?php

namespace SMXD\Application\Models;

class Student extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    protected $id;

    /**
     *
     * @var string
     */
    protected $uuid;

    /**
     *
     * @var integer
     */
    protected $user_group_id;
    /**
     *
     * @var string
     */
    protected $title;

    /**
     *
     * @var integer
     */
    protected $gender;

    /**
     *
     * @var string
     */
    protected $firstname;

    /**
     *
     * @var string
     */
    protected $lastname;

    /**
     *
     * @var integer
     */
    protected $company_id;

    /**
     *
     * @var string
     */
    protected $phone;

    /**
     *
     * @var string
     */
    protected $email;
    /**
     *
     * @var integer
     */
    protected $is_active;

    /**
     *
     * @var string
     */
    protected $birthdate;

    /**
     *
     * @var string
     */
    protected $address;

    /**
     *
     * @var string
     */
    protected $street;

    /**
     *
     * @var string
     */
    protected $town;

    /**
     *
     * @var string
     */
    protected $zipcode;

    /**
     *
     * @var integer
     */
    protected $country_id;
    /**
     *
     * @var integer
     */
    protected $status;
    /**
     *
     * @var integer
     */
    protected $login_status;

    /**
     *
     * @var string
     */
    protected $created_at;

    /**
     *
     * @var string
     */
    protected $updated_at;

    /**
     *
     * @var string
     */
    protected $deleted_at;

    /**
     *
     * @var string
     */
    protected $aws_cognito_uuid;

    /**
     *
     * @var integer
     */
    protected $is_end_user;

    /**
     *
     * @var integer
     */
    protected $is_staff_user;

    /**
     *
     * @var integer
     */
    protected $is_master_admin_user;

    /**
     *
     * @var integer
     */
    protected $is_deleted;
    /**
     *
     * @var integer
     */
    protected $lvl;
    /**
     *
     * @var integer
     */
    protected $verification_status;
    /**
     *
     * @var string
     */
    protected $id_number;
    /**
     *
     * @var string
     */
    protected $password;
    /**
     *
     * @var string
     */
    protected $access_token;
    /**
     *
     * @var string
     */
    protected $refresh_token;
    /**
     *
     * @var int
     */
    protected $access_token_expired_at;
    /**
     *
     * @var string
     */
    protected $facebook;
    /**
     *
     * @var string
     */
    protected $mother_firstname;
    /**
     *
     * @var string
     */
    protected $mother_lastname;
    /**
     *
     * @var string
     */
    protected $mother_phone;
    /**
     *
     * @var string
     */
    protected $mother_email;
    /**
     *
     * @var string
     */
    protected $mother_facebook;
    /**
     *
     * @var string
     */
    protected $father_firstname;
    /**
     *
     * @var string
     */
    protected $father_lastname;
    /**
     *
     * @var string
     */
    protected $father_phone;
    /**
     *
     * @var string
     */
    protected $father_email;
    /**
     *
     * @var string
     */
    protected $father_facebook;
    /**
     *
     * @var integer
     */
    protected $birth_year;

    /**
     * Method to set the value of field id
     *
     * @param integer $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Method to set the value of field uuid
     *
     * @param string $uuid
     * @return $this
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * Method to set the value of field user_group_id
     *
     * @param integer $user_group_id
     * @return $this
     */
    public function setUserGroupId($user_group_id)
    {
        $this->user_group_id = $user_group_id;

        return $this;
    }

    /**
     * Method to set the value of field title
     *
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Method to set the value of field gender
     *
     * @param integer $gender
     * @return $this
     */
    public function setGender($gender)
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * Method to set the value of field firstname
     *
     * @param string $firstname
     * @return $this
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Method to set the value of field lastname
     *
     * @param string $lastname
     * @return $this
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Method to set the value of field company_id
     *
     * @param integer $company_id
     * @return $this
     */
    public function setCompanyId($company_id)
    {
        $this->company_id = $company_id;

        return $this;
    }

    /**
     * Method to set the value of field phone
     *
     * @param string $phone
     * @return $this
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Method to set the value of field email
     *
     * @param string $email
     * @return $this
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Method to set the value of field is_active
     *
     * @param integer $is_active
     * @return $this
     */
    public function setIsActive($is_active)
    {
        $this->is_active = $is_active;

        return $this;
    }

    /**
     * Method to set the value of field birthdate
     *
     * @param string $birthdate
     * @return $this
     */
    public function setBirthdate($birthdate)
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    /**
     * Method to set the value of field address
     *
     * @param string $address
     * @return $this
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Method to set the value of field street
     *
     * @param string $street
     * @return $this
     */
    public function setStreet($street)
    {
        $this->street = $street;

        return $this;
    }

    /**
     * Method to set the value of field town
     *
     * @param string $town
     * @return $this
     */
    public function setTown($town)
    {
        $this->town = $town;

        return $this;
    }

    /**
     * Method to set the value of field zipcode
     *
     * @param string $zipcode
     * @return $this
     */
    public function setZipcode($zipcode)
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    /**
     * Method to set the value of field country_id
     *
     * @param integer $country_id
     * @return $this
     */
    public function setCountryId($country_id)
    {
        $this->country_id = $country_id;

        return $this;
    }

    /**
     * Method to set the value of field status
     *
     * @param integer $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Method to set the value of field login_status
     *
     * @param integer $login_status
     * @return $this
     */
    public function setLoginStatus($login_status)
    {
        $this->login_status = $login_status;

        return $this;
    }

    /**
     * Method to set the value of field created_at
     *
     * @param string $created_at
     * @return $this
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * Method to set the value of field updated_at
     *
     * @param string $updated_at
     * @return $this
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    /**
     * Method to set the value of field deleted_at
     *
     * @param string $deleted_at
     * @return $this
     */
    public function setDeletedAt($deleted_at)
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    /**
     * Method to set the value of field aws_cognito_uuid
     *
     * @param string $aws_cognito_uuid
     * @return $this
     */
    public function setAwsCognitoUuid($aws_cognito_uuid)
    {
        $this->aws_cognito_uuid = $aws_cognito_uuid;

        return $this;
    }

    /**
     * Method to set the value of field is_end_user
     *
     * @param string $is_end_user
     * @return $this
     */
    public function setIsEndUser($is_end_user)
    {
        $this->is_end_user = $is_end_user;

        return $this;
    }

    /**
     * Method to set the value of field is_staff_user
     *
     * @param string $is_staff_user
     * @return $this
     */
    public function setIsStaffUser($is_staff_user)
    {
        $this->is_staff_user = $is_staff_user;

        return $this;
    }

    /**
     * Method to set the value of field is_master_admin_user
     *
     * @param string $is_master_admin_user
     * @return $this
     */

    public function setIsMasterAdminUser($is_master_admin_user)
    {
        $this->is_master_admin_user = $is_master_admin_user;

        return $this;
    }

    /**
     * Method to set the value of field is_deleted
     *
     * @param string $is_deleted
     * @return $this
     */
    public function setIsDeleted($is_deleted)
    {
        $this->is_deleted = $is_deleted;

        return $this;
    }

    /**
     * Method to set the value of field lvl
     *
     * @param string $lvl
     * @return $this
     */
    public function setLvl($lvl)
    {
        $this->lvl = $lvl;

        return $this;
    }

    /**
     * Method to set the value of field verification_status
     *
     * @param string $verification_status
     * @return $this
     */
    public function setVerificationStatus($verification_status)
    {
        $this->verification_status = $verification_status;

        return $this;
    }

    /**
     * Method to set the value of field id_number
     *
     * @param string $id_number
     * @return $this
     */
    public function setIdNumber($id_number)
    {
        $this->id_number = $id_number;

        return $this;
    }

    /**
     * Method to set the value of field password
     *
     * @param string $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Method to set the value of field access_token
     *
     * @param string $access_token
     * @return $this
     */
    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;

        return $this;
    }

    /**
     * Method to set the value of field refresh_token
     *
     * @param string $refresh_token
     * @return $this
     */
    public function setRefreshToken($refresh_token)
    {
        $this->refresh_token = $refresh_token;

        return $this;
    }

    /**
     * Method to set the value of field access_token_expired_at
     *
     * @param string $access_token_expired_at
     * @return $this
     */
    public function setAccessTokenExpiredAt($access_token_expired_at)
    {
        $this->access_token_expired_at = $access_token_expired_at;

        return $this;
    }

    /**
     * Method to set the value of field facebook
     *
     * @param string $facebook
     * @return $this
     */
    public function setFacebook($facebook)
    {
        $this->facebook = $facebook;

        return $this;
    }

    /**
     * Method to set the value of field mother_firstname
     *
     * @param string $mother_firstname
     * @return $this
     */
    public function setMotherFirstname($mother_firstname)
    {
        $this->mother_firstname = $mother_firstname;

        return $this;
    }

    /**
     * Method to set the value of field mother_lastname
     *
     * @param string $mother_lastname
     * @return $this
     */
    public function setMotherLastname($mother_lastname)
    {
        $this->mother_lastname = $mother_lastname;

        return $this;
    }

    /**
     * Method to set the value of field mother_phone
     *
     * @param string $mother_phone
     * @return $this
     */
    public function setMotherPhone($mother_phone)
    {
        $this->mother_phone = $mother_phone;

        return $this;
    }

    /**
     * Method to set the value of field mother_email
     *
     * @param string $mother_email
     * @return $this
     */
    public function setMotherEmail($mother_email)
    {
        $this->mother_email = $mother_email;

        return $this;
    }

    /**
     * Method to set the value of field mother_facebook
     *
     * @param string $mother_facebook
     * @return $this
     */
    public function setMotherFacebook($mother_facebook)
    {
        $this->mother_facebook = $mother_facebook;

        return $this;
    }

    /**
     * Method to set the value of field father_firstname
     *
     * @param string $father_firstname
     * @return $this
     */
    public function setFatherFirstname($father_firstname)
    {
        $this->father_firstname = $father_firstname;

        return $this;
    }

    /**
     * Method to set the value of field father_lastname
     *
     * @param string $father_lastname
     * @return $this
     */
    public function setFatherLastname($father_lastname)
    {
        $this->father_lastname = $father_lastname;

        return $this;
    }

    /**
     * Method to set the value of field father_phone
     *
     * @param string $father_phone
     * @return $this
     */
    public function setFatherPhone($father_phone)
    {
        $this->father_phone = $father_phone;

        return $this;
    }

    /**
     * Method to set the value of field father_email
     *
     * @param string $father_email
     * @return $this
     */
    public function setFatherEmail($father_email)
    {
        $this->father_email = $father_email;

        return $this;
    }

    /**
     * Method to set the value of field father_facebook
     *
     * @param string $father_facebook
     * @return $this
     */
    public function setFatherFacebook($father_facebook)
    {
        $this->father_facebook = $father_facebook;

        return $this;
    }

    /**
     * Method to set the value of field birth_year
     *
     * @param string $birth_year
     * @return $this
     */
    public function setBirthYear($birth_year)
    {
        $this->birth_year = $birth_year;

        return $this;
    }

    /**
     * Returns the value of field id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the value of field uuid
     *
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Returns the value of field user_group_id
     *
     * @return integer
     */
    public function getUserGroupId()
    {
        return $this->user_group_id;
    }

    /**
     * Returns the value of field title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Returns the value of field gender
     *
     * @return integer
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * Returns the value of field firstname
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Returns the value of field lastname
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Returns the value of field company_id
     *
     * @return integer
     */
    public function getCompanyId()
    {
        return $this->company_id;
    }

    /**
     * Returns the value of field phone
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Returns the value of field email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Returns the value of field is_active
     *
     * @return integer
     */
    public function getIsActive()
    {
        return $this->is_active;
    }

    /**
     * Returns the value of field birthdate
     *
     * @return string
     */
    public function getBirthdate()
    {
        return $this->birthdate;
    }

    /**
     * Returns the value of field address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Returns the value of field street
     *
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * Returns the value of field town
     *
     * @return string
     */
    public function getTown()
    {
        return $this->town;
    }

    /**
     * Returns the value of field zipcode
     *
     * @return string
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * Returns the value of field country_id
     *
     * @return integer
     */
    public function getCountryId()
    {
        return $this->country_id;
    }

    /**
     * Returns the value of field status
     *
     * @return integer
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Returns the value of field login_status
     *
     * @return integer
     */
    public function getLoginStatus()
    {
        return $this->login_status;
    }

    /**
     * Returns the value of field created_at
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Returns the value of field updated_at
     *
     * @return string
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * Returns the value of field deleted_at
     *
     * @return string
     */
    public function getDeletedAt()
    {
        return $this->deleted_at;
    }

    /**
     * Returns the value of field aws_cognito_uuid
     *
     * @return string
     */
    public function getAwsCognitoUuid()
    {
        return $this->aws_cognito_uuid;
    }

    /**
     * Returns the value of field is_end_user
     *
     * @return integer
     */
    public function getIsEndUser()
    {
        return $this->is_end_user;
    }

    /**
     * Returns the value of field is_staff_user
     *
     * @return integer
     */
    public function getIsStaffUser()
    {
        return $this->is_staff_user;
    }

    /**
     * Returns the value of field is_master_admin_user
     *
     * @return integer
     */
    public function getIsMasterAdminUser()
    {
        return $this->is_master_admin_user;
    }

    /**
     * Returns the value of field is_deleted
     *
     * @return integer
     */
    public function getIsDeleted()
    {
        return $this->is_deleted;
    }

    /**
     * Returns the value of field lvl
     *
     * @return integer
     */
    public function getLvl()
    {
        return $this->lvl;
    }

    /**
     * Returns the value of field verification_status
     *
     * @return integer
     */
    public function getVerificationStatus()
    {
        return $this->verification_status;
    }

    /**
     * Returns the value of field id_number
     *
     * @return integer
     */
    public function getIdNumber()
    {
        return $this->id_number;
    }

    /**
     * Returns the value of field password
     *
     * @return integer
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Returns the value of field access_token
     *
     * @return integer
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }

    /**
     * Returns the value of field refresh_token
     *
     * @return integer
     */
    public function getRefreshToken()
    {
        return $this->refresh_token;
    }

    /**
     * Returns the value of field access_token_expired_at
     *
     * @return integer
     */
    public function getAccessTokenExpiredAt()
    {
        return $this->access_token_expired_at;
    }

    /**
     * Returns the value of field facebook
     *
     * @return string
     */
    public function getFacebook()
    {
        return $this->facebook;
    }

    /**
     * Returns the value of field mother_firstname
     *
     * @return string
     */
    public function getMotherFirstname()
    {
        return $this->mother_firstname;
    }

    /**
     * Returns the value of field mother_lastname
     *
     * @return string
     */
    public function getMotherLastname()
    {
        return $this->mother_lastname;
    }

    /**
     * Returns the value of field mother_phone
     *
     * @return string
     */
    public function getMotherPhone()
    {
        return $this->mother_phone;
    }

    /**
     * Returns the value of field mother_email
     *
     * @return string
     */
    public function getMotherEmail()
    {
        return $this->mother_email;
    }

    /**
     * Returns the value of field mother_facebook
     *
     * @return string
     */
    public function getMotherFacebook()
    {
        return $this->mother_facebook;
    }

    /**
     * Returns the value of field father_firstname
     *
     * @return string
     */
    public function getFatherFirstname()
    {
        return $this->father_firstname;
    }

    /**
     * Returns the value of field father_lastname
     *
     * @return string
     */
    public function getFatherLastname()
    {
        return $this->father_lastname;
    }

    /**
     * Returns the value of field father_phone
     *
     * @return string
     */
    public function getFatherPhone()
    {
        return $this->father_phone;
    }

    /**
     * Returns the value of field father_email
     *
     * @return string
     */
    public function getFatherEmail()
    {
        return $this->father_email;
    }

    /**
     * Returns the value of field father_facebook
     *
     * @return string
     */
    public function getFatherFacebook()
    {
        return $this->father_facebook;
    }

    /**
     * Returns the value of field birth_year
     *
     * @return string
     */
    public function getBirthYear()
    {
        return $this->birth_year;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSource("student");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Student[]|Student|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Student|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null): \Phalcon\Mvc\ModelInterface
    {
        return parent::findFirst($parameters)?: null;
    }

    protected $source = 'student';

}
