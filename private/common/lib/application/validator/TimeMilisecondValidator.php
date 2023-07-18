<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use Reloday\Application\Lib\Helpers;

class TimeMilisecondValidator extends Validator implements ValidatorInterface
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

        if (is_numeric($value) && Helpers::__isTimeMiliSecond($value)) {
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