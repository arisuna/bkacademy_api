<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;

class InterPositiveValidator extends Validator implements ValidatorInterface
{
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

        if( is_numeric($value) && ctype_digit(strval( $value )) && (int)$value > 0 ){
            return true;
        }else{
            $messageInteger = $this->getOption(
                'message',
                $value. ' - The value should be integer'
            );
            $validator->appendMessage(new Message($messageInteger, $attribute, 'Numeric'));
        }

        if (count($validator)) {
            return false;
        }
        return true;
    }
}