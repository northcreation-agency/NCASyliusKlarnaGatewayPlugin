<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class NorthCreationAgencySyliusKlarnaGatewayExtension extends Extension
{
    /**
     * @psalm-suppress UnusedVariable
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->resolveEnvPlaceholders(true);

        if (key_exists('cypher', $config)) {
            /** @var string $cypherKey */
            $cypherKey = $config['cypher']['key'] ?? '';
            $container->setParameter(
                'north_creation_agency_sylius_klarna_gateway.cypher.key',
                $cypherKey
            );
        }

        if (key_exists('checkout', $config)) {
            /** @var string|null $checkoutUri */
            $checkoutUri = $config['checkout']['uri'] ?? '';
            $container->setParameter(
                'north_creation_agency_sylius_klarna_gateway.checkout.uri',
                $checkoutUri
            );
        }
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }
}
