<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\CorsListener;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsListenerTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testCorsOptionsRequest(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new CorsListener(new ParameterBag([]));
        $dispatcher->addListener('onKernelRequest', [$listener, 'onKernelRequest']);

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', method: 'OPTIONS'),
            1,
        );
        $dispatcher->dispatch($event, 'onKernelRequest');

        $this->assertSame($event->getResponse()->getContent(), '[]');
    }

    public function testCorsOptionsRequestContainer(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', method: 'OPTIONS'),
            1,
        );
        $dispatcher->dispatch($event, KernelEvents::REQUEST);

        $this->assertSame($event->getResponse()->getContent(), '[]');
    }

    public function testCorsGetResponse(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new CorsListener(new ParameterBag([
            'api.cors_header' => [
                ['name' => 'Access-Control-Allow-Origin', 'value' => '*'],
                ['name' => 'Access-Control-Allow-Methods', 'value' => 'GET,POST,PUT,PATCH,DELETE'],
                ['name' => 'Access-Control-Allow-Headers', 'value' => '*'],
                ['name' => 'Access-Control-Expose-Headers', 'value' => 'Content-Disposition'],
            ],
        ]));
        $dispatcher->addListener('onKernelResponse', [$listener, 'onKernelResponse']);

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
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

    public function testCorsGetResponseContainer(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            1,
            new Response(),
        );
        $dispatcher->dispatch($event, KernelEvents::RESPONSE);

        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-origin'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-methods'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-headers'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-expose-headers'));
    }
}
