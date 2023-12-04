<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\BodyJsonTransformer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BodyJsonTransformerTest extends KernelTestCase
{
    public function testJsonContentEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new BodyJsonTransformer();
        $dispatcher->addListener('onKernelRequest', [$listener, 'onKernelRequest']);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/', content: '{"test": "content"}'),
            1,
        );
        $dispatcher->dispatch($event, 'onKernelRequest');

        $this->assertSame($event->getRequest()->request->all(), ['test' => 'content']);
    }
}
