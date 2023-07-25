<?php

namespace SMXD\App\Models;

use Mpdf\Cache;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf as PresenceOfValidator;
use Phalcon\Validation\Validator\Email as EmailValidator;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Models\MediaAttachmentExt;

use \Phalcon\Mvc\Model\Transaction\Failed as TransactionFailed;
use \Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class Attributes extends \SMXD\Application\Models\AttributesExt
{
    /**
     * @return \SMXD\Application\Models\Attributes[]
     */
    public static function __getAllAttributes()
    {
        return self::find([
            "cache" => [
                "key" => "__ALL_ATTRIBUTES_",
                "lifetime" => 86400,
            ],
        ]);
    }

    /**
     * @return \SMXD\Application\Models\Attributes[]
     */
    public static function __getAllAttributesByCompany($language)
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\SMXD\App\Models\Attributes', 'Attributes')
            ->distinct(true)
            ->leftjoin('\SMXD\App\Models\AttributesValue', 'AttributesValue.attributes_id = Attributes.id', 'AttributesValue')
            ->leftjoin('\SMXD\App\Models\AttributesValueTranslation', 'AttributesValueTranslation.attributes_value_id = AttributesValue.id', 'AttributesValueTranslation')
            ->where('AttributesValueTranslation.language = :language:', ['language' => $language]);
        if(ModuleModel::$company){
            $queryBuilder->andWhere('(AttributesValue.standard = 1 OR AttributesValue.company_id = :company_id:) AND AttributesValue.archived = 0', [
                'company_id' => ModuleModel::$company->getId(),
            ]);
        } else {
            $queryBuilder->andWhere('(AttributesValue.standard = 1) AND AttributesValue.archived = 0');
        }
        $queryBuilder->columns(
            [
                "code" => "Attributes.code",
                "attribute_id" => "Attributes.id",
                "value_id" => "AttributesValue.id",
                "translation" => "AttributesValueTranslation.value"
            ]
        );

        $items = ($queryBuilder->getQuery()->execute());
        $data_array = [];
        if (count($items) > 0) {
            foreach ($items as $item) {
                $data_array[$item["code"]][$item["attribute_id"]."_".$item["value_id"]] = $item["translation"];
            }
        };
        return $data_array;
    }

    /**
     * init model
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', '\SMXD\App\Models\AttributesValue', 'attributes_id', [
            'alias' => 'values',
        ]);
    }

    /**
     * list all values of an attributes by company
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public function bk_listValuesOfCompany($company_id, $language = 'en')
    {

        if ($language == 'en') {
            $attributesValues = $this->getValues([
                "standard = 1 OR ( company_id = :company_id: AND archived = 0 )",
                "bind" => [
                    "company_id" => $company_id,
                ],
            ]);
            $attributeArray = [];
            foreach ($attributesValues as $attributeValue) {
                $attributeArray[] = [
                    'id' => intval($attributeValue->getId()),
                    'value' => $attributeValue->getValue(),
                    'company_id' => intval($attributeValue->getCompanyId()),
                    'attributes_id' => intval($attributeValue->getAttributesId()),
                    'standard' => intval($attributeValue->getStandard()),
                    'archived' => intval($attributeValue->getArchived()),
                    'code' => $attributeValue->getCode()
                ];
            }
            return $attributeArray;
        } else {
            $attributesValues = $this->getValues([
                "standard = 1 OR ( company_id = :company_id: AND archived = 0 )",
                "bind" => [
                    "company_id" => $company_id,
                ]
            ]);
            $attributeArray = [];
            foreach ($attributesValues as $attributeValue) {
                $translation = $attributeValue->getTranslation([
                    "conditions" => "language = :language:",
                    "bind" => [
                        "language" => $language
                    ],
                ])->getFirst();
                $item = $attributeValue->toArray();
                $item['code'] = $attributeValue->getCode();
                if ($translation) {
                    $item['value'] = $translation->getValue();
                }
                $attributeArray[] = $item;
            }
            return $attributeArray;
        }
    }


    /**
     * list all values of an attributes by company
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public function listValuesArchivedOfCompany($company_id, $language = 'en')
    {

        if ($language == 'en') {
            $attributesValues = $this->getValues([
                "company_id = :company_id: AND archived = 1",
                "bind" => [
                    "company_id" => $company_id,
                ],
            ]);
            $attributeArray = [];
            foreach ($attributesValues as $attributeValue) {
                $attributeArray[] = [
                    'id' => intval($attributeValue->getId()),
                    'value' => $attributeValue->getValue(),
                    'company_id' => intval($attributeValue->getCompanyId()),
                    'attributes_id' => intval($attributeValue->getAttributesId()),
                    'standard' => intval($attributeValue->getStandard()),
                    'archived' => intval($attributeValue->getArchived()),
                    'code' => $attributeValue->getCode()
                ];
            }
            return $attributeArray;
        } else {
            $attributesValues = $this->getValues([
                "( company_id = :company_id: AND archived = 1 )",
                "bind" => [
                    "company_id" => $company_id,
                ]
            ]);
            $attributeArray = [];
            foreach ($attributesValues as $attributeValue) {
                $translation = $attributeValue->getTranslation([
                    "conditions" => "language = :language:",
                    "bind" => [
                        "language" => $language
                    ],
                ])->getFirst();
                $item = $attributeValue->toArray();
                $item['code'] = $attributeValue->getCode();
                if ($translation) {
                    $item['value'] = $translation->getValue();
                }
                $attributeArray[] = $item;
            }
            return $attributeArray;
        }
    }

    /**
     * [__save description]
     * @return [type] [description]
     */
    public function __save($custom = [])
    {

        $company = ModuleModel::$company;
        $company_id = ModuleModel::$company->getId();

        $values = isset($custom['values']) ? $custom['values'] : [];
        $language = isset($custom['language']) && $custom['language'] != '' ? $custom['language'] : ModuleModel::$language;
        $values_to_remove = isset($custom['values_to_remove']) ? $custom['values_to_remove'] : [];

        $transactionManager = new TransactionManager();
        $transaction = $transactionManager->get();

        foreach ($values as $attributeValue) {

            $attributeValue = (array)$attributeValue;
            if ($attributeValue['standard'] == AttributesValue::ATTRIBUTE_VALUE_STANDARD) {
                continue;
            }

            $attributeValueObject = false;
            if (isset($attributeValue['id']) && $attributeValue['id'] > 0) {
                $attributeValueObject = AttributesValue::findFirstById($attributeValue['id']);
            }

            if (isset($attributeValue['id']) && $attributeValue['id'] > 0 && $attributeValueObject) {
                $translation = $attributeValueObject->getTranslation([
                    'conditions' => 'language = :language:',
                    "bind" => [
                        "language" => $language,
                    ]
                ])->getFirst();

                if (isset($translation) && $translation) {
                    $translation->setValue($attributeValue['value']);
                } else {
                    $translation = new AttributesValueTranslation();
                    $translation->setValue($attributeValue['value']);
                    $translation->setLanguage($language);
                    $attributeValueObject->translation = [$translation];
                    $attributeValueObject->setCompanyId($company_id);
                }
                if ($language == "en") {
                    $attributeValueObject->setValue($attributeValue['value']);
                }
            } else {
                $attributeValueObject = new AttributesValue();
                $translation = new AttributesValueTranslation();

                $translation->setValue($attributeValue['value']);
                $translation->setLanguage($language);


                $attributeValueObject->setAttributesId($this->getId());
                $attributeValueObject->setCompanyId($company_id);
                $attributeValueObject->setValue($attributeValue['value']);
                $attributeValueObject->setStandard(AttributesValue::ATTRIBUTE_VALUE_NOT_STANDARD);
                $attributeValueObject->setArchived(AttributesValue::ATTRIBUTE_VALUE_NOT_ARCHIVED);
                $attributeValueObject->translation = [$translation];
            }

            $resultSaveValue = $attributeValueObject->__quickSave();
            if ($resultSaveValue['success'] == false) {
                $transaction->rollback();
                $result = [
                    'detail' => $resultSaveValue['detail'],
                    'success' => false,
                    'message' => 'SAVE_ATTRIBUTES_FAIL_TEXT',
                    'detail' => $attributeValueObject
                ];
                return $result;
            }


        }

        foreach ($values_to_remove as $attributeValue) {
            $attributeValue = (array)$attributeValue;

            if ($attributeValue['standard'] == AttributesValue::ATTRIBUTE_VALUE_STANDARD) {
                continue;
            }
            if (isset($attributeValue['id']) && $attributeValue['id'] > 0) {
                $attributeValueObject = AttributesValue::findFirstById($attributeValue['id']);

                if ($attributeValueObject) {
                    $resultRemove = $attributeValueObject->__quickRemove();
                    if ($resultRemove['success'] == false) {
                        $transaction->rollback();
                        $result = [
                            'success' => false,
                            'message' => 'SAVE_ATTRIBUTES_FAIL_TEXT',
                            'detail' => $attributeValueObject
                        ];
                        return $result;
                    }
                }
            }
        }

        $transaction->commit();
        $result = [
            'success' => true,
            'message' => 'SAVE_ATTRIBUTES_SUCCESS_TEXT',
        ];
        return $result;
    }

    /**
     * @param $value
     * @param $language
     */
    public function saveSimpleValue($value, $company_id, $language)
    {


        $attributeValueObject = new AttributesValue();
        $translation = new AttributesValueTranslation();

        $translation->setValue($value);
        $translation->setLanguage($language);

        $attributeValueObject->setAttributesId($this->getId());
        $attributeValueObject->setCompanyId($company_id);
        $attributeValueObject->setValue($value);
        $attributeValueObject->setStandard(AttributesValue::ATTRIBUTE_VALUE_NOT_STANDARD);
        $attributeValueObject->setArchived(AttributesValue::ATTRIBUTE_VALUE_NOT_ARCHIVED);
        $attributeValueObject->translation = [$translation];

        try {
            if ($attributeValueObject->save()) {
                $result = [
                    'success' => true,
                    'data' => [
                        'complex_id' => $this->getId() . "_" . $attributeValueObject->getId(),
                        'value' => $value,
                    ],
                    'message' => 'SAVE_ATTRIBUTES_SUCCESS_TEXT',
                    'detail' => $attributeValueObject
                ];
            } else {
                $msg = [];
                foreach ($attributeValueObject->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_ATTRIBUTES_FAIL_TEXT',
                    'detail' => $attributeValueObject
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_ATTRIBUTES_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'SAVE_ATTRIBUTES_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
        }

        return $result;
    }

}
