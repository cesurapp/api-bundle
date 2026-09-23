<?php

namespace Cesurapp\ApiBundle\Tests\_App\Controller;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\Response\ApiResponse;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/v1/invokable', methods: ['GET'])]
class InvokableController extends ApiController
{
    #[Thor(stack: 'Invokable', title: 'Invokable Controller')]
    public function __invoke(): ApiResponse
    {
        return ApiResponse::create()->setData(['ok' => true]);
    }
}
