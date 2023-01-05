<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('anders_bjorkland_sylius_klarna_gateway');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
