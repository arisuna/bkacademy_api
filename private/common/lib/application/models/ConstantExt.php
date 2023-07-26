<?php
/**
 *
 */

namespace SMXD\Application\Models;

use Mpdf\Cache;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Traits\ModelTraits;

class ConstantExt extends Constant
{
    use ModelTraits;
    /**
     *
     */
    public function initilize()
    {
        $this->hasMany('id', 'SMXD\Application\Models\ConstantTranslationExt', 'constant_id', [
            'alias' => 'ConstantTranslation',
            'reusable' => true,
            'cache' => [
                "key" => CacheHelper::__getCacheNameConstantTranslation($this->getName()),
                "lifetime" => CacheHelper::__TIME_24H
            ]
        ]);
    }

    /**
     * @param $lang
     */
    public function getTranslatedValue($lang = SupportedLanguageExt::LANG_EN)
    {
        $result = $this->getConstantTranslation([
            'conditions' => 'language = :language:',
            'bind' => [
                'language' => $lang
            ],
            'cache' => [
                'key' => CacheHelper::__getCacheNameConstantTranslationLanguage($this->getName(), $lang),
                "lifetime" => CacheHelper::__TIME_24H
            ]
        ]);

        if (!$result) {
            $result = $this->getConstantTranslation([
                'conditions' => 'language = :language:',
                'bind' => [
                    'language' => "en"
                ],
                'cache' => [
                    'key' => CacheHelper::__getCacheNameConstantTranslationLanguage($this->getName(), 'en'),
                    "lifetime" => CacheHelper::__TIME_24H
                ]
            ]);

        }

        if ($result) {
            return $result->getFirst() ? $result->getFirst()->getValue() : $this->getValue();
        } else {
            return $this->getValue();
        }
    }

    /**
     * @param string $language
     * @return array
     */
    public static function getQuoteLabelList($language = 'en')
    {
        $label_list = [
            'QUOTE_TEXT',
            'REQUESTER_TEXT',
            'NOTE_INSTRUCTION_TEXT',
            'ITEM_TEXT',
            'QUANTITY_TEXT',
            'PRICE_TEXT',
            'CURRENCY_TEXT',
            'AMOUNT_TEXT',
            'SUBTOTAL_TEXT',
            'DISCOUNT_TEXT',
            'TAX_TEXT',
            'TOTAL_TEXT',
            'DATE_TEXT',
            'QUOTE_MESSAGE_TEXT',
            'REFERENCE_TEXT',
            'ORIGIN_TEXT'
        ];
        // Translate label
        $constants = self::find([
            'conditions' => 'name IN ({label_list:array})',
            'bind' => [
                'label_list' => $label_list
            ]
        ]);
        $keys = [];

        if (count($constants) > 0) {
            foreach ($constants as $constant) {
                $keys[$constant->getName()] = $constant->getTranslatedValue($language);
            }
        }
        return $keys;
    }

    /**
     * @param $language
     * @return array
     */
    public static function getInvoiceLabelList($language)
    {
        $label_list = [
            'INVOICE_TEXT',
            'INVOICED_BY_TEXT',
            'INVOICED_TO_TEXT',
            'NOTE_INSTRUCTION_TEXT',
            'ITEM_TEXT',
            'QUANTITY_TEXT',
            'PRICE_TEXT',
            'CURRENCY_TEXT',
            'AMOUNT_TEXT',
            'SUBTOTAL_TEXT',
            'DISCOUNT_TEXT',
            'TAX_TEXT',
            'TOTAL_TEXT',
            'DATE_TEXT',
            'DUE_DATE_TEXT',
            'PAYMENT_TO_TEXT',
            'COMPANY_NAME_TEXT',
            'ADDRESS_TEXT',
            'BANK_ACCOUNT_TEXT',
            'BANK_NAME_TEXT',
            'REFERENCE_TEXT',
            'ORIGIN_TEXT',
            'TOTAL_TAX_TEXT',
            'TOTAL_BEFORE_TAX_TEXT',
            'UNIT_PRICE_TEXT',
            'ORDER_NUMBER_TEXT',
            'FOR_TEXT',
            'QTY_TEXT',
            'DISCOUNT_AMOUNT_TEXT',
            'NOTE_TEXT',
            'BANK_NAME_TEXT',
            'BANK_ACCOUNT_TEXT',
            'VAT_NUMBER_TEXT',
            'BILL_TO_TEXT',
            'ITEMS_TEXT',

        ];
        // Translate label
        $constants = self::find([
            'conditions' => 'name IN ({label_list:array})',
            'bind' => [
                'label_list' => $label_list
            ]
        ]);
        $keys = [];
        if (count($constants) > 0) {
            foreach ($constants as $constant) {
                $keys[$constant->getName()] = $constant->getTranslatedValue($language);
            }
        }
        return $keys;
    }



