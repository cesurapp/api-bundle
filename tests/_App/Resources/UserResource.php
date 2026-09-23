<?php

namespace Cesurapp\ApiBundle\Tests\_App\Resources;

use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Cesurapp\ApiBundle\Response\ApiResourcePreloadInterface;
use Cesurapp\ApiBundle\Tests\_App\Entity\User;
use Doctrine\ORM\QueryBuilder;

class UserResource implements ApiResourceInterface, ApiResourcePreloadInterface
{
    /** @var list<list<object>> */
    public static array $preloaded = [];

    /**
     * @param User $item
     */
    public function toArray(mixed $item, mixed $optional = null): array
    {
        return [
            'id' => $item->getId(),
            'email' => $item->getEmail(),
            'team' => $item->getTeam(),
            'tags' => array_map(static fn ($tag) => $tag->getName(), $item->getTags()->toArray()),
        ];
    }

    public function preload(array $items, mixed $optional = null): void
    {
        self::$preloaded[] = $items;
    }

    public function toResource(): array
    {
        return [
            'id' => [
                'type' => 'int',
                'filter' => static function (QueryBuilder $builder, string $alias, string $data) {
                    if (!ctype_digit($data)) {
                        throw new \InvalidArgumentException(sprintf('Invalid ID: "%s".', $data));
                    }
                    $builder->andWhere("$alias.id = :id")->setParameter('id', (int) $data);
                },
                'table' => ['label' => 'ID', 'sortable' => true],
            ],
            'email' => [
                'type' => 'string',
                'filter' => static function (QueryBuilder $builder, string $alias, string $data) {
                    $builder->andWhere("$alias.email LIKE :email")->setParameter('email', $data.'%');
                },
                'table' => ['label' => 'Email', 'sortable' => true],
            ],
            'tags' => [
                'type' => 'array',
                'table' => [
                    'label' => 'Tags',
                    'exporter' => static fn ($tags) => implode('|', array_map(static fn ($tag) => $tag->getName(), $tags->toArray())),
                ],
            ],
            'team' => [
                'type' => '?string',
                'filter' => static function (QueryBuilder $builder, string $alias, array|string $data) {
                    $builder->andWhere("$alias.team IN (:teams)")->setParameter('teams', (array) $data);
                },
                'table' => ['label' => 'Team', 'sortable' => true],
            ],
            'createdRange' => [
                'type' => 'string',
                'filter' => [
                    'from' => static function (QueryBuilder $builder, string $alias, string $data) {
                        $builder->andWhere("$alias.id >= :idFrom")->setParameter('idFrom', (int) $data);
                    },
                    'to' => static function (QueryBuilder $builder, string $alias, string $data) {
                        $builder->andWhere("$alias.id <= :idTo")->setParameter('idTo', (int) $data);
                    },
                ],
            ],
        ];
    }
}
