<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use Reloday\Application\Lib\EmailHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\DependantExt;
use Reloday\Application\Models\DocumentTypeExt;

class EmailUniqueValidator extends Validator implements ValidatorInterface
{
    /**
     * @param Validation $validator
     * @param string $attribute
     * @return bool
     */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $value = $validator->getValue($attribute);
        $message = $this->getOption('message');

        if (Helpers::__isEmail($value) == false) {
            $message = 'Email not valid';
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }

        if (EmailHelper::__isAvailable($value) == false) {
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }
    }
}