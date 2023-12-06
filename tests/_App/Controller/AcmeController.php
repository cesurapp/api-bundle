<?php

namespace Cesurapp\ApiBundle\Tests\_App\Controller;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\Tests\_App\Dto\AcmeDto;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AcmeController extends ApiController
{
    #[Route('/', methods: ['GET', 'POST'])]
    public function homeAction(): Response
    {
        return new Response('hi');
    }

    #[Route('/dto', methods: ['POST'])]
    public function dtoAction(AcmeDto $dto): Response
    {
        return new Response('asddto');
    }
}
