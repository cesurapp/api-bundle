<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\BodyJsonTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class BodyJsonTransformerTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testJsonContentEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new BodyJsonTransformer();
        $dispatcher->addListener('onKernelRequest', [$listener, 'onKernelRequest']);

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', server: ['CONTENT_TYPE' => 'application/json'], content: '{"test": "content"}'),
            1,
        );
        $dispatcher->dispatch($event, 'onKernelRequest');

        $this->assertSame($event->getRequest()->request->all(), ['test' => 'content']);
    }

    public function testJsonContentEventContainer(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/v1/admin/home/1', method: 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"test": "content"}'),
            1,
        );
        $dispatcher->dispatch($event, KernelEvents::REQUEST);

        $this->assertSame($event->getRequest()->request->all(), ['test' => 'content']);
    }

    /**
     * text/plain, or no Content-Type at all, is sent cross-origin without a preflight: decoding it
     * would let any site post JSON with the user's cookies.
     */
    #[DataProvider('nonJsonContentTypes')]
    public function testNonJsonContentTypeIsNotDecoded(?string $contentType): void
    {
        $server = null === $contentType ? [] : ['CONTENT_TYPE' => $contentType];
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', 'POST', server: $server, content: '{"role": "ROLE_ADMIN"}'),
            HttpKernelInterface::MAIN_REQUEST,
        );
        new BodyJsonTransformer()->onKernelRequest($event);

        $this->assertSame([], $event->getRequest()->request->all());
    }

    public static function nonJsonContentTypes(): iterable
    {
        yield 'text/plain' => ['text/plain'];
        yield 'form' => ['application/x-www-form-urlencoded'];
        yield 'none' => [null];
    }

    #[DataProvider('jsonContentTypes')]
    public function testJsonContentTypesAreDecoded(string $contentType): void
    {
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', 'POST', server: ['CONTENT_TYPE' => $contentType], content: "\n [1, {\"a\": 2}] "),
            HttpKernelInterface::MAIN_REQUEST,
        );
        new BodyJsonTransformer()->onKernelRequest($event);

        $this->assertSame([1, ['a' => 2]], $event->getRequest()->request->all());
    }

    public static function jsonContentTypes(): iterable
    {
        yield ['application/json'];
        yield ['application/json; charset=utf-8'];
        yield ['application/merge-patch+json'];
    }

    public function testBadJsonIsBadRequest(): void
    {
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"a":'),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->expectException(BadRequestHttpException::class);
        new BodyJsonTransformer()->onKernelRequest($event);
    }
}
