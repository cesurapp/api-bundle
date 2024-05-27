<?php

namespace Cesurapp\ApiBundle\Validator;

use Doctrine\Common\Collections\Criteria;
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

        $criteria = Criteria::create()->where(Criteria::expr()->eq($constraint->colName, $value));
        $repo = $this->entityManager->getRepository($constraint->entityClass); // @phpstan-ignore-line
        if (!$result = $repo->matching($criteria)->first()) {
            $this->context->addViolation($constraint->message);
        }

        $this->context->getObject()->{$this->context->getPropertyName()} = $result;
    }
}
