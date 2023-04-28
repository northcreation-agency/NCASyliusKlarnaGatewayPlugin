<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface GatewayConfigResolverInterface
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getMerchantData(PaymentInterface $payment): ?MerchantData;
}
