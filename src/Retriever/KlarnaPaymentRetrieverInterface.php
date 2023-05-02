<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface KlarnaPaymentRetrieverInterface
{
    public function retrieveFromOrder(OrderInterface $order): ?PaymentInterface;
}
