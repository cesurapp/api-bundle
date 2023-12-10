<?php

namespace Cesurapp\ApiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ApiConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('api');

        // Thor Configuration
        $treeBuilder->getRootNode() // @phpstan-ignore-line
            ->children()
                ->booleanNode('exception_converter')->defaultTrue()->end()
                ->arrayNode('cors_header')
                    ->defaultValue([
                        ['name' => 'Access-Control-Allow-Origin', 'value' => '*'],
                        ['name' => 'Access-Control-Allow-Methods', 'value' => 'GET,POST,PUT,PATCH,DELETE'],
                        ['name' => 'Access-Control-Allow-Headers', 'value' => '*'],
                        ['name' => 'Access-Control-Expose-Headers', 'value' => 'Content-Disposition'],
                    ])
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('value')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('thor')->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base_url')->defaultNull()->end()
                        ->arrayNode('global_config')
                            ->ignoreExtraKeys(false)
                            ->defaultValue([
                                'authHeader' => [
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer Token',
                                ],
                                'query' => [],
                                'request' => [],
                                'header' => [
                                    'Content-Type' => 'application/json',
                                    'Accept' => 'application/json',
                                ],
                                'response' => [],
                                'isAuth' => true,
                                'isHidden' => false,
                                'isPaginate' => false,
                            ])
                            ->arrayPrototype()->ignoreExtraKeys(false)->addDefaultsIfNotSet()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
