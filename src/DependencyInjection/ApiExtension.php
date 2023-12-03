<?php

namespace Cesurapp\ApiBundle\DependencyInjection;

use Cesurapp\ApiBundle\AbstractClass\AbstractApiController;
use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ApiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(AbstractApiController::class)
            ->addTag('controller.service_arguments');

        // Register Configuration
        foreach ($this->processConfiguration(new ApiConfiguration(), $configs) as $key => $value) {
            $container->getParameterBag()->set('api.'.$key, $value);
        }

        // Register Api Resources
        $container->registerForAutoconfiguration(ApiResourceInterface::class)
            ->addTag('resources')
            ->setLazy(true);

        // Load Services
        (new PhpFileLoader($container, new FileLocator(__DIR__)))->load('Services.php');
    }
}