    /**
     * @param $language
     * @return array
     */
    public static function getSalaryCalculatorLabelList($language)
    {
        // Translate label
        $label_list = [
            'COST_PROJECTION_FOOTER_TEXT',
            'COST_PROJECTION_HEADER_TEXT',
            'DATE_TEXT',
            'MOVING_FROM_TEXT',
            'MOVING_TO_TEXT',
            'COST_PROJECTION_OVERVIEW_TEXT',
            'COMPANY_TEXT',
            'EXCHANGE_RATE_TEXT',
            'LAST_NAME_TEXT',
            'EXCHANGE_RATE_DATE_TEXT',
            'FIRST_NAME_TEXT',
            'OVERVIEW_TEXT',
            'TOTAL_ALLOWANCES_TEXT',
            'TOTAL_COSTS_TO_COMPANY_TEXT',
            'ESTIMATED_INCREASE_IN_REVENUE_TEXT',
            'COST_PROJECTION_TEXT',
            'COST_PROJECTION_IN_MAIN_CURRENCY_TEXT',
            'INFLATION_RATE_TEXT',
            'YEAR_TEXT',
            'SALARY_CALCULATOR_FOOTER_TEXT',
            'SALARY_CALCULATOR_HEADER_TEXT',
            'ESTIMATED_WAGE_FOR_RELOCATION_TEXT',
            'MARITAL_STATUS_TEXT',
            'SINGLE_TEXT',
            'MARRIED_TEXT',
            'N_OF_CHILDREN_TEXT',
            'CHILDREN_UNDER_18_TEXT',
            'US_CITIZEN_TEXT',
            'YES_TEXT',
            'NO_TEXT',
            'TAX_STATUS_IN_TEXT',
            'RESIDENT_TEXT',
            'ALIEN_TEXT',
            'TAX_YEAR_START_DATE_IN_TEXT',
            'TAX_YEAR_END_DATE_IN_TEXT',
            'COLA_TEXT',
            'COL_INDEX_FOR_TEXT',
            'COL_INDEX_TEXT',
            'DATE_COL_INDEX_TEXT',
            'COL_LAST_UPDATED_ON_TEXT',
            'HOME_LOCATION_TEXT',
            'HOST_LOCATION_TEXT',
            'TOTAL_GROSS_SALARY_TEXT',
            'GROSS_ANNUAL_SALARY_TEXT',
            'GROSS_ANNUAL_BONUS_TEXT',
            'SOCIAL_CONTRIBUTIONS_TEXT',
            'INCOME_TAX_TEXT',
            'USA_INCOME_TAX_TEXT',
            'FAMILY_BENEFITS_TEXT',
            'TOTAL_ANNUAL_NET_HOME_TEXT',
            'SPENDABLE_INCOME_TEXT',
            'SCHOOLING_COSTS_TEXT',
            'HOUSING_COSTS_TEXT',
            'APPLICABLE_COLA_TEXT',
            'SAVING_AND_INVESTMENT_OUTPUT_TEXT',
            'COST_TO_EMPLOYER_TEXT',
            'EMPLOYER_SOCIAL_CONTRIBUTIONS_TEXT',
            'EMPLOYER_HOUSING_CONTRIBUTIONS_TEXT',
            'EMPLOYER_SCHOOLING_CONTRIBUTIONS_TEXT',
            'EMPLOYER_TOTAL_COST_TEXT',
            'WAGE_ESTIMATED_FOR_RELOCATION_TEXT',
            'COST_LIVING_INDEX_TEXT',
            'COST_LIVING_WEIGHTING_TEXT',
            'CITY_TEXT',
            'INDEX_TEXT',
            'BIN_WEIGHTING_TEXT',
            'INDEX_TOTAL_TEXT',
            'RESTAURANTS_TEXT',
            'FOOD_AT_HOME_TEXT',
            'BEVERAGE_AND_ALCOHOL_TEXT',
            'TRANSPORT_TEXT',
            'LEISURE_TEXT',
            'CLOTHING_TEXT',
            'UTILITIES_TEXT',
            'OTHER_TEXT',
            'OUR_COST_OF_LIVING_SURVEY_FOUND_THAT_AN_EXPENDITURE_OF_TEXT',
            'IN_TEXT',
            'WOULD_COST_YOU_TEXT',
            'NOTES_TEXT',
            'SALARY_CALCULATOR_NOTE1_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE1_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE2_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE2_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE3_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE3_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE4_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE4_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE5_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE5_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE6_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE6_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE7_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE7_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE8_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE8_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE9_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE9_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE10_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE10_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE11_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE11_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_NOTE12_TITLE_TEXT',
            'SALARY_CALCULATOR_NOTE12_SUBTITLE_TEXT',
            'SALARY_CALCULATOR_INFOS_TEXT',
            'SALARY_CALCULATOR_INFOS_DETAIL_TEXT',
            'SALARY_CALCULATOR_DISCLAIMERS_TEXT',
            'SALARY_CALCULATOR_DISCLAIMERS_DETAIL_TEXT',
            'SALARY_CALCULATOR_LOCK_TEXT',
            'SALARY_CALCULATOR_LOCK_DETAIL_TEXT',
            'SALARY_CALCULATOR_CONTACT_TEXT',
            'SALARY_CALCULATOR_CONTACT_DETAIL_TEXT',
            'SC_CONTACT_FEED_BACK_EMAIL_TEXT',
            'BONUS_TEXT',
            'CALCULATION_TEXT',
            'NET_TO_GROSS_TEXT',
            'GROSS_TO_GROSS_TEXT',
            'NON_TAXABLE_INCOME_TEXT',
            'INCOME_TAX_ALLOWANCE_TEXT',
            'TAXABLE_INCOME_STANDARD_RATE_TEXT',
            'TAXABLE_INCOME_CUSTOM_RATE_TEXT',
            'COST_PROJECTION_WITH_SALARY_CALCULATOR_FOOTER_TEXT',
            'EFFECTIVE_INCOME_TAX_RATE_TEXT',
            'TAXABLE_GROSS_TEXT',
            'APPLICABLE_TAX_RATE_NAME_TEXT',
            'APPLICABLE_RATE_TEXT',
            'GROSS_TEXT',
            'TAXABLE_TEXT'
        ];
        $constants = self::find([
            'conditions' => 'name IN ({label_list:array})',
            'bind' => [
                'label_list' => $label_list
            ],
            'cache' => [
                "key" => CacheHelper::__getCacheNameConstantTranslationLanguage('SALARY_CALCULATOR', $language),
                "lifetime" => CacheHelper::__TIME_24H
            ]
        ]);
        $keys = [];
        if (count($constants) > 0) {
            foreach ($constants as $constant) {
                $keys[$constant->getName()] = $constant->getTranslatedValue($language);
            }
        }
        return $keys;
    }

