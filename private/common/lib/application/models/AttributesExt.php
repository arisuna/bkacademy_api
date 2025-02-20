<?php

namespace SMXD\Application\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Traits\ModelTraits;
use Phalcon\Validation;

class AttributesExt extends Attributes
{
    use ModelTraits;
    const VEHICLE_TYPE = 'VEHICLE_TYPE';
    const EMPLOYEE_GRADE = 'EMPLOYEE_GRADE';
    const PROVIDER_POLICY = 'PROVIDER_POLICY';
    const BILLING_POLICY = 'BILLING_POLICY';
    const MARITAL_STATUS = 'MARITAL_STATUS';
    const NEED_ASSESSMENT_CATEGORY = 'NEED_ASSESSMENT_CATEGORY';

    const LIMIT_PER_PAGE = 50;

    /**
     *
     */
    public function initialize()
    {
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

        $this->hasMany('id', 'SMXD\Application\Models\AttributesDescriptionTranslation', 'attributes_id', [
            'alias' => 'AttributesDescriptionTranslation'
        ]);


        $this->hasMany('id', '\SMXD\Application\Models\AttributesValueExt', 'attributes_id', [
            'alias' => 'AttributesValue',
        ]);


    }

    /**
     * @return bool
     */
    public function validation()
    {
        $validator = new Validation();
        $validator->add(
            'name',
            new Validation\Validator\PresenceOf([
                'model' => $this,
                'message' => 'NAME_REQUIRED_TEXT'
            ])
        );

        $validator->add(
            'code',
            new Validation\Validator\PresenceOf([
                'model' => $this,
                'message' => 'CODE_REQUIRED_TEXT'
            ])
        );

        $validator->add(
            ['name'],
            new Validation\Validator\Uniqueness([
                'model' => $this,
                'message' => 'NAME_SHOULD_BE_UNIQUE_TEXT',
            ])
        );


        $validator->add(
            ['code'],
            new Validation\Validator\Uniqueness([
                'model' => $this,
                'message' => 'CODE_SHOULD_BE_UNIQUE_TEXT',
            ])
        );

        return $this->validate($validator);
    }

