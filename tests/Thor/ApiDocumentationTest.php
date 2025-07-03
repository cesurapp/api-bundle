<?php

namespace Cesurapp\ApiBundle\Tests\Thor;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiDocumentationTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testViewDocumentation(): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('/thor'));
        $this->assertTrue($response->isOk());
    }

    public function testDownloadDocumentation(): void
    {
        self::bootKernel();
        /** @var BinaryFileResponse $response */
        $response = self::$kernel->handle(Request::create('/thor/download'));
        $this->assertTrue($response->isOk());
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertTrue($response->getFile()->isFile());
    }
}
