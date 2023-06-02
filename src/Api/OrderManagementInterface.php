<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface OrderManagementInterface
{
    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * @throws ApiException
     */
    public function fetchOrderDataFromKlarnaWithPayment(PaymentInterface $payment): array;

    /**
     * @throws ApiException
     */
    public function fetchOrderDataFromKlarna(OrderInterface $order): array;

    public function getStatus(array $data): string;

    public function isCancelled(array $data): bool;

    public function canCreateNewCheckoutOrder(array $data): bool;

    public function fetchCheckoutWidget(array $requestData, PaymentInterface &$payment): string;

    public function getKlarnaReference(PaymentInterface $payment): ?string;
}
