<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Payum\Core\Model\GatewayConfigInterface;
use SM\SMException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class CancelPayment implements CancelPaymentInterface
{
    public function __construct(
        private ClientInterface $client,
        private ParameterBagInterface $parameterBag,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
    ) {
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws SMException
     */
    public function cancel(PaymentInterface $payment): void
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        if (!$this->supportsPaymentMethod($paymentMethod)) {
            return;
        }

        $this->sendCancellationRequest($payment);
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendCancellationRequest(PaymentInterface $payment): int
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);

        $paymentDetails = $payment->getDetails();

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($paymentMethod);

        /** @var ?string $klarnaOrderId */
        $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? null;
        assert($klarnaOrderId !== null);

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         * @var string $orderManagementUrlTemplate
         */
        $orderManagementUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
        );

        $cancelUrl = $this->replacePlaceholder($klarnaOrderId, $orderManagementUrlTemplate) . '/cancel';

        try {
            $response = $this->client->request(
                'POST',
                $cancelUrl,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                ],
            );

            $status = $response->getStatusCode();
        } catch (GuzzleException $e) {
            throw new ApiException('Cancellation of Klarna payment was not successful: ' . $e->getMessage());
        }

        if ($status !== Response::HTTP_NO_CONTENT) {
            throw new ApiException('Cancellation of Klarna payment was not successful');
        }

        return $status;
    }

    protected function replacePlaceholder(string $replacement, string $string): string
    {
        $strStart = strpos($string, '{');
        $strEnd = strpos($string, '}');

        if ($strStart === false || $strEnd === false) {
            return $string;
        }

        return substr_replace($string, $replacement, $strStart, $strEnd - $strStart + 1);
    }

    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $paymentConfig = $paymentMethod->getGatewayConfig();
        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }
}
