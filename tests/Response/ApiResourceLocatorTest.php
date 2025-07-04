<?php

namespace Cesurapp\ApiBundle\Tests\Response;

use Cesurapp\ApiBundle\Response\ApiResourceLocator;
use Cesurapp\ApiBundle\Tests\_App\Resources\AcmeResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiResourceLocatorTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testValidationExceptionResponse(): void
    {
        $locator = self::getContainer()->get(ApiResourceLocator::class);
        $this->assertInstanceOf(AcmeResource::class, $locator->get(AcmeResource::class));
        $this->assertSame(['acme' => 'string'], $locator->get(AcmeResource::class)->toResource());
    }
}
