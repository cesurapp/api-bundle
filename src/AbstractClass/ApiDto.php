<?php

namespace Cesurapp\ApiBundle\AbstractClass;

use Cesurapp\ApiBundle\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Data Transfer Object for Validation.
 */
#[\AllowDynamicProperties]
abstract class ApiDto
{
    protected bool $auto = true;

    protected array $validated = [];

    protected ConstraintViolationListInterface $constraints;

    public function __construct(protected Request $request, protected ValidatorInterface $validator)
    {
        $this->constraints = new ConstraintViolationList();
        $this->initProperties([
            ...$this->request->query->all(),
            ...$this->request->request->all(),
            ...$this->request->files->all(),
        ]);

        // Append ID for Edit Request
        if ($this->request->isMethod('PUT')) {
            $this->id = $this->request->attributes->get('id'); // @phpstan-ignore-line
        }

        // Run Validate
        if ($this->auto) {
            $this->validate(true);
        }
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Validate DTO Request.
     *
     * @throws ValidationException
     */
    final public function validate(bool $throw = true): bool
    {
        // Start Validated
        $this->beforeValidated();

        // Validate
        $constraints = $this->validator->validate($this, groups: ['Default']);
        $constraints->addAll($this->constraints);
        if ($constraints->count()) {
            if (!$throw) {
                return false;
            }

            throw new ValidationException(errors: $constraints);
        }

        // End Validated
        $this->endValidated();

        return true;
    }

    /**
     * Get Validated Data.
     */
    final public function validated(?string $key = null): mixed
    {
        if (!$this->validated) {
            $this->validated = array_diff_key(
                get_object_vars($this),
                array_flip([
                    'id',
                    'request',
                    'validator',
                    'auto',
                    'validated',
                    'constraints',
                ])
            );
        }

        if ($key) {
            if (!isset($this->validated[$key])) {
                return null;
            }

            if ('' === $this->validated[$key]) {
                return null;
            }

            return $this->validated[$key];
        }

        return $this->validated;
    }

    /**
     * Run Before Validation.
     */
    protected function beforeValidated(): void
    {
    }

    /**
     * Run Success Validation.
     */
    protected function endValidated(): void
    {
    }

    /**
     * Validated Data to Object Setter.
     *
     * @template T
     *
     * @param T $object
     *
     * @return T
     */
    public function initObject(mixed $object = null): mixed
    {
        return $object;
    }

    private function initProperties(array $fields): void
    {
        $refClass = new \ReflectionClass(static::class);

        foreach ($fields as $field => $value) {
            if ($refClass->hasProperty($field)) {
                $propType = $refClass->getProperty($field)->getType();

                if (!$propType) {
                    $name = null;
                } else {
                    $name = $propType instanceof \ReflectionUnionType ?
                        $propType->getTypes()[0]->getName() :
                        $propType->getName(); // @phpstan-ignore-line
                }

                try {
                    $data = match ($name) {
                        'DateTime' => new \DateTime($value),
                        'DateTimeImmutable' => new \DateTimeImmutable($value),
                        'bool' => (bool) $value,
                        'int' => (int) $value,
                        'string' => (string) $value,
                        default => $value,
                    };

                    if (null === $value && !$propType->allowsNull()) {
                        continue;
                    }

                    if (enum_exists($name)) {
                        $this->$field = $name::from($value); // @phpstan-ignore-line
                    } else {
                        $this->$field = $data;
                    }
                } catch (\Throwable) {
                    $this->constraints->add(
                        new ConstraintViolation(
                            'The type of this value is incorrect.',
                            'The type of this value is incorrect.',
                            [],
                            $this,
                            $field,
                            $value,
                        )
                    );
                }
            }
        }
    }

    public function setProp(string $propName, mixed $value): self
    {
        $this->{$propName} = $value;

        return $this;
    }
}
