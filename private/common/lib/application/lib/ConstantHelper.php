<?php

namespace SMXD\Application\Lib;

use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\Constant;
use SMXD\Application\Models\ConstantExt;
use SMXD\Application\Models\SupportedLanguageExt;

class ConstantHelper
{
    const NOT_FOUND = 'DATA_NOT_FOUND_TEXT';

    static $constants = [];

    /**
     * ConstantHelper constructor.
     */
    public function __construct()
    {
        $this->constants = include __DIR__ . '/../constant/constant.php';
    }

    /**
     * @param $name
     * @param $params = array('message', 'object');
     */
    public static function __message($lang, $name, $params)
    {
        $values = include __DIR__ . '/../constant/constant.php';

        $content = isset($values[$lang]) &&
        isset($values[$lang][$name]) ? $values[$lang][$name] : "";

        $message = isset($params['message']) ? $params['message'] : false;
        $object = isset($params['object']) ? $params['object'] : false;
        $profile = isset($params['profile']) ? $params['profile'] : false;

        if ($message) {

            if (isset($message['name'])) {
                $content = str_replace('{#name}', $message['name'], $content);
            } elseif (isset($message['username'])) {
                $content = str_replace('{#name}', $message['username'], $content);
            } elseif (isset($message['user_name'])) {
                $content = str_replace('{#name}', $message['user_name'], $content);
            }

            if (isset($message['number'])) {
                $content = str_replace('{#number}', $message['number'], $content);
                $number = $message['number'];
            } elseif (isset($message['reference'])) {
                $content = str_replace('{#number}', $message['reference'], $content);
                $number = $message['reference'];
            } elseif (isset($message['identify'])) {
                $content = str_replace('{#identify}', $message['identify'], $content);
                $number = $message['identify'];
            } else {
                $number = "";
            }

            if (isset($message['time']) && is_numeric($message['time'])) {
                $content = str_replace('{#time}', date('d/m/Y H:i', intval($message['time'])), $content);
            }

            if (isset($message['object_label'])) {
                $object = self::simple($lang, $message['object_label'], $values);
                $content = str_replace('{#object}', $object . " " . $number, $content);
            }
        }
        if ($object) {

            $method = "getNumber";
            if (method_exists($object, $method)) {
                $content = str_replace('{#number}', $object->$method(), $content);
            }

            $method = "getIdentify";
            if (method_exists($object, $method)) {
                $content = str_replace('{#number}', $object->$method(), $content);
            }

            $method = "getReference";
            if (method_exists($object, $method)) {
                $content = str_replace('{#number}', $object->$method(), $content);
            }
        }


        return $content;

    }

    /**
     * @param $lang
     * @param $name
     * @return string
     */
    public static function __translate($name, $lang = SupportedLanguageExt::LANG_EN)
    {
        $content = ConstantExt::__translateConstant($name, $lang);
        if(empty($content)){
            return $name;
        }
        return $content;
    }

    /**
     * @param $lang
     * @param $name
     * @return string
     */
    public static function __translateForPresto($name, $lang = SupportedLanguageExt::LANG_EN)
    {
        $content = str_replace("'", "''", ConstantExt::__translateConstant($name, $lang));
        return $content;
    }

    /**
     * @param $lang
     * @param $name
     * @return string
     */
    public static function __simple($lang, $name, $values = array())
    {
        $content = isset($values[$lang]) && isset($values[$lang][$name]) ? $values[$lang][$name] : "";
        return $content;
    }
}

?>