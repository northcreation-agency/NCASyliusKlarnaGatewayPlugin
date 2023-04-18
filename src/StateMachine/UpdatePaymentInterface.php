<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use SM\SMException;
use Sylius\Component\Core\Model\OrderInterface;

interface UpdatePaymentInterface
{
    /**
     * @throws ApiException
     * @throws SMException
     */
    public function afterCreateOrder(OrderInterface $order): void;
}
