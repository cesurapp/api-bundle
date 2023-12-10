<?php

namespace Cesurapp\ApiBundle\EventListener;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cors Handler.
 */
readonly class CorsListener implements EventSubscriberInterface
{
    public function __construct(private ParameterBagInterface $bag)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!in_array($event->getRequest()->getMethod(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $event->setResponse(new JsonResponse([]));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        foreach ($this->bag->get('api.cors_header') as $header) {
            if (!$response->headers->has($header['name'])) {
                $response->headers->set($header['name'], $header['value']);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }
}
