<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;

class FileNameValidator extends Validator implements ValidatorInterface
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
        $whiteSpace = '\s';
        $underscore = '\_';
        $hyphen = '\-';
        $openParenthese = '\(';
        $closeParenthese = '\)';
        $dot = '\.';

        if (!preg_match('/([a-zA-Z0-9]+)/', $value)) {
            $message = $this->getOption('message');
            if (!$message) {
                $message = 'The filename should countain at least one alphanumeric character';
            }
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        $matchValidator = '/^([\p{L}0-9' . $whiteSpace . $underscore . $hyphen . $openParenthese . $closeParenthese . $dot . '])+$/u';

        if (!preg_match($matchValidator, $value)) {
            $message = $this->getOption('message');
            if (!$message) {
                $message = 'The filename can contain only alphanumeric, underscore, hyphen, dot and white space characters';
            }
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        if ($min = (int)$this->getOption('min')) {
            if (strlen($value) < $min) {
                $messageMin = $this->getOption(
                    'messageMinimum',
                    'The value must contain at least ' . $min . ' characters.'
                );
                $validator->appendMessage(new Message($messageMin, $attribute, 'AlphaNumeric'));
            }
        }
        if ($max = (int)$this->getOption('max')) {
            if (strlen($value) > $max) {
                $messageMax = $this->getOption(
                    'messageMaximum',
                    'The value can contain maximum ' . $max . ' characters.'
                );
                $validator->appendMessage(new Message($messageMax, $attribute, 'AlphaNumeric'));
            }
        }
        if (count($validator)) {
            return false;
        }
        return true;
    }
}