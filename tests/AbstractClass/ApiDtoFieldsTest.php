<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Cesurapp\ApiBundle\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Which properties a request may write, and how values are converted.
 */
class ApiDtoFieldsTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testAutoCannotBeDisabledFromTheRequest(): void
    {
        $this->expectException(ValidationException::class);

        $this->dto(SignupDto::class, body: ['email' => 'not-an-email', 'password' => '1'], query: ['auto' => '0']);
    }

    public function testValidatedCannotBeInjected(): void
    {
        $dto = $this->dto(SignupDto::class, body: [
            'email' => 'a@b.co',
            'password' => '12345678',
            'validated' => ['role' => 'ROLE_ADMIN'],
            'constraints' => 'x',
            'request' => 'x',
            'validator' => 'x',
        ]);

        $this->assertSame(['email' => 'a@b.co', 'password' => '12345678', 'age' => null, 'expiresAt' => null, 'active' => true], $dto->validated());
    }

    public function testNonPublicPropertiesAreNotRequestFields(): void
    {
        $dto = $this->dto(SignupDto::class, body: ['email' => 'a@b.co', 'password' => '12345678', 'internal' => 'hacked']);

        $this->assertSame('default', $dto->getInternal());
        $this->assertArrayNotHasKey('internal', $dto->validated());
    }

    /**
     * A shared abstract DTO between ApiDto and the concrete one (e.g. a date range base): its public
     * fields are request fields too.
     */
    public function testFieldsInheritedFromAnIntermediateDtoAreFilled(): void
    {
        $dto = $this->dto(CallRangeDto::class, query: ['start_date' => '2026-01-01T00:00:00+00:00', 'end_date' => '2026-01-31T00:00:00+00:00', 'status' => 'open']);

        $this->assertSame('2026-01-01T00:00:00+00:00', $dto->start_date);
        $this->assertSame([
            'status' => 'open',
            'start_date' => '2026-01-01T00:00:00+00:00',
            'end_date' => '2026-01-31T00:00:00+00:00',
        ], $dto->validated());
        $this->assertSame('2026-01-31T00:00:00+00:00', $dto->validated('end_date'));
    }

    public function testInheritedFieldsAreValidated(): void
    {
        $this->expectException(ValidationException::class);

        $this->dto(CallRangeDto::class, query: ['start_date' => 'yesterday-ish']);
    }

    public function testValidatedIsNotServedStale(): void
    {
        $dto = $this->dto(SignupDto::class, body: ['email' => 'a@b.co', 'password' => '12345678']);
        $this->assertSame('a@b.co', $dto->validated('email'));

        $dto->setProp('email', 'c@d.co');

        $this->assertSame('c@d.co', $dto->validated('email'));
        $this->assertSame('c@d.co', $dto->validated()['email']);
    }

    public function testNullStaysNull(): void
    {
        $dto = $this->dto(SignupDto::class, body: ['email' => 'a@b.co', 'password' => '12345678', 'age' => null, 'expiresAt' => null]);

        $this->assertNull($dto->age);
        $this->assertNull($dto->expiresAt);
    }

    public function testEmptyFormValueOfANonStringFieldIsNull(): void
    {
        $dto = $this->dto(SignupDto::class, query: ['email' => 'a@b.co', 'password' => '12345678', 'age' => '', 'expiresAt' => '']);

        $this->assertNull($dto->age);
        $this->assertNull($dto->expiresAt);
    }

    #[DataProvider('boolValues')]
    public function testBoolFromFormAndJson(mixed $value, bool $expected): void
    {
        $dto = $this->dto(SignupDto::class, query: ['email' => 'a@b.co', 'password' => '12345678', 'active' => $value]);

        $this->assertSame($expected, $dto->active);
    }

    public static function boolValues(): iterable
    {
        yield ['false', false];
        yield ['0', false];
        yield ['off', false];
        yield [false, false];
        yield ['true', true];
        yield ['1', true];
        yield [true, true];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueIsAViolationNotACast(string $field, mixed $value): void
    {
        try {
            $this->dto(SignupDto::class, body: ['email' => 'a@b.co', 'password' => '12345678', $field => $value]);
            $this->fail('ValidationException expected');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->getErrors());
        }
    }

    public static function invalidValues(): iterable
    {
        yield 'int from text' => ['age', 'abc'];
        yield 'int from decimal' => ['age', '1.5'];
        yield 'int from array' => ['age', [1]];
        yield 'bool from text' => ['active', 'maybe'];
        yield 'date from text' => ['expiresAt', 'not a date'];
        yield 'string from array' => ['email', ['a@b.co']];
    }

    public function testIntFromNumericString(): void
    {
        $dto = $this->dto(SignupDto::class, query: ['email' => 'a@b.co', 'password' => '12345678', 'age' => '007']);

        $this->assertSame(7, $dto->age);
    }

    public function testUnionPrefersTheValuesOwnType(): void
    {
        $dto = $this->dto(TypesDto::class, body: ['idOrName' => '42', 'userOrId' => 'abc', 'priority' => '2', 'score' => '1.5']);

        $this->assertSame('42', $dto->idOrName);
        $this->assertSame('abc', $dto->userOrId);
        $this->assertSame(Priority::HIGH, $dto->priority);
        $this->assertSame(1.5, $dto->score);
    }

    public function testUnknownEnumValueIsAViolation(): void
    {
        $this->expectException(ValidationException::class);

        $this->dto(TypesDto::class, body: ['priority' => '9']);
    }

    public function testPutRouteIdIsKeptOutOfValidated(): void
    {
        $request = new Request(request: ['email' => 'a@b.co', 'password' => '12345678']);
        $request->setMethod('PUT');
        $request->attributes->set('id', '15');

        $dto = new SignupDto($request, $this->validator);

        $this->assertSame('15', $dto->id); // @phpstan-ignore-line
        $this->assertArrayNotHasKey('id', $dto->validated());
    }

    /**
     * @template T of ApiDto
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function dto(string $class, array $body = [], array $query = []): ApiDto
    {
        $request = new Request(query: $query, request: $body);
        $request->setMethod('POST');

        return new $class($request, $this->validator);
    }
}

class SignupDto extends ApiDto
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8)]
    public ?string $password = null;

    public ?int $age = null;

    public ?\DateTimeImmutable $expiresAt = null;

    public bool $active = true;

    protected string $internal = 'default';

    public function getInternal(): string
    {
        return $this->internal;
    }
}

abstract class RangeDto extends ApiDto
{
    #[Assert\AtLeastOneOf([new Assert\DateTime(DATE_RFC3339_EXTENDED), new Assert\DateTime(DATE_ATOM)])]
    public ?string $start_date = null;

    #[Assert\AtLeastOneOf([new Assert\DateTime(DATE_RFC3339_EXTENDED), new Assert\DateTime(DATE_ATOM)])]
    public ?string $end_date = null;
}

class CallRangeDto extends RangeDto
{
    public ?string $status = null;
}

enum Priority: int
{
    case LOW = 1;
    case HIGH = 2;
}

class TypesDto extends ApiDto
{
    public int|string|null $idOrName = null;

    public \stdClass|string|null $userOrId = null;

    public ?Priority $priority = null;

    public ?float $score = null;
}
