<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\GlobalExceptionHandler;
use Cesurapp\ApiBundle\Exception\ValidationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlobalExceptionHandlerTest extends KernelTestCase
{
    public function testExceptionResponseTest(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler($this->createMock(TranslatorInterface::class));
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

    public function testValidationExceptionResponseTest(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new GlobalExceptionHandler($this->createMock(TranslatorInterface::class));
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
}
