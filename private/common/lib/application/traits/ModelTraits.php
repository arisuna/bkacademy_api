<?php

namespace SMXD\Application\Traits;

use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;

trait ModelTraits
{
    /**
     * @var bool
     */
    protected $__isNew = true;

    /**
     * @return mixed
     */
    public function toArray($columns = NULL)
    {
        $array = parent::toArray($columns);
        $array = ModelHelper::__toArray($this, $array);
        return $array;
    }


    /**
     * @return array
     */
    public function __quickUpdate()
    {
        return ModelHelper::__quickUpdate($this);
    }

    /**
     * @return array
     */
    public function __quickCreate()
    {
        return ModelHelper::__quickCreate($this);
    }

    /**
     * @return array
     */
    public function __quickSave()
    {
        return ModelHelper::__quickSave($this);
    }

    /**
     * @return array
     */
    public function __quickRemove()
    {
        return ModelHelper::__quickRemove($this);
    }



    /**
     * [set description]
     * @param [type] $name  [description]
     * @param [type] $value [description]
     */
    public function set($name, $value)
    {
        $this->$name = $value;
    }

    /**
     * [set description]
     * @param [type] $name  [description]
     * @param [type] $value [description]
     */
    public function get($name)
    {
        return $this->$name;
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function __findWithCache($parameters = null, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::find($parameters);
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function __findFirstWithCache($parameters = null, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        // Convert the parameters to an array
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::findFirst($parameters);
    }

    /**
     * @param $id
     * @param $lifetime
     * @return mixed
     */
    public static function __findFirstByIdWithCache($id, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        // Convert the parameters to an array
        $parameters = ModelHelper::_getFindParametersWithCache([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $id
            ]
        ], $lifetime, (new self())->getSource());
        return parent::findFirst($parameters);
    }

    /**
     * @param $id
     * @param $lifetime
     * @return mixed
     */
    public static function __findFirstByGeonameidWithCache($geonameid, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        // Convert the parameters to an array
        if (self::__hasAttribute('geonameid')) {
            $parameters = ModelHelper::_getFindParametersWithCache([
                'conditions' => 'geonameid = :geonameid:',
                'bind' => [
                    'geonameid' => $geonameid
                ]
            ], $lifetime, (new self())->getSource());
            return parent::findFirst($parameters);
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
     * @param $id
     * @param $lifetime
     * @return mixed
     */
    public static function __findFirstByCountryCodeWithCache($countryCode, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        if (self::__hasAttribute('country_code')) {
            // Convert the parameters to an array
            $parameters = ModelHelper::_getFindParametersWithCache([
                'conditions' => 'country_code = :country_code:',
                'bind' => [
                    'country_code' => $countryCode
                ]
            ], $lifetime, (new self())->getSource());
            return parent::findFirst($parameters);
        }
    }

    /**
     * @param $id
     * @param $lifetime
     * @return mixed
     */
    public static function __findFirstByCountryIdWithCache($countryId, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        if (self::__hasAttribute('country_id')) {
            // Convert the parameters to an array
            $parameters = ModelHelper::_getFindParametersWithCache([
                'conditions' => 'country_id = :country_id:',
                'bind' => [
                    'country_id' => $countryId
                ]
            ], $lifetime, (new self())->getSource());
            return parent::findFirst($parameters);
        }
    }

    /**
     * @param $id
     * @param $lifetime
     * @return mixed
     */
    public static function __findByCountryIdWithCache($countryId, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        if (self::__hasAttribute('country_id')) {
            // Convert the parameters to an array
            $parameters = ModelHelper::_getFindParametersWithCache([
                'conditions' => 'country_id = :country_id:',
                'bind' => [
                    'country_id' => $countryId
                ]
            ], $lifetime, (new self())->getSource());
            return parent::find($parameters);
        }
    }


    /**
     * @param $attribute
     * @return mixed
     */
    public function hasAttribute($attribute)
    {
        $modelData = $this->getModelsMetaData();
        return $modelData->hasAttribute($this, $attribute);
    }

    /**
     * @param String $attribute
     * @return mixed
     */
    public static function __hasAttribute(String $attribute)
    {
        $model = new self();
        return $model->hasAttribute($attribute);
    }

    /**
     * @return bool
     */
    public function isExists()
    {
        return $this->_exists($this->getModelsMetaData(), $this->getReadConnection());
    }

    /**
     * @param $uuid
     * @return \SMXD\Application\Models\Employee
     */
    public static function __findFirstByUuid($uuid)
    {
        try {
            return self::findFirstByUuid($uuid);
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * @param $uuid
     * @return \SMXD\Application\Models\Employee
     */
    public static function __findFirstByUuidCache($uuid, $cacheLifeTime = CacheHelper::__TIME_5_MINUTES)
    {
        if (self::__hasAttribute('uuid')) {
            // Convert the parameters to an array
            $parameters = ModelHelper::_getFindParametersWithCache([
                'conditions' => 'uuid = :uuid:',
                'bind' => [
                    'uuid' => $uuid
                ]
            ], $cacheLifeTime, (new self())->getSource());
            return parent::findFirst($parameters);
        }
    }

    /**
     * @param $array
     */
    public static function __generateSimpleConditions($fieldValues = [])
    {
        $conditions = [];
        $bind = [];
        foreach ($fieldValues as $fieldName => $fieldValue) {
            $conditions[] = $fieldName . " = :$fieldName:";
            $bind[$fieldName] = $fieldValue;
        }
        return [
            'conditions' => implode(" AND ", $conditions),
            'bind' => $bind,
        ];
    }

    /**
     * @param String $fieldName
     */
    public function hasChangedInteger(String $fieldName)
    {
        if ($this->hasChanged($fieldName)) {
            $data = $this->getOldSnapshotData();
            if (intval($this->__get($fieldName)) == intval($data[$fieldName])) {
                return false;
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * @param String $fieldName
     */
    public function hasChangedFloat(String $fieldName)
    {
        if ($this->hasChanged($fieldName)) {
            $data = $this->getOldSnapshotData();
            if (floatval($this->__get($fieldName)) == floatval($data[$fieldName])) {
                return false;
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * @param String $fieldName
     */
    public function hasChangedField(String $fieldName)
    {
        if ($this->hasChanged($fieldName)) {
            $data = $this->getOldSnapshotData();
            if ($this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_FLOAT || $this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_DECIMAL || $this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_DOUBLE) {
                if (floatval($this->__get($fieldName)) == floatval($data[$fieldName])) {
                    return false;
                } else {
                    return true;
                }
            } else if ($this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_INTEGER) {
                if (intval($this->__get($fieldName)) == intval($data[$fieldName])) {
                    return false;
                } else {
                    return true;
                }
            } else {
                return $this->hasChanged($fieldName);
            }
        }
        return false;
    }

    /**
     * @param String $fieldName
     */
    public function getOldFieldValue(String $fieldName)
    {
        if ($this->hasSnapshotData()) {
            $data = $this->getOldSnapshotData();
            if ($this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_FLOAT || $this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_DECIMAL || $this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_DOUBLE) {
                return floatval($data[$fieldName]);
            } else if ($this->getFieldType($fieldName) == \Phalcon\Db\Column::TYPE_INTEGER) {
                return intval($data[$fieldName]);
            } else {
                return $data[$fieldName];
            }
        }
    }

    /**
     * @param String $fieldName
     */
    public function getFieldType(String $fieldName)
    {
        $metadata = $this->getModelsMetaData();
        $types = $metadata->getDataTypes($this);
        if (array_key_exists($fieldName, $types) && !is_null($types[$fieldName])) {
            return $types[$fieldName];
        }
    }

    /**
     * Implement a method that returns a string key based
     * on the query parameters
     */
    protected static function __createCustomCacheKey($parameters)
    {
        $uniqueKey = [];
        foreach ($parameters as $key => $value) {
            if (is_scalar($value)) {
                $uniqueKey[] = $key . ':' . $value;
            } elseif (is_array($value)) {
                $uniqueKey[] = $key . ':[' . self::__createCustomCacheKey($value) . ']';
            }
        }
        return join(',', $uniqueKey);
    }

    /**
     *
     */
    public function afterFetch()
    {
        $this->__isNew = false;
    }
}

