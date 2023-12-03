<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\AbstractApiController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AbstractApiControllerTest extends KernelTestCase
{
    public function testSetContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $stub = $this->getMockForAbstractClass(AbstractApiController::class);
        $stubContainer = $stub->setContainer($container);

        $this->assertNull($stubContainer);
    }
}
