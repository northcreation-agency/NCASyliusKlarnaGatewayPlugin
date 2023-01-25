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
            $container->setParameter(
                'north_creation_agency_sylius_klarna_gateway.cypher.key',
                $config['cypher']['key'] ?? ''
            );
        }

        if (key_exists('checkout', $config)) {
            $container->setParameter(
                'north_creation_agency_sylius_klarna_gateway.checkout.uri',
                $config['checkout']['uri'] ?? ''
            );
        }
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }
}
