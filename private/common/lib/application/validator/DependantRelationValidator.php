<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\DependantExt;

class DependantRelationValidator extends Validator implements ValidatorInterface
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

        if(!is_numeric($value)) {
            $message = $this->getOption('message');
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }

        $item = array_filter(DependantExt::$dependant_relations, function ($val, $key) use ($value) {
            return is_array($val) && $val['code'] == $value;
        }, ARRAY_FILTER_USE_BOTH);
        if (is_array($item) && sizeof($item) > 0) {
            $item = reset($item);
        }else{
            $item = null;
        }

        if(!$item){
            $message = $this->getOption('message');
            $validator->appendMessage(new Message($message, $attribute));
        }else{
            return true;
        }

        if (count($validator)) {
            return false;
        }
    }
}