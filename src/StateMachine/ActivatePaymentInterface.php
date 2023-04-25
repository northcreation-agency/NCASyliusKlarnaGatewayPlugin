<?php

declare(strict_types=1);

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
     * Send capture request and returns status code
     *
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendCaptureRequest(SyliusPaymentInterface $payment): int;

    public function replacePlaceholder(string $replacement, string $string): string;

    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool;
}
