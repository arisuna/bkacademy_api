<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use SMXD\Application\Lib\Helpers;

class PhoneValidator extends Validator implements ValidatorInterface
{
    /**
     * Executes the validation. Allowed options:
     * 'whiteSpace' : allow white spaces;
     * 'underscore' : allow underscores;
     * 'hyphen' : allow hyphen only;
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
        $whiteSpace = '\s';
        $hyphen = '-';
        $dot = '\.';
        $plus = '\+';
        $parenthese = '\(\)';

        $allowEmpty = $this->getOption('allowEmpty');
        if ($allowEmpty == true && Helpers::__isNull($value)) {
            return true;
        }

        if (!preg_match('/^([0-9' . $whiteSpace . $hyphen . $dot . $plus.  $parenthese .'])+$/u', $value)) {
            $message = $this->getOption('message');
            if (!$message) {
                $message = 'The phone number can contain only numeric, hyphen, dot and white space characters';
            }
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        if (strlen($value) < 6) {
            $message = $this->getOption(
                'messageMinimum',
                'The phone number must contain at least 6 characters.'
            );
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        if (strlen($value) >= 20) {
            $message = $this->getOption(
                'messageMaximum',
                'The phone number must contain maximum 20 characters.'
            );
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }
        if (is_array($validator) && count($validator) > 0) {
            return false;
        }
        return true;
    }
}