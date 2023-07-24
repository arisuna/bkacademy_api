<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use SMXD\Application\Lib\Helpers;

class TimeSecondValidator extends Validator implements ValidatorInterface
{
    /**
     *  Executes the validation. Allowed options:
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

        $allowEmpty = $this->getOption('allowEmpty');
        if ($allowEmpty == true && Helpers::__isNull($value)) {
            return true;
        }

        if (is_numeric($value) && Helpers::__isTimeSecond($value)) {
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
    }
}