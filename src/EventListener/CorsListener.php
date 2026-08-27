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
 *
 * Preflight (OPTIONS) is answered here, before routing, and mirrors the request Origin for ANY
 * origin. That is deliberate: a preflight carries no data, and the browser only exposes the REAL
 * response when THAT response names the origin — which onKernelResponse still does only for the
 * configured `api.cors_allowed_origin` list / local webviews, and an app controller only for
 * whatever tenant domain it resolves itself. Gating the preflight too would mean a per-tenant
 * origin (known only after routing) can never send a non-simple request — an Authorization
 * header, a JSON body, DELETE — and is forced into query-string auth. An unrelated origin gains
 * nothing: its request now gets *sent*, but without a token it is what curl could already do.
 */
readonly class CorsListener implements EventSubscriberInterface
{
    public const array localOrigins = ['http://localhost', 'https://localhost', 'capacitor://localhost', 'ionic://localhost', 'file://'];

    /** How long the browser may reuse a preflight verdict, in seconds (cached per origin + URL). */
    public const int PREFLIGHT_MAX_AGE = 600;

    public function __construct(private ParameterBagInterface $bag)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ('OPTIONS' === $event->getRequest()->getMethod()) {
            $response = new JsonResponse([], 204);

            $origin = $event->getRequest()->headers->get('Origin');
            if (null !== $origin && '' !== $origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', (string) self::PREFLIGHT_MAX_AGE);
                // A per-origin answer must not be handed to another origin by a shared cache.
                $response->headers->set('Vary', 'Origin', false);
            }

            $event->setResponse($response);

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
