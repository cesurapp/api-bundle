<?php

namespace Cesurapp\ApiBundle\Tests\Response;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiResponseTest extends KernelTestCase
{
    public function testApiResponse(): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('/v1/auth/api-response'));

        $this->assertSame($response->getStatusCode(), 200);
        $this->assertSame($response->headers->get('custom-header'), 'acme');
        $this->assertSame($response->getContent(), '{"test":"acme","data":{"custom-data":"acme-data"},"message":{"error":["acme message"]}}');
        $this->assertSame($response->headers->get('Access-Control-Allow-Origin'), 'custom-domain.test');
    }
}
