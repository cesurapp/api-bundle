<?php

namespace Cesurapp\ApiBundle\Tests\Exception;

use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Cesurapp\ApiBundle\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationExceptionTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testValidationExceptionResponse(): void
    {
        $validator = self::getContainer()->get('validator');
        $data = [
            'password' => '123123123',
            'language' => 'en',
        ];
        $request = new Request(request: $data);
        $request->setMethod('POST');

        try {
            $this->generateDto($request, $validator);
        } catch (ValidationException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertSame($exception->getErrors(), [
                'first_name' => ['This value should not be null.'],
                'last_name' => ['This value should not be null.'],
                'send_at' => ['This value should not be null.'],
            ]);
        }
    }

    private function generateDto(Request $request, ValidatorInterface $validator): void
    {
        new class ($request, $validator) extends ApiDto {
            #[Assert\Length(min: 8)]
            public ?string $password = null;

            #[Assert\Language]
            public ?string $language;

            #[Assert\Length(min: 2, max: 50)]
            #[Assert\NotNull]
            public string $first_name;

            #[Assert\Length(min: 2, max: 50)]
            #[Assert\NotNull]
            public string $last_name;

            #[Assert\NotNull]
            #[Assert\GreaterThan(new \DateTimeImmutable())]
            public \DateTimeImmutable $send_at;
        };
    }
}
