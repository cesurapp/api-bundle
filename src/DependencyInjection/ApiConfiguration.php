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
                ->scalarNode('storage_path')->defaultValue('')->end()
                ->scalarNode('globals')->defaultValue('')->end()
                ->scalarNode('base_url')->defaultValue('')->end()
                ->scalarNode('ts_extra_path')->defaultValue('')->end()
                ->booleanNode('versioning')->defaultFalse()->end()
                ->arrayNode('cors_header')->defaultValue([
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

            ->end();

        return $treeBuilder;
    }
}
