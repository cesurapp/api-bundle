<?php

namespace Cesurapp\ApiBundle\EventListener;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Global Exception Handler.
 */
readonly class GlobalExceptionHandler implements EventSubscriberInterface
{
    public function __construct(private TranslatorInterface $translator, private ParameterBagInterface $bag)
    {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->bag->get('api.exception_converter')) {
            return;
        }

        if ('test' === $this->bag->get('kernel.environment') && ($event->getRequest()->server->has('dd') || $event->getRequest()->server->has('ddd'))) {
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
        $code = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : $exception->getCode();
        $message = [
            'type' => (new \ReflectionClass($exception))->getShortName(),
            'code' => isset(Response::$statusTexts[$code]) ? $code : 500,
            'message' => $this->translator->trans($exception->getMessage(), domain: 'exception'),
        ];

        // Append Fields
        if (method_exists($exception, 'getErrors')) {
            $message['errors'] = $exception->getErrors();
        }

        // Json Response
        $event->setResponse(new JsonResponse($message, $message['code']));
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => [['onKernelException', -100]]];
    }
}
