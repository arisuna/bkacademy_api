<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Validator\PresenceOf;
use Phalcon\Validation;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Resultset\Custom;
use SMXD\Application\Traits\ModelTraits;

class CountryExt extends Country
{

    use ModelTraits;
    const STATUS_ACTIVATED = 1;
    const IS_TOP_YES = 1;
    const IS_TOP_NO = 0;

    const IS_ACTIVE_YES = 1;
    const IS_ACTIVE_NO = 0;

    /**
     * @return string
     */
    static function getTable()
    {
        $instance = new Country();
        return $instance->getSource();
    }

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', 'SMXD\Application\Models\CountryTranslationExt', 'country_id', ['alias' => 'Translation']);
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
    }

    /**
     * [getTranslationByLanguage description]
     * @param string $language [description]
     * @return [type]           [description]
     */
    public function getTranslationByLanguage($language = SupportedLanguageExt::LANG_EN)
    {

        if ($language == 'en') {
            return $this;
        }

        $languageProfile = SupportedLanguageExt::__getLangData($language);

        $translation = $this->getTranslation([
            'conditions' => 'supported_language_id = :languageId:',
            'bind' => [
                'languageId' => $languageProfile->getId(),
            ]
        ])->getFirst();

        if ($translation || (method_exists($translation, 'getValue') && $translation->getValue() != '')) {
            return $translation;
        } else {
            return $this;
        }
    }

    /**
     * [getValueTranslationByLanguage description]
     * @param string $language [description]
     * @return [type]           [description]
     */
    public function getValueTranslationByLanguage($language = SupportedLanguageExt::LANG_EN)
    {

        if ($language == 'en') {
            return $this->get('name');
        }

        $languageProfile = SupportedLanguageExt::__getLangData($language);

        $translation = $this->getTranslation([
            'conditions' => 'supported_language_id = :languageId:',
            'bind' => [
                'languageId' => $languageProfile->getId(),
            ]
        ])->getFirst();

        if ($translation || (method_exists($translation, 'getValue') && $translation->getValue() != '')) {
            return $translation->getValue();
        } else {
            return $this->get('name');
        }
    }

    /**
     * @return bool
     */
    public function beforeValidation()
    {
        $validator = new Validation();
        $validator->add('name', new Validation\Validator\PresenceOf([
            'model' => $this,
            'message' => 'COUNTRY_NAME_REQUIRED_TEXT'
        ]));
        return $this->validate($validator);
    }

    /**
     * [getAll description]
     * @return [type] [description]
     */
    public static function getAll()
    {
        return self::find([
            "conditions" => "active = :active:",
            "bind" => [
                'active' => self::STATUS_ACTIVATED
            ],
            "cache" => [
                "key" => "APP_ALL_COUNTRIES",
            ],
        ]);
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
     * @param $companyId
     * @param $aOptions
     * @return mixed
     */
    public static function __getCountRelocationByCountryOriginGSM($companyId, $aOptions = [])
    {
        $countryItems = [];
        $withCache = true;
        $modelManager = (new self())->getModelsManager();
        $queryBuilder = $modelManager->createBuilder();
        $queryBuilder->addFrom('\SMXD\Application\Models\CountryExt', 'Country');
        $queryBuilder->leftJoin('\SMXD\Application\Models\AssignmentExt', 'Assignment.home_country_id = Country.id', 'Assignment');
        $queryBuilder->leftJoin('\SMXD\Application\Models\RelocationExt', 'Relocation.assignment_id = Assignment.id', 'Relocation');

        $queryBuilder->innerJoin('\SMXD\Application\Models\AssignmentInContractExt', 'Assignment.id = AssignmentInContract.assignment_id', 'AssignmentInContract');
        $queryBuilder->innerJoin('\SMXD\Application\Models\ContractExt', 'AssignmentInContract.contract_id = Contract.id', 'Contract');

        $queryBuilder->distinct(true);
        $queryBuilder->columns(
            ["Country.id", "Country.cio", "Country.label", "number" => "COUNT(Relocation.id)"]
        );


        $queryBuilder->where('Contract.to_company_id = :gms_company_id:');
        $queryBuilder->andwhere('Contract.status = :contract_active:');
        $queryBuilder->andwhere('(Relocation.active = :relocation_activated: OR Relocation.active = :relocation_archived:)');
        $queryBuilder->andwhere('Assignment.archived <> :assignment_archived:');

        if (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '1_month') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        } elseif (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '3_months') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        } elseif (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '6_months') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        } elseif (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '1_year') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        }

        $queryBuilder->orderBy('number DESC');
        $queryBuilder->groupBy('Country.id');

        $bindArray = [
            'gms_company_id' => $companyId,
            'contract_active' => ContractExt::STATUS_ACTIVATED,
            'relocation_activated' => RelocationExt::STATUS_ACTIVATED,
            'relocation_archived' => RelocationExt::STATUS_ARCHIVED,
            'assignment_archived' => AssignmentExt::ARCHIVED_YES
        ];

        try {
            if ( ! $withCache ) {
                $countryItems = $queryBuilder->getQuery()->execute($bindArray);
            } else {
                $countryItems = $queryBuilder->getQuery()->cache([
                    'key' => '__getCountRelocationByCountryOriginGSM_' . $companyId,
                    'lifetime' => CacheHelper::__TIME_5_MINUTES
                ])->execute($bindArray);
            }
        } catch (\Exception $e) {
            $countryItems = [];
        }
        return $countryItems;
    }

    /**
     * @param $companyId
     * @param $aOptions
     * @return array
     */
    public static function __getCountRelocationByCountryDestinationGSM($companyId, $aOptions = [])
    {
        $countryItems = [];
        $withCache = true;

        $modelManager = (new self())->getModelsManager();
        $queryBuilder = $modelManager->createBuilder();
        $queryBuilder->addFrom('\SMXD\Application\Models\CountryExt', 'Country');
        $queryBuilder->leftJoin('\SMXD\Application\Models\AssignmentDestinationExt', 'AssignmentDestination.destination_country_id = Country.id', 'AssignmentDestination');
        $queryBuilder->leftJoin('\SMXD\Application\Models\AssignmentExt', 'Assignment.id = AssignmentDestination.id', 'Assignment');
        $queryBuilder->leftJoin('\SMXD\Application\Models\RelocationExt', 'Relocation.assignment_id = Assignment.id', 'Relocation');

        $queryBuilder->innerJoin('\SMXD\Application\Models\AssignmentInContractExt', 'Assignment.id = AssignmentInContract.assignment_id', 'AssignmentInContract');
        $queryBuilder->innerJoin('\SMXD\Application\Models\ContractExt', 'AssignmentInContract.contract_id = Contract.id', 'Contract');

        $queryBuilder->distinct(true);
        $queryBuilder->columns(
            ["Country.id", "Country.cio", "Country.label", "number" => "COUNT(Relocation.id)"]
        );


        $queryBuilder->where('Contract.to_company_id = :gms_company_id:');
        $queryBuilder->andwhere('Contract.status = :contract_active:');
        $queryBuilder->andwhere('(Relocation.active = :relocation_active: OR Relocation.active = :relocation_archived:)');
        $queryBuilder->andwhere('Assignment.archived <> :assignment_archived:');

        if (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '1_month') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 1 month'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        } elseif (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '3_months') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 3 months'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        } elseif (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '6_months') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 6 months'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        } elseif (isset($aOptions['created_at_period']) && is_string($aOptions['created_at_period']) && $aOptions['created_at_period'] == '1_year') {
            $now = date('Y-m-d', strtotime("now"));
            $created_at = date('Y-m-d', strtotime('- 1 year'));
            $queryBuilder->andwhere('Relocation.created_at >= :created_at: and Relocation.created_at <= :now:', [
                'now' => $now,
                'created_at' => $created_at,
            ]);
            $withCache = false;
        }


        $queryBuilder->orderBy('number DESC');
        $queryBuilder->groupBy('Country.id');

        $bindArray = [
            'gms_company_id' => $companyId,
            'contract_active' => ContractExt::STATUS_ACTIVATED,
            'relocation_active' => RelocationExt::STATUS_ACTIVATED,
            'relocation_archived' => RelocationExt::STATUS_ARCHIVED,
            'assignment_archived' => AssignmentExt::ARCHIVED_YES,
        ];

        if (isset($aOptions['id']) && $aOptions['id'] ) {
            $queryBuilder->andwhere('Country.id = '.$aOptions['id']);
            $withCache = false;
        }

        if (isset($aOptions['query']) && $aOptions['query'] ) {
            $queryBuilder->andwhere('Country.name LIKE "%'.$aOptions['query'].'%"');
            $withCache = false;
        }

        try {
            if ( ! $withCache ) {
                $countryItems = $queryBuilder->getQuery()->execute($bindArray);
            } else {
                $countryItems = $queryBuilder->getQuery()->cache([
                    'key' => '__getCountRelocationByCountryDestinationGSM_' . $companyId,
                    'lifetime' => CacheHelper::__TIME_5_MINUTES
                ])->execute($bindArray);
            }
        } catch (\Exception $e) {
            $countryItems = [];
        }
        return $countryItems;
    }


    /**
     * @param int $countryId
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findFirstByIdCache(int $countryId)
    {
        return self::findFirstWithCache([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $countryId
            ]
        ], CacheHelper::__TIME_6_MONTHS);
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findWithCache($parameters = null, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::find($parameters);
    }

    /**
     * @param null $parameters
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function findFirstWithCache($parameters = null, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        // Convert the parameters to an array
        $parameters = ModelHelper::_getFindParametersWithCache($parameters, $lifetime, (new self())->getSource());
        return parent::findFirst($parameters);
    }

    /**
     * @param $isoCode
     * @param int $lifetime
     * @return Country
     */
    public static function __findFirstByIsoCodeWithCache($isoCode, $lifetime = CacheHelper::__TIME_5_MINUTES)
    {
        if (self::__hasAttribute('cio')) {
            // Convert the parameters to an array
            $parameters = ModelHelper::_getFindParametersWithCache([
                'conditions' => 'cio = :iso_code: AND active = :country_active:',
                'bind' => [
                    'iso_code' => $isoCode,
                    'country_active' => 1
                ]
            ], $lifetime, (new self())->getSource());
            return parent::findFirst($parameters);
        }
    }

    public function parsedDataToArray($lang = 'en'){
        $arr = $this->toArray();
        $nameLang = $this->getValueTranslationByLanguage($lang);
        $arr['name'] = $nameLang;
        return $arr;
    }
}
