<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use SMXD\Application\Lib\Helpers;

class UnixDateRangeValidator extends Validator implements ValidatorInterface
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
        $message = $this->getOption('message');
        if ($message == '') $message = 'Date Range invalid';

        $allowEmpty = $this->getOption('allowEmpty');
        if ($allowEmpty == true && Helpers::__isNull($value)) {
            return true;
        }

        if (is_array($value) && isset($value['from']) && isset($value['to']) && Helpers::__isTimeSecond($value['from']) && Helpers::__isTimeSecond($value['to'])) {
            return true;
        } else {

            $validator->appendMessage(new Message($message, $attribute));
        }

        if (count($validator)) {
            return false;
        }
    }
}