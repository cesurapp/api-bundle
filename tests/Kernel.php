<?php

namespace Cesurapp\ApiBundle\Tests;

use Cesurapp\ApiBundle\ApiBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Create App Test Kernel.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new ApiBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
        ]);

        $container->extension('api', [
            'storage_path' => 'adasd',
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->load('Cesurapp\\ApiBundle\\Tests\\_App\\Dto\\', '_App/Dto');
        $services->load('Cesurapp\\ApiBundle\\Tests\\_App\\Resources\\', '_App/Resources');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('_App/Controller', 'attribute');
    }
}
