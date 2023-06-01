<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Service;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface SupportedPaymentCheckerInterface
{
    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool;
}
