<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface ActivatePaymentInterface
{
    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function activate(OrderInterface $order): void;

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendCaptureRequest(SyliusPaymentInterface $payment): void;

    public function replacePlaceholder(string $replacement, string $string): string;

    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool;
}
