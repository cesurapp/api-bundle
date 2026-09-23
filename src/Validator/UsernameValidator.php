<?php

namespace Cesurapp\ApiBundle\Validator;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Identity Validator (Email-Phone).
 */
class UsernameValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Username) {
            throw new UnexpectedTypeException($constraint, Username::class);
        }

        // Check Email
        if (!is_numeric($value)) {
            $errors = $this->context->getValidator()->validate($value, new Email());
            if ($errors->count() > 0) {
                $this->context->addViolation($errors->get(0)->getMessage());
            }

            return;
        }

        // Check Number (a numeric value may arrive as int)
        $value = (string) $value;
        if (strlen($value) < 8) {
            $this->context->addViolation('Please enter a valid phone number');

            return;
        }

        $util = PhoneNumberUtil::getInstance();
        // Parse Region (too long / not a number is the user's input, not a server error)
        try {
            $parse = $util->parse(str_starts_with($value, '+') ? $value : '+'.$value);
        } catch (NumberParseException) {
            $this->context->addViolation('Please enter a valid phone number');

            return;
        }

        if (!$parse->getCountryCode()) {
            $this->context->addViolation('Please enter a valid phone number');

            return;
        }

        $region = $util->getRegionCodeForCountryCode($parse->getCountryCode());
        $this->context->getValidator()
            ->inContext($this->context)
            ->validate($value, new PhoneNumber(defaultRegion: $region));
    }
}
