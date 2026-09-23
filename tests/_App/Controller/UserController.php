<?php

namespace Cesurapp\ApiBundle\Tests\_App\Controller;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\Response\ApiResponse;
use Cesurapp\ApiBundle\Response\MessageType;
use Cesurapp\ApiBundle\Security\Attribute\IsGrantedAny;
use Cesurapp\ApiBundle\Tests\_App\Repository\UserRepository;
use Cesurapp\ApiBundle\Tests\_App\Resources\UserResource;
use Cesurapp\ApiBundle\Thor\Attribute\Thor;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends ApiController
{
    #[Route('/v1/users', methods: ['GET'])]
    #[Thor(stack: 'User', title: 'List Users', response: [200 => ['data' => UserResource::class]], isPaginate: true)]
    #[IsGrantedAny(['ROLE_USER_LIST', 'ROLE_ADMIN'], message: 'No list for you')]
    public function list(UserRepository $repo): ApiResponse
    {
        return ApiResponse::create()
            ->setQuery($repo->createQueryBuilder('u'))
            ->setPaginate(2, total: true)
            ->setResource(UserResource::class)
            ->addMessage('Listed');
    }

    #[Route('/v1/users/with-tags', methods: ['GET'])]
    public function withTags(UserRepository $repo): ApiResponse
    {
        // Fetch-joined collection: a SQL row per tag, pages must still hold whole users
        return ApiResponse::create()
            ->setQuery($repo->createQueryBuilder('u')->leftJoin('u.tags', 't')->addSelect('t')->orderBy('u.id'))
            ->setPaginate(2, total: true)
            ->setResource(UserResource::class);
    }

    #[Route('/v1/users/cursor', methods: ['GET'])]
    #[Thor(stack: 'User', title: 'Cursor Users', response: [200 => ['data' => UserResource::class]], isPaginate: true)]
    public function cursor(UserRepository $repo): ApiResponse
    {
        return ApiResponse::create()
            ->setQuery($repo->createQueryBuilder('u'))
            ->setPaginate(2, cursor: true)
            ->setResource(UserResource::class);
    }

    #[Route('/v1/users/query', methods: ['GET'])]
    public function query(UserRepository $repo): ApiResponse
    {
        return ApiResponse::create()
            ->setQuery($repo->createQueryBuilder('u')->orderBy('u.id')->getQuery())
            ->setPaginate(2)
            ->setResource(UserResource::class);
    }

    #[Route('/v1/users/export-limited', methods: ['GET'])]
    public function exportLimited(UserRepository $repo): ApiResponse
    {
        return ApiResponse::create()
            ->setQuery($repo->createQueryBuilder('u')->orderBy('u.id'))
            ->setPaginate()
            ->setExportLimit(2)
            ->setResource(UserResource::class);
    }

    #[Route('/v1/users/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UserRepository $repo): ApiResponse
    {
        return ApiResponse::create()
            ->setQuery($repo->createQueryBuilder('u')->where('u.id = :id')->setParameter('id', $id))
            ->setData($repo->find($id))
            ->setResource(UserResource::class)
            ->addMessage('Found', MessageType::INFO);
    }

    #[Route('/v1/value-objects', methods: ['GET'])]
    public function valueObjects(): ApiResponse
    {
        return ApiResponse::create()->setData([
            'at' => new \DateTimeImmutable('2020-01-02T03:04:05+00:00'),
            'type' => MessageType::WARNING,
        ]);
    }
}
