<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\DependantExt;

class SexValidator extends Validator implements ValidatorInterface
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


        if (is_numeric($value)) {
            if ($value == -1 || $value == 1 || $value == 0) {
                return true;
            }else{
                $validator->appendMessage(new Message($message, $attribute));
                return false;
            }
        }

        if (is_string($value)) {
            if ($value != 'M' && $value != 'F' && $value != 'O') {
                $validator->appendMessage(new Message($message, $attribute));
                return false;
            }
        }

        return true;
    }
}