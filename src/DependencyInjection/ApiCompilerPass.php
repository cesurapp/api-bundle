<?php

namespace Cesurapp\ApiBundle\DependencyInjection;

use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Cesurapp\ApiBundle\Response\ApiResourceLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ApiCompilerPass implements CompilerPassInterface
{
    /** Tag of bundle versions before api.resource; still collected for manually tagged services. */
    public const string LEGACY_RESOURCE_TAG = 'resources';

    public function process(ContainerBuilder $container): void
    {
        // Init Resource Locator
        $resources = [];
        foreach (array_keys($container->findTaggedServiceIds(ApiExtension::RESOURCE_TAG)) as $id) {
            $resources[$id] = new Reference($id);
        }

        // The generic legacy tag may be used by other bundles too: only take actual resources
        foreach (array_keys($container->findTaggedServiceIds(self::LEGACY_RESOURCE_TAG)) as $id) {
            $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass() ?? $id);
            if (is_string($class) && is_subclass_of($class, ApiResourceInterface::class)) {
                $resources[$id] = new Reference($id);
            }
        }

        $container
            ->register(ApiResourceLocator::class, ApiResourceLocator::class)
            ->addArgument(ServiceLocatorTagPass::register($container, $resources));
    }
}
