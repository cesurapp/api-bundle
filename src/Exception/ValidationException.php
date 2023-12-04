<?php

namespace Cesurapp\ApiBundle\Exception;

use Cesurapp\ApiBundle\AbstractClass\ApiException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Form Validation Exception.
 */
class ValidationException extends ApiException
{
    public function __construct(
        string $message = 'Validation failed',
        int $code = 422,
        protected array|ConstraintViolationListInterface|null $errors = null
    ) {
        if ($errors instanceof ConstraintViolationListInterface) {
            $errors = $this->parseErrors($errors);
        }

        parent::__construct($message, $code, $errors);
    }

    private function parseErrors(ConstraintViolationListInterface $errors): array
    {
        $fields = [];

        foreach ($errors as $error) {
            $fields[$error->getPropertyPath()][] = $error->getMessage();
        }

        return $fields;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }
}
