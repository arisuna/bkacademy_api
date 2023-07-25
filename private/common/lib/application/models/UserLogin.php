<?php

namespace SMXD\Application\Models;

use Phalcon\Validation\Validator\Email as EmailValidator;

class UserLogin extends \Phalcon\Mvc\Model
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
    protected $email;

    /**
     *
     * @var string
     */
    protected $password;

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
    protected $lastconnect_at;

    /**
     *
     * @var string
     */
    protected $firstconnect_at;

    /**
     *
     * @var string
     */
    protected $activation;

    /**
     *
     * @var integer
     */
    protected $status;

    /**
     *
     * @var string
     */
    protected $aws_uuid;

    /**
     *
     * @var string
     */
    protected $email_deleted;

    /**
     *
     * @var string
     */
    protected $aws_uuid_copy;

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
     * Method to set the value of field lastconnect_at
     *
     * @param string $lastconnect_at
     * @return $this
     */
    public function setLastconnectAt($lastconnect_at)
    {
        $this->lastconnect_at = $lastconnect_at;

        return $this;
    }

    /**
     * Method to set the value of field firstconnect_at
     *
     * @param string $firstconnect_at
     * @return $this
     */
    public function setFirstconnectAt($firstconnect_at)
    {
        $this->firstconnect_at = $firstconnect_at;

        return $this;
    }

    /**
     * Method to set the value of field activation
     *
     * @param string $activation
     * @return $this
     */
    public function setActivation($activation)
    {
        $this->activation = $activation;

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
     * Method to set the value of field aws_uuid
     *
     * @param string $aws_uuid
     * @return $this
     */
    public function setAwsUuid($aws_uuid)
    {
        $this->aws_uuid = $aws_uuid;

        return $this;
    }

    /**
     * Method to set the value of field email_deleted
     *
     * @param string $email_deleted
     * @return $this
     */
    public function setEmailDeleted($email_deleted)
    {
        $this->email_deleted = $email_deleted;

        return $this;
    }

    /**
     * Method to set the value of field aws_uuid_copy
     *
     * @param string $aws_uuid_copy
     * @return $this
     */
    public function setAwsUuidCopy($aws_uuid_copy)
    {
        $this->aws_uuid_copy = $aws_uuid_copy;

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
     * Returns the value of field email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Returns the value of field password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
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
     * Returns the value of field lastconnect_at
     *
     * @return string
     */
    public function getLastconnectAt()
    {
        return $this->lastconnect_at;
    }

    /**
     * Returns the value of field firstconnect_at
     *
     * @return string
     */
    public function getFirstconnectAt()
    {
        return $this->firstconnect_at;
    }

    /**
     * Returns the value of field activation
     *
     * @return string
     */
    public function getActivation()
    {
        return $this->activation;
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
     * Returns the value of field aws_uuid
     *
     * @return string
     */
    public function getAwsUuid()
    {
        return $this->aws_uuid;
    }

    /**
     * Returns the value of field email_deleted
     *
     * @return string
     */
    public function getEmailDeleted()
    {
        return $this->email_deleted;
    }

    /**
     * Returns the value of field aws_uuid_copy
     *
     * @return string
     */
    public function getAwsUuidCopy()
    {
        return $this->aws_uuid_copy;
    }

    /**
     * Validations and business logic
     *
     * @return boolean
     */
    public function validation()
    {
        $validator = new \Phalcon\Validation();

        $validator->add(
            'email',
            new EmailValidator(
                [
                    'model'   => $this,
                    'message' => 'Please enter a correct email address',
                ]
            )
        );

        return $this->validate($validator);
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {

    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserLogin[]
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return UserLogin
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'user_login';
    }

}
