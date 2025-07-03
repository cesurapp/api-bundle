<?php

namespace Cesurapp\ApiBundle\Tests\ArgumentResolver;

use Cesurapp\ApiBundle\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DtoResolverTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testValidationExceptionResponse(): void
    {
        $client = static::createClient();
        $client->catchExceptions(false);
        $this->expectException(ValidationException::class);
        $client->jsonRequest('POST', '/v1/admin/dto');


        // $content = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        /*$this->assertSame([
            'type' => 'ValidationException',
            'code' => 422,
            'message' => 'Validation failed',
            'errors' => [
                'password' => ['This value should not be null.'],
                'language' => ['This value should not be null.'],
            ],
        ], $content);*/
    }
}
