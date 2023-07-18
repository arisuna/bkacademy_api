<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;

class CurrencyValidator extends Validator implements ValidatorInterface
{
    static $currencies = array(
        'AED' =>
            array(
                'code' => 'AED',
                'name' => 'UAE Dirham',
                'active' => '1',
                'principal' => '0',
            ),
        'AMD' =>
            array(
                'code' => 'AMD',
                'name' => 'Armenian Dram',
                'active' => '1',
                'principal' => '0',
            ),
        'ANG' =>
            array(
                'code' => 'ANG',
                'name' => 'Netherlands Antillean Guilder',
                'active' => '1',
                'principal' => '0',
            ),
        'ARS' =>
            array(
                'code' => 'ARS',
                'name' => 'Argentine Peso',
                'active' => '1',
                'principal' => '1',
            ),
        'AUD' =>
            array(
                'code' => 'AUD',
                'name' => 'Australian Dollar',
                'active' => '1',
                'principal' => '1',
            ),
        'BDT' =>
            array(
                'code' => 'BDT',
                'name' => 'Bangladeshi Taka',
                'active' => '0',
                'principal' => '0',
            ),
        'BRL' =>
            array(
                'code' => 'BRL',
                'name' => 'Brazilian Real',
                'active' => '1',
                'principal' => '0',
            ),
        'CAD' =>
            array(
                'code' => 'CAD',
                'name' => 'Canadian Dollar',
                'active' => '1',
                'principal' => '1',
            ),
        'CHE' =>
            array(
                'code' => 'CHE',
                'name' => 'WIR Euro',
                'active' => '1',
                'principal' => '0',
            ),
        'CHF' =>
            array(
                'code' => 'CHF',
                'name' => 'Swiss Franc',
                'active' => '1',
                'principal' => '1',
            ),
        'CHW' =>
            array(
                'code' => 'CHW',
                'name' => 'WIR Franc',
                'active' => '1',
                'principal' => '0',
            ),
        'CNY' =>
            array(
                'code' => 'CNY',
                'name' => 'Chinese Renminbi',
                'active' => '1',
                'principal' => '1',
            ),
        'DJF' =>
            array(
                'code' => 'DJF',
                'name' => 'Djibouti Franc',
                'active' => '1',
                'principal' => '0',
            ),
        'DKK' =>
            array(
                'code' => 'DKK',
                'name' => 'Danish Krone',
                'active' => '1',
                'principal' => '0',
            ),
        'DOP' =>
            array(
                'code' => 'DOP',
                'name' => 'Dominican Peso',
                'active' => '1',
                'principal' => '0',
            ),
        'EGP' =>
            array(
                'code' => 'EGP',
                'name' => 'Egyptian Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'ERN' =>
            array(
                'code' => 'ERN',
                'name' => 'Nakfa',
                'active' => '1',
                'principal' => '0',
            ),
        'ETB' =>
            array(
                'code' => 'ETB',
                'name' => 'Ethiopian Birr',
                'active' => '1',
                'principal' => '0',
            ),
        'EUR' =>
            array(
                'code' => 'EUR',
                'name' => 'Euro',
                'active' => '1',
                'principal' => '1',
            ),
        'FJD' =>
            array(
                'code' => 'FJD',
                'name' => 'Fiji Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'FKP' =>
            array(
                'code' => 'FKP',
                'name' => 'Falkland Islands Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'GBP' =>
            array(
                'code' => 'GBP',
                'name' => 'British Pound',
                'active' => '1',
                'principal' => '1',
            ),
        'GEL' =>
            array(
                'code' => 'GEL',
                'name' => 'Lari',
                'active' => '1',
                'principal' => '0',
            ),
        'GHS' =>
            array(
                'code' => 'GHS',
                'name' => 'Ghana Cedi',
                'active' => '1',
                'principal' => '0',
            ),
        'GIP' =>
            array(
                'code' => 'GIP',
                'name' => 'Gibraltar Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'GMD' =>
            array(
                'code' => 'GMD',
                'name' => 'Dalasi',
                'active' => '1',
                'principal' => '0',
            ),
        'GNF' =>
            array(
                'code' => 'GNF',
                'name' => 'Guinea Franc',
                'active' => '1',
                'principal' => '0',
            ),
        'GTQ' =>
            array(
                'code' => 'GTQ',
                'name' => 'Quetzal',
                'active' => '1',
                'principal' => '0',
            ),
        'GYD' =>
            array(
                'code' => 'GYD',
                'name' => 'Guyana Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'HKD' =>
            array(
                'code' => 'HKD',
                'name' => 'Hong Kong Dollar',
                'active' => '1',
                'principal' => '1',
            ),
        'HNL' =>
            array(
                'code' => 'HNL',
                'name' => 'Lempira',
                'active' => '1',
                'principal' => '0',
            ),
        'HTG' =>
            array(
                'code' => 'HTG',
                'name' => 'Gourde',
                'active' => '1',
                'principal' => '0',
            ),
        'HUF' =>
            array(
                'code' => 'HUF',
                'name' => 'Forint',
                'active' => '1',
                'principal' => '0',
            ),
        'IDR' =>
            array(
                'code' => 'IDR',
                'name' => 'Rupiah',
                'active' => '1',
                'principal' => '0',
            ),
        'ILS' =>
            array(
                'code' => 'ILS',
                'name' => 'New Israeli Sheqel',
                'active' => '1',
                'principal' => '0',
            ),
        'INR' =>
            array(
                'code' => 'INR',
                'name' => 'Indian Rupee',
                'active' => '1',
                'principal' => '0',
            ),
        'IQD' =>
            array(
                'code' => 'IQD',
                'name' => 'Iraqi Dinar',
                'active' => '1',
                'principal' => '0',
            ),
        'IRR' =>
            array(
                'code' => 'IRR',
                'name' => 'Iranian Rial',
                'active' => '1',
                'principal' => '0',
            ),
        'ISK' =>
            array(
                'code' => 'ISK',
                'name' => 'Iceland Krona',
                'active' => '1',
                'principal' => '0',
            ),
        'JMD' =>
            array(
                'code' => 'JMD',
                'name' => 'Jamaican Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'JOD' =>
            array(
                'code' => 'JOD',
                'name' => 'Jordanian Dinar',
                'active' => '1',
                'principal' => '0',
            ),
        'JPY' =>
            array(
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'active' => '1',
                'principal' => '1',
            ),
        'KES' =>
            array(
                'code' => 'KES',
                'name' => 'Kenyan Shilling',
                'active' => '1',
                'principal' => '0',
            ),
        'KGS' =>
            array(
                'code' => 'KGS',
                'name' => 'Som',
                'active' => '1',
                'principal' => '0',
            ),
        'KPW' =>
            array(
                'code' => 'KPW',
                'name' => 'North Korean Won',
                'active' => '1',
                'principal' => '0',
            ),
        'KRW' =>
            array(
                'code' => 'KRW',
                'name' => 'South Korea Won',
                'active' => '1',
                'principal' => '0',
            ),
        'KWD' =>
            array(
                'code' => 'KWD',
                'name' => 'Kuwaiti Dinar',
                'active' => '1',
                'principal' => '0',
            ),
        'KZT' =>
            array(
                'code' => 'KZT',
                'name' => 'Tenge',
                'active' => '1',
                'principal' => '0',
            ),
        'LAK' =>
            array(
                'code' => 'LAK',
                'name' => 'Kip',
                'active' => '1',
                'principal' => '1',
            ),
        'LBP' =>
            array(
                'code' => 'LBP',
                'name' => 'Lebanese Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'LKR' =>
            array(
                'code' => 'LKR',
                'name' => 'Sri Lanka Rupee',
                'active' => '1',
                'principal' => '0',
            ),
        'LRD' =>
            array(
                'code' => 'LRD',
                'name' => 'Liberian Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'LSL' =>
            array(
                'code' => 'LSL',
                'name' => 'Loti',
                'active' => '1',
                'principal' => '0',
            ),
        'LYD' =>
            array(
                'code' => 'LYD',
                'name' => 'Libyan Dinar',
                'active' => '1',
                'principal' => '0',
            ),
        'MAD' =>
            array(
                'code' => 'MAD',
                'name' => 'Moroccan Dirham',
                'active' => '1',
                'principal' => '0',
            ),
        'MDL' =>
            array(
                'code' => 'MDL',
                'name' => 'Moldovan Leu',
                'active' => '1',
                'principal' => '0',
            ),
        'MGA' =>
            array(
                'code' => 'MGA',
                'name' => 'Malagasy Ariary',
                'active' => '1',
                'principal' => '0',
            ),
        'MKD' =>
            array(
                'code' => 'MKD',
                'name' => 'Denar',
                'active' => '1',
                'principal' => '0',
            ),
        'MMK' =>
            array(
                'code' => 'MMK',
                'name' => 'Kyat',
                'active' => '1',
                'principal' => '0',
            ),
        'MNT' =>
            array(
                'code' => 'MNT',
                'name' => 'Tugrik',
                'active' => '1',
                'principal' => '0',
            ),
        'MOP' =>
            array(
                'code' => 'MOP',
                'name' => 'Pataca',
                'active' => '1',
                'principal' => '0',
            ),
        'MRO' =>
            array(
                'code' => 'MRO',
                'name' => 'Ouguiya',
                'active' => '1',
                'principal' => '0',
            ),
        'MUR' =>
            array(
                'code' => 'MUR',
                'name' => 'Mauritius Rupee',
                'active' => '1',
                'principal' => '0',
            ),
        'MVR' =>
            array(
                'code' => 'MVR',
                'name' => 'Rufiyaa',
                'active' => '1',
                'principal' => '0',
            ),
        'MWK' =>
            array(
                'code' => 'MWK',
                'name' => 'Malawi Kwacha',
                'active' => '1',
                'principal' => '0',
            ),
        'MXN' =>
            array(
                'code' => 'MXN',
                'name' => 'Mexican Peso',
                'active' => '1',
                'principal' => '0',
            ),
        'MXV' =>
            array(
                'code' => 'MXV',
                'name' => 'Mexican Unidad de Inversion (UDI)',
                'active' => '1',
                'principal' => '0',
            ),
        'MYR' =>
            array(
                'code' => 'MYR',
                'name' => 'Malaysian Ringgit',
                'active' => '1',
                'principal' => '0',
            ),
        'MZN' =>
            array(
                'code' => 'MZN',
                'name' => 'Mozambique Metical',
                'active' => '1',
                'principal' => '0',
            ),
        'NAD' =>
            array(
                'code' => 'NAD',
                'name' => 'Namibia Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'NGN' =>
            array(
                'code' => 'NGN',
                'name' => 'Naira',
                'active' => '1',
                'principal' => '0',
            ),
        'NIO' =>
            array(
                'code' => 'NIO',
                'name' => 'Cordoba Oro',
                'active' => '1',
                'principal' => '0',
            ),
        'NOK' =>
            array(
                'code' => 'NOK',
                'name' => 'Norwegian Krone',
                'active' => '1',
                'principal' => '0',
            ),
        'NPR' =>
            array(
                'code' => 'NPR',
                'name' => 'Nepalese Rupee',
                'active' => '1',
                'principal' => '0',
            ),
        'NZD' =>
            array(
                'code' => 'NZD',
                'name' => 'New Zealand Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'OMR' =>
            array(
                'code' => 'OMR',
                'name' => 'Rial Omani',
                'active' => '1',
                'principal' => '0',
            ),
        'PAB' =>
            array(
                'code' => 'PAB',
                'name' => 'Balboa',
                'active' => '1',
                'principal' => '0',
            ),
        'PEN' =>
            array(
                'code' => 'PEN',
                'name' => 'Sol',
                'active' => '1',
                'principal' => '0',
            ),
        'PGK' =>
            array(
                'code' => 'PGK',
                'name' => 'Kina',
                'active' => '1',
                'principal' => '0',
            ),
        'PHP' =>
            array(
                'code' => 'PHP',
                'name' => 'Philippine Peso',
                'active' => '1',
                'principal' => '0',
            ),
        'PKR' =>
            array(
                'code' => 'PKR',
                'name' => 'Pakistan Rupee',
                'active' => '1',
                'principal' => '0',
            ),
        'PLN' =>
            array(
                'code' => 'PLN',
                'name' => 'Zloty',
                'active' => '1',
                'principal' => '0',
            ),
        'PYG' =>
            array(
                'code' => 'PYG',
                'name' => 'Guarani',
                'active' => '1',
                'principal' => '0',
            ),
        'QAR' =>
            array(
                'code' => 'QAR',
                'name' => 'Qatari Rial',
                'active' => '1',
                'principal' => '0',
            ),
        'RON' =>
            array(
                'code' => 'RON',
                'name' => 'Romanian Leu',
                'active' => '1',
                'principal' => '0',
            ),
        'RSD' =>
            array(
                'code' => 'RSD',
                'name' => 'Serbian Dinar',
                'active' => '1',
                'principal' => '0',
            ),
        'RUB' =>
            array(
                'code' => 'RUB',
                'name' => 'Russian Ruble',
                'active' => '1',
                'principal' => '1',
            ),
        'RWF' =>
            array(
                'code' => 'RWF',
                'name' => 'Rwanda Franc',
                'active' => '1',
                'principal' => '0',
            ),
        'SAR' =>
            array(
                'code' => 'SAR',
                'name' => 'Saudi Riyal',
                'active' => '1',
                'principal' => '0',
            ),
        'SBD' =>
            array(
                'code' => 'SBD',
                'name' => 'Solomon Islands Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'SCR' =>
            array(
                'code' => 'SCR',
                'name' => 'Seychelles Rupee',
                'active' => '1',
                'principal' => '0',
            ),
        'SDG' =>
            array(
                'code' => 'SDG',
                'name' => 'Sudanese Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'SEK' =>
            array(
                'code' => 'SEK',
                'name' => 'Swedish Krona',
                'active' => '1',
                'principal' => '0',
            ),
        'SGD' =>
            array(
                'code' => 'SGD',
                'name' => 'Singaporean Dollar',
                'active' => '1',
                'principal' => '1',
            ),
        'SHP' =>
            array(
                'code' => 'SHP',
                'name' => 'Saint Helena Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'SLL' =>
            array(
                'code' => 'SLL',
                'name' => 'Leone',
                'active' => '1',
                'principal' => '0',
            ),
        'SOS' =>
            array(
                'code' => 'SOS',
                'name' => 'Somali Shilling',
                'active' => '1',
                'principal' => '0',
            ),
        'SRD' =>
            array(
                'code' => 'SRD',
                'name' => 'Surinam Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'SSP' =>
            array(
                'code' => 'SSP',
                'name' => 'South Sudanese Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'STD' =>
            array(
                'code' => 'STD',
                'name' => 'Dobra',
                'active' => '1',
                'principal' => '0',
            ),
        'SVC' =>
            array(
                'code' => 'SVC',
                'name' => 'El Salvador Colon',
                'active' => '1',
                'principal' => '0',
            ),
        'SYP' =>
            array(
                'code' => 'SYP',
                'name' => 'Syrian Pound',
                'active' => '1',
                'principal' => '0',
            ),
        'SZL' =>
            array(
                'code' => 'SZL',
                'name' => 'Lilangeni',
                'active' => '1',
                'principal' => '0',
            ),
        'THB' =>
            array(
                'code' => 'THB',
                'name' => 'Baht',
                'active' => '1',
                'principal' => '0',
            ),
        'TJS' =>
            array(
                'code' => 'TJS',
                'name' => 'Somoni',
                'active' => '1',
                'principal' => '0',
            ),
        'TMT' =>
            array(
                'code' => 'TMT',
                'name' => 'Turkmenistan New Manat',
                'active' => '1',
                'principal' => '0',
            ),
        'TND' =>
            array(
                'code' => 'TND',
                'name' => 'Tunisian Dinar',
                'active' => '1',
                'principal' => '0',
            ),
        'TOP' =>
            array(
                'code' => 'TOP',
                'name' => 'Pa’anga',
                'active' => '1',
                'principal' => '0',
            ),
        'TRY' =>
            array(
                'code' => 'TRY',
                'name' => 'Turkish Lira',
                'active' => '1',
                'principal' => '0',
            ),
        'TTD' =>
            array(
                'code' => 'TTD',
                'name' => 'Trinidad and Tobago Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'TWD' =>
            array(
                'code' => 'TWD',
                'name' => 'New Taiwan Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'TZS' =>
            array(
                'code' => 'TZS',
                'name' => 'Tanzanian Shilling',
                'active' => '1',
                'principal' => '0',
            ),
        'UAH' =>
            array(
                'code' => 'UAH',
                'name' => 'Hryvnia',
                'active' => '1',
                'principal' => '0',
            ),
        'UGX' =>
            array(
                'code' => 'UGX',
                'name' => 'Uganda Shilling',
                'active' => '1',
                'principal' => '0',
            ),
        'USD' =>
            array(
                'code' => 'USD',
                'name' => 'US Dollar',
                'active' => '1',
                'principal' => '1',
            ),
        'USN' =>
            array(
                'code' => 'USN',
                'name' => 'US Dollar (Next day)',
                'active' => '1',
                'principal' => '0',
            ),
        'UYI' =>
            array(
                'code' => 'UYI',
                'name' => 'Uruguay Peso en Unidades Indexadas (URUIURUI)',
                'active' => '1',
                'principal' => '0',
            ),
        'UYU' =>
            array(
                'code' => 'UYU',
                'name' => 'Peso Uruguayo',
                'active' => '1',
                'principal' => '0',
            ),
        'UZS' =>
            array(
                'code' => 'UZS',
                'name' => 'Uzbekistan Sum',
                'active' => '1',
                'principal' => '0',
            ),
        'VEF' =>
            array(
                'code' => 'VEF',
                'name' => 'Bolívar',
                'active' => '1',
                'principal' => '0',
            ),
        'VND' =>
            array(
                'code' => 'VND',
                'name' => 'Vietnam - Dong',
                'active' => '1',
                'principal' => '0',
            ),
        'VUV' =>
            array(
                'code' => 'VUV',
                'name' => 'Vatu',
                'active' => '1',
                'principal' => '0',
            ),
        'WST' =>
            array(
                'code' => 'WST',
                'name' => 'Tala',
                'active' => '1',
                'principal' => '0',
            ),
        'XAF' =>
            array(
                'code' => 'XAF',
                'name' => 'CFA Franc BEAC',
                'active' => '1',
                'principal' => '0',
            ),
        'XCD' =>
            array(
                'code' => 'XCD',
                'name' => 'East Caribbean Dollar',
                'active' => '1',
                'principal' => '0',
            ),
        'XDR' =>
            array(
                'code' => 'XDR',
                'name' => 'SDR (Special Drawing Right)',
                'active' => '1',
                'principal' => '0',
            ),
        'XOF' =>
            array(
                'code' => 'XOF',
                'name' => 'CFA Franc BCEAO',
                'active' => '1',
                'principal' => '0',
            ),
        'XPF' =>
            array(
                'code' => 'XPF',
                'name' => 'CFP Franc',
                'active' => '1',
                'principal' => '0',
            ),
        'XSU' =>
            array(
                'code' => 'XSU',
                'name' => 'Sucre',
                'active' => '1',
                'principal' => '0',
            ),
        'XUA' =>
            array(
                'code' => 'XUA',
                'name' => 'ADB Unit of Account',
                'active' => '1',
                'principal' => '0',
            ),
        'YER' =>
            array(
                'code' => 'YER',
                'name' => 'Yemeni Rial',
                'active' => '1',
                'principal' => '0',
            ),
        'ZAR' =>
            array(
                'code' => 'ZAR',
                'name' => 'Rand',
                'active' => '1',
                'principal' => '0',
            ),
        'ZMW' =>
            array(
                'code' => 'ZMW',
                'name' => 'Zambian Kwacha',
                'active' => '1',
                'principal' => '0',
            ),
        'ZWL' =>
            array(
                'code' => 'ZWL',
                'name' => 'Zimbabwe Dollar',
                'active' => '1',
                'principal' => '0',
            ),
    );


    /**
     * Executes the validation. Allowed options:
     * 'whiteSpace' : allow white spaces;
     * 'underscore' : allow underscores;
     * 'hyphen' : allow hyphen only;
     * 'min' : input value must not be shorter than it;
     * 'max' : input value must not be longer than it.
     *
     * @param  Validation $validator
     * @param  string $attribute
     *
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $value = $validator->getValue($attribute);

        if (is_null($value) || $value === '') {
            return true;
        }

        if (is_string($value) && isset(self::$currencies[$value])) {
            return true;
        } else {
            $messageInteger = $this->getOption(
                'message'
            );
            $validator->appendMessage(new Message($messageInteger, $attribute, 'Numeric'));
        }

        if (count($validator)) {
            return false;
        }
        return true;
    }
}