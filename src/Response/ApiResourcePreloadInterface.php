<?php

namespace Cesurapp\ApiBundle\Response;

/**
 * Optional companion of ApiResourceInterface: receives every item of a response at once, before
 * toArray() runs for each of them, so the relations toArray() reads can be loaded in one query
 * instead of one query per item (N+1).
 *
 * Example — toArray() reads $user->getCompany()->getName(): one query for the companies of the
 * whole page, which initializes the company proxies the users already hold.
 *
 *     public function preload(array $items, mixed $optional = null): void
 *     {
 *         $ids = array_map(static fn (User $user) => $user->getCompany()->getId(), $items);
 *         $this->em->getRepository(Company::class)->findBy(['id' => array_unique($ids)]);
 *     }
 */
interface ApiResourcePreloadInterface
{
    /**
     * @param list<object> $items
     */
    public function preload(array $items, mixed $optional = null): void;
}
