<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Security\Random;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Uniqueness as UniquenessValidator;
use Phalcon\Validation\Validator\Email as EmailValidator;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Traits\ModelTraits;

class CompanyExt extends Company
{
    use ModelTraits;

    const STATUS_ACTIVATED = -1;
    const STATUS_ARCHIVED = -1;

    const STATUS_VERIFIED = 1;
    const STATUS_PENDING = 0;
    const STATUS_UNVERIFIED = -1;

    /**
     * [initialize description]
     * @return [type] [description]
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

        $this->addBehavior(new SoftDelete([
            'field' => 'is_deleted',
            'value' => Helpers::YES
        ]));

        $this->belongsTo('country_id', 'SMXD\Application\Models\CountryExt', 'id', [
            'alias' => 'Country',
        ]);
//        $this->belongsTo('head_user_id', 'SMXD\Application\Models\UserExt', 'id', [
//            'alias' => 'HeadUser',
//        ]);

//        $this->hasMany('id', 'SMXD\Application\Models\UserExt', 'company_id', [
//            'alias' => 'Users',
//            'params' => [
//                'order' => 'SMXD\Application\Models\UserExt.created_at ASC'
//            ]
//        ]);

        // $this->hasMany('id', 'SMXD\Application\Models\UserExt', 'company_id', [
        //     'alias' => 'AdminProfiles',
        //     'params' => [
        //         'conditions' => 'SMXD\Application\Models\UserExt.status = :status_active: AND  ( SMXD\Application\Models\UserExt.user_group_id = :user_gms_group_admin: OR SMXD\Application\Models\UserExt.user_group_id = :user_hr_group_admin:)',
        //         'bind' => [
        //             'status_active' => UserExt::STATUS_ACTIVE,
        //             'user_hr_group_admin' => UserGroupExt::GROUP_HR_ADMIN,
        //             'user_gms_group_admin' => UserGroupExt::GROUP_GMS_ADMIN
        //         ],
        //         'order' => 'SMXD\Application\Models\UserExt.created_at ASC'
        //     ]
        // ]);
    }


    /**
     * @return bool
     */
    public function beforeValidationOnCreate()
    {
        /**
         * set official Account YES
         */
        $this->setIsOfficial(ModelHelper::YES);

        $validator = new Validation();

        if ($this->getEmail() != '' && !($this->getId() > 0)) {
            $validator->add(
                ['email'], new UniquenessValidator([
                    'model' => $this,
                    'message' => 'COMPANY_EMAIL_UNIQUE_TEXT'
                ])
            );
        }
        return $this->validate($validator);
    }

    /**
     * @return bool
     */
    public function beforeValidationOnUpdate()
    {
        $validator = new Validation();
        if ($this->getEmail() != '') {
            $validator->add(
                ['email'], new UniquenessValidator([
                    'model' => $this,
                    'message' => 'COMPANY_EMAIL_UNIQUE_TEXT'
                ])
            );
        }
        return $this->validate($validator);
    }

    /**
     * [beforeValidation description]
     * @return [type] [description]
     */
    public function beforeValidation()
    {
        $validator = new Validation();
        if ($this->getEmail() != '') {
            $validator->add(
                'email',
                new EmailValidator([
                    'model' => $this,
                    'message' => 'COMPANY_EMAIL_INCORRECT_TEXT'
                ])
            );
        }
        $this->setEmail(Helpers::__sanitizeEmail($this->getEmail()));
        return $this->validate($validator);
    }

    /**
     * [beforeSave description]
     * @return [type] [description]
     */
    public function beforeSave()
    {
        //@TODO get frontendurl
    }

    /**
     * [afterSave description]
     * @return [type] [description]
     */
    public function afterSave()
    {

    }

    /**
     * @param array $custom : app_id, profile_id, created_by
     * @return array|CompanyExt|Company
     */
    public function __save($custom = [])
    {
        $req = new Request();

        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            $model = $this->findFirst($req->getPut('id'));
            if (!$model instanceof $this) {
                return [
                    'success' => false,
                    'message' => 'COMPANY_NOT_FOUND_TEXT',
                    'detail' => []
                ];
            }
            $data = $req->getPut();
        }

