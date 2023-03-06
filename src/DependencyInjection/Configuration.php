<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable, MixedMethodCall, PossiblyUndefinedMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('north_creation_agency_sylius_klarna_gateway');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('cypher')
                    ->children()
                        ->scalarNode('key')->end()
                    ->end()
                ->end()
                ->arrayNode('checkout')
                    ->children()
                        ->booleanNode('headless')->end()
                        ->booleanNode('silent_exception')->end()
                        ->scalarNode('read_order')->end()
                        ->scalarNode('push_confirmation')->end()
                        ->scalarNode('uri')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
