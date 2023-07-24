<?php

namespace SMXD\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\DependantExt;
use SMXD\Application\Models\DocumentTypeExt;

class DocumentTypeValidator extends Validator implements ValidatorInterface
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

        if (!is_numeric($value)) {
            $message = $this->getOption('message');
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }

        $documentTypes = DocumentTypeExt::getAll();
        if (is_object($documentTypes) && method_exists($documentTypes, 'toArray')) {
            $documentTypes = $documentTypes->toArray();
        }

        $item = array_filter($documentTypes, function ($val, $key) use ($value) {
            return is_array($val) && $val['id'] == $value;
        }, ARRAY_FILTER_USE_BOTH);
        if (is_array($item) && sizeof($item) > 0) {
            $item = reset($item);
        } else {
            $item = null;
        }

        if (!$item) {
            $message = $this->getOption('message');
            $validator->appendMessage(new Message($message, $attribute));
        } else {
            return true;
        }

        if (count($validator)) {
            return false;
        }
    }
}