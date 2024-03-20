<?php

namespace Cesurapp\ApiBundle\Tests\Validator;

use Cesurapp\ApiBundle\Validator\Username;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UsernameValidatorTest extends KernelTestCase
{
    /**
     * @dataProvider validateProvider
     */
    public function testValidate(?string $username, int $exceptedCount = 0): void
    {
        $validator = self::getContainer()->get('validator');
        $class = new UsernameDummy();
        $class->username = $username;
        $this->assertSame($exceptedCount, $validator->validateProperty($class, 'username')->count());
    }

    public function validateProvider(): iterable
    {
        yield ['+905411111111', 0];
        yield ['5411111111', 1];
        yield ['+441234567890', 0];
        yield ['2015333', 1];
        yield ['asdadsadsa@sdas.com', 0];
        yield ['asdadsadsa@sasds', 1];
        yield ['foo', 1];
    }
}

class UsernameDummy
{
    #[Username]
    public string $username;
}
