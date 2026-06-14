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
    public const array localOrigins = ['http://localhost', 'https://localhost', 'capacitor://localhost', 'ionic://localhost', 'file://'];

    public function __construct(private ParameterBagInterface $bag)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ('OPTIONS' === $event->getRequest()->getMethod()) {
            $event->setResponse(new JsonResponse([], 204));

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
        $origin = $event->getRequest()->headers->get('Origin');

        // Set Cors Headers
        foreach ($this->bag->get('api.cors_header') as $header) {
            if (!$response->headers->has($header['name'])) {
                $response->headers->set($header['name'], $header['value']);
            }
        }

        if (!$origin) {
            return;
        }

        // Custom Origins
        if (in_array($origin, $this->bag->get('api.cors_allowed_origin'), true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');

            return;
        }

        // Is Localhost / WebView (Capacitor, Ionic, React Native)
        foreach (self::localOrigins as $prefix) {
            if (str_starts_with($origin, $prefix)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                break;
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
