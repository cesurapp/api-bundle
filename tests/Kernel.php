<?php

namespace Cesurapp\ApiBundle\Tests;

use Cesurapp\ApiBundle\ApiBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
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
            new DoctrineBundle(),
            new ApiBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
        ]);

        // Doctrine Bundle Default Configuration
        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'url' => 'sqlite:///%kernel.project_dir%/var/database.sqlite',
            ],
            'orm' => [
                'auto_mapping' => true,
                'controller_resolver' => [
                    'auto_mapping' => false,
                ],
                'mappings' => [
                    'App' => [
                        'is_bundle' => false,
                        'dir' => __DIR__.'/_App/Entity',
                        'prefix' => 'Cesurapp\ApiBundle\Tests\_App\Entity',
                        'alias' => 'Cesurapp\ApiBundle\Tests',
                        'type' => 'attribute',
                    ],
                ],
            ],
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->load('Cesurapp\\ApiBundle\\Tests\\_App\\Dto\\', '_App/Dto');
        $services->load('Cesurapp\\ApiBundle\\Tests\\_App\\Repository\\', '_App/Repository');
        $services->load('Cesurapp\\ApiBundle\\Tests\\_App\\Resources\\', '_App/Resources');
        $services->load('Cesurapp\\ApiBundle\\Tests\\_App\\EventListener\\', '_App/EventListener');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('_App/Controller', 'attribute');
        $routes->import('../src/Thor/Controller', 'attribute');
    }
}
