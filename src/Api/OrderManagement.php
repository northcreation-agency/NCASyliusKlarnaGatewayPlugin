<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use GuzzleHttp\ClientInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OrderManagement implements OrderManagementInterface
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private ClientInterface $client,
    ) {
    }

    public function fetchOrderDataFromKlarna(OrderInterface $order): array
    {
        $payment = $order->getPayments()->first();

        assert($payment instanceof PaymentInterface);

        return $this->fetchOrderDataFromKlarnaWithPayment($payment);
    }

    public function fetchOrderDataFromKlarnaWithPayment(PaymentInterface $payment): array
    {
        $paymentDetails = $payment->getDetails();

        /** @var ?string $klarnaOrderId */
        $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? null;
        assert($klarnaOrderId !== null);

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1) */
        $readOrderUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
        );
        assert(is_string($readOrderUrlTemplate));

        $readOrderUrl = str_replace('{order_id}', $klarnaOrderId, $readOrderUrlTemplate);

        /** @var ?PaymentMethodInterface $method */
        $method = $payment->getMethod();
        assert($method !== null);
        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);

        $response = $this->client->request(
            'GET',
            $readOrderUrl,
            [
                'headers' => [
                    'Authorization' => $basicAuthString,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        $dataContents = $response->getBody()->getContents();

        /** @var array $data */
        $data = json_decode($dataContents, true);

        return $data;
    }

    public function getStatus(array $data): string
    {
        /** @var string $status */
        $status = $data['status'] ?? '';

        return $status;
    }

    public function isCancelled(array $data): bool
    {
        return strtoupper($this->getStatus($data)) === self::STATUS_CANCELLED;
    }

    /**
     * If a Klarna Order is cancelled and no amount has been captured or refunded, we can allow for a new checkout to
     * be started.
     */
    public function canCreateNewCheckoutOrder(array $data): bool
    {
        /** @var array $captures */
        $captures = $data['captures'] ?? [];

        /** @var array $refunds */
        $refunds = $data['refunds'] ?? [];

        $hasTransactions = count($captures) > 0 || count($refunds) > 0;

        return $this->isCancelled($data) && !$hasTransactions;
    }
}
