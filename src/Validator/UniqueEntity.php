<?php

namespace Cesurapp\ApiBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueEntity extends Constraint
{
    public function __construct(
        public string $entityClass,
        public string|array $fields,
        public ?string $editField = null,
        public ?string $editColumn = null,
        public string $message = 'This value is already used.',
        mixed $options = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
    }
}
