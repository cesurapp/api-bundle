<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiControllerTest extends KernelTestCase
{
    public function testSetContainer(): void
    {
        $container = self::getContainer();

        $stub = $this->getMockForAbstractClass(ApiController::class);
        $stubContainer = $stub->setContainer($container);

        $this->assertNull($stubContainer);
    }
}
