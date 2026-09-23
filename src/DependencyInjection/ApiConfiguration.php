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
                ->booleanNode('cors')->defaultTrue()->info('Register the CORS listener (preflight + Access-Control-* headers).')->end()
                ->booleanNode('json_body')->defaultTrue()->info('Decode application/json request bodies into $request->request.')->end()
                ->booleanNode('sticky_locale')->defaultTrue()->info('Keep the _locale of the route in the session.')->end()
                ->integerNode('export_max_rows')->defaultValue(100000)->min(0)->info('Max rows of an ?export=csv|xls download (0 = unlimited). ApiResponse::setExportLimit() overrides it per response.')->end()
                ->arrayNode('cors_header')
                    ->defaultValue([
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
                ->arrayNode('cors_allowed_origin')
                    ->info('Exact origins (scheme://host[:port]) that get Access-Control-Allow-Origin with credentials. localhost / capacitor:// / ionic:// are always allowed.')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
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
