<?php

namespace Cesurapp\ApiBundle\Validator;

use libphonenumber\NumberParseException;
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
            throw new UnexpectedTypeException($constraint, UniqueEntity::class);
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

        $validTypes = array_unique($validTypes);

        if (0 < \count($validTypes)) {
            $type = $util->getNumberType($phoneNumber);

            if (!\in_array($type, $validTypes, true)) {
                $this->addViolation($value, $constraint);
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
                dump($object);
                throw new \LogicException('The current validation does not concern an object');
            }

            $defaultRegion = $object->{$path};
        }

        return $defaultRegion ?? $constraint->defaultRegion;
    }
}
