<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\ApiException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiExceptionTest extends KernelTestCase
{
    public function testApiException(): void
    {
        self::bootKernel();

        $this->expectExceptionCode(450);

        throw new class () extends ApiException {
            public function __construct(
                string $message = 'Stub Exception!',
                int $code = 450,
            ) {
                parent::__construct($message, $code);
            }
        };
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }
}
