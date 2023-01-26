<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
                        ->scalarNode('uri')->end()
                        ->scalarNode('push_confirmation')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
