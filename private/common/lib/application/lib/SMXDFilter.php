<?php

namespace SMXD\Application\Lib;

use Phalcon\Filter;

class SMXDFilter
{
    /**
     * @return Filter
     */
    public static function __getFilter(){
        $filter = new Filter();
        $filter->add(
            'text',
            function ($value) {
                return filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS);
                //return preg_replace('/[^A-Za-z0-9\-\ ]/', '', $value);
            }
        );
        $filter->add(
            'alphanum_space',
            function ($value) {
                //return filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS);
                return preg_replace("/[^a-zA-Z0-9_\-\s]+/i", "", $value);
            }
        );
        return $filter;
    }
}

?>