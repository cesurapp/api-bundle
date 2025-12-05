<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiControllerTest extends KernelTestCase
{
    public function testSetContainer(): void
    {
        $container = self::getContainer();

        // Use a concrete test stub (anonymous class) instead of a PHPUnit mock without expectations
        $stub = new class () extends ApiController {
        };

        $stubContainer = $stub->setContainer($container);

        $this->assertNull($stubContainer);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }
}
