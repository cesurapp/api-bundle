<?php

namespace Cesurapp\ApiBundle\DependencyInjection;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\EventListener\BodyJsonTransformer;
use Cesurapp\ApiBundle\EventListener\CorsListener;
use Cesurapp\ApiBundle\EventListener\StickyUserLocale;
use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ApiExtension extends Extension implements PrependExtensionInterface
{
    public const string RESOURCE_TAG = 'api.resource';

    public function prepend(ContainerBuilder $container): void
    {
        $acs = [];
        if ($container->hasExtension('security')) {
            foreach ($container->getExtensionConfig('security') as $config) {
                if (isset($config['access_control'])) {
                    // Lists: `+` would drop every rule whose index an earlier config already used
                    $acs = [...$acs, ...$config['access_control']];
                }
            }
        }

        $container->setParameter('api.thor.access_control', $acs);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new ApiConfiguration(), $configs);

        // Register Configuration
        foreach ($config as $key => $value) {
            if (is_array($value) && !array_is_list($value)) {
                foreach ($value as $k => $v) {
                    $container->getParameterBag()->set('api.'.$key.'.'.$k, $v);
                }
            } else {
                $container->getParameterBag()->set('api.'.$key, $value);
            }
        }

        $container->registerForAutoconfiguration(ApiController::class)
            ->addTag('controller.service_arguments');

        // Register Api Resources (the ServiceLocator already instantiates them on demand)
        $container->registerForAutoconfiguration(ApiResourceInterface::class)
            ->addTag(self::RESOURCE_TAG);

        // Load Services
        new PhpFileLoader($container, new FileLocator(__DIR__))->load('Services.php');

        // Optional Listeners
        foreach (['cors' => CorsListener::class, 'json_body' => BodyJsonTransformer::class, 'sticky_locale' => StickyUserLocale::class] as $key => $listener) {
            if (!$config[$key]) {
                $container->removeDefinition($listener);
            }
        }
    }
}
