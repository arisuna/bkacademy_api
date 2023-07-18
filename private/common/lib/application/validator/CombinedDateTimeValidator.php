<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use Reloday\Application\Lib\Helpers;

class CombinedDateTimeValidator extends Validator implements ValidatorInterface
{
    /**
     *  Executes the validation. Allowed options:
     * 'min' : input value must not be shorter than it;
     * 'max' : input value must not be longer than it.
     *
     * @param Validation $validator
     * @param string $attribute
     *
     * @return boolean
     */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $value = $validator->getValue($attribute);
        $allowEmpty = $this->getOption('allowEmpty');
        $message = $this->getOption('message');
        if ($allowEmpty == true && Helpers::__isNull($value)) {
            return true;
        }

        if (is_numeric($value) && Helpers::__isTimeSecond($value)) {
            return true;
        } else if (is_string($value)) {
            if (Helpers::__isDate($value, "Y-m-d") == false && Helpers::__isDate($value, "Y-m-d H:i:s") == false) {
                $validator->appendMessage(new Message($message, $attribute));
            }
        } else {
            $validator->appendMessage(new Message($message, $attribute));
        }

        if (count($validator)) {
            return false;
        }
    }
}