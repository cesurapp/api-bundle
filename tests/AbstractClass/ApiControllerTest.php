<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use PHPUnit\Framework\MockObject\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiControllerTest extends KernelTestCase
{
    /**
     * @throws Exception
     */
    public function testSetContainer(): void
    {
        $container = self::getContainer();

        $stub = $this->createPartialMock(ApiController::class, ['setContainer']);
        $stubContainer = $stub->setContainer($container);

        $this->assertNull($stubContainer);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }
}
