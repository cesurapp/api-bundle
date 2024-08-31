<?php

namespace Cesurapp\ApiBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class EntityExists extends Constraint
{
    public function __construct(
        public string $entityClass,
        public string|array $colName,
        public string $message = 'No such value was found!',
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);
    }
}
