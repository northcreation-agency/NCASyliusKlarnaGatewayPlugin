<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Service;

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class SupportedPaymentChecker implements SupportedPaymentCheckerInterface
{
    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $paymentConfig = $paymentMethod->getGatewayConfig();

        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }
}
