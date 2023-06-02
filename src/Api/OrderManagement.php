<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OrderManagement implements OrderManagementInterface
{
    public const WIDGET_SNIPPET_KEY = 'html_snippet';

    public function __construct(
        private ParameterBagInterface $parameterBag,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private ClientInterface $client,
    ) {
    }

    /**
     * @throws ApiException
     */
    public function fetchCheckoutWidget(array $requestData, PaymentInterface &$payment): string
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($paymentMethod);

        /**
         * @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         *
         * @var string $klarnaUri
         */
        $klarnaUri = $this->parameterBag->get('north_creation_agency_sylius_klarna_gateway.checkout.uri');

        $klarnaOrderId = $this->getKlarnaReference($payment);

        $replaceReference = false;
        if ($klarnaOrderId !== null) {
            $shouldGetCheckout = false;

            try {
                $klarnaOrderData = $this->fetchOrderDataFromKlarnaWithPayment($payment);
                $shouldCreateNewCheckout = $this->canCreateNewCheckoutOrder($klarnaOrderData);
                if (!$shouldCreateNewCheckout) {
                    $klarnaUri .= '/' . $klarnaOrderId;
                } else {
                    $replaceReference = true;
                }
            } catch (RequestException $exception) {
                $response = $exception->getResponse();
                if ($response !== null && $response->getStatusCode() === 404) {
                    $shouldGetCheckout = true;
                }
            }

            if ($shouldGetCheckout) {
                try {
                    $snippet = $this->getCheckoutWidget($klarnaOrderId, $basicAuthString);

                    return $snippet;
                } catch (RequestException $exception) {
                    $response = $exception->getResponse();
                    if ($response !== null && $response->getStatusCode() !== 404) {
                        throw new ApiException($exception->getMessage(), $response->getStatusCode());
                    }
                }
            }
        }

        try {
            $response = $this->client->request(
                'POST',
                $klarnaUri,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($requestData),
                ],
            );

            $contents = json_decode($response->getBody()->getContents(), true);
            assert(is_array($contents));

            /** @var string|null $klarnaOrderId */
            $klarnaOrderId = $contents['order_id'] ?? null;
            if (is_string($klarnaOrderId)) {
                $this->addKlarnaReference($payment, $klarnaOrderId, $replaceReference);
            }

            /** @var string $snippet */
            $snippet = $contents[self::WIDGET_SNIPPET_KEY] ?? throw new \Exception(
                'Expected to find key ' . self::WIDGET_SNIPPET_KEY . ' but none were found in response.',
                500,
            );
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $requestStatus = $response !== null ? $response->getStatusCode() : 403;
            $errorMessage =
                $response !== null ?
                    $response->getBody()->getContents() : 'Something went wrong with request.'
            ;

            throw new ApiException($errorMessage, $requestStatus);
        } catch (GuzzleException $e) {
            $requestStatus = 500;
            $errorMessage = $e->getMessage();

            throw new ApiException($errorMessage, $requestStatus);
        }

        return $snippet;
    }

    /**
     * @throws ApiException
     * @throws RequestException
     */
    private function getCheckoutWidget(string $orderId, string $basicAuthString): string
    {
        /** @var string $klarnaUri */
        $klarnaUri = $this->parameterBag->get('north_creation_agency_sylius_klarna_gateway.checkout.uri');
        $klarnaUri = str_ends_with($klarnaUri, '/') ? $klarnaUri . $orderId : $klarnaUri . '/' . $orderId;

        try {
            $response = $this->client->request(
                'GET',
                $klarnaUri,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                ],
            );

            $contents = json_decode($response->getBody()->getContents(), true);
            assert(is_array($contents));

            /** @var string $snippet */
            $snippet = $contents[self::WIDGET_SNIPPET_KEY] ?? throw new ApiException(
                'Expected to find key ' . self::WIDGET_SNIPPET_KEY . ' but none were found in response.',
                500,
            );
        } catch (GuzzleException $e) {
            $requestStatus = 500;
            $errorMessage = $e->getMessage();

            throw new ApiException($errorMessage, $requestStatus);
        }

        return $snippet;
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

    public function getKlarnaReference(PaymentInterface $payment): ?string
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_id', $details)) {
            return null;
        }

        /** @var string $klarnaOrderId */
        $klarnaOrderId = $details['klarna_order_id'];

        return $klarnaOrderId;
    }

    private function addKlarnaReference(PaymentInterface $payment, string $reference, bool $replaceReference = false): void
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_id', $details) || $replaceReference) {
            $details['klarna_order_id'] = $reference;
        }

        $payment->setDetails($details);
    }
}
