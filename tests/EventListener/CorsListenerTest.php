<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\CorsListener;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Preflight mirrors ANY origin: a tenant origin is only known after routing, and a preflight
     * that never names it blocks every non-simple request from that origin. The verdict is
     * cacheable (Max-Age), and Vary: Origin keeps a shared cache from handing it to another origin.
     */
    public function testCorsOptionsRequestMirrorsAnyOrigin(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new CorsListener(new ParameterBag([]));
        $dispatcher->addListener('onKernelRequest', [$listener, 'onKernelRequest']);

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', method: 'OPTIONS', server: ['HTTP_ORIGIN' => 'https://tenant.example']),
            1,
        );
        $dispatcher->dispatch($event, 'onKernelRequest');

        $response = $event->getResponse();
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('https://tenant.example', $response->headers->get('access-control-allow-origin'));
        $this->assertSame('true', $response->headers->get('access-control-allow-credentials'));
        $this->assertSame((string) CorsListener::PREFLIGHT_MAX_AGE, $response->headers->get('access-control-max-age'));
        $this->assertContains('Origin', $response->headers->all('vary'));
    }

    /** A non-CORS OPTIONS (no Origin header) is answered bare — there is nothing to mirror. */
    public function testCorsOptionsRequestWithoutOriginHasNoAllowOrigin(): void
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

        $this->assertFalse($event->getResponse()->headers->has('access-control-allow-origin'));
    }

    /**
     * The boundary that makes the open preflight safe: a real response still names only a
     * configured origin. An unknown origin gets no Access-Control-Allow-Origin, so the browser
     * withholds the body from it.
     */
    public function testCorsResponseDoesNotMirrorUnknownOrigin(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = new CorsListener(new ParameterBag([
            'api.cors_header' => [],
            'api.cors_allowed_origin' => ['https://panel.example'],
        ]));
        $dispatcher->addListener('onKernelResponse', [$listener, 'onKernelResponse']);

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', server: ['HTTP_ORIGIN' => 'https://tenant.example']),
            1,
            new Response(),
        );
        $dispatcher->dispatch($event, 'onKernelResponse');

        $this->assertFalse($event->getResponse()->headers->has('access-control-allow-origin'));
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

        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-methods'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-allow-headers'));
        $this->assertTrue($event->getResponse()->headers->has('access-control-expose-headers'));
    }

    #[DataProvider('localOrigins')]
    public function testLocalOriginIsMatchedOnTheExactHost(string $origin, bool $allowed): void
    {
        $event = $this->responseEvent($origin, ['api.cors_header' => [], 'api.cors_allowed_origin' => []]);

        $this->assertSame($allowed ? $origin : null, $event->getResponse()->headers->get('access-control-allow-origin'));
        $this->assertSame($allowed ? 'true' : null, $event->getResponse()->headers->get('access-control-allow-credentials'));
    }

    public static function localOrigins(): iterable
    {
        yield ['http://localhost', true];
        yield ['http://localhost:9000', true];
        yield ['https://localhost', true];
        yield ['capacitor://localhost', true];
        yield ['ionic://localhost', true];
        yield ['file://', true];
        yield ['http://localhost.attacker.com', false];
        yield ['http://localhostevil.io', false];
        yield ['https://localhost@evil.com', false];
        yield ['http://localhost/path', false];
        yield ['ftp://localhost', false];
    }

    public function testResponseVariesOnOrigin(): void
    {
        $event = $this->responseEvent(null, ['api.cors_header' => [], 'api.cors_allowed_origin' => []]);
        $this->assertSame(['Origin'], $event->getResponse()->getVary());

        // Not added twice
        $listener = new CorsListener(new ParameterBag([]));
        $listener->onKernelResponse($event);
        $this->assertSame(['Origin'], $event->getResponse()->getVary());
    }

    public function testHeadRequestReachesRouting(): void
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/health', 'HEAD'), HttpKernelInterface::MAIN_REQUEST);
        new CorsListener(new ParameterBag([]))->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testUnsupportedMethodIsRefused(): void
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/', 'TRACE'), HttpKernelInterface::MAIN_REQUEST);
        new CorsListener(new ParameterBag([]))->onKernelRequest($event);

        $this->assertSame(405, $event->getResponse()->getStatusCode());
        $this->assertStringContainsString('HEAD', $event->getResponse()->headers->get('Allow'));
    }

    /**
     * `*` does not cover Authorization and is literal for credentialed requests: a preflight gets
     * the headers it asked for when the configuration allows any header.
     */
    public function testPreflightAnswersRequestedHeadersForWildcard(): void
    {
        $bag = ['api.cors_header' => [['name' => 'Access-Control-Allow-Headers', 'value' => '*']]];
        $request = Request::create('/', 'OPTIONS', server: ['HTTP_ORIGIN' => 'https://tenant.example', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type']);

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        new CorsListener(new ParameterBag($bag))->onKernelRequest($event);
        $this->assertSame('authorization,content-type', $event->getResponse()->headers->get('access-control-allow-headers'));

        // An explicit list is kept as configured
        $bag = ['api.cors_header' => [['name' => 'Access-Control-Allow-Headers', 'value' => 'Content-Type,Authorization']]];
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        new CorsListener(new ParameterBag($bag))->onKernelRequest($event);
        $this->assertFalse($event->getResponse()->headers->has('access-control-allow-headers'));
    }

    private function responseEvent(?string $origin, array $parameters): ResponseEvent
    {
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/', server: null === $origin ? [] : ['HTTP_ORIGIN' => $origin]),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );
        new CorsListener(new ParameterBag($parameters))->onKernelResponse($event);

        return $event;
    }
}
