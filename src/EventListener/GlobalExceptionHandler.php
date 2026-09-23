<?php

namespace Cesurapp\ApiBundle\EventListener;

use Cesurapp\ApiBundle\AbstractClass\ApiException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Global Exception Handler.
 *
 * The message of an unexpected server error (a 5xx that is not an HttpException or ApiException —
 * a Doctrine/driver error, a TypeError…) can carry SQL, file paths or user data, so outside debug
 * it is replaced by a generic text. The exception is still logged by the framework.
 */
readonly class GlobalExceptionHandler implements EventSubscriberInterface
{
    public const string GENERIC_MESSAGE = 'Internal Server Error';

    public function __construct(private TranslatorInterface $translator, private ParameterBagInterface $bag)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->bag->get('api.exception_converter')) {
            return;
        }

        if ('test' === $this->param('kernel.environment') && ($event->getRequest()->server->has('dd') || $event->getRequest()->server->has('ddd'))) {
            $throwable = $event->getThrowable();
            $event->setResponse(new JsonResponse([
                'message' => $throwable->getMessage(),
                'code' => $throwable->getCode(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $event->getRequest()->server->has('ddd') ? $throwable->getTrace() : null,
            ], 500));

            return;
        }

        // Create Exception Message
        $exception = $event->getThrowable();
        $code = self::statusCode($exception);
        $exposed = $code < 500
            || $exception instanceof HttpExceptionInterface
            || $exception instanceof ApiException
            || $this->param('kernel.debug');

        $message = [
            'type' => new \ReflectionClass($exception)->getShortName(),
            'code' => $code,
            'message' => $this->translator->trans($exposed ? $exception->getMessage() : self::GENERIC_MESSAGE, domain: 'exception'),
        ];

        // Append Fields
        if (method_exists($exception, 'getErrors')) {
            $message['errors'] = $exception->getErrors();
        }

        // Json Response (keeps WWW-Authenticate, Allow, Retry-After… of an HttpException)
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $event->setResponse(new JsonResponse($message, $code, $headers));
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => [['onKernelException', -100]]];
    }

    /**
     * An HTTP error status: the HttpException's own, else the exception code when it is a known
     * 4xx/5xx status, else 500. A library code that happens to be 101 or 204 never becomes the
     * response status.
     */
    private static function statusCode(\Throwable $exception): int
    {
        $code = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            method_exists($exception, 'getStatusCode') => $exception->getStatusCode(),
            default => $exception->getCode(),
        };

        return is_int($code) && $code >= 400 && $code < 600 && isset(Response::$statusTexts[$code]) ? $code : 500;
    }

    private function param(string $name): mixed
    {
        return $this->bag->has($name) ? $this->bag->get($name) : null;
    }
}
