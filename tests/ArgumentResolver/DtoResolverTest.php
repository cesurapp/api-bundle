<?php

namespace Cesurapp\ApiBundle\Tests\ArgumentResolver;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class DtoResolverTest extends KernelTestCase
{
    public function testValidationExceptionResponse(): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('/v1/admin/dto', method: 'POST'));
        $content = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([
            'type' => 'ValidationException',
            'code' => 422,
            'message' => 'Validation failed',
            'errors' => [
                'password' => ['This value should not be null.'],
                'language' => ['This value should not be null.'],
            ],
        ], $content);
    }
}
