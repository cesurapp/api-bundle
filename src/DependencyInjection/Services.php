<?php

namespace Cesurapp\ApiBundle\DependencyInjection;

use Cesurapp\ApiBundle\ArgumentResolver\DtoResolver;
use Cesurapp\ApiBundle\EventListener\ControllerResultConverter;
use Cesurapp\ApiBundle\EventListener\StickyUserLocale;
use Cesurapp\ApiBundle\Thor\Controller\ThorController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    // Set Autoconfigure
    $services = $container->services()->defaults()->autowire()->autoconfigure();

    // Services
    $services->load('Cesurapp\\ApiBundle\\EventListener\\', '../EventListener/');
    $services->load('Cesurapp\\ApiBundle\\Validator\\', '../Validator/');
    $services->set(StickyUserLocale::class)->arg('$defaultLocale', '%kernel.default_locale%');
    $services->set(ControllerResultConverter::class)->arg('$exportMaxRows', '%api.export_max_rows%');

    // Argument Resolver
    $services->set(DtoResolver::class)->tag('controller.argument_value_resolver', ['priority' => 19]);

    // Thor
    $services->load('Cesurapp\\ApiBundle\\Thor\\Command\\', '../Thor/Command/');
    $thorExtractor = $services->load('Cesurapp\\ApiBundle\\Thor\\Extractor\\', '../Thor/Extractor/');
    $services->set(ThorController::class, ThorController::class);
    if ('test' === $container->env()) {
        $thorExtractor->public();
    }
};
