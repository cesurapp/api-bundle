<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\GlobalExceptionHandler;
use Cesurapp\ApiBundle\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
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

    public function testUnexpectedServerErrorMessageIsHiddenOutsideDebug(): void
    {
        $response = $this->handle(new \RuntimeException("SQLSTATE[23000]: Duplicate entry 'admin@corp.com'"), debug: false);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['type' => 'RuntimeException', 'code' => 500, 'message' => GlobalExceptionHandler::GENERIC_MESSAGE], json_decode($response->getContent(), true));
    }

    public function testUnexpectedServerErrorMessageIsShownInDebug(): void
    {
        $response = $this->handle(new \RuntimeException('SQLSTATE[23000]'), debug: true);

        $this->assertSame('SQLSTATE[23000]', json_decode($response->getContent(), true)['message']);
    }

    public function testClientErrorMessagesStayVisible(): void
    {
        $this->assertSame('Wrong OTP key!', json_decode($this->handle(new \RuntimeException('Wrong OTP key!', 403), debug: false)->getContent(), true)['message']);
        $this->assertSame('Down for maintenance', json_decode($this->handle(new HttpException(503, 'Down for maintenance'), debug: false)->getContent(), true)['message']);
    }

    #[DataProvider('nonErrorCodes')]
    public function testNonErrorCodeIsNotUsedAsStatus(int $code): void
    {
        $this->assertSame(500, $this->handle(new \LogicException('x', $code), debug: false)->getStatusCode());
    }

    public static function nonErrorCodes(): iterable
    {
        yield [101];
        yield [204];
        yield [302];
        yield [1062];
    }

    public function testHttpExceptionHeadersAreKept(): void
    {
        $response = $this->handle(new UnauthorizedHttpException('Signature', 'Invalid link.'), debug: false);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Signature', $response->headers->get('WWW-Authenticate'));
    }

    private function handle(\Throwable $exception, bool $debug): \Symfony\Component\HttpFoundation\Response
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $listener = new GlobalExceptionHandler($translator, new ParameterBag([
            'api.exception_converter' => true,
            'kernel.environment' => 'prod',
            'kernel.debug' => $debug,
        ]));

        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $exception);
        $listener->onKernelException($event);

        return $event->getResponse();
    }
}
