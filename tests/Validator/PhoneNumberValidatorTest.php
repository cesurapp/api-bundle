<?php

namespace Cesurapp\ApiBundle\Tests\Validator;

use Cesurapp\ApiBundle\Validator\PhoneNumber;
use Cesurapp\ApiBundle\Validator\PhoneNumberValidator;
use libphonenumber\PhoneNumberFormat;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class PhoneNumberValidatorTest extends KernelTestCase
{
    private ExecutionContextInterface&MockObject $context;
    private PhoneNumberValidator $validator;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->validator = new PhoneNumberValidator();
        $this->validator->initialize($this->context);
        $this->context->method('getObject')->willReturn(new Foo());
    }

    /**
     * @dataProvider validateProvider
     */
    public function testValidate(
        ?string $value,
        bool $violates,
        array|string|null $type = null,
        ?string $defaultRegion = null,
        ?string $regionPath = null,
        ?int $format = null
    ): void {
        $constraint = new PhoneNumber(types: $type, defaultRegion: $defaultRegion, regionPath: $regionPath, format: $format);

        if (true === $violates) {
            $constraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
            $constraintViolationBuilder
                ->expects($this->exactly(2))
                ->method('setParameter')
                ->with($this->isType('string'), $this->isType('string'))
                ->willReturn($constraintViolationBuilder);
            $constraintViolationBuilder
                ->expects($this->once())
                ->method('setCode')
                ->with($this->isType('string'))
                ->willReturn($constraintViolationBuilder);

            $this->context
                ->expects($this->once())
                ->method('buildViolation')
                ->with($constraint->getMessage())
                ->willReturn($constraintViolationBuilder);
        } else {
            $this->context->expects($this->never())->method('buildViolation');
        }

        $this->validator->validate($value, $constraint);
    }

    public function testValidateFromAttribute(): void
    {
        $classMetadata = new ClassMetadata(PhoneNumberDummy::class);
        (new AttributeLoader())->loadClassMetadata($classMetadata);

        [$constraint1] = $classMetadata->properties['phoneNumber1']->constraints;

        $validator = self::getContainer()->get('validator');
        $class = new PhoneNumberDummy();

        $this->assertSame(0, $validator->validate('+905411111111', $constraint1)->count());
        $this->assertSame(0, $validator->validate('5411111111', $constraint1)->count());
        $this->assertSame(1, $validator->validate('1411111111', $constraint1)->count());

        $class->phoneNumber2 = '5411111111';
        $this->assertSame(0, $validator->validateProperty($class, 'phoneNumber2')->count());
        $class->regionPath = 'GB';
        $this->assertSame(1, $validator->validateProperty($class, 'phoneNumber2')->count());
    }

    /**
     * 0 => Value
     * 1 => Violates?
     * 2 => Type (optional)
     * 3 => Default region (optional).
     * 4 => Region Path (optional).
     *
     * @return iterable<array{string|null, bool, 2?: string|string[]|null, 3?: ?string, 4?: ?string, 5?: ?int}>
     */
    public function validateProvider(): iterable
    {
        yield [null, false];
        yield ['', false];
        yield ['+441234567890', false];
        yield ['+441234567890', false, 'fixed_line'];
        yield ['+441234567890', true, 'mobile'];
        yield ['+441234567890', false, ['mobile', 'fixed_line']];
        yield ['+441234567890', true, ['mobile', 'voip']];
        yield ['+44123456789', true];
        yield ['+44123456789', true, 'mobile'];
        yield ['+12015555555', false];
        yield ['+12015555555', false, 'fixed_line'];
        yield ['+12015555555', false, 'mobile'];
        yield ['+12015555555', false, ['mobile', 'fixed_line']];
        yield ['+12015555555', true, ['pager', 'voip', 'uan']];
        yield ['+447640123456', false, 'pager'];
        yield ['+441234567890', true, 'pager'];
        yield ['+447012345678', false, 'personal_number'];
        yield ['+441234567890', true, 'personal_number'];
        yield ['+449012345678', false, 'premium_rate'];
        yield ['+441234567890', true, 'premium_rate'];
        yield ['+441234567890', true, 'shared_cost'];
        yield ['+448001234567', false, 'toll_free'];
        yield ['+441234567890', true, 'toll_free'];
        yield ['+445512345678', false, 'uan'];
        yield ['+441234567890', true, 'uan'];
        yield ['+445612345678', false, 'voip'];
        yield ['+441234567890', true, 'voip'];
        yield ['+41860123456789', false, 'voicemail'];
        yield ['+441234567890', true, 'voicemail'];
        yield ['2015555555', false, null, 'US'];
        yield ['2015555555', false, 'fixed_line', 'US'];
        yield ['2015555555', false, 'mobile', 'US'];
        yield ['01234 567890', false, null, 'GB'];
        yield ['foo', true];
        yield ['+441234567890', true, 'mobile', null, 'regionPath'];
        yield ['+33606060606', false, 'mobile', null, 'regionPath'];
        yield ['+33606060606', false, 'mobile', null, null, PhoneNumberFormat::E164];
        yield ['2015555555', true, null, null, null, PhoneNumberFormat::E164];
    }
}

class Foo
{
    public string $regionPath = 'GB';
}

class PhoneNumberDummy
{
    #[PhoneNumber(types: [PhoneNumber::MOBILE], defaultRegion: 'TR')]
    public ?string $phoneNumber1;

    #[PhoneNumber(regionPath: 'regionPath')]
    public ?string $phoneNumber2;

    public string $regionPath = 'TR';
}
