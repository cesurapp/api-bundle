<?php

namespace Cesurapp\ApiBundle\Exception;

use Cesurapp\ApiBundle\AbstractClass\ApiException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Resource Not Found Exception.
 *
 * A resource class that is not registered is a server-side mistake, hence 500.
 */
class ResourceNotFoundException extends ApiException
{
    public function __construct(
        string $message = 'Api Resource Not Found!',
        int $code = 500,
        ConstraintViolationListInterface|array|null $errors = null,
    ) {
        parent::__construct($message, $code, $errors);
    }
}
