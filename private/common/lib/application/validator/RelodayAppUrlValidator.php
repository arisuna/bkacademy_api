<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;

class RelodayAppUrlValidator extends Validator implements ValidatorInterface
{
    static $not_allowed_prefixs = [
        'api', 'google', 'yahoo', 'twitter', 'cloud', 'thinhdev', 'dev', 'app', 'cloud', 'data', 'static', 'images', 'email',
        'amazon', 'aws', 'relotalent', 'reloday', 'production', 'stagging', 'staging', 'preprod', 'gmail', 'mail', 'mp3', 'application',
        'aws', 'gov', 'reloday', 'usa'
    ];

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
        $hyphen = '-';
        $max = 64;
        $min = 3;

        if (!preg_match('/^([\p{L}0-9' . $hyphen . '])+$/u', $value)) {
            $message = $this->getOption('message');
            if (!$message) {
                if ($hyphen) {
                    $message = 'The url can contain only alphanumeric and hyphen characters';
                } else {
                    $message = 'The url can contain only alphanumeric characters';
                }
            }
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        if (in_array($value, self::$not_allowed_prefixs)) {
            $message = 'The url is not allowed';
            $validator->appendMessage(new Message($message, $attribute, 'AlphaNumeric'));
        }

        if (strlen($value) < $min) {
            $message = 'The url must contain at least ' . $min . ' characters.';
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