<?php

namespace Cesurapp\ApiBundle\Validator;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Phone number validator.
 */
class PhoneNumberValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PhoneNumber) {
            throw new UnexpectedTypeException($constraint, PhoneNumber::class);
        }

        if (!$value || !is_string($value)) {
            return;
        }

        $util = PhoneNumberUtil::getInstance();
        try {
            $phoneNumber = $util->parse($value, $this->getRegion($constraint));
        } catch (NumberParseException) {
            $this->addViolation($value, $constraint);

            return;
        }

        // Validate Number
        if (!$util->isValidNumber($phoneNumber)) {
            $this->addViolation($value, $constraint);

            return;
        }

        // Check Number Type
        $validTypes = [];
        foreach ($constraint->types as $type) {
            switch ($type) {
                case PhoneNumber::FIXED_LINE:
                    $validTypes[] = PhoneNumberType::FIXED_LINE;
                    $validTypes[] = PhoneNumberType::FIXED_LINE_OR_MOBILE;
                    break;
                case PhoneNumber::MOBILE:
                    $validTypes[] = PhoneNumberType::MOBILE;
                    $validTypes[] = PhoneNumberType::FIXED_LINE_OR_MOBILE;
                    break;
                case PhoneNumber::PAGER:
                    $validTypes[] = PhoneNumberType::PAGER;
                    break;
                case PhoneNumber::PERSONAL_NUMBER:
                    $validTypes[] = PhoneNumberType::PERSONAL_NUMBER;
                    break;
                case PhoneNumber::PREMIUM_RATE:
                    $validTypes[] = PhoneNumberType::PREMIUM_RATE;
                    break;
                case PhoneNumber::SHARED_COST:
                    $validTypes[] = PhoneNumberType::SHARED_COST;
                    break;
                case PhoneNumber::TOLL_FREE:
                    $validTypes[] = PhoneNumberType::TOLL_FREE;
                    break;
                case PhoneNumber::UAN:
                    $validTypes[] = PhoneNumberType::UAN;
                    break;
                case PhoneNumber::VOIP:
                    $validTypes[] = PhoneNumberType::VOIP;
                    break;
                case PhoneNumber::VOICEMAIL:
                    $validTypes[] = PhoneNumberType::VOICEMAIL;
                    break;
            }
        }

        // No array_unique(): the types are ints in libphonenumber 8 and enum cases in 9, which it cannot compare
        if (0 < \count($validTypes)) {
            $type = $util->getNumberType($phoneNumber);

            if (!\in_array($type, $validTypes, true)) {
                $this->addViolation($value, $constraint);

                return;
            }
        }

        // With a format, the validated property then holds the number in it, as EntityExists's holds the entity
        if (null !== $constraint->format) {
            $object = $this->context->getObject();
            $property = $this->context->getPropertyName();
            if (\is_object($object) && $property) {
                // A FORMAT_* name stands for libphonenumber's constant of that name; theirs (an int in 8, an enum case in 9) as is
                $format = \is_string($constraint->format)
                    ? \constant(PhoneNumberFormat::class.'::'.strtoupper($constraint->format))
                    : $constraint->format;
                $object->{$property} = $util->format($phoneNumber, $format);
            }
        }
    }

    private function addViolation(string $value, PhoneNumber $constraint): void
    {
        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ types }}', implode(', ', $constraint->types))
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setCode('ca23f4ca-38f4-4325-9bcc-eb570a4abe7f')
            ->addViolation();
    }

    private function getRegion(PhoneNumber $constraint): ?string
    {
        $defaultRegion = null;

        if (null !== $path = $constraint->regionPath) {
            $object = $this->context->getObject();
            if (null === $object) {
                throw new \LogicException('The current validation does not concern an object');
            }

            // An unset (typed, uninitialized) region property means "no region", not an Error
            $defaultRegion = isset($object->{$path}) ? $object->{$path} : null;
        }

        return $defaultRegion ?? $constraint->defaultRegion;
    }
}
