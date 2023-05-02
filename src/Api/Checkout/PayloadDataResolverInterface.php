<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface PayloadDataResolverInterface
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getMerchantData(PaymentInterface $payment): ?MerchantData;

    public function getOptionsData(PaymentInterface $payment): OptionsData;

    /**
     * @param array<string, string> $customerData
     */
    public function getCustomerData(array $customerData): CustomerData;
}
