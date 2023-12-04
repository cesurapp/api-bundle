<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\CorsListener;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CorsListenerTest extends KernelTestCase
{
    public function testCorsOptionsRequest(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new CorsListener();
        $dispatcher->addListener('onKernelRequest', [$listener, 'onKernelRequest']);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', method: 'OPTIONS'),
            1,
        );
        $dispatcher->dispatch($event, 'onKernelRequest');

        $this->assertSame($event->getResponse()->getContent(), '[]');
    }

    public function testCorsGetResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new CorsListener();
        $dispatcher->addListener('onKernelResponse', [$listener, 'onKernelResponse']);

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', method: 'OPTIONS'),
            1,
            new Response(),
        );
        $dispatcher->dispatch($event, 'onKernelResponse');

        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-origin'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-methods'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-headers'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-expose-headers'));
    }
}
