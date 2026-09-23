<?php

namespace Cesurapp\ApiBundle\Tests\Thor;

use Cesurapp\ApiBundle\DependencyInjection\ApiExtension;
use Cesurapp\ApiBundle\Thor\Extractor\ThorExtractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiDocumentationTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testViewDocumentation(): void
    {
        self::bootKernel();
        $response = self::$kernel->handle(Request::create('/thor'));
        $this->assertTrue($response->isOk());
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));

        // Public page: no source tree, pinned + integrity checked assets
        $this->assertStringNotContainsString(self::$kernel->getProjectDir(), $response->getContent());
        $this->assertStringNotContainsString('vue@3/', $response->getContent());
        $this->assertStringContainsString('integrity="sha384-', $response->getContent());
    }

    public function testDownloadDocumentation(): void
    {
        self::bootKernel();
        /** @var BinaryFileResponse $response */
        $response = self::$kernel->handle(Request::create('/thor/download'));
        $this->assertTrue($response->isOk());
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertTrue($response->getFile()->isFile());
        $this->assertStringContainsString('Api.tar.gz', $response->headers->get('Content-Disposition'));
    }

    /**
     * Outside debug the page and the archive are built once, then served from the cache directory.
     */
    public function testBuiltOnceOutsideDebug(): void
    {
        self::bootKernel(['debug' => false]);

        /** @var BinaryFileResponse $download */
        $download = self::$kernel->handle(Request::create('/thor/download'));
        file_put_contents($download->getFile()->getPathname(), 'cached archive');
        $download = self::$kernel->handle(Request::create('/thor/download'));
        $this->assertSame('cached archive', file_get_contents($download->getFile()->getPathname()));

        self::$kernel->handle(Request::create('/thor'));
        file_put_contents(self::$kernel->getCacheDir().'/thor/index.html', 'cached page');
        $this->assertSame('cached page', self::$kernel->handle(Request::create('/thor'))->getContent());

        // Start the next run from a fresh build
        unlink($download->getFile()->getPathname());
        unlink(self::$kernel->getCacheDir().'/thor/index.html');
    }

    public function testSourceLocationIsOnlyExtractedInDev(): void
    {
        foreach ($this->routes() as $route) {
            $this->assertArrayNotHasKey('controllerPath', $route);
            $this->assertArrayNotHasKey('controllerLine', $route);
            $this->assertArrayNotHasKey('controller', $route);
            $this->assertArrayHasKey('controllerResponseType', $route);
        }
    }

    public function testInvokableControllerIsDocumented(): void
    {
        $this->assertSame('Invokable Controller', $this->route('/v1/invokable')['title']);
    }

    public function testRolesAreThePermissionArgumentsOnly(): void
    {
        $this->assertSame(['ROLE_USER_LIST', 'ROLE_ADMIN'], $this->route('/v1/users')['roles']);
    }

    public function testMessageTypesComeFromTheAddMessageCalls(): void
    {
        $this->assertSame(['success' => '?array'], $this->route('/v1/users')['response'][200]['message']);
        $this->assertSame(['error' => '?array'], $this->route('/v1/auth/api-response')['response'][200]['message']);
    }

    /**
     * Every access_control list is kept: `+` on lists dropped the rules of a later config.
     */
    public function testAccessControlOfEveryConfigIsCollected(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class () extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'security';
            }
        });
        $container->prependExtensionConfig('security', ['access_control' => [['path' => '^/api', 'roles' => 'ROLE_USER']]]);
        $container->prependExtensionConfig('security', ['access_control' => [['path' => '^/admin', 'roles' => 'ROLE_ADMIN']]]);

        new ApiExtension()->prepend($container);

        $this->assertSame(['^/admin', '^/api'], array_column($container->getParameter('api.thor.access_control'), 'path'));
    }

    private function route(string $path): array
    {
        foreach ($this->routes() as $route) {
            if ($route['path'] === $path) {
                return $route;
            }
        }

        $this->fail(sprintf('Route "%s" is not documented.', $path));
    }

    /**
     * @return list<array>
     */
    private function routes(): array
    {
        self::bootKernel();
        $data = self::getContainer()->get(ThorExtractor::class)->extractData();

        return array_values(array_filter($data, static fn ($v, $k) => !str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_BOTH));
    }
}
