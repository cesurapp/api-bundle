<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\AbstractApiException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AbstractApiExceptionTest extends KernelTestCase
{
    public function testApiException(): void
    {
        self::bootKernel();

        $this->expectExceptionCode(450);

        throw new class () extends AbstractApiException {
            public function __construct(
                string $message = 'Stub Exception!',
                int $code = 450,
            ) {
                parent::__construct($message, $code);
            }
        };
    }
}
