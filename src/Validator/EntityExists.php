<?php

namespace Cesurapp\ApiBundle\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * The value must match an entity on $colName. When it does, the property is replaced by the entity.
 */
#[\Attribute]
class EntityExists extends Constraint
{
    #[HasNamedArguments]
    public function __construct(
        public string $entityClass,
        public string $colName,
        public string $message = 'No such value was found!',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
