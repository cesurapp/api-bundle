<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\StickyUserLocale;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class StickyUserLocaleTest extends TestCase
{
    public function testRouteLocaleIsStoredAndReused(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $listener = new StickyUserLocale('en');

        $first = $this->request($session);
        $first->attributes->set('_locale', 'tr');
        $listener->onKernelRequest($this->event($first));
        $this->assertSame('tr', $session->get('_locale'));

        $second = $this->request($session);
        $listener->onKernelRequest($this->event($second));
        $this->assertSame('tr', $second->getLocale());
    }

    public function testDefaultLocaleWithoutStoredOne(): void
    {
        $request = $this->request(new Session(new MockArraySessionStorage()));
        new StickyUserLocale('de')->onKernelRequest($this->event($request));

        $this->assertSame('de', $request->getLocale());
    }

    public function testNoSessionNoChange(): void
    {
        $request = new Request();
        new StickyUserLocale('de')->onKernelRequest($this->event($request));

        $this->assertSame('en', $request->getLocale());
    }

    private function request(Session $session): Request
    {
        $request = new Request(cookies: [$session->getName() => 'id']);
        $request->setSession($session);

        return $request;
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
