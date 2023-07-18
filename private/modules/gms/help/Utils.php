<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/16
 * Time: 10:50
 */

namespace Reloday\Gms\Help;

class Utils
{
    static function format4DigitNumber($input)
    {

        $value = str_pad($input, 4, '0', STR_PAD_LEFT);

        return $value;
    }

    /**
     * Get config discount value
     * @return array
     */
    static function discountValue()
    {
        return [
            1 => [
                'id' => 1,
                'name' => 'Discount 1',
                'value' => 10
            ],
            2 => [
                'id' => 2,
                'name' => 'Discount 2',
                'value' => 15
            ],
            3 => [
                'id' => 3,
                'name' => 'Discount 3',
                'value' => 20]
        ];
    }

    static function _decodeUTF8($text)
    {
        return iconv('utf-8', 'cp1258//IGNORE', $text);
    }
}