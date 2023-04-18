<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Verifier;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use Sylius\Component\Core\Model\OrderInterface;

interface OrderVerifierInterface
{
    public function verify(OrderInterface $order): StatusDO;
    public function update(OrderInterface $order): void;
}
