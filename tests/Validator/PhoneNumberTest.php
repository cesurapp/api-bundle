<?php

namespace Cesurapp\ApiBundle\Tests\Validator;

use Cesurapp\ApiBundle\Validator\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PhoneNumberTest extends KernelTestCase
{
    public function testProperties(): void
    {
        $phoneNumber = new PhoneNumber();

        $this->assertObjectHasProperty('message', $phoneNumber);
        $this->assertObjectHasProperty('types', $phoneNumber);
        $this->assertObjectHasProperty('defaultRegion', $phoneNumber);
        $this->assertObjectHasProperty('regionPath', $phoneNumber);
    }

    /**
     * @dataProvider messageProvider
     */
    public function testMessage(?string $message, array|string|null $type, ?int $format, string $expectedMessage): void
    {
        $phoneNumber = new PhoneNumber(types: $type, format: $format, message: $message);
        $this->assertSame($expectedMessage, $phoneNumber->getMessage());
        $this->assertSame($format, $phoneNumber->format);
    }

    /**
     * 0 => Message (optional)
     * 1 => Type (optional)
     * 2 => Format (optional)
     * 3 => Expected message.
     *
     * @return iterable<array{?string, string|string[]|null, ?int, string}>
     */
    public function messageProvider(): iterable
    {
        yield [null, null, null, 'This value is not a valid phone number.'];
        yield [null, 'fixed_line', null, 'This value is not a valid fixed-line number.'];
        yield [null, 'mobile', null, 'This value is not a valid mobile number.'];
        yield [null, 'pager', null, 'This value is not a valid pager number.'];
        yield [null, 'personal_number', null, 'This value is not a valid personal number.'];
        yield [null, 'premium_rate', null, 'This value is not a valid premium-rate number.'];
        yield [null, 'shared_cost', null, 'This value is not a valid shared-cost number.'];
        yield [null, 'toll_free', null, 'This value is not a valid toll-free number.'];
        yield [null, 'uan', null, 'This value is not a valid UAN.'];
        yield [null, 'voip', null, 'This value is not a valid VoIP number.'];
        yield [null, 'voicemail', null, 'This value is not a valid voicemail access number.'];
        yield [null, ['fixed_line', 'voip'], null, 'This value is not a valid phone number.'];
        yield [null, ['uan', 'fixed_line'], null, 'This value is not a valid phone number.'];
        yield ['foo', null, null, 'foo'];
        yield ['foo', 'fixed_line', null, 'foo'];
        yield ['foo', 'mobile', null, 'foo'];
        yield [null, null, PhoneNumberFormat::E164, 'This value is not a valid phone number.'];
    }
}
