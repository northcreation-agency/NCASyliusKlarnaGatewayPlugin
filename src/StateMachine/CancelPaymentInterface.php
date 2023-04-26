<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use SM\SMException;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface CancelPaymentInterface
{
    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws SMException
     */
    public function cancel(PaymentInterface $payment): void;

    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool;
}
