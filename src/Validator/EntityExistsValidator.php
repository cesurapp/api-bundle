<?php

namespace Cesurapp\ApiBundle\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class EntityExistsValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntityExists) {
            throw new UnexpectedTypeException($constraint, EntityExists::class);
        }

        if (!$value) {
            return;
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            $this->context->addViolation($constraint->message);

            return;
        }

        // findOneBy: LIMIT 1, instead of hydrating every matching row
        $repo = $this->entityManager->getRepository($constraint->entityClass); // @phpstan-ignore-line
        $result = $repo->findOneBy([$constraint->colName => $value]);
        if (null === $result) {
            $this->context->addViolation($constraint->message);

            return;
        }

        $object = $this->context->getObject();
        $property = $this->context->getPropertyName();
        if (is_object($object) && $property) {
            $object->{$property} = $result;
        }
    }
}
