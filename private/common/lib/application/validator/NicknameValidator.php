<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;

class NicknameValidator extends Validator implements ValidatorInterface
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
        $dot = '\.';
        $max = 64;
        $min = 6;
        $match = '/^([\p{L}0-9' . $dot . '])+$/u';
        $match = '/^([a-z0-9' . $dot . ']+)$/';

        if (!preg_match($match, $value)) {
            $message = $this->getOption('message');
            if (!$message) {
                if ($dot) {
                    $message = 'The url can contain only alphanumeric and dot characters';
                } else {
                    $message = 'The url can contain only alphanumeric characters';
                }
            }
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }
        
        if (strlen($value) < $min) {
            $message = 'The nickname must contain at least ' . $min . ' characters.';
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        if (strlen($value) > $max) {
            $message = 'The value can contain maximum ' . $max . ' characters.';
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        if (count($validator)) {
            return false;
        }
        return true;
    }
}