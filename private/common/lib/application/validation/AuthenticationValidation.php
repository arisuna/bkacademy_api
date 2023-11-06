<?php

namespace SMXD\Application\Validation;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email;
use Phalcon\Validation\Validator\Callback;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\PasswordStrength;
use Phalcon\Validation\Validator\AlphaNamesValidator;
use Phalcon\Validation\Validator\StringLength;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Traits\ValidationTraits;
use SMXD\Application\Validator\CountryIsoCodeValidator;
use SMXD\Application\Validator\DependantRelationValidator;
use SMXD\Application\Validator\NameValidator;
use SMXD\Application\Validator\PhoneValidator;
use Phalcon\Validation\Validator\Date as DateValidator;
use Phalcon\Validation\Validator\Url as UrlValidator;
use SMXD\Application\Validator\SexValidator;
use SMXD\Application\Validator\UuidValidator;

class AuthenticationValidation extends Validation
{
    use ValidationTraits;

    public function initialize()
    {
        $this->add('phone', new \SMXD\Application\Validator\PhoneValidator([
            'message' => 'PHONE_NUMBER_INVALID_TEXT',
            'allowEmpty' => false,
        ]));

        $this->add('code', new Validation\Validator\OtpValidator([
            'message' => 'OTP_INVALID_TEXT',
            'allowEmpty' => false,
        ]));
    }
}

