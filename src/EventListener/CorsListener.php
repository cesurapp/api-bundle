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
 *
 * Local origins are matched on the exact host: `http://localhost.attacker.com` is not localhost.
 */
readonly class CorsListener implements EventSubscriberInterface
{
    /** Schemes a local dev server or a mobile WebView (Capacitor, Ionic, React Native) serves the app from, on host `localhost`. */
    public const array LOCAL_SCHEMES = ['http', 'https', 'capacitor', 'ionic'];

    /** Origin a WebView sends for a page loaded from the file system. */
    public const string FILE_ORIGIN = 'file://';

    /** Methods that reach routing; anything else (TRACE, CONNECT, WebDAV…) is refused with 405. */
    public const array ALLOWED_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'];

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

        $request = $event->getRequest();

        if ('OPTIONS' === $request->getMethod()) {
            $response = new JsonResponse([], 204);

            $origin = $request->headers->get('Origin');
            if (null !== $origin && '' !== $origin) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', (string) self::PREFLIGHT_MAX_AGE);
                // A per-origin answer must not be handed to another origin by a shared cache.
                $response->headers->set('Vary', 'Origin', false);

                // `*` is no wildcard for a credentialed request and never covers Authorization:
                // answer "allow any header" with exactly the headers the preflight asks for.
                $requested = $request->headers->get('Access-Control-Request-Headers');
                if ($requested && '*' === $this->configuredHeader('Access-Control-Allow-Headers')) {
                    $response->headers->set('Access-Control-Allow-Headers', $requested);
                }
            }

            $event->setResponse($response);

            return;
        }

        if (!in_array($request->getMethod(), self::ALLOWED_METHODS, true)) {
            $event->setResponse(new JsonResponse([], 405, ['Allow' => implode(', ', [...self::ALLOWED_METHODS, 'OPTIONS'])]));
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
        foreach ($this->param('api.cors_header') as $header) {
            if (!$response->headers->has($header['name'])) {
                $response->headers->set($header['name'], $header['value']);
            }
        }

        // Allow-Origin depends on the request Origin, so a shared cache (CDN, setHTTPCache) must
        // key on it — otherwise one tenant's Allow-Origin is served to another.
        if (!in_array('Origin', $response->getVary(), true)) {
            $response->setVary('Origin', false);
        }

        if (!$origin) {
            return;
        }

        // Custom Origins, Localhost / WebView (Capacitor, Ionic, React Native)
        if (in_array($origin, $this->param('api.cors_allowed_origin'), true) || self::isLocalOrigin($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    public static function isLocalOrigin(string $origin): bool
    {
        if (self::FILE_ORIGIN === $origin) {
            return true;
        }

        $parts = parse_url($origin);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        // An Origin is scheme://host[:port]; a path or user info means it is not one.
        if (array_diff_key($parts, ['scheme' => 0, 'host' => 0, 'port' => 0])) {
            return false;
        }

        return 'localhost' === strtolower($parts['host']) && in_array(strtolower($parts['scheme']), self::LOCAL_SCHEMES, true);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    private function param(string $name): array
    {
        return $this->bag->has($name) ? (array) $this->bag->get($name) : [];
    }

    private function configuredHeader(string $name): ?string
    {
        foreach ($this->param('api.cors_header') as $header) {
            if (0 === strcasecmp($header['name'] ?? '', $name)) {
                return (string) ($header['value'] ?? '');
            }
        }

        return null;
    }
}
