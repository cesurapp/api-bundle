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
use Symfony\Contracts\Translation\TranslatorInterface;

class GlobalExceptionHandlerTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testExceptionResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler(
            $this->createStub(TranslatorInterface::class),
            new ParameterBag([
                'api.exception_converter' => true,
                'kernel.environment' => 'test',
            ])
        );
        $dispatcher->addListener('onKernelException', [$listener, 'onKernelException']);

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
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
        $container = self::getContainer();
        $handler = $container->get(GlobalExceptionHandler::class);
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new NotFoundHttpException(),
        );
        $handler->onKernelException($event);

        $this->assertEquals(
            '{"type":"NotFoundHttpException","code":404,"message":""}',
            $event->getResponse()->getContent()
        );
    }

    public function testValidationExceptionResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler(
            $this->createStub(TranslatorInterface::class),
            new ParameterBag([
                'api.exception_converter' => true,
                'kernel.environment' => 'test',
            ])
        );
        $dispatcher->addListener('onKernelException', [$listener, 'onKernelException']);

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
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
        $container = self::getContainer();
        $handler = $container->get(GlobalExceptionHandler::class);

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new ValidationException(),
        );
        $handler->onKernelException($event);

        $this->assertEquals(
            '{"type":"ValidationException","code":422,"message":"Validation failed","errors":null}',
            $event->getResponse()->getContent()
        );
    }

    public function testExceptionDisable(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler(
            $this->createStub(TranslatorInterface::class),
            new ParameterBag(['api.exception_converter' => false])
        );
        $dispatcher->addListener('onKernelException', [$listener, 'onKernelException']);

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
            new NotFoundHttpException(),
        );
        $dispatcher->dispatch($event, 'onKernelException');

        $this->assertEquals(null, $event->getResponse());
    }
}
