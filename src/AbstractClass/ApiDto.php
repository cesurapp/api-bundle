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
 *
 * Request fields are the public, non-static properties declared anywhere below ApiDto: the concrete
 * DTO or any abstract parent between them (e.g. a shared date range base). ApiDto's own state
 * (auto, constraints, request, validator) is never a request field, so the client cannot switch
 * validation off or smuggle values into validated().
 */
#[\AllowDynamicProperties]
abstract class ApiDto
{
    protected bool $auto = true;

    protected ConstraintViolationListInterface $constraints;

    /** @var array<class-string, array<string, \ReflectionProperty>> */
    private static array $fieldCache = [];

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
     *
     * Read from the same field list the request fills, every call: a value changed after the first
     * call (endValidated, an EntityExists lookup, setProp) is never served stale.
     */
    final public function validated(?string $key = null): mixed
    {
        $validated = [];
        foreach (self::fields(static::class) as $name => $property) {
            if ('id' !== $name && $property->isInitialized($this)) {
                $validated[$name] = $property->getValue($this);
            }
        }

        if (null === $key) {
            return $validated;
        }

        $value = $validated[$key] ?? null;

        return '' === $value ? null : $value;
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

    public function setProp(string $propName, mixed $value): self
    {
        $this->{$propName} = $value;

        return $this;
    }

    /**
     * Public, non-static properties not declared by ApiDto itself — inherited ones included.
     *
     * @param class-string $class
     *
     * @return array<string, \ReflectionProperty>
     */
    private static function fields(string $class): array
    {
        if (isset(self::$fieldCache[$class])) {
            return self::$fieldCache[$class];
        }

        $fields = [];
        foreach (new \ReflectionClass($class)->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && self::class !== $property->getDeclaringClass()->getName()) {
                $fields[$property->getName()] = $property;
            }
        }

        return self::$fieldCache[$class] = $fields;
    }

    private function initProperties(array $input): void
    {
        foreach (self::fields(static::class) as $field => $property) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            $type = $property->getType();

            try {
                // null, or an empty form/query value for a non-string field, means "no value"
                if (null === $value || ('' === $value && !self::acceptsString($type))) {
                    if (null === $type || $type->allowsNull()) {
                        $this->{$field} = null;
                    }

                    continue;
                }

                $this->{$field} = self::cast($type, $value);
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

    private static function acceptsString(?\ReflectionType $type): bool
    {
        if (!$type instanceof \ReflectionNamedType && !$type instanceof \ReflectionUnionType) {
            return true;
        }

        return array_any(
            $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type],
            static fn ($t) => $t instanceof \ReflectionNamedType && in_array($t->getName(), ['string', 'mixed'], true)
        );
    }

    /**
     * Convert a request value to the property type. A union prefers the member the value already
     * is (a string stays a string in `int|string`), then the first member it converts to.
     *
     * @throws \UnexpectedValueException
     */
    private static function cast(?\ReflectionType $type, mixed $value): mixed
    {
        if (!$type instanceof \ReflectionNamedType && !$type instanceof \ReflectionUnionType) {
            return $value; // untyped or intersection: the assignment itself decides
        }

        $names = [];
        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $member) {
            if ($member instanceof \ReflectionNamedType && 'null' !== $member->getName()) {
                $names[] = $member->getName();
            }
        }

        foreach ($names as $name) {
            if (self::isExact($name, $value)) {
                return $value;
            }
        }

        foreach ($names as $name) {
            try {
                return self::castTo($name, $value);
            } catch (\Throwable) {
            }
        }

        throw new \UnexpectedValueException('The type of this value is incorrect.');
    }

    private static function isExact(string $type, mixed $value): bool
    {
        return match ($type) {
            'mixed' => true,
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            'true' => true === $value,
            'false' => false === $value,
            'array' => is_array($value),
            'iterable' => is_iterable($value),
            'object' => is_object($value),
            default => $value instanceof $type,
        };
    }

    /**
     * @throws \UnexpectedValueException
     */
    private static function castTo(string $type, mixed $value): mixed
    {
        return match ($type) {
            'string' => is_scalar($value) ? (string) $value : throw new \UnexpectedValueException(),
            'int' => self::toInt($value),
            'float' => is_int($value) || (is_string($value) && is_numeric($value)) ? (float) $value : throw new \UnexpectedValueException(),
            'bool', 'true', 'false' => self::toBool($type, $value),
            \DateTime::class => self::toDate(\DateTime::class, $value),
            \DateTimeImmutable::class, \DateTimeInterface::class => self::toDate(\DateTimeImmutable::class, $value),
            default => enum_exists($type) ? self::toEnum($type, $value) : throw new \UnexpectedValueException(),
        };
    }

    private static function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (int) $value;
        }

        // Plain decimal integers only ("007" and "+5" too); "abc", "1.5", "1e3" and overflow fail.
        if (is_string($value) && preg_match('/^\s*([+-]?)0*(\d+)\s*$/', $value, $m)) {
            $int = filter_var(('-' === $m[1] ? '-' : '').$m[2], FILTER_VALIDATE_INT);
            if (false !== $int) {
                return $int;
            }
        }

        throw new \UnexpectedValueException();
    }

    private static function toBool(string $type, mixed $value): bool
    {
        $bool = is_bool($value) ? $value : null;
        if (is_int($value) || is_string($value)) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (null === $bool || ('true' === $type && !$bool) || ('false' === $type && $bool)) {
            throw new \UnexpectedValueException();
        }

        return $bool;
    }

    /**
     * @param class-string<\DateTime|\DateTimeImmutable> $class
     */
    private static function toDate(string $class, mixed $value): \DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $class::createFromInterface($value);
        }

        if (!is_string($value) || '' === trim($value)) {
            throw new \UnexpectedValueException();
        }

        return new $class($value);
    }

    /**
     * @param class-string<\UnitEnum> $enum
     */
    private static function toEnum(string $enum, mixed $value): \UnitEnum
    {
        if (is_subclass_of($enum, \BackedEnum::class)) {
            /** @var class-string<\BackedEnum> $enum */
            $backing = new \ReflectionEnum($enum)->getBackingType();
            $value = match (true) {
                $backing instanceof \ReflectionNamedType && 'int' === $backing->getName() => self::toInt($value),
                is_string($value) || is_int($value) => (string) $value,
                default => throw new \UnexpectedValueException(),
            };

            return $enum::tryFrom($value) ?? throw new \UnexpectedValueException();
        }

        // Pure enum: by case name
        if (is_string($value) && defined($enum.'::'.$value) && ($case = constant($enum.'::'.$value)) instanceof $enum) {
            return $case;
        }

        throw new \UnexpectedValueException();
    }
}
