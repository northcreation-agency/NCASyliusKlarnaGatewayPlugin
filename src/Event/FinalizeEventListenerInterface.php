<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Event;

use Sylius\Component\Core\Model\OrderInterface;

interface FinalizeEventListenerInterface
{
    public function checkPayment(OrderInterface $order): void;
}
