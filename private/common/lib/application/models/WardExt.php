<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\Helpers;
use Phalcon\Security\Random;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Relation;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;

class WardExt extends Ward
{
    use ModelTraits;

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');

        $this->hasMany('administrative_unit_id', '\SMXD\Application\Models\AdministrativeUnitExt', 'id', [
            'alias' => 'AdministrativeUnit'
        ]);

        $this->hasMany('district_id', '\SMXD\Application\Models\DistrictExt', 'id', [
            'alias' => 'District'
        ]);
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
     * @param array $custom
     */
    public function setData($custom = [])
    {

        ModelHelper::__setData($this, $custom);
        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
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
        $metadata = $this->getDI()->get('modelsMetadata');
        $types = $metadata->getDataTypes($this);
        foreach ($types as $attribute => $type) {
            $array[$attribute] = ModelHelper::__getAttributeValue($type, $array[$attribute]);
        }
        return $array;
    }

    /**
     * @param int $countryId
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findFirstByCodeCache($code)
    {
        return self::findFirstWithCache([
            'conditions' => 'code = :code:',
            'bind' => [
                'code' => $code
            ]
        ], CacheHelper::__TIME_6_MONTHS);
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findWithCache($parameters = null, $lifetime = CacheHelper::__TIME_6_MONTHS)
    {
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::find($parameters);
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findFirstWithCache($parameters = null, $lifetime = CacheHelper::__TIME_6_MONTHS)
    {
        // Convert the parameters to an array
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::findFirst($parameters);
    }
}
