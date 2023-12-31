<?php

namespace Cesurapp\ApiBundle\DependencyInjection;

use Cesurapp\ApiBundle\AbstractClass\ApiController;
use Cesurapp\ApiBundle\Response\ApiResourceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ApiExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $acs = [];
        if ($container->hasExtension('security')) {
            $all = $container->getExtensionConfig('security');
            foreach ($all as $config) {
                if (isset($config['access_control'])) {
                    $acs += $config['access_control'];
                }
            }
        }

        $container->setParameter('api.thor.access_control', $acs);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // Register Configuration
        foreach ($this->processConfiguration(new ApiConfiguration(), $configs) as $key => $value) {
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

        // Register Api Resources
        $container->registerForAutoconfiguration(ApiResourceInterface::class)
            ->addTag('resources')
            ->setLazy(true);

        // Load Services
        (new PhpFileLoader($container, new FileLocator(__DIR__)))->load('Services.php');
    }
}
