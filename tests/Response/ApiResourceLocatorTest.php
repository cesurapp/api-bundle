<?php

namespace Cesurapp\ApiBundle\Tests\Response;

use Cesurapp\ApiBundle\Response\ApiResourceLocator;
use Cesurapp\ApiBundle\Tests\_App\Resources\AcmeResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiResourceLocatorTest extends KernelTestCase
{
    public function testValidationExceptionResponse(): void
    {
        $locator = self::getContainer()->get(ApiResourceLocator::class);
        $this->assertInstanceOf(AcmeResource::class, $locator->get(AcmeResource::class));
        $this->assertSame([], $locator->get(AcmeResource::class)->toResource());
    }
}
