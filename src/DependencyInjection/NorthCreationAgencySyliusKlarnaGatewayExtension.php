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

        if (array_key_exists('cypher', $config)) {
            if (is_array($config['cypher']) && array_key_exists('key', $config['cypher'])) {
                /** @var string $includeShipping */
                $includeShipping = $config['cypher']['key'] ?? '';
                $container->setParameter(
                    'north_creation_agency_sylius_klarna_gateway.cypher.key',
                    $includeShipping,
                );
            }
        }

        if (array_key_exists('checkout', $config)) {
            if (is_array($config['checkout']) && array_key_exists('headless', $config['checkout'])) {
                /** @var bool|null $headless */
                $headless = $config['checkout']['headless'] ?? '';
                $container->setParameter(
                    'north_creation_agency_sylius_klarna_gateway.checkout.headless',
                    $headless,
                );
            }

            if (is_array($config['checkout']) && array_key_exists('silent_exception', $config['checkout'])) {
                /** @var bool|null $silentException */
                $silentException = $config['checkout']['silent_exception'] ?? '';
                $container->setParameter(
                    'north_creation_agency_sylius_klarna_gateway.checkout.silent_exception',
                    $silentException,
                );
            }

            if (is_array($config['checkout']) && array_key_exists('read_order', $config['checkout'])) {
                /** @var string|null $readOrder */
                $readOrder = $config['checkout']['read_order'] ?? '';
                $container->setParameter(
                    'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
                    $readOrder,
                );
            }

            if (is_array($config['checkout']) && array_key_exists('push_confirmation', $config['checkout'])) {
                /** @var string|null $pushConfirmation */
                $pushConfirmation = $config['checkout']['push_confirmation'] ?? '';
                $container->setParameter(
                    'north_creation_agency_sylius_klarna_gateway.checkout.push_confirmation',
                    $pushConfirmation,
                );
            }

            if (is_array($config['checkout']) && array_key_exists('uri', $config['checkout'])) {
                /** @var string|null $checkoutUri */
                $checkoutUri = $config['checkout']['uri'] ?? '';
                $container->setParameter(
                    'north_creation_agency_sylius_klarna_gateway.checkout.uri',
                    $checkoutUri,
                );
            }
        }

        if (array_key_exists('refund', $config)) {
            if (is_array($config['refund']) && array_key_exists('include_shipping', $config['refund'])) {
                /** @var bool $includeShipping */
                $includeShipping = $config['refund']['include_shipping'] ?? false;
                $container->setParameter(
                    'north_creation_agency_sylius_klarna_gateway.refund.include_shipping',
                    $includeShipping,
                );
            }
        }
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }
}
