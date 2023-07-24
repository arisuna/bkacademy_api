<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\DependantExt;
use SMXD\Application\Models\DocumentTypeExt;

class UuidValidator extends Validator implements ValidatorInterface
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
        $message = $this->getOption('message');
        if ($allowEmpty == true && Helpers::__isNull($value)) {
            return true;
        }

        if (!is_string($value)) {
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }

        if (Helpers::__isValidUuid($value) == false) {
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        } else {
            return true;
        }
    }
}