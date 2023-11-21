<?php

namespace SMXD\Application\Models;
class BankAccount extends \Phalcon\Mvc\Model
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
     * @var string
     */
    protected $object_uuid;

    /**
     *
     * @var integer
     */
    protected $object_type;

    /**
     *
     * @var string
     */
    protected $bank_id;

    /**
     *
     * @var string
     */
    protected $account_name;

    /**
     *
     * @var string
     */
    protected $bank_name;

    /**
     *
     * @var string
     */
    protected $bank_code;

    /**
     *
     * @var string
     */
    protected $currency;

    /**
     *
     * @var string
     */
    protected $iban;

    /**
     *
     * @var string
     */
    protected $bin;

    /**
     *
     * @var string
     */
    protected $swift_code;

    /**
     *
     * @var integer
     */
    protected $is_verified;

    /**
     *
     * @var integer
     */
    protected $is_deleted;

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
    protected $account_number;

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
     * Method to set the value of field object_uuid
     *
     * @param string $object_uuid
     * @return $this
     */
    public function setObjectUuid($object_uuid)
    {
        $this->object_uuid = $object_uuid;

        return $this;
    }

    /**
     * Method to set the value of field object_type
     *
     * @param integer $object_type
     * @return $this
     */
    public function setObjectType($object_type)
    {
        $this->object_type = $object_type;

        return $this;
    }

    /**
     * Method to set the value of field bank_id
     *
     * @param string $bank_id
     * @return $this
     */
    public function setBankId($bank_id)
    {
        $this->bank_id = $bank_id;

        return $this;
    }

    /**
     * Method to set the value of field account_name
     *
     * @param string $account_name
     * @return $this
     */
    public function setAccountName($account_name)
    {
        $this->account_name = $account_name;

        return $this;
    }

    /**
     * Method to set the value of field bank_name
     *
     * @param string $bank_name
     * @return $this
     */
    public function setBankName($bank_name)
    {
        $this->bank_name = $bank_name;

        return $this;
    }

    /**
     * Method to set the value of field bank_code
     *
     * @param string $bank_code
     * @return $this
     */
    public function setBankCode($bank_code)
    {
        $this->bank_code = $bank_code;

        return $this;
    }

    /**
     * Method to set the value of field currency
     *
     * @param string $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Method to set the value of field iban
     *
     * @param string $iban
     * @return $this
     */
    public function setIban($iban)
    {
        $this->iban = $iban;

        return $this;
    }

    /**
     * Method to set the value of field bin
     *
     * @param string $bin
     * @return $this
     */
    public function setBin($bin)
    {
        $this->bin = $bin;

        return $this;
    }

    /**
     * Method to set the value of field swift_code
     *
     * @param string $swift_code
     * @return $this
     */
    public function setSwiftCode($swift_code)
    {
        $this->swift_code = $swift_code;

        return $this;
    }

    /**
     * Method to set the value of field is_verified
     *
     * @param integer $is_verified
     * @return $this
     */
    public function setIsVerified($is_verified)
    {
        $this->is_verified = $is_verified;

        return $this;
    }

    /**
     * Method to set the value of field is_deleted
     *
     * @param integer $is_deleted
     * @return $this
     */
    public function setIsDeleted($is_deleted)
    {
        $this->is_deleted = $is_deleted;

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
     * Method to set the value of field account_number
     *
     * @param string $account_number
     * @return $this
     */
    public function setAccountNumber($account_number)
    {
        $this->account_number = $account_number;

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
     * Returns the value of field object_uuid
     *
     * @return string
     */
    public function getObjectUuid()
    {
        return $this->object_uuid;
    }

    /**
     * Returns the value of field object_type
     *
     * @return integer
     */
    public function getObjectType()
    {
        return $this->object_type;
    }

    /**
     * Returns the value of field bank_id
     *
     * @return string
     */
    public function getBankId()
    {
        return $this->bank_id;
    }

    /**
     * Returns the value of field account_name
     *
     * @return string
     */
    public function getAccountName()
    {
        return $this->account_name;
    }

    /**
     * Returns the value of field bank_name
     *
     * @return string
     */
    public function getBankName()
    {
        return $this->bank_name;
    }

    /**
     * Returns the value of field bank_code
     *
     * @return string
     */
    public function getBankCode()
    {
        return $this->bank_code;
    }

    /**
     * Returns the value of field currency
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Returns the value of field iban
     *
     * @return string
     */
    public function getIban()
    {
        return $this->iban;
    }

    /**
     * Returns the value of field bin
     *
     * @return string
     */
    public function getBin()
    {
        return $this->bin;
    }

    /**
     * Returns the value of field swift_code
     *
     * @return string
     */
    public function getSwiftCode()
    {
        return $this->swift_code;
    }

    /**
     * Returns the value of field is_verified
     *
     * @return integer
     */
    public function getIsVerified()
    {
        return $this->is_verified;
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
     * Returns the value of field account_number
     *
     * @return string
     */
    public function getAccountNumber()
    {
        return $this->account_number;
    }

    /**
     * Initialize method for model.
     */
    public function initialize()
    {
//        $this->setSchema("sanmayxaydung");
//        $this->setSource("bank_account");
    }

    /**
     * Returns table name mapped in the model.
     *
     * @return string
     */
    public function getSource()
    {
        return 'bank_account';
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return BankAccount[]|BankAccount|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null)
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return BankAccount|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
