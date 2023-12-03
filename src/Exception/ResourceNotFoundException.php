<?php

namespace Cesurapp\ApiBundle\Exception;

use Cesurapp\ApiBundle\AbstractClass\AbstractApiException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Resource Not Found Exception.
 */
class ResourceNotFoundException extends AbstractApiException
{
    public function __construct(
        string $message = 'Api Resource Not Found!',
        int $code = 424,
        ConstraintViolationListInterface|array $errors = null
    ) {
        parent::__construct($message, $code, $errors);
    }
}
