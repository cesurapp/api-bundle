<?php

namespace Cesurapp\ApiBundle\Tests\_App\Controller;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\Response\ApiResponse;
use Cesurapp\ApiBundle\Response\MessageType;
use Cesurapp\ApiBundle\Tests\_App\Dto\AcmeDto;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AcmeController extends ApiController
{
    #[Route('/v1/admin/home/{id}', methods: ['GET', 'POST'])]
    #[Thor(stack: 'Home|1', title: 'HomePage', info: 'home page request')]
    public function homeAction(): Response
    {
        return new Response('hi');
    }

    #[Route('/v1/admin/dto', methods: ['POST'])]
    #[Thor(stack: 'Home', title: 'Acme DTO', info: 'dto request', dto: AcmeDto::class)]
    public function dtoAction(AcmeDto $dto): Response
    {
        return new Response('dto');
    }

    #[Route('/v1/auth/api-response', methods: ['GET'])]
    #[Thor(stack: 'ApiResponse|2', title: 'Api Response Test', info: 'api response request')]
    public function apiResponseAction(): ApiResponse
    {
        return ApiResponse::create(200)
            ->setData(['test' => 'acme'])
            ->addData('custom-data', 'acme-data')
            ->setHeaders(['custom-header' => 'acme'])
            ->setCorsOrigin('custom-domain.test')
            ->addMessage('acme message', MessageType::ERROR);
    }
}
