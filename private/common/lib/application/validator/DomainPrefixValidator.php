<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;

class DomainPrefixValidator extends Validator implements ValidatorInterface
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
        $whiteSpace = (bool)$this->getOption('whiteSpace');
        $whiteSpace = $whiteSpace ? '\s' : '';
        $underscore = (bool)$this->getOption('underscore');
        $underscore = $underscore ? '_' : '';
        $hyphen = (bool)$this->getOption('hyphen');
        $hyphen = $hyphen ? '-' : '';

        if (!preg_match('/^([\p{L}0-9' . $whiteSpace . $underscore . $hyphen . '])+$/u', $value)) {
            $message = $this->getOption('message');
            if (!$message) {
                if ($whiteSpace && $underscore) {
                    $message = 'The value can contain only alphanumeric, underscore and white space characters';
                } elseif ($whiteSpace) {
                    $message = 'The value can contain only alphanumeric and white space characters';
                } elseif ($underscore) {
                    $message = 'The value can contain only alphanumeric and underscore characters';
                } elseif ($hyphen) {
                    $message = 'The value can contain only alphanumeric and hyphen characters';
                } else {
                    $message = 'The value can contain only alphanumeric characters';
                }
            }
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }
        if ($min = (int)$this->getOption('min')) {
            if (strlen($value) < $min) {
                $message = $this->getOption('message');
                if (!$message) {
                    $messageMin = $this->getOption(
                        'messageMinimum',
                        'The value must contain at least ' . $min . ' characters.'
                    );
                    $validator->appendMessage(new Message($messageMin, $attribute, 'AlphaNumeric'));
                }else{
                    $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
                }
            }
        }
        if ($max = (int)$this->getOption('max')) {

            if (strlen($value) > $max) {
                $message = $this->getOption('message');
                if (!$message) {
                    $messageMax = $this->getOption(
                        'messageMaximum',
                        'The value can contain maximum ' . $max . ' characters.'
                    );
                    $validator->appendMessage(new Message($messageMax, $attribute, 'AlphaNumeric'));
                }else{
                    $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
                }
            }
        }
        if (is_array($validator) && count($validator)) {
            return false;
        }
        return true;
    }
}