<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\AlreadyCancelledException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\AlreadyRefundedException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

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

        $klarnaOrderId = $this->getKlarnaOrderId($payment);

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
                    $orderData = $this->fetchCheckoutOrderData($klarnaOrderId, $basicAuthString);

                    if (!$this->hasDiff($requestData, $orderData)) {
                        $snippet = $this->getCheckoutWidget($klarnaOrderId, $basicAuthString);

                        return $snippet;
                    }
                    $klarnaUri .= '/' . $klarnaOrderId;
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
                $this->addKlarnaOrderId($payment, $klarnaOrderId, $replaceReference);
            }

            /** @var string|null $klarnaOrderReference */
            $klarnaOrderReference = $contents['klarna_reference'] ?? null;
            if (is_string($klarnaOrderReference)) {
                $this->addKlarnaOrderReference($payment, $klarnaOrderReference, $replaceReference);
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

    public function fetchCheckoutOrderData(string $orderId, string $basicAuthString): array
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

            $data = $contents;
        } catch (GuzzleException $e) {
            $requestStatus = 500;
            $errorMessage = $e->getMessage();

            throw new ApiException($errorMessage, $requestStatus);
        }

        return $data;
    }

    /**
     * @throws ApiException
     */
    public function updateCheckoutAddress(PaymentInterface $payment, array $addressData): void
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($paymentMethod);

        $klarnaOrderId = $this->getKlarnaOrderId($payment);

        if ($klarnaOrderId === null) {
            throw new ApiException('Klarna order ID not found.');
        }

        $klarnaUri = $this->parameterBag->get('north_creation_agency_sylius_klarna_gateway.checkout.uri');

        assert(is_string($klarnaUri));

        $klarnaUri = $klarnaUri . '/' . $klarnaOrderId;

        try {
            $this->client->request(
                'POST',
                $klarnaUri,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($addressData),
                ],
            );
        } catch (GuzzleException $exception) {
            throw new ApiException('Failed to update checkout address.', $exception->getCode(), $exception);
        }
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

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendCaptureRequest(PaymentInterface $payment, array $payload): int
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);

        $paymentDetails = $payment->getDetails();

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $basicAuthString = $this->getBasicAuth($paymentMethod);

        /** @var ?string $klarnaOrderId */
        $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? null;
        assert($klarnaOrderId !== null);

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         * @var string $orderManagementUrlTemplate
         */
        $orderManagementUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
        );

        $captureUrl = str_replace('{order_id}', $klarnaOrderId, $orderManagementUrlTemplate) . '/captures';

        try {
            $response = $this->client->request(
                'POST',
                $captureUrl,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($payload),
                ],
            );

            $status = $response->getStatusCode();
        } catch (GuzzleException $e) {
            if (str_contains($e->getMessage(), StatusDO::ERROR_CODE_CAPTURE_NOT_ALLOWED)) {
                $order = $payment->getOrder();

                assert($order instanceof OrderInterface);

                $orderData = $this->fetchOrderDataFromKlarna($order);

                if ($this->isCancelled($orderData)) {
                    throw new AlreadyCancelledException('Can not capture already cancelled order');
                }

                $isRefunded = $this->isRefunded($orderData);
                if ($isRefunded) {
                    throw new AlreadyRefundedException('Could not capture payment. Payment already refunded!');
                }

                $isCaptured = $this->isCaptured($orderData);
                $status = $isCaptured ? StatusDO::PAYMENT_ALREADY_CAPTURED : 400;
            } else {
                throw new ApiException('Activation of Klarna payment was not successful: ' . $e->getMessage());
            }
        }

        if ($status !== Response::HTTP_CREATED && $status !== Response::HTTP_ACCEPTED) {
            throw new ApiException('Activation of Klarna payment was not successful');
        }

        return $status;
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendRefundRequest(PaymentInterface $payment, array $payload): void
    {
        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         * @var string $orderManagementUrlTemplate
         */
        $orderManagementUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
        );

        $klarnaOrderId = $this->getKlarnaOrderId($payment);
        assert(is_string($klarnaOrderId));

        $refundUrl = str_replace('{order_id}', $klarnaOrderId, $orderManagementUrlTemplate) . '/refunds';

        try {
            $response = $this->client->request(
                'POST',
                $refundUrl,
                [
                    'headers' => [
                        'Authorization' => $this->getBasicAuth($paymentMethod),
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($payload),
                ],
            );

            $status = $response->getStatusCode();
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            if ($response !== null) {
                $data = json_decode($response->getBody()->getContents(), true);

                assert(is_array($data));

                /** @var string $errorCode */
                $errorCode = $data['error_code'] ?? 'REFUND_NOT_ALLOWED';
                if ($errorCode === StatusDO::ERROR_CODE_REFUND_NOT_ALLOWED) {
                    $orderData = $this->fetchOrderDataFromKlarna($order);
                    if ($this->isRefunded($orderData)) {
                        throw new AlreadyRefundedException('Already refunded');
                    }
                }
            }

            throw new ApiException('Refund was not created with Klarna');
        } catch (GuzzleException $exception) {
            throw new ApiException('Refund was not created with Klarna');
        }

        if ($status !== Response::HTTP_CREATED) {
            throw new ApiException('Refund was not created with Klarna');
        }
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

    public function getKlarnaOrderId(PaymentInterface $payment): ?string
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_id', $details)) {
            return null;
        }

        /** @var string $klarnaOrderId */
        $klarnaOrderId = $details['klarna_order_id'];

        return $klarnaOrderId;
    }

    private function addKlarnaOrderId(PaymentInterface $payment, string $reference, bool $replaceReference = false): void
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_id', $details) || $replaceReference) {
            $details['klarna_order_id'] = $reference;
        }

        $payment->setDetails($details);
    }

    public function getKlarnaOrderReference(PaymentInterface $payment): ?string
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_id', $details)) {
            return null;
        }

        /** @var string $klarnaOrderId */
        $klarnaOrderId = $details['klarna_order_id'];

        return $klarnaOrderId;
    }

    private function addKlarnaOrderReference(PaymentInterface $payment, string $reference, bool $replaceReference = false): void
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_reference', $details) || $replaceReference) {
            $details['klarna_order_reference'] = $reference;
        }

        $payment->setDetails($details);
    }

    private function hasDiff(array $requestData, array $klarnaData): bool
    {
        return $requestData['order_amount'] !== $klarnaData['order_amount'];
    }

    private function getBasicAuth(PaymentMethodInterface $method): string
    {
        return $this->basicAuthenticationRetriever->getBasicAuthentication($method);
    }

    private function isCaptured(array $data): bool
    {
        /** @var string $orderStatus */
        $orderStatus = $data['status'] ?? 'Not set';

        if ($orderStatus !== StatusDO::STATUS_CAPTURED) {
            return false;
        }

        return true;
    }

    private function isRefunded(array $orderData): bool
    {
        /** @var int $refundAmount */
        $refundAmount = $orderData['refunded_amount'];

        return $refundAmount > 0;
    }
}
