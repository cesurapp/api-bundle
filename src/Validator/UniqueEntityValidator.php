<?php

namespace Cesurapp\ApiBundle\Validator;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * UniqueEntity for Single|Multiple Column.
 */
class UniqueEntityValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntity) {
            throw new UnexpectedTypeException($constraint, UniqueEntity::class);
        }

        if (!$value) {
            return;
        }

        $fields = !is_array($constraint->fields) ? [$constraint->fields] : $constraint->fields;
        $criteria = Criteria::create();
        foreach ($fields as $columnName => $field) {
            if (!is_numeric($columnName)) {
                if (is_array($field)) {
                    $criteria->andWhere(Criteria::expr()->in($columnName, $field));
                } else {
                    $criteria->andWhere(Criteria::expr()->eq($columnName, $this->context->getObject()->{$field}));
                }
            } else {
                $criteria->andWhere(Criteria::expr()->eq($field, $this->context->getObject()->{$field}));
            }
        }

        // Edit Mode Exclude ID
        if ($constraint->editField && isset($this->context->getObject()->{$constraint->editField})) {
            $criteria->andWhere(Criteria::expr()->neq(
                $constraint->editColumn ?? $constraint->editField,
                $this->context->getObject()->{$constraint->editField}
            ));
        }

        $repo = $this->entityManager->getRepository($constraint->entityClass); // @phpstan-ignore-line
        if ($repo->matching($criteria)->count() > 0) {
            $this->context->addViolation($constraint->message);
        }
    }
}
