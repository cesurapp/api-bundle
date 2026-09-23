<?php

namespace Cesurapp\ApiBundle\Tests\EventListener;

use Cesurapp\ApiBundle\EventListener\IsGrantedAnyAttributeListener;
use Cesurapp\ApiBundle\Security\Attribute\IsGrantedAny;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class IsGrantedAnyAttributeListenerTest extends TestCase
{
    public function testGrantedWhenAnyAttributeIsGranted(): void
    {
        $checked = [];
        $this->dispatch(['ROLE_EDITOR'], [new PostController(), 'edit'], ['post' => 'post-1'], $checked);

        // Stops at the first granted attribute; the subject is resolved from the argument name
        $this->assertSame([['ROLE_ADMIN', 'post-1'], ['ROLE_EDITOR', 'post-1']], $checked);
    }

    public function testDeniedWhenNoneIsGranted(): void
    {
        $checked = [];

        try {
            $this->dispatch([], [new PostController(), 'edit'], ['post' => 'post-1'], $checked);
            $this->fail('HttpException expected');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
            $this->assertSame('Post not found.', $exception->getMessage());
        }
    }

    public function testSingleAttributeString(): void
    {
        $this->assertSame(['ROLE_ADMIN'], new IsGrantedAny('ROLE_ADMIN')->attributes);
        $this->assertSame(['ROLE_A', 'ROLE_B'], new IsGrantedAny(['ROLE_A', 'ROLE_B'])->attributes);
    }

    /**
     * @param list<string> $granted
     */
    private function dispatch(array $granted, callable $controller, array $arguments, array &$checked): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(static function ($attribute, $subject) use ($granted, &$checked) {
            $checked[] = [$attribute, $subject];

            return in_array($attribute, $granted, true);
        });

        $event = new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            $controller,
            array_values($arguments),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );

        new IsGrantedAnyAttributeListener($checker)->onKernelControllerArguments($event);
    }
}

class PostController
{
    #[IsGrantedAny(['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_OWNER'], subject: 'post', message: 'Post not found.', statusCode: 404)]
    public function edit(string $post): void
    {
    }
}
