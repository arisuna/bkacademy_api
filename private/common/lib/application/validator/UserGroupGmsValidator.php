<?php

namespace Reloday\Application\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Message;
use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\DependantExt;
use Reloday\Application\Models\DocumentTypeExt;
use Reloday\Application\Models\UserGroupExt;

class UserGroupGmsValidator extends Validator implements ValidatorInterface
{
    /**
     * @param Validation $validator
     * @param string $attribute
     * @return bool
     */
    public function validate(\Phalcon\Validation $validator, $attribute)
    {
        $userGroupId = $validator->getValue($attribute);
        $allowEmpty = $this->getOption('allowEmpty');
        $message = $this->getOption('message');
        if ($allowEmpty == true && Helpers::__isNull($userGroupId)) {
            return true;
        }
        $gmsRoleIds = UserGroupExt::__getGmpRoleIds();

        if (in_array($userGroupId, $gmsRoleIds)) {
            return true;
        } else {
            $validator->appendMessage(new Message($message, $attribute));
            return false;
        }
    }
}