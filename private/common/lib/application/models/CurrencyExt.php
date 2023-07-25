<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Security;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Traits\ModelTraits;

class CurrencyExt extends Currency
{
    use ModelTraits;

    const CURRENCY_ACTIVE = 1;
    const CURRENCY_DESACTIVE = 0;
    const CURRENCY_PRINCIPAL = 1;
    const CURRENCY_NOT_PRINCIPAL = 0;

    /**
     * @return mixed
     */
    public static function getAll()
    {
        return self::__findFirstWithCache([
            'conditions' => 'active = :active_yes:',
            'bind' => [
                'active_yes' => self::CURRENCY_ACTIVE
            ]
        ], CacheHelper::__TIME_24H);
    }
}