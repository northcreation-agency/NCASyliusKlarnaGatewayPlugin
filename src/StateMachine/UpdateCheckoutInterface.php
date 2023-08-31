<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use Sylius\Component\Core\Model\OrderInterface;

interface UpdateCheckoutInterface
{
    public function updateAddress(OrderInterface $order): void;
}
