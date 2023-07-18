<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\DependantExt;
use Reloday\Application\Models\DocumentTypeExt;
use Reloday\Application\Models\SupportedLanguageExt;

class LanguageIso2Validator extends Validator implements ValidatorInterface
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

        if (in_array($value, SupportedLanguageExt::$languages)) {
            return true;
        } else {
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }
    }
}