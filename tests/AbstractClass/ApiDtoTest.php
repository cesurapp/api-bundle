<?php

namespace Cesurapp\ApiBundle\Tests\AbstractClass;

use Cesurapp\ApiBundle\AbstractClass\ApiDto;
use Cesurapp\ApiBundle\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiDtoTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    public function testDtoInvalidRequest(): void
    {
        self::bootKernel();

        $validator = self::getContainer()->get('validator');
        $request = new Request();

        $this->expectException(ValidationException::class);
        $this->generateDto($request, $validator);
    }

    public function testDtoValidRequest(): void
    {
        $validator = self::getContainer()->get('validator');

        $data = [
            'password' => '123123123',
            'language' => 'en',
            'first_name' => 'Cesur',
            'last_name' => 'Apaydın',
            'send_at' =>  (new \DateTimeImmutable('+1 hour'))->format(DATE_ATOM),
        ];
        $request = new Request(request: $data);
        $request->setMethod('POST');
        $dto = $this->generateDto($request, $validator);

        $this->assertSame($dto->validated('send_at')->format(DATE_ATOM), $data['send_at']);
    }

    private function generateDto(Request $request, ValidatorInterface $validator): ApiDto
    {
        return new class ($request, $validator) extends ApiDto {
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