    /**
     * @param $name
     * @param $lang
     */
    public static function __translateConstant($name, $lang = "en")
    {
        $constant = self::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $name
            ],
            'cache' => [
                "key" => CacheHelper::__getCacheNameConstantTranslation($name),
                "lifetime" => CacheHelper::__TIME_24H
            ]
        ]);
        if ($constant) return $constant->getTranslatedValue($lang);
        else return false;
    }

    /**
     * @param $name
     * @param $lang
     */
    public static function __translateConstantWithParams($name, $lang = "en", $params = [])
    {
        $constant = self::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $name
            ],
            'cache' => [
                "key" => CacheHelper::__getCacheNameConstantTranslation($name),
                "lifetime" => CacheHelper::__TIME_24H
            ]
        ]);

        if ($constant) {
            $translatedValue = $constant->getTranslatedValue($lang);
            $input = $translatedValue;
            foreach ($params as $index => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $input = str_replace('{#' . $index . '}', $value, $input);
                }
            }

            return $input;
        }
        else return false;
    }

    /**
     * @param $language
     * @return array
     */
    public static function getSamlInvalidList($language)
    {
        $label_list = [
            'VERIFIED_RELOTALENT_FAILED_TEXT',
            'LOGIN_FAILED_TEXT',
            'RETURN_LOGIN_TEXT',
        ];
        // Translate label
        $constants = self::find([
            'conditions' => 'name IN ({label_list:array})',
            'bind' => [
                'label_list' => $label_list
            ]
        ]);
        $keys = [];
        if (count($constants) > 0) {
            foreach ($constants as $constant) {
                $keys[$constant->getName()] = $constant->getTranslatedValue($language);
            }
        }
        return $keys;
    }
}