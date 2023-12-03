<?php

namespace Cesurapp\ApiBundle\DependencyInjection;

use Cesurapp\ApiBundle\ArgumentResolver\DtoResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    // Set Autoconfigure
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    // Event Listener
    $services->load('Cesurapp\\ApiBundle\\EventListener\\', '../EventListener/');

    // Argument Resolver
    $services->set(DtoResolver::class)->tag('controller.argument_value_resolver', ['priority' => 50]);
};
