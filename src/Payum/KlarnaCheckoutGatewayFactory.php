<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum;

use AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\Action\StatusAction;
use AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\ValueObject\KlarnaApi;
use Payum\Core\Bridge\Spl\ArrayObject;

class KlarnaCheckoutGatewayFactory extends \Payum\Core\GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name' => 'klarna_checkout',
            'payum.factory_title' => 'Klarna Checkout',
            'payum.action.status' => new StatusAction(),
        ]);

        $config['payum.api'] = function (ArrayObject $config): KlarnaApi {
            $apiKey = $config['api_key'] ?? "....";
            assert(is_string($apiKey));

            return new KlarnaApi($apiKey);
        };
    }
}
