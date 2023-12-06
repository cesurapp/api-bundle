<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\GlobalExceptionHandler;
use Cesurapp\ApiBundle\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlobalExceptionHandlerTest extends KernelTestCase
{
    public function testExceptionResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler(
            $this->createMock(TranslatorInterface::class),
            new ParameterBag(['api.exception_converter' => true])
        );
        $dispatcher->addListener('onKernelException', [$listener, 'onKernelException']);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new NotFoundHttpException(),
        );
        $dispatcher->dispatch($event, 'onKernelException');

        $this->assertEquals(
            '{"type":"NotFoundHttpException","code":404,"message":""}',
            $event->getResponse()->getContent()
        );
    }

    public function testExceptionResponseContainer(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new NotFoundHttpException(),
        );
        $dispatcher->dispatch($event, KernelEvents::EXCEPTION);
        $this->assertEquals(
            '{"type":"NotFoundHttpException","code":404,"message":""}',
            $event->getResponse()->getContent()
        );
    }

    public function testValidationExceptionResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler(
            $this->createMock(TranslatorInterface::class),
            new ParameterBag(['api.exception_converter' => true])
        );
        $dispatcher->addListener('onKernelException', [$listener, 'onKernelException']);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new ValidationException(),
        );
        $dispatcher->dispatch($event, 'onKernelException');

        $this->assertEquals(
            '{"type":"ValidationException","code":422,"message":"","errors":null}',
            $event->getResponse()->getContent()
        );
    }

    public function testValidationExceptionResponseContainer(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new ValidationException(),
        );
        $dispatcher->dispatch($event, KernelEvents::EXCEPTION);
        $this->assertEquals(
            '{"type":"ValidationException","code":422,"message":"Validation failed","errors":null}',
            $event->getResponse()->getContent()
        );
    }

    public function testExceptionDisable(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler(
            $this->createMock(TranslatorInterface::class),
            new ParameterBag(['api.exception_converter' => false])
        );
        $dispatcher->addListener('onKernelException', [$listener, 'onKernelException']);

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new NotFoundHttpException(),
        );
        $dispatcher->dispatch($event, 'onKernelException');

        $this->assertEquals(null, $event->getResponse());
    }
}
