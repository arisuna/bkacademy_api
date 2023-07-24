<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\DependantExt;
use SMXD\Application\Models\DocumentTypeExt;

class CustomEmailValidator extends Validator implements ValidatorInterface
{
    /**
     * @param Validation $validator
     * @param string $attribute
     * @return bool
     */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $value = $validator->getValue($attribute);
        $allowEmpty = $this->getOption('allowEmpty');
        if ($allowEmpty == true && Helpers::__isNull($value)) {
            return true;
        }

        if (!is_string($value)) {
            $message = $this->getOption('message');
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }

        if (!Helpers::__isEmail($value)) {
            $message = $this->getOption('message');
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }

        if (count($validator)) {
            return false;
        }

        return true;
    }
}