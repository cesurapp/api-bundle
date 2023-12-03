<?php

namespace Cesurapp\ApiBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Append Request Attribute.
 */
readonly class GlobalRequestAttribute // implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->isMethod('PUT')) {
            $token = $this->tokenStorage->getToken();
            if ($token && ($user = $token->getUser())) {
                $request->attributes->set('uid', $user->getId()?->toBase32()); // @phpstan-ignore-line
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest']];
    }
}