    /**
     * list all values of an attributes by company
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public function getListValuesOfCompany($company_id = 0, $language = SupportedLanguageExt::LANG_EN)
    {
        if(Helpers::__isNull($language) == true){
            $language = SupportedLanguageExt::LANG_EN;
        }
        $attributesValues = $this->getAttributesValue([
            "(standard = 1 OR company_id = :company_id: OR company_id is null) AND archived = 0",
            "bind" => [
                "company_id" => $company_id
            ]
        ]);
        $attributeListArray = [];
        foreach ($attributesValues as $attributeValue) {
            $messageException = '';
            $translation = false;
            try {
                $translationList = $attributeValue->getAttributesValueTranslationItems([
                    "conditions" => "language = :language:",
                    "bind" => [
                        "language" => $language
                    ]
                ]);
                if($translationList->count() > 0){
                    $translation = $translationList->getFirst();
                }
            } catch (\Exception $e) {
                $translation = false;
            }

            $item = $attributeValue->toArray();
            $item['code'] = $attributeValue->getCode();
            if ($translation && Helpers::__isNull($translation->getValue()) == false) {
                $item['value'] = $translation->getValue();
                $item['translation_id'] = $translation->getId();
                $item['hasTranslation'] = $translation;
            } else {
                $item['value'] = $attributeValue->getValue();
                $item['translation_id'] = false;
                $item['hasTranslation'] = false;
            }
            $attributeListArray[] = $item;
        }
        return $attributeListArray;

    }

    /**
     * @param $id
     * @return Attributes
     */
    public static function __getAttributeById($id)
    {
        return self::findFirst([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $id,
            ],
            'cache' => [
                'key' => 'CACHE_ATTRIBUTE_' . $id,
                'lifetime' => 86400
            ],
        ]);
    }

    /**
     * @param $name
     * @return Attributes
     */
    public static function __getAttributeByName($name)
    {
        return self::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $name,
            ],
            'cache' => [
                'key' => 'CACHE_ATTRIBUTE_' . $name,
                'lifetime' => 86400
            ],
        ]);
    }

    /**
     * value exact of an attribute by languages
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function __getTranslateValue($name, $language = SupportedLanguageExt::LANG_EN)
    {
        /** check name */

        if (preg_match('#^([0-9]+)\_([0-9]+)$#', $name) == true) {

            $cacheKey = 'ATTTRIBUTE_VALUE_' . $name . '_' . $language;

            $di = \Phalcon\DI::getDefault();
            $cacheManager = $di->get('cache');
            if (!$cacheManager->exists($cacheKey)) {

                list($attributeId, $attributeValueId) = explode("_", $name);
                $attribute = self::__getAttributeById($attributeId);

                $attributeValue = $attribute->getAttributesValue([
                    'conditions' => 'id = :id:',
                    'bind' => [
                        'id' => $attributeValueId
                    ]
                ]);

                if ($attributeValue && $attributeValue->count() > 0) {
                    $firstValue = $attributeValue->getFirst();
                    if ($firstValue) {
                        $translation = $firstValue->getTranslation([
                            'conditions' => 'language = :language:',
                            'bind' => [
                                'language' => $language,
                            ]
                        ]);

                        if ($language != SupportedLanguageExt::LANG_EN && $translation->count() == 0) {
                            $translation = $attributeValue->getFirst()->getTranslation([
                                'conditions' => 'language = :language:',
                                'bind' => [
                                    'language' => SupportedLanguageExt::LANG_EN,
                                ]
                            ]);
                        }

                        if ($translation && $translation->count() > 0) {
                            // $cacheManager->save($cacheKey, $translation->getFirst()->getValue(), 86400);
                            return $translation->getFirst()->getValue();
                        } else {
                            // $cacheManager->save($cacheKey, $attributeValue->getFirst()->getValue(), 86400);
                            return $attributeValue->getFirst()->getValue();
                        }
                    }
                }
            } else {
                return $cacheManager->get($cacheKey);
            }
        }
    }


    /**
     * list all translation of an value
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function __getTranslateValueList($name)
    {
        /** check name */
        if (preg_match('#^([0-9]+)\_([0-9]+)$#', $name) == true) {
            $cacheKey = 'ATTTRIBUTE_VALUE_LIST_' . $name;
            $di = \Phalcon\DI::getDefault();
            $cacheManager = $di->get('cache');
            if (!$cacheManager->exists($cacheKey)) {
                list($attributeId, $attributeValueId) = explode("_", $name);
                $attribute = self::__getAttributeById($attributeId);
                $attributeValue = $attribute->getAttributesValue([
                    'conditions' => 'id = :id:',
                    'bind' => [
                        'id' => $attributeValueId
                    ]
                ]);
                if ($attributeValue) {
                    $translationList = $attributeValue->getFirst()->getTranslation();
                    if ($translationList->count()) {
                        $translationArray = [];
                        foreach ($translationList as $translationValue) {
                            $translationArray[] = [
                                'language' => $translationValue->getLanguage(),
                                'value' => $translationValue->getValue()
                            ];
                        }
                        $cacheManager->save($cacheKey, $translationArray, 86400);
                        return $translationArray;
                    }
                }
            } else {
                return $cacheManager->get($cacheKey);
            }
        }
    }

    /**
     * @param String $name
     * @return string
     */
    public static function __getAttributeName(String $name)
    {
        /** check name */
        if (preg_match('#^([0-9]+)\_([0-9]+)$#', $name) == true) {
            list($attributeId, $attributeValueId) = explode("_", $name);
            $attribute = self::__getAttributeById($attributeId);
            if ($attribute) {
                return $attribute->getName();
            }
        }
        return null;
    }

    /**
     * value exact of an attribute by languages
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public static function __getTranslateValueWithoutCache($value, $language = SupportedLanguageExt::LANG_EN)
    {
        /** check name */

        if (preg_match('#^([0-9]+)\_([0-9]+)$#', $value) == true) {

            list($attributeId, $attributeValueId) = explode("_", $value);
            $attribute = self::findFirstById($attributeId);

            $attributeValue = $attribute->getAttributesValue([
                'conditions' => 'id = :id:',
                'bind' => [
                    'id' => $attributeValueId
                ]
            ]);

            if ($attributeValue && $attributeValue->count() > 0) {
                $firstValue = $attributeValue->getFirst();
                if ($firstValue) {
                    $translation = $firstValue->getTranslation([
                        'conditions' => 'language = :language:',
                        'bind' => [
                            'language' => $language,
                        ]
                    ]);

                    if ($language != SupportedLanguageExt::LANG_EN && $translation->count() == 0) {
                        $translation = $attributeValue->getFirst()->getTranslation([
                            'conditions' => 'language = :language:',
                            'bind' => [
                                'language' => SupportedLanguageExt::LANG_EN,
                            ]
                        ]);
                    }

                    if ($translation && $translation->count() > 0) {
                        return $translation->getFirst()->getValue();
                    } else {
                        return $attributeValue->getFirst()->getValue();
                    }
                }
            }
        }
        return null;
    }
}
