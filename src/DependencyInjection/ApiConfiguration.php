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
        $treeBuilder->getRootNode()
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
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('authHeader')
                                    ->defaultValue([
                                        'Content-Type' => 'application/json',
                                        'Authorization' => 'Bearer Token',
                                    ])
                                ->end()
                                ->variableNode('query')->defaultValue([])->end()
                                ->variableNode('request')->defaultValue([])->end()
                                ->variableNode('header')
                                    ->defaultValue([
                                        'Content-Type' => 'application/json',
                                        'Accept' => 'application/json',
                                    ])
                                ->end()
                                ->variableNode('response')->defaultValue([])->end()
                                ->booleanNode('isAuth')->defaultValue(true)->end()
                                ->booleanNode('isPaginate')->defaultValue(false)->end()
                                ->booleanNode('isHidden')->defaultValue(false)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
