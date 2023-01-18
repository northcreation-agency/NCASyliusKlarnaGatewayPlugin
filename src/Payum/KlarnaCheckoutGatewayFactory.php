<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\Action\StatusAction;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\ValueObject\KlarnaApi;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class KlarnaCheckoutGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'klarna_checkout',
            'payum.factory_title' => 'Klarna Checkout',
            'payum.action.status' => new StatusAction(),
        ]);

        $config['payum.api'] = function (ArrayObject $config): KlarnaApi {
            $apiKey = $config['api_key'] ?? '....';
            assert(is_string($apiKey));

            return new KlarnaApi($apiKey);
        };
    }
}
