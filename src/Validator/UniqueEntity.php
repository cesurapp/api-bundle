<?php

namespace Cesurapp\ApiBundle\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueEntity extends Constraint
{
    /**
     * Checks before the write, so two concurrent requests can both pass: keep a unique index on
     * the column(s) as the real guarantee.
     */
    #[HasNamedArguments]
    public function __construct(
        public string $entityClass,
        public string|array $fields,
        public ?string $editField = null,
        public ?string $editColumn = null,
        public string $message = 'This value is already used.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