        $model->setName(array_key_exists('name', $data) ? $data['name'] : (isset($custom['name']) ? $custom['name'] : $model->getName()));
        $model->setUuid(isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid()));
        $model->setPhone(isset($custom['phone']) ? $custom['phone'] : (isset($data['phone']) ? $data['phone'] : $model->getPhone()));
        $model->setFax(isset($custom['fax']) ? $custom['fax'] : (isset($data['fax']) ? $data['fax'] : $model->getFax()));
        $model->setEmail(isset($custom['email']) ? $custom['email'] : (isset($data['email']) ? $data['email'] : $model->getEmail()));
        $model->setWebsite(isset($custom['website']) ? $custom['website'] : (isset($data['website']) ? $data['website'] : $model->getWebsite()));
        $model->setAddress(array_key_exists('address', $data) ? $data['address'] : $model->getAddress());
        $model->setStreet(array_key_exists('street', $data) ? $data['street'] : $model->getStreet());
        $model->setTown(array_key_exists('town', $data) ? $data['town'] : $model->getTown());


        $zip_code = array_key_exists('zip_code', $data) ? $data['zip_code'] : (array_key_exists('zipcode', $data) ? $data['zipcode'] : $model->getZipcode());
        $model->setZipcode($zip_code);

        // Process country by post

        if (isset($data['country_id']) && is_numeric($data['country_id'])) {
            $country = $data['country_id'];
        } elseif (isset($data['country']) && isset($data['country']['value'])) {
            $country = $data['country']['value'];
        } elseif (isset($data['country']) && is_numeric($data['country']) && $data['country'] > 0) {
            $country = $data['country'];
        } else {
            $country = isset($data['country_id']) ? $data['country_id'] : (isset($custom['country_id']) ? $custom['country_id'] : $model->getCountryId());
        }

        if (is_array($country)) {
            if (isset($country['value']))
                $country = $country['value'];
        }
        $model->setCountryId($country != "" ? $country : null);

        if (isset($data['profile_id']) || isset($custom['profile_id'])) {
            if (isset($data['profile_id']) && $data['profile_id'] !== "") {
                $model->setHeadUserId($data['profile_id']);
            } elseif (isset($custom['profile_id']) && $custom['profile_id'] !== "") {
                $model->setHeadUserId($custom['profile_id']);
            } else {
                $model->setHeadUserId(null);
            }
        }

        $model->setStatus(array_key_exists('status', $data) ? (int)$data['status'] : $model->getStatus());

        try {
            if ($model->getId() == null) {
            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_COMPANY_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_COMPANY_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __update($custom = [])
    {
        $model = $this;
        if (!($model->getId() > 0)) {
            return [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT',
            ];
        } else {
            return $model->__saveData($custom);
        }
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __saveData($custom = [])
    {

        $req = new Request();
        $model = $this;

        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
                if ($model->getUuid() == '') {
                    $random = new Random;
                    $uuid = $random->uuid();
                    $model->setUuid($uuid);
                }
            }
        }
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at'
                && $field_name != "password"
            ) {

                if (!isset($fields_numeric[$field_name])) {
                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__existCustomValue($field_name, $custom) ? $field_name_value : Helpers::__coalesce($field_name_value, $model->get($field_name));


                    //$field_name_value = $field_name_value != '' ? $field_name_value : $model->get($field_name);
                    $model->set($field_name, $field_name_value);

                } else {

                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->get($field_name));
                    if ($field_name_value != '' && !is_null($field_name_value)) {
                        $model->set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/

        /****** END YOUR CODE **/
        try {
            if ($model->getId() == null) {

            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * @param $custom
     */
    public static function prepareDataFromArray($custom = [])
    {
        return [
            'id' => isset($custom['id']) && Helpers::__checkId($custom['id']) ? $custom['id'] : null,
            'uuid' => isset($custom['uuid']) && Helpers::__checkUuid($custom['uuid']) ? $custom['uuid'] : null,
            'name' => isset($custom['name']) && $custom['name'] != '' ? $custom['name'] : null,
            'email' => isset($custom['email']) && Helpers::__isEmail($custom['email']) ? $custom['email'] : null,
            'phone' => isset($custom['phone']) && Helpers::__checkString($custom['phone']) ? $custom['phone'] : null,
            'fax' => isset($custom['fax']) && Helpers::__checkString($custom['fax']) ? $custom['fax'] : null,
            'website' => isset($custom['website']) && Helpers::__checkString($custom['website']) ? $custom['website'] : null,
            'address' => isset($custom['address']) && Helpers::__checkString($custom['address']) ? $custom['address'] : null,
            'street' => isset($custom['street']) && Helpers::__checkString($custom['street']) ? $custom['street'] : null,
            'zipcode' => isset($custom['zipcode']) && Helpers::__checkString($custom['zipcode']) ? $custom['zipcode'] : null,
            'country_id' => isset($custom['country_id']) && Helpers::__checkId($custom['country_id']) ? $custom['country_id'] : null,
            'head_user_id' => isset($custom['head_user_id']) && Helpers::__checkId($custom['head_user_id']) ? $custom['head_user_id'] : null,
            'status' => isset($custom['status']) && Helpers::__checkStatus($custom['status']) ? (int)$custom['status'] : null,
            'country_name' => isset($custom['country_name']) && Helpers::__checkString($custom['country_name']) ? $custom['phone'] : null,
        ];
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {
        ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/
        $email = Helpers::__getRequestValueWithCustom('email', $custom);
        if ($email == '') $email = $this->getEmail();
        if ($email != '') $this->setEmail($email);

        $country_iso = Helpers::__getRequestValueWithCustom('country_iso', $custom);
        if (isset($country_iso)) {
            if ($country_iso != '') {
                $cny = CountryExt::__findFirstByIsoCodeWithCache($country_iso);
                if ($cny) {
                    $this->setCountryId($cny->getId());
                }
            }
        }

        $countryId = Helpers::__getCustomValue('country_id', $custom);
        if (Helpers::__existCustomValue("country_id", $custom) && ($countryId == 0 || $countryId == null)) {
            $this->setCountryId(ModelHelper::__getValueNull());
        }

        /****** END YOUR CODE **/
    }


    /**
     * @return string
     */
    public function getCountryName()
    {
        return $this->getCountry() ? $this->getCountry()->getName() : '';
    }

    /**
     * @return string
     */
    public function getHeadProfileName()
    {
        return $this->getHeadUser() ? $this->getHeadUser()->getFullName() : '';
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->getStatus() == self::STATUS_ACTIVATED;
    }

    /**
     * @param int $id
     * @return Company
     */
    public static function findFirstByIdCache(int $id)
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $id,
            ],
            'cache' => [
                'key' => 'COMPANY_EXT_' . $id,
                'lifetime' => CacheHelper::__TIME_24H,
            ],
        ]);
    }

    /**
     * @param int $id
     * @return Company
     */
    public static function findFirstByUuidCache(String $uuid)
    {
        return self::findFirst([
            'conditions' => 'uuid = :uuid:',
            'bind' => [
                'uuid' => $uuid,
            ],
            'cache' => [
                'key' => 'COMPANY_EXT_' . $uuid,
                'lifetime' => CacheHelper::__TIME_24H,
            ],
        ]);
    }

    /**
     * @return bool
     */
    public function isOfficial()
    {
        return $this->getIsOfficial() == ModelHelper::YES;
    }

    /**
     * @param null $columns
     * @return mixed
     */
    public function toArrayInItem($columns = NULL, $language = 'en')
    {
        $excludeToDisplay = [
        ];

        $result = $this->toArray($columns);
        if (is_array($excludeToDisplay)) {
            foreach ($excludeToDisplay as $x) {
                unset($result[$x]);
            }
        }
        return $result;
    }

    public function parsedDataToArray(){
        $item = $this->toArray();
        $item['default_billing_address'] = $this->getDefaultBillingAddress();
        return $item;
    }

    public function getDefaultBillingAddress(){
        $defaultAddress = AddressExt::findFirst([
            'conditions' => 'is_default = 1 and company_id = :company_id: and address_type = :address_type:',
            'bind' => [
                'company_id' => $this->getId(),
                'address_type' => AddressExt::BILLING_ADDRESS
            ]
        ]);

        if ($defaultAddress){
            return $defaultAddress;
        }

        $defaultAddress2 = AddressExt::findFirst([
            'conditions' => 'company_id = :company_id: and address_type = :address_type:',
            'bind' => [
                'company_id' => $this->getId(),
                'address_type' => AddressExt::BILLING_ADDRESS
            ],
            'orders' => 'created_at ASC'
        ]);

        if ($defaultAddress2){
            return $defaultAddress2;
        }

        return null;
    }
}
