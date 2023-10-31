<?php

namespace SMXD\Application\Validation;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email;
use Phalcon\Validation\Validator\Callback;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\PasswordStrength;
use Phalcon\Validation\Validator\AlphaNamesValidator;
use Phalcon\Validation\Validator\StringLength;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\DependantExt;
use Reloday\Application\Traits\ValidationTraits;
use Reloday\Application\Validator\CountryIsoCodeValidator;
use Reloday\Application\Validator\DependantRelationValidator;
use Reloday\Application\Validator\NameValidator;
use Reloday\Application\Validator\PhoneValidator;
use Phalcon\Validation\Validator\Date as DateValidator;
use Phalcon\Validation\Validator\Url as UrlValidator;
use Reloday\Application\Validator\SexValidator;
use Reloday\Application\Validator\UuidValidator;

class AuthenticationValidation extends Validation
{
    use ValidationTraits;

    public function initialize()
    {
        $this->add('phone', new \SMXD\Application\Validator\PhoneValidator([
            'message' => 'PHONE_NUMBER_INVALID_TEXT',
            'allowEmpty' => false,
        ]));

        $this->add('otp', new Validation\Validator\OtpValidator([
            'message' => 'OTP_INVALID_TEXT',
            'allowEmpty' => false,
        ]));
    }
}

