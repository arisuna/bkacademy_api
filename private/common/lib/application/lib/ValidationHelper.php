<?php

namespace SMXD\Application\Lib;

use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use SMXD\Application\Lib\Helpers;

class ValidationHelper
{
    /**
     * @param array $input
     * @return array
     */
    public static function __validate($dataInput = [], string $className)
    {
        $fullClassName = "SMXD\Application\Validation\\" . $className;
        $validation = new $fullClassName();
        $messagesValidation = $validation->validate($dataInput);
        if (count($messagesValidation) > 0) {
            $return = [
                'success' => false,
                'detail' => $validation->getMessagesArray(),
                'message' => $validation->getFirstMessage()
            ];
            return $return;
        }
        return ['success' => true];
    }

    /**
     * @return string
     */
    public static function __init(string $className)
    {
        $fullClassName = "SMXD\Application\Validation\\" . $className;
        $validation = new $fullClassName();
        return $validation;
    }


    /**
     * @return string
     */
    public static function __isValid($dataInput = [], $objectValidation)
    {
        $messagesValidation = $objectValidation->validate($dataInput);
        if (count($messagesValidation) > 0) {
            $return = [
                'success' => false,
                'detail' => $objectValidation->getMessagesArray(),
                'message' => $objectValidation->getFirstMessage()
            ];
            return $return;
        }
        return ['success' => true];
    }


    /**
     * @param $object
     * @param $fieldName
     * @param $
     */
    public static function __addField(string $fieldName, $objectValidation, string $condition, string $message)
    {
        if ($condition == 'PresenceOf') {
            $objectValidation->add($fieldName, new PresenceOf([
                'message' => $message
            ]));
        }
        return $objectValidation;
    }


    /**
     * @param $object
     * @param $fieldName
     * @param $
     */
    public static function __addFieldOnUpdate(string $fieldName, $objectValidation, string $condition, string $message)
    {
        if ($condition == 'PresenceOf') {
            $objectValidation->add($fieldName, new Validation\Validator\Callback([
                "callback" => function ($data) use ($fieldName, $message) {
                    if (Helpers::__existCustomValue($fieldName, $data)) {
                        return new PresenceOf([
                            'message' => $message
                        ]);
                    }
                    return true;
                }
            ]));
        }
        return $objectValidation;
    }
}