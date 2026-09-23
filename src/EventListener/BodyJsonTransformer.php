<?php

namespace Cesurapp\ApiBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Json Request Body to Array Convert.
 *
 * Only a body declared as JSON (application/json, application/*+json) is decoded. A browser can
 * send text/plain — or a body with no Content-Type at all — cross-origin WITHOUT a preflight, so
 * decoding those would let any site post a JSON payload with the user's cookies (CSRF).
 */
class BodyJsonTransformer implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        // A sub-request (forward) duplicates the already decoded request bag.
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!self::isJsonContentType($request->headers->get('Content-Type'))) {
            return;
        }

        $content = $request->getContent();
        if (strspn($content, " \t\n\r") === strlen($content)) {
            return;
        }

        try {
            $data = json_decode($content, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Bad JSON Content.');
        }

        if (is_array($data)) {
            $request->request->add($data);
        }
    }

    public static function isJsonContentType(?string $contentType): bool
    {
        if (null === $contentType || '' === $contentType) {
            return false;
        }

        $mime = strtolower(trim(explode(';', $contentType, 2)[0]));

        return 'application/json' === $mime || 'application/x-json' === $mime || (str_starts_with($mime, 'application/') && str_ends_with($mime, '+json'));
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 100]];
    }
}
